<?php
require_once('tcpdf/tcpdf.php');

function genererFacturePDF($pdo, $idCommande) {
    error_log("🔄 GENERER FACTURE PDF - Début pour commande: " . $idCommande);
    
    try {
        // Récupérer les informations complètes de la commande
        $stmt = $pdo->prepare("
            SELECT 
                c.idCommande,
                c.dateCommande,
                c.montantTotal,
                c.fraisDePort,
                c.statut,
                a_fact.nom as client_nom,
                a_fact.prenom as client_prenom,
                cl.email as client_email,
                cl.telephone as client_telephone,
                a_liv.adresse as adresse_livraison,
                a_liv.codePostal as cp_livraison,
                a_liv.ville as ville_livraison,
                a_liv.pays as pays_livraison,
                a_fact.adresse as adresse_facturation,
                a_fact.codePostal as cp_facturation,
                a_fact.ville as ville_facturation,
                a_fact.pays as pays_facturation
            FROM Commande c
            JOIN Client cl ON c.idClient = cl.idClient
            JOIN Adresse a_liv ON c.idAdresseLivraison = a_liv.idAdresse
            LEFT JOIN Adresse a_fact ON c.idAdresseFacturation = a_fact.idAdresse
            WHERE c.idCommande = ?
        ");
        $stmt->execute([$idCommande]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$commande) {
            throw new Exception("Commande non trouvée: " . $idCommande);
        }
        
        // Récupérer les articles de la commande
        $stmt = $pdo->prepare("
            SELECT 
                lc.quantite,
                lc.prixUnitaire,
                (lc.quantite * lc.prixUnitaire) as total_ligne,
                o.nom as produit_nom,
                o.description
            FROM LigneCommande lc
            JOIN Origami o ON lc.idOrigami = o.idOrigami
            WHERE lc.idCommande = ?
        ");
        $stmt->execute([$idCommande]);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Vérifier que TCPDF est bien inclus
        if (!class_exists('TCPDF')) {
            throw new Exception("TCPDF non chargé");
        }
        
        // Créer un nouveau PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Information du document
        $pdf->SetCreator('Youki and Co');
        $pdf->SetAuthor('Youki and Co');
        $pdf->SetTitle('Facture #' . $idCommande);
        $pdf->SetSubject('Facture');
        
        // Marges calibrées
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        // Supprimer header/footer par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Police par défaut
        $pdf->SetFont('helvetica', '', 10);
        
        // Nom complet du client
        $nomCompletClient = ($commande['client_nom'] ?? '') . ' ' . ($commande['client_prenom'] ?? '');
        if (empty(trim($nomCompletClient))) {
            $nomCompletClient = 'Client';
        }
        
        // Calcul des totaux
        $sousTotal = 0;
        foreach ($articles as $article) {
            $sousTotal += $article['total_ligne'];
        }
        $totalGeneral = $sousTotal + ($commande['fraisDePort'] ?? 0);
        
        // HTML avec colonnes calibrées
        $html = '
        <style>
            body {
                font-family: helvetica;
                font-size: 10pt;
                line-height: 1.4;
                color: #333;
                margin: 0;
                padding: 0;
            }
            .header {
                margin-bottom: 35px;
                border-bottom: 1px solid #ddd;
                padding-bottom: 15px;
            }
            .company {
                font-size: 20pt;
                font-weight: 300;
                letter-spacing: 2px;
                color: #333;
                margin-bottom: 5px;
            }
            .company-details {
                font-size: 8pt;
                color: #666;
            }
            .invoice-title {
                font-size: 18pt;
                font-weight: 300;
                color: #333;
                margin-bottom: 5px;
            }
            .invoice-number {
                font-size: 10pt;
                color: #666;
            }
            .client-section {
                margin-bottom: 30px;
            }
            .client-box {
                margin-bottom: 15px;
            }
            .client-label {
                font-size: 8pt;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #999;
                margin-bottom: 6px;
            }
            .client-name {
                font-size: 11pt;
                font-weight: 500;
                margin-bottom: 4px;
            }
            .client-info {
                font-size: 9pt;
                color: #666;
                line-height: 1.4;
            }
            .address-table {
                width: 100%;
                margin: 15px 0;
                border-collapse: collapse;
            }
            .address-table td {
                width: 50%;
                vertical-align: top;
                padding: 5px 0;
            }
            .address-label-small {
                font-size: 8pt;
                font-weight: bold;
                text-transform: uppercase;
                color: #999;
                margin-bottom: 5px;
            }
            .address-content {
                font-size: 9pt;
                color: #666;
                line-height: 1.4;
            }
            .products-table {
                width: 100%;
                border-collapse: collapse;
                margin: 25px 0;
            }
            .products-table th {
                background: #f8f8f8;
                padding: 10px 6px;
                text-align: left;
                border-bottom: 1px solid #e0e0e0;
                font-size: 9pt;
                font-weight: 600;
                color: #555;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .products-table td {
                padding: 10px 6px;
                border-bottom: 1px solid #f0f0f0;
                font-size: 9pt;
                color: #444;
            }
            .product-col { width: 45%; }
            .price-col { width: 15%; text-align: right; }
            .qty-col { width: 10%; text-align: center; }
            .total-col { width: 15%; text-align: right; }
            .totals {
                text-align: right;
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #e0e0e0;
            }
            .total-line {
                padding: 5px 0;
                font-size: 9pt;
                color: #666;
            }
            .grand-total {
                font-size: 11pt;
                font-weight: bold;
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px solid #ccc;
                color: #333;
            }
            .footer {
                margin-top: 40px;
                padding-top: 15px;
                border-top: 1px solid #eee;
                text-align: center;
                font-size: 7pt;
                color: #999;
            }
            .legal-mention {
                margin-top: 20px;
                padding: 10px;
                background: #fafafa;
                font-size: 7pt;
                color: #666;
                text-align: center;
            }
            .clearfix { clear: both; }
        </style>
        
        <!-- En-tête -->
        <div class="header">
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                <tr>
                    <td width="60%" style="vertical-align: top;">
                        <div class="company">YOUKI & CO</div>
                        <div class="company-details">Créations artisanales japonaises</div>
                    </td>
                    <td width="40%" style="text-align: right; vertical-align: top;">
                        <div class="invoice-title">FACTURE</div>
                        <div class="invoice-number">N° ' . $idCommande . '</div>
                        <div class="invoice-number">' . date('d/m/Y', strtotime($commande['dateCommande'])) . '</div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Informations client -->
        <div class="client-section">
            <div class="client-box">
                <div class="client-label">CLIENT</div>
                <div class="client-name">' . htmlspecialchars($nomCompletClient) . '</div>
                <div class="client-info">' . htmlspecialchars($commande['client_email'] ?? '') . '</div>';
        
        if (!empty($commande['client_telephone'])) {
            $html .= '<div class="client-info">' . htmlspecialchars($commande['client_telephone']) . '</div>';
        }
        
        $html .= '
            </div>
            
            <table class="address-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <div class="address-label-small">ADRESSE DE LIVRAISON</div>
                        <div class="address-content">' . htmlspecialchars($nomCompletClient) . '<br>
                        ' . htmlspecialchars($commande['adresse_livraison'] ?? '') . '<br>
                        ' . htmlspecialchars(($commande['cp_livraison'] ?? '') . ' ' . ($commande['ville_livraison'] ?? '')) . '<br>
                        ' . htmlspecialchars($commande['pays_livraison'] ?? '') . '</div>
                    </td>
                    <td>
                        <div class="address-label-small">ADRESSE DE FACTURATION</div>
                        <div class="address-content">' . htmlspecialchars($nomCompletClient) . '<br>
                        ' . htmlspecialchars($commande['adresse_facturation'] ?? '') . '<br>
                        ' . htmlspecialchars(($commande['cp_facturation'] ?? '') . ' ' . ($commande['ville_facturation'] ?? '')) . '<br>
                        ' . htmlspecialchars($commande['pays_facturation'] ?? '') . '</div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Tableau des produits avec colonnes calibrées -->
        <table class="products-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th class="product-col">DÉSIGNATION</th>
                    <th class="price-col">PRIX UNIT.</th>
                    <th class="qty-col">QTÉ</th>
                    <th class="total-col">TOTAL</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($articles as $article) {
            $html .= '
                <tr>
                    <td class="product-col">' . htmlspecialchars($article['produit_nom']) . '</td>
                    <td class="price-col">' . number_format($article['prixUnitaire'], 2, ',', ' ') . ' €</td>
                    <td class="qty-col">' . $article['quantite'] . '</td>
                    <td class="total-col">' . number_format($article['total_ligne'], 2, ',', ' ') . ' €</td>
                </tr>';
        }
        
        $html .= '
            </tbody>
        </table>
        
        <!-- Totaux -->
        <div class="totals">
            <div class="total-line">Sous-total : ' . number_format($sousTotal, 2, ',', ' ') . ' €</div>
            <div class="total-line">Frais de port : ' . number_format(($commande['fraisDePort'] ?? 0), 2, ',', ' ') . ' €</div>
            <div class="grand-total">TOTAL : ' . number_format($totalGeneral, 2, ',', ' ') . ' €</div>
        </div>
        
        <!-- Mentions légales -->
        <div class="legal-mention">
            Exonération de TVA - Article 293 B du Code Général des Impôts
        </div>
        
        <!-- Pied de page -->
        <div class="footer">
            Youki & Co - SIRET 123 456 789 00012<br>
            contact@youkiandco.fr<br>
            Facture générée le ' . date('d/m/Y à H:i') . '
        </div>';
        
        // Écrire le contenu HTML
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Créer le répertoire factures s'il n'existe pas
        $factureDir = __DIR__ . '/factures';
        if (!is_dir($factureDir)) {
            mkdir($factureDir, 0755, true);
        }
        
        // Nom du fichier
        $filename = 'facture_' . $idCommande . '_' . date('Ymd') . '.pdf';
        $filepath = $factureDir . '/' . $filename;
        
        // Sauvegarder le PDF
        $pdf->Output($filepath, 'F');
        
        if (file_exists($filepath)) {
            error_log("✅ PDF créé avec succès: " . $filepath);
            return $filepath;
        } else {
            throw new Exception("Le fichier PDF n'a pas été créé");
        }
        
    } catch (Exception $e) {
        error_log("❌ ERREUR génération facture PDF: " . $e->getMessage());
        return false;
    }
}

function afficherFacturePDFDirect($pdo, $idCommande) {
    $filepath = genererFacturePDF($pdo, $idCommande);
    
    if ($filepath && file_exists($filepath)) {
        $filename = 'facture_' . $idCommande . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        header('Content-Type: text/html');
        echo "Erreur: Impossible de générer la facture PDF";
        exit;
    }
}

function envoyerFactureParEmail($emailClient, $cheminFichier, $idCommande) {
    try {
        error_log("📧 Envoi facture par email à: " . $emailClient);
        
        $filename = basename($cheminFichier);
        
        $to = $emailClient;
        $subject = "Votre facture Youki and Co - Commande #" . $idCommande;
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 25px; }
                .logo { font-size: 24px; font-weight: 300; letter-spacing: 2px; color: #333; }
                .content { margin-bottom: 30px; }
                .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>YOUKI & CO</div>
                </div>
                <div class='content'>
                    <p>Bonjour,</p>
                    <p>Nous vous remercions pour votre commande n°<strong>" . $idCommande . "</strong>.</p>
                    <p>Votre facture est disponible en pièce jointe au format PDF.</p>
                    <p>Pour toute question, n'hésitez pas à nous contacter.</p>
                    <p>Cordialement,<br>L'équipe Youki & Co</p>
                </div>
                <div class='footer'>
                    Youki & Co - Créations artisanales japonaises<br>
                    contact@youkiandco.fr
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Youki and Co <noreply@youkiandco.fr>" . "\r\n";
        
        $boundary = md5(time());
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        
        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $message . "\r\n";
        
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: application/pdf; name=\"$filename\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
        $body .= chunk_split(base64_encode(file_get_contents($cheminFichier))) . "\r\n";
        $body .= "--$boundary--";
        
        $success = mail($to, $subject, $body, $headers);
        
        if ($success) {
            error_log("✅ Email envoyé avec succès à: " . $emailClient);
            return true;
        } else {
            error_log("❌ Échec envoi email à: " . $emailClient);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("❌ ERREUR envoi email facture: " . $e->getMessage());
        return false;
    }
}

function traiterPaiementReussi($pdo, $idCommande) {
    error_log("💰 TRAITEMENT PAIEMENT RÉUSSI - Commande: " . $idCommande);
    
    $stmt = $pdo->prepare("UPDATE Commande SET statut = 'payee', datePaiement = NOW() WHERE idCommande = ?");
    $stmt->execute([$idCommande]);
    
    $resultatFacture = genererFacturePDF($pdo, $idCommande);
    
    if ($resultatFacture) {
        error_log("✅ Paiement traité avec succès - Facture générée");
        return true;
    } else {
        error_log("⚠️ Paiement traité mais problème avec la facture");
        return false;
    }
}
?>
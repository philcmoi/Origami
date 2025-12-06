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
            WHERE cl.type = 'permanent' and c.idCommande = ?
        ");
        $stmt->execute([$idCommande]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("=== DEBUG COMPLET DE LA REQUÊTE ===");
        error_log("ID Commande: " . $idCommande);
        error_log("Nombre de colonnes retournées: " . count($commande));

        // Afficher toutes les colonnes
        foreach ($commande as $key => $value) {
        $value_display = is_null($value) ? 'NULL' : "'$value'";
        error_log("Colonne '$key' = $value_display");
        }

    // Vérifier spécifiquement
    error_log("--- VÉRIFICATION DES COLONNES CLIENT ---");
    $client_columns = ['client_nom', 'client_prenom', 'client_email'];
    foreach ($client_columns as $col) {
        if (isset($commande[$col])) {
            error_log("✅ '$col' = '" . $commande[$col] . "'");
        } else {
        error_log("❌ '$col' N'EXISTE PAS dans le résultat");
          }
    }

    // Vérifier si les colonnes existent sans alias
    error_log("--- VÉRIFICATION SANS ALIAS ---");
    $raw_columns = ['nom', 'prenom', 'email'];
    foreach ($raw_columns as $col) {
        if (isset($commande[$col])) {
        error_log("⚠️  Colonne '$col' existe sans alias = '" . $commande[$col] . "'");
        }
    }

        if (!$commande) {
            throw new Exception("Commande non trouvée: " . $idCommande);
        }
        
        // DEBUG: Afficher ce que contient $commande
        error_log("🔍 DEBUG Commande #" . $idCommande . " - Données récupérées:");
        error_log("🔍 client_nom: " . ($commande['client_nom'] ?? 'NULL'));
        error_log("🔍 client_prenom: " . ($commande['client_prenom'] ?? 'NULL'));
        error_log("🔍 client_email: " . ($commande['client_email'] ?? 'NULL'));
        error_log("🔍 Toutes les clés: " . implode(', ', array_keys($commande)));
        
        // Vérifier si les colonnes existent
        if (!isset($commande['client_nom']) || !isset($commande['client_prenom'])) {
            error_log("❌ ERREUR: Colonnes client_nom ou client_prenom non trouvées");
            error_log("❌ Colonnes disponibles: " . print_r(array_keys($commande), true));
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
        
        // Créer un nouveau PDF avec une police de base
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Information du document
        $pdf->SetCreator('Youki and Co');
        $pdf->SetAuthor('Youki and Co');
        $pdf->SetTitle('Facture - ' . ($commande['client_prenom'] ?? '') . ' ' . ($commande['client_nom'] ?? ''));
        $pdf->SetSubject('Facture');
        
        // Marges simplifiées
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(TRUE, 20);
        
        // Supprimer header/footer par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Nom complet du client (avec vérification)
        $nomCompletClient = ($commande['client_prenom'] ?? '') . ' ' . ($commande['client_nom'] ?? '');
        if (empty(trim($nomCompletClient))) {
            $nomCompletClient = 'Client non spécifié';
            error_log("⚠️ Nom client vide pour commande: " . $idCommande);
        }
        
        // Contenu HTML
        $html = '
        <style>
            .header-box { 
                background-color: #f8f9fa; 
                border: 2px solid #d40000; 
                padding: 15px; 
                margin-bottom: 20px; 
                border-radius: 5px;
            }
            .facture-title { 
                text-align: center; 
                color: #d40000; 
                font-size: 24px; 
                font-weight: bold; 
                margin-bottom: 10px;
            }
            .section-title {
                background-color: #d40000;
                color: white;
                padding: 8px 12px;
                margin: 15px 0 10px 0;
                font-weight: bold;
                border-radius: 3px;
            }
            .info-box {
                background-color: #f8f9fa;
                border: 1px solid #e9ecef;
                padding: 10px;
                margin: 10px 0;
                border-radius: 5px;
            }
            .total-box {
                background-color: #e9ffe9;
                border: 2px solid #28a745;
                padding: 15px;
                margin-top: 20px;
                border-radius: 5px;
            }
        </style>
        
        <!-- En-tête de la facture -->
        <div class="header-box">
            <table width="100%">
                <tr>
                    <td width="50%" style="vertical-align: top;">
                        <div style="color: #d40000; font-size: 20px; font-weight: bold;">Youki and Co</div>
                        <div style="font-size: 12px; color: #666;">Créations artisanales japonaises</div>
                        <div style="font-size: 11px; margin-top: 5px;">
                            SIRET: 123 456 789 00012<br>
                            Exonération de TVA, art. 293 B du CGI
                        </div>
                    </td>
                    <td width="50%" style="text-align: right; vertical-align: top;">
                        <div style="font-size: 22px; font-weight: bold; color: #d40000;">FACTURE</div>
                        <div style="font-size: 14px; margin-top: 5px;">
                            <strong>N° Facture:</strong> ' . $idCommande . '<br>
                            <strong>Date:</strong> ' . date('d/m/Y') . '<br>
                            <strong>Statut:</strong> ' . htmlspecialchars($commande['statut'] ?? '') . '
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- NOM ET PRÉNOM (remplace CLIENT) -->
        <div style="margin-bottom: 20px;">
            <div style="font-weight: bold; margin-bottom: 10px; color: #d40000;">NOM ET PRÉNOM</div>
            <div><strong>' . htmlspecialchars($nomCompletClient) . '</strong></div>
            <div>' . htmlspecialchars($commande['client_email'] ?? '') . '</div>';
        
        if (!empty($commande['client_telephone'])) {
            $html .= '<div>' . htmlspecialchars($commande['client_telephone']) . '</div>';
        }
        
        $html .= '
        </div>
        
        <!-- INFORMATIONS (remplace INFORMATIONS CLIENT) -->
        <div class="section-title">INFORMATIONS</div>
        
        <table width="100%" cellpadding="5">
            <tr>
                <td width="50%" style="vertical-align: top;">
                    <div class="info-box">
                        <strong>Nom et prénom:</strong><br>
                        ' . htmlspecialchars($nomCompletClient) . '<br><br>
                        <strong>Contact:</strong><br>
                        ' . htmlspecialchars($commande['client_email'] ?? '') . '<br>';
        
        if (!empty($commande['client_telephone'])) {
            $html .= '' . htmlspecialchars($commande['client_telephone']) . '<br>';
        }
        
        $html .= '
                    </div>
                </td>
                <td width="50%" style="vertical-align: top;">
                    <div class="info-box">
                        <strong>Adresse de facturation:</strong><br>
                        ' . htmlspecialchars($commande['adresse_facturation'] ?? '') . '<br>
                        ' . htmlspecialchars(($commande['cp_facturation'] ?? '') . ' ' . ($commande['ville_facturation'] ?? '')) . '<br>
                        ' . htmlspecialchars($commande['pays_facturation'] ?? '') . '
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Détails de la commande -->
        <div class="section-title">DÉTAILS DE LA COMMANDE</div>
        
        <table border="1" cellpadding="8" style="border-collapse: collapse; width:100%; font-size: 11px;">
            <thead>
                <tr style="background-color:#d40000; color:white;">
                    <th width="45%"><strong>PRODUIT</strong></th>
                    <th width="15%"><strong>QUANTITÉ</strong></th>
                    <th width="20%"><strong>PRIX UNITAIRE</strong></th>
                    <th width="20%"><strong>TOTAL</strong></th>
                </tr>
            </thead>
            <tbody>';
        
        $sousTotal = 0;
        foreach ($articles as $article) {
            $html .= '
                <tr>
                    <td><strong>' . htmlspecialchars($article['produit_nom']) . '</strong><br>
                        <small style="color:#666;">' . htmlspecialchars(substr($article['description'], 0, 80)) . '...</small>
                    </td>
                    <td style="text-align:center;">' . $article['quantite'] . '</td>
                    <td style="text-align:right;">' . number_format($article['prixUnitaire'], 2, ',', ' ') . ' €</td>
                    <td style="text-align:right;"><strong>' . number_format($article['total_ligne'], 2, ',', ' ') . ' €</strong></td>
                </tr>';
            $sousTotal += $article['total_ligne'];
        }
        
        $totalGeneral = $sousTotal + ($commande['fraisDePort'] ?? 0);
        
        $html .= '
            </tbody>
        </table>
        
        <!-- Totaux -->
        <div class="total-box">
            <table width="100%">
                <tr>
                    <td width="70%"></td>
                    <td width="30%">
                        <table width="100%" style="font-size: 13px;">
                            <tr>
                                <td>Sous-total produits:</td>
                                <td style="text-align:right; padding-left: 10px;">' . number_format($sousTotal, 2, ',', ' ') . ' €</td>
                            </tr>
                            <tr>
                                <td>Frais de port:</td>
                                <td style="text-align:right; padding-left: 10px;">' . number_format(($commande['fraisDePort'] ?? 0), 2, ',', ' ') . ' €</td>
                            </tr>
                            <tr style="border-top: 2px solid #28a745; font-size: 15px;">
                                <td><strong>TOTAL TTC:</strong></td>
                                <td style="text-align:right; padding-left: 10px;"><strong>' . number_format($totalGeneral, 2, ',', ' ') . ' €</strong></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Mentions légales -->
        <div style="margin-top: 30px; padding: 15px; background-color: #f0f0f0; border-radius: 5px; font-size: 10px; color: #666;">
            <div style="text-align: center;">
                <strong>MENTIONS LÉGALES</strong>
            </div>
            <div style="margin-top: 10px;">
                <table width="100%">
                    <tr>
                        <td width="60%" style="vertical-align: top;">
                            <strong>Facture établie au nom de :</strong><br>
                            ' . htmlspecialchars($nomCompletClient) . '<br>
                            ' . htmlspecialchars($commande['adresse_facturation'] ?? '') . '<br>
                            ' . htmlspecialchars(($commande['cp_facturation'] ?? '') . ' ' . ($commande['ville_facturation'] ?? '')) . '<br>
                            ' . htmlspecialchars($commande['pays_facturation'] ?? '') . '
                        </td>
                        <td width="40%" style="vertical-align: top; text-align: right;">
                            <strong>Youki and Co</strong><br>
                            Créations artisanales japonaises<br>
                            SIRET: 123 456 789 00012<br>
                            Exonération de TVA, art. 293 B du CGI
                        </td>
                    </tr>
                </table>
            </div>
            <div style="text-align: center; margin-top: 15px; font-size: 9px;">
                Facture générée le ' . date('d/m/Y à H:i') . ' - Cette facture fait foi entre les parties
            </div>
        </div>';
        
        // Écrire le contenu HTML
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Créer le répertoire factures s'il n'existe pas
        $factureDir = __DIR__ . '/factures';
        if (!is_dir($factureDir)) {
            mkdir($factureDir, 0755, true);
        }
        
        // Nom du fichier avec le nom du client
        $filename = 'Facture_' . $idCommande . '_' . ($commande['client_prenom'] ?? '') . '_' . ($commande['client_nom'] ?? '') . '.pdf';
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename); // Nettoyer le nom de fichier
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
        // Récupérer les infos client pour le nom du fichier
        $stmt = $pdo->prepare("
            SELECT cl.prenom, cl.nom 
            FROM Commande c 
            JOIN Client cl ON c.idClient = cl.idClient 
            WHERE c.idCommande = ?
        ");
        $stmt->execute([$idCommande]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $filename = 'Facture_';
        if ($client) {
            $filename .= $idCommande . '_' . $client['prenom'] . '_' . $client['nom'] . '.pdf';
            $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        } else {
            $filename .= $idCommande . '.pdf';
        }
        
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
        
        // Récupérer le nom du client depuis le chemin du fichier
        $filename = basename($cheminFichier);
        
        // Configuration de l'email
        $to = $emailClient;
        $subject = "Votre facture Youki and Co - Commande #" . $idCommande;
        $message = "
        <html>
        <head>
            <title>Votre facture Youki and Co</title>
        </head>
        <body>
            <h2>Facture établie à votre nom</h2>
            <p>Bonjour,</p>
            <p>Votre commande #" . $idCommande . " a été traitée avec succès.</p>
            <p><strong>Votre facture personnelle a été établie à votre nom.</strong></p>
            <p>Vous trouverez votre facture détaillée en pièce jointe.</p>
            <p>Nous vous remercions pour votre confiance.</p>
            <br>
            <p>Cordialement,<br>L'équipe Youki and Co</p>
        </body>
        </html>
        ";
        
        // Headers pour email HTML
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Youki and Co <noreply@YoukiandCo.com>" . "\r\n";
        
        // Boundary pour les pièces jointes
        $boundary = md5(time());
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        
        // Corps du message avec pièce jointe
        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $message . "\r\n";
        
        // Pièce jointe
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: application/pdf; name=\"$filename\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
        $body .= chunk_split(base64_encode(file_get_contents($cheminFichier))) . "\r\n";
        $body .= "--$boundary--";
        
        // Envoi de l'email
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
    
    // 1. Mettre à jour le statut de la commande
    $stmt = $pdo->prepare("UPDATE Commande SET statut = 'payee', datePaiement = NOW() WHERE idCommande = ?");
    $stmt->execute([$idCommande]);
    
    // 2. Générer et envoyer la facture
    $resultatFacture = genererFacturePDF($pdo, $idCommande);
    
    if ($resultatFacture) {
        error_log("✅ Paiement traité avec succès - Facture générée/envoyée");
        return true;
    } else {
        error_log("⚠️ Paiement traité mais problème avec la facture");
        return false;
    }
}
?>
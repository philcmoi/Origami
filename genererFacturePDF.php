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
                cl.nom as client_nom,
                cl.prenom as client_prenom,
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
        
        // Créer un nouveau PDF avec une police de base
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Information du document
        $pdf->SetCreator('Youki and Co');
        $pdf->SetAuthor('Youki and Co');
        $pdf->SetTitle('Facture #' . $idCommande);
        $pdf->SetSubject('Facture');
        
        // Marges simplifiées
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Supprimer header/footer par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Contenu HTML avec ADRESSE DE FACTURATION
        $html = '
        <h1 style="text-align:center; color:#d40000;">FACTURE</h1>
        <h2 style="text-align:center;">Youki and Co</h2>
        <hr>
        
        <table width="100%">
            <tr>
                <td width="50%">
                    <strong>Facture N°:</strong> ' . $idCommande . '<br>
                    <strong>Date:</strong> ' . date('d/m/Y') . '<br>
                    <strong>Statut:</strong> ' . htmlspecialchars($commande['statut']) . '
                </td>
                <td width="50%" style="text-align:right;">
                    <strong>Youki and Co</strong><br>
                    Créations artisanales japonaises<br>
                    SIRET: 123 456 789 00012
                </td>
            </tr>
        </table>
        
        <br>
        
        <table width="100%">
            <tr>
                <td width="50%">
                    <strong>CLIENT</strong><br>
                    ' . htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) . '<br>
                    Email: ' . htmlspecialchars($commande['client_email']) . '
                </td>
                <td width="50%">
                    <strong>FACTURATION</strong><br>
                    ' . htmlspecialchars($commande['adresse_facturation']) . '<br>
                    ' . htmlspecialchars($commande['cp_facturation'] . ' ' . $commande['ville_facturation']) . '<br>
                    ' . htmlspecialchars($commande['pays_facturation']) . '
                </td>
            </tr>
        </table>
        
        <br>
        
        <h3>DÉTAIL DE LA COMMANDE</h3>
        <table border="1" cellpadding="5" style="border-collapse: collapse; width:100%;">
            <thead>
                <tr style="background-color:#f0f0f0;">
                    <th width="50%">Produit</th>
                    <th width="15%">Quantité</th>
                    <th width="15%">Prix Unitaire</th>
                    <th width="20%">Total</th>
                </tr>
            </thead>
            <tbody>';
        
        $sousTotal = 0;
        foreach ($articles as $article) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($article['produit_nom']) . '</td>
                    <td>' . $article['quantite'] . '</td>
                    <td>' . number_format($article['prixUnitaire'], 2, ',', ' ') . ' €</td>
                    <td>' . number_format($article['total_ligne'], 2, ',', ' ') . ' €</td>
                </tr>';
            $sousTotal += $article['total_ligne'];
        }
        
        $totalGeneral = $sousTotal + $commande['fraisDePort'];
        
        $html .= '
            </tbody>
        </table>
        
        <br>
        
        <table width="100%">
            <tr>
                <td width="70%"></td>
                <td width="30%">
                    <table width="100%">
                        <tr>
                            <td>Sous-total:</td>
                            <td style="text-align:right;">' . number_format($sousTotal, 2, ',', ' ') . ' €</td>
                        </tr>
                        <tr>
                            <td>Frais de port:</td>
                            <td style="text-align:right;">' . number_format($commande['fraisDePort'], 2, ',', ' ') . ' €</td>
                        </tr>
                        <tr style="border-top:1px solid #000;">
                            <td><strong>Total:</strong></td>
                            <td style="text-align:right;"><strong>' . number_format($totalGeneral, 2, ',', ' ') . ' €</strong></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        
        <br>
        
        <div style="text-align:center; color:#666; font-size:10px;">
            <p>Youki and Co - Créations artisanales japonaises</p>
            <p>Facture générée le ' . date('d/m/Y à H:i') . '</p>
        </div>';
        
        // Écrire le contenu HTML
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Créer le répertoire factures s'il n'existe pas
        $factureDir = __DIR__ . '/factures';
        if (!is_dir($factureDir)) {
            mkdir($factureDir, 0755, true);
        }
        
        // Nom du fichier
        $filename = 'facture_' . $idCommande . '.pdf';
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
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="facture_' . $idCommande . '.pdf"');
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
        
        // Configuration de l'email
        $to = $emailClient;
        $subject = "Votre facture Youki and Co - Commande #" . $idCommande;
        $message = "
        <html>
        <head>
            <title>Votre facture Youki and Go</title>
        </head>
        <body>
            <h2>Merci pour votre commande !</h2>
            <p>Votre commande #" . $idCommande . " a été traitée avec succès.</p>
            <p>Vous trouverez votre facture en pièce jointe.</p>
            <p>Nous vous remercions pour votre confiance.</p>
            <br>
            <p>Cordialement,<br>L'équipe Youki and Go</p>
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
        $body .= "Content-Type: application/pdf; name=\"facture_$idCommande.pdf\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"facture_$idCommande.pdf\"\r\n\r\n";
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
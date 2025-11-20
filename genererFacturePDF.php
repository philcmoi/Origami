<?php
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
                cl.nom as client_nom,
                cl.prenom as client_prenom,
                cl.email as client_email,
                a_liv.adresse as adresse_livraison,
                a_liv.codePostal as cp_livraison,
                a_liv.ville as ville_livraison,
                a_liv.pays as pays_livraison
            FROM Commande c
            JOIN Client cl ON c.idClient = cl.idClient
            JOIN Adresse a_liv ON c.idAdresseLivraison = a_liv.idAdresse
            WHERE c.idCommande = ?
        ");
        $stmt->execute([$idCommande]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("📊 Données commande récupérées: " . ($commande ? 'OUI' : 'NON'));
        
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
        
        error_log("📦 Articles commande récupérés: " . count($articles));
        
        // Vérifier que TCPDF est bien inclus
        if (!class_exists('TCPDF')) {
            throw new Exception("TCPDF non chargé");
        }
        
        // Créer un nouveau PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Information du document
        $pdf->SetCreator('Youki and Co');
        $pdf->SetAuthor('Youki and Co');
        $pdf->SetTitle('Facture #' . $idCommande);
        $pdf->SetSubject('Facture');
        
        // Marges
        $pdf->SetMargins(15, 25, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Contenu de la facture détaillé
        $html = '
        <style>
            .header { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px; }
            .section { margin-bottom: 15px; }
            .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .table th { background-color: #f8f9fa; padding: 8px; text-align: left; border: 1px solid #dee2e6; }
            .table td { padding: 8px; border: 1px solid #dee2e6; }
            .total { font-weight: bold; font-size: 16px; color: #2c3e50; }
            .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #dee2e6; color: #6c757d; font-size: 12px; }
        </style>
        
        <div class="header">
            <h1>FACTURE #' . $idCommande . '</h1>
            <h2>Youki and Co</h2>
            <p>Date de facturation: ' . date('d/m/Y') . '</p>
        </div>
        
        <div class="section">
            <h3>Informations Client</h3>
            <p><strong>' . htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) . '</strong></p>
            <p>Email: ' . htmlspecialchars($commande['client_email']) . '</p>
        </div>
        
        <div class="section">
            <h3>Adresse de Livraison</h3>
            <p>' . htmlspecialchars($commande['adresse_livraison']) . '</p>
            <p>' . htmlspecialchars($commande['cp_livraison'] . ' ' . $commande['ville_livraison']) . '</p>
            <p>' . htmlspecialchars($commande['pays_livraison']) . '</p>
        </div>
        
        <h3>Détail de la Commande</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Quantité</th>
                    <th>Prix Unitaire</th>
                    <th>Total</th>
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
        
        $html .= '
            </tbody>
        </table>
        
        <div style="text-align: right;">
            <p>Sous-total: ' . number_format($sousTotal, 2, ',', ' ') . ' €</p>
            <p>Frais de port: ' . number_format($commande['fraisDePort'], 2, ',', ' ') . ' €</p>
            <p class="total">Total TTC: ' . number_format($commande['montantTotal'], 2, ',', ' ') . ' €</p>
        </div>
        
        <div class="footer">
            <p>Merci pour votre confiance !</p>
            <p>Youki and Go - Contact: contact@YoukiandGo.com</p>
        </div>
        ';
        
        // Écrire le contenu HTML
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Sauvegarder le PDF dans un fichier temporaire
        $filename = 'facture_' . $idCommande . '_' . date('YmdHis') . '.pdf';
        $filepath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        
        error_log("💾 Sauvegarde PDF: " . $filepath);
        
        $result = $pdf->Output($filepath, 'F');
        
        if (file_exists($filepath)) {
            $size = filesize($filepath);
            error_log("✅ PDF créé avec succès: " . $filepath . " (" . $size . " bytes)");
            
            // ENVOYER LA FACTURE PAR EMAIL
            $emailEnvoye = envoyerFactureParEmail($commande['client_email'], $filepath, $idCommande);
            
            if ($emailEnvoye) {
                error_log("✅ Facture envoyée par email à: " . $commande['client_email']);
                // Supprimer le fichier temporaire après envoi
                unlink($filepath);
                return true;
            } else {
                error_log("❌ Échec envoi email, PDF conservé: " . $filepath);
                return $filepath; // Retourne le chemin pour gestion manuelle
            }
            
        } else {
            error_log("❌ PDF non créé: " . $filepath);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("❌ ERREUR génération facture PDF: " . $e->getMessage());
        return false;
    }
}

// NOUVELLE FONCTION POUR ENVOYER LA FACTURE PAR EMAIL
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

// FONCTION À APPELER APRÈS PAIEMENT RÉUSSI
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
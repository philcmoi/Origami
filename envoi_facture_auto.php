<?php
// envoi_facture_auto.php
function envoyerFactureAuto($pdo, $idCommande) {
    require_once 'genererFacturePDF.php';
    require_once 'PHPMailer/src/Exception.php';
    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';
    
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    
    // Vérifier que la commande est payée
    $stmt = $pdo->prepare("SELECT statut FROM Commande WHERE idCommande = ?");
    $stmt->execute([$idCommande]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande || $commande['statut'] !== 'payee') {
        error_log("❌ Commande non payée, impossible d'envoyer la facture");
        return false;
    }
    
    // Générer la facture PDF
    $cheminFacture = genererFacturePDF($pdo, $idCommande);
    
    if (!$cheminFacture || !file_exists($cheminFacture)) {
        error_log("❌ Erreur génération PDF pour commande: " . $idCommande);
        return false;
    }
    
    // Récupérer les infos client
    $stmt = $pdo->prepare("
        SELECT cl.email, cl.prenom, cl.nom, c.montantTotal 
        FROM Commande c 
        JOIN Client cl ON c.idClient = cl.idClient 
        WHERE c.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        error_log("❌ Client non trouvé pour commande: " . $idCommande);
        return false;
    }
    
    // Email sobre avec pièce jointe
    $sujet = "Votre facture Youki & Co - Commande #" . $idCommande;
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 4px; overflow: hidden; }
            .header { background: #333; padding: 30px; text-align: center; }
            .header h1 { margin: 0; color: white; font-weight: 300; font-size: 28px; letter-spacing: 2px; }
            .content { padding: 40px; }
            h2 { color: #333; font-weight: 400; margin-top: 0; }
            p { color: #666; line-height: 1.6; margin-bottom: 20px; }
            .info-box { background: #fafafa; padding: 25px; margin: 25px 0; border: 1px solid #f0f0f0; }
            .info-box p { margin: 10px 0; }
            .info-box strong { color: #333; }
            .footer { background: #fafafa; padding: 20px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #f0f0f0; }
            .mention { background: #f9f9f9; padding: 15px; margin: 25px 0; color: #666; font-size: 13px; text-align: center; border: 1px solid #f0f0f0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>YOUKI & CO</h1>
            </div>
            
            <div class='content'>
                <h2>Bonjour " . htmlspecialchars($client['prenom'] . ' ' . $client['nom']) . ",</h2>
                
                <p>Merci pour votre commande !</p>
                
                <div class='info-box'>
                    <p><strong>Commande :</strong> #" . $idCommande . "</p>
                    <p><strong>Montant total :</strong> " . number_format($client['montantTotal'], 2, ',', ' ') . " €</p>
                    <p><strong>Date :</strong> " . date('d/m/Y') . "</p>
                </div>
                
                <p>Votre facture détaillée est jointe à cet email au format PDF.</p>
                
                <div class='mention'>
                    Exonération de TVA - Art. 293 B du CGI
                </div>
                
                <p>Nous vous remercions pour votre confiance.</p>
                
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
    
    // Envoyer l'email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->setFrom(SMTP_FROM_EMAIL, 'Youki & Co');
        $mail->addAddress($client['email']);
        $mail->addReplyTo(SMTP_FROM_EMAIL, 'Youki & Co');
        
        // Pièce jointe
        $mail->addAttachment($cheminFacture, 'facture_' . $idCommande . '.pdf');
        
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        if ($mail->send()) {
            error_log("✅ Facture envoyée avec succès à: " . $client['email']);
            return true;
        } else {
            error_log("❌ Erreur envoi: " . $mail->ErrorInfo);
            return false;
        }
    } catch (Exception $e) {
        error_log("❌ Erreur PHPMailer: " . $e->getMessage());
        return false;
    }
}
?>
<?php
// envoi_facture_auto.php
function envoyerFactureAuto($pdo, $idCommande) {
    require_once 'genererFacturePDF.php';
    require_once 'PHPMailer/src/Exception.php';
    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';
    
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
    
    // Préparer l'email
    $sujet = "Votre facture Youki and Co - Commande #" . $idCommande;
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto;'>
            <div style='background: #d40000; color: white; padding: 20px; text-align: center;'>
                <h1>Youki and Co</h1>
                <p>Créations artisanales japonaises</p>
            </div>
            <div style='padding: 20px; background: #f9f9f9;'>
                <h2>Merci pour votre commande !</h2>
                <p>Bonjour <strong>" . htmlspecialchars($client['prenom']) . " " . htmlspecialchars($client['nom']) . "</strong>,</p>
                
                <div style='background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #d40000;'>
                    <h3 style='margin-top: 0;'>📦 Détails de votre commande</h3>
                    <p><strong>Commande #" . $idCommande . "</strong></p>
                    <p>Date : " . date('d/m/Y') . "</p>
                    <p><strong>Montant total : " . number_format($client['montantTotal'], 2, ',', ' ') . " € TTC</strong></p>
                </div>
                
                <p>Votre facture détaillée est jointe à cet email au format PDF.</p>
                <p>Nous vous remercions pour votre confiance !</p>
                <br>
                <p>Cordialement,<br>L'équipe Youki and Co</p>
            </div>
            <div style='padding: 20px; text-align: center; color: #666; font-size: 12px; background: #f0f0f0;'>
                <p><strong>Youki and Co - Créations artisanales japonaises</strong></p>
                <p>📧 " . SMTP_FROM_EMAIL . " | 📞 +33 1 23 45 67 89</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Envoyer l'email avec PHPMailer
    $mail = new PHPMailer(true);
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Destinataires
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($client['email']);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Pièce jointe
        $mail->addAttachment($cheminFacture, 'facture_' . $idCommande . '.pdf');
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        if ($mail->send()) {
            error_log("✅ Facture auto-envoyée avec succès à: " . $client['email']);
            return true;
        } else {
            error_log("❌ Erreur envoi facture auto: " . $mail->ErrorInfo);
            return false;
        }
    } catch (Exception $e) {
        error_log("❌ Erreur PHPMailer (auto): " . $e->getMessage());
        return false;
    }
}
?>
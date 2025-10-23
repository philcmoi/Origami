<?php
// test_smtp_fixed.php
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

echo "<h3>Test SMTP Gmail (SSL corrigé)</h3>";

$mail = new PHPMailer(true);

try {
    // Solution SSL pour WAMP
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'lhpp.philippe@gmail.com';
    $mail->Password = 'lvpk zqjt vuon qyrz';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    $mail->setFrom('votre_email@gmail.com', 'Test');
    $mail->addAddress('votre_email@gmail.com');
    
    $mail->Subject = 'Test SMTP SSL Corrigé';
    $mail->Body = 'Ceci est un test avec SSL corrigé';
    
    if ($mail->send()) {
        echo "<p style='color: green;'>✅ Email envoyé avec succès!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur: " . $e->getMessage() . "</p>";
}
?>
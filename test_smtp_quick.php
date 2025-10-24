<?php
// test_smtp_quick.php
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

echo "<h3>Test SMTP Gmail</h3>";

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'lhpp.philippe@gmail.com'; // METTEZ VOTRE EMAIL
    $mail->Password = 'lvpk zqjt vuon qyrz'; // METTEZ VOTRE MOT DE PASSE
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    $mail->setFrom('lhpp.philippe@gmail.com', 'Test');
    $mail->addAddress('wonfeyhong45@gmail.com'); // Envoyez à vous-même
    
    $mail->Subject = 'Test SMTP';
    $mail->Body = 'Ceci est un test';
    
    if ($mail->send()) {
        echo "<p style='color: green;'>✅ Email envoyé avec succès!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur: " . $e->getMessage() . "</p>";
}
?>
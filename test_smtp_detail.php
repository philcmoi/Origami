<?php
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'votre-email@gmail.com';
    $mail->Password = 'votre-mot-de-passe-application';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    $mail->setFrom('votre-email@gmail.com', 'Test');
    $mail->addAddress('email-de-destination@example.com'); // Remplacez par votre email de test

    $mail->isHTML(true);
    $mail->Subject = 'Test SMTP';
    $mail->Body = 'Ceci est un test.';

    $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'html';

    $mail->send();
    echo 'Message envoyé';
} catch (Exception $e) {
    echo "Erreur: {$e->getMessage()}";
}
?>
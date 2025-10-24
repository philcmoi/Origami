<?php
header('Content-Type: text/html; charset=utf-8');
echo "<h1>Debug Email Réel</h1>";

require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configuration RÉELLE - À MODIFIER AVEC VOS COORDONNÉES
$config = [
    'host' => 'smtp.gmail.com',
    'username' => 'lhpp.philippe@gmail.com', // VOTRE GMAIL
    'password' => 'lvpk zqjt vuon qyrz', // MOT DE PASSE D'APPLICATION
    'port' => 587,
    'from_email' => 'lhpp.philippe@gmail.com',
    'from_name' => 'Origami Zen',
    'to_email' => 'wongfeyhong45@gmail.com' // EMAIL OÙ VOUS ATTENDEZ L'EMAIL
];

echo "<h2>Configuration utilisée :</h2>";
echo "<pre>" . print_r([
    'host' => $config['host'],
    'username' => $config['username'],
    'password' => '***' . substr($config['password'], -3),
    'port' => $config['port'],
    'to_email' => $config['to_email']
], true) . "</pre>";

try {
    $mail = new PHPMailer(true);
    
    // Configuration SMTP
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $config['port'];
    
    // DÉSACTIVER LA VÉRIFICATION SSL (important en local)
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    
    $mail->Timeout = 15;
    $mail->CharSet = 'UTF-8';
    
    // Expéditeur et destinataire
    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($config['to_email']);
    $mail->addReplyTo($config['from_email'], $config['from_name']);
    
    // Contenu de test
    $mail->isHTML(true);
    $mail->Subject = 'TEST RÉEL - Origami Zen';
    $mail->Body = '
    <h1>Test d\'envoi d\'email RÉEL</h1>
    <p>Si vous recevez cet email, la configuration SMTP fonctionne.</p>
    <p>Date: ' . date('d/m/Y H:i:s') . '</p>
    <p>Serveur: ' . $_SERVER['SERVER_NAME'] . '</p>
    ';
    
    $mail->AltBody = 'Test réel - Origami Zen - ' . date('d/m/Y H:i:s');
    
    echo "<h2>Tentative d'envoi...</h2>";
    
    // Test de connexion SMTP
    if ($mail->smtpConnect()) {
        echo "<p style='color: green;'>✅ Connexion SMTP réussie</p>";
        $mail->smtpClose();
    } else {
        echo "<p style='color: red;'>❌ Échec connexion SMTP</p>";
    }
    
    // Envoi réel
    if ($mail->send()) {
        echo "<p style='color: green; font-size: 20px;'>✅ Email RÉEL envoyé avec succès à {$config['to_email']}</p>";
        echo "<p><strong>Vérifiez votre boîte de réception (et les spams) !</strong></p>";
    } else {
        echo "<p style='color: red;'>❌ Échec de l'envoi: " . $mail->ErrorInfo . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; font-size: 18px;'>❌ ERREUR: " . $e->getMessage() . "</p>";
}

echo "<hr><h2>Instructions importantes :</h2>";
echo "<ol>
<li><strong>Mot de passe d'application Gmail :</strong> Utilisez un mot de passe d'application, pas votre mot de passe Gmail normal</li>
<li><strong>Vérification en 2 étapes :</strong> Doit être activée sur votre compte Google</li>
<li><strong>Spams :</strong> Vérifiez votre dossier spam/courrier indésirable</li>
<li><strong>Autorisations :</strong> Autorisez les applications moins sécurisées (temporairement)</li>
</ol>";

// Lien pour générer un mot de passe d'application
echo '<p><a href="https://myaccount.google.com/apppasswords" target="_blank">🔗 Générer un mot de passe d\'application Google</a></p>';
?>
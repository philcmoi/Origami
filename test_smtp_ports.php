<?php
header('Content-Type: text/html; charset=utf-8');
echo "<h1>Test des ports SMTP</h1>";

require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$ports_to_test = [
    ['port' => 587, 'secure' => PHPMailer::ENCRYPTION_STARTTLS, 'name' => 'TLS'],
    ['port' => 465, 'secure' => PHPMailer::ENCRYPTION_SMTPS, 'name' => 'SSL'],
    ['port' => 25, 'secure' => '', 'name' => 'Non sécurisé']
];

foreach ($ports_to_test as $config) {
    echo "<h3>Test port {$config['port']} ({$config['name']})</h3>";
    
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'lhpp.philippe@gmail.com';
        $mail->Password = 'votre-mot-de-passe-application';
        $mail->SMTPSecure = $config['secure'];
        $mail->Port = $config['port'];
        $mail->Timeout = 5;
        
        // Désactiver vérification SSL pour les tests
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        if ($mail->smtpConnect()) {
            echo "<p style='color: green;'>✅ Connexion réussie sur le port {$config['port']}</p>";
            $mail->smtpClose();
        } else {
            echo "<p style='color: red;'>❌ Échec sur le port {$config['port']}</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erreur: " . $e->getMessage() . "</p>";
    }
    echo "<hr>";
}
?>
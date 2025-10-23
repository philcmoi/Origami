<?php
header('Content-Type: text/html; charset=utf-8');
echo "<h1>Test Configuration SMTP</h1>";

// Import PHPMailer classes - DOIT ÊTRE EN DEHORS du try/catch
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Test de base de PHPMailer
try {
    $mail = new PHPMailer(true);
    
    // Configuration Gmail
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'lhpp.philippe@gmail.com';
    $mail->Password = 'votre-mot-de-passe-application'; // À remplacer
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->Timeout = 10; // 10 secondes max
    
    echo "<p style='color: green;'>✅ PHPMailer chargé avec succès</p>";
    echo "<p>Configuration SMTP testée</p>";
    
    // Test de connexion SMTP
    if ($mail->smtpConnect()) {
        echo "<p style='color: green;'>✅ Connexion SMTP réussie</p>";
        $mail->smtpClose();
    } else {
        echo "<p style='color: red;'>❌ Échec de connexion SMTP</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur PHPMailer: " . $e->getMessage() . "</p>";
}

// Test des dépendances
echo "<h2>Vérification des dépendances</h2>";
$required_files = [
    'PHPMailer/src/Exception.php',
    'PHPMailer/src/PHPMailer.php', 
    'PHPMailer/src/SMTP.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ $file existe</p>";
    } else {
        echo "<p style='color: red;'>❌ $file manquant</p>";
    }
}

// Test de la version PHP
echo "<h2>Vérification de l'environnement PHP</h2>";
echo "<p>Version PHP: " . PHP_VERSION . "</p>";
echo "<p>Extensions chargées: " . implode(', ', get_loaded_extensions()) . "</p>";

// Vérification des permissions
echo "<h2>Vérification des permissions</h2>";
$writable_dirs = ['.', 'PHPMailer'];
foreach ($writable_dirs as $dir) {
    if (is_writable($dir)) {
        echo "<p style='color: green;'>✅ Le dossier '$dir' est accessible en écriture</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Le dossier '$dir' n'est pas accessible en écriture</p>";
    }
}
?>
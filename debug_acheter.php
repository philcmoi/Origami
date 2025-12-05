<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "=== DEBUG acheter.php ===<br>";
echo "Session: " . (session_status() === PHP_SESSION_ACTIVE ? "Active" : "Inactive") . "<br>";

// Test des inclusions
$files = ['config.php', 'smtp_config.php', 'tcpdf/tcpdf.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "? $file existe<br>";
    } else {
        echo "? $file MANQUANT<br>";
    }
}

echo "=== FIN DEBUG ===";
?>

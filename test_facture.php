<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);

// Inclure et tester génération facture
require_once 'genererFacturePDF.php';

$idCommande = 1; // Changez avec un ID réel

$resultat = genererFacturePDF($pdo, $idCommande);

if ($resultat) {
    echo "✅ Facture générée : " . $resultat;
    
    // Tester l'affichage direct
    afficherFacturePDFDirect($pdo, $idCommande);
} else {
    echo "❌ Échec de génération";
    
    // Vérifier les logs
    $log_file = 'C:/wamp64/logs/paypal_errors.log';
    if (file_exists($log_file)) {
        echo "<pre>Logs : " . file_get_contents($log_file) . "</pre>";
    }
}
?>
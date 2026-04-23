<?php
// config.php
$host = 'localhost';
$dbname = 'origami';
$username = 'Philippe';
$password = 'l@99339R';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// ✅ Ajoute cette fonction
function getConnexionBD() {
    global $pdo;
    return $pdo;
}
?>
<?php
// config.php
$host = '217.182.198.20';
$dbname = 'origami'; // Le nom de votre base de données
$username = 'root';   // Votre utilisateur MySQL (root par défaut sur WAMP)
$password = 'L099339R';       // Votre mot de passe MySQL (vide par défaut sur WAMP)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>
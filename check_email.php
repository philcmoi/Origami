<?php
// check_email.php - Vérifie si un email existe déjà dans la base
session_start();
header('Content-Type: application/json');

// Connexion à la base de données
$host = 'localhost';
$dbname = 'heureducadeau';
$username = 'Philippe';
$password = 'l@99339R';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['exists' => false, 'error' => 'Erreur de connexion']);
    exit;
}

// Récupérer l'email depuis la requête POST
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['exists' => false, 'error' => 'Email invalide']);
    exit;
}

// Vérifier si l'email existe déjà
$stmt = $pdo->prepare("SELECT id_client FROM clients WHERE email = ?");
$stmt->execute([$email]);
$client = $stmt->fetch();

if ($client) {
    echo json_encode(['exists' => true, 'id_client' => $client['id_client']]);
} else {
    echo json_encode(['exists' => false]);
}
?>
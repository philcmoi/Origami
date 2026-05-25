<?php
// test_produits.php - Test direct de la base de données
header('Content-Type: application/json');

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test 1 : Compter les produits
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Origami WHERE visible = 1");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Test 2 : Récupérer les produits
    $stmt = $pdo->query("SELECT idOrigami, nom, description, photo, prixHorsTaxe FROM Origami WHERE visible = 1 LIMIT 5");
    $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'total_produits' => $total['total'],
        'produits' => $produits,
        'debug' => [
            'host' => $host,
            'dbname' => $dbname,
            'username' => $username
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
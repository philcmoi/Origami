<?php
// api_produits.php - API minimale pour les produits
header('Content-Type: application/json');

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $page = isset($_POST['page']) ? (int)$_POST['page'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : (isset($_GET['limit']) ? (int)$_GET['limit'] : 8);
    $offset = ($page - 1) * $limit;
    
    // Compter le total
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Origami WHERE visible = 1");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Récupérer les produits
    $stmt = $pdo->prepare("
        SELECT idOrigami, nom, description, photo, prixHorsTaxe 
        FROM Origami 
        WHERE visible = 1
        ORDER BY idOrigami 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 200,
        'data' => [
            'produits' => $produits,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 500,
        'error' => $e->getMessage()
    ]);
}
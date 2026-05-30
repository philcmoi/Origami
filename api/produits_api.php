<?php
// api/produits_api.php
// ============================================
// API DE PAGINATION DES PRODUITS
// ============================================

$page = isset($data['page']) ? (int)$data['page'] : 1;
$limit = isset($data['limit']) ? (int)$data['limit'] : 8;
$offset = ($page - 1) * $limit;

try {
    // Compter le nombre total de produits visibles
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM Origami WHERE visible = 1");
    $stmt->execute();
    $totalRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $totalRow ? (int)$totalRow['total'] : 0;
    
    // Récupérer les produits paginés
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
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $total > 0 ? ceil($total / $limit) : 1
        ]
    ]);
} catch (Exception $e) {
    error_log("❌ Erreur get_produits_pagines: " . $e->getMessage());
    echo json_encode([
        'status' => 500, 
        'error' => 'Erreur: ' . $e->getMessage()
    ]);
}
exit;
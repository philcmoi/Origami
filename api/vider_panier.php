<?php
// api/vider_panier.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vider le panier
    unset($_SESSION['panier']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Panier vidé avec succès'
    ]);
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
}
?>
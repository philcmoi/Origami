<?php
// api/vider_panier.php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['panier'] = [];
    echo json_encode(['success' => true, 'message' => 'Panier vidé']);
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}
?>
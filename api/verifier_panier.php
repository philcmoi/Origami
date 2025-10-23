<?php
// api/verifier_panier.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// Inclure les fonctions panier
require_once '../fonctions_panier.php';

$response = [
    'panier_vide' => panierEstVide(),
    'nombre_articles' => getNombreArticlesPanier(),
    'total_panier' => calculerTotalPanier(),
    'articles' => []
];

// Ajouter les détails des articles si le panier n'est pas vide
if (!$response['panier_vide'] && is_array($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $id => $produit) {
        $response['articles'][] = [
            'id' => $id,
            'nom' => $produit['nom'] ?? "Produit #$id",
            'quantite' => $produit['quantite'] ?? 1,
            'prix' => $produit['prix'] ?? 0
        ];
    }
}

echo json_encode($response);
?>
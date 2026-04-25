<?php
// get_produit.php - Récupère les données d'un produit pour l'édition
require_once 'config.php';
require_once 'admin_protection.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID manquant ou invalide']);
    exit;
}

$id = intval($_GET['id']);

try {
    $bdd = getConnexionBD();
    $stmt = $bdd->prepare("SELECT idOrigami, nom, description, photo, prixHorsTaxe FROM Origami WHERE idOrigami = ?");
    $stmt->execute([$id]);
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($produit) {
        echo json_encode([
            'success' => true,
            'nom' => $produit['nom'],
            'description' => $produit['description'],
            'photo' => $produit['photo'],
            'prixHorsTaxe' => $produit['prixHorsTaxe']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Produit non trouvé']);
    }
} catch (PDOException $e) {
    error_log("Erreur get_produit: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur base de données: ' . $e->getMessage()]);
}
?>
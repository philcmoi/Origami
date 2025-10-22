<?php
header('Content-Type: application/json');

include_once 'config.php';
include_once 'Commande.php';

$database = new Database();
$db = $database->getConnection();
$commande = new Commande($db);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch($action) {
        case 'create':
            $commande->client_nom = $_POST['client_nom'];
            $commande->client_email = $_POST['client_email'];
            $commande->produit = $_POST['produit'];
            $commande->quantite = $_POST['quantite'];
            $commande->prix_unitaire = $_POST['prix_unitaire'];
            $commande->statut = $_POST['statut'];

            if($commande->create()) {
                echo json_encode(['success' => true, 'message' => 'Commande créée avec succès']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la création']);
            }
            break;

        case 'read':
            $commande->id = $_GET['id'];
            if($commande->readOne()) {
                echo json_encode([
                    'id' => $commande->id,
                    'client_nom' => $commande->client_nom,
                    'client_email' => $commande->client_email,
                    'produit' => $commande->produit,
                    'quantite' => $commande->quantite,
                    'prix_unitaire' => $commande->prix_unitaire,
                    'statut' => $commande->statut
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
            }
            break;

        case 'update':
            $commande->id = $_POST['id'];
            $commande->client_nom = $_POST['client_nom'];
            $commande->client_email = $_POST['client_email'];
            $commande->produit = $_POST['produit'];
            $commande->quantite = $_POST['quantite'];
            $commande->prix_unitaire = $_POST['prix_unitaire'];
            $commande->statut = $_POST['statut'];

            if($commande->update()) {
                echo json_encode(['success' => true, 'message' => 'Commande mise à jour avec succès']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
            }
            break;

        case 'delete':
            $commande->id = $_GET['id'];
            if($commande->delete()) {
                echo json_encode(['success' => true, 'message' => 'Commande supprimée avec succès']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}

// Redirection après traitement des actions POST
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: index.php');
    exit;
}
?>
<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$db = new Database();
$connection = $db->getConnection();

function sendResponse($status, $data = null, $error = null) {
    http_response_code($status);
    echo json_encode([
        'status' => $status,
        'data' => $data,
        'error' => $error
    ]);
    exit;
}

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        
        switch($action) {
            case 'get_panier':
                $idClient = $_GET['idClient'] ?? 0;
                getPanier($idClient);
                break;
            case 'get_produits':
                getProduits();
                break;
            case 'get_commandes':
                $idClient = $_GET['idClient'] ?? 0;
                getCommandes($idClient);
                break;
            default:
                sendResponse(400, null, "Action GET non reconnue");
        }
    } 
    elseif ($method === 'POST') {
        $action = $input['action'] ?? '';
        
        switch($action) {
            case 'ajouter_au_panier':
                ajouterAuPanier($input);
                break;
            case 'modifier_quantite':
                modifierQuantitePanier($input);
                break;
            case 'supprimer_du_panier':
                supprimerDuPanier($input);
                break;
            case 'creer_commande':
                creerCommande($input);
                break;
            case 'creer_client':
                creerClient($input);
                break;
            default:
                sendResponse(400, null, "Action POST non reconnue: " . $action);
        }
    }
    elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        switch($action) {
            case 'vider_panier':
                viderPanier($input);
                break;
            default:
                sendResponse(400, null, "Action DELETE non reconnue");
        }
    }
    else {
        sendResponse(405, null, "Méthode non autorisée");
    }
} catch (Exception $e) {
    sendResponse(500, null, "Erreur serveur: " . $e->getMessage());
}

// Fonction pour ajouter au panier (version améliorée)
function ajouterAuPanier($data) {
    global $connection;
    
    $idClient = $data['idClient'] ?? 0;
    $idOrigami = $data['idOrigami'] ?? 0;
    $quantite = $data['quantite'] ?? 1;
    
    if (!$idClient || !$idOrigami) {
        sendResponse(400, null, "ID client et ID origami requis");
    }
    
    try {
        // Vérifier si le panier existe
        $query = "SELECT idPanier FROM Panier WHERE idClient = :idClient";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(":idClient", $idClient);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $panier = $stmt->fetch(PDO::FETCH_ASSOC);
            $idPanier = $panier['idPanier'];
        } else {
            // Créer un nouveau panier
            $query = "INSERT INTO Panier (idClient, dateModification) VALUES (:idClient, NOW())";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(":idClient", $idClient);
            $stmt->execute();
            $idPanier = $connection->lastInsertId();
        }
        
        // Vérifier si l'article est déjà dans le panier
        $query = "SELECT idLignePanier, quantite FROM LignePanier WHERE idPanier = :idPanier AND idOrigami = :idOrigami";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(":idPanier", $idPanier);
        $stmt->bindParam(":idOrigami", $idOrigami);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Mettre à jour la quantité
            $ligne = $stmt->fetch(PDO::FETCH_ASSOC);
            $nouvelleQuantite = $ligne['quantite'] + $quantite;
            
            $query = "UPDATE LignePanier SET quantite = :quantite WHERE idLignePanier = :idLignePanier";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(":quantite", $nouvelleQuantite);
            $stmt->bindParam(":idLignePanier", $ligne['idLignePanier']);
            $stmt->execute();
            
            $message = "Quantité mise à jour dans le panier";
        } else {
            // Récupérer le prix de l'origami
            $query = "SELECT nom, prixHorsTaxe FROM Origami WHERE idOrigami = :idOrigami";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(":idOrigami", $idOrigami);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                sendResponse(404, null, "Produit non trouvé");
            }
            
            $origami = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Ajouter au panier
            $query = "INSERT INTO LignePanier (idPanier, idOrigami, quantite, prixUnitaire) 
                     VALUES (:idPanier, :idOrigami, :quantite, :prixUnitaire)";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(":idPanier", $idPanier);
            $stmt->bindParam(":idOrigami", $idOrigami);
            $stmt->bindParam(":quantite", $quantite);
            $stmt->bindParam(":prixUnitaire", $origami['prixHorsTaxe']);
            $stmt->execute();
            
            $message = "Article ajouté au panier";
        }
        
        // Mettre à jour la date de modification du panier
        $query = "UPDATE Panier SET dateModification = NOW() WHERE idPanier = :idPanier";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(":idPanier", $idPanier);
        $stmt->execute();
        
        // Récupérer le contenu actuel du panier
        $query = "SELECT COUNT(*) as totalArticles FROM LignePanier WHERE idPanier = :idPanier";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(":idPanier", $idPanier);
        $stmt->execute();
        $totalArticles = $stmt->fetch(PDO::FETCH_ASSOC)['totalArticles'];
        
        sendResponse(200, [
            "message" => $message,
            "idPanier" => $idPanier,
            "totalArticles" => $totalArticles
        ]);
        
    } catch (PDOException $exception) {
        sendResponse(500, null, "Erreur base de données: " . $exception->getMessage());
    }
}

// Fonction pour modifier la quantité
function modifierQuantitePanier($data) {
    global $connection;
    
    $idLignePanier = $data['idLignePanier'] ?? 0;
    $quantite = $data['quantite'] ?? 1;
    
    if ($quantite <= 0) {
        // Si la quantité est 0 ou moins, supprimer l'article
        supprimerDuPanier($data);
        return;
    }
    
    try {
        $query = "UPDATE LignePanier SET quantite = :quantite WHERE idLignePanier = :idLignePanier";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(":quantite", $quantite);
        $stmt->bindParam(":idLignePanier", $idLignePanier);
        $stmt->execute();
        
        sendResponse(200, ["message" => "Quantité mise à jour"]);
        
    } catch (PDOException $exception) {
        sendResponse(500, null, "Erreur: " . $exception->getMessage());
    }
}

// Fonction pour supprimer un article du panier
function supprimerDuPanier($data) {
    global $connection;
    
    $idLignePanier = $data['idLignePanier'] ?? 0;
    
    try {
        $query = "DELETE FROM LignePanier WHERE idLignePanier = :idLignePanier";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(":idLignePanier", $idLignePanier);
        $stmt->execute();
        
        sendResponse(200, ["message" => "Article supprimé du panier"]);
        
    } catch (PDOException $exception) {
        sendResponse(500, null, "Erreur: " . $exception->getMessage());
    }
}

// Fonction pour vider le panier
function viderPanier($data) {
    global $connection;
    
    $idClient = $data['idClient'] ?? 0;
    
    try {
        // Trouver le panier du client
        $query = "SELECT idPanier FROM Panier WHERE idClient = :idClient";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(":idClient", $idClient);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $panier = $stmt->fetch(PDO::FETCH_ASSOC);
            $idPanier = $panier['idPanier'];
            
            // Supprimer toutes les lignes du panier
            $query = "DELETE FROM LignePanier WHERE idPanier = :idPanier";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(":idPanier", $idPanier);
            $stmt->execute();
            
            sendResponse(200, ["message" => "Panier vidé"]);
        } else {
            sendResponse(404, null, "Panier non trouvé");
        }
        
    } catch (PDOException $exception) {
        sendResponse(500, null, "Erreur: " . $exception->getMessage());
    }
}

// Fonction pour récupérer le panier
function getPanier($idClient) {
    global $connection;
    
    try {
        $query = "SELECT lp.idLignePanier, lp.idOrigami, o.nom, o.description, o.photo, 
                         lp.quantite, lp.prixUnitaire, (lp.quantite * lp.prixUnitaire) as totalLigne
                  FROM Panier p
                  JOIN LignePanier lp ON p.idPanier = lp.idPanier
                  JOIN Origami o ON lp.idOrigami = o.idOrigami
                  WHERE p.idClient = :idClient
                  ORDER BY lp.idLignePanier DESC";
        
        $stmt = $connection->prepare($query);
        $stmt->bindParam(":idClient", $idClient);
        $stmt->execute();
        
        $panier = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer le total
        $total = 0;
        $nombreArticles = 0;
        foreach ($panier as $article) {
            $total += $article['totalLigne'];
            $nombreArticles += $article['quantite'];
        }
        
        sendResponse(200, [
            "articles" => $panier,
            "total" => round($total, 2),
            "nombreArticles" => count($panier),
            "totalQuantites" => $nombreArticles
        ]);
        
    } catch (PDOException $exception) {
        sendResponse(500, null, "Erreur: " . $exception->getMessage());
    }
}

// Fonction pour récupérer les produits
function getProduits() {
    global $connection;
    
    try {
        $query = "SELECT idOrigami, nom, description, photo, prixHorsTaxe FROM Origami ORDER BY nom";
        $stmt = $connection->prepare($query);
        $stmt->execute();
        
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, $produits);
        
    } catch (PDOException $exception) {
        sendResponse(500, null, "Erreur: " . $exception->getMessage());
    }
}

// Fonction pour créer une commande
function creerCommande($data) {
    global $connection;
    
    try {
        $connection->beginTransaction();
        
        $idClient = $data['idClient'];
        $idAdresseLivraison = $data['idAdresseLivraison'];
        $modeReglement = $data['modeReglement'] ?? 'CB';
        $fraisDePort = $data['fraisDePort'] ?? 5.90;
        
        // Récupérer le panier
        $query = "SELECT p.idPanier, lp.idOrigami, lp.quantite, lp.prixUnitaire
                  FROM Panier p
                  JOIN LignePanier lp ON p.idPanier = lp.idPanier
                  WHERE p.idClient = :idClient";
        
        $stmt = $connection->prepare($query);
        $stmt->bindParam(":idClient", $idClient);
        $stmt->execute();
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($articles)) {
            sendResponse(400, null, "Le panier est vide");
            return;
        }
        
        // Calculer le montant total
        $montantTotal = $fraisDePort;
        foreach ($articles as $article) {
            $montantTotal += $article['quantite'] * $article['prixUnitaire'];
        }
        
        // Calculer la date de livraison (7 jours ouvrés)
        $delaiLivraison = date('Y-m-d', strtotime('+7 weekdays'));
        
        // Créer la commande
        $query = "INSERT INTO Commande (idClient, idAdresseLivraison, dateCommande, modeReglement, delaiLivraison, fraisDePort, montantTotal, statut)
                  VALUES (:idClient, :idAdresseLivraison, NOW(), :modeReglement, :delaiLivraison, :fraisDePort, :montantTotal, 'en_attente')";
        
        $stmt = $connection->prepare($query);
        $stmt->bindParam(":idClient", $idClient);
        $stmt->bindParam(":idAdresseLivraison", $idAdresseLivraison);
        $stmt->bindParam(":modeReglement", $modeReglement);
        $stmt->bindParam(":delaiLivraison", $delaiLivraison);
        $stmt->bindParam(":fraisDePort", $fraisDePort);
        $stmt->bindParam(":montantTotal", $montantTotal);
        $stmt->execute();
        
        $idCommande = $connection->lastInsertId();
        
        // Créer les lignes de commande
        $query = "INSERT INTO LigneCommande (idCommande, idOrigami, quantite, prixUnitaire)
                  VALUES (:idCommande, :idOrigami, :quantite, :prixUnitaire)";
        
        $stmt = $connection->prepare($query);
        
        foreach ($articles as $article) {
            $stmt->bindParam(":idCommande", $idCommande);
            $stmt->bindParam(":idOrigami", $article['idOrigami']);
            $stmt->bindParam(":quantite", $article['quantite']);
            $stmt->bindParam(":prixUnitaire", $article['prixUnitaire']);
            $stmt->execute();
        }
        
        // Vider le panier
        $query = "DELETE FROM LignePanier WHERE idPanier = (SELECT idPanier FROM Panier WHERE idClient = :idClient)";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(":idClient", $idClient);
        $stmt->execute();
        
        $connection->commit();
        
        sendResponse(201, [
            "message" => "Commande créée avec succès",
            "idCommande" => $idCommande,
            "numeroCommande" => "CMD" . str_pad($idCommande, 6, '0', STR_PAD_LEFT),
            "montantTotal" => $montantTotal
        ]);
        
    } catch (PDOException $exception) {
        $connection->rollBack();
        sendResponse(500, null, "Erreur lors de la création de la commande: " . $exception->getMessage());
    }
}

// Fonction pour créer un client
function creerClient($data) {
    global $connection;
    
    try {
        $email = $data['email'];
        $motDePasse = password_hash($data['motDePasse'], PASSWORD_DEFAULT);
        
        $query = "INSERT INTO Client (email, motDePasse) VALUES (:email, :motDePasse)";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":motDePasse", $motDePasse);
        $stmt->execute();
        
        $idClient = $connection->lastInsertId();
        
        sendResponse(201, [
            "message" => "Client créé avec succès",
            "idClient" => $idClient
        ]);
        
    } catch (PDOException $exception) {
        sendResponse(500, null, "Erreur: " . $exception->getMessage());
    }
}

// Fonction pour récupérer les commandes
function getCommandes($idClient) {
    global $connection;
    
    try {
        $query = "SELECT c.idCommande, c.dateCommande, c.modeReglement, c.delaiLivraison, 
                         c.fraisDePort, c.montantTotal, c.statut,
                         CONCAT('CMD', LPAD(c.idCommande, 6, '0')) as numeroCommande
                  FROM Commande c
                  WHERE c.idClient = :idClient
                  ORDER BY c.dateCommande DESC";
        
        $stmt = $connection->prepare($query);
        $stmt->bindParam(":idClient", $idClient);
        $stmt->execute();
        
        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, $commandes);
        
    } catch (PDOException $exception) {
        sendResponse(500, null, "Erreur: " . $exception->getMessage());
    }
}
?>
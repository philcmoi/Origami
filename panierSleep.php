<?php
// panier.php - API de gestion du panier
// Version adaptée à la structure BDD: Origami, Panier, LignePanier, Client

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Configuration BDD
$host = 'localhost';
$dbname = 'origami';
$username = 'Philippe';
$password = 'l@99339R';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion BDD']);
    exit;
}

// Récupération de l'action
$action = $_GET['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['action'])) {
        $action = $input['action'];
    }
}

// Router des actions
switch ($action) {
    case 'ajouter':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            break;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        ajouterAuPanier($pdo, $input);
        break;
    
    case 'supprimer':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' false, 'message' => 'Méthode non autorisée']);
            break;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        supprimerDuPanier($pdo, $input);
        break;
    
    case 'modifier':
    case 'update_quantite':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            break;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        modifierQuantite($pdo, $input);
        break;
    
    case 'compter':
        compterArticles($pdo);
        break;
    
    case 'vider':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            break;
        }
        viderPanier($pdo);
        break;
    
    case 'get':
        getPanier($pdo);
        break;
    
    case 'init_checkout':
        initCheckout($pdo);
        break;
    
    case 'test':
        testAPI($pdo);
        break;
    
    default:
        echo json_encode([
            'success' => false, 
            'message' => 'Action non valide: ' . $action,
            'available_actions' => ['ajouter', 'supprimer', 'modifier', 'compter', 'vider', 'get', 'init_checkout', 'test']
        ]);
}

/**
 * Récupère le contenu du panier depuis la BDD
 */
function getPanier($pdo) {
    $idClient = $_SESSION['client_id'] ?? null;
    
    if (!$idClient) {
        echo json_encode(['success' => true, 'panier' => [], 'sous_total' => 0, 'total_items' => 0]);
        return;
    }
    
    try {
        // Récupérer le panier du client
        $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
        $stmt->execute([$idClient]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$panier) {
            echo json_encode(['success' => true, 'panier' => [], 'sous_total' => 0, 'total_items' => 0]);
            return;
        }
        
        // Récupérer les lignes du panier
        $stmt = $pdo->prepare("
            SELECT lp.idLignePanier, lp.idOrigami, lp.quantite, lp.prixUnitaire, 
                   o.nom, o.description, o.photo
            FROM LignePanier lp
            JOIN Origami o ON lp.idOrigami = o.idOrigami
            WHERE lp.idPanier = ?
        ");
        $stmt->execute([$panier['idPanier']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sous_total = 0;
        $total_items = 0;
        
        foreach ($items as &$item) {
            $item['prix_unitaire'] = $item['prixUnitaire'];
            $item['total_ligne'] = $item['quantite'] * $item['prixUnitaire'];
            $sous_total += $item['total_ligne'];
            $total_items += $item['quantite'];
            $item['disponible'] = true;
        }
        
        echo json_encode([
            'success' => true,
            'panier' => $items,
            'sous_total' => $sous_total,
            'total_items' => $total_items
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur getPanier: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Compte les articles dans le panier
 */
function compterArticles($pdo) {
    $idClient = $_SESSION['client_id'] ?? null;
    
    if (!$idClient) {
        echo json_encode(['success' => true, 'total' => 0]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(lp.quantite), 0) as total 
            FROM Panier p
            INNER JOIN LignePanier lp ON p.idPanier = lp.idPanier
            WHERE p.idClient = ?
        ");
        $stmt->execute([$idClient]);
        $total = (int)$stmt->fetchColumn();
        
        echo json_encode(['success' => true, 'total' => $total]);
        
    } catch (Exception $e) {
        error_log("Erreur compterArticles: " . $e->getMessage());
        echo json_encode(['success' => false, 'total' => 0, 'message' => $e->getMessage()]);
    }
}

/**
 * Ajoute un produit au panier
 */
function ajouterAuPanier($pdo, $input) {
    $idOrigami = filter_var($input['idOrigami'] ?? ($input['id_produit'] ?? 0), FILTER_VALIDATE_INT);
    $quantite = filter_var($input['quantite'] ?? 1, FILTER_VALIDATE_INT);
    
    if (!$idOrigami || $idOrigami <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
        return;
    }
    
    if (!$quantite || $quantite < 1) $quantite = 1;
    
    $idClient = $_SESSION['client_id'] ?? null;
    
    // Si pas de client, en créer un temporaire
    if (!$idClient) {
        $idClient = getOrCreateTemporaryClient($pdo);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Vérifier le produit
        $stmt = $pdo->prepare("
            SELECT idOrigami, nom, prixHorsTaxe, visible
            FROM Origami 
            WHERE idOrigami = ? AND visible = 1
        ");
        $stmt->execute([$idOrigami]);
        $produit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$produit) {
            throw new Exception("Produit non trouvé ou indisponible");
        }
        
        $prixUnitaire = $produit['prixHorsTaxe'];
        
        // Récupérer ou créer le panier
        $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
        $stmt->execute([$idClient]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$panier) {
            $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
            $stmt->execute([$idClient]);
            $idPanier = $pdo->lastInsertId();
            $_SESSION['panier_id'] = $idPanier;
        } else {
            $idPanier = $panier['idPanier'];
            $_SESSION['panier_id'] = $idPanier;
            
            $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
            $stmt->execute([$idPanier]);
        }
        
        // Vérifier si le produit est déjà dans le panier
        $stmt = $pdo->prepare("
            SELECT idLignePanier, quantite FROM LignePanier 
            WHERE idPanier = ? AND idOrigami = ?
        ");
        $stmt->execute([$idPanier, $idOrigami]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            $nouvelleQuantite = $item['quantite'] + $quantite;
            $stmt = $pdo->prepare("
                UPDATE LignePanier 
                SET quantite = ?, prixUnitaire = ? 
                WHERE idLignePanier = ?
            ");
            $stmt->execute([$nouvelleQuantite, $prixUnitaire, $item['idLignePanier']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO LignePanier (idPanier, idOrigami, quantite, prixUnitaire) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$idPanier, $idOrigami, $quantite, $prixUnitaire]);
        }
        
        $pdo->commit();
        
        // Récupérer le total des articles
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantite), 0) as total FROM LignePanier WHERE idPanier = ?");
        $stmt->execute([$idPanier]);
        $total_articles = (int)$stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'message' => 'Produit ajouté au panier avec succès',
            'produit' => [
                'id' => (int)$produit['idOrigami'],
                'nom' => $produit['nom'],
                'prix_ttc' => floatval($produit['prixHorsTaxe']),
                'image' => $produit['photo'] ?? 'img/default-product.jpg'
            ],
            'panier' => [
                'id' => (int)$idPanier,
                'quantite_produit' => $quantite,
                'total_articles' => $total_articles
            ]
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Erreur ajout panier: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Supprime un produit du panier
 */
function supprimerDuPanier($pdo, $input) {
    $idLignePanier = filter_var($input['idLignePanier'] ?? ($input['id_item'] ?? 0), FILTER_VALIDATE_INT);
    
    if (!$idLignePanier || $idLignePanier <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID ligne panier invalide']);
        return;
    }
    
    $idClient = $_SESSION['client_id'] ?? null;
    
    if (!$idClient) {
        echo json_encode(['success' => false, 'message' => 'Client non identifié']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Vérifier que la ligne appartient bien au client
        $stmt = $pdo->prepare("
            DELETE lp FROM LignePanier lp
            INNER JOIN Panier p ON lp.idPanier = p.idPanier
            WHERE lp.idLignePanier = ? AND p.idClient = ?
        ");
        $stmt->execute([$idLignePanier, $idClient]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Produit retiré du panier']);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Erreur suppression panier: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Modifie la quantité d'un produit
 */
function modifierQuantite($pdo, $input) {
    $idLignePanier = filter_var($input['idLignePanier'] ?? ($input['id_item'] ?? 0), FILTER_VALIDATE_INT);
    $quantite = filter_var($input['quantite'] ?? 1, FILTER_VALIDATE_INT);
    
    if (!$idLignePanier || $idLignePanier <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID ligne panier invalide']);
        return;
    }
    
    if ($quantite < 1) {
        // Si quantité < 1, supprimer l'article
        supprimerDuPanier($pdo, ['idLignePanier' => $idLignePanier]);
        return;
    }
    
    $idClient = $_SESSION['client_id'] ?? null;
    
    if (!$idClient) {
        echo json_encode(['success' => false, 'message' => 'Client non identifié']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Mettre à jour la quantité
        $stmt = $pdo->prepare("
            UPDATE LignePanier lp
            INNER JOIN Panier p ON lp.idPanier = p.idPanier
            SET lp.quantite = ?
            WHERE lp.idLignePanier = ? AND p.idClient = ?
        ");
        $stmt->execute([$quantite, $idLignePanier, $idClient]);
        
        // Mettre à jour la date du panier
        $stmt = $pdo->prepare("
            UPDATE Panier p
            INNER JOIN LignePanier lp ON p.idPanier = lp.idPanier
            SET p.dateModification = NOW()
            WHERE lp.idLignePanier = ? AND p.idClient = ?
        ");
        $stmt->execute([$idLignePanier, $idClient]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Quantité mise à jour']);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Erreur modification panier: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Vide le panier
 */
function viderPanier($pdo) {
    $idClient = $_SESSION['client_id'] ?? null;
    
    if (!$idClient) {
        echo json_encode(['success' => false, 'message' => 'Client non identifié']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Récupérer l'ID du panier
        $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
        $stmt->execute([$idClient]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($panier) {
            $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ?");
            $stmt->execute([$panier['idPanier']]);
            
            $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
            $stmt->execute([$panier['idPanier']]);
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Panier vidé avec succès']);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Erreur vidage panier: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors du vidage du panier']);
    }
}

/**
 * Initialise le checkout
 */
function initCheckout($pdo) {
    $idClient = $_SESSION['client_id'] ?? null;
    
    if (!$idClient) {
        echo json_encode(['success' => false, 'message' => 'Client non identifié']);
        return;
    }
    
    try {
        // Récupérer le panier
        $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
        $stmt->execute([$idClient]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$panier) {
            echo json_encode(['success' => false, 'message' => 'Panier vide']);
            return;
        }
        
        // Vérifier que le panier contient des articles
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM LignePanier WHERE idPanier = ?");
        $stmt->execute([$panier['idPanier']]);
        $nbItems = $stmt->fetchColumn();
        
        if ($nbItems == 0) {
            echo json_encode(['success' => false, 'message' => 'Panier vide']);
            return;
        }
        
        // Stocker l'ID du panier en session
        $_SESSION['panier_id'] = $panier['idPanier'];
        $_SESSION['client_id'] = $idClient;
        
        echo json_encode([
            'success' => true,
            'message' => 'Checkout initialisé',
            'redirect' => 'livraison_form.php'
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur initCheckout: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'initialisation: ' . $e->getMessage()]);
    }
}

/**
 * Test de l'API
 */
function testAPI($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM Origami WHERE visible = 1");
        $count = $stmt->fetchColumn();
        
        $idClient = $_SESSION['client_id'] ?? null;
        
        // Tester la récupération du panier
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM LignePanier lp
            INNER JOIN Panier p ON lp.idPanier = p.idPanier
            WHERE p.idClient = ?
        ");
        $stmt->execute([$idClient]);
        $panierCount = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'message' => 'API panier fonctionnelle',
            'timestamp' => time(),
            'session' => [
                'client_id' => $idClient,
                'is_logged_in' => $idClient ? true : false
            ],
            'database' => [
                'connected' => true,
                'produits_actifs' => (int)$count
            ],
            'panier' => [
                'articles_count' => (int)$panierCount
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur test API: ' . $e->getMessage()
        ]);
    }
}

/**
 * Crée ou récupère un client temporaire
 */
function getOrCreateTemporaryClient($pdo) {
    $session_id = session_id();
    
    // Vérifier si un client temporaire existe déjà pour cette session
    $stmt = $pdo->prepare("
        SELECT idClient FROM Client 
        WHERE session_id = ? AND type = 'temporaire'
        ORDER BY idClient DESC LIMIT 1
    ");
    $stmt->execute([$session_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($client) {
        $_SESSION['client_id'] = $client['idClient'];
        return $client['idClient'];
    }
    
    // Créer un nouveau client temporaire
    $emailTemp = 'temp_' . uniqid() . '@YoukiAndCo.fr';
    $stmt = $pdo->prepare("
        INSERT INTO Client (email, motDePasse, nom, prenom, type, session_id, date_creation) 
        VALUES (?, NULL, 'Invité', 'Client', 'temporaire', ?, NOW())
    ");
    $stmt->execute([$emailTemp, $session_id]);
    $idClient = $pdo->lastInsertId();
    
    $_SESSION['client_id'] = $idClient;
    $_SESSION['client_email'] = $emailTemp;
    
    return $idClient;
}
?>
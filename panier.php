<?php
// panier.php - API de gestion du panier (CORRIGÉ)
// Harmonisation avec la structure BDD existante (LignePanier, Panier, Client)

require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Gestion des requêtes OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

// Connexion BDD via config.php
global $pdo;
if (!$pdo) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur de connexion à la base de données'
    ]);
    exit;
}

// Récupération de l'action
$action = $_GET['action'] ?? null;

// Si c'est une requête POST, essayer de lire le body JSON
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
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
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
 * Récupère ou crée un client temporaire
 */
function getOrCreateClientPanier($pdo) {
    if (isset($_SESSION['client_id'])) {
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE idClient = ?");
        $stmt->execute([$_SESSION['client_id']]);
        if ($stmt->fetch()) {
            return $_SESSION['client_id'];
        }
        unset($_SESSION['client_id']);
    }
    
    $sessionId = session_id();
    
    // Vérifier si un client existe avec cette session
    $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE session_id = ? AND type = 'temporaire'");
    $stmt->execute([$sessionId]);
    $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($clientExist) {
        $_SESSION['client_id'] = $clientExist['idClient'];
        return $clientExist['idClient'];
    }
    
    // Créer un nouveau client temporaire
    $emailTemp = 'temp_' . uniqid() . '@YoukiAndCo.fr';
    $stmt = $pdo->prepare("INSERT INTO Client (email, nom, prenom, session_id, type, date_creation) VALUES (?, 'Invité', 'Client', ?, 'temporaire', NOW())");
    $stmt->execute([$emailTemp, $sessionId]);
    
    $clientId = $pdo->lastInsertId();
    $_SESSION['client_id'] = $clientId;
    
    return $clientId;
}

/**
 * Récupère le contenu du panier depuis la BDD
 * Structure: Panier + LignePanier + Origami
 */
function getPanier($pdo) {
    $client_id = $_SESSION['client_id'] ?? null;
    
    if (!$client_id) {
        echo json_encode([
            'success' => true,
            'panier' => [],
            'sous_total' => 0,
            'total_items' => 0,
            'message' => 'Panier vide'
        ]);
        return;
    }
    
    try {
        // Récupérer le panier actif du client
        $stmt = $pdo->prepare("
            SELECT idPanier, dateModification 
            FROM Panier 
            WHERE idClient = ?
            ORDER BY dateModification DESC LIMIT 1
        ");
        $stmt->execute([$client_id]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$panier) {
            echo json_encode([
                'success' => true,
                'panier' => [],
                'sous_total' => 0,
                'total_items' => 0,
                'message' => 'Panier vide'
            ]);
            return;
        }
        
        // Récupérer les articles du panier
        $stmt = $pdo->prepare("
            SELECT 
                lp.idLignePanier,
                lp.idOrigami,
                lp.quantite,
                lp.prixUnitaire,
                (lp.quantite * lp.prixUnitaire) as total_ligne,
                o.nom as produit_nom,
                o.description,
                o.photo,
                o.prixHorsTaxe as prix_original
            FROM LignePanier lp
            JOIN Origami o ON lp.idOrigami = o.idOrigami
            WHERE lp.idPanier = ? AND o.visible = 1
        ");
        $stmt->execute([$panier['idPanier']]);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sous_total = 0;
        $total_items = 0;
        
        foreach ($articles as &$article) {
            $sous_total += $article['total_ligne'];
            $total_items += $article['quantite'];
        }
        
        echo json_encode([
            'success' => true,
            'panier' => [
                'id_panier' => $panier['idPanier'],
                'articles' => $articles,
                'sous_total' => $sous_total,
                'total_items' => $total_items
            ],
            'sous_total' => $sous_total,
            'total_items' => $total_items
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur getPanier: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la récupération du panier: ' . $e->getMessage()
        ]);
    }
}

/**
 * Compte les articles dans le panier
 */
function compterArticles($pdo) {
    $client_id = $_SESSION['client_id'] ?? null;
    
    if (!$client_id) {
        echo json_encode([
            'success' => true,
            'total' => 0
        ]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(lp.quantite), 0) as total 
            FROM Panier p
            INNER JOIN LignePanier lp ON p.idPanier = lp.idPanier
            WHERE p.idClient = ?
        ");
        $stmt->execute([$client_id]);
        $total = (int)$stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'total' => $total
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur compterArticles: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'total' => 0,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Ajoute un produit au panier
 */
function ajouterAuPanier($pdo, $input) {
    $idOrigami = filter_var($input['id_produit'] ?? ($input['idOrigami'] ?? 0), FILTER_VALIDATE_INT);
    $quantite = filter_var($input['quantite'] ?? 1, FILTER_VALIDATE_INT);
    
    if (!$idOrigami || $idOrigami <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
        return;
    }
    
    if (!$quantite || $quantite < 1) $quantite = 1;
    
    $client_id = getOrCreateClientPanier($pdo);
    
    try {
        $pdo->beginTransaction();
        
        // Vérifier le produit
        $stmt = $pdo->prepare("
            SELECT idOrigami, nom, description, photo, prixHorsTaxe 
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
        $stmt = $pdo->prepare("
            SELECT idPanier FROM Panier 
            WHERE idClient = ?
            ORDER BY dateModification DESC LIMIT 1
        ");
        $stmt->execute([$client_id]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$panier) {
            $stmt = $pdo->prepare("
                INSERT INTO Panier (idClient, dateModification) 
                VALUES (?, NOW())
            ");
            $stmt->execute([$client_id]);
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
            $quantiteFinale = $nouvelleQuantite;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO LignePanier (idPanier, idOrigami, quantite, prixUnitaire) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$idPanier, $idOrigami, $quantite, $prixUnitaire]);
            $quantiteFinale = $quantite;
        }
        
        $pdo->commit();
        
        // Compter le total des articles
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantite), 0) as total FROM LignePanier WHERE idPanier = ?");
        $stmt->execute([$idPanier]);
        $totalArticles = (int)$stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'message' => 'Produit ajouté au panier avec succès',
            'produit' => [
                'id' => (int)$produit['idOrigami'],
                'nom' => $produit['nom'],
                'description' => $produit['description'],
                'photo' => $produit['photo'],
                'prixHorsTaxe' => floatval($produit['prixHorsTaxe'])
            ],
            'panier' => [
                'id' => (int)$idPanier,
                'quantite_produit' => $quantiteFinale,
                'total_articles' => $totalArticles
            ],
            'timestamp' => time()
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur ajout panier: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Supprime un produit du panier
 */
function supprimerDuPanier($pdo, $input) {
    $idOrigami = filter_var($input['id_produit'] ?? ($input['idOrigami'] ?? 0), FILTER_VALIDATE_INT);
    $idLignePanier = filter_var($input['idLignePanier'] ?? 0, FILTER_VALIDATE_INT);
    
    $client_id = $_SESSION['client_id'] ?? null;
    
    if (!$client_id) {
        echo json_encode(['success' => false, 'message' => 'Client non identifié']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Trouver l'item
        if ($idLignePanier > 0) {
            $stmt = $pdo->prepare("
                SELECT lp.idLignePanier, lp.idPanier, lp.quantite
                FROM LignePanier lp
                INNER JOIN Panier p ON lp.idPanier = p.idPanier
                WHERE lp.idLignePanier = ? AND p.idClient = ?
            ");
            $stmt->execute([$idLignePanier, $client_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT lp.idLignePanier, lp.idPanier, lp.quantite
                FROM LignePanier lp
                INNER JOIN Panier p ON lp.idPanier = p.idPanier
                WHERE lp.idOrigami = ? AND p.idClient = ?
            ");
            $stmt->execute([$idOrigami, $client_id]);
        }
        
        $itemInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$itemInfo) {
            throw new Exception("Produit non trouvé dans le panier");
        }
        
        $idItem = $itemInfo['idLignePanier'];
        $idPanier = $itemInfo['idPanier'];
        
        // Supprimer l'article
        $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idLignePanier = ?");
        $stmt->execute([$idItem]);
        
        // Mettre à jour la date du panier
        $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
        $stmt->execute([$idPanier]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Produit retiré du panier'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur suppression panier: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Modifie la quantité d'un produit
 */
function modifierQuantite($pdo, $input) {
    $idLignePanier = filter_var($input['idLignePanier'] ?? 0, FILTER_VALIDATE_INT);
    $quantite = filter_var($input['quantite'] ?? 1, FILTER_VALIDATE_INT);
    
    if (!$idLignePanier || $idLignePanier <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID ligne panier invalide']);
        return;
    }
    
    if ($quantite < 1) {
        // Si quantité = 0, supprimer l'article
        supprimerDuPanier($pdo, ['idLignePanier' => $idLignePanier]);
        return;
    }
    
    $client_id = $_SESSION['client_id'] ?? null;
    
    if (!$client_id) {
        echo json_encode(['success' => false, 'message' => 'Client non identifié']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Vérifier que l'item appartient au client
        $stmt = $pdo->prepare("
            SELECT lp.idLignePanier, lp.idPanier
            FROM LignePanier lp
            INNER JOIN Panier p ON lp.idPanier = p.idPanier
            WHERE lp.idLignePanier = ? AND p.idClient = ?
        ");
        $stmt->execute([$idLignePanier, $client_id]);
        $itemInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$itemInfo) {
            throw new Exception("Article non trouvé");
        }
        
        // Mettre à jour la quantité
        $stmt = $pdo->prepare("UPDATE LignePanier SET quantite = ? WHERE idLignePanier = ?");
        $stmt->execute([$quantite, $idLignePanier]);
        
        // Mettre à jour la date du panier
        $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
        $stmt->execute([$itemInfo['idPanier']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Quantité mise à jour'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur modification panier: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Vide le panier
 */
function viderPanier($pdo) {
    $client_id = $_SESSION['client_id'] ?? null;
    
    if (!$client_id) {
        echo json_encode(['success' => true, 'message' => 'Panier déjà vide']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            SELECT idPanier FROM Panier 
            WHERE idClient = ?
            ORDER BY dateModification DESC LIMIT 1
        ");
        $stmt->execute([$client_id]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($panier) {
            $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ?");
            $stmt->execute([$panier['idPanier']]);
            
            $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
            $stmt->execute([$panier['idPanier']]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Panier vidé avec succès'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur vidage panier: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Erreur lors du vidage du panier'
        ]);
    }
}

/**
 * Initialise le checkout
 */
function initCheckout($pdo) {
    try {
        $result = getPanierData($pdo);
        
        if (!$result['success'] || empty($result['panier']['articles'])) {
            echo json_encode([
                'success' => false, 
                'message' => 'Panier vide'
            ]);
            return;
        }
        
        // Stocker les infos en session pour le checkout
        $_SESSION['checkout_data'] = [
            'panier' => $result['panier']['articles'],
            'sous_total' => $result['sous_total'],
            'total_items' => $result['total_items'],
            'timestamp' => time()
        ];
        
        echo json_encode([
            'success' => true,
            'message' => 'Checkout initialisé',
            'redirect' => 'livraison_form.php'
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur initCheckout: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Erreur lors de l\'initialisation'
        ]);
    }
}

/**
 * Récupère les données du panier (helper)
 */
function getPanierData($pdo) {
    $client_id = $_SESSION['client_id'] ?? null;
    $result = [
        'success' => false,
        'panier' => ['articles' => []],
        'sous_total' => 0,
        'total_items' => 0
    ];
    
    if (!$client_id) {
        return $result;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT idPanier FROM Panier 
            WHERE idClient = ?
            ORDER BY dateModification DESC LIMIT 1
        ");
        $stmt->execute([$client_id]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($panier) {
            $stmt = $pdo->prepare("
                SELECT 
                    lp.idLignePanier,
                    lp.idOrigami,
                    lp.quantite,
                    lp.prixUnitaire,
                    (lp.quantite * lp.prixUnitaire) as total_ligne,
                    o.nom as produit_nom,
                    o.description,
                    o.photo
                FROM LignePanier lp
                JOIN Origami o ON lp.idOrigami = o.idOrigami
                WHERE lp.idPanier = ? AND o.visible = 1
            ");
            $stmt->execute([$panier['idPanier']]);
            $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $sous_total = 0;
            $total_items = 0;
            
            foreach ($articles as $article) {
                $sous_total += $article['total_ligne'];
                $total_items += $article['quantite'];
            }
            
            $result = [
                'success' => true,
                'panier' => [
                    'id_panier' => $panier['idPanier'],
                    'articles' => $articles
                ],
                'sous_total' => $sous_total,
                'total_items' => $total_items
            ];
        }
        
    } catch (Exception $e) {
        error_log("Erreur getPanierData: " . $e->getMessage());
    }
    
    return $result;
}

/**
 * Test de l'API
 */
function testAPI($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM Origami WHERE visible = 1");
        $count = $stmt->fetchColumn();
        
        $session_id = session_id();
        $client_id = $_SESSION['client_id'] ?? null;
        
        // Tester la récupération du panier
        $panierData = getPanierData($pdo);
        
        echo json_encode([
            'success' => true,
            'message' => 'API panier fonctionnelle',
            'timestamp' => time(),
            'session' => [
                'id' => $session_id,
                'client_id' => $client_id,
                'is_logged_in' => $client_id ? true : false
            ],
            'database' => [
                'connected' => true,
                'produits_visibles' => (int)$count,
                'table_structure' => 'Panier + LignePanier + Origami'
            ],
            'panier_test' => [
                'count_articles' => count($panierData['panier']['articles'] ?? []),
                'total_items' => $panierData['total_items'] ?? 0,
                'sous_total' => $panierData['sous_total'] ?? 0
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur test API: ' . $e->getMessage()
        ]);
    }
}
?>
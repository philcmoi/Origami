<?php
// ============================================
// session_verification.php - GESTION UNIFIÉE DES SESSIONS
// Fusion avec session_config.php - Version finale
// ============================================

// ============================================
// 1. DÉMARRAGE DE LA SESSION
// ============================================

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    // Configuration uniforme des sessions
    $sessionPath = __DIR__ . '/sessions';
    if (!is_dir($sessionPath) && is_writable(__DIR__)) {
        mkdir($sessionPath, 0755, true);
    }
    
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        ini_set('session.save_path', $sessionPath);
    }
    ini_set('session.gc_maxlifetime', 86400); // 24 heures
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    
    session_start();
}

// ============================================
// 2. CONSTANTES DE SESSION UNIFIÉES
// ============================================
define('SESSION_KEY_PANIER', 'panier');
define('SESSION_KEY_PANIER_ID', 'panier_id');
define('SESSION_KEY_CHECKOUT', 'checkout');
define('SESSION_KEY_COMMANDE', 'commande_en_cours');
define('SESSION_KEY_CLIENT_ID', 'client_id');
define('SESSION_KEY_MESSAGES', 'messages');
define('SESSION_KEY_ERRORS', 'checkout_errors');

// Structure standard du checkout
define('CHECKOUT_STRUCTURE', json_encode([
    'panier_id' => null,
    'client_id' => null,
    'client_email' => null,
    'adresse_livraison' => [],
    'adresse_facturation' => [],
    'mode_livraison' => 'standard',
    'emballage_cadeau' => false,
    'instructions' => null,
    'etape' => 'livraison',
    'date_creation' => null,
    'date_modification' => null,
    'validation' => [
        'panier_valide' => false,
        'adresse_valide' => false,
        'paiement_autorise' => false
    ]
]));

// ============================================
// 3. CONSTANTES DE CONFIGURATION BDD
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'origami');
define('DB_USER', 'Philippe');
define('DB_PASS', 'l@99339R');
define('DB_CHARSET', 'utf8mb4');

// Cache pour les résultats
$GLOBALS['cart_cache'] = null;
$GLOBALS['cart_cache_time'] = 0;
$GLOBALS['cart_cache_ttl'] = 30;

// ============================================
// 4. FONCTIONS DE CONNEXION BDD
// ============================================

/**
 * Établit une connexion PDO à la base de données
 * @return PDO|null Retourne l'objet PDO ou null en cas d'échec
 */
function getPDOConnection() {
    static $pdo = null;
    
    // Retourner la connexion existante si elle est toujours valide
    if ($pdo !== null) {
        try {
            $pdo->query("SELECT 1");
            return $pdo;
        } catch (PDOException $e) {
            error_log("Connexion PDO perdue, reconnexion...");
            $pdo = null;
        }
    }
    
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci",
            PDO::ATTR_TIMEOUT => 5
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        error_log("Connexion BDD établie avec succès");
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Erreur de connexion BDD: " . $e->getMessage());
        return null;
    }
}

/**
 * Vérifie la connexion à la base de données
 * @return bool True si la connexion est OK
 */
function checkDatabaseConnection() {
    $pdo = getPDOConnection();
    if (!$pdo) return false;
    try {
        $pdo->query("SELECT 1");
        return true;
    } catch (Exception $e) {
        error_log("checkDatabaseConnection: " . $e->getMessage());
        return false;
    }
}

/**
 * Alias pour compatibilité ascendante
 */
function getConnexionBD() {
    return getPDOConnection();
}

// ============================================
// 5. FONCTIONS DE GESTION DE SESSION
// ============================================

/**
 * Initialise le panier en session s'il n'existe pas
 */
function initPanier() {
    if (!isset($_SESSION[SESSION_KEY_PANIER])) {
        $_SESSION[SESSION_KEY_PANIER] = [];
    }
}

/**
 * Initialise/Réinitialise le checkout avec la structure standard
 */
function initCheckout($panier_id = null) {
    $_SESSION[SESSION_KEY_CHECKOUT] = json_decode(CHECKOUT_STRUCTURE, true);
    $_SESSION[SESSION_KEY_CHECKOUT]['panier_id'] = $panier_id;
    $_SESSION[SESSION_KEY_CHECKOUT]['date_creation'] = date('Y-m-d H:i:s');
    $_SESSION[SESSION_KEY_CHECKOUT]['date_modification'] = date('Y-m-d H:i:s');
}

/**
 * Ajoute un message en session
 * @param string $message Le message
 * @param string $type Type de message (success, error, info)
 */
function addSessionMessage($message, $type = 'success') {
    if (!isset($_SESSION[SESSION_KEY_MESSAGES])) {
        $_SESSION[SESSION_KEY_MESSAGES] = [];
    }
    $_SESSION[SESSION_KEY_MESSAGES][] = [
        'message' => $message,
        'type' => $type,
        'date' => date('Y-m-d H:i:s')
    ];
}

/**
 * Récupère et efface les messages de session
 * @return array
 */
function getSessionMessages() {
    $messages = $_SESSION[SESSION_KEY_MESSAGES] ?? [];
    unset($_SESSION[SESSION_KEY_MESSAGES]);
    return $messages;
}

/**
 * Ajoute des erreurs de formulaire
 */
function addCheckoutErrors($errors) {
    $_SESSION[SESSION_KEY_ERRORS] = $errors;
}

/**
 * Récupère et efface les erreurs
 */
function getCheckoutErrors() {
    $errors = $_SESSION[SESSION_KEY_ERRORS] ?? [];
    unset($_SESSION[SESSION_KEY_ERRORS]);
    return $errors;
}

/**
 * Nettoyer complètement la session de l'utilisateur
 */
function cleanUserSession() {
    unset($_SESSION[SESSION_KEY_PANIER]);
    unset($_SESSION[SESSION_KEY_PANIER_ID]);
    unset($_SESSION[SESSION_KEY_CHECKOUT]);
    unset($_SESSION[SESSION_KEY_COMMANDE]);
    unset($_SESSION[SESSION_KEY_CLIENT_ID]);
    cleanPayPalFlags();
}

// ============================================
// 6. FONCTIONS DE GESTION DU PANIER
// ============================================

/**
 * Vérifie si le panier contient des articles
 * @return bool
 */
function hasValidCart() {
    initPanier();
    
    // Vérifier d'abord la session
    if (!empty($_SESSION[SESSION_KEY_PANIER])) {
        return true;
    }
    
    // Si session vide, essayer de récupérer via l'API
    static $apiChecked = false;
    if (!$apiChecked) {
        $apiChecked = true;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . '/acheter.php?action=get_panier');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data['status'] === 200 && !empty($data['data']['articles'])) {
                // Reconstruire le panier en session
                $_SESSION[SESSION_KEY_PANIER] = [];
                foreach ($data['data']['articles'] as $article) {
                    $_SESSION[SESSION_KEY_PANIER][] = [
                        'id_produit' => $article['idOrigami'],
                        'quantite' => $article['quantite'],
                        'prix' => $article['prixUnitaire'],
                        'nom' => $article['nom']
                    ];
                }
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Compte le nombre d'articles dans le panier
 * @param bool $forceRefresh Forcer le rafraîchissement du cache
 * @return int Nombre total d'articles
 */
function countCartItems($forceRefresh = false) {
    // Vérifier le cache
    if (!$forceRefresh && 
        $GLOBALS['cart_cache'] !== null && 
        (time() - $GLOBALS['cart_cache_time']) < $GLOBALS['cart_cache_ttl']) {
        return $GLOBALS['cart_cache'];
    }
    
    initPanier();
    $total = 0;
    foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
        $total += intval($item['quantite'] ?? 0);
    }
    
    // Mettre en cache
    $GLOBALS['cart_cache'] = $total;
    $GLOBALS['cart_cache_time'] = time();
    
    return $total;
}

/**
 * Récupère le contenu complet du panier
 * @return array
 */
function getCartItems() {
    initPanier();
    return $_SESSION[SESSION_KEY_PANIER];
}

/**
 * Ajoute un article au panier
 * @param int $id_produit ID du produit
 * @param int $quantite Quantité
 * @param float|null $prix Prix unitaire
 * @return bool
 */
function addToCart($id_produit, $quantite = 1, $prix = null) {
    initPanier();
    
    // Vérifier si le produit existe déjà
    foreach ($_SESSION[SESSION_KEY_PANIER] as &$item) {
        if ($item['id_produit'] == $id_produit) {
            $item['quantite'] += $quantite;
            if ($prix !== null) {
                $item['prix'] = $prix;
            }
            return true;
        }
    }
    
    // Ajouter un nouvel article
    $_SESSION[SESSION_KEY_PANIER][] = [
        'id_produit' => $id_produit,
        'quantite' => $quantite,
        'prix' => $prix,
        'nom' => null
    ];
    return true;
}

/**
 * Supprime un article du panier
 * @param int $id_produit ID du produit
 * @return bool
 */
function removeFromCart($id_produit) {
    initPanier();
    foreach ($_SESSION[SESSION_KEY_PANIER] as $key => $item) {
        if ($item['id_produit'] == $id_produit) {
            unset($_SESSION[SESSION_KEY_PANIER][$key]);
            $_SESSION[SESSION_KEY_PANIER] = array_values($_SESSION[SESSION_KEY_PANIER]);
            return true;
        }
    }
    return false;
}

/**
 * Vide complètement le panier
 */
function clearCart() {
    $_SESSION[SESSION_KEY_PANIER] = [];
    unset($_SESSION[SESSION_KEY_PANIER_ID]);
    $GLOBALS['cart_cache'] = null;
}

// ============================================
// 7. FONCTIONS DE GESTION DES PRODUITS
// ============================================

/**
 * Récupère les détails d'un produit
 * @param int $id_produit ID du produit
 * @param PDO|null $pdo Connexion PDO optionnelle
 * @return array|null
 */
function getProductDetails($id_produit, $pdo = null) {
    if (!$pdo) {
        $pdo = getPDOConnection();
    }
    if (!$pdo) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT idOrigami as id_produit, nom, description, photo as image, prixHorsTaxe as prix_ht, 
                   prixHorsTaxe as prix_ttc, 'origami' as type
            FROM Origami 
            WHERE idOrigami = ? AND visible = 1
        ");
        $stmt->execute([$id_produit]);
        $produit = $stmt->fetch();
        
        if ($produit && empty($produit['image'])) {
            $produit['image'] = 'img/default-product.jpg';
        }
        
        return $produit ?: null;
        
    } catch (Exception $e) {
        error_log("Erreur getProductDetails: " . $e->getMessage());
        return null;
    }
}

// ============================================
// 8. FONCTIONS DE GESTION DE LA LIVRAISON
// ============================================

/**
 * Vérifie si l'utilisateur peut accéder à la page de livraison
 */
function checkLivraisonAccess() {
    if (!hasValidCart()) {
        addSessionMessage('Votre panier est vide.', 'error');
        header('Location: panier.html');
        exit;
    }
}

/**
 * Vérifie si l'utilisateur peut accéder à la page de paiement
 */
function checkPaiementAccess() {
    if (!hasValidCart()) {
        addSessionMessage('Votre panier est vide.', 'error');
        header('Location: panier.html');
        exit;
    }
    
    if (!hasShippingAddress()) {
        addSessionMessage('Veuillez d\'abord renseigner votre adresse de livraison.', 'error');
        header('Location: livraison_form.php');
        exit;
    }
}

/**
 * Vérifie si une adresse de livraison est définie
 * @return bool
 */
function hasShippingAddress() {
    return isset($_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']) && 
           !empty($_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']);
}

// ============================================
// 9. FONCTIONS DE SYNCHRONISATION PANIER
// ============================================

/**
 * Synchronise le panier session avec la BDD
 * @param PDO $pdo Connexion PDO
 * @param string $session_id ID de session
 */
function synchroniserPanierSessionBDD($pdo, $session_id) {
    if (!$pdo) return;
    
    try {
        // Vérifier si la table Panier existe
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'Panier'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) return;
        
        // Récupérer le client ID
        $client_id = $_SESSION[SESSION_KEY_CLIENT_ID] ?? null;
        
        if ($client_id) {
            // Vérifier si un panier BDD existe pour ce client
            $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
            $stmt->execute([$client_id]);
            $panier_bdd = $stmt->fetch();
            
            if ($panier_bdd) {
                $_SESSION[SESSION_KEY_PANIER_ID] = $panier_bdd['idPanier'];
            } elseif (!empty($_SESSION[SESSION_KEY_PANIER])) {
                // Créer un nouveau panier
                $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
                $stmt->execute([$client_id]);
                $_SESSION[SESSION_KEY_PANIER_ID] = $pdo->lastInsertId();
            }
        }
    } catch (Exception $e) {
        error_log("Erreur synchronisation panier: " . $e->getMessage());
    }
}

// ============================================
// 10. FONCTIONS DE CALCUL DES TOTAUX
// ============================================

/**
 * Calcule les totaux du panier
 * @param array $panier_details Détails du panier
 * @param array $checkout Données de checkout
 * @return array
 */
function calculerTotauxPanier($panier_details, $checkout = []) {
    $sous_total = 0;
    $total_items = 0;
    
    foreach ($panier_details as $item) {
        $sous_total += floatval($item['prix_total'] ?? 0);
        $total_items += intval($item['quantite'] ?? 0);
    }
    
    // Frais de livraison
    $mode_livraison = $checkout['mode_livraison'] ?? 'standard';
    $frais_livraison = 0;
    
    if ($mode_livraison === 'express') {
        $frais_livraison = 9.90;
    } elseif ($mode_livraison === 'relais') {
        $frais_livraison = 4.90;
    } elseif ($sous_total < 50) {
        $frais_livraison = 4.90;
    }
    
    $frais_emballage = ($checkout['emballage_cadeau'] ?? false) ? 3.90 : 0;
    $total = $sous_total + $frais_livraison + $frais_emballage;
    
    return [
        'sous_total' => round($sous_total, 2),
        'frais_livraison' => round($frais_livraison, 2),
        'frais_emballage' => round($frais_emballage, 2),
        'total' => round($total, 2),
        'total_items' => $total_items,
        'seuil_livraison_gratuite' => 50
    ];
}

// ============================================
// 11. FONCTIONS DE GESTION PAYPAL
// ============================================

/**
 * Nettoie les flags PayPal en session
 */
function cleanPayPalFlags() {
    unset($_SESSION['paypal_processing']);
    unset($_SESSION['paypal_order_id']);
    unset($_SESSION['paypal_commande_id']);
    unset($_SESSION['paypal_token']);
    unset($_SESSION['paypal_montant']);
}

// ============================================
// 12. FONCTIONS D'INFORMATION SESSION
// ============================================

/**
 * Vérifie si l'utilisateur est connecté
 * @return bool
 */
function isUserLoggedIn() {
    return isset($_SESSION[SESSION_KEY_CLIENT_ID]) && $_SESSION[SESSION_KEY_CLIENT_ID] > 0;
}

/**
 * Récupère l'ID du client connecté
 * @return int|null
 */
function getCurrentClientId() {
    return $_SESSION[SESSION_KEY_CLIENT_ID] ?? null;
}

/**
 * Récupère des informations sur la session
 * @return array
 */
function getSessionInfo() {
    return [
        'session_id' => session_id(),
        'client_id' => $_SESSION[SESSION_KEY_CLIENT_ID] ?? null,
        'is_logged_in' => isUserLoggedIn(),
        'cart_count' => countCartItems(),
        'session_status' => session_status(),
        'has_cart' => hasValidCart(),
        'has_address' => hasShippingAddress(),
        'timestamp' => time()
    ];
}

// ============================================
// 13. FONCTIONS DE NETTOYAGE
// ============================================

/**
 * Nettoie les tokens expirés
 * @param PDO $pdo Connexion PDO
 */
function nettoyerTokensExpires($pdo) {
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare("DELETE FROM tokens_confirmation WHERE expiration < NOW()");
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Erreur nettoyage tokens: " . $e->getMessage());
    }
}

/**
 * Nettoie les clients temporaires obsolètes
 * @param PDO $pdo Connexion PDO
 */
function nettoyerClientsTemporairesAmeliore($pdo) {
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare("
            DELETE FROM Client 
            WHERE type = 'temporaire' 
            AND date_creation < DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND idClient NOT IN (SELECT DISTINCT idClient FROM Commande WHERE idClient IS NOT NULL)
        ");
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Erreur nettoyage clients: " . $e->getMessage());
    }
}

// ============================================
// 14. INITIALISATION
// ============================================

// Initialiser le panier
initPanier();

// Initialiser le checkout si inexistant
if (!isset($_SESSION[SESSION_KEY_CHECKOUT])) {
    initCheckout();
}

// Nettoyage périodique (10% des requêtes)
if (rand(1, 100) <= 10) {
    $pdo = getPDOConnection();
    if ($pdo) {
        if (function_exists('nettoyerTokensExpires')) {
            nettoyerTokensExpires($pdo);
        }
        if (function_exists('nettoyerClientsTemporairesAmeliore')) {
            nettoyerClientsTemporairesAmeliore($pdo);
        }
    }
    
    // Vider le cache si trop vieux
    if ($GLOBALS['cart_cache_time'] > 0 && (time() - $GLOBALS['cart_cache_time']) > 3600) {
        $GLOBALS['cart_cache'] = null;
        $GLOBALS['cart_cache_time'] = 0;
    }
}

// Vérification rapide de la BDD au chargement
if (!isset($GLOBALS['db_check_done'])) {
    $GLOBALS['db_check_done'] = true;
    if (!checkDatabaseConnection()) {
        error_log("ATTENTION: La connexion à la base de données a échoué au démarrage");
    }
}

error_log("session_verification.php chargé - Session ID: " . session_id());
?>
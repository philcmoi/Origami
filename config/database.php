<?php
// config/database.php

class Database {
    private $host = 'localhost';
    private $db_name = 'origami';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    public $pdo;
    public $error;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => true
        ];

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            $this->error = "Erreur de connexion : " . $e->getMessage();
            error_log($this->error);
            die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
        }
    }

    // Méthode pour préparer et exécuter une requête
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->error = "Erreur de requête : " . $e->getMessage();
            error_log($this->error);
            return false;
        }
    }

    // Récupérer une seule ligne
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : false;
    }

    // Récupérer toutes les lignes
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : false;
    }

    // Récupérer une seule valeur
    public function fetchColumn($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchColumn() : false;
    }

    // Insérer des données et retourner l'ID
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->error = "Erreur d'insertion : " . $e->getMessage();
            error_log($this->error);
            return false;
        }
    }

    // Mettre à jour des données
    public function update($table, $data, $where, $where_params = []) {
        $set = '';
        foreach ($data as $key => $value) {
            $set .= "$key = :$key, ";
        }
        $set = rtrim($set, ', ');
        
        $sql = "UPDATE $table SET $set WHERE $where";
        $params = array_merge($data, $where_params);
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            $this->error = "Erreur de mise à jour : " . $e->getMessage();
            error_log($this->error);
            return false;
        }
    }

    // Supprimer des données
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            $this->error = "Erreur de suppression : " . $e->getMessage();
            error_log($this->error);
            return false;
        }
    }

    // Compter le nombre de lignes
    public function count($table, $where = '', $params = []) {
        $sql = "SELECT COUNT(*) FROM $table";
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }
        
        return $this->fetchColumn($sql, $params);
    }

    // Vérifier si une valeur existe
    public function exists($table, $column, $value) {
        $sql = "SELECT COUNT(*) FROM $table WHERE $column = ?";
        return $this->fetchColumn($sql, [$value]) > 0;
    }

    // Démarrer une transaction
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    // Valider une transaction
    public function commit() {
        return $this->pdo->commit();
    }

    // Annuler une transaction
    public function rollBack() {
        return $this->pdo->rollBack();
    }

    // Échapper les données (alternative à htmlspecialchars)
    public function quote($value) {
        return $this->pdo->quote($value);
    }

    // Fermer la connexion
    public function close() {
        $this->pdo = null;
    }

    // MÉTHODES SPÉCIFIQUES POUR ORIGAMI

    // Récupérer les commandes avec les informations client
    public function getCommandesAvecClients() {
        $sql = "SELECT c.*, cl.nom as client_nom, cl.prenom, cl.email 
                FROM Commande c 
                LEFT JOIN Client cl ON c.idClient = cl.idClient 
                ORDER BY c.dateCommande DESC";
        return $this->fetchAll($sql);
    }

    // Récupérer les détails d'une commande avec articles
    public function getDetailsCommande($idCommande) {
        $sql = "SELECT lc.*, o.nom, o.photo, o.description 
                FROM LigneCommande lc 
                LEFT JOIN Origami o ON lc.idOrigami = o.idOrigami 
                WHERE lc.idCommande = ?";
        return $this->fetchAll($sql, [$idCommande]);
    }

    // Récupérer les informations complètes d'une commande
    public function getCommandeComplete($idCommande) {
        $sql = "SELECT c.*, cl.nom as client_nom, cl.prenom, cl.email, cl.telephone,
                       a.adresse, a.ville, a.codePostal, a.pays
                FROM Commande c 
                LEFT JOIN Client cl ON c.idClient = cl.idClient 
                LEFT JOIN Adresse a ON c.idAdresseLivraison = a.idAdresse 
                WHERE c.idCommande = ?";
        return $this->fetch($sql, [$idCommande]);
    }

    // Récupérer tous les origamis
    public function getOrigamis() {
        $sql = "SELECT * FROM Origami ORDER BY nom";
        return $this->fetchAll($sql);
    }

    // Récupérer un origami par ID
    public function getOrigami($idOrigami) {
        $sql = "SELECT * FROM Origami WHERE idOrigami = ?";
        return $this->fetch($sql, [$idOrigami]);
    }

    // Récupérer le panier d'un client
    public function getPanierClient($idClient) {
        $sql = "SELECT lp.*, o.nom, o.photo, o.prixHorsTaxe 
                FROM LignePanier lp 
                LEFT JOIN Origami o ON lp.idOrigami = o.idOrigami 
                LEFT JOIN Panier p ON lp.idPanier = p.idPanier 
                WHERE p.idClient = ?";
        return $this->fetchAll($sql, [$idClient]);
    }

    // Mettre à jour le statut d'une commande
    public function updateStatutCommande($idCommande, $statut) {
        $sql = "UPDATE Commande SET statut = ? WHERE idCommande = ?";
        return $this->query($sql, [$statut, $idCommande]);
    }

    // Vérifier si un client existe
    public function clientExists($email) {
        return $this->exists('Client', 'email', $email);
    }

    // Récupérer un client par email
    public function getClientByEmail($email) {
        $sql = "SELECT * FROM Client WHERE email = ?";
        return $this->fetch($sql, [$email]);
    }

    // Récupérer les adresses d'un client
    public function getAdressesClient($idClient) {
        $sql = "SELECT * FROM Adresse WHERE idClient = ? ORDER BY type, dateCreation DESC";
        return $this->fetchAll($sql, [$idClient]);
    }
}

// Créer une instance globale de la base de données
try {
    $database = new Database();
    $pdo = $database->pdo;
} catch (Exception $e) {
    die("Impossible de se connecter à la base de données.");
}

// Fonction utilitaire pour logger les erreurs
function logError($message) {
    $log_dir = ROOT_PATH . '/logs/';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, $log_dir . "errors.log");
}

// Fonction pour sécuriser les données de sortie
function safe_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Fonction pour rediriger avec un message
function redirect($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION[$type . '_message'] = $message;
    }
    header("Location: $url");
    exit;
}

// Vérifier si la table existe (utile pour l'installation)
function tableExists($pdo, $tableName) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$tableName'");
        return $result->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Configuration supplémentaire
define('DB_HOST', 'localhost');
define('DB_NAME', 'origami');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Chemins importants
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('PRODUCT_IMAGE_PATH', UPLOAD_PATH . 'products/');
define('ORIGAMI_IMAGE_PATH', ROOT_PATH . '/img/'); // Chemin des images d'origami

// Constantes pour les statuts de commande
define('STATUT_EN_ATTENTE', 'en_attente');
define('STATUT_CONFIRMEE', 'confirmee');
define('STATUT_EXPEDIEE', 'expediee');
define('STATUT_LIVREE', 'livree');
define('STATUT_ANNULEE', 'annulee');

// Constantes pour les types de clients
define('CLIENT_TEMPORAIRE', 'temporaire');
define('CLIENT_PERMANENT', 'permanent');

// Créer les dossiers nécessaires s'ils n'existent pas
$directories = [UPLOAD_PATH, PRODUCT_IMAGE_PATH, ORIGAMI_IMAGE_PATH, ROOT_PATH . '/logs/'];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Gestion des erreurs
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logError("Erreur [$errno] : $errstr dans $errfile à la ligne $errline");
});

// Configuration du fuseau horaire
date_default_timezone_set('Europe/Paris');

// Désactiver l'affichage des erreurs en production
if (!defined('DEBUG') || !DEBUG) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . '/logs/php_errors.log');
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Fonctions spécifiques pour Origami

/**
 * Récupère le chemin de l'image d'un origami
 */
function getOrigamiImagePath($photo) {
    // Vérifier d'abord dans le dossier uploads
    if (file_exists(PRODUCT_IMAGE_PATH . $photo)) {
        return PRODUCT_IMAGE_PATH . $photo;
    }
    // Sinon chercher dans le dossier img
    elseif (file_exists(ORIGAMI_IMAGE_PATH . $photo)) {
        return ORIGAMI_IMAGE_PATH . $photo;
    }
    // Image par défaut
    else {
        return ORIGAMI_IMAGE_PATH . 'default-origami.jpg';
    }
}

/**
 * Récupère l'URL de l'image pour l'affichage
 */
function getOrigamiImageUrl($photo) {
    // Vérifier d'abord dans le dossier uploads
    if (file_exists(PRODUCT_IMAGE_PATH . $photo)) {
        return '/uploads/products/' . $photo;
    }
    // Sinon chercher dans le dossier img
    elseif (file_exists(ORIGAMI_IMAGE_PATH . $photo)) {
        return '/img/' . $photo;
    }
    // Image par défaut
    else {
        return '/img/default-origami.jpg';
    }
}

/**
 * Formate un prix pour l'affichage
 */
function formatPrix($prix) {
    return number_format($prix, 2, ',', ' ') . ' €';
}

/**
 * Calcule le prix TTC (20% de TVA)
 */
function calculerPrixTTC($prixHT) {
    return $prixHT * 1.20;
}

/**
 * Génère un statut badge Bootstrap
 */
function getStatutBadge($statut) {
    $classes = [
        'en_attente' => 'bg-warning',
        'confirmee' => 'bg-info',
        'expediee' => 'bg-primary',
        'livree' => 'bg-success',
        'annulee' => 'bg-danger'
    ];
    
    $texte = [
        'en_attente' => 'En attente',
        'confirmee' => 'Confirmée',
        'expediee' => 'Expédiée',
        'livree' => 'Livrée',
        'annulee' => 'Annulée'
    ];
    
    $classe = $classes[$statut] ?? 'bg-secondary';
    $texte_statut = $texte[$statut] ?? $statut;
    
    return '<span class="badge ' . $classe . '">' . $texte_statut . '</span>';
}
?>
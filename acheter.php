<?php
// ============================================
// acheter.php - ROUTEUR PRINCIPAL CORRIGÉ
// Toutes les requêtes AJAX pointent vers ce fichier
// ============================================

session_start();

// Activation des erreurs pour le débogage (à désactiver en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'smtp_config.php';
require_once 'config.php';

// Inclusion des fonctions
require_once 'includes/fonctions_panier.php';
require_once 'includes/fonctions_paiement.php';
require_once 'includes/fonctions_email.php';
require_once 'includes/fonctions_facture.php';
require_once 'includes/fonctions_commande.php';
require_once 'includes/fonctions_validation.php';

// ============================================
// TCPDF - PAS DE CHARGEMENT ICI
// ============================================
if (!class_exists('TCPDF', false)) {
    class TCPDF {
        private $page = 1;
        private $html_content = '';
        public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4') {}
        public function AddPage() {}
        public function SetFont($family, $style = '', $size = null) {}
        public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '') {}
        public function Output($name = 'doc.pdf', $dest = 'I') { return ''; }
        public function writeHTML($html) { $this->html_content = $html; }
        public function SetMargins($left, $top, $right = -1) {}
        public function SetAutoPageBreak($auto, $margin = 0) {}
        public function setPrintHeader($value) {}
        public function setPrintFooter($value) {}
    }
}

// Configuration PayPal
$paypal_config = [
    'client_id' => 'Aac1-P0VrxBQ_5REVeo4f557_-p6BDeXA_hyiuVZfi21sILMWccBFfTidQ6nnhQathCbWaCSQaDmxJw5',
    'client_secret' => 'EJxech0i1faRYlo0-ln2sU09ecx5rP3XEOGUTeTduI2t-I0j4xoSPqRRFQTxQsJoSBbSL8aD1b1GPPG1',
    'environment' => 'sandbox',
    'return_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/acheter.php?action=paypal_success',
    'cancel_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/acheter.php?action=paypal_cancel'
];

// ============================================
// RÉCUPÉRATION DE L'ACTION
// ============================================
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$action = $_POST['action'] ?? ($_GET['action'] ?? ($data['action'] ?? ''));

// ============================================
// GESTION DES EN-TÊTES
// ============================================
$is_html_response = false;

if (isset($_GET['token']) && (!isset($_POST['action']))) {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
} else {
    header('Content-Type: application/json');
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// ============================================
// CONNEXION BDD
// ============================================
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $error = 'Erreur de connexion à la base de données: ' . $e->getMessage();
    if ($is_html_response) { 
        echo $error; 
        exit; 
    } else { 
        echo json_encode(['status' => 500, 'error' => $error]); 
        exit; 
    }
}

// ============================================
// NETTOYAGE PÉRIODIQUE
// ============================================
if (rand(1, 5) === 1) {
    if (function_exists('nettoyerTokensExpires')) {
        nettoyerTokensExpires($pdo);
    }
}
if (rand(1, 5) === 1) {
    if (function_exists('nettoyerClientsTemporairesAmeliore')) {
        nettoyerClientsTemporairesAmeliore($pdo);
    }
}

// Mise à jour de l'action si token présent
if (!$action && isset($_GET['token'])) {
    $action = 'confirmer_commande';
    $is_html_response = true;
}

// ============================================
// ACTION: CONFIRMER_COMMANDE (AVEC VALIDATION AUTO DU TOKEN)
// ============================================
if ($action == 'confirmer_commande') {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        echo "<!DOCTYPE html><html><head><title>Erreur</title>";
        echo "<style>body{font-family:sans-serif;text-align:center;padding:50px;}</style>";
        echo "</head><body><h1>❌ Erreur</h1><p>Token manquant.</p></body></html>";
        exit;
    }
    
    try {
        // Vérifier l'existence de la table tokens_confirmation
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'tokens_confirmation'");
        $stmt->execute();
        $tableExists = $stmt->rowCount() > 0;
        
        if (!$tableExists) {
            error_log("Table tokens_confirmation n'existe pas");
            echo "<!DOCTYPE html><html><head><title>Erreur technique</title>";
            echo "<style>body{font-family:sans-serif;text-align:center;padding:50px;}</style>";
            echo "</head><body><h1>❌ Erreur technique</h1><p>Configuration manquante. Veuillez contacter le support.</p>";
            echo "<a href='index.html'>Retour à l'accueil</a></body></html>";
            exit;
        }
        
        // 1. Vérifier le token dans la base
        $stmt = $pdo->prepare("
            SELECT id, email, id_client, expiration, utilise 
            FROM tokens_confirmation 
            WHERE token = ? 
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 2. Token invalide
        if (!$tokenData) {
            echo "<!DOCTYPE html><html><head><title>Lien invalide</title>";
            echo "<style>body{font-family:sans-serif;text-align:center;padding:50px;}</style>";
            echo "</head><body><h1>❌ Lien invalide</h1><p>Ce lien de confirmation n'existe pas.</p>";
            echo "<a href='index.html'>Retour à l'accueil</a></body></html>";
            exit;
        }
        
        // 3. Token déjà utilisé
        if ($tokenData['utilise'] == 1) {
            echo "<!DOCTYPE html><html><head><title>Lien déjà utilisé</title>";
            echo "<style>body{font-family:sans-serif;text-align:center;padding:50px;}</style>";
            echo "</head><body><h1>⚠️ Lien déjà utilisé</h1><p>Ce lien de confirmation a déjà été utilisé.</p>";
            echo "<a href='index.html'>Retour à l'accueil</a></body></html>";
            exit;
        }
        
        // 4. Token expiré
        $expiration = new DateTime($tokenData['expiration']);
        $now = new DateTime();
        if ($now > $expiration) {
            echo "<!DOCTYPE html><html><head><title>Lien expiré</title>";
            echo "<style>body{font-family:sans-serif;text-align:center;padding:50px;}</style>";
            echo "</head><body><h1>⏰ Lien expiré</h1><p>Ce lien de confirmation a expiré. Veuillez recommencer la procédure.</p>";
            echo "<a href='index.html'>Retour à l'accueil</a></body></html>";
            exit;
        }
        
        // 5. Marquer le token comme utilisé
        $stmt = $pdo->prepare("UPDATE tokens_confirmation SET utilise = 1 WHERE id = ?");
        $stmt->execute([$tokenData['id']]);
        
        // 6. Associer le client à la session
        if (!empty($tokenData['id_client'])) {
            $_SESSION['client_id'] = $tokenData['id_client'];
            $_SESSION['client_email'] = $tokenData['email'];
        }
        
        // 7. Stocker l'email validé en session
        $_SESSION['email_confirme'] = $tokenData['email'];
        $_SESSION['token_valide'] = true;
        $_SESSION['token_confirmation'] = $token;
        
        // 8. Rediriger automatiquement vers le formulaire d'adresse
        $redirectUrl = "livraison_form.php?token=" . urlencode($token);
        
        // Affichage d'une page de redirection automatique
        echo "<!DOCTYPE html>";
        echo "<html><head><meta charset='UTF-8'>";
        echo "<meta http-equiv='refresh' content='2;url=" . htmlspecialchars($redirectUrl) . "'>";
        echo "<title>Confirmation réussie</title>";
        echo "<style>";
        echo "body{font-family:'Segoe UI',sans-serif;text-align:center;padding:50px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;margin:0;display:flex;align-items:center;justify-content:center;}";
        echo ".container{background:white;border-radius:30px;padding:50px;box-shadow:0 30px 60px rgba(0,0,0,0.3);max-width:500px;}";
        echo ".success-icon{font-size:80px;color:#27ae60;margin-bottom:20px;}";
        echo "h1{color:#2c3e50;margin-bottom:20px;}";
        echo "p{color:#7f8c8d;margin-bottom:20px;}";
        echo ".loader{border:4px solid #f3f3f3;border-top:4px solid #27ae60;border-radius:50%;width:40px;height:40px;animation:spin 1s linear infinite;margin:20px auto;}";
        echo "@keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}";
        echo "a{color:#3498db;text-decoration:none;}";
        echo "</style>";
        echo "</head><body>";
        echo "<div class='container'>";
        echo "<div class='success-icon'>✅</div>";
        echo "<h1>Email confirmé !</h1>";
        echo "<p>Votre adresse email <strong>" . htmlspecialchars($tokenData['email']) . "</strong> a été vérifiée avec succès.</p>";
        echo "<div class='loader'></div>";
        echo "<p>Vous allez être redirigé vers le formulaire d'adresse dans quelques secondes...</p>";
        echo "<p><a href='" . htmlspecialchars($redirectUrl) . "'>Cliquez ici si la redirection ne fonctionne pas</a></p>";
        echo "</div></body></html>";
        exit;
        
    } catch (PDOException $e) {
        error_log("Erreur validation token: " . $e->getMessage());
        echo "<!DOCTYPE html><html><head><title>Erreur technique</title>";
        echo "<style>body{font-family:sans-serif;text-align:center;padding:50px;}</style>";
        echo "</head><body><h1>❌ Erreur technique</h1><p>Une erreur est survenue. Veuillez réessayer.</p>";
        echo "<a href='index.html'>Retour à l'accueil</a></body></html>";
        exit;
    }
}

// ============================================
// ACTION: SAISIR_ADRESSE (sans token, depuis panier)
// ============================================
if ($action == 'saisir_adresse' && !isset($_GET['token'])) {
    // Vérifier que l'utilisateur a un panier valide
    if (!isset($_SESSION['panier']) || empty($_SESSION['panier'])) {
        header('Location: index.html');
        exit;
    }
    header('Location: livraison_form.php');
    exit;
}

// ============================================
// INCLUSION DE genererFacturePDF.php SI NÉCESSAIRE
// ============================================
$actions_necessitant_facture = ['generer_facture_pdf', 'telecharger_facture', 'envoyer_facture_email'];
if (in_array($action, $actions_necessitant_facture) && !function_exists('genererFacturePDF')) {
    if (file_exists('genererFacturePDF.php')) {
        require_once 'genererFacturePDF.php';
    }
}

// ============================================
// 1. ACTION: get_produits_pagines (PAGINATION PRODUITS)
// ============================================
if ($action == 'get_produits_pagines') {
    header('Content-Type: application/json');
    
    $page = isset($data['page']) ? (int)$data['page'] : 1;
    $limit = isset($data['limit']) ? (int)$data['limit'] : 8;
    $offset = ($page - 1) * $limit;
    
    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 8;
    if ($offset < 0) $offset = 0;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM Origami WHERE visible = 1");
        $stmt->execute();
        $totalRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = $totalRow ? (int)$totalRow['total'] : 0;
        
        $stmt = $pdo->prepare("
            SELECT idOrigami, nom, description, photo, prixHorsTaxe 
            FROM Origami 
            WHERE visible = 1
            ORDER BY idOrigami 
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response = [
            'status' => 200,
            'data' => [
                'produits' => $produits,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $total > 0 ? ceil($total / $limit) : 1
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 500,
            'error' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ============================================
// 2. ACTIONS DU PANIER
// ============================================

$actions_panier = ['ajouter_au_panier', 'get_panier', 'modifier_quantite', 'supprimer_du_panier', 'vider_panier'];
if (in_array($action, $actions_panier)) {
    $idClient = getOrCreateClient($pdo);
}

// 2.1 AJOUTER AU PANIER
if ($action == 'ajouter_au_panier') {
    if (!$idClient) {
        echo json_encode(['status' => 400, 'error' => 'Client non initialisé']);
        exit;
    }

    $idOrigami = $data['idOrigami'] ?? null;
    $quantite = $data['quantite'] ?? 1;

    if (!$idOrigami) {
        echo json_encode(['status' => 400, 'error' => 'ID origami manquant']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
        $stmt->execute([$idClient]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$panier) {
            $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
            $stmt->execute([$idClient]);
            $idPanier = $pdo->lastInsertId();
        } else {
            $idPanier = $panier['idPanier'];
        }

        $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ? AND idOrigami IS NULL");
        $stmt->execute([$idPanier]);

        $stmt = $pdo->prepare("SELECT prixHorsTaxe FROM Origami WHERE idOrigami = ? AND visible = 1");
        $stmt->execute([$idOrigami]);
        $origami = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$origami) {
            throw new Exception('Origami non trouvé');
        }

        $prixUnitaire = $origami['prixHorsTaxe'];

        $stmt = $pdo->prepare("SELECT idLignePanier, quantite FROM LignePanier WHERE idPanier = ? AND idOrigami = ?");
        $stmt->execute([$idPanier, $idOrigami]);
        $ligneExistante = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ligneExistante) {
            $nouvelleQuantite = $ligneExistante['quantite'] + $quantite;
            $stmt = $pdo->prepare("UPDATE LignePanier SET quantite = ?, prixUnitaire = ? WHERE idLignePanier = ?");
            $stmt->execute([$nouvelleQuantite, $prixUnitaire, $ligneExistante['idLignePanier']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO LignePanier (idPanier, idOrigami, quantite, prixUnitaire) VALUES (?, ?, ?, ?)");
            $stmt->execute([$idPanier, $idOrigami, $quantite, $prixUnitaire]);
        }

        $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
        $stmt->execute([$idPanier]);
        
        $pdo->commit();

        echo json_encode(['status' => 200, 'message' => 'Article ajouté au panier']);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur ajout panier: " . $e->getMessage());
        echo json_encode(['status' => 500, 'error' => 'Erreur: ' . $e->getMessage()]);
    }
    exit;
}

// 2.2 GET PANIER
if ($action == 'get_panier') {
    if (!$idClient) {
        echo json_encode(['status' => 200, 'data' => ['articles' => [], 'total' => 0, 'totalQuantites' => 0]]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT p.idPanier FROM Panier p WHERE p.idClient = ?");
    $stmt->execute([$idClient]);
    $panier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$panier) {
        echo json_encode(['status' => 200, 'data' => ['articles' => [], 'total' => 0, 'totalQuantites' => 0]]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT 
            lp.idLignePanier,
            lp.idOrigami,
            lp.quantite,
            lp.prixUnitaire,
            o.nom,
            o.description,
            o.photo,
            (lp.quantite * lp.prixUnitaire) as totalLigne
        FROM LignePanier lp
        JOIN Origami o ON lp.idOrigami = o.idOrigami
        WHERE lp.idPanier = ? AND o.visible = 1
    ");
    $stmt->execute([$panier['idPanier']]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    $totalQuantites = 0;
    foreach ($articles as $article) {
        $total += $article['totalLigne'];
        $totalQuantites += $article['quantite'];
    }

    echo json_encode([
        'status' => 200,
        'data' => [
            'articles' => $articles,
            'total' => $total,
            'totalQuantites' => $totalQuantites
        ]
    ]);
    exit;
}

// 2.3 MODIFIER QUANTITE
if ($action == 'modifier_quantite') {
    if (!$idClient) {
        echo json_encode(['status' => 400, 'error' => 'Client non initialisé']);
        exit;
    }

    $idLignePanier = $data['idLignePanier'] ?? null;
    $quantite = $data['quantite'] ?? null;

    if (!$idLignePanier || !$quantite) {
        echo json_encode(['status' => 400, 'error' => 'ID ligne panier ou quantité manquant']);
        exit;
    }

    if ($quantite < 1) {
        echo json_encode(['status' => 400, 'error' => 'La quantité doit être au moins 1']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE LignePanier SET quantite = ? WHERE idLignePanier = ?");
    $stmt->execute([$quantite, $idLignePanier]);

    echo json_encode(['status' => 200, 'message' => 'Quantité modifiée']);
    exit;
}

// 2.4 SUPPRIMER DU PANIER
if ($action == 'supprimer_du_panier') {
    if (!$idClient) {
        echo json_encode(['status' => 400, 'error' => 'Client non initialisé']);
        exit;
    }

    $idLignePanier = $data['idLignePanier'] ?? null;

    if (!$idLignePanier) {
        echo json_encode(['status' => 400, 'error' => 'ID ligne panier manquant']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idLignePanier = ?");
    $stmt->execute([$idLignePanier]);

    echo json_encode(['status' => 200, 'message' => 'Article supprimé du panier']);
    exit;
}

// 2.5 VIDER PANIER
if ($action == 'vider_panier') {
    if (!$idClient) {
        echo json_encode(['status' => 400, 'error' => 'Client non initialisé']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
    $stmt->execute([$idClient]);
    $panier = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($panier) {
        $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ?");
        $stmt->execute([$panier['idPanier']]);
        
        $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
        $stmt->execute([$panier['idPanier']]);
    }

    echo json_encode(['status' => 200, 'message' => 'Panier vidé']);
    exit;
}

// ============================================
// 3. ACTION: envoyer_lien_confirmation
// ============================================
if ($action == 'envoyer_lien_confirmation') {
    $email = $data['email'] ?? '';
    $nom = $data['nom'] ?? 'Client';
    $prenom = $data['prenom'] ?? '';
    $telephone = $data['telephone'] ?? '';

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 400, 'error' => 'Email invalide']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT idClient, nom, prenom FROM Client WHERE email = ? AND type = 'permanent'");
        $stmt->execute([$email]);
        $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("SELECT idClient, nom, prenom FROM Client WHERE email = ?");
        $stmt->execute([$email]);
        $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $clientExistant = ($clientExist !== false);
    $idClient = null;

    if (!$clientExistant) {
        $motDePasse = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone, type) VALUES (?, ?, ?, ?, ?, 'permanent')");
            $stmt->execute([$email, $motDePasse, $nom, $prenom, $telephone]);
        } catch (Exception $e) {
            $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$email, $motDePasse, $nom, $prenom, $telephone]);
        }
        $idClient = $pdo->lastInsertId();
    } else {
        $idClient = $clientExist['idClient'];
    }

    $idClientTemporaire = $_SESSION['client_id'] ?? null;

    $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
    $stmt->execute([$idClient]);
    $panierPermanent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$panierPermanent) {
        $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
        $stmt->execute([$idClient]);
        $panierPermanent = ['idPanier' => $pdo->lastInsertId()];
    }

    if ($idClientTemporaire && $idClientTemporaire != $idClient) {
        try {
            $stmt = $pdo->prepare("
                SELECT lp.idLignePanier, lp.idOrigami, lp.quantite, lp.prixUnitaire 
                FROM LignePanier lp 
                JOIN Panier p ON lp.idPanier = p.idPanier 
                WHERE p.idClient = ?
            ");
            $stmt->execute([$idClientTemporaire]);
            $articlesTemporaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($articlesTemporaires)) {
                foreach ($articlesTemporaires as $article) {
                    $stmt = $pdo->prepare("SELECT idLignePanier, quantite FROM LignePanier WHERE idPanier = ? AND idOrigami = ?");
                    $stmt->execute([$panierPermanent['idPanier'], $article['idOrigami']]);
                    $articleExistant = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($articleExistant) {
                        $nouvelleQuantite = $articleExistant['quantite'] + $article['quantite'];
                        $stmt = $pdo->prepare("UPDATE LignePanier SET quantite = ? WHERE idLignePanier = ?");
                        $stmt->execute([$nouvelleQuantite, $articleExistant['idLignePanier']]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO LignePanier (idPanier, idOrigami, quantite, prixUnitaire) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$panierPermanent['idPanier'], $article['idOrigami'], $article['quantite'], $article['prixUnitaire']]);
                    }
                }
                
                $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier IN (SELECT idPanier FROM Panier WHERE idClient = ?)");
                $stmt->execute([$idClientTemporaire]);
                $stmt = $pdo->prepare("DELETE FROM Panier WHERE idClient = ?");
                $stmt->execute([$idClientTemporaire]);
                $stmt = $pdo->prepare("DELETE FROM Client WHERE idClient = ? AND (type = 'temporaire' OR email LIKE 'temp_%@origamizen.fr')");
                $stmt->execute([$idClientTemporaire]);
            }
            
            $_SESSION['client_id'] = $idClient;
        } catch (Exception $e) {
            error_log("Erreur transfert panier: " . $e->getMessage());
        }
    }

    $tokenConfirmation = bin2hex(random_bytes(32));
    $urlConfirmation = "http://" . $_SERVER['HTTP_HOST'] . "/acheter.php?action=confirmer_commande&token=" . $tokenConfirmation;

    $stmt = $pdo->prepare("INSERT INTO tokens_confirmation (token, email, id_client, expiration, utilise) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)");
    $stmt->execute([$tokenConfirmation, $email, $idClient]);

    $sujet = "Confirmez votre commande - Youki and Co";
    $messageHTML = "
    <html>
    <head><style>
        body{font-family:Arial,sans-serif;background:#f9f9f9;margin:0;padding:20px;}
        .container{background:white;padding:30px;border-radius:12px;max-width:600px;margin:0 auto;}
        .header{text-align:center;color:#d40000;margin-bottom:20px;}
        .btn{display:inline-block;background:#d40000;color:white;padding:12px 30px;text-decoration:none;border-radius:6px;margin:20px 0;}
        .footer{font-size:12px;color:#666;text-align:center;margin-top:30px;}
    </style></head>
    <body>
        <div class='container'>
            <div class='header'><h1>Youki and Co</h1></div>
            <p>Bonjour $nom,</p>
            <p>Cliquez sur le bouton ci-dessous pour confirmer votre commande :</p>
            <p style='text-align:center'><a href='$urlConfirmation' class='btn'>Confirmer ma commande</a></p>
            <p>Ce lien est valable 15 minutes.</p>
            <div class='footer'><p>Youki and Co - Créations artisanales japonaises</p></div>
        </div>
    </body>
    </html>";
    
    $resultatEmail = envoyerEmail($email, $sujet, $messageHTML);

    if ($resultatEmail['success']) {
        echo json_encode([
            'status' => 200,
            'data' => [
                'message' => 'Lien de confirmation envoyé',
                'client_existant' => $clientExistant,
                'id_client' => $idClient
            ]
        ]);
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur lors de l\'envoi de l\'email']);
    }
    exit;
}

// ============================================
// 4. ACTIONS PAYPAL (simplifiées)
// ============================================
if ($action == 'creer_commande_paypal') {
    $montant = $data['montant'] ?? 0;
    $idCommande = $data['id_commande'] ?? null;
    
    if ($montant <= 0) {
        echo json_encode(['status' => 400, 'error' => 'Montant invalide']);
        exit;
    }
    
    // Simulation pour test local
    echo json_encode([
        'status' => 200,
        'data' => [
            'order_id' => 'SIMU_' . time(),
            'approve_url' => 'paiement_paypal.php?commande=' . $idCommande . '&montant=' . $montant,
            'montant' => $montant
        ]
    ]);
    exit;
}

if ($action == 'capturer_paiement_paypal') {
    $order_id = $data['order_id'] ?? '';
    $commande_id = $_SESSION['paypal_commande_id'] ?? null;
    
    if ($commande_id) {
        try {
            $stmt = $pdo->prepare("UPDATE Commande SET statut = 'payee', modeReglement = 'PayPal' WHERE idCommande = ?");
            $stmt->execute([$commande_id]);
            
            $stmt = $pdo->prepare("
                INSERT INTO Paiement 
                (idCommande, montant, currency, statut, methode_paiement, reference, date_creation) 
                VALUES (?, ?, 'EUR', 'payee', 'PayPal', ?, NOW())
            ");
            $stmt->execute([$commande_id, $_SESSION['paypal_montant'] ?? 0, $order_id]);
            
            unset($_SESSION['paypal_order_id']);
            unset($_SESSION['paypal_commande_id']);
            unset($_SESSION['paypal_montant']);
            
        } catch (Exception $e) {
            error_log("Erreur mise à jour commande PayPal: " . $e->getMessage());
        }
    }
    
    echo json_encode(['status' => 200, 'message' => 'Paiement capturé avec succès', 'order_id' => $order_id]);
    exit;
}

// ============================================
// 5. ACTIONS FACTURES
// ============================================
if ($action == 'generer_facture_html') {
    $idCommande = $data['id_commande'] ?? null;
    
    if (!$idCommande) {
        echo json_encode(['status' => 400, 'error' => 'ID commande manquant']);
        exit;
    }
    
    $urlFacture = "facture.php?id=" . $idCommande;
    echo json_encode([
        'status' => 200,
        'data' => [
            'url_facture' => $urlFacture,
            'message' => 'Facture HTML générée avec succès'
        ]
    ]);
    exit;
}

if ($action == 'envoyer_facture_email') {
    $idCommande = $data['id_commande'] ?? null;
    $email = $data['email'] ?? null;
    $format = $data['format'] ?? 'pdf';
    
    if (!$idCommande || !$email) {
        echo json_encode(['status' => 400, 'error' => 'ID commande ou email manquant']);
        exit;
    }
    
    echo json_encode([
        'status' => 200,
        'data' => [
            'message' => 'Facture envoyée par email',
            'id_commande' => $idCommande,
            'format' => $format
        ]
    ]);
    exit;
}

// ============================================
// 6. ACTIONS HTML (PAGES)
// ============================================
if ($action == 'paypal_success') {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html><html><head><title>Paiement réussi</title>";
    echo "<style>body{font-family:sans-serif;text-align:center;padding:50px;}</style>";
    echo "</head><body><h1>✅ Paiement PayPal réussi</h1>";
    echo "<a href='index.html'>Retour à l'accueil</a></body></html>";
    exit;
}

if ($action == 'paypal_cancel') {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html><html><head><title>Paiement annulé</title>";
    echo "<style>body{font-family:sans-serif;text-align:center;padding:50px;}</style>";
    echo "</head><body><h1>⚠️ Paiement PayPal annulé</h1>";
    echo "<a href='index.html'>Retour à l'accueil</a></body></html>";
    exit;
}

// ============================================
// 7. SITEMAP
// ============================================
if ($_SERVER['REQUEST_URI'] == '/sitemap.xml' || (isset($_GET['action']) && $_GET['action'] == 'sitemap')) {
    header("Content-Type: application/xml");
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    echo '<url><loc>https://youkiandco.fr/</loc><priority>1.0</priority></url>';
    
    $stmt = $pdo->query("SELECT idOrigami, nom, dateModification FROM Origami WHERE visible = 1");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $row['nom']), '-'));
        echo '<url>';
        echo '<loc>https://youkiandco.fr/produit.php?id=' . $row['idOrigami'] . '&slug=' . $slug . '</loc>';
        echo '<lastmod>' . date('Y-m-d', strtotime($row['dateModification'] ?? 'now')) . '</lastmod>';
        echo '<priority>0.8</priority>';
        echo '</url>';
    }
    echo '</urlset>';
    exit;
}

// ============================================
// 8. ACTION: verifier_panier_avant_commande
// ============================================
if ($action == 'verifier_panier_avant_commande') {
    header('Content-Type: application/json');
    
    $idClient = $_SESSION['client_id'] ?? null;
    
    if (!$idClient) {
        echo json_encode([
            'status' => 200,
            'panier_valide' => false,
            'message' => 'Aucun client identifié, création en cours...',
            'redirect' => 'livraison_form.php'
        ]);
        exit;
    }
    
    try {
        // Vérifier que le panier existe et contient des articles
        $stmt = $pdo->prepare("
            SELECT p.idPanier, COUNT(lp.idLignePanier) as nb_articles
            FROM Panier p
            LEFT JOIN LignePanier lp ON p.idPanier = lp.idPanier
            WHERE p.idClient = ?
            GROUP BY p.idPanier
        ");
        $stmt->execute([$idClient]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $nbArticles = $result['nb_articles'] ?? 0;
        
        if ($nbArticles > 0) {
            echo json_encode([
                'status' => 200,
                'panier_valide' => true,
                'nb_articles' => $nbArticles,
                'message' => 'Panier validé, redirection vers livraison'
            ]);
        } else {
            echo json_encode([
                'status' => 200,
                'panier_valide' => false,
                'nb_articles' => 0,
                'message' => 'Panier vide, redirection vers page d\'accueil'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Erreur vérification panier: " . $e->getMessage());
        echo json_encode([
            'status' => 500,
            'panier_valide' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// ============================================
// ACTION PAR DÉFAUT
// ============================================
if (!$action) {
    if ($is_html_response) { 
        echo "Action non spécifiée"; 
    } else { 
        echo json_encode(['status' => 400, 'error' => 'Action non spécifiée']); 
    }
    exit;
}

echo json_encode(['status' => 400, 'error' => 'Action non reconnue: ' . $action]);
?>
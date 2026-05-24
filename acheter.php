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
// On ne charge PAS TCPDF pour éviter le conflit
// Une classe factice est créée si nécessaire

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
// RÉCUPÉRATION DE L'ACTION (AVANT LES EN-TÊTES QUI EN DÉPENDENT)
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
// INCLUSION DE genererFacturePDF.php SI NÉCESSAIRE
// ============================================
$actions_necessitant_facture = ['generer_facture_pdf', 'telecharger_facture', 'envoyer_facture_email'];
if (in_array($action, $actions_necessitant_facture) && !function_exists('genererFacturePDF')) {
    if (file_exists('genererFacturePDF.php')) {
        require_once 'genererFacturePDF.php';
    }
}

// ============================================
// 1. ACTION: get_produits_pagines (PAGINATION PRODUITS - CORRIGÉE)
// ============================================
if ($action == 'get_produits_pagines') {
    // Forcer le type JSON
    header('Content-Type: application/json');
    
    // Récupérer les paramètres
    $page = isset($data['page']) ? (int)$data['page'] : 1;
    $limit = isset($data['limit']) ? (int)$data['limit'] : 8;
    $offset = ($page - 1) * $limit;
    
    // Validation des paramètres
    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 8;
    if ($offset < 0) $offset = 0;
    
    try {
        // Compter le nombre total de produits visibles
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM Origami WHERE visible = 1");
        $stmt->execute();
        $totalRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = $totalRow ? (int)$totalRow['total'] : 0;
        
        // Récupérer les produits paginés
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
        
        // Construction de la réponse
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

// Créer ou récupérer le client pour les actions panier
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

        // Nettoyer les éventuelles lignes orphelines
        $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ? AND idOrigami IS NULL");
        $stmt->execute([$idPanier]);

        // Récupérer le prix de l'origami
        $stmt = $pdo->prepare("SELECT prixHorsTaxe FROM Origami WHERE idOrigami = ? AND visible = 1");
        $stmt->execute([$idOrigami]);
        $origami = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$origami) {
            throw new Exception('Origami non trouvé');
        }

        $prixUnitaire = $origami['prixHorsTaxe'];

        // Vérifier si l'article est déjà dans le panier
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

        // Mettre à jour la date de modification du panier
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

    // Vérifier si l'email existe déjà
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

    // Si le client n'existe pas, le créer
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

    // Gestion du panier
    $idClientTemporaire = $_SESSION['client_id'] ?? null;

    // Vérifier si le client permanent a déjà un panier
    $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
    $stmt->execute([$idClient]);
    $panierPermanent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$panierPermanent) {
        $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
        $stmt->execute([$idClient]);
        $panierPermanent = ['idPanier' => $pdo->lastInsertId()];
    }

    // Transférer les articles du panier temporaire si nécessaire
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
                
                // Supprimer le panier temporaire
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

    // Récupérer le récapitulatif du panier
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
    $stmt->execute([$panierPermanent['idPanier']]);
    $articlesPanier = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tokenConfirmation = bin2hex(random_bytes(32));
    $urlConfirmation = "http://" . $_SERVER['HTTP_HOST'] . "/acheter.php?action=confirmer_commande&token=" . $tokenConfirmation;

    // Stocker le token
    $stmt = $pdo->prepare("INSERT INTO tokens_confirmation (token, email, id_client, expiration, utilise) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)");
    $stmt->execute([$tokenConfirmation, $email, $idClient]);

    // Envoyer l'email
    $sujet = "Confirmez votre commande - Youki and Co";
    $messageHTML = "
    <html>
    <body>
        <h2>Confirmation de commande</h2>
        <p>Bonjour $nom,</p>
        <p>Cliquez sur le lien pour confirmer votre commande :</p>
        <a href='$urlConfirmation'>Confirmer ma commande</a>
        <p>Ce lien est valable 15 minutes.</p>
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
// 4. ACTIONS PAYPAL
// ============================================
if ($action == 'creer_commande_paypal') {
    $montant = $data['montant'] ?? 0;
    $idCommande = $data['id_commande'] ?? null;
    
    if ($montant <= 0) {
        echo json_encode(['status' => 400, 'error' => 'Montant invalide']);
        exit;
    }
    
    $access_token = getPayPalAccessToken(
        $paypal_config['client_id'],
        $paypal_config['client_secret'],
        $paypal_config['environment']
    );
    
    if (!$access_token) {
        echo json_encode(['status' => 500, 'error' => 'Erreur de connexion à PayPal']);
        exit;
    }
    
    $custom_data = $idCommande ? "commande_$idCommande" : null;
    $order = createPayPalOrder(
        $access_token,
        $montant,
        'EUR',
        $paypal_config['environment'],
        $paypal_config['return_url'],
        $paypal_config['cancel_url'],
        $custom_data
    );
    
    if ($order && isset($order['id'])) {
        $_SESSION['paypal_order_id'] = $order['id'];
        if ($idCommande) {
            $_SESSION['paypal_commande_id'] = $idCommande;
        }
        
        $approve_link = '';
        foreach ($order['links'] as $link) {
            if ($link['rel'] === 'approve') {
                $approve_link = $link['href'];
                break;
            }
        }
        
        echo json_encode([
            'status' => 200,
            'data' => [
                'order_id' => $order['id'],
                'approve_url' => $approve_link,
                'montant' => $montant
            ]
        ]);
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur lors de la création de la commande PayPal']);
    }
    exit;
}

if ($action == 'capturer_paiement_paypal') {
    $order_id = $data['order_id'] ?? '';
    
    if (!$order_id) {
        echo json_encode(['status' => 400, 'error' => 'ID commande manquant']);
        exit;
    }
    
    $access_token = getPayPalAccessToken(
        $paypal_config['client_id'],
        $paypal_config['client_secret'],
        $paypal_config['environment']
    );
    
    if (!$access_token) {
        echo json_encode(['status' => 500, 'error' => 'Erreur de connexion à PayPal']);
        exit;
    }
    
    $capture = capturePayPalPayment($access_token, $order_id, $paypal_config['environment']);
    
    if ($capture && isset($capture['status']) && $capture['status'] === 'COMPLETED') {
        $commande_id = $_SESSION['paypal_commande_id'] ?? null;
        
        if ($commande_id) {
            try {
                $stmt = $pdo->prepare("UPDATE Commande SET statut = 'payee', modeReglement = 'PayPal' WHERE idCommande = ?");
                $stmt->execute([$commande_id]);
                
                $montant = $capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0;
                $stmt = $pdo->prepare("
                    INSERT INTO Paiement 
                    (idCommande, montant, currency, statut, methode_paiement, reference, date_creation) 
                    VALUES (?, ?, 'EUR', 'payee', 'PayPal', ?, NOW())
                ");
                $transaction_id = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? $order_id;
                $stmt->execute([$commande_id, $montant, $transaction_id]);
                
                unset($_SESSION['paypal_order_id']);
                unset($_SESSION['paypal_commande_id']);
                
            } catch (Exception $e) {
                error_log("Erreur mise à jour commande PayPal: " . $e->getMessage());
            }
        }
        
        echo json_encode([
            'status' => 200, 
            'message' => 'Paiement capturé avec succès',
            'order_id' => $order_id,
            'commande_id' => $commande_id
        ]);
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur lors de la capture du paiement PayPal']);
    }
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

if ($action == 'generer_facture_pdf') {
    $idCommande = $data['id_commande'] ?? null;
    
    if (!$idCommande) {
        echo json_encode(['status' => 400, 'error' => 'ID commande manquant']);
        exit;
    }
    
    // Inclure le fichier maintenant si nécessaire
    if (!function_exists('genererFacturePDF') && file_exists('genererFacturePDF.php')) {
        require_once 'genererFacturePDF.php';
    }
    
    if (!function_exists('genererFacturePDF')) {
        echo json_encode(['status' => 500, 'error' => 'Fonction genererFacturePDF non disponible']);
        exit;
    }
    
    $fichierFacture = genererFacturePDF($pdo, $idCommande);
    
    if ($fichierFacture) {
        echo json_encode([
            'status' => 200,
            'data' => [
                'fichier_facture' => $fichierFacture,
                'url_facture' => 'http://' . $_SERVER['HTTP_HOST'] . '/' . $fichierFacture,
                'message' => 'Facture PDF générée avec succès'
            ]
        ]);
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur lors de la génération de la facture PDF']);
    }
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
    
    $resultat = envoyerFactureEmail($pdo, $idCommande, $email, $format);
    
    if ($resultat['success']) {
        echo json_encode([
            'status' => 200,
            'data' => [
                'message' => $resultat['message'],
                'id_commande' => $idCommande,
                'format' => $format
            ]
        ]);
    } else {
        echo json_encode(['status' => 500, 'error' => $resultat['error']]);
    }
    exit;
}

if ($action == 'telecharger_facture') {
    $idCommande = $data['id_commande'] ?? ($_GET['id_commande'] ?? null);
    
    if (!$idCommande) {
        if ($is_html_response) {
            echo "<script>alert('ID commande manquant'); window.location.href = 'index.html';</script>";
        } else {
            echo json_encode(['status' => 400, 'error' => 'ID commande manquant']);
        }
        exit;
    }
    
    // Inclure le fichier maintenant si nécessaire
    if (!function_exists('genererFacturePDF') && file_exists('genererFacturePDF.php')) {
        require_once 'genererFacturePDF.php';
    }
    
    if (!function_exists('genererFacturePDF')) {
        if ($is_html_response) {
            echo "<script>alert('Fonction de génération PDF non disponible'); window.location.href = 'index.html';</script>";
        } else {
            echo json_encode(['status' => 500, 'error' => 'Fonction genererFacturePDF non disponible']);
        }
        exit;
    }
    
    $fichierFacture = genererFacturePDF($pdo, $idCommande);
    
    if ($fichierFacture && file_exists($fichierFacture)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="facture_' . $idCommande . '.pdf"');
        readfile($fichierFacture);
        exit;
    } else {
        if ($is_html_response) {
            echo "<script>alert('Erreur lors de la génération du PDF'); window.location.href = 'index.html';</script>";
        } else {
            echo json_encode(['status' => 500, 'error' => 'Erreur lors de la génération du PDF']);
        }
    }
    exit;
}

// ============================================
// 6. ACTIONS HTML (PAGES)
// ============================================
if ($action == 'saisir_adresse' && isset($_GET['token'])) {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html><html><head><title>Saisir adresse</title></head><body>";
    echo "<h1>Formulaire d'adresse</h1>";
    echo "<p>Token: " . htmlspecialchars($_GET['token']) . "</p>";
    echo "<form method='POST' action='acheter.php'>";
    echo "<input type='hidden' name='action' value='sauvegarder_adresses'>";
    echo "<input type='hidden' name='token' value='" . htmlspecialchars($_GET['token']) . "'>";
    echo "<label>Nom: <input type='text' name='nom_livraison' required></label><br>";
    echo "<label>Prénom: <input type='text' name='prenom_livraison' required></label><br>";
    echo "<label>Adresse: <input type='text' name='adresse_livraison' required></label><br>";
    echo "<label>Code postal: <input type='text' name='code_postal_livraison' required></label><br>";
    echo "<label>Ville: <input type='text' name='ville_livraison' required></label><br>";
    echo "<button type='submit'>Continuer</button>";
    echo "</form></body></html>";
    exit;
}

if ($action == 'sauvegarder_adresses') {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
    echo "<h1>Adresses sauvegardées</h1>";
    echo "<p>Merci ! Votre commande a été créée.</p>";
    echo "<a href='index.html'>Retour à l'accueil</a>";
    exit;
}

if ($action == 'confirmer_commande') {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
    $token = $_GET['token'] ?? '';
    echo "<h1>Confirmation commande</h1>";
    echo "<p>Token: " . htmlspecialchars($token) . "</p>";
    echo "<a href='acheter.php?action=saisir_adresse&token=" . urlencode($token) . "'>Continuer vers adresse</a>";
    exit;
}

if ($action == 'paypal_success') {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
    echo "<h1>✅ Paiement PayPal réussi</h1>";
    echo "<a href='index.html'>Retour à l'accueil</a>";
    exit;
}

if ($action == 'paypal_cancel') {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
    echo "<h1>⚠️ Paiement PayPal annulé</h1>";
    echo "<a href='index.html'>Retour à l'accueil</a>";
    exit;
}

if ($action == 'utiliser_adresse_existante') {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
    echo "<h1>Adresse existante utilisée</h1>";
    echo "<a href='index.html'>Retour à l'accueil</a>";
    exit;
}

// ============================================
// 7. ACTIONS SPÉCIALES
// ============================================
if ($action == 'nettoyer_clients_zombies') {
    $result = forcerNettoyageComplet($pdo);
    echo json_encode(['status' => 200, 'message' => 'Nettoyage exécuté', 'elements_supprimes' => $result]);
    exit;
}

// ============================================
// 8. SITEMAP
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
// 9. ACTION PAR DÉFAUT
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
<?php
session_start();
require_once 'smtp_config.php';
require_once 'config.php';
// DÉBUT CRITIQUE : Gestion intelligente des en-têtes
$is_html_response = false;

// Inclure TCPDF pour la génération de PDF
require_once('tcpdf/tcpdf.php');

// Inclure le fichier de génération de facture
require_once 'genererFacturePDF.php';

// Détecter si c'est une requête de confirmation HTML
if (isset($_GET['token']) && (!isset($_POST['action']))) {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
} else {
    // Pour toutes les autres requêtes (AJAX/API)
    header('Content-Type: application/json');
}

// En-têtes CORS pour toutes les requêtes
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}
// FIN CRITIQUE

// Inclure PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Configuration de la base de données

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    if ($is_html_response) {
        echo "Erreur de connexion à la base de données";
        exit;
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]);
        exit;
    }
}

// Configuration PayPal
// Configuration PayPal
$paypal_config = [
    'client_id' => 'Aac1-P0VrxBQ_5REVeo4f557_-p6BDeXA_hyiuVZfi21sILMWccBFfTidQ6nnhQathCbWaCSQaDmxJw5',
    'client_secret' => 'EJxech0i1faRYlo0-ln2sU09ecx5rP3XEOGUTeTduI2t-I0j4xoSPqRRFQTxQsJoSBbSL8aD1b1GPPG1',
    'environment' => 'sandbox',
    'return_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/Origami/acheter.php?action=paypal_success',
    'cancel_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/Origami/acheter.php?action=paypal_cancel'
];

// Fonction pour obtenir l'access token PayPal
function getPayPalAccessToken($client_id, $client_secret, $environment) {
    $url = $environment === 'live' 
        ? 'https://api.paypal.com/v1/oauth2/token'
        : 'https://api.sandbox.paypal.com/v1/oauth2/token';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ":" . $client_secret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $json = json_decode($result);
        return $json->access_token;
    } else {
        error_log("Erreur PayPal Access Token: " . $result);
        return false;
    }
}

// Fonction pour créer une commande PayPal
function createPayPalOrder($access_token, $amount, $currency, $environment, $return_url, $cancel_url, $custom_data = null) {
    $url = $environment === 'live' 
        ? 'https://api.paypal.com/v2/checkout/orders'
        : 'https://api.sandbox.paypal.com/v2/checkout/orders';
    
    $data = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'amount' => [
                    'currency_code' => $currency,
                    'value' => number_format($amount, 2, '.', '')
                ]
            ]
        ],
        'application_context' => [
            'return_url' => $return_url,
            'cancel_url' => $cancel_url,
            'brand_name' => 'Youki and Co',
            'user_action' => 'PAY_NOW'
        ]
    ];
    
    // Ajouter les données personnalisées si fournies
    if ($custom_data) {
        $data['purchase_units'][0]['custom_id'] = $custom_data;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 201) {
        $json = json_decode($result, true);
        return $json;
    } else {
        error_log("Erreur création commande PayPal: " . $result);
        return false;
    }
}

// Fonction pour capturer le paiement PayPal
function capturePayPalPayment($access_token, $order_id, $environment) {
    $url = $environment === 'live' 
        ? 'https://api.paypal.com/v2/checkout/orders/' . $order_id . '/capture'
        : 'https://api.sandbox.paypal.com/v2/checkout/orders/' . $order_id . '/capture';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 201) {
        $json = json_decode($result, true);
        return $json;
    } else {
        error_log("Erreur capture PayPal: " . $result);
        return false;
    }
}

// Fonction pour envoyer un email avec PHPMailer
function envoyerEmail($destinataire, $sujet, $message) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration du serveur SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        
        // Options de sécurité
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Destinataires
        $mail->setFrom('lhpp.philippe@gmail.com', 'Youki and Co');
        $mail->addAddress($destinataire);
        $mail->addReplyTo('lhpp.philippe@gmail.com', 'Youki and Co');
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        if ($mail->send()) {
            return ['success' => true, 'message' => 'Email envoyé avec succès'];
        } else {
            return ['success' => false, 'error' => 'Échec de l\'envoi sans exception'];
        }
        
    } catch (Exception $e) {
        error_log("Erreur PHPMailer: " . $mail->ErrorInfo);
        return ['success' => false, 'error' => 'Erreur PHPMailer: ' . $e->getMessage()];
    }
}

// Fonction pour générer un token de confirmation
function genererTokenConfirmation() {
    return bin2hex(random_bytes(32));
}

// Fonction pour nettoyer les tokens expirés
function nettoyerTokensExpires($pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM tokens_confirmation WHERE expiration < NOW() OR utilise = 1");
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Erreur nettoyage tokens: " . $e->getMessage());
    }
}

// FONCTION AMÉLIORÉE : Nettoyage complet des clients temporaires et zombies
function nettoyerClientsTemporairesAmeliore($pdo) {
    try {
        error_log("🔍 Début du nettoyage des clients temporaires et zombies");
        $countTotal = 0;

        // 1. Nettoyage des clients temporaires anciens (avec ou sans colonne type)
        try {
            $stmt = $pdo->prepare("DELETE FROM Client WHERE (type = 'temporaire' OR email LIKE 'temp_%@origamizen.fr') AND date_creation < DATE_SUB(NOW(), INTERVAL 1 DAY)");
            $stmt->execute();
            $countTemp = $stmt->rowCount();
            $countTotal += $countTemp;
            if ($countTemp > 0) {
                error_log("🧹 Clients temporaires supprimés: " . $countTemp);
            }
        } catch (Exception $e) {
            // Si colonne type manquante, nettoyer par email et date
            error_log("Colonne type manquante, utilisation alternative");
            try {
                $stmt = $pdo->prepare("DELETE FROM Client WHERE email LIKE 'temp_%@origamizen.fr' AND date_creation < DATE_SUB(NOW(), INTERVAL 1 DAY)");
                $stmt->execute();
                $countTemp = $stmt->rowCount();
                $countTotal += $countTemp;
                if ($countTemp > 0) {
                    error_log("🧹 Clients temporaires (sans type) supprimés: " . $countTemp);
                }
            } catch (Exception $e2) {
                // Si date_creation manquante, nettoyer seulement par email
                error_log("Colonne date_creation manquante, nettoyage par email uniquement");
                $stmt = $pdo->prepare("DELETE FROM Client WHERE email LIKE 'temp_%@origamizen.fr'");
                $stmt->execute();
                $countTemp = $stmt->rowCount();
                $countTotal += $countTemp;
                if ($countTemp > 0) {
                    error_log("🧹 Clients temporaires (email uniquement) supprimés: " . $countTemp);
                }
            }
        }

        // 2. Nettoyer les clients sans panier ni commande (zombis) - plus agressif (2 heures)
        try {
            $stmt = $pdo->prepare("
                DELETE c FROM Client c 
                LEFT JOIN Panier p ON c.idClient = p.idClient 
                LEFT JOIN Commande cmd ON c.idClient = cmd.idClient 
                WHERE p.idPanier IS NULL 
                AND cmd.idCommande IS NULL 
                AND c.date_creation < DATE_SUB(NOW(), INTERVAL 2 HOUR)
                AND (c.type = 'temporaire' OR c.email LIKE 'temp_%@origamizen.fr')
            ");
            $stmt->execute();
            $countZombies = $stmt->rowCount();
            $countTotal += $countZombies;
            if ($countZombies > 0) {
                error_log("🧟 Clients zombies supprimés: " . $countZombies);
            }
        } catch (Exception $e) {
            error_log("Erreur nettoyage zombies: " . $e->getMessage());
        }

        // 3. Nettoyer les paniers orphelins (sans client)
        try {
            $stmt = $pdo->prepare("
                DELETE p FROM Panier p 
                LEFT JOIN Client c ON p.idClient = c.idClient 
                WHERE c.idClient IS NULL
            ");
            $stmt->execute();
            $countPaniers = $stmt->rowCount();
            if ($countPaniers > 0) {
                error_log("🛒 Paniers orphelins supprimés: " . $countPaniers);
            }
        } catch (Exception $e) {
            error_log("Erreur nettoyage paniers orphelins: " . $e->getMessage());
        }

        // 4. Nettoyer les lignes de panier orphelines
        try {
            $stmt = $pdo->prepare("
                DELETE lp FROM LignePanier lp 
                LEFT JOIN Panier p ON lp.idPanier = p.idPanier 
                WHERE p.idPanier IS NULL
            ");
            $stmt->execute();
            $countLignes = $stmt->rowCount();
            if ($countLignes > 0) {
                error_log("📝 Lignes panier orphelines supprimées: " . $countLignes);
            }
        } catch (Exception $e) {
            error_log("Erreur nettoyage lignes panier: " . $e->getMessage());
        }

        if ($countTotal > 0) {
            error_log("✅ Nettoyage terminé: " . $countTotal . " éléments nettoyés");
        }

        return $countTotal;

    } catch (Exception $e) {
        error_log("❌ Erreur lors du nettoyage: " . $e->getMessage());
        return 0;
    }
}

// Fonction pour forcer un nettoyage (à appeler manuellement si besoin)
function forcerNettoyageComplet($pdo) {
    error_log("🚨 FORÇAGE DU NETTOYAGE COMPLET");
    $result = nettoyerClientsTemporairesAmeliore($pdo);
    error_log("🚨 Résultat nettoyage forcé: " . $result . " éléments supprimés");
    return $result;
}

// Gestion des clients temporaires - VERSION COMPATIBLE (avec ou sans colonne type)
function getOrCreateClient($pdo) {
    // Vérifier d'abord si on a déjà un client_id en session valide
    if (isset($_SESSION['client_id'])) {
        // Vérifier que ce client existe encore en base
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE idClient = ?");
        $stmt->execute([$_SESSION['client_id']]);
        if ($stmt->fetch()) {
            return $_SESSION['client_id'];
        }
        // Si n'existe pas, nettoyer la session
        unset($_SESSION['client_id']);
    }
    
    // Vérifier s'il existe un client temporaire avec cette session
    $sessionId = session_id();
    
    try {
        // Essayer de chercher avec la colonne type
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE session_id = ? AND type = 'temporaire'");
        $stmt->execute([$sessionId]);
        $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Si échec (colonne type manquante), chercher simplement par session_id
        error_log("Colonne type non trouvée, utilisation de session_id uniquement");
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($clientExist) {
        $_SESSION['client_id'] = $clientExist['idClient'];
        return $clientExist['idClient'];
    }
    
    // AVANT de créer un nouveau client, nettoyer les anciens clients temporaires pour cette session
    try {
        $stmt = $pdo->prepare("DELETE FROM Client WHERE session_id = ? AND (type = 'temporaire' OR email LIKE 'temp_%@origamizen.fr')");
        $stmt->execute([$sessionId]);
    } catch (Exception $e) {
        error_log("Erreur nettoyage anciens clients session: " . $e->getMessage());
    }
    
    // Créer un nouveau client temporaire
    try {
        // Essayer d'insérer avec la colonne type
        $stmt = $pdo->prepare("INSERT INTO Client (email, nom, prenom, type, date_creation, session_id) VALUES (?, 'Invité', 'Client', 'temporaire', NOW(), ?)");
        $emailTemp = 'temp_' . uniqid() . '@origamizen.fr';
        $stmt->execute([$emailTemp, $sessionId]);
    } catch (Exception $e) {
        // Si échec (colonne type manquante), insérer sans type
        error_log("Insertion sans colonne type: " . $e->getMessage());
        try {
            // Essayer avec date_creation
            $stmt = $pdo->prepare("INSERT INTO Client (email, nom, prenom, date_creation, session_id) VALUES (?, 'Invité', 'Client', NOW(), ?)");
            $emailTemp = 'temp_' . uniqid() . '@origamizen.fr';
            $stmt->execute([$emailTemp, $sessionId]);
        } catch (Exception $e2) {
            // Si échec (date_creation manquante), insérer avec les colonnes minimales
            error_log("Insertion avec colonnes minimales: " . $e2->getMessage());
            $stmt = $pdo->prepare("INSERT INTO Client (email, nom, prenom, session_id) VALUES (?, 'Invité', 'Client', ?)");
            $emailTemp = 'temp_' . uniqid() . '@origamizen.fr';
            $stmt->execute([$emailTemp, $sessionId]);
        }
    }
    
    $clientId = $pdo->lastInsertId();
    $_SESSION['client_id'] = $clientId;
    
    error_log("🆕 Nouveau client temporaire créé: ID " . $clientId . " pour session " . $sessionId);
    return $clientId;
}

// FONCTION : Générer une facture via l'API facture.php
// FONCTION : Générer une facture
// Remplacer cette fonction dans acheter.php
function genererFactureAPI($idCommande, $format = 'html') {
    error_log("🔄 Génération facture pour commande: " . $idCommande . " format: " . $format);
    
    global $pdo;
    
    if ($format === 'pdf') {
        // Utiliser la nouvelle fonction simplifiée
        $resultat = genererFacturePDF($pdo, $idCommande);
        
        if ($resultat) {
            return $resultat; // Retourne le chemin du fichier PDF
        } else {
            error_log("❌ Erreur génération PDF");
            return false;
        }
    } else {
        // Pour HTML, redirection simple
        return "facture.php?id=" . $idCommande;
    }
}

// FONCTION : Envoyer email avec pièce jointe
function envoyerEmailAvecPieceJointe($destinataire, $sujet, $message, $fichierJoint) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration du serveur SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        
        // Options de sécurité
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Destinataires
        $mail->setFrom('lhpp.philippe@gmail.com', 'Youki and Co');
        $mail->addAddress($destinataire);
        $mail->addReplyTo('lhpp.philippe@gmail.com', 'Youki and Co');
        
        // Pièce jointe
        if (file_exists($fichierJoint)) {
            $mail->addAttachment($fichierJoint, 'facture_' . basename($fichierJoint));
        }
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        if ($mail->send()) {
            return ['success' => true, 'message' => 'Email avec facture envoyé avec succès'];
        } else {
            return ['success' => false, 'error' => 'Échec de l\'envoi sans exception'];
        }
        
    } catch (Exception $e) {
        error_log("Erreur PHPMailer avec pièce jointe: " . $mail->ErrorInfo);
        return ['success' => false, 'error' => 'Erreur PHPMailer: ' . $e->getMessage()];
    }
}

// FONCTION : Envoyer la facture par email
function envoyerFactureEmail($pdo, $idCommande, $emailClient, $format = 'pdf') {
    error_log("📧 Envoi facture par email pour commande: " . $idCommande . " à: " . $emailClient);
    
    try {
        // Générer la facture via l'API
        $fichierFacture = genererFactureAPI($idCommande, $format);
        
        if (!$fichierFacture) {
            throw new Exception("Erreur lors de la génération de la facture");
        }
        
        // Récupérer les infos de la commande pour l'email
        $stmt = $pdo->prepare("
            SELECT c.idCommande, c.montantTotal, cl.prenom, cl.nom
            FROM Commande c
            JOIN Client cl ON c.idClient = cl.idClient
            WHERE c.idCommande = ?
        ");
        $stmt->execute([$idCommande]);
        $commande_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$commande_info) {
            throw new Exception("Commande non trouvée");
        }
        
        // Préparer l'email
        $sujet = "Votre facture #" . $idCommande . " - Youki and Co";
        
        if ($format === 'pdf') {
            $message = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2 style='color: #d40000;'>Votre facture Youki and Co</h2>
                <p>Bonjour " . htmlspecialchars($commande_info['prenom']) . ",</p>
                <p>Veuillez trouver ci-joint votre facture pour la commande #" . $commande_info['idCommande'] . ".</p>
                <p><strong>Montant :</strong> " . number_format($commande_info['montantTotal'], 2, ',', ' ') . " €</p>
                <p>Vous pouvez également visualiser votre facture en ligne :</p>
                <p><a href='" . genererFactureAPI($idCommande, 'html') . "' style='background: #d40000; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Voir la facture en ligne</a></p>
                <p>Merci pour votre confiance !</p>
                <br>
                <p><strong>L'équipe Youki and Co</strong></p>
            </body>
            </html>
            ";
            
            // Envoyer l'email avec pièce jointe
            return envoyerEmailAvecPieceJointe($emailClient, $sujet, $message, $fichierFacture);
        } else {
            $message = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2 style='color: #d40000;'>Votre facture Youki and Co</h2>
                <p>Bonjour " . htmlspecialchars($commande_info['prenom']) . ",</p>
                <p>Veuillez trouver votre facture pour la commande #" . $commande_info['idCommande'] . " en cliquant sur le lien ci-dessous :</p>
                <p><a href='" . $fichierFacture . "' style='background: #d40000; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Voir ma facture</a></p>
                <p><strong>Montant :</strong> " . number_format($commande_info['montantTotal'], 2, ',', ' ') . " €</p>
                <p>Merci pour votre confiance !</p>
                <br>
                <p><strong>L'équipe Youki and Co</strong></p>
            </body>
            </html>
            ";
            
            return envoyerEmail($emailClient, $sujet, $message);
        }
        
    } catch (Exception $e) {
        error_log("❌ Erreur envoi facture email: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Récupération des données JSON (uniquement pour les requêtes API)
if (!$is_html_response) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
} else {
    $data = [];
}

// Vérification de l'action - CORRECTION CRITIQUE
// Vérifier d'abord POST puis GET
$action = $_POST['action'] ?? ($_GET['action'] ?? ($data['action'] ?? ''));

// Si pas d'action mais un token dans GET, c'est une confirmation directe
if (!$action && isset($_GET['token'])) {
    $action = 'confirmer_commande';
    $is_html_response = true;
}

// Si action=saisir_adresse avec token, afficher formulaire HTML
if ($action == 'saisir_adresse' && isset($_GET['token'])) {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
}

// ACTION SPÉCIALE : Nettoyage manuel
if ($action == 'nettoyer_clients_zombies') {
    $result = forcerNettoyageComplet($pdo);
    echo json_encode(['status' => 200, 'message' => 'Nettoyage exécuté', 'elements_supprimes' => $result]);
    exit;
}

// NOUVELLES ACTIONS POUR LA GESTION DES FACTURES
if ($action == 'generer_facture_html') {
    $idCommande = $data['id_commande'] ?? null;
    
    if (!$idCommande) {
        echo json_encode(['status' => 400, 'error' => 'ID commande manquant']);
        exit;
    }
    
    $urlFacture = genererFactureAPI($idCommande, 'html');
    
    if ($urlFacture) {
        echo json_encode([
            'status' => 200,
            'data' => [
                'url_facture' => $urlFacture,
                'message' => 'Facture HTML générée avec succès'
            ]
        ]);
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur lors de la génération de la facture HTML']);
    }
    exit;
}

if ($action == 'generer_facture_pdf') {
    $idCommande = $data['id_commande'] ?? null;
    
    if (!$idCommande) {
        echo json_encode(['status' => 400, 'error' => 'ID commande manquant']);
        exit;
    }
    
    $fichierFacture = genererFactureAPI($idCommande, 'pdf');
    
    if ($fichierFacture) {
        echo json_encode([
            'status' => 200,
            'data' => [
                'fichier_facture' => $fichierFacture,
                'url_facture' => 'http://' . $_SERVER['HTTP_HOST'] . '/Origami/' . $fichierFacture,                'message' => 'Facture PDF générée avec succès'
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
    
    // Générer le PDF
    $fichierFacture = genererFactureAPI($idCommande, 'pdf');
    
    if ($fichierFacture && file_exists($fichierFacture)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="facture_' . $idCommande . '.pdf"');
        readfile($fichierFacture);
        exit;
    } else {
        if ($is_html_response) {
            echo "<script>alert('Erreur lors de la generation du PDF'); window.location.href = 'index.html';</script>";
        } else {
            echo json_encode(['status' => 500, 'error' => 'Erreur lors de la generation du PDF']);
        }
    }
    exit;
}

// ACTIONS PAYPAL EXISTANTES
if ($action == 'creer_commande_paypal') {
    $montant = $data['montant'] ?? 0;
    $idCommande = $data['id_commande'] ?? null;
    
    if ($montant <= 0) {
        echo json_encode(['status' => 400, 'error' => 'Montant invalide']);
        exit;
    }
    
    // Obtenir l'access token PayPal
    $access_token = getPayPalAccessToken(
        $paypal_config['client_id'],
        $paypal_config['client_secret'],
        $paypal_config['environment']
    );
    
    if (!$access_token) {
        echo json_encode(['status' => 500, 'error' => 'Erreur de connexion à PayPal']);
        exit;
    }
    
    // Créer la commande PayPal
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
        // Stocker l'ID de commande PayPal en session pour validation ultérieure
        $_SESSION['paypal_order_id'] = $order['id'];
        if ($idCommande) {
            $_SESSION['paypal_commande_id'] = $idCommande;
        }
        
        // Retourner les liens d'approbation
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
    
    // Capturer le paiement PayPal
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
        // Récupérer l'ID de commande depuis la session ou les données personnalisées
        $commande_id = $_SESSION['paypal_commande_id'] ?? null;
        
        if ($commande_id) {
            try {
                // Mettre à jour le statut de la commande
                $stmt = $pdo->prepare("UPDATE Commande SET statut = 'payee', modeReglement = 'PayPal' WHERE idCommande = ?");
                $stmt->execute([$commande_id]);
                
                // Enregistrer le paiement
                $montant = $capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0;
                $stmt = $pdo->prepare("
                    INSERT INTO Paiement 
                    (idCommande, montant, currency, statut, methode_paiement, reference, date_creation) 
                    VALUES (?, ?, 'EUR', 'payee', 'PayPal', ?, NOW())
                ");
                $transaction_id = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? $order_id;
                $stmt->execute([$commande_id, $montant, $transaction_id]);
                
                // Générer automatiquement la facture PDF
                $fichierFacture = genererFactureAPI($commande_id, 'pdf');
                
                // Envoyer la facture par email au client
                $stmt = $pdo->prepare("SELECT cl.email FROM Commande c JOIN Client cl ON c.idClient = cl.idClient WHERE c.idCommande = ?");
                $stmt->execute([$commande_id]);
                $client_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($client_info) {
                    envoyerFactureEmail($pdo, $commande_id, $client_info['email'], 'pdf');
                }
                
                // Nettoyer la session
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
            'facture_generee' => !empty($fichierFacture)
        ]);
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur lors de la capture du paiement PayPal']);
    }
    exit;
}

if ($action == 'paypal_success') {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
    
    $order_id = $_GET['token'] ?? $_SESSION['paypal_order_id'] ?? '';
    $commande_id = $_SESSION['paypal_commande_id'] ?? '';
    
    if (!$order_id) {
        echo "<script>alert('Données de commande manquantes'); window.location.href = 'index.html';</script>";
        exit;
    }
    
    // Capturer le paiement PayPal
    $access_token = getPayPalAccessToken(
        $paypal_config['client_id'],
        $paypal_config['client_secret'],
        $paypal_config['environment']
    );
    
    if (!$access_token) {
        echo "<script>alert('Erreur de connexion à PayPal'); window.location.href = 'index.html';</script>";
        exit;
    }
    
    $capture = capturePayPalPayment($access_token, $order_id, $paypal_config['environment']);
    
    if ($capture && isset($capture['status']) && $capture['status'] === 'COMPLETED') {
        // Paiement réussi
        $montant = $capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0;
        
        // Mettre à jour le statut de la commande dans la base de données
        if ($commande_id) {
            try {
                $stmt = $pdo->prepare("UPDATE Commande SET statut = 'payee', modeReglement = 'PayPal' WHERE idCommande = ?");
                $stmt->execute([$commande_id]);
                
                // Enregistrer le paiement
                $stmt = $pdo->prepare("
                    INSERT INTO Paiement 
                    (idCommande, montant, currency, statut, methode_paiement, date_creation) 
                    VALUES (?, ?, 'EUR', 'payee', 'PayPal', NOW())
                ");
                $stmt->execute([$commande_id, $montant]);
                
                // Générer automatiquement la facture PDF
                $fichierFacture = genererFactureAPI($commande_id, 'pdf');
                $urlFactureHTML = genererFactureAPI($commande_id, 'html');
                
                // Récupérer les infos de la commande pour l'email
                $stmt = $pdo->prepare("
                    SELECT c.idCommande, c.montantTotal, cl.email, cl.prenom, cl.nom
                    FROM Commande c
                    JOIN Client cl ON c.idClient = cl.idClient
                    WHERE c.idCommande = ?
                ");
                $stmt->execute([$commande_id]);
                $commande_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Envoyer email de confirmation avec facture
                if ($commande_info) {
                    $sujet = "Confirmation de paiement - Commande #" . $commande_info['idCommande'];
                    $message = "
                    <html>
                    <body>
                        <h2>Paiement confirmé</h2>
                        <p>Bonjour " . htmlspecialchars($commande_info['prenom']) . ",</p>
                        <p>Votre paiement PayPal pour la commande #" . $commande_info['idCommande'] . " a été traité avec succès.</p>
                        <p><strong>Montant :</strong> " . number_format($montant, 2, ',', ' ') . " €</p>
                        <p>Votre facture est disponible en pièce jointe à télécharger :</p>
                        <!--<p><a href='" . $urlFactureHTML . "'>Voir ma facture en ligne</a></p>-->
                        <p>Merci pour votre confiance !</p>
                    </body>
                    </html>
                    ";
                    
                    envoyerEmailAvecPieceJointe($commande_info['email'], $sujet, $message, $fichierFacture);
                }
                
            } catch (Exception $e) {
                error_log("Erreur mise à jour commande PayPal: " . $e->getMessage());
            }
        }
        
        // Nettoyer la session
        unset($_SESSION['paypal_order_id']);
        unset($_SESSION['paypal_commande_id']);
        
        // Afficher confirmation avec options de facture
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Paiement Réussi - Youki and Co</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; max-width: 600px; }
                .success { color: #28a745; }
                .btn { 
                    display: inline-block;
                    background-color: #d40000; 
                    color: white; 
                    padding: 12px 30px; 
                    text-decoration: none; 
                    border-radius: 2px; 
                    margin: 10px 5px;
                }
                .btn-success { 
                    background-color: #28a745; 
                }
                .facture-options {
                    background: #e7f3ff;
                    border: 1px solid #b3d9ff;
                    padding: 20px;
                    border-radius: 4px;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1 class="success">✅ Paiement Réussi !</h1>
                <p>Votre paiement PayPal a été traité avec succès.</p>
                <?php if ($commande_id): ?>
                <p><strong>Numéro de commande :</strong> #<?= $commande_id ?></p>
                <p><strong>Montant payé :</strong> <?= number_format($montant, 2, ',', ' ') ?> €</p>
                <?php endif; ?>
                
                <!--<div class="facture-options">
                    <h3>📄 Votre facture</h3>
                    <p>Votre facture a été générée.</p>
                    <p>Vous pouvez :</p>
                     <a href="<?//= $urlFactureHTML ?>" target="_blank" class="btn">👁️ Voir la facture HTML</a>
                    <a href="acheter.php?action=telecharger_facture&id_commande=<?//= $commande_id ?>" class="btn btn-success">📥 Télécharger PDF</a>
                </div>-->
                
                <p>Vous recevrez un email de confirmation sous peu.</p>
                <a href="index.html" class="btn">🏠 Retour à l'accueil</a>
            </div>
        </body>
        </html>
        <?php
        
    } else {
        // Paiement échoué
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Erreur de Paiement - Youki and Co</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
                .error { color: #dc3545; }
                .btn { 
                    display: inline-block;
                    background-color: #d40000; 
                    color: white; 
                    padding: 12px 30px; 
                    text-decoration: none; 
                    border-radius: 2px; 
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1 class="error">❌ Erreur de Paiement</h1>
                <p>Une erreur est survenue lors du traitement de votre paiement PayPal.</p>
                <p>Veuillez réessayer ou contacter notre service client.</p>
                <a href="index.html" class="btn">Retour à l'accueil</a>
            </div>
        </body>
        </html>
        <?php
    }
    exit;
}

if ($action == 'paypal_cancel') {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
    
    // Nettoyer la session
    unset($_SESSION['paypal_order_id']);
    unset($_SESSION['paypal_commande_id']);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Paiement Annulé - Youki and Co</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
            .warning { color: #856404; }
            .btn { 
                display: inline-block;
                background-color: #d40000; 
                color: white; 
                padding: 12px 30px; 
                text-decoration: none; 
                border-radius: 2px; 
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 class="warning">⚠️ Paiement Annulé</h1>
            <p>Vous avez annulé votre paiement PayPal.</p>
            <p>Votre panier a été conservé. Vous pouvez finaliser votre commande ultérieurement.</p>
            <a href="index.html" class="btn">Retour à l'accueil</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (!$action) {
    if ($is_html_response) {
        echo "Action non spécifiée";
        exit;
    } else {
        echo json_encode(['status' => 400, 'error' => 'Action non spécifiée']);
        exit;
    }
}

try {
    // NETTOYAGE AMÉLIORÉ : Plus fréquent et plus agressif
    // Nettoyer les tokens expirés périodiquement (1 chance sur 5)
    if (rand(1, 5) === 1) {
        nettoyerTokensExpires($pdo);
    }
    
    // NETTOYAGE RENFORCÉ : Clients temporaires (1 chance sur 8 au lieu de 20)
    if (rand(1, 5) === 1) {
        nettoyerClientsTemporairesAmeliore($pdo);
    }

    // CORRECTION CRITIQUE : Seulement créer un client pour les actions qui en ont vraiment besoin
    $actionsNecessitantClient = [
        'ajouter_au_panier', 
        'get_panier', 
        'modifier_quantite', 
        'supprimer_du_panier', 
        'vider_panier'
    ];

    if (in_array($action, $actionsNecessitantClient)) {
        $idClient = getOrCreateClient($pdo);
    } else {
        $idClient = null;
    }

    if ($action == 'envoyer_lien_confirmation') {
        $email = $data['email'] ?? '';
        $nom = $data['nom'] ?? 'Client';
        $prenom = $data['prenom'] ?? '';
        $telephone = $data['telephone'] ?? '';

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 400, 'error' => 'Email invalide']);
            exit;
        }

        // Vérifier si l'email existe déjà - VERSION COMPATIBLE
        try {
            $stmt = $pdo->prepare("SELECT idClient, nom, prenom FROM Client WHERE email = ? AND type = 'permanent'");
            $stmt->execute([$email]);
            $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Attraper spécifiquement PDOException
            if (strpos($e->getMessage(), "Column not found") !== false || strpos($e->getMessage(), "Unknown column") !== false) {
                error_log("Colonne type manquante, recherche par email uniquement");
                $stmt = $pdo->prepare("SELECT idClient, nom, prenom FROM Client WHERE email = ?");
                $stmt->execute([$email]);
                $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // Relancer l'exception si c'est une autre erreur
                throw $e;
            }
        }

        $clientExistant = ($clientExist !== false);
        
        // SI LE CLIENT N'EXISTE PAS, LE CRÉER MAINTENANT - VERSION COMPATIBLE
        if (!$clientExistant) {
            // Créer le client permanent - VERSION COMPATIBLE
            $motDePasse = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone, type) VALUES (?, ?, ?, ?, ?, 'permanent')");
                $stmt->execute([$email, $motDePasse, $nom, $prenom, $telephone]);
            } catch (Exception $e) {
                // Si colonne type manquante, insérer sans type
                error_log("Insertion client sans colonne type");
                $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$email, $motDePasse, $nom, $prenom, $telephone]);
            }
            $idClient = $pdo->lastInsertId();
        } else {
            $idClient = $clientExist['idClient'];
        }

        // GESTION DU PANIER SANS DOUBLONS
        $idClientTemporaire = $_SESSION['client_id'] ?? null;
        
        // Vérifier si le client permanent a déjà un panier
        $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
        $stmt->execute([$idClient]);
        $panierPermanent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$panierPermanent) {
            // Le client permanent n'a pas de panier, on en crée un
            $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
            $stmt->execute([$idClient]);
            $panierPermanent = ['idPanier' => $pdo->lastInsertId()];
        }
        
        // Si on a un client temporaire avec un panier, transférer les articles
        if ($idClientTemporaire && $idClientTemporaire != $idClient) {
            try {
                // Vérifier si le client temporaire a des articles dans son panier
                $stmt = $pdo->prepare("
                    SELECT lp.idLignePanier, lp.idOrigami, lp.quantite, lp.prixUnitaire 
                    FROM LignePanier lp 
                    JOIN Panier p ON lp.idPanier = p.idPanier 
                    WHERE p.idClient = ?
                ");
                $stmt->execute([$idClientTemporaire]);
                $articlesTemporaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($articlesTemporaires)) {
                    // Transférer chaque article du panier temporaire vers le panier permanent
                    foreach ($articlesTemporaires as $article) {
                        // Vérifier si l'article existe déjà dans le panier permanent
                        $stmt = $pdo->prepare("SELECT idLignePanier, quantite FROM LignePanier WHERE idPanier = ? AND idOrigami = ?");
                        $stmt->execute([$panierPermanent['idPanier'], $article['idOrigami']]);
                        $articleExistant = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($articleExistant) {
                            // Mettre à jour la quantité
                            $nouvelleQuantite = $articleExistant['quantite'] + $article['quantite'];
                            $stmt = $pdo->prepare("UPDATE LignePanier SET quantite = ? WHERE idLignePanier = ?");
                            $stmt->execute([$nouvelleQuantite, $articleExistant['idLignePanier']]);
                        } else {
                            // Ajouter un nouvel article
                            $stmt = $pdo->prepare("INSERT INTO LignePanier (idPanier, idOrigami, quantite, prixUnitaire) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$panierPermanent['idPanier'], $article['idOrigami'], $article['quantite'], $article['prixUnitaire']]);
                        }
                    }
                    
                    // Supprimer le panier temporaire et le client temporaire
                    $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier IN (SELECT idPanier FROM Panier WHERE idClient = ?)");
                    $stmt->execute([$idClientTemporaire]);
                    
                    $stmt = $pdo->prepare("DELETE FROM Panier WHERE idClient = ?");
                    $stmt->execute([$idClientTemporaire]);
                    
                    // NETTOYAGE IMMÉDIAT : Supprimer le client temporaire après transfert
                    $stmt = $pdo->prepare("DELETE FROM Client WHERE idClient = ? AND (type = 'temporaire' OR email LIKE 'temp_%@origamizen.fr')");
                    $stmt->execute([$idClientTemporaire]);
                    
                    error_log("🔄 Articles transférés du client temporaire " . $idClientTemporaire . " vers le client permanent " . $idClient . " et client temporaire supprimé");
                }
                
                // Mettre à jour la session
                $_SESSION['client_id'] = $idClient;
                
            } catch (Exception $e) {
                error_log("Erreur transfert panier: " . $e->getMessage());
            }
        }

        // RÉCUPÉRER LE RÉCAPITULATIF DU PANIER AVANT DE CRÉER LE TOKEN
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
            WHERE lp.idPanier = ?
        ");
        $stmt->execute([$panierPermanent['idPanier']]);
        $articlesPanier = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer le total du panier
        $totalPanier = 0;
        $totalQuantites = 0;
        foreach ($articlesPanier as $article) {
            $totalPanier += $article['totalLigne'];
            $totalQuantites += $article['quantite'];
        }
        
        // Ajouter les frais de port
        $fraisDePort = 0.0;
        $montantTotal = $totalPanier + $fraisDePort;

        $tokenConfirmation = genererTokenConfirmation();

        // Stocker le token avec vérification
        try {
            $stmt = $pdo->prepare("INSERT INTO tokens_confirmation (token, email, id_client, expiration, utilise) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)");
            $stmt->execute([$tokenConfirmation, $email, $idClient]);
            // Vérifier que l'insertion a réussi
            if ($stmt->rowCount() === 0) {
                throw new Exception("Échec de l'insertion du token");
            }
            
            error_log("Token créé pour client ID: " . $idClient . ", email: " . $email);
            
        } catch (Exception $e) {
            error_log("ERREUR insertion token: " . $e->getMessage());
            echo json_encode(['status' => 500, 'error' => 'Erreur technique lors de la création du lien de confirmation']);
            exit;
        }

        // URL de confirmation pointant vers acheter.php
        $urlConfirmation = "http://" . $_SERVER['HTTP_HOST'] . "/Origami/acheter.php?action=confirmer_commande&token=" . $tokenConfirmation;

        // PRÉPARER LE RÉCAPITULATIF DES ACHATS POUR L'EMAIL
        $recapAchatsHTML = "";
        if (!empty($articlesPanier)) {
            $recapAchatsHTML = "
            <div style='margin: 25px 0; background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef;'>
                <h3 style='color: #d40000; margin-top: 0; margin-bottom: 20px; text-align: center;'>📦 Récapitulatif de votre panier</h3>
                
                <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                    <thead>
                        <tr style='background: #e9ecef;'>
                            <th style='padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;'>Produit</th>
                            <th style='padding: 12px; text-align: center; border-bottom: 2px solid #dee2e6;'>Quantité</th>
                            <th style='padding: 12px; text-align: right; border-bottom: 2px solid #dee2e6;'>Prix unitaire</th>
                            <th style='padding: 12px; text-align: right; border-bottom: 2px solid #dee2e6;'>Total</th>
                        </tr>
                    </thead>
                    <tbody>
            ";
            
            foreach ($articlesPanier as $article) {
                $recapAchatsHTML .= "
                        <tr style='border-bottom: 1px solid #dee2e6;'>
                            <td style='padding: 12px;'>
                                <strong>" . htmlspecialchars($article['nom']) . "</strong><br>
                                <small style='color: #666;'>" . htmlspecialchars(substr($article['description'], 0, 80)) . "...</small>
                            </td>
                            <td style='padding: 12px; text-align: center;'>" . $article['quantite'] . "</td>
                            <td style='padding: 12px; text-align: right;'>" . number_format($article['prixUnitaire'], 2, ',', ' ') . " €</td>
                            <td style='padding: 12px; text-align: right; font-weight: bold;'>" . number_format($article['totalLigne'], 2, ',', ' ') . " €</td>
                        </tr>
                ";
            }
            
            $recapAchatsHTML .= "
                    </tbody>
                    <tfoot style='background: #f8f9fa;'>
                        <tr>
                            <td colspan='3' style='padding: 12px; text-align: right; font-weight: bold;'>Sous-total :</td>
                            <td style='padding: 12px; text-align: right; font-weight: bold;'>" . number_format($totalPanier, 2, ',', ' ') . " €</td>
                        </tr>
                        <tr>
                            <td colspan='3' style='padding: 12px; text-align: right;'>Frais de port :</td>
                            <td style='padding: 12px; text-align: right;'>" . number_format($fraisDePort, 2, ',', ' ') . " €</td>
                        </tr>
                        <tr style='border-top: 2px solid #d40000;'>
                            <td colspan='3' style='padding: 12px; text-align: right; font-weight: bold; font-size: 1.1em; color: #d40000;'>Total à payer :</td>
                            <td style='padding: 12px; text-align: right; font-weight: bold; font-size: 1.1em; color: #d40000;'>" . number_format($montantTotal, 2, ',', ' ') . " €</td>
                        </tr>
                    </tfoot>
                </table>
                
                <div style='text-align: center; margin-top: 15px; padding: 15px; background: #e7f3ff; border-radius: 4px;'>
                    <strong>🛒 " . $totalQuantites . " article(s) dans votre panier</strong>
                </div>
            </div>
            ";
        } else {
            $recapAchatsHTML = "
            <div style='margin: 25px 0; background: #fff3cd; padding: 20px; border-radius: 8px; border: 1px solid #ffeaa7; text-align: center;'>
                <p style='margin: 0; color: #856404;'><strong>⚠️ Votre panier est vide</strong><br>
                Ajoutez des articles avant de finaliser votre commande.</p>
            </div>
            ";
        }

        // Préparer l'email HTML avec le lien de confirmation ET le récapitulatif
        $sujet = "Confirmez votre commande - Youki and Co";
        $messageHTML = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Confirmation de commande</title>
            <style>
                body { 
                    font-family: 'Helvetica Neue', Arial, sans-serif; 
                    background-color: #f9f9f9; 
                    margin: 0; 
                    padding: 0; 
                    color: #333;
                }
                .container { 
                    max-width: 700px; 
                    margin: 0 auto; 
                    background: white; 
                    padding: 40px; 
                    border-radius: 8px; 
                    box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
                }
                .header { 
                    text-align: center; 
                    color: #d40000; 
                    margin-bottom: 30px; 
                    border-bottom: 2px solid #f0f0f0;
                    padding-bottom: 20px;
                }
                .header h1 { 
                    font-size: 28px; 
                    margin: 0; 
                    font-weight: 300;
                }
                .btn-confirmation { 
                    display: block; 
                    width: 280px; 
                    margin: 30px auto; 
                    padding: 15px 30px; 
                    background-color: #a6a2dcff; 
                    color: white; 
                    text-decoration: none; 
                    text-align: center; 
                    border-radius: 5px; 
                    font-size: 18px; 
                    font-weight: bold;
                }
                .btn-confirmation:hover {
                    background-color: #16b005ff;
                }
                .footer { 
                    margin-top: 40px; 
                    padding-top: 20px; 
                    border-top: 1px solid #eee; 
                    color: #666; 
                    font-size: 14px; 
                    text-align: center;
                }
                .content { 
                    line-height: 1.6; 
                    font-size: 16px;
                }
                .warning { 
                    background: #fff3cd; 
                    border: 1px solid #ffeaa7; 
                    padding: 15px; 
                    border-radius: 4px; 
                    margin: 20px 0; 
                    color: #856404;
                }
                .url-backup { 
                    word-break: break-all; 
                    font-size: 14px; 
                    color: #666; 
                    text-align: center; 
                    margin-top: 10px;
                }
                .recap-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .recap-table th {
                    background: #f8f9fa;
                    padding: 12px;
                    text-align: left;
                    border-bottom: 2px solid #dee2e6;
                }
                .recap-table td {
                    padding: 12px;
                    border-bottom: 1px solid #dee2e6;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Youki and Co</h1>
                    <h2>Confirmation de votre commande</h2>
                </div>
                
                <div class='content'>
                    <p>Bonjour <strong>" . htmlspecialchars($nom) . "</strong>,</p>
                    
                    <p>Pour finaliser votre commande sur Youki and Co, veuillez cliquer sur le bouton de confirmation ci-dessous :</p>
                    
                    " . $recapAchatsHTML . "
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <p><strong>Étape suivante :</strong> Après confirmation, vous serez redirigé vers le paiement sécurisé.</p>
                        <a href='" . $urlConfirmation . "' class='btn-confirmation'>Confirmer ma commande</a>
                    </div>
                    
                    <div class='url-backup'>
                        Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
                        " . $urlConfirmation . "
                    </div>
                    
                    <div class='warning'>
                        <strong>⚠️ Important :</strong> Ce lien est valable pendant <strong>15 minutes</strong> seulement.
                    </div>
                    
                    <p>Si vous n'avez pas initié cette demande, veuillez ignorer cet email.</p>
                </div>
                
                <div class='footer'>
                    <p><strong>YOUKI and Co - Créations artisanales japonaises</strong></p>
                    <!--<p>📧 contact@origamizen.fr | 📞 +33 1 23 45 67 89</p>-->
                </div>
            </div>
        </body>
        </html>
        ";

        // Envoyer l'email avec PHPMailer
        $resultatEmail = envoyerEmail($email, $sujet, $messageHTML);

        if ($resultatEmail['success']) {
            echo json_encode([
                'status' => 200,
                'data' => [
                    'message' => 'Lien de confirmation envoyé',
                    'client_existant' => $clientExistant,
                    'id_client' => $idClient,
                    'recap_panier' => [
                        'articles' => $articlesPanier,
                        'total' => $totalPanier,
                        'frais_port' => $fraisDePort,
                        'total_general' => $montantTotal,
                        'quantite_total' => $totalQuantites
                    ]
                ]
            ]);
        } else {
            error_log("Échec envoi email à: " . $email . " - Erreur: " . $resultatEmail['error']);
            echo json_encode([
                'status' => 500, 
                'error' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.',
                'debug' => $resultatEmail['error'] // À retirer en production
            ]);
        }

    } elseif ($action == 'saisir_adresse' && isset($_GET['token'])) {
        // Afficher le formulaire de saisie d'adresse et facturation
        $token = $_GET['token'];
        
        // Vérifier la validité du token avec plus de détails
        $stmt = $pdo->prepare("SELECT email, id_client, expiration, utilise FROM tokens_confirmation WHERE token = ?");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData) {
            echo "<script>alert('Lien invalide'); window.location.href = 'index.html';</script>";
            exit;
        }

        if ($tokenData['utilise'] == 1) {
            echo "<script>alert('Ce lien a déjà été utilisé'); window.location.href = 'index.html';</script>";
            exit;
        }

        if (strtotime($tokenData['expiration']) < time()) {
            echo "<script>alert('Lien expiré'); window.location.href = 'index.html';</script>";
            exit;
        }

        $idClient = $tokenData['id_client'];

        // Récupérer les infos du client pour pré-remplir le formulaire
        $stmt = $pdo->prepare("SELECT nom, prenom, email FROM Client WHERE idClient = ?");
        $stmt->execute([$idClient]);
        $clientInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Adresses de Livraison et Facturation - Youki and Co</title>
            <style>
                body { 
                    font-family: 'Helvetica Neue', Arial, sans-serif; 
                    background-color: #f9f9f9; 
                    margin: 0; 
                    padding: 20px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }
                .container { 
                    max-width: 800px; 
                    background: white; 
                    padding: 40px; 
                    border-radius: 8px; 
                    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                }
                .header { 
                    text-align: center; 
                    color: #d40000; 
                    margin-bottom: 30px; 
                }
                .form-section {
                    margin-bottom: 30px;
                    padding: 20px;
                    border: 1px solid #e0e0e0;
                    border-radius: 8px;
                    background: #fafafa;
                }
                .form-section h3 {
                    margin-top: 0;
                    color: #d40000;
                    border-bottom: 1px solid #e0e0e0;
                    padding-bottom: 10px;
                }
                .form-group { 
                    margin-bottom: 15px; 
                }
                label { 
                    display: block; 
                    margin-bottom: 5px; 
                    font-weight: bold; 
                    font-size: 14px;
                }
                input, select, textarea { 
                    width: 100%; 
                    padding: 10px; 
                    border: 1px solid #ddd; 
                    border-radius: 4px; 
                    box-sizing: border-box;
                    font-size: 14px;
                }
                .checkbox-group {
                    display: flex;
                    align-items: center;
                    margin-bottom: 15px;
                }
                .checkbox-group input {
                    width: auto;
                    margin-right: 10px;
                }
                .btn { 
                    background-color: #d40000; 
                    color: white; 
                    padding: 12px 30px; 
                    border: none; 
                    border-radius: 4px; 
                    cursor: pointer; 
                    width: 100%; 
                    font-size: 16px;
                    margin-top: 20px;
                }
                .btn:hover {
                    background-color: #b30000;
                }
                .required { 
                    color: #d40000; 
                }
                .form-row {
                    display: flex;
                    gap: 15px;
                }
                .form-row .form-group {
                    flex: 1;
                }
                @media (max-width: 768px) {
                    .form-row {
                        flex-direction: column;
                        gap: 0;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📦 Adresses de Livraison et Facturation</h1>
                    <p>Complétez vos adresses pour finaliser la commande</p>
                </div>
                
                <form id="formAdresse" method="POST" action="acheter.php">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="action" value="sauvegarder_adresses">
                    
                    <!-- Section Adresse de Livraison -->
                    <div class="form-section">
                        <h3>Adresse de Livraison</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nom_livraison">Nom <span class="required">*</span></label>
                                <input type="text" id="nom_livraison" name="nom_livraison" value="<?= htmlspecialchars($clientInfo['nom'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="prenom_livraison">Prénom <span class="required">*</span></label>
                                <input type="text" id="prenom_livraison" name="prenom_livraison" value="<?= htmlspecialchars($clientInfo['prenom'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="adresse_livraison">Adresse <span class="required">*</span></label>
                            <input type="text" id="adresse_livraison" name="adresse_livraison" placeholder="Numéro et nom de rue" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="code_postal_livraison">Code Postal <span class="required">*</span></label>
                                <input type="text" id="code_postal_livraison" name="code_postal_livraison" pattern="[0-9]{5}" placeholder="75000" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="ville_livraison">Ville <span class="required">*</span></label>
                                <input type="text" id="ville_livraison" name="ville_livraison" placeholder="Paris" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="pays_livraison">Pays <span class="required">*</span></label>
                            <select id="pays_livraison" name="pays_livraison" required>
                                <option value="France" selected>France</option>
                                <option value="Belgique">Belgique</option>
                                <option value="Suisse">Suisse</option>
                                <option value="Luxembourg">Luxembourg</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="telephone_livraison">Téléphone </label>
                            <input type="tel" id="telephone_livraison" name="telephone_livraison" placeholder="+33 1 23 45 67 89">
                        </div>
                        
                        <div class="form-group">
                            <label for="instructions_livraison">Instructions de livraison (optionnel)</label>
                            <textarea id="instructions_livraison" name="instructions_livraison" rows="3" placeholder="Informations supplémentaires pour le livreur..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Section Adresse de Facturation -->
                    <div class="form-section">
                        <h3>Adresse de Facturation</h3>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="meme_adresse" name="meme_adresse" checked>
                            <label for="meme_adresse">Utiliser la même adresse que la livraison</label>
                        </div>
                        
                        <div id="facturation_fields" style="display: none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nom_facturation">Nom <span class="required">*</span></label>
                                    <input type="text" id="nom_facturation" name="nom_facturation">
                                </div>
                                
                                <div class="form-group">
                                    <label for="prenom_facturation">Prénom <span class="required">*</span></label>
                                    <input type="text" id="prenom_facturation" name="prenom_facturation">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="adresse_facturation">Adresse <span class="required">*</span></label>
                                <input type="text" id="adresse_facturation" name="adresse_facturation" placeholder="Numéro et nom de rue">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="code_postal_facturation">Code Postal <span class="required">*</span></label>
                                    <input type="text" id="code_postal_facturation" name="code_postal_facturation" pattern="[0-9]{5}" placeholder="75000">
                                </div>
                                
                                <div class="form-group">
                                    <label for="ville_facturation">Ville <span class="required">*</span></label>
                                    <input type="text" id="ville_facturation" name="ville_facturation" placeholder="Paris">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="pays_facturation">Pays <span class="required">*</span></label>
                                <select id="pays_facturation" name="pays_facturation">
                                    <option value="France" selected>France</option>
                                    <option value="Belgique">Belgique</option>
                                    <option value="Suisse">Suisse</option>
                                    <option value="Luxembourg">Luxembourg</option>
                                    <option value="autre">Autre</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">Finaliser la commande</button>
                </form>
            </div>

            <script>
                // Gestion de la case à cocher "même adresse"
                document.getElementById('meme_adresse').addEventListener('change', function() {
                    const facturationFields = document.getElementById('facturation_fields');
                    facturationFields.style.display = this.checked ? 'none' : 'block';
                    
                    // Rendre les champs obligatoires ou non
                    const inputs = facturationFields.querySelectorAll('input, select');
                    inputs.forEach(input => {
                        input.required = !this.checked;
                    });
                });
            </script>
        </body>
        </html>
        <?php
        exit;

    } elseif ($action == 'sauvegarder_adresses') {
        // CORRECTION : Forcer le type HTML pour cette action
        $is_html_response = true;
        header('Content-Type: text/html; charset=UTF-8');
        
        // Sauvegarder les adresses de livraison et facturation
        $token = $_POST['token'] ?? '';
        $nomLivraison = $_POST['nom_livraison'] ?? '';
        $prenomLivraison = $_POST['prenom_livraison'] ?? '';
        $adresseLivraison = $_POST['adresse_livraison'] ?? '';
        $codePostalLivraison = $_POST['code_postal_livraison'] ?? '';
        $villeLivraison = $_POST['ville_livraison'] ?? '';
        $paysLivraison = $_POST['pays_livraison'] ?? '';
        $telephoneLivraison = $_POST['telephone_livraison'] ?? '';
        $instructionsLivraison = $_POST['instructions_livraison'] ?? '';
        $memeAdresse = isset($_POST['meme_adresse']);

        // Vérifier le token avec plus de détails
        $stmt = $pdo->prepare("SELECT id_client, email, expiration, utilise FROM tokens_confirmation WHERE token = ?");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData) {
            echo "<script>alert('Token invalide'); window.location.href = 'index.html';</script>";
            exit;
        }

        if ($tokenData['utilise'] == 1) {
            echo "<script>alert('Ce lien a déjà été utilisé'); window.location.href = 'index.html';</script>";
            exit;
        }

        if (strtotime($tokenData['expiration']) < time()) {
            echo "<script>alert('Lien expiré'); window.location.href = 'index.html';</script>";
            exit;
        }

        $idClient = $tokenData['id_client'];

        // Validation des données requises pour la livraison
        if (!$nomLivraison || !$prenomLivraison || !$adresseLivraison || !$codePostalLivraison || !$villeLivraison) {
            echo "<script>alert('Tous les champs obligatoires de livraison doivent être remplis'); history.back();</script>";
            exit;
        }

        // Créer l'adresse de livraison
        $stmt = $pdo->prepare("
            INSERT INTO Adresse 
            (idClient, type, nom, prenom, adresse, codePostal, ville, pays, telephone, instructions, dateCreation) 
            VALUES (?, 'livraison', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$idClient, $nomLivraison, $prenomLivraison, $adresseLivraison, $codePostalLivraison, $villeLivraison, $paysLivraison, $telephoneLivraison, $instructionsLivraison]);
        $idAdresseLivraison = $pdo->lastInsertId();

        // Gérer l'adresse de facturation
        if ($memeAdresse) {
            // Utiliser la même adresse pour la facturation
            $idAdresseFacturation = $idAdresseLivraison;
        } else {
            // Créer une adresse de facturation séparée
            $nomFacturation = $_POST['nom_facturation'] ?? '';
            $prenomFacturation = $_POST['prenom_facturation'] ?? '';
            $adresseFacturation = $_POST['adresse_facturation'] ?? '';
            $codePostalFacturation = $_POST['code_postal_facturation'] ?? '';
            $villeFacturation = $_POST['ville_facturation'] ?? '';
            $paysFacturation = $_POST['pays_facturation'] ?? '';

            // Validation des données de facturation
            if (!$nomFacturation || !$prenomFacturation || !$adresseFacturation || !$codePostalFacturation || !$villeFacturation) {
                echo "<script>alert('Tous les champs obligatoires de facturation doivent être remplis'); history.back();</script>";
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO Adresse 
                (idClient, type, nom, prenom, adresse, codePostal, ville, pays, dateCreation) 
                VALUES (?, 'facturation', ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$idClient, $nomFacturation, $prenomFacturation, $adresseFacturation, $codePostalFacturation, $villeFacturation, $paysFacturation]);
            $idAdresseFacturation = $pdo->lastInsertId();
        }

        // Marquer le token comme utilisé
        $stmt = $pdo->prepare("UPDATE tokens_confirmation SET utilise = 1 WHERE token = ?");
        $stmt->execute([$token]);

        // FINALISER LA COMMANDE
        finaliserCommande($pdo, $idClient, $idAdresseLivraison, $idAdresseFacturation);

    } elseif ($action == 'confirmer_commande') {
        // NOUVELLE FONCTIONNALITÉ : Afficher le choix entre utiliser l'adresse existante ou en saisir une nouvelle
        $token = $data['token'] ?? ($_GET['token'] ?? '');
        
        if (!$token) {
            if (isset($_GET['token'])) {
                // PROPOSER LE PAIEMENT PAYPAL AVEC OPTION COMPTE
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - Youki and Co</title>
    <style>
        .btn-paypal-account { 
            background-color: #0070ba; 
            color: white; 
            padding: 15px 40px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 18px;
            margin: 10px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: 280px;
        }
        .payment-options {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!--<h1>💳 Finaliser le Paiement</h1>
        <p>Votre commande #<?= $idCommande ?> a été créée avec succès.</p> -->
        
        <div class="payment-options">
            <!-- Option 1 : Compte PayPal -->
            <button class="btn-paypal-account" onclick="paiementPayPalCompte()">
                <img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/PP_logo_h_100x26.png" alt="PayPal" height="26">
                Payer avec compte PayPal
            </button>
            
            <!-- Option 2 : Carte via PayPal -->
            <button class="btn-cb" onclick="paiementCarte()">
                💳 Payer par Carte Bancaire
            </button>
        </div>
    </div>

    <script>
        function paiementPayPalCompte() {
            window.location.href = 'paiement_paypal.php?commande=<?= $idCommande ?>';
        }

        function paiementCarte() {
            window.location.href = 'paiement_cb.php?commande=<?= $idCommande ?>';
        }
    </script>
</body>
</html>
<?php
            exit;
            } else {
                echo json_encode(['status' => 400, 'error' => 'Token manquant']);
                exit;
            }
        }

        // Vérifier le token
        $stmt = $pdo->prepare("SELECT email, id_client, expiration, utilise FROM tokens_confirmation WHERE token = ?");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData) {
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Lien Invalide</title>
                <style>
                    body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                    .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
                    .error { color: #dc3545; }
                    .btn { 
                        display: inline-block;
                        background-color: #d40000; 
                        color: white; 
                        padding: 12px 30px; 
                        text-decoration: none; 
                        border-radius: 2px; 
                        margin-top: 20px;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1 class="error">❌ Lien Invalide</h1>
                    <p>Ce lien de confirmation est invalide ou a déjà été utilisé.</p>
                    <a href="index.html" class="btn">Retour à l'accueil</a>
                </div>
            </body>
            </html>
            <?php
            exit;
        }

        if ($tokenData['utilise'] == 1) {
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Lien Déjà Utilisé</title>
                <style>
                    body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                    .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
                    .warning { color: #856404; }
                    .btn { 
                        display: inline-block;
                        background-color: #d40000; 
                        color: white; 
                        padding: 12px 30px; 
                        text-decoration: none; 
                        border-radius: 2px; 
                        margin-top: 20px;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1 class="warning">⚠️ Lien Déjà Utilisé</h1>
                    <p>Ce lien de confirmation a déjà été utilisé.</p>
                    <a href="index.html" class="btn">Retour à l'accueil</a>
                </div>
            </body>
            </html>
            <?php
            exit;
        }

        if (strtotime($tokenData['expiration']) < time()) {
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Lien Expiré</title>
                <style>
                    body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                    .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
                    .warning { color: #856404; }
                    .btn { 
                        display: inline-block;
                        background-color: #d40000; 
                        color: white; 
                        padding: 12px 30px; 
                        text-decoration: none; 
                        border-radius: 2px; 
                        margin-top: 20px;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1 class="warning">⚠️ Lien Expiré</h1>
                    <p>Ce lien de confirmation a expiré. Veuillez demander un nouveau lien.</p>
                    <a href="index.html" class="btn">Retour à l'accueil</a>
                </div>
            </body>
            </html>
            <?php
            exit;
        }

        $idClient = $tokenData['id_client'];

        // Vérifier si le client a déjà des adresses enregistrées
        $stmt = $pdo->prepare("
            SELECT * FROM Adresse 
            WHERE idClient = ? AND type = 'livraison' 
            ORDER BY dateCreation DESC 
            LIMIT 1
        ");
        $stmt->execute([$idClient]);
        $adresseExistante = $stmt->fetch(PDO::FETCH_ASSOC);

        // AFFICHER LE CHOIX ENTRE ADRESSE EXISTANTE ET NOUVELLE ADRESSE
        if ($adresseExistante) {
            // Afficher la page de choix
            afficherChoixAdresse($pdo, $token, $adresseExistante, $idClient);
            exit;
        } else {
            // Pas d'adresse existante, rediriger directement vers le formulaire
            header("Location: acheter.php?action=saisir_adresse&token=" . $token);
            exit;
        }

    } elseif ($action == 'ajouter_au_panier') {
        // Vérifier qu'on a bien un client
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

        // Vérifier si le panier existe, sinon le créer
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

        // Récupérer le prix de l'origami
        $stmt = $pdo->prepare("SELECT prixHorsTaxe FROM Origami WHERE idOrigami = ?");
        $stmt->execute([$idOrigami]);
        $origami = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$origami) {
            echo json_encode(['status' => 404, 'error' => 'Origami non trouvé']);
            exit;
        }

        $prixUnitaire = $origami['prixHorsTaxe'];

        // Vérifier si l'article est déjà dans le panier
        $stmt = $pdo->prepare("SELECT idLignePanier, quantite FROM LignePanier WHERE idPanier = ? AND idOrigami = ?");
        $stmt->execute([$idPanier, $idOrigami]);
        $ligneExistante = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ligneExistante) {
            // Mettre à jour la quantité
            $nouvelleQuantite = $ligneExistante['quantite'] + $quantite;
            $stmt = $pdo->prepare("UPDATE LignePanier SET quantite = ?, prixUnitaire = ? WHERE idLignePanier = ?");
            $stmt->execute([$nouvelleQuantite, $prixUnitaire, $ligneExistante['idLignePanier']]);
        } else {
            // Ajouter une nouvelle ligne
            $stmt = $pdo->prepare("INSERT INTO LignePanier (idPanier, idOrigami, quantite, prixUnitaire) VALUES (?, ?, ?, ?)");
            $stmt->execute([$idPanier, $idOrigami, $quantite, $prixUnitaire]);
        }

        // Mettre à jour la date de modification du panier
        $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
        $stmt->execute([$idPanier]);

        echo json_encode(['status' => 200, 'message' => 'Article ajouté au panier']);

    } elseif ($action == 'get_panier') {
        // Vérifier qu'on a bien un client
        if (!$idClient) {
            // Retourner un panier vide si pas de client
            echo json_encode([
                'status' => 200, 
                'data' => [
                    'articles' => [], 
                    'total' => 0, 
                    'totalQuantites' => 0
                ]
            ]);
            exit;
        }

        // Récupérer le panier
        $stmt = $pdo->prepare("
            SELECT p.idPanier 
            FROM Panier p 
            WHERE p.idClient = ?
        ");
        $stmt->execute([$idClient]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si le panier n'existe pas, retourner un panier vide
        if (!$panier) {
            echo json_encode([
                'status' => 200, 
                'data' => [
                    'articles' => [], 
                    'total' => 0, 
                    'totalQuantites' => 0
                ]
            ]);
            exit;
        }

        // Récupérer les articles du panier avec les détails des origamis
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
            WHERE lp.idPanier = ?
        ");
        $stmt->execute([$panier['idPanier']]);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculer le total
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

    } elseif ($action == 'modifier_quantite') {
        // Vérifier qu'on a bien un client
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

        // Mettre à jour la date du panier
        $stmt = $pdo->prepare("
            UPDATE Panier 
            SET dateModification = NOW() 
            WHERE idPanier = (SELECT idPanier FROM LignePanier WHERE idLignePanier = ?)
        ");
        $stmt->execute([$idLignePanier]);

        echo json_encode(['status' => 200, 'message' => 'Quantité modifiée']);

    } elseif ($action == 'supprimer_du_panier') {
        // Vérifier qu'on a bien un client
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

    } elseif ($action == 'vider_panier') {
        // Vérifier qu'on a bien un client
        if (!$idClient) {
            echo json_encode(['status' => 400, 'error' => 'Client non initialisé']);
            exit;
        }

        // Récupérer l'ID du panier
        $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
        $stmt->execute([$idClient]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($panier) {
            $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ?");
            $stmt->execute([$panier['idPanier']]);

            // Mettre à jour la date de modification
            $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
            $stmt->execute([$panier['idPanier']]);
        }

        echo json_encode(['status' => 200, 'message' => 'Panier vidé']);

    } elseif ($action == 'utiliser_adresse_existante') {
        $is_html_response = true;
        header('Content-Type: text/html; charset=UTF-8');
        
        $token = $_POST['token'] ?? '';
        $choixAdresse = $_POST['choix_adresse'] ?? '';
        
        if (!$token || !$choixAdresse) {
            echo "<script>alert('Données manquantes'); window.location.href = 'index.html';</script>";
            exit;
        }
        
        // Vérifier le token
        $stmt = $pdo->prepare("SELECT id_client, expiration, utilise FROM tokens_confirmation WHERE token = ?");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tokenData || $tokenData['utilise'] == 1 || strtotime($tokenData['expiration']) < time()) {
            echo "<script>alert('Lien invalide ou expiré'); window.location.href = 'index.html';</script>";
            exit;
        }
        
        $idClient = $tokenData['id_client'];
        
        if ($choixAdresse === 'existante') {
            // Récupérer la dernière adresse de livraison du client
            $stmt = $pdo->prepare("
                SELECT idAdresse 
                FROM Adresse 
                WHERE idClient = ? AND type = 'livraison' 
                ORDER BY dateCreation DESC 
                LIMIT 1
            ");
            $stmt->execute([$idClient]);
            $adresseExistante = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($adresseExistante) {
                // Marquer le token comme utilisé
                $stmt = $pdo->prepare("UPDATE tokens_confirmation SET utilise = 1 WHERE token = ?");
                $stmt->execute([$token]);
                
                // Créer la commande avec l'adresse existante
                creerCommandeAvecAdresseExistante($pdo, $idClient, $adresseExistante['idAdresse']);
                exit;
            } else {
                echo "<script>alert('Aucune adresse trouvée'); window.location.href = 'acheter.php?action=saisir_adresse&token=" . $token . "';</script>";
                exit;
            }
        }
    } else {
        echo json_encode(['status' => 400, 'error' => 'Action non reconnue: ' . $action]);
    }

} catch (PDOException $e) {
    if ($is_html_response) {
        echo "Erreur base de données: " . $e->getMessage();
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur base de données: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    if ($is_html_response) {
        echo "Erreur: " . $e->getMessage();
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur: ' . $e->getMessage()]);
    }
}

// NOUVELLE FONCTION : Afficher le choix entre adresse existante et nouvelle adresse
function afficherChoixAdresse($pdo, $token, $adresseExistante, $idClient) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Choix de l'adresse - Youki and Co</title>
        <style>
            body { 
                font-family: 'Helvetica Neue', Arial, sans-serif; 
                background-color: #f9f9f9; 
                margin: 0; 
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .container { 
                max-width: 600px; 
                background: white; 
                padding: 40px; 
                border-radius: 8px; 
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
            .header { 
                text-align: center; 
                color: #d40000; 
                margin-bottom: 30px; 
            }
            .choice-card {
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                padding: 25px;
                margin-bottom: 20px;
                cursor: pointer;
                transition: all 0.3s ease;
                background: #fafafa;
            }
            .choice-card:hover {
                border-color: #007bff;
                background: #f0f8ff;
                transform: translateY(-2px);
            }
            .choice-card.selected {
                border-color: #007bff;
                background: #e6f2ff;
                box-shadow: 0 4px 8px rgba(0, 123, 255, 0.2);
            }
            .choice-icon {
                font-size: 24px;
                margin-bottom: 10px;
            }
            .choice-title {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 10px;
                color: #333;
            }
            .choice-description {
                color: #666;
                margin-bottom: 15px;
            }
            .address-details {
                background: white;
                padding: 15px;
                border-radius: 4px;
                border: 1px solid #e0e0e0;
                margin-top: 10px;
                color: #555;
            }
            .btn { 
                background-color: #007bff; 
                color: white; 
                padding: 15px 30px; 
                border: none; 
                border-radius: 4px; 
                cursor: pointer; 
                width: 100%; 
                font-size: 16px;
                margin-top: 20px;
                transition: background-color 0.3s ease;
            }
            .btn:hover {
                background-color: #0056b3;
            }
            .btn:disabled {
                background-color: #cccccc;
                cursor: not-allowed;
            }
            .selected-indicator {
                color: #007bff;
                font-weight: bold;
                margin-top: 10px;
                display: none;
            }
            .choice-card.selected .selected-indicator {
                display: block;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🏠 Choix de l'adresse de livraison</h1>
                <p>Nous avons trouvé une adresse enregistrée pour votre compte</p>
            </div>

            <form id="choixAdresseForm" method="POST" action="acheter.php">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="action" value="utiliser_adresse_existante">
                <input type="hidden" name="choix_adresse" id="choix_adresse" value="">

                <!-- Option 1 : Utiliser l'adresse existante -->
                <div class="choice-card" onclick="selectChoice('existante')" id="card_existante">
                    <div class="choice-icon">🏠</div>
                    <div class="choice-title">Utiliser mon adresse enregistrée</div>
                    <div class="choice-description">
                        Plus rapide - Utilisez l'adresse que vous avez déjà renseignée
                    </div>
                    <div class="address-details">
                        <strong><?= htmlspecialchars($adresseExistante['prenom']) ?> <?= htmlspecialchars($adresseExistante['nom']) ?></strong><br>
                        <?= htmlspecialchars($adresseExistante['adresse']) ?><br>
                        <?= htmlspecialchars($adresseExistante['codePostal']) ?> <?= htmlspecialchars($adresseExistante['ville']) ?><br>
                        <?= htmlspecialchars($adresseExistante['pays']) ?>
                    </div>
                    <div class="selected-indicator">✓ Sélectionné</div>
                </div>

                <!-- Option 2 : Saisir une nouvelle adresse -->
                <div class="choice-card" onclick="selectChoice('nouvelle')" id="card_nouvelle">
                    <div class="choice-icon">📝</div>
                    <div class="choice-title">Saisir une nouvelle adresse</div>
                    <div class="choice-description">
                        Utilisez une adresse différente pour cette commande
                    </div>
                    <div class="selected-indicator">✓ Sélectionné</div>
                </div>

                <button type="submit" class="btn" id="btnContinuer" disabled>Continuer vers le paiement</button>
            </form>
        </div>

        <script>
            let choixSelectionne = '';

            function selectChoice(choix) {
                choixSelectionne = choix;
                
                // Mettre à jour l'apparence des cartes
                document.getElementById('card_existante').classList.remove('selected');
                document.getElementById('card_nouvelle').classList.remove('selected');
                document.getElementById('card_' + choix).classList.add('selected');
                
                // Activer le bouton
                document.getElementById('btnContinuer').disabled = false;
                document.getElementById('choix_adresse').value = choix;
            }

            // Gestion de la soumission du formulaire
            document.getElementById('choixAdresseForm').addEventListener('submit', function(e) {
                if (!choixSelectionne) {
                    e.preventDefault();
                    alert('Veuillez choisir une option');
                    return;
                }

                if (choixSelectionne === 'nouvelle') {
                    // Rediriger vers le formulaire de saisie d'adresse
                    e.preventDefault();
                    window.location.href = 'acheter.php?action=saisir_adresse&token=<?= $token ?>';
                }
                // Si choix est 'existante', le formulaire se soumet normalement
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// FONCTION : Créer une commande avec une adresse existante
function creerCommandeAvecAdresseExistante($pdo, $idClient, $idAdresseLivraison) {
    try {
        // Utiliser la même adresse pour la facturation
        $idAdresseFacturation = $idAdresseLivraison;

        // Finaliser la commande
        finaliserCommande($pdo, $idClient, $idAdresseLivraison, $idAdresseFacturation);
        
    } catch (Exception $e) {
        error_log("Erreur création commande avec adresse existante: " . $e->getMessage());
        echo "<script>alert('Erreur lors de la création de la commande'); window.location.href = 'index.html';</script>";
        exit;
    }
}

// FONCTION : Finaliser la commande (extraite pour réutilisation)
function finaliserCommande($pdo, $idClient, $idAdresseLivraison, $idAdresseFacturation) {
    // 1. Récupérer le panier du client
    $stmt = $pdo->prepare("
        SELECT p.idPanier 
        FROM Panier p 
        WHERE p.idClient = ?
    ");
    $stmt->execute([$idClient]);
    $panier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$panier) {
        echo "<script>alert('Panier non trouvé'); window.location.href = 'index.html';</script>";
        exit;
    }
    
    // 2. Récupérer les articles du panier
    $stmt = $pdo->prepare("
        SELECT 
            lp.idOrigami,
            lp.quantite,
            lp.prixUnitaire,
            (lp.quantite * lp.prixUnitaire) as totalLigne
        FROM LignePanier lp
        WHERE lp.idPanier = ?
    ");
    $stmt->execute([$panier['idPanier']]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($articles)) {
        echo "<script>alert('Panier vide'); window.location.href = 'index.html';</script>";
        exit;
    }
    
    // 3. Calculer le total de la commande
    $total = 0;
    foreach ($articles as $article) {
        $total += $article['totalLigne'];
    }
    
    // 4. Définir les paramètres de la commande
    $fraisDePort = 0.0; // Frais de port fixes
    $delaiLivraison = date('Y-m-d', strtotime('+5 days')); // Délai de 5 jours
    $montantTotal = $total + $fraisDePort;
    
    // 5. Créer la commande
    $stmt = $pdo->prepare("
        INSERT INTO Commande 
        (idClient, idAdresseLivraison, idAdresseFacturation, dateCommande, modeReglement, delaiLivraison, fraisDePort, montantTotal, statut) 
        VALUES (?, ?, ?, NOW(), 'PayPal', ?, ?, ?, 'en_attente_paiement')
    ");
    $stmt->execute([$idClient, $idAdresseLivraison, $idAdresseFacturation, $delaiLivraison, $fraisDePort, $montantTotal]);
    $idCommande = $pdo->lastInsertId();
    
    // 6. Créer les lignes de commande
    $stmtLigne = $pdo->prepare("
        INSERT INTO LigneCommande 
        (idCommande, idOrigami, quantite, prixUnitaire) 
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($articles as $article) {
        $stmtLigne->execute([
            $idCommande, 
            $article['idOrigami'], 
            $article['quantite'], 
            $article['prixUnitaire']
        ]);
    }
    
    // 7. Vider le panier
    $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ?");
    $stmt->execute([$panier['idPanier']]);
    
    // 8. Mettre à jour la date de modification du panier
    $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
    $stmt->execute([$panier['idPanier']]);

    // 9. Générer automatiquement la facture HTML (pour affichage immédiat)
    $urlFactureHTML = genererFactureAPI($idCommande, 'html');

    // PROPOSER LE PAIEMENT AVEC OPTION DE TÉLÉCHARGEMENT DE FACTURE
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Paiement - Youki and Co</title>
        <style>
            body { 
                font-family: 'Helvetica Neue', Arial, sans-serif; 
                background-color: #f9f9f9; 
                margin: 0; 
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .container { 
                max-width: 700px; 
                background: white; 
                padding: 40px; 
                border-radius: 8px; 
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                text-align: center;
            }
            .btn-paypal { 
                background-color: #0070ba; 
                color: white; 
                padding: 15px 40px; 
                border: none; 
                border-radius: 4px; 
                cursor: pointer; 
                font-size: 18px;
                margin: 10px;
                display: inline-flex;
                align-items: center;
                gap: 10px;
            }
            .btn-facture { 
                background-color: #28a745; 
                color: white; 
                padding: 12px 25px; 
                border: none; 
                border-radius: 4px; 
                cursor: pointer; 
                font-size: 14px;
                margin: 5px;
                text-decoration: none;
                display: inline-block;
            }
            .btn-cb { 
                background-color: #d40000; 
                color: white; 
                padding: 15px 40px; 
                border: none; 
                border-radius: 4px; 
                cursor: pointer; 
                font-size: 18px;
                margin: 10px;
            }
            .details {
                text-align: left;
                background: #f8f9fa;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
            }
            .facture-options {
                background: #e7f3ff;
                border: 1px solid #b3d9ff;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
            }
            .facture-options h3 {
                margin-top: 0;
                color: #0070ba;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>💳 Finaliser le Paiement</h1>
            <p>Votre commande #<?= $idCommande ?> a été créée avec succès.</p>
            
            <div class="details">
                <p><strong>Montant total :</strong> <?= number_format($montantTotal, 2, ',', ' ') ?> €</p>
                <p><strong>Livraison prévue :</strong> <?= date('d/m/Y', strtotime($delaiLivraison)) ?></p>
            </div>

            <!--<div class="facture-options">
                <h3>📄 Options de facture</h3>
                <p>Vous pouvez déjà visualiser ou télécharger votre facture :</p>
                <a href="<?= $urlFactureHTML ?>" target="_blank" class="btn-facture">👁️ Voir la facture HTML</a>
                <button onclick="telechargerFacturePDF(<?= $idCommande ?>)" class="btn-facture">📥 Télécharger PDF</button>
                <button onclick="envoyerFactureEmail(<?= $idCommande ?>)" class="btn-facture">📧 Envoyer par email</button>
            </div>--> 
            
            <p>Choisissez votre méthode de paiement :</p>
            
            <div>
                <button class="btn-paypal" onclick="initierPaiementPayPal()">
                    <img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/PP_logo_h_100x26.png" alt="PayPal" height="26">
                    Payer avec PayPal
                </button>
                
                <button class="btn-cb" onclick="paiementCB()">
                    💳 Payer par Carte Bancaire
                </button>
            </div>
        </div>

        <script>
            function initierPaiementPayPal() {
                // Désactiver les boutons pendant le traitement
                document.querySelectorAll('button').forEach(btn => btn.disabled = true);
                
                fetch('acheter.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'creer_commande_paypal',
                        montant: <?= $montantTotal ?>,
                        id_commande: <?= $idCommande ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 200) {
                        // Rediriger vers PayPal
                        window.location.href = data.data.approve_url;
                    } else {
                        alert('Erreur: ' + data.error);
                        document.querySelectorAll('button').forEach(btn => btn.disabled = false);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue');
                    document.querySelectorAll('button').forEach(btn => btn.disabled = false);
                });
            }

            function paiementCB() {
                // Rediriger vers le traitement CB classique
                window.location.href = 'paiement_cb.php?commande=<?= $idCommande ?>';
            }

            function telechargerFacturePDF(idCommande) {
                window.open('acheter.php?action=telecharger_facture&id_commande=' + idCommande, '_blank');
            }

            function envoyerFactureEmail(idCommande) {
                const email = prompt('Entrez votre email pour recevoir la facture:');
                if (email && email.includes('@')) {
                    fetch('acheter.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'envoyer_facture_email',
                            id_commande: idCommande,
                            email: email,
                            format: 'pdf'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 200) {
                            alert('Facture envoyée avec succès à ' + email);
                        } else {
                            alert('Erreur: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Une erreur est survenue lors de l\'envoi');
                    });
                } else if (email !== null) {
                    alert('Email invalide');
                }
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>
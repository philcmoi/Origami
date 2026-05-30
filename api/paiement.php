<?php
// api/paiement.php - API de paiement unifiée PayPal
session_start();
require_once '../config.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

function getPDOConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("Erreur connexion BDD: " . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

function createPayPalOrder($amount, $description = '', $items = []) {
    $paypalConfig = [
        'client_id' => PAYPAL_CLIENT_ID,
        'client_secret' => PAYPAL_CLIENT_SECRET,
        'environment' => PAYPAL_ENVIRONMENT,
        'currency' => 'EUR'
    ];
    
    // Obtenir l'access token
    $access_token = getPayPalAccessToken(
        $paypalConfig['client_id'],
        $paypalConfig['client_secret'],
        $paypalConfig['environment']
    );
    
    if (!$access_token) {
        return ['success' => false, 'message' => 'Erreur de connexion PayPal'];
    }
    
    // URL API PayPal
    $url = $paypalConfig['environment'] === 'live' 
        ? 'https://api.paypal.com/v2/checkout/orders'
        : 'https://api.sandbox.paypal.com/v2/checkout/orders';
    
    // Données de la commande
    $data = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'amount' => [
                'currency_code' => $paypalConfig['currency'],
                'value' => number_format($amount, 2, '.', '')
            ],
            'description' => $description
        ]],
        'application_context' => [
            'return_url' => PAYPAL_RETURN_URL,
            'cancel_url' => PAYPAL_CANCEL_URL,
            'brand_name' => 'HEURE DU CADEAU',
            'user_action' => 'PAY_NOW'
        ]
    ];
    
    // Ajouter les items si disponibles
    if (!empty($items)) {
        $data['purchase_units'][0]['items'] = $items;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
        'PayPal-Request-Id: ' . uniqid()
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 201) {
        $response = json_decode($result, true);
        return [
            'success' => true,
            'order_id' => $response['id'],
            'status' => $response['status']
        ];
    } else {
        error_log("Erreur création commande PayPal: " . $result);
        return [
            'success' => false,
            'message' => 'Erreur PayPal: ' . $http_code
        ];
    }
}

function getPayPalAccessToken($client_id, $client_secret, $environment) {
    $url = $environment === 'live' 
        ? 'https://api.paypal.com/v1/oauth2/token'
        : 'https://api.sandbox.paypal.com/v1/oauth2/token';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
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
    }
    return false;
}

function capturePayPalPayment($orderId) {
    $paypalConfig = [
        'client_id' => PAYPAL_CLIENT_ID,
        'client_secret' => PAYPAL_CLIENT_SECRET,
        'environment' => PAYPAL_ENVIRONMENT
    ];
    
    $access_token = getPayPalAccessToken(
        $paypalConfig['client_id'],
        $paypalConfig['client_secret'],
        $paypalConfig['environment']
    );
    
    if (!$access_token) {
        return ['success' => false, 'message' => 'Erreur de connexion PayPal'];
    }
    
    $url = $paypalConfig['environment'] === 'live' 
        ? 'https://api.paypal.com/v2/checkout/orders/' . $orderId . '/capture'
        : 'https://api.sandbox.paypal.com/v2/checkout/orders/' . $orderId . '/capture';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
        'PayPal-Request-Id: ' . uniqid()
    ]);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 201 || $http_code == 200) {
        $response = json_decode($result, true);
        return [
            'success' => true,
            'status' => $response['status'],
            'capture_id' => $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? $orderId
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Erreur capture PayPal: ' . $http_code
        ];
    }
}

function saveOrderToDB($orderData, $paymentMethod, $transactionId, $customerId = null, $status = 'paye') {
    $pdo = getPDOConnection();
    if (!$pdo) return false;
    
    try {
        $pdo->beginTransaction();
        
        // Générer numéro de commande
        $numeroCommande = 'CMD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Insérer la commande
        $stmt = $pdo->prepare("
            INSERT INTO commandes (
                numero_commande, 
                id_client, 
                statut, 
                statut_paiement, 
                sous_total, 
                total_ttc, 
                mode_paiement, 
                reference_paiement,
                date_commande,
                date_paiement
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $statutCommande = $status === 'paye' ? 'confirmee' : 'en_attente';
        $modePaiement = ($paymentMethod === 'card') ? 'carte' : 'paypal';
        
        $stmt->execute([
            $numeroCommande,
            $customerId,
            $statutCommande,
            $status,
            $orderData['total'],
            $orderData['total'],
            $modePaiement,
            $transactionId
        ]);
        
        $idCommande = $pdo->lastInsertId();
        
        // Insérer les items
        if (isset($orderData['items']) && is_array($orderData['items'])) {
            foreach ($orderData['items'] as $item) {
                // Récupérer infos produit
                $stmtProduit = $pdo->prepare("
                    SELECT reference, prix_ht, tva 
                    FROM produits 
                    WHERE id_produit = ?
                ");
                $stmtProduit->execute([$item['id_produit']]);
                $produit = $stmtProduit->fetch();
                
                $prixHT = $produit ? $produit['prix_ht'] : $item['prix_unitaire'] / 1.2;
                $tva = $produit ? $produit['tva'] : 20.00;
                
                // Insérer item
                $stmtItem = $pdo->prepare("
                    INSERT INTO commande_items (
                        id_commande, 
                        id_produit, 
                        reference_produit, 
                        nom_produit, 
                        quantite, 
                        prix_unitaire_ht,
                        prix_unitaire_ttc,
                        tva
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmtItem->execute([
                    $idCommande,
                    $item['id_produit'],
                    $produit['reference'] ?? 'REF-' . $item['id_produit'],
                    $item['nom'],
                    $item['quantite'],
                    round($prixHT, 2),
                    $item['prix_unitaire'],
                    $tva
                ]);
                
                // Mettre à jour le stock
                if ($status === 'paye') {
                    $stmtStock = $pdo->prepare("
                        UPDATE produits 
                        SET quantite_stock = quantite_stock - ?,
                            ventes = ventes + ?
                        WHERE id_produit = ?
                    ");
                    $stmtStock->execute([
                        $item['quantite'],
                        $item['quantite'],
                        $item['id_produit']
                    ]);
                }
            }
        }
        
        // Vider le panier
        if (isset($_SESSION['id_panier'])) {
            $stmtViderPanier = $pdo->prepare("DELETE FROM panier_items WHERE id_panier = ?");
            $stmtViderPanier->execute([$_SESSION['id_panier']]);
        }
        
        $pdo->commit();
        
        return [
            'id_commande' => $idCommande,
            'numero_commande' => $numeroCommande,
            'total_ttc' => $orderData['total']
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erreur enregistrement commande: " . $e->getMessage());
        return false;
    }
}

// Traitement des requêtes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (empty($data) || !isset($data['action'])) {
        sendError('Action non spécifiée');
    }
    
    $action = $data['action'];
    
    switch ($action) {
        case 'create_paypal_order':
            if (!isset($data['orderData']) || !isset($data['orderData']['total'])) {
                sendError('Données de commande invalides');
            }
            
            $amount = $data['orderData']['total'];
            $description = $data['description'] ?? 'Commande HEURE DU CADEAU';
            
            // Créer items PayPal
            $paypalItems = [];
            if (isset($data['orderData']['items']) && is_array($data['orderData']['items'])) {
                foreach ($data['orderData']['items'] as $item) {
                    $paypalItems[] = [
                        'name' => substr($item['nom'], 0, 127),
                        'quantity' => $item['quantite'],
                        'unit_amount' => [
                            'currency_code' => 'EUR',
                            'value' => number_format($item['prix_unitaire'], 2, '.', '')
                        ]
                    ];
                }
            }
            
            $result = createPayPalOrder($amount, $description, $paypalItems);
            
            if ($result['success']) {
                // Stocker en session
                $_SESSION['paypal_order'] = [
                    'order_id' => $result['order_id'],
                    'order_data' => $data['orderData'],
                    'amount' => $amount
                ];
                
                echo json_encode([
                    'success' => true,
                    'order_id' => $result['order_id'],
                    'status' => $result['status']
                ]);
            } else {
                sendError($result['message']);
            }
            break;
            
        case 'capture_paypal_order':
            if (!isset($data['orderId'])) {
                sendError('ID de commande manquant');
            }
            
            $orderId = $data['orderId'];
            $paymentMethod = $data['paymentMethod'] ?? 'paypal';
            
            // Capturer le paiement
            $captureResult = capturePayPalPayment($orderId);
            
            if ($captureResult['success']) {
                // Récupérer données de commande
                $orderData = $_SESSION['paypal_order']['order_data'] ?? [];
                $customerId = $_SESSION['id_client'] ?? null;
                
                // Enregistrer en BDD
                $orderResult = saveOrderToDB(
                    $orderData,
                    $paymentMethod,
                    $captureResult['capture_id'] ?? $orderId,
                    $customerId,
                    'paye'
                );
                
                if ($orderResult) {
                    // Nettoyer session
                    unset($_SESSION['paypal_order']);
                    
                    // Log
                    $pdo = getPDOConnection();
                    if ($pdo) {
                        $stmtLog = $pdo->prepare("
                            INSERT INTO logs (type_log, niveau, message, utilisateur_id, ip_address)
                            VALUES ('paiement', 'info', ?, ?, ?)
                        ");
                        $stmtLog->execute([
                            'Paiement PayPal réussi - Ref: ' . ($captureResult['capture_id'] ?? $orderId),
                            $customerId,
                            $_SERVER['REMOTE_ADDR'] ?? null
                        ]);
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Paiement capturé avec succès',
                        'order' => $orderResult,
                        'payment_info' => [
                            'method' => $paymentMethod,
                            'transaction_id' => $captureResult['capture_id'] ?? $orderId,
                            'status' => $captureResult['status']
                        ]
                    ]);
                } else {
                    sendError('Erreur enregistrement commande');
                }
            } else {
                sendError($captureResult['message']);
            }
            break;
            
        default:
            sendError('Action non reconnue');
    }
    
} else {
    sendError('Méthode non autorisée', 405);
}
?>
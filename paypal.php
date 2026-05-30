<?php
// ============================================
// paypal.php - API PayPal pour paiements
// Version complète et fonctionnelle
// ============================================

session_start();

// Inclure la configuration
require_once __DIR__ . '/session_verification.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration PayPal - vos identifiants
define('PAYPAL_CLIENT_ID', 'Aac1-P0VrxBQ_5REVeo4f557_-p6BDeXA_hyiuVZfi21sILMWccBFfTidQ6nnhQathCbWaCSQaDmxJw5');
define('PAYPAL_CLIENT_SECRET', 'EJxech0i1faRYlo0-ln2sU09ecx5rP3XEOGUTeTduI2t-I0j4xoSPqRRFQTxQsJoSBbSL8aD1b1GPPG1');
define('PAYPAL_ENVIRONMENT', 'sandbox'); // sandbox ou live
define('PAYPAL_CURRENCY', 'EUR');

// URLs de retour
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
$base_url = $protocol . $_SERVER['HTTP_HOST'];
define('PAYPAL_RETURN_URL', $base_url . '/paiement-reussi-email.php');
define('PAYPAL_CANCEL_URL', $base_url . '/paiement-annule.php');

/**
 * Obtient le token d'accès PayPal
 */
function getPayPalAccessToken() {
    $url = PAYPAL_ENVIRONMENT === 'live' 
        ? 'https://api.paypal.com/v1/oauth2/token'
        : 'https://api.sandbox.paypal.com/v1/oauth2/token';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: fr_FR'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['access_token'])) {
            error_log("PayPal: Token obtenu avec succès");
            return $data['access_token'];
        }
    }
    
    error_log("PayPal: Erreur token - HTTP $httpCode - " . substr($response, 0, 200));
    if ($curlError) error_log("PayPal: cURL Error - $curlError");
    
    return null;
}

/**
 * Crée une commande PayPal
 */
function createPayPalOrder($montant, $commande_id) {
    $access_token = getPayPalAccessToken();
    if (!$access_token) {
        return ['success' => false, 'message' => 'Erreur authentification PayPal'];
    }
    
    $url = PAYPAL_ENVIRONMENT === 'live'
        ? 'https://api.paypal.com/v2/checkout/orders'
        : 'https://api.sandbox.paypal.com/v2/checkout/orders';
    
    $data = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'reference_id' => 'commande_' . ($commande_id ?: 'temp_' . time()),
                'custom_id' => (string)$commande_id,
                'description' => 'Commande Youki and Co',
                'amount' => [
                    'currency_code' => PAYPAL_CURRENCY,
                    'value' => number_format($montant, 2, '.', '')
                ]
            ]
        ],
        'application_context' => [
            'brand_name' => 'Youki and Co',
            'locale' => 'fr-FR',
            'landing_page' => 'LOGIN',
            'user_action' => 'PAY_NOW',
            'return_url' => PAYPAL_RETURN_URL,
            'cancel_url' => PAYPAL_CANCEL_URL
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token,
            'PayPal-Request-Id: ' . uniqid('order_'),
            'Prefer: return=representation'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201 || $httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['id'])) {
            error_log("PayPal: Commande créée - ID: " . $result['id']);
            return [
                'success' => true,
                'order_id' => $result['id'],
                'status' => $result['status']
            ];
        }
    }
    
    error_log("PayPal: Erreur création - HTTP $httpCode - " . substr($response, 0, 300));
    return ['success' => false, 'message' => 'Erreur création commande PayPal', 'http_code' => $httpCode];
}

/**
 * Capture un paiement PayPal
 */
function capturePayPalPayment($order_id) {
    $access_token = getPayPalAccessToken();
    if (!$access_token) {
        return ['success' => false, 'message' => 'Erreur authentification PayPal'];
    }
    
    $url = PAYPAL_ENVIRONMENT === 'live'
        ? 'https://api.paypal.com/v2/checkout/orders/' . $order_id . '/capture'
        : 'https://api.sandbox.paypal.com/v2/checkout/orders/' . $order_id . '/capture';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token,
            'Prefer: return=representation'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201 || $httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['status']) && $result['status'] === 'COMPLETED') {
            $capture_id = $result['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
            $amount = $result['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0;
            $custom_id = $result['purchase_units'][0]['custom_id'] ?? null;
            
            error_log("PayPal: Paiement capturé - ID: $capture_id, Montant: $amount");
            
            return [
                'success' => true,
                'capture_id' => $capture_id,
                'amount' => $amount,
                'commande_id' => $custom_id,
                'status' => $result['status']
            ];
        }
    }
    
    error_log("PayPal: Erreur capture - HTTP $httpCode - " . substr($response, 0, 300));
    return ['success' => false, 'message' => 'Erreur capture paiement PayPal'];
}

// ============================================
// GESTION DES ACTIONS
// ============================================

$action = $_GET['action'] ?? '';

// Action: créer une commande
if ($action === 'create_order') {
    $input = json_decode(file_get_contents('php://input'), true);
    $montant = floatval($input['montant'] ?? 0);
    $commande_id = intval($input['commande_id'] ?? 0);
    
    if ($montant <= 0) {
        echo json_encode(['success' => false, 'message' => 'Montant invalide']);
        exit;
    }
    
    $result = createPayPalOrder($montant, $commande_id);
    echo json_encode($result);
    exit;
}

// Action: capturer un paiement
if ($action === 'capture_order') {
    $order_id = $_GET['order_id'] ?? '';
    
    if (empty($order_id)) {
        echo json_encode(['success' => false, 'message' => 'Order ID manquant']);
        exit;
    }
    
    $result = capturePayPalPayment($order_id);
    echo json_encode($result);
    exit;
}

// Action: vérifier une commande
if ($action === 'check_order') {
    $order_id = $_GET['order_id'] ?? '';
    
    if (empty($order_id)) {
        echo json_encode(['success' => false, 'message' => 'Order ID manquant']);
        exit;
    }
    
    $access_token = getPayPalAccessToken();
    if (!$access_token) {
        echo json_encode(['success' => false, 'message' => 'Erreur authentification']);
        exit;
    }
    
    $url = PAYPAL_ENVIRONMENT === 'live'
        ? 'https://api.paypal.com/v2/checkout/orders/' . $order_id
        : 'https://api.sandbox.paypal.com/v2/checkout/orders/' . $order_id;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo $response;
    } else {
        echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
    }
    exit;
}

// Action par défaut
echo json_encode(['success' => false, 'message' => 'Action non reconnue: ' . $action]);
?>
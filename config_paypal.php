<?php
// config_paypal.php - Configuration PayPal réelle
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/paypal_errors.log');

// Configuration PayPal
define('PAYPAL_CLIENT_ID', 'Aac1-P0VrxBQ_5REVeo4f557_-p6BDeXA_hyiuVZfi21sILMWccBFfTidQ6nnhQathCbWaCSQaDmxJw5');
define('PAYPAL_CLIENT_SECRET', 'EJxech0i1faRYlo0-ln2sU09ecx5rP3XEOGUTeTduI2t-I0j4xoSPqRRFQTxQsJoSBbSL8aD1b1GPPG1');
define('PAYPAL_ENVIRONMENT', 'sandbox'); // sandbox ou live
define('PAYPAL_CURRENCY', 'EUR');

// URLs PayPal
define('PAYPAL_BASE_URL', PAYPAL_ENVIRONMENT === 'live' 
    ? 'https://api.paypal.com' 
    : 'https://api.sandbox.paypal.com');
define('PAYPAL_OAUTH_URL', PAYPAL_BASE_URL . '/v1/oauth2/token');
define('PAYPAL_ORDERS_URL', PAYPAL_BASE_URL . '/v2/checkout/orders');

// URLs de retour
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
define('PAYPAL_RETURN_URL', $base_url . '/api/paiement_reussi.php');
define('PAYPAL_CANCEL_URL', $base_url . '/api/paiement_annule.php');

// Connexion BDD
function getPDOConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;dbname=heureducadeau;charset=utf8",
                "Philippe",
                "l@99339R",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            error_log("Erreur connexion BDD: " . $e->getMessage());
            return false;
        }
    }
    
    return $pdo;
}

// Obtenir le token d'accès PayPal
function getPayPalAccessToken() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PAYPAL_OAUTH_URL);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ":" . PAYPAL_CLIENT_SECRET);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
        "Accept-Language: fr_FR"
    ]);
    
    // Désactiver la vérification SSL en développement
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        return $data['access_token'];
    } else {
        error_log("Erreur token PayPal ($httpCode): " . $response);
        return false;
    }
}

// Créer une commande PayPal
function createPayPalOrder($amount, $description = "Commande HEURE DU CADEAU", $items = []) {
    $accessToken = getPayPalAccessToken();
    if (!$accessToken) {
        return ['success' => false, 'message' => 'Erreur authentification PayPal'];
    }
    
    $data = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'amount' => [
                'currency_code' => PAYPAL_CURRENCY,
                'value' => number_format($amount, 2, '.', ''),
                'breakdown' => [
                    'item_total' => [
                        'currency_code' => PAYPAL_CURRENCY,
                        'value' => number_format($amount, 2, '.', '')
                    ]
                ]
            ],
            'description' => $description
        ]],
        'payment_source' => [
            'card' => [
                'attributes' => [
                    'verification' => [
                        'method' => 'SCA_ALWAYS'
                    ]
                ]
            ]
        ],
        'application_context' => [
            'return_url' => PAYPAL_RETURN_URL,
            'cancel_url' => PAYPAL_CANCEL_URL,
            'brand_name' => 'HEURE DU CADEAU',
            'locale' => 'fr-FR',
            'landing_page' => 'BILLING',
            'user_action' => 'PAY_NOW',
            'shipping_preference' => 'NO_SHIPPING'
        ]
    ];
    
    // Ajouter les items si disponibles
    if (!empty($items)) {
        $data['purchase_units'][0]['items'] = $items;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PAYPAL_ORDERS_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken,
        'PayPal-Partner-Attribution-Id: FR_HEUREDUCADEAU',
        'Prefer: return=representation'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    // Désactiver la vérification SSL en développement
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 201 || $httpCode == 200) {
        $result = json_decode($response, true);
        return [
            'success' => true,
            'order_id' => $result['id'],
            'status' => $result['status'],
            'links' => $result['links']
        ];
    } else {
        error_log("Erreur création commande PayPal ($httpCode): " . $response);
        return [
            'success' => false,
            'message' => 'Erreur création commande PayPal',
            'details' => $response
        ];
    }
}

// Capturer un paiement PayPal
function capturePayPalPayment($orderId) {
    $accessToken = getPayPalAccessToken();
    if (!$accessToken) {
        return ['success' => false, 'message' => 'Erreur authentification PayPal'];
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PAYPAL_ORDERS_URL . '/' . $orderId . '/capture');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken,
        'Prefer: return=representation'
    ]);
    
    // Désactiver la vérification SSL en développement
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 201 || $httpCode == 200) {
        $result = json_decode($response, true);
        return [
            'success' => true,
            'capture_id' => $result['purchase_units'][0]['payments']['captures'][0]['id'] ?? null,
            'status' => $result['status'],
            'payer' => $result['payer'] ?? null,
            'details' => $result
        ];
    } else {
        error_log("Erreur capture PayPal ($httpCode): " . $response);
        return [
            'success' => false,
            'message' => 'Erreur capture paiement PayPal',
            'details' => $response
        ];
    }
}

// Obtenir les détails d'une commande PayPal
function getPayPalOrderDetails($orderId) {
    $accessToken = getPayPalAccessToken();
    if (!$accessToken) {
        return ['success' => false, 'message' => 'Erreur authentification PayPal'];
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PAYPAL_ORDERS_URL . '/' . $orderId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);
    
    // Désactiver la vérification SSL en développement
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $result = json_decode($response, true);
        return ['success' => true, 'order' => $result];
    } else {
        return ['success' => false, 'message' => 'Erreur récupération commande'];
    }
}
?>
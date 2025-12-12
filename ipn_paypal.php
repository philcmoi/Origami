<?php
// ipn_paypal.php - Instant Payment Notification (PayPal)

// ============================================
// CONFIGURATION
// ============================================
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' ou 'live'
define('PAYPAL_SANDBOX_URL', 'https://www.sandbox.paypal.com/cgi-bin/webscr');
define('PAYPAL_LIVE_URL', 'https://www.paypal.com/cgi-bin/webscr');

// Logs
define('LOG_FILE', 'logs/paypal_ipn.log');
define('LOG_ENABLED', true);

// Base de données (à adapter)
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');

// Email de notification
define('ADMIN_EMAIL', 'lhpp.philippe@gmail.com');

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

function logMessage($message) {
    if (!LOG_ENABLED) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $logDir = dirname(__FILE__) . '/logs';
    
    // Créer le dossier logs s'il n'existe pas
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logDir . '/paypal_ipn.log', $logMessage, FILE_APPEND);
}

function sendEmailNotification($subject, $message, $commande_id = null) {
    $headers = "From: IPN Notification <no-reply@heureducadeau.fr>\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $fullMessage = "<html><body>";
    $fullMessage .= "<h2>$subject</h2>";
    if ($commande_id) {
        $fullMessage .= "<p><strong>Commande ID:</strong> $commande_id</p>";
    }
    $fullMessage .= "<pre>" . htmlspecialchars($message) . "</pre>";
    $fullMessage .= "<p><small>Date: " . date('Y-m-d H:i:s') . "</small></p>";
    $fullMessage .= "</body></html>";
    
    mail(ADMIN_EMAIL, $subject, $fullMessage, $headers);
}

function validateIPN() {
    // Récupérer les données POST brutes
    $raw_post_data = file_get_contents('php://input');
    $raw_post_array = explode('&', $raw_post_data);
    $myPost = array();
    
    foreach ($raw_post_array as $keyval) {
        $keyval = explode('=', $keyval);
        if (count($keyval) == 2) {
            $myPost[$keyval[0]] = urldecode($keyval[1]);
        }
    }
    
    // Préparer la requête de validation
    $req = 'cmd=_notify-validate';
    foreach ($myPost as $key => $value) {
        $value = urlencode($value);
        $req .= "&$key=$value";
    }
    
    // Choisir l'URL PayPal
    $paypal_url = (PAYPAL_MODE == 'sandbox') ? PAYPAL_SANDBOX_URL : PAYPAL_LIVE_URL;
    
    // Envoyer la requête à PayPal
    $ch = curl_init($paypal_url);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
    
    $res = curl_exec($ch);
    
    if (curl_errno($ch)) {
        logMessage("CURL ERROR: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    // Vérifier la réponse
    if (strcmp($res, "VERIFIED") == 0) {
        return $myPost;
    } else {
        logMessage("INVALID IPN: $res");
        return false;
    }
}

function updateOrderStatus($db, $commande_id, $status, $payment_status, $txn_id, $payment_amount, $payer_email) {
    try {
        $db->beginTransaction();
        
        // 1. Mettre à jour la commande
        $stmt = $db->prepare("
            UPDATE commandes 
            SET statut = :status,
                statut_paiement = :payment_status,
                reference_paiement = :txn_id,
                date_paiement = NOW()
            WHERE id_commande = :commande_id
            AND statut_paiement IN ('en_attente', 'echec')
        ");
        
        $stmt->execute([
            ':status' => $status,
            ':payment_status' => $payment_status,
            ':txn_id' => $txn_id,
            ':commande_id' => $commande_id
        ]);
        
        if ($stmt->rowCount() > 0) {
            // 2. Loguer l'événement
            $stmt_log = $db->prepare("
                INSERT INTO logs 
                (type_log, niveau, message, utilisateur_id, ip_address, metadata) 
                VALUES 
                ('paiement', 'info', :message, NULL, :ip, :metadata)
            ");
            
            $log_message = "Paiement PayPal confirmé pour commande #$commande_id";
            $metadata = json_encode([
                'commande_id' => $commande_id,
                'txn_id' => $txn_id,
                'montant' => $payment_amount,
                'payer_email' => $payer_email,
                'status' => $payment_status
            ]);
            
            $stmt_log->execute([
                ':message' => $log_message,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                ':metadata' => $metadata
            ]);
            
            // 3. Mettre à jour le stock
            if ($payment_status == 'paye') {
                $stmt_items = $db->prepare("
                    SELECT id_produit, quantite 
                    FROM commande_items 
                    WHERE id_commande = :commande_id
                ");
                $stmt_items->execute([':commande_id' => $commande_id]);
                $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($items as $item) {
                    $stmt_stock = $db->prepare("
                        UPDATE produits 
                        SET quantite_stock = quantite_stock - :quantite,
                            ventes = ventes + :quantite
                        WHERE id_produit = :id_produit
                        AND quantite_stock >= :quantite
                    ");
                    $stmt_stock->execute([
                        ':quantite' => $item['quantite'],
                        ':id_produit' => $item['id_produit']
                    ]);
                }
            }
            
            $db->commit();
            return true;
        }
        
        $db->rollBack();
        return false;
        
    } catch (Exception $e) {
        $db->rollBack();
        logMessage("DB ERROR: " . $e->getMessage());
        return false;
    }
}

// ============================================
// TRAITEMENT PRINCIPAL
// ============================================

logMessage("=== Début du traitement IPN ===");

// 1. Valider la notification PayPal
$ipn_data = validateIPN();

if (!$ipn_data) {
    logMessage("Validation IPN échouée");
    http_response_code(400);
    exit;
}

logMessage("IPN validé avec succès");

// 2. Extraire les données importantes
$receiver_email = $ipn_data['receiver_email'] ?? '';
$payer_email = $ipn_data['payer_email'] ?? '';
$txn_id = $ipn_data['txn_id'] ?? '';
$payment_status = $ipn_data['payment_status'] ?? '';
$mc_gross = $ipn_data['mc_gross'] ?? 0;
$mc_currency = $ipn_data['mc_currency'] ?? '';
$custom = $ipn_data['custom'] ?? ''; // Contient l'ID de commande
$item_number = $ipn_data['item_number'] ?? ''; // Alternative pour l'ID de commande

// Déterminer l'ID de commande
$commande_id = !empty($custom) ? intval($custom) : intval($item_number);

logMessage("Données reçues - Commande: $commande_id, Statut: $payment_status, Montant: $mc_gross $mc_currency");

// 3. Validation des données
if ($commande_id <= 0) {
    logMessage("ERROR: ID de commande invalide");
    sendEmailNotification("IPN ERROR - ID commande invalide", print_r($ipn_data, true));
    exit;
}

if ($mc_currency != 'EUR') {
    logMessage("ERROR: Devise incorrecte ($mc_currency)");
    sendEmailNotification("IPN ERROR - Devise incorrecte", print_r($ipn_data, true), $commande_id);
    exit;
}

// 4. Connexion à la base de données
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
} catch (PDOException $e) {
    logMessage("DB CONNECTION ERROR: " . $e->getMessage());
    sendEmailNotification("IPN ERROR - Connexion DB", $e->getMessage(), $commande_id);
    exit;
}

// 5. Traiter selon le statut de paiement
switch ($payment_status) {
    case 'Completed':
        // Paiement complet
        $order_status = 'confirmee';
        $payment_status_db = 'paye';
        logMessage("Paiement complet pour commande #$commande_id");
        break;
        
    case 'Pending':
        // Paiement en attente
        $order_status = 'en_attente';
        $payment_status_db = 'en_attente';
        logMessage("Paiement en attente pour commande #$commande_id");
        break;
        
    case 'Failed':
    case 'Denied':
    case 'Expired':
    case 'Voided':
        // Paiement échoué
        $order_status = 'annulee';
        $payment_status_db = 'echec';
        logMessage("Paiement échoué pour commande #$commande_id");
        break;
        
    case 'Refunded':
    case 'Reversed':
        // Remboursement
        $order_status = 'remboursee';
        $payment_status_db = 'rembourse';
        logMessage("Remboursement pour commande #$commande_id");
        break;
        
    default:
        logMessage("Statut inconnu: $payment_status");
        $order_status = 'en_attente';
        $payment_status_db = 'en_attente';
}

// 6. Mettre à jour la commande
$success = updateOrderStatus($db, $commande_id, $order_status, $payment_status_db, $txn_id, $mc_gross, $payer_email);

if ($success) {
    logMessage("Commande #$commande_id mise à jour avec succès: $payment_status_db");
    
    // Envoyer un email de notification pour les paiements importants
    if ($payment_status == 'Completed' && $mc_gross > 100) {
        sendEmailNotification("Paiement reçu - Commande #$commande_id", 
            "Montant: $mc_gross EUR\n" .
            "Email payeur: $payer_email\n" .
            "Transaction: $txn_id",
            $commande_id
        );
    }
} else {
    logMessage("Échec mise à jour commande #$commande_id");
    sendEmailNotification("IPN WARNING - Échec mise à jour", print_r($ipn_data, true), $commande_id);
}

// 7. Journaliser la transaction
$stmt_log = $db->prepare("
    INSERT INTO logs 
    (type_log, niveau, message, utilisateur_id, ip_address, user_agent, url, metadata, date_log) 
    VALUES 
    ('paiement', 'info', :message, NULL, :ip, :ua, :url, :metadata, NOW())
");

$metadata = json_encode([
    'ipn_data' => $ipn_data,
    'commande_id' => $commande_id,
    'payment_status' => $payment_status,
    'txn_id' => $txn_id,
    'amount' => $mc_gross
]);

$stmt_log->execute([
    ':message' => "Notification IPN reçue - $payment_status",
    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'IPN',
    ':url' => $_SERVER['REQUEST_URI'] ?? '',
    ':metadata' => $metadata
]);

logMessage("=== Fin du traitement IPN ===");

// 8. Répondre à PayPal
http_response_code(200);
echo "OK";
?>
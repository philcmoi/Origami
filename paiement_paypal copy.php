<?php
// paiement_paypal.php - Script de traitement PayPal CORRIGÉ

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // À 0 en production
ini_set('log_errors', 1);

// ============================================
// 1. VALIDATION DU PARAMÈTRE
// ============================================
if (!isset($_GET['commande']) || empty($_GET['commande'])) {
    header('HTTP/1.0 400 Bad Request');
    die('Erreur : Numéro de commande manquant.');
}

$commande_id = intval($_GET['commande']);

if ($commande_id <= 0) {
    header('HTTP/1.0 400 Bad Request');
    die('Erreur : Numéro de commande invalide.');
}

// ============================================
// 2. CONNEXION À LA BASE DE DONNÉES
// ============================================
$servername = "localhost";
$username = "Philippe";
$password = "l@99339R";
$dbname = "heureducadeau";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ============================================
    // 3. RÉCUPÉRATION DES INFOS DE LA COMMANDE
    // ============================================
    $stmt = $conn->prepare("
        SELECT * FROM commandes 
        WHERE id_commande = :commande_id 
        AND statut_paiement = 'en_attente'
    ");
    $stmt->bindParam(':commande_id', $commande_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        header('HTTP/1.0 404 Not Found');
        die('Erreur : Commande #' . $commande_id . ' introuvable ou déjà traitée.');
    }
    
    // ============================================
    // 4. CONFIGURATION PAYPAL
    // ============================================
    // MODE SANDBOX (test) - Changez en 'live' pour la production
    $paypal_mode = 'sandbox';
    
    if ($paypal_mode == 'sandbox') {
        $paypal_url = "https://www.sandbox.paypal.com/cgi-bin/webscr";
        // Compte de test sandbox
        $paypal_email = "sb-vyvj047419601@business.example.com";
    } else {
        $paypal_url = "https://www.paypal.com/cgi-bin/webscr";
        // VOTRE VRAI EMAIL PAYPAL BUSINESS
        $paypal_email = "vendeur@heureducadeau.fr";
    }
    
    // ============================================
    // 5. PRÉPARATION DES DONNÉES PAYPAL
    // ============================================
    $item_name = "Commande #" . $commande['numero_commande'];
    $item_amount = floatval($commande['total_ttc']);
    $currency_code = "EUR";
    
    // URLs de retour
    $base_url = "https://" . $_SERVER['HTTP_HOST']; // Adaptez à votre domaine
    $return_url = $base_url . "/paiement_reussi.php?commande=" . $commande_id;
    $cancel_url = $base_url . "/paiement_annule.php?commande=" . $commande_id;
    $notify_url = $base_url . "/ipn_paypal.php";
    
} catch(PDOException $e) {
    error_log("Erreur base de données paiement_paypal.php: " . $e->getMessage());
    header('HTTP/1.0 500 Internal Server Error');
    die('Une erreur technique est survenue. Veuillez réessayer ultérieurement.');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paiement PayPal - Commande <?php echo htmlspecialchars($commande['numero_commande']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            text-align: center;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        h2 {
            color: #003087;
        }
        .loader {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #003087;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .order-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: left;
        }
        .btn-paypal {
            background: #003087;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-paypal:hover {
            background: #001f5c;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="loader"></div>
        
        <h2>Redirection vers PayPal...</h2>
        <p>Veuillez patienter pendant que nous vous redirigeons vers le système de paiement sécurisé PayPal.</p>
        
        <div class="order-info">
            <h3>Résumé de votre commande</h3>
            <p><strong>Numéro :</strong> <?php echo htmlspecialchars($commande['numero_commande']); ?></p>
            <p><strong>Montant :</strong> <?php echo number_format($item_amount, 2, ',', ' '); ?> €</p>
            <p><strong>Date :</strong> <?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?></p>
        </div>
        
        <!-- Formulaire PayPal -->
        <form action="<?php echo $paypal_url; ?>" method="post" id="paypal_form" target="_top">
            <!-- Paramètres obligatoires -->
            <input type="hidden" name="cmd" value="_xclick">
            <input type="hidden" name="business" value="<?php echo $paypal_email; ?>">
            <input type="hidden" name="item_name" value="<?php echo htmlspecialchars($item_name); ?>">
            <input type="hidden" name="item_number" value="<?php echo $commande_id; ?>">
            <input type="hidden" name="amount" value="<?php echo number_format($item_amount, 2, '.', ''); ?>">
            <input type="hidden" name="currency_code" value="<?php echo $currency_code; ?>">
            <input type="hidden" name="return" value="<?php echo $return_url; ?>">
            <input type="hidden" name="cancel_return" value="<?php echo $cancel_url; ?>">
            <input type="hidden" name="notify_url" value="<?php echo $notify_url; ?>">
            <input type="hidden" name="custom" value="<?php echo $commande_id; ?>">
            
            <!-- Options supplémentaires -->
            <input type="hidden" name="no_shipping" value="1">
            <input type="hidden" name="no_note" value="1">
            <input type="hidden" name="lc" value="FR">
            <input type="hidden" name="bn" value="PP-BuyNowBF">
            
            <p>Si la redirection ne démarre pas automatiquement :</p>
            <button type="submit" class="btn-paypal">
                <i class="fab fa-paypal"></i> Payer avec PayPal
            </button>
        </form>
        
        <p style="margin-top: 30px; color: #666; font-size: 12px;">
            <i class="fas fa-lock"></i> Transaction sécurisée SSL 256-bit
        </p>
    </div>
    
    <script>
        // Soumission automatique après 2 secondes
        setTimeout(function() {
            document.getElementById('paypal_form').submit();
        }, 2000);
    </script>
</body>
</html>
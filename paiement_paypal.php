<?php
// paiement_paypal.php - Script de redirection PayPal
session_start();
require_once 'config.php';

$idCommande = $_GET['commande'] ?? null;

if (!$idCommande) {
    header('Location: index.html');
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer la commande
    $stmt = $pdo->prepare("
        SELECT * FROM commandes 
        WHERE id_commande = ? AND statut_paiement = 'en_attente'
    ");
    $stmt->execute([$idCommande]);
    $commande = $stmt->fetch();
    
    if (!$commande) {
        die('Commande introuvable ou déjà traitée');
    }
    
    // Configuration PayPal
    $paypal_mode = PAYPAL_ENVIRONMENT; // 'sandbox' ou 'live'
    
    if ($paypal_mode == 'sandbox') {
        $paypal_url = "https://www.sandbox.paypal.com/cgi-bin/webscr";
        $paypal_email = "sb-vyvj047419601@business.example.com"; // Email sandbox
    } else {
        $paypal_url = "https://www.paypal.com/cgi-bin/webscr";
        $paypal_email = "vendeur@heureducadeau.fr"; // Votre email PayPal
    }
    
    $item_name = "Commande #" . $commande['numero_commande'];
    $item_amount = floatval($commande['total_ttc']);
    $currency_code = "EUR";
    
    // URLs de retour
    $base_url = SITE_URL;
    $return_url = $base_url . "/paiement_reussi.php?commande=" . $idCommande;
    $cancel_url = $base_url . "/paiement_annule.php?commande=" . $idCommande;
    $notify_url = $base_url . "/ipn_paypal.php";
    
} catch (PDOException $e) {
    die('Erreur base de données: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Redirection PayPal - Commande <?= htmlspecialchars($commande['numero_commande']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; text-align: center; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .loader { border: 5px solid #f3f3f3; border-top: 5px solid #003087; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .order-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: left; }
        .btn-paypal { background: #003087; color: white; border: none; padding: 12px 30px; font-size: 16px; border-radius: 5px; cursor: pointer; margin-top: 20px; }
        .btn-paypal:hover { background: #001f5c; }
    </style>
</head>
<body>
    <div class="container">
        <div class="loader"></div>
        <h2>Redirection vers PayPal...</h2>
        
        <div class="order-info">
            <h3>Résumé de votre commande</h3>
            <p><strong>Numéro :</strong> <?= htmlspecialchars($commande['numero_commande']) ?></p>
            <p><strong>Montant :</strong> <?= number_format($item_amount, 2, ',', ' ') ?> €</p>
            <p><strong>Date :</strong> <?= date('d/m/Y à H:i', strtotime($commande['date_commande'])) ?></p>
        </div>
        
        <form action="<?= $paypal_url ?>" method="post" id="paypal_form" target="_top">
            <input type="hidden" name="cmd" value="_xclick">
            <input type="hidden" name="business" value="<?= $paypal_email ?>">
            <input type="hidden" name="item_name" value="<?= htmlspecialchars($item_name) ?>">
            <input type="hidden" name="item_number" value="<?= $idCommande ?>">
            <input type="hidden" name="amount" value="<?= number_format($item_amount, 2, '.', '') ?>">
            <input type="hidden" name="currency_code" value="<?= $currency_code ?>">
            <input type="hidden" name="return" value="<?= $return_url ?>">
            <input type="hidden" name="cancel_return" value="<?= $cancel_url ?>">
            <input type="hidden" name="notify_url" value="<?= $notify_url ?>">
            <input type="hidden" name="custom" value="<?= $idCommande ?>">
            <input type="hidden" name="no_shipping" value="1">
            <input type="hidden" name="no_note" value="1">
            <input type="hidden" name="lc" value="FR">
            
            <p>Si la redirection ne démarre pas automatiquement :</p>
            <button type="submit" class="btn-paypal"><i class="fab fa-paypal"></i> Payer avec PayPal</button>
        </form>
    </div>
    
    <script>
        setTimeout(function() {
            document.getElementById('paypal_form').submit();
        }, 2000);
    </script>
</body>
</html>
<?php
// api/paiement_reussi.php - Page de retour après paiement réussi
session_start();
require_once __DIR__ . '/../config_paypal.php';

$pdo = getPDOConnection();

// Récupérer les paramètres PayPal
$orderId = $_GET['token'] ?? $_GET['paymentId'] ?? null;
$payerId = $_GET['PayerID'] ?? null;

if ($orderId && $payerId) {
    // Capturer le paiement
    $result = capturePayPalPayment($orderId);
    
    if ($result['success']) {
        // Récupérer les données de la session
        $orderData = $_SESSION['paypal_order']['order_data'] ?? [];
        $customerId = $_SESSION['id_client'] ?? null;
        
        // Enregistrer la commande
        $orderResult = saveOrder(
            $orderData,
            'paypal',
            $result['capture_id'] ?? $orderId,
            $customerId,
            'paye'
        );
        
        // Rediriger vers la page de confirmation
        if ($orderResult) {
            unset($_SESSION['paypal_order']);
            header('Location: /paiement_confirmation.php?order_id=' . $orderResult['id_commande']);
            exit();
        }
    }
}

// Si erreur, rediriger vers la page d'erreur
header('Location: /paiement_erreur.php');
exit();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traitement du paiement...</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #f8f9fa;
        }
        
        .container {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <h2>Traitement du paiement en cours...</h2>
        <p>Veuillez patienter, vous allez être redirigé.</p>
    </div>
</body>
</html>
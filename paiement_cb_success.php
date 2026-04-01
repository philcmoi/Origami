<?php
session_start();
require_once 'config.php';

$id_commande = $_GET['commande'] ?? 0;
$reference = $_GET['ref'] ?? '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($id_commande > 0) {
        $stmt = $pdo->prepare("
            SELECT c.*, cl.email, cl.prenom, cl.nom 
            FROM commandes c 
            JOIN clients cl ON c.id_client = cl.id_client 
            WHERE c.id_commande = ?
        ");
        $stmt->execute([$id_commande]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $commande = null;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Réussi - HEURE DU CADEAU</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            text-align: center;
        }
        .success-container {
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            max-width: 600px;
        }
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        h1 {
            color: #28a745;
            margin-bottom: 20px;
        }
        .order-details {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin: 25px 0;
            text-align: left;
            border-left: 4px solid #28a745;
        }
        .order-details p {
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            background: #764ba2;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            background: #667eea;
        }
        .email-notice {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            color: #155724;
        }
        .security-badge {
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✅</div>
        <h1>Paiement Confirmé !</h1>
        
        <?php if ($commande): ?>
        <div class="order-details">
            <p><strong>📋 Commande :</strong> #<?= htmlspecialchars($commande['numero_commande']) ?></p>
            <p><strong>💰 Montant :</strong> <?= number_format($commande['total_ttc'], 2, ',', ' ') ?> €</p>
            <p><strong>💳 Mode de paiement :</strong> Carte Bancaire (PayPal)</p>
            <?php if ($reference): ?>
            <p><strong>🔗 Référence :</strong> <?= htmlspecialchars($reference) ?></p>
            <?php endif; ?>
        </div>
        
        <div class="email-notice">
            <p><strong>📧 Email envoyé !</strong></p>
            <p>Un email de confirmation a été envoyé à <strong><?= htmlspecialchars($commande['email']) ?></strong>.</p>
        </div>
        <?php endif; ?>
        
        <p>Votre commande est en cours de préparation. Vous recevrez un email de suivi dès son expédition.</p>
        
        <div>
            <a href="index.php" class="btn">🏠 Retour à l'accueil</a>
            <a href="compte.php?page=commandes" class="btn">📋 Mes commandes</a>
        </div>
        
        <div class="security-badge">
            <p>🔒 Paiement sécurisé par PayPal | HEURE DU CADEAU © 2024</p>
        </div>
    </div>
</body>
</html>
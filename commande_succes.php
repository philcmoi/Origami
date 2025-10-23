<?php
// commande_succes.php
session_start();

// Vérifier si une commande a bien été confirmée
if (!isset($_SESSION['commande_confirmee']) || !$_SESSION['commande_confirmee']) {
    header('Location: boutique.php');
    exit;
}

// Inclure les fonctions panier
require_once 'fonctions_panier.php';

// Récupérer l'email vérifié
$email = $_SESSION['email_verifie'] ?? '';

// Nettoyer la session pour la prochaine commande
unset($_SESSION['commande_confirmee']);
unset($_SESSION['email_confirme']);
// Note: On ne vide pas le panier tout de suite, on laisse l'utilisateur voir sa commande
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande Validée - Origami Zen</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Arial', sans-serif; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header { 
            background: linear-gradient(135deg, #d40000, #ff6b6b);
            color: white; 
            padding: 40px; 
            text-align: center; 
        }
        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .content { padding: 40px; }
        .section { 
            margin-bottom: 30px; 
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .btn { 
            background: linear-gradient(135deg, #d40000, #ff6b6b); 
            color: white; 
            padding: 15px 30px; 
            text-decoration: none; 
            border-radius: 8px; 
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin: 10px;
            display: inline-block;
            transition: transform 0.2s;
        }
        .btn:hover { 
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #a0a0a0);
        }
        .table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0; 
        }
        .table th, .table td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        .table th { 
            background: #e9ecef; 
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">🎉</div>
            <h1>Commande Validée !</h1>
            <p>Merci pour votre achat chez Origami Zen</p>
        </div>
        
        <div class="content">
            <div class="section">
                <h2>Récapitulatif de votre commande</h2>
                <p><strong>Email de confirmation :</strong> <?php echo htmlspecialchars($email); ?></p>
                <p><strong>Numéro de commande :</strong> #<?php echo strtoupper(uniqid()); ?></p>
                <p><strong>Date :</strong> <?php echo date('d/m/Y à H:i'); ?></p>
            </div>
            
            <?php if (!panierEstVide()): ?>
            <div class="section">
                <h3>Détails de la commande</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Quantité</th>
                            <th>Prix unitaire</th>
                            <th>Sous-total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo getDetailsPanierPourEmail(); ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="3" style="text-align: right;">Total :</td>
                            <td><?php echo number_format(calculerTotalPanier(), 2); ?> €</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="section">
                <h3>Prochaines étapes</h3>
                <p>✅ <strong>Email de confirmation</strong> envoyé à <?php echo htmlspecialchars($email); ?></p>
                <p>📦 <strong>Préparation de commande</strong> sous 24-48 heures</p>
                <p>🚚 <strong>Livraison</strong> selon le mode choisi</p>
                <p>📧 <strong>Email de suivi</strong> avec numéro de tracking</p>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="boutique.php" class="btn">Continuer mes achats</a>
                <a href="index.php" class="btn btn-secondary">Retour à l'accueil</a>
            </div>
        </div>
    </div>
    
    <script>
        // Vider le panier après affichage de la confirmation
        setTimeout(() => {
            // Optionnel : vider le panier via AJAX
            fetch('api/vider_panier.php', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    console.log('Panier vidé après commande');
                });
        }, 3000);
    </script>
</body>
</html>
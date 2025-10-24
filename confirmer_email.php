<?php
session_start();

// Inclure les fonctions panier
require_once 'fonctions_panier.php';

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';

if (!$email || !$token) {
    afficherErreur("Lien de confirmation invalide");
    exit;
}

// Vérification complète du token
$session_token = $_SESSION['email_token'] ?? '';
$session_email = $_SESSION['email_verifie'] ?? '';
$token_timestamp = $_SESSION['token_timestamp'] ?? 0;

// Vérifier l'expiration (1 heure)
$current_time = time();
$token_expired = ($current_time - $token_timestamp) > 3600;

function afficherSucces($email) {
    echo "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Commande Confirmée - Origami Zen</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: 'Arial', sans-serif; 
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container { 
                background: white; 
                padding: 40px; 
                border-radius: 15px; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                max-width: 500px;
                width: 100%;
                text-align: center;
            }
            .success-icon {
                font-size: 60px;
                color: #28a745;
                margin-bottom: 20px;
            }
            h1 { 
                color: #28a745; 
                margin-bottom: 20px;
                font-size: 28px;
            }
            p { 
                margin-bottom: 15px; 
                line-height: 1.6;
                color: #555;
            }
            .email { 
                color: #d40000; 
                font-weight: bold;
                font-size: 18px;
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
                margin-top: 25px;
                transition: transform 0.2s, box-shadow 0.2s;
                display: inline-block;
            }
            .btn:hover { 
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(212, 0, 0, 0.3);
            }
            .info-box {
                background: #f8f9fa;
                border-left: 4px solid #d40000;
                padding: 15px;
                margin: 20px 0;
                text-align: left;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='success-icon'>✓</div>
            <h1>Commande Confirmée !</h1>
            <p>Votre adresse email <span class='email'>$email</span> a été vérifiée avec succès.</p>
            
            <div class='info-box'>
                <p><strong>Votre commande est maintenant confirmée.</strong></p>
                <p>Vous recevrez un email de suivi avec les détails de livraison sous peu.</p>
            </div>
            
            <p>Merci pour votre confiance !</p>
            <p>L'équipe Origami Zen</p>
            
            <button class='btn' onclick='fermerEtRediriger()'>Retour au site</button>
        </div>

        <script>
            function fermerEtRediriger() {
                // Si c'est une fenêtre popup, la fermer
                if (window.opener && !window.opener.closed) {
                    window.opener.location.href = 'commande_succes.php';
                    window.close();
                } else {
                    // Sinon rediriger normalement
                    window.location.href = 'commande_succes.php';
                }
            }
            
            // Redirection automatique après 5 secondes
            setTimeout(() => {
                fermerEtRediriger();
            }, 5000);
        </script>
    </body>
    </html>
    ";
}

function afficherErreur($message) {
    echo "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Erreur de Confirmation - Origami Zen</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: 'Arial', sans-serif; 
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container { 
                background: white; 
                padding: 40px; 
                border-radius: 15px; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                max-width: 500px;
                width: 100%;
                text-align: center;
            }
            .error-icon {
                font-size: 60px;
                color: #dc3545;
                margin-bottom: 20px;
            }
            h1 { 
                color: #dc3545; 
                margin-bottom: 20px;
                font-size: 28px;
            }
            p { 
                margin-bottom: 15px; 
                line-height: 1.6;
                color: #555;
            }
            .btn { 
                background: linear-gradient(135deg, #6c757d, #a0a0a0); 
                color: white; 
                padding: 15px 30px; 
                text-decoration: none; 
                border-radius: 8px; 
                border: none;
                cursor: pointer;
                font-size: 16px;
                font-weight: bold;
                margin-top: 25px;
                transition: transform 0.2s;
                display: inline-block;
            }
            .btn:hover { 
                transform: translateY(-2px);
            }
            .btn-primary {
                background: linear-gradient(135deg, #d40000, #ff6b6b);
                margin-left: 10px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='error-icon'>⚠️</div>
            <h1>Erreur de Confirmation</h1>
            <p>$message</p>
            
            <div>
                <button class='btn' onclick='window.close()'>Fermer</button>
                <button class='btn btn-primary' onclick='window.location.href=\"boutique.php\"'>Retour à la boutique</button>
            </div>
        </div>
    </body>
    </html>
    ";
}

// VÉRIFICATION PRINCIPALE
if (!$token_expired && 
    $session_token === $token && 
    $session_email === $email &&
    isset($_SESSION['commande_en_attente'])) {
    
    // Marquer la commande comme confirmée
    $_SESSION['email_confirme'] = true;
    $_SESSION['commande_confirmee'] = true;
    $_SESSION['email_verifie'] = $email;
    
    // Nettoyer les données temporaires
    unset($_SESSION['email_token']);
    unset($_SESSION['token_timestamp']);
    unset($_SESSION['commande_en_attente']);
    
    afficherSucces($email);
    
} else {
    if ($token_expired) {
        afficherErreur("Le lien de confirmation a expiré. Veuillez demander un nouveau lien.");
    } else {
        afficherErreur("Lien de confirmation invalide. Veuillez demander un nouveau lien.");
    }
}
?>
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Inclure les fonctions panier
require_once 'fonctions_panier.php';

// Inclure PHPMailer
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$email = $input['email'] ?? '';
$type = $input['type'] ?? '';

function sendResponse($status, $data = null, $error = null) {
    http_response_code($status);
    echo json_encode([
        'status' => $status,
        'data' => $data,
        'error' => $error
    ]);
    exit;
}

if ($action === 'envoyer_lien_confirmation') {
    // VÉRIFICATION CRITIQUE : PANIER NON VIDE
    if (panierEstVide()) {
        sendResponse(400, null, "Votre panier est vide. Ajoutez des articles avant de valider votre commande.");
    }
    
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(400, null, "Email invalide");
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // Configuration SMTP pour WAMP
        $mail->isSMTP();
        $mail->Host = 'localhost';
        $mail->SMTPAuth = false;
        $mail->Port = 25;
        
        // Options de sécurité et timeout
        $mail->Timeout = 30;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Encodage
        $mail->CharSet = 'UTF-8';
        
        // Expéditeur et destinataire
        $mail->setFrom('contact@origamizen.fr', 'Origami Zen');
        $mail->addAddress($email);
        
        // Contenu de l'email
        $mail->isHTML(true);
        $mail->Subject = 'Confirmation de votre commande - Origami Zen';
        
        // Générer token sécurisé
        $token = bin2hex(random_bytes(32));
        $lienConfirmation = 'http://localhost/origamizen/confirmer_email.php?email=' . urlencode($email) . '&token=' . $token;
        
        // Détails du panier pour l'email
        $detailsPanier = getDetailsPanierPourEmail();
        $nombreArticles = getNombreArticlesPanier();
        $totalCommande = calculerTotalPanier();
        
        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #d40000; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .button { background: #d40000; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
                    .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    .table th, .table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                    .table th { background: #f0f0f0; }
                    .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Origami Zen</h1>
                        <h2>Confirmation de votre commande</h2>
                    </div>
                    
                    <div class='content'>
                        <p>Bonjour,</p>
                        <p>Merci pour votre commande ! Voici le récapitulatif :</p>
                        
                        <table class='table'>
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Prix unitaire</th>
                                    <th>Sous-total</th>
                                </tr>
                            </thead>
                            <tbody>
                                $detailsPanier
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan='3' style='text-align: right; font-weight: bold;'>Total :</td>
                                    <td style='font-weight: bold;'>" . number_format($totalCommande, 2) . " €</td>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <p>Pour finaliser votre commande, veuillez cliquer sur le bouton ci-dessous :</p>
                        
                        <p style='text-align: center;'>
                            <a href='{$lienConfirmation}' class='button'>Confirmer ma commande</a>
                        </p>
                        
                        <p>Ou copiez ce lien dans votre navigateur :<br>
                        <a href='{$lienConfirmation}'>{$lienConfirmation}</a></p>
                        
                        <p><strong>⚠️ Important : Ce lien expirera dans 1 heure.</strong></p>
                        
                        <p>Merci pour votre confiance !</p>
                        <p>L'équipe Origami Zen</p>
                    </div>
                    
                    <div class='footer'>
                        <p>© " . date('Y') . " Origami Zen. Tous droits réservés.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Confirmez votre commande de $nombreArticles article(s) pour un total de " . number_format($totalCommande, 2) . " € en visitant: $lienConfirmation";
        
        if ($mail->send()) {
            // Stocker les informations de confirmation
            $_SESSION['email_token'] = $token;
            $_SESSION['email_verifie'] = $email;
            $_SESSION['token_timestamp'] = time();
            $_SESSION['commande_en_attente'] = true;
            
            sendResponse(200, [
                "message" => "Email de confirmation envoyé avec succès",
                "articles_count" => $nombreArticles,
                "total" => $totalCommande
            ]);
        } else {
            sendResponse(500, null, "Erreur lors de l'envoi de l'email: " . $mail->ErrorInfo);
        }
        
    } catch (Exception $e) {
        sendResponse(500, null, "Erreur PHPMailer: " . $e->getMessage());
    }
} else {
    sendResponse(400, null, "Action non reconnue");
}
?>
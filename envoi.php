<?php
// envoi.php - Envoi de la facture par email
error_log("📧 envoi.php - Envoi email facture");

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'config.php';
require_once 'smtp_config.php';

// Accepter les requêtes POST pour l'appel automatique
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $idCommande = $input['id_commande'] ?? null;
    
    if ($idCommande) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Récupérer les infos de la commande
            $stmt = $pdo->prepare("
                SELECT 
                    c.idCommande,
                    c.montantTotal,
                    cl.email as client_email,
                    cl.prenom as client_prenom,
                    cl.nom as client_nom
                FROM Commande c
                JOIN Client cl ON c.idClient = cl.idClient
                WHERE c.idCommande = ?
            ");
            $stmt->execute([$idCommande]);
            $commande = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$commande) {
                throw new Exception("Commande #" . $idCommande . " non trouvée");
            }
            
            // Envoyer l'email
            $mail = new PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            
            $mail->setFrom(SMTP_FROM_EMAIL, 'Youki & Co');
            $mail->addAddress($commande['client_email'], $commande['client_prenom'] . ' ' . $commande['client_nom']);
            $mail->addReplyTo(SMTP_FROM_EMAIL, 'Youki & Co');
            
            $mail->isHTML(true);
            $mail->Subject = 'Votre facture Youki & Co - Commande #' . $idCommande;
            
            // Email sobre et professionnel
            $messageHTML = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
                    .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 4px; overflow: hidden; }
                    .header { background: #333; padding: 30px; text-align: center; }
                    .header h1 { margin: 0; color: white; font-weight: 300; font-size: 28px; letter-spacing: 2px; }
                    .content { padding: 40px; }
                    h2 { color: #333; font-weight: 400; margin-top: 0; }
                    p { color: #666; line-height: 1.6; margin-bottom: 20px; }
                    .info-box { background: #fafafa; padding: 25px; margin: 25px 0; border: 1px solid #f0f0f0; }
                    .info-box p { margin: 10px 0; }
                    .info-box strong { color: #333; }
                    .btn { display: inline-block; background: #333; color: white; text-decoration: none; padding: 12px 30px; border-radius: 4px; margin: 20px 0; }
                    .btn:hover { background: #444; }
                    .footer { background: #fafafa; padding: 20px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #f0f0f0; }
                    .mention { background: #f9f9f9; padding: 15px; margin: 25px 0; color: #666; font-size: 13px; text-align: center; border: 1px solid #f0f0f0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>YOUKI & CO</h1>
                    </div>
                    
                    <div class='content'>
                        <h2>Bonjour " . htmlspecialchars($commande['client_prenom']) . ",</h2>
                        
                        <p>Votre facture pour la commande <strong>#" . $idCommande . "</strong> est disponible.</p>
                        
                        <div class='info-box'>
                            <p><strong>Commande :</strong> #" . $idCommande . "</p>
                            <p><strong>Montant :</strong> " . number_format($commande['montantTotal'], 2, ',', ' ') . " €</p>
                            <p><strong>Date :</strong> " . date('d/m/Y') . "</p>
                        </div>
                        
                        <p>Pour consulter votre facture, cliquez sur le lien ci-dessous :</p>
                        
                        <div style='text-align: center;'>
                            <a href='http://217.182.198.20/facture.php?id=" . $idCommande . "' class='btn'>Consulter ma facture</a>
                        </div>
                        
                        <div class='mention'>
                            Exonération de TVA - Art. 293 B du CGI
                        </div>
                        
                        <p>Nous vous remercions pour votre confiance.</p>
                        
                        <p>Cordialement,<br>L'équipe Youki & Co</p>
                    </div>
                    
                    <div class='footer'>
                        Youki & Co - Créations artisanales japonaises<br>
                        contact@youkiandco.fr
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->Body = $messageHTML;
            $mail->AltBody = "Bonjour " . $commande['client_prenom'] . ",\n\nVotre facture pour la commande #" . $idCommande . " est disponible.\nMontant: " . number_format($commande['montantTotal'], 2, ',', ' ') . " €\n\nConsultez votre facture: http://217.182.198.20/facture.php?id=" . $idCommande . "\n\nExonération de TVA - Art. 293 B du CGI\n\nCordialement,\nYouki & Co";
            
            if ($mail->send()) {
                error_log("✅ Email envoyé à: " . $commande['client_email']);
                echo json_encode([
                    'status' => 'success',
                    'email' => $commande['client_email'],
                    'message' => 'Email envoyé avec succès'
                ]);
            } else {
                throw new Exception("Erreur envoi email: " . $mail->ErrorInfo);
            }
            
        } catch (Exception $e) {
            error_log("❌ Erreur: " . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// Code pour l'affichage web
$idCommande = $_GET['id'] ?? $_POST['id_commande'] ?? null;

if (!$idCommande) {
    die("❌ ID de commande manquant");
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("
        SELECT 
            c.idCommande,
            c.montantTotal,
            cl.email as client_email,
            cl.prenom as client_prenom,
            cl.nom as client_nom
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        WHERE c.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        throw new Exception("Commande #" . $idCommande . " non trouvée");
    }
    
    $mail = new PHPMailer(true);
    
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->CharSet = 'UTF-8';
    
    $mail->setFrom(SMTP_FROM_EMAIL, 'Youki & Co');
    $mail->addAddress($commande['client_email'], $commande['client_prenom'] . ' ' . $commande['client_nom']);
    $mail->addReplyTo(SMTP_FROM_EMAIL, 'Youki & Co');
    
    $mail->isHTML(true);
    $mail->Subject = 'Votre facture Youki & Co - Commande #' . $idCommande;
    
    // Même template que ci-dessus
    $messageHTML = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 4px; overflow: hidden; }
            .header { background: #333; padding: 30px; text-align: center; }
            .header h1 { margin: 0; color: white; font-weight: 300; font-size: 28px; letter-spacing: 2px; }
            .content { padding: 40px; }
            h2 { color: #333; font-weight: 400; margin-top: 0; }
            p { color: #666; line-height: 1.6; margin-bottom: 20px; }
            .info-box { background: #fafafa; padding: 25px; margin: 25px 0; border: 1px solid #f0f0f0; }
            .info-box p { margin: 10px 0; }
            .info-box strong { color: #333; }
            .btn { display: inline-block; background: #333; color: white; text-decoration: none; padding: 12px 30px; border-radius: 4px; margin: 20px 0; }
            .btn:hover { background: #444; }
            .footer { background: #fafafa; padding: 20px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #f0f0f0; }
            .mention { background: #f9f9f9; padding: 15px; margin: 25px 0; color: #666; font-size: 13px; text-align: center; border: 1px solid #f0f0f0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>YOUKI & CO</h1>
            </div>
            
            <div class='content'>
                <h2>Bonjour " . htmlspecialchars($commande['client_prenom']) . ",</h2>
                
                <p>Votre facture pour la commande <strong>#" . $idCommande . "</strong> est disponible.</p>
                
                <div class='info-box'>
                    <p><strong>Commande :</strong> #" . $idCommande . "</p>
                    <p><strong>Montant :</strong> " . number_format($commande['montantTotal'], 2, ',', ' ') . " €</p>
                    <p><strong>Date :</strong> " . date('d/m/Y') . "</p>
                </div>
                
                <p>Pour consulter votre facture, cliquez sur le lien ci-dessous :</p>
                
                <div style='text-align: center;'>
                    <a href='http://217.182.198.20/facture.php?id=" . $idCommande . "' class='btn'>Consulter ma facture</a>
                </div>
                
                <div class='mention'>
                    Exonération de TVA - Art. 293 B du CGI
                </div>
                
                <p>Nous vous remercions pour votre confiance.</p>
                
                <p>Cordialement,<br>L'équipe Youki & Co</p>
            </div>
            
            <div class='footer'>
                Youki & Co - Créations artisanales japonaises<br>
                contact@youkiandco.fr
            </div>
        </div>
    </body>
    </html>
    ";
    
    $mail->Body = $messageHTML;
    $mail->AltBody = "Bonjour " . $commande['client_prenom'] . ",\n\nVotre facture pour la commande #" . $idCommande . " est disponible.\nMontant: " . number_format($commande['montantTotal'], 2, ',', ' ') . " €\n\nConsultez votre facture: http://217.182.198.20/facture.php?id=" . $idCommande . "\n\nExonération de TVA - Art. 293 B du CGI\n\nCordialement,\nYouki & Co";
    
    if ($mail->send()) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Email envoyé</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .container { background: white; padding: 40px; border-radius: 4px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); max-width: 400px; }
                h1 { color: #333; font-weight: 400; margin-bottom: 20px; }
                p { color: #666; margin-bottom: 30px; }
                .btn { display: inline-block; padding: 10px 20px; margin: 0 5px; background: white; border: 1px solid #ddd; color: #333; text-decoration: none; border-radius: 4px; }
                .btn:hover { background: #f5f5f5; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>Email envoyé</h1>
                <p>La facture a été envoyée à <strong>" . htmlspecialchars($commande['client_email']) . "</strong></p>
                <div>
                    <a href='facture.php?id=" . $idCommande . "' class='btn'>Voir la facture</a>
                    <a href='index.html' class='btn'>Accueil</a>
                </div>
            </div>
        </body>
        </html>";
    } else {
        throw new Exception("Erreur envoi email: " . $mail->ErrorInfo);
    }
    
} catch (Exception $e) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Erreur</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .container { background: white; padding: 40px; border-radius: 4px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); max-width: 400px; }
            h1 { color: #dc3545; font-weight: 400; margin-bottom: 20px; }
            p { color: #666; margin-bottom: 30px; }
            .btn { display: inline-block; padding: 10px 20px; background: white; border: 1px solid #ddd; color: #333; text-decoration: none; border-radius: 4px; }
            .btn:hover { background: #f5f5f5; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>Erreur</h1>
            <p>" . htmlspecialchars($e->getMessage()) . "</p>
            <a href='facture.php?id=" . $idCommande . "' class='btn'>Retour à la facture</a>
        </div>
    </body>
    </html>";
}
?>
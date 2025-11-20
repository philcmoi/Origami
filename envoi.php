<?php
// envoi.php - Envoi de la facture par email
error_log("📧 ENVOI.PHP - Envoi email facture");

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configuration de la base de données
$host = '217.182.198.20';
$dbname = 'origami';
$username = 'root';
$password = 'L099339R';

// Accepter les requêtes POST pour l'appel automatique
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $idCommande = $input['id_commande'] ?? null;
    $fichierFacture = $input['fichier_facture'] ?? null;
    
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
            
            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            
            // Destinataires
            $mail->setFrom('lhpp.philippe@gmail.com', 'Origami Zen');
            $mail->addAddress($commande['client_email'], $commande['client_prenom'] . ' ' . $commande['client_nom']);
            $mail->addReplyTo('lhpp.philippe@gmail.com', 'Origami Zen');
            
            // Sujet et contenu
            $mail->isHTML(true);
            $mail->Subject = 'Votre facture Origami Zen - Commande #' . $idCommande;
            
            $messageHTML = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
                    .header { color: #d40000; text-align: center; margin-bottom: 30px; }
                    .content { line-height: 1.6; }
                    .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; text-align: center; }
                    .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
                    .btn { background: #d40000; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🎉 Facture Disponible !</h1>
                    </div>
                    
                    <div class='content'>
                        <p>Bonjour <strong>" . htmlspecialchars($commande['client_prenom']) . "</strong>,</p>
                        
                        <p>Votre facture pour la commande <strong>#" . $idCommande . "</strong> est maintenant disponible.</p>
                        
                        <div class='info-box'>
                            <h3>📦 Détails de la commande</h3>
                            <p><strong>Numéro de commande :</strong> #" . $idCommande . "</p>
                            <p><strong>Montant :</strong> " . number_format($commande['montantTotal'], 2, ',', ' ') . " € HT</p>
                            <p><strong>Date :</strong> " . date('d/m/Y à H:i') . "</p>
                        </div>
                        
                        <p><strong>📋 Votre facture :</strong></p>
                        <p>Vous pouvez consulter et télécharger votre facture en cliquant sur le lien ci-dessous :</p>
                        
                        <div style='text-align: center; margin: 25px 0;'>
                            <a href='http://217.182.198.20/Origami/facture.php?id=" . $idCommande . "' class='btn'>📄 Voir ma facture</a>
                        </div>
                        
                        <div class='info-box'>
                            <h4>🏢 Mention importante</h4>
                            <p><strong>Exonération de TVA - Art. 293 B du CGI</strong></p>
                            <p>Tous les montants sont indiqués hors taxes conformément à la réglementation en vigueur.</p>
                        </div>
                        
                        <p>Nous vous remercions pour votre confiance !</p>
                    </div>
                    
                    <div class='footer'>
                        <p><strong>Youki and Co - Créations artisanales japonaises</strong></p>
                        <!--<p>📧 contact@origamizen.fr | 📞 +33 1 23 45 67 89</p>-->
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->Body = $messageHTML;
            $mail->AltBody = "Bonjour " . $commande['client_prenom'] . ",\n\nVotre facture pour la commande #" . $idCommande . " est disponible.\nMontant: " . number_format($commande['montantTotal'], 2, ',', ' ') . " € HT\n\nConsultez votre facture: http://217.182.198.20/Origami/facture.php?id=" . $idCommande . "\n\nExonération de TVA - Art. 293 B du CGI\n\nMerci pour votre confiance!\n\nOrigami Zen";
            
            if ($mail->send()) {
                error_log("✅ Email envoyé avec succès à: " . $commande['client_email']);
                echo json_encode([
                    'status' => 'success',
                    'email' => $commande['client_email'],
                    'message' => 'Email envoyé avec succès'
                ]);
            } else {
                throw new Exception("Erreur envoi email: " . $mail->ErrorInfo);
            }
            
        } catch (Exception $e) {
            error_log("❌ Erreur envoi email API: " . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// Code original pour l'affichage web reste inchangé
$idCommande = $_GET['id'] ?? $_POST['id_commande'] ?? null;

if (!$idCommande) {
    die("❌ ID de commande manquant");
}

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
    
    // Configuration SMTP
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->CharSet = 'UTF-8';
    
    // Destinataires
    $mail->setFrom('lhpp.philippe@gmail.com', 'Origami Zen');
    $mail->addAddress($commande['client_email'], $commande['client_prenom'] . ' ' . $commande['client_nom']);
    $mail->addReplyTo('lhpp.philippe@gmail.com', 'Origami Zen');
    
    // Sujet et contenu
    $mail->isHTML(true);
    $mail->Subject = 'Votre facture Origami Zen - Commande #' . $idCommande;
    
    $messageHTML = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
            .header { color: #d40000; text-align: center; margin-bottom: 30px; }
            .content { line-height: 1.6; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; text-align: center; }
            .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .btn { background: #d40000; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 Facture Disponible !</h1>
            </div>
            
            <div class='content'>
                <p>Bonjour <strong>" . htmlspecialchars($commande['client_prenom']) . "</strong>,</p>
                
                <p>Votre facture pour la commande <strong>#" . $idCommande . "</strong> est maintenant disponible.</p>
                
                <div class='info-box'>
                    <h3>📦 Détails de la commande</h3>
                    <p><strong>Numéro de commande :</strong> #" . $idCommande . "</p>
                    <p><strong>Montant :</strong> " . number_format($commande['montantTotal'], 2, ',', ' ') . " € HT</p>
                    <p><strong>Date :</strong> " . date('d/m/Y à H:i') . "</p>
                </div>
                
                <p><strong>📋 Votre facture :</strong></p>
                <p>Vous pouvez consulter et télécharger votre facture en cliquant sur le lien ci-dessous :</p>
                
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='http://217.182.198.20/Origami/facture.php?id=" . $idCommande . "' class='btn'>📄 Voir ma facture</a>
                </div>
                
                <div class='info-box'>
                    <h4>🏢 Mention importante</h4>
                    <p><strong>Exonération de TVA - Art. 293 B du CGI</strong></p>
                    <p>Tous les montants sont indiqués hors taxes conformément à la réglementation en vigueur.</p>
                </div>
                
                <p>Nous vous remercions pour votre confiance !</p>
            </div>
            
            <div class='footer'>
                <p><strong>Youki and Co - Créations artisanales japonaises</strong></p>
                <p>📧 contact@YoukiandCo.fr | 📞 +33 1 23 45 67 89</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $mail->Body = $messageHTML;
    $mail->AltBody = "Bonjour " . $commande['client_prenom'] . ",\n\nVotre facture pour la commande #" . $idCommande . " est disponible.\nMontant: " . number_format($commande['montantTotal'], 2, ',', ' ') . " € HT\n\nConsultez votre facture: http://217.182.198.20/Origami/facture.php?id=" . $idCommande . "\n\nExonération de TVA - Art. 293 B du CGI\n\nMerci pour votre confiance!\n\nOrigami Zen";
    
    if ($mail->send()) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Email envoyé</title>
            <style>
                body { font-family: Arial; background: #d4edda; color: #155724; padding: 50px; text-align: center; }
                .success-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
                .btn { background: #d40000; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px; }
            </style>
        </head>
        <body>
            <div class='success-container'>
                <h1>✅ Email envoyé !</h1>
                <p>La facture a été envoyée à <strong>" . htmlspecialchars($commande['client_email']) . "</strong></p>
                <p>Commande #" . $idCommande . "</p>
                <div style='margin-top: 30px;'>
                    <a href='facture.php?id=" . $idCommande . "' class='btn'>📄 Voir la facture</a>
                    <a href='index.html' class='btn'>🏠 Accueil</a>
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
            body { font-family: Arial; background: #f8d7da; color: #721c24; padding: 50px; text-align: center; }
            .error-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <h1>❌ Erreur</h1>
            <p>" . htmlspecialchars($e->getMessage()) . "</p>
            <p><a href='facture.php?id=" . $idCommande . "'>← Retour à la facture</a></p>
        </div>
    </body>
    </html>";
}
?>
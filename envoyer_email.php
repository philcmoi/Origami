<?php
// envoyer_email.php
require_once 'config.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && $input['action'] === 'envoyer_lien_confirmation') {
        $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
        
        if ($email) {
            try {
                // Générer un token unique
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Vérifier si le client existe déjà
                $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE email = ?");
                $stmt->execute([$email]);
                $client = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($client) {
                    // Mettre à jour le token pour le client existant
                    $stmt = $pdo->prepare("
                        UPDATE Client 
                        SET token_confirmation = ?, token_expires = ? 
                        WHERE idClient = ?
                    ");
                    $stmt->execute([$token, $expires, $client['idClient']]);
                } else {
                    // Créer un nouveau client avec un mot de passe temporaire
                    $motDePasseTemporaire = bin2hex(random_bytes(8));
                    $motDePasseHache = password_hash($motDePasseTemporaire, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO Client (email, motDePasse, token_confirmation, token_expires) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$email, $motDePasseHache, $token, $expires]);
                }
                
                // Créer le lien de confirmation
                $lienConfirmation = "http://localhost/origami/confirmer_commande.php?email=" . urlencode($email) . "&token=" . $token;
                
                // Configuration SMTP Gmail
                $mail = new PHPMailer(true);
                
                // DÉSACTIVER LA VÉRIFICATION SSL (pour développement)
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'lhpp.philippe@gmail.com'; // REMPLACEZ
                $mail->Password = 'lvpk zqjt vuon qyrz'; // REMPLACEZ
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                // Destinataires
                $mail->setFrom('lhpp.philippe@gmail.com', 'Origami Zen');
                $mail->addAddress($email);
                
                // Contenu
                $mail->isHTML(true);
                $mail->Subject = 'Confirmez votre commande Origami Zen';
                
                $mail->Body = "
                    <h2>Confirmation de votre commande</h2>
                    <p>Merci pour votre commande chez Origami Zen !</p>
                    <p>Veuillez cliquer sur le lien ci-dessous pour confirmer votre adresse email :</p>
                    <p><a href='$lienConfirmation' style='background-color: #d40000; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px;'>Confirmer mon email</a></p>
                    <p>Ou copiez ce lien dans votre navigateur :<br>$lienConfirmation</p>
                    <p>Ce lien expirera dans 1 heure.</p>
                ";
                
                $mail->AltBody = "Confirmez votre commande en cliquant sur : $lienConfirmation";
                
                if ($mail->send()) {
                    $response = [
                        'status' => 200,
                        'message' => 'Lien de confirmation envoyé avec succès à ' . $email
                    ];
                } else {
                    $response = [
                        'status' => 500, 
                        'error' => 'Erreur envoi email: ' . $mail->ErrorInfo
                    ];
                }
                
            } catch (Exception $e) {
                $response = [
                    'status' => 500, 
                    'error' => 'Erreur PHPMailer: ' . $e->getMessage()
                ];
            }
        } else {
            $response = ['status' => 400, 'error' => 'Email invalide'];
        }
    }
} else {
    $response = ['status' => 405, 'error' => 'Méthode non autorisée'];
}

echo json_encode($response);
?>
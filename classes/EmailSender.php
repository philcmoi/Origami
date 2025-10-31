<?php
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';
require_once 'config/smtp_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailSender {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->setupSMTP();
    }
    
    private function setupSMTP() {
        // Configuration SMTP
        $this->mail->isSMTP();
        $this->mail->Host = SMTP_HOST;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = SMTP_USERNAME;
        $this->mail->Password = SMTP_PASSWORD;
        $this->mail->SMTPSecure = SMTP_SECURE;
        $this->mail->Port = SMTP_PORT;
        
        // Options SSL pour développement
        global $smtp_options;
        $this->mail->SMTPOptions = $smtp_options;
        
        // Encodage
        $this->mail->CharSet = 'UTF-8';
        
        // Expéditeur par défaut
        $this->mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    }
    
    public function envoyerEmailConfirmation($email_client, $nom_client, $code_confirmation, $details_commande = []) {
        try {
            // Réinitialiser
            $this->mail->clearAllRecipients();
            
            // Destinataire
            $this->mail->addAddress($email_client, $nom_client);
            
            // Sujet
            $this->mail->Subject = 'Confirmation de votre commande - Origami Zen';
            
            // Corps HTML
            $this->mail->isHTML(true);
            $this->mail->Body = $this->genererEmailConfirmation($nom_client, $code_confirmation, $details_commande);
            
            // Corps texte
            $this->mail->AltBody = $this->genererEmailConfirmationTexte($nom_client, $code_confirmation, $details_commande);
            
            // Envoi
            $result = $this->mail->send();
            
            return [
                'success' => $result,
                'message' => $result ? 'Email de confirmation envoyé avec succès' : $this->mail->ErrorInfo
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    private function genererEmailConfirmation($nom_client, $code_confirmation, $details_commande) {
        $produits_html = '';
        $total = 0;
        
        if (!empty($details_commande['articles'])) {
            foreach ($details_commande['articles'] as $article) {
                $produits_html .= '
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">' . htmlspecialchars($article['nom']) . '</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;">' . $article['quantite'] . '</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;">' . number_format($article['prixUnitaire'], 2, ',', ' ') . ' €</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;">' . number_format($article['totalLigne'], 2, ',', ' ') . ' €</td>
                </tr>';
                $total += $article['totalLigne'];
            }
        }
        
        return '
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Confirmation de commande - Origami Zen</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f9f9f9; }
                .container { max-width: 600px; margin: 0 auto; background: white; }
                .header { background: #d40000; color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .footer { background: #f1f1f1; padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .code-confirmation { background: #f8f9fa; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; border: 2px dashed #d40000; }
                .produits-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .produits-table th { background: #f8f9fa; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; }
                .total { font-size: 18px; font-weight: bold; color: #d40000; text-align: right; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎎 Origami Zen</h1>
                    <p>L\'art délicat de l\'origami</p>
                </div>
                
                <div class="content">
                    <h2>Bonjour ' . htmlspecialchars($nom_client) . ',</h2>
                    <p>Merci pour votre commande chez <strong>Origami Zen</strong> !</p>
                    <p>Votre commande a bien été enregistrée et est en cours de préparation.</p>
                    
                    <div class="code-confirmation">
                        <h3 style="margin: 0; color: #d40000;">Votre code de confirmation</h3>
                        <div style="font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 10px 0;">' . $code_confirmation . '</div>
                        <p style="margin: 0; font-size: 14px; color: #666;">Conservez ce code pour suivre votre commande</p>
                    </div>
                    
                    <h3>Détails de votre commande :</h3>
                    <table class="produits-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th style="text-align: center;">Quantité</th>
                                <th style="text-align: right;">Prix unitaire</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $produits_html . '
                        </tbody>
                    </table>
                    
                    <div class="total">
                        Total de la commande : ' . number_format($total, 2, ',', ' ') . ' €
                    </div>
                    
                    <p><strong>Prochaines étapes :</strong></p>
                    <ul>
                        <li>Vous recevrez un email de confirmation lorsque votre commande sera expédiée</li>
                        <li>Délai de livraison estimé : 3-5 jours ouvrables</li>
                        <li>Pour toute question, contactez-nous à contact@origamizen.fr</li>
                    </ul>
                </div>
                
                <div class="footer">
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                    <p>&copy; ' . date('Y') . ' Origami Zen. Tous droits réservés.<br>
                    <a href="' . SITE_URL . '" style="color: #d40000;">' . SITE_URL . '</a></p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    private function genererEmailConfirmationTexte($nom_client, $code_confirmation, $details_commande) {
        $texte = "Bonjour $nom_client,\n\n";
        $texte .= "Merci pour votre commande chez Origami Zen !\n\n";
        $texte .= "VOTRE CODE DE CONFIRMATION : $code_confirmation\n\n";
        $texte .= "Détails de votre commande :\n";
        
        $total = 0;
        if (!empty($details_commande['articles'])) {
            foreach ($details_commande['articles'] as $article) {
                $texte .= "- {$article['nom']} x{$article['quantite']} : " . number_format($article['totalLigne'], 2, ',', ' ') . " €\n";
                $total += $article['totalLigne'];
            }
        }
        
        $texte .= "\nTOTAL : " . number_format($total, 2, ',', ' ') . " €\n\n";
        $texte .= "Votre commande est en cours de préparation.\n";
        $texte .= "Délai de livraison estimé : 3-5 jours ouvrables\n\n";
        $texte .= "Cordialement,\nL'équipe Origami Zen\n";
        $texte .= SITE_URL . "\n";
        $texte .= "contact@origamizen.fr";
        
        return $texte;
    }
}
?>
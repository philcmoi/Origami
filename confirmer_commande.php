<?php
// confirmer_commande.php
session_start();

// Inclure la configuration de la base de données
require_once 'config.php';

// SUPPRIMER cette ligne : header('Content-Type: application/json');

$response = ['status' => 400, 'error' => 'Requête invalide'];

// Vérifier si c'est une requête POST ou GET (pour le lien dans l'email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // Pour les requêtes POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $token = $input['token'] ?? '';
    } 
    // Pour les requêtes GET (lien dans l'email)
    else {
        $email = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $token = $_GET['token'] ?? '';
    }
    
    if ($email && !empty($token)) {
        try {
            // Vérifier le token dans la table Client
            $stmt = $pdo->prepare("
                SELECT idClient, token_confirmation, token_expires 
                FROM Client 
                WHERE email = ? AND token_confirmation = ?
            ");
            $stmt->execute([$email, $token]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($client) {
                // Vérifier si le token n'a pas expiré
                if (strtotime($client['token_expires']) > time()) {
                    // Marquer l'email comme confirmé
                    $stmt = $pdo->prepare("
                        UPDATE Client 
                        SET email_confirme = 1, token_confirmation = NULL, token_expires = NULL 
                        WHERE idClient = ?
                    ");
                    $stmt->execute([$client['idClient']]);
                    
                    // Réponse JSON pour les requêtes AJAX
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        header('Content-Type: application/json'); // ← Déplacer ici
                        $response = [
                            'status' => 200,
                            'message' => 'Email confirmé avec succès!',
                            'data' => ['idClient' => $client['idClient']]
                        ];
                        echo json_encode($response);
                        exit;
                    } 
                    // Page HTML pour les liens directs
                    else {
                        header('Content-Type: text/html; charset=UTF-8'); // ← Ajouter cet en-tête
                        echo "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Email confirmé - Origami Zen</title>
                            <style>
                                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                                .success { color: #28a745; font-size: 24px; }
                                .info { color: #666; margin-top: 20px; }
                            </style>
                        </head>
                        <body>
                            <div class='success'>✅ Email confirmé avec succès !</div>
                            <div class='info'>Vous pouvez maintenant retourner à votre commande.</div>
                            <script>
                                setTimeout(function() {
                                    window.close();
                                }, 3000);
                            </script>
                        </body>
                        </html>
                        ";
                        exit;
                    }
                } else {
                    $response = ['status' => 400, 'error' => 'Token expiré'];
                }
            } else {
                $response = ['status' => 400, 'error' => 'Token invalide'];
            }
        } catch (PDOException $e) {
            $response = ['status' => 500, 'error' => 'Erreur base de données: ' . $e->getMessage()];
        }
    } else {
        $response = ['status'  => 400, 'error' => 'Email ou token invalide'];
    }
}

// Si on arrive ici, c'est une erreur en mode POST ou autre
header('Content-Type: application/json');
echo json_encode($response);
?>
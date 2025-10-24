<?php
// verifier_confirmation.php

// Inclure la configuration de la base de données
require_once 'config.php';

header('Content-Type: application/json');

$email = $_GET['email'] ?? '';

if ($email) {
    try {
        $stmt = $pdo->prepare("
            SELECT idClient, email_confirme 
            FROM Client 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($client) {
            $response = [
                'status' => 200,
                'data' => [
                    'confirme' => (bool)$client['email_confirme'],
                    'id_client' => $client['idClient']
                ]
            ];
        } else {
            $response = ['status' => 404, 'error' => 'Client non trouvé'];
        }
    } catch (PDOException $e) {
        $response = ['status' => 500, 'error' => 'Erreur base de données'];
    }
} else {
    $response = ['status' => 400, 'error' => 'Email requis'];
}

echo json_encode($response);
?>
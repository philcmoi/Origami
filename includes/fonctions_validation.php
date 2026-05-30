<?php
// includes/fonctions_validation.php

function validerTokenConfirmation($pdo, $token) {
    $stmt = $pdo->prepare("SELECT email, id_client, expiration, utilise FROM tokens_confirmation WHERE token = ?");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenData) {
        return ['valid' => false, 'error' => 'Lien invalide'];
    }

    if ($tokenData['utilise'] == 1) {
        return ['valid' => false, 'error' => 'Ce lien a déjà été utilisé'];
    }

    if (strtotime($tokenData['expiration']) < time()) {
        return ['valid' => false, 'error' => 'Lien expiré'];
    }

    return ['valid' => true, 'data' => $tokenData];
}
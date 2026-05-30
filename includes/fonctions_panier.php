<?php
// includes/fonctions_panier.php

function getOrCreateClient($pdo) {
    if (isset($_SESSION['client_id'])) {
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE idClient = ?");
        $stmt->execute([$_SESSION['client_id']]);
        if ($stmt->fetch()) {
            return $_SESSION['client_id'];
        }
        unset($_SESSION['client_id']);
    }
    
    $sessionId = session_id();
    
    try {
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($clientExist) {
        $_SESSION['client_id'] = $clientExist['idClient'];
        return $clientExist['idClient'];
    }
    
    // Créer un nouveau client temporaire
    try {
        $stmt = $pdo->prepare("INSERT INTO Client (email, nom, prenom, session_id) VALUES (?, 'Invité', 'Client', ?)");
        $emailTemp = 'temp_' . uniqid() . '@YoukiAndCo.fr';
        $stmt->execute([$emailTemp, $sessionId]);
    } catch (Exception $e) {
        $stmt = $pdo->prepare("INSERT INTO Client (email, nom, prenom, session_id) VALUES (?, 'Invité', 'Client', ?)");
        $emailTemp = 'temp_' . uniqid() . '@YoukiAndCo.fr';
        $stmt->execute([$emailTemp, $sessionId]);
    }
    
    $clientId = $pdo->lastInsertId();
    $_SESSION['client_id'] = $clientId;
    
    return $clientId;
}

function nettoyerTokensExpires($pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM tokens_confirmation WHERE expiration < NOW() OR utilise = 1");
        $stmt->execute();
    } catch (Exception $e) {
        // Ignorer les erreurs
    }
}

function nettoyerClientsTemporairesAmeliore($pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM Client WHERE session_id IS NOT NULL AND date_creation < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (Exception $e) {
        return 0;
    }
}

function forcerNettoyageComplet($pdo) {
    return nettoyerClientsTemporairesAmeliore($pdo);
}
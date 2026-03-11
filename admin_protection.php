<?php
// admin_protection.php - Fichier de protection pour toutes les pages admin
session_start();

// Vérifier si l'administrateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Vérifier la dernière activité pour la sécurité
$timeout_duration = 1800; // 30 minutes en secondes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    // Session expirée
    session_unset();
    session_destroy();
    header('Location: admin_login.php?expired=1');
    exit;
}

// Mettre à jour le timestamp de dernière activité
$_SESSION['last_activity'] = time();

// Protection contre les attaques de fixation de session
if (!isset($_SESSION['admin_ip'])) {
    $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'];
} else {
    if ($_SESSION['admin_ip'] !== $_SERVER['REMOTE_ADDR']) {
        // Adresse IP a changé - déconnexion forcée
        session_unset();
        session_destroy();
        header('Location: admin_login.php?security=1');
        exit;
    }
}
?>
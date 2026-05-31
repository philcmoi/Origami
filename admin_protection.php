<?php
// admin_protection.php - Fichier de protection pour toutes les pages admin
session_start();

// Vérifier si l'administrateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Timeout session : 30 minutes
$timeout_duration = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: admin_login.php?expired=1');
    exit;
}

$_SESSION['last_activity'] = time();

// Protection IP
if (!isset($_SESSION['admin_ip'])) {
    $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'];
} elseif ($_SESSION['admin_ip'] !== $_SERVER['REMOTE_ADDR']) {
    session_unset();
    session_destroy();
    header('Location: admin_login.php?security=1');
    exit;
}

// Protection User-Agent (optionnel mais recommandé)
if (!isset($_SESSION['admin_user_agent'])) {
    $_SESSION['admin_user_agent'] = $_SERVER['HTTP_USER_AGENT'];
} elseif ($_SESSION['admin_user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    session_unset();
    session_destroy();
    header('Location: admin_login.php?security=1');
    exit;
}
?>
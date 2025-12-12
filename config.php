<?php
// config.php - Configuration de l'application

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'Philippe'); // À adapter
define('DB_PASS', 'l@99339R'); // À adapter

// Configuration PayPal
define('PAYPAL_CLIENT_ID', 'Aac1-P0VrxBQ_5REVeo4f557_-p6BDeXA_hyiuVZfi21sILMWccBFfTidQ6nnhQathCbWaCSQaDmxJw5'); // Remplacez par votre Client ID
define('PAYPAL_CLIENT_SECRET', 'EJxech0i1faRYlo0-ln2sU09ecx5rP3XEOGUTeTduI2t-I0j4xoSPqRRFQTxQsJoSBbSL8aD1b1GPPG1'); // Remplacez par votre Client Secret
define('PAYPAL_ENVIRONMENT', 'sandbox'); // 'sandbox' pour test, 'live' pour production
define('PAYPAL_RETURN_URL', 'http://localhost/paiement_reussi.php');
define('PAYPAL_CANCEL_URL', 'http://localhost/paiement_annule.php');

// Configuration du site
define('SITE_NAME', 'HEURE DU CADEAU');
define('SITE_EMAIL', 'contact@heureducadeau.fr');
define('SITE_URL', 'http://localhost');

// Configuration SMTP pour emails (optionnel)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'votre-email@gmail.com');
define('SMTP_PASS', 'votre-mot-de-passe');
define('SMTP_FROM', 'contact@heureducadeau.fr');
define('SMTP_FROM_NAME', 'HEURE DU CADEAU');

// Désactiver l'affichage des erreurs en production
if (PAYPAL_ENVIRONMENT === 'live') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
?>
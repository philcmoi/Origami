<?php
// Configuration SMTP pour Origami Zen
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'lhpp.philippe@gmail.com'); // Remplacez par votre email
define('SMTP_PASSWORD', 'lvpk zqjt vuon qyrz'); // Mot de passe d'application Gmail
define('SMTP_FROM_EMAIL', 'lhpp.philipel@gmail.com');
define('SMTP_FROM_NAME', 'Origami Zen');
define('SMTP_SECURE', 'tls');

// Options de sécurité pour développement
$smtp_options = array(
    'ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    )
);

// Configuration du site
define('SITE_URL', 'http://localhost/origami');
define('SITE_NAME', 'Origami Zen');
?>
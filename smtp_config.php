<?php
// Configuration SMTP - Ne pas commit dans Git!
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'lhpp.philippe@gmail.com');
define('SMTP_PASSWORD', 'lvpk zqjt vuon qyrz');
define('SMTP_FROM_EMAIL', 'lhpp.philippe@gmail.com');
define('SMTP_FROM_NAME', 'Votre Site Origami');
define('SMTP_SECURE', 'tls'); // tls ou ssl

// Options de sécurité pour développement
$smtp_options = array(
    'ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    )
);
?>
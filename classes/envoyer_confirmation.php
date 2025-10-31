<?php
session_start();
require_once 'classes/EmailSender.php';

header('Content-Type: application/json');

// Récupérer les données POST
$email = $_POST['email'] ?? '';
$panier = $_POST['panier'] ?? []; // Le panier est envoyé en JSON

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email manquant']);
    exit;
}

// Générer un code de confirmation (6 chiffres)
$code = sprintf("%06d", mt_rand(1, 999999));

// Stocker le code en session
$_SESSION['code_confirmation'] = $code;
$_SESSION['email_confirmation'] = $email;
$_SESSION['timestamp_confirmation'] = time();

// Préparer le contenu de l'email
$sujet = "Votre code de confirmation - Origami Zen";
$corps = "
    <h2>Confirmation de votre commande</h2>
    <p>Merci de votre intérêt pour nos créations Origami.</p>
    <p>Votre code de confirmation est : <strong>{$code}</strong></p>
    <p>Veuillez saisir ce code sur notre site pour confirmer votre commande.</p>
    <p>Ce code est valable pendant 15 minutes.</p>
";

// Envoyer l'email
$emailSender = new EmailSender();
$resultat = $emailSender->sendEmail($email, $email, $sujet, $corps);

if ($resultat['success']) {
    echo json_encode(['success' => true, 'message' => 'Code de confirmation envoyé']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'email: ' . $resultat['message']]);
}
?>
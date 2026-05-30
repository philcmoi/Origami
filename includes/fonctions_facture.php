<?php
// includes/fonctions_facture.php

// Vérifier si TCPDF est déjà chargé avant de l'inclure
if (!class_exists('TCPDF', false)) {
    if (file_exists('tcpdf/tcpdf.php')) {
        require_once('tcpdf/tcpdf.php');
    }
}

// Inclure genererFacturePDF.php seulement si nécessaire
if (!function_exists('genererFacturePDF') && file_exists('genererFacturePDF.php')) {
    require_once 'genererFacturePDF.php';
}

function genererFactureAPI($idCommande, $format = 'html') {
    global $pdo;
    
    if ($format === 'pdf') {
        if (function_exists('genererFacturePDF')) {
            return genererFacturePDF($pdo, $idCommande);
        }
        return false;
    } else {
        return "facture.php?id=" . $idCommande;
    }
}

function envoyerFactureEmail($pdo, $idCommande, $emailClient, $format = 'pdf') {
    $fichierFacture = genererFactureAPI($idCommande, $format);
    
    if (!$fichierFacture) {
        return ['success' => false, 'error' => 'Erreur génération facture'];
    }
    
    $sujet = "Votre facture #" . $idCommande . " - Youki and Co";
    $message = "<h2>Votre facture</h2><p>Merci pour votre commande.</p>";
    
    if (function_exists('envoyerEmailAvecPieceJointe')) {
        return envoyerEmailAvecPieceJointe($emailClient, $sujet, $message, $fichierFacture);
    }
    
    return ['success' => false, 'error' => 'Fonction d\'envoi d\'email non disponible'];
}
?>
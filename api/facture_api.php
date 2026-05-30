<?php
// ============================================
// API DE GESTION DES FACTURES
// ============================================

if ($action == 'generer_facture_html') {
    $idCommande = $data['id_commande'] ?? null;
    
    if (!$idCommande) {
        echo json_encode(['status' => 400, 'error' => 'ID commande manquant']);
        exit;
    }
    
    $urlFacture = genererFactureAPI($idCommande, 'html');
    
    if ($urlFacture) {
        echo json_encode([
            'status' => 200,
            'data' => [
                'url_facture' => $urlFacture,
                'message' => 'Facture HTML générée avec succès'
            ]
        ]);
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur lors de la génération de la facture HTML']);
    }
    exit;
}

if ($action == 'generer_facture_pdf') {
    $idCommande = $data['id_commande'] ?? null;
    
    if (!$idCommande) {
        echo json_encode(['status' => 400, 'error' => 'ID commande manquant']);
        exit;
    }
    
    $fichierFacture = genererFactureAPI($idCommande, 'pdf');
    
    if ($fichierFacture) {
        echo json_encode([
            'status' => 200,
            'data' => [
                'fichier_facture' => $fichierFacture,
                'url_facture' => 'http://' . $_SERVER['HTTP_HOST'] . '/' . $fichierFacture,
                'message' => 'Facture PDF générée avec succès'
            ]
        ]);
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur lors de la génération de la facture PDF']);
    }
    exit;
}

if ($action == 'envoyer_facture_email') {
    $idCommande = $data['id_commande'] ?? null;
    $email = $data['email'] ?? null;
    $format = $data['format'] ?? 'pdf';
    
    if (!$idCommande || !$email) {
        echo json_encode(['status' => 400, 'error' => 'ID commande ou email manquant']);
        exit;
    }
    
    $resultat = envoyerFactureEmail($pdo, $idCommande, $email, $format);
    
    if ($resultat['success']) {
        echo json_encode([
            'status' => 200,
            'data' => [
                'message' => $resultat['message'],
                'id_commande' => $idCommande,
                'format' => $format
            ]
        ]);
    } else {
        echo json_encode(['status' => 500, 'error' => $resultat['error']]);
    }
    exit;
}

if ($action == 'telecharger_facture') {
    $idCommande = $data['id_commande'] ?? ($_GET['id_commande'] ?? null);
    
    if (!$idCommande) {
        if ($is_html_response) {
            echo "<script>alert('ID commande manquant'); window.location.href = 'index.html';</script>";
        } else {
            echo json_encode(['status' => 400, 'error' => 'ID commande manquant']);
        }
        exit;
    }
    
    $success = telechargerFacturePDF($idCommande);
    
    if (!$success) {
        if ($is_html_response) {
            echo "<script>alert('Erreur lors de la génération du PDF'); window.location.href = 'index.html';</script>";
        } else {
            echo json_encode(['status' => 500, 'error' => 'Erreur lors de la génération du PDF']);
        }
    }
    exit;
}
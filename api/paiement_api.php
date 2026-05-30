<?php
// ============================================
// API DE PAIEMENT PAYPAL
// ============================================

global $paypal_config, $pdo;

if ($action == 'creer_commande_paypal') {
    $montant = $data['montant'] ?? 0;
    $idCommande = $data['id_commande'] ?? null;
    
    if ($montant <= 0) {
        echo json_encode(['status' => 400, 'error' => 'Montant invalide']);
        exit;
    }
    
    $access_token = getPayPalAccessToken(
        $paypal_config['client_id'],
        $paypal_config['client_secret'],
        $paypal_config['environment']
    );
    
    if (!$access_token) {
        echo json_encode(['status' => 500, 'error' => 'Erreur de connexion à PayPal']);
        exit;
    }
    
    $custom_data = $idCommande ? "commande_$idCommande" : null;
    $order = createPayPalOrder(
        $access_token,
        $montant,
        'EUR',
        $paypal_config['environment'],
        $paypal_config['return_url'],
        $paypal_config['cancel_url'],
        $custom_data
    );
    
    if ($order && isset($order['id'])) {
        $_SESSION['paypal_order_id'] = $order['id'];
        if ($idCommande) {
            $_SESSION['paypal_commande_id'] = $idCommande;
        }
        
        // Trouver le lien d'approbation
        $approve_link = '';
        foreach ($order['links'] as $link) {
            if ($link['rel'] === 'approve') {
                $approve_link = $link['href'];
                break;
            }
        }
        
        echo json_encode([
            'status' => 200,
            'data' => [
                'order_id' => $order['id'],
                'approve_url' => $approve_link,
                'montant' => $montant
            ]
        ]);
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur lors de la création de la commande PayPal']);
    }
    exit;
}

elseif ($action == 'capturer_paiement_paypal') {
    $order_id = $data['order_id'] ?? '';
    
    if (!$order_id) {
        echo json_encode(['status' => 400, 'error' => 'ID commande manquant']);
        exit;
    }
    
    $access_token = getPayPalAccessToken(
        $paypal_config['client_id'],
        $paypal_config['client_secret'],
        $paypal_config['environment']
    );
    
    if (!$access_token) {
        echo json_encode(['status' => 500, 'error' => 'Erreur de connexion à PayPal']);
        exit;
    }
    
    $capture = capturePayPalPayment($access_token, $order_id, $paypal_config['environment']);
    
    if ($capture && isset($capture['status']) && $capture['status'] === 'COMPLETED') {
        $commande_id = $_SESSION['paypal_commande_id'] ?? null;
        
        if ($commande_id) {
            try {
                $stmt = $pdo->prepare("UPDATE Commande SET statut = 'payee', modeReglement = 'PayPal' WHERE idCommande = ?");
                $stmt->execute([$commande_id]);
                
                $montant = $capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0;
                $stmt = $pdo->prepare("
                    INSERT INTO Paiement 
                    (idCommande, montant, currency, statut, methode_paiement, reference, date_creation) 
                    VALUES (?, ?, 'EUR', 'payee', 'PayPal', ?, NOW())
                ");
                $transaction_id = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? $order_id;
                $stmt->execute([$commande_id, $montant, $transaction_id]);
                
                // Générer la facture PDF
                $fichierFacture = genererFactureAPI($commande_id, 'pdf');
                
                // Envoyer la facture par email
                $stmt = $pdo->prepare("SELECT cl.email FROM Commande c JOIN Client cl ON c.idClient = cl.idClient WHERE c.idCommande = ?");
                $stmt->execute([$commande_id]);
                $client_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($client_info) {
                    envoyerFactureEmail($pdo, $commande_id, $client_info['email'], 'pdf');
                }
                
                unset($_SESSION['paypal_order_id']);
                unset($_SESSION['paypal_commande_id']);
                
            } catch (Exception $e) {
                error_log("Erreur mise à jour commande PayPal: " . $e->getMessage());
            }
        }
        
        echo json_encode([
            'status' => 200, 
            'message' => 'Paiement capturé avec succès',
            'order_id' => $order_id,
            'commande_id' => $commande_id
        ]);
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur lors de la capture du paiement PayPal']);
    }
    exit;
}
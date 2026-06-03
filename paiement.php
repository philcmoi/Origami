<?php
// ============================================
// PAGE DE PAIEMENT - VERSION CORRIGÉE
// SOLUTION : Utilisation du bouton PayPal Direct au lieu des Hosted Fields
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';
require_once __DIR__ . '/smtp_config.php';
require_once 'modal_paiement_test.php';

// Configuration PayPal
define('PAYPAL_CLIENT_ID', 'Aac1-P0VrxBQ_5REVeo4f557_-p6BDeXA_hyiuVZfi21sILMWccBFfTidQ6nnhQathCbWaCSQaDmxJw5');
define('PAYPAL_CURRENCY', 'EUR');

// Vérification d'accès
checkPaiementAccess();

// Connexion BDD
$pdo = getPDOConnection();

// Synchronisation panier
if ($pdo) {
    synchroniserPanierSessionBDD($pdo, session_id());
}

// Nettoyer les flags PayPal
cleanPayPalFlags();

// Récupérer les messages
$messages = getSessionMessages();

// Récupérer les données du checkout
$checkout = $_SESSION[SESSION_KEY_CHECKOUT] ?? [];
$adresse = $checkout['adresse_livraison'] ?? [];
$mode_livraison = $checkout['mode_livraison'] ?? 'standard';
$emballage_cadeau = $checkout['emballage_cadeau'] ?? false;

// Récupérer les articles du panier
$panier_details = [];
$sous_total = 0;

if (isset($_SESSION[SESSION_KEY_PANIER]) && !empty($_SESSION[SESSION_KEY_PANIER])) {
    foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
        $produit = getProductDetails($item['id_produit'], $pdo);
        $prix_unitaire = floatval($produit['prix_ttc'] ?? $item['prix'] ?? 0);
        $quantite = intval($item['quantite'] ?? 1);
        $prix_total = $quantite * $prix_unitaire;
        $sous_total += $prix_total;
        
        $panier_details[] = [
            'id_produit' => $item['id_produit'],
            'quantite' => $quantite,
            'nom' => $produit['nom'] ?? $item['nom'] ?? 'Produit',
            'description' => $produit['description'] ?? '',
            'prix_unitaire' => $prix_unitaire,
            'prix_total' => $prix_total,
            'image' => $produit['photo'] ?? 'img/default-product.jpg'
        ];
    }
}

// Si panier vide via BDD
if (empty($panier_details) && $pdo && isset($checkout['panier_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT lp.idOrigami, lp.quantite, lp.prixUnitaire, o.nom, o.description, o.photo
            FROM LignePanier lp
            JOIN Origami o ON lp.idOrigami = o.idOrigami
            JOIN Panier p ON lp.idPanier = p.idPanier
            WHERE p.idPanier = ?
        ");
        $stmt->execute([$checkout['panier_id']]);
        $bdItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($bdItems as $item) {
            $prix_total = $item['quantite'] * $item['prixUnitaire'];
            $sous_total += $prix_total;
            
            $panier_details[] = [
                'id_produit' => $item['idOrigami'],
                'quantite' => $item['quantite'],
                'nom' => $item['nom'],
                'description' => $item['description'] ?? '',
                'prix_unitaire' => $item['prixUnitaire'],
                'prix_total' => $prix_total,
                'image' => $item['photo'] ?? 'img/default-product.jpg'
            ];
        }
    } catch (Exception $e) {
        error_log("Erreur récupération panier BDD: " . $e->getMessage());
    }
}

// Calcul des totaux
$totaux = calculerTotauxPanier($panier_details, $checkout);
$total_commande = $totaux['total'];

// Si panier vide, rediriger
if (empty($panier_details)) {
    addSessionMessage('Votre panier est vide', 'error');
    header('Location: index.html');
    exit;
}

// Si pas d'adresse, rediriger
if (empty($adresse)) {
    addSessionMessage('Veuillez renseigner votre adresse de livraison', 'warning');
    header('Location: livraison_form.php');
    exit;
}

// Traitement du paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['process_payment'])) {
        $methode = $input['methode_paiement'] ?? 'paypal';
        $reference = $input['reference_paiement'] ?? null;
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO Commande (
                    idClient, idAdresseLivraison, idAdresseFacturation,
                    dateCommande, modeReglement, delaiLivraison,
                    fraisDePort, montantTotal, statut, statut_paiement
                ) VALUES (
                    ?, ?, ?,
                    NOW(), ?, DATE_ADD(NOW(), INTERVAL 5 DAY),
                    ?, ?, 'payee', 'payee'
                )
            ");
            
            $idClient = $checkout['client_id'] ?? $_SESSION[SESSION_KEY_CLIENT_ID] ?? null;
            $idAdresseLivraison = $adresse['id'] ?? null;
            $idAdresseFacturation = $checkout['adresse_facturation']['id'] ?? $idAdresseLivraison;
            $modeReglement = ($methode === 'paypal') ? 'PayPal' : 'Carte Bancaire (PayPal)';
            $fraisDePort = $totaux['frais_livraison'] + $totaux['frais_emballage'];
            
            $stmt->execute([
                $idClient,
                $idAdresseLivraison,
                $idAdresseFacturation,
                $modeReglement,
                $fraisDePort,
                $total_commande
            ]);
            
            $commande_id = $pdo->lastInsertId();
            
            $stmt_item = $pdo->prepare("
                INSERT INTO LigneCommande (idCommande, idOrigami, quantite, prixUnitaire)
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($panier_details as $item) {
                $stmt_item->execute([
                    $commande_id,
                    $item['id_produit'],
                    $item['quantite'],
                    $item['prix_unitaire']
                ]);
            }
            
            $stmt_paiement = $pdo->prepare("
                INSERT INTO Paiement (idCommande, montant, currency, statut, methode_paiement, reference, date_creation)
                VALUES (?, ?, 'EUR', 'payee', ?, ?, NOW())
            ");
            $reference_finale = $reference ?? 'PAY_' . time() . '_' . $commande_id;
            $stmt_paiement->execute([$commande_id, $total_commande, $modeReglement, $reference_finale]);
            
            if (isset($checkout['panier_id'])) {
                $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ?");
                $stmt->execute([$checkout['panier_id']]);
            }
            
            $_SESSION[SESSION_KEY_PANIER] = [];
            unset($_SESSION[SESSION_KEY_PANIER_ID]);
            unset($_SESSION[SESSION_KEY_CHECKOUT]);
            
            $pdo->commit();
            
            if (file_exists('genererFacturePDF.php')) {
                require_once 'genererFacturePDF.php';
                if (function_exists('genererFacturePDF')) {
                    $cheminFacture = genererFacturePDF($pdo, $commande_id);
                    if ($cheminFacture && !empty($adresse['email'])) {
                        envoyerFactureParEmail($adresse['email'], $cheminFacture, $commande_id);
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'commande_id' => $commande_id,
                'redirect' => 'paiement-reussi-email.php?commande=' . $commande_id . '&token=' . urlencode($reference_finale)
            ]);
            exit;
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erreur création commande: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande: ' . $e->getMessage()
            ]);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Sécurisé - Youki and Co</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chargement du SDK PayPal avec client-id -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo PAYPAL_CLIENT_ID; ?>&currency=EUR&components=buttons&enable-funding=paypal,card&locale=fr_FR"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .payment-layout {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 30px;
        }
        @media (max-width: 992px) {
            .payment-layout { grid-template-columns: 1fr; }
        }
        .payment-main, .payment-sidebar {
            background: white;
            border-radius: 30px;
            padding: 35px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h2, h3 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        h2 { border-bottom: 3px solid #e74c3c; padding-bottom: 15px; display: flex; align-items: center; gap: 15px; font-size: 1.5rem; }
        h2 i, h3 i { color: #e74c3c; }
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 40px;
            position: relative;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }
        .step-number {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #ecf0f1;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .step.active .step-number { background: #e74c3c; color: white; }
        .step.completed .step-number { background: #27ae60; color: white; }
        .step-text { font-size: 0.85rem; color: #7f8c8d; }
        .step.active .step-text { color: #e74c3c; font-weight: 600; }
        .step.completed .step-text { color: #27ae60; }
        .step-line {
            position: absolute;
            top: 22px;
            left: 10%;
            right: 10%;
            height: 3px;
            background: #ecf0f1;
            z-index: 0;
        }
        .adresse-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 30px;
            border-left: 5px solid #e74c3c;
        }
        .adresse-line { margin-bottom: 5px; color: #4a5568; }
        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 30px;
        }
        .method-tab {
            display: flex;
            gap: 15px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }
        .method-btn {
            padding: 12px 25px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            color: #7f8c8d;
            border-radius: 30px;
            transition: all 0.3s;
        }
        .method-btn.active {
            background: #e74c3c;
            color: white;
        }
        .method-btn:hover:not(.active) {
            background: #f0f0f0;
        }
        .payment-panel {
            display: none;
            padding: 20px 0;
        }
        .paypal-buttons-container { margin-top: 20px; min-height: 200px; }
        .cart-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .cart-item:last-child { border-bottom: none; }
        .cart-image {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .cart-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .cart-details { flex: 1; }
        .cart-details h4 { color: #2c3e50; margin-bottom: 5px; font-size: 1rem; }
        .cart-details .product-desc { color: #7f8c8d; font-size: 0.8rem; margin-bottom: 8px; }
        .cart-price { font-weight: 700; color: #e74c3c; }
        .cart-quantity { color: #7f8c8d; font-size: 0.85rem; margin-top: 5px; }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .summary-total {
            font-size: 1.3rem;
            font-weight: 800;
            color: #e74c3c;
            border-top: 2px solid #f0f0f0;
            padding-top: 15px;
            margin-top: 10px;
        }
        .security-badge {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            text-align: center;
            color: #7f8c8d;
            font-size: 0.85rem;
        }
        .message {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 12px;
            border-left: 5px solid transparent;
        }
        .message.success { background: #d4edda; color: #155724; border-left-color: #27ae60; }
        .message.error { background: #f8d7da; color: #721c24; border-left-color: #e74c3c; }
        .message.info { background: #d1ecf1; color: #0c5460; border-left-color: #3498db; }
        .btn-pay-simple {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .btn-pay-simple:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(39,174,96,0.3);
        }
        @media (max-width: 768px) {
            .payment-main, .payment-sidebar { padding: 25px; }
            .cart-image { width: 50px; height: 50px; }
            .method-tab { flex-direction: column; align-items: stretch; gap: 10px; }
            .method-btn { text-align: center; }
        }
    </style>
</head>
<body>

    <!-- Votre formulaire de paiement existant -->
    <div style="text-align: center; margin-top: 50px;">
        <h2>Formulaire de paiement</h2>
    
    <!-- Bouton pour afficher la modal d'aide -->
        <button type="button" onclick="ouvrirModalPaiement()" style="background-color: #2196F3; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
            📋 Afficher les coordonnées de test
        </button>
    
    </div>


    <div class="container">
        <div class="step-indicator">
            <div class="step completed"><div class="step-number">1</div><span class="step-text">Panier</span></div>
            <div class="step completed"><div class="step-number">2</div><span class="step-text">Livraison</span></div>
            <div class="step active"><div class="step-number">3</div><span class="step-text">Paiement</span></div>
            <div class="step-line"></div>
        </div>

        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message <?php echo htmlspecialchars($msg['type']); ?>">
                    <?php echo htmlspecialchars($msg['message']); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="payment-layout">
            <div class="payment-main">
                <h2><i class="fas fa-credit-card"></i> Mode de paiement</h2>

                <?php if (!empty($adresse)): ?>
                <div class="adresse-card">
                    <h3 style="margin-bottom: 10px;"><i class="fas fa-map-marker-alt"></i> Livraison</h3>
                    <p class="adresse-line"><strong><?php echo htmlspecialchars($adresse['prenom'] . ' ' . $adresse['nom']); ?></strong></p>
                    <p class="adresse-line"><?php echo htmlspecialchars($adresse['adresse']); ?></p>
                    <?php if (!empty($adresse['complement'])): ?>
                        <p class="adresse-line"><?php echo htmlspecialchars($adresse['complement']); ?></p>
                    <?php endif; ?>
                    <p class="adresse-line"><?php echo htmlspecialchars($adresse['code_postal'] . ' ' . $adresse['ville']); ?></p>
                    <p class="adresse-line"><?php echo htmlspecialchars($adresse['pays']); ?></p>
                    <a href="livraison_form.php" style="color: #e74c3c; margin-top: 10px; display: inline-block; font-size: 0.9rem;">
                        <i class="fas fa-edit"></i> Modifier
                    </a>
                </div>
                <?php endif; ?>

                <!-- UN SEUL BOUTON PAYPAL QUI FONCTIONNE -->
                <div class="payment-methods">
                    <p style="color: #7f8c8d; margin-bottom: 15px;">
                        <i class="fas fa-lock"></i> Paiement sécurisé par PayPal - Carte bancaire acceptée sans compte
                    </p>
                    <div id="paypal-button-container" class="paypal-buttons-container"></div>
                </div>
            </div>

            <div class="payment-sidebar">
                <h3><i class="fas fa-receipt"></i> Récapitulatif de la commande</h3>
                
                <div id="cart-items">
                    <?php foreach ($panier_details as $item): ?>
                    <div class="cart-item">
                        <div class="cart-image">
                            <?php if (!empty($item['image']) && file_exists($item['image'])): ?>
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['nom']); ?>">
                            <?php else: ?>
                                <i class="fas fa-gift" style="font-size: 28px; color: #e74c3c;"></i>
                            <?php endif; ?>
                        </div>
                        <div class="cart-details">
                            <h4><?php echo htmlspecialchars($item['nom']); ?></h4>
                            <?php if (!empty($item['description'])): ?>
                                <div class="product-desc"><?php echo htmlspecialchars(substr($item['description'], 0, 60)) . (strlen($item['description']) > 60 ? '...' : ''); ?></div>
                            <?php endif; ?>
                            <div class="cart-quantity">Quantité: <?php echo $item['quantite']; ?></div>
                        </div>
                        <div class="cart-price"><?php echo number_format($item['prix_total'], 2, ',', ' '); ?> €</div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-details">
                    <div class="summary-row">
                        <span>Sous-total</span>
                        <span><?php echo number_format($totaux['sous_total'], 2, ',', ' '); ?> €</span>
                    </div>
                    <div class="summary-row">
                        <span>Livraison (<?php echo $mode_livraison === 'express' ? 'Express' : ($mode_livraison === 'relais' ? 'Point Relais' : 'Standard'); ?>)</span>
                        <span><?php echo number_format($totaux['frais_livraison'], 2, ',', ' '); ?> €</span>
                    </div>
                    <?php if ($emballage_cadeau): ?>
                    <div class="summary-row">
                        <span>Emballage cadeau</span>
                        <span><?php echo number_format($totaux['frais_emballage'], 2, ',', ' '); ?> €</span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-row summary-total">
                        <span>Total TTC</span>
                        <span><?php echo number_format($total_commande, 2, ',', ' '); ?> €</span>
                    </div>
                </div>

                <div class="security-badge">
                    <i class="fas fa-shield-alt"></i> Paiement 100% sécurisé par PayPal<br>
                    <small>Vos données bancaires ne sont jamais stockées</small>
                </div>
            </div>
        </div>
    </div>

    <script>
        const totalMontant = <?php echo $total_commande; ?>;
        let paymentInProgress = false;

        async function createServerOrder(method, reference = null) {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    process_payment: true, 
                    methode_paiement: method,
                    reference_paiement: reference,
                    montant_total: totalMontant
                })
            });
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Erreur création commande');
            }
            return data;
        }

        function redirectToSuccess(commande_id, reference) {
            window.location.href = 'paiement-reussi-email.php?commande=' + commande_id + '&token=' + encodeURIComponent(reference);
        }

        function initPayPalButtons() {
            if (typeof paypal === 'undefined') {
                console.error('PayPal SDK non chargé, tentative dans 1s...');
                setTimeout(initPayPalButtons, 1000);
                return;
            }
            
            console.log('Initialisation PayPal, montant:', totalMontant);
            
            paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'blue',
                    shape: 'rect',
                    label: 'paypal',
                    height: 55
                },
                createOrder: function(data, actions) {
                    console.log('Création commande PayPal...');
                    return fetch('paypal.php?action=create_order', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            montant: totalMontant,
                            commande_id: 0
                        })
                    })
                    .then(function(response) {
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        return response.json();
                    })
                    .then(function(orderData) {
                        if (!orderData.success) throw new Error(orderData.message);
                        console.log('Commande PayPal créée:', orderData.order_id);
                        return orderData.order_id;
                    });
                },
                onApprove: function(data, actions) {
                    if (paymentInProgress) return;
                    paymentInProgress = true;
                    
                    console.log('Paiement approuvé:', data);
                    
                    return fetch('paypal.php?action=capture_order&order_id=' + data.orderID)
                        .then(function(response) {
                            if (!response.ok) throw new Error('HTTP ' + response.status);
                            return response.json();
                        })
                        .then(function(captureData) {
                            if (!captureData.success) throw new Error(captureData.message);
                            return createServerOrder('paypal', captureData.capture_id || data.orderID);
                        })
                        .then(function(result) {
                            redirectToSuccess(result.commande_id, result.reference || data.orderID);
                        })
                        .catch(function(error) {
                            console.error('Erreur:', error);
                            alert('Erreur: ' + error.message);
                            paymentInProgress = false;
                        });
                },
                onError: function(err) {
                    console.error('PayPal Error:', err);
                    alert('Erreur PayPal. Veuillez réessayer.');
                    paymentInProgress = false;
                },
                onCancel: function() {
                    console.log('Paiement annulé');
                    paymentInProgress = false;
                }
            }).render('#paypal-button-container').catch(function(err) {
                console.error('Erreur rendu:', err);
                document.getElementById('paypal-button-container').innerHTML = 
                    '<div style="color: red; padding: 20px; text-align: center;">' +
                    '⚠️ Erreur de chargement PayPal. Veuillez rafraîchir la page.' +
                    '</div>';
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initPayPalButtons, 500);
        });
    </script>


<?php
    // Appel de la fonction qui génère la modal (en bas de page)
    afficherModalCartesTest();
?>

</body>
</html>
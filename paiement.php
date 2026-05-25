<?php
// ============================================
// PAGE DE PAIEMENT - VERSION CORRIGÉE
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';
require_once __DIR__ . '/smtp_config.php';

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
            'nom' => $produit['nom'] ?? 'Produit',
            'prix_unitaire' => $prix_unitaire,
            'prix_total' => $prix_total,
            'image' => 'img/default-product.jpg'
        ];
    }
}

// Si panier vide via BDD, essayer de récupérer depuis la BDD
if (empty($panier_details) && $pdo && isset($checkout['panier_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT lp.idOrigami, lp.quantite, lp.prixUnitaire, o.nom
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
                'prix_unitaire' => $item['prixUnitaire'],
                'prix_total' => $prix_total,
                'image' => 'img/default-product.jpg'
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

// Traitement du paiement si formulaire soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['process_payment'])) {
        $methode = $input['methode_paiement'] ?? 'paypal';
        $reference = $input['reference_paiement'] ?? null;
        
        try {
            $pdo->beginTransaction();
            
            // Créer la commande
            $stmt = $pdo->prepare("
                INSERT INTO Commande (
                    idClient, idAdresseLivraison, idAdresseFacturation,
                    dateCommande, modeReglement, delaiLivraison,
                    fraisDePort, montantTotal, statut, statut_paiement
                ) VALUES (
                    ?, ?, ?,
                    NOW(), ?, DATE_ADD(NOW(), INTERVAL 5 DAY),
                    ?, ?, 'payee', 'paye'
                )
            ");
            
            $idClient = $checkout['client_id'] ?? $_SESSION[SESSION_KEY_CLIENT_ID] ?? null;
            $idAdresseLivraison = $adresse['id'] ?? null;
            $idAdresseFacturation = $checkout['adresse_facturation']['id'] ?? $idAdresseLivraison;
            $modeReglement = ($methode === 'paypal') ? 'PayPal' : 'Carte Bancaire';
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
            
            // Ajouter les lignes de commande
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
            
            // Enregistrer le paiement
            $stmt_paiement = $pdo->prepare("
                INSERT INTO Paiement (idCommande, montant, currency, statut, methode_paiement, reference, date_creation)
                VALUES (?, ?, 'EUR', 'payee', ?, ?, NOW())
            ");
            $reference_finale = $reference ?? 'PAY_' . time() . '_' . $commande_id;
            $stmt_paiement->execute([$commande_id, $total_commande, $modeReglement, $reference_finale]);
            
            // Vider le panier
            if (isset($checkout['panier_id'])) {
                $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ?");
                $stmt->execute([$checkout['panier_id']]);
            }
            
            // Nettoyer la session
            $_SESSION[SESSION_KEY_PANIER] = [];
            unset($_SESSION[SESSION_KEY_PANIER_ID]);
            unset($_SESSION[SESSION_KEY_CHECKOUT]);
            
            $pdo->commit();
            
            // Générer la facture PDF et envoyer par email
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
    <title>Paiement - Youki and Co</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            grid-template-columns: 1fr 380px;
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
        h1, h2, h3 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        h1 {
            border-bottom: 3px solid #e74c3c;
            padding-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        h1 i, h2 i, h3 i { color: #e74c3c; }
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
        .step.active .step-number {
            background: #e74c3c;
            color: white;
        }
        .step.completed .step-number {
            background: #27ae60;
            color: white;
        }
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
        .payment-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
        }
        .payment-option {
            border: 2px solid #e0e0e0;
            border-radius: 16px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-option:hover { border-color: #e74c3c; }
        .payment-option.selected {
            border-color: #27ae60;
            background: rgba(39,174,96,0.05);
        }
        .option-header {
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .btn-payer {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s;
            margin-top: 20px;
        }
        .btn-payer:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(39,174,96,0.3);
        }
        .btn-payer:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        .cart-item-mini {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .cart-item-mini:last-child { border-bottom: none; }
        .mini-image {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .mini-details { flex: 1; }
        .mini-details h4 { margin-bottom: 5px; color: #2c3e50; }
        .mini-price { font-weight: 700; color: #e74c3c; }
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
        .message.success {
            background: #d4edda;
            color: #155724;
            border-left-color: #27ae60;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #e74c3c;
        }
        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border-left-color: #3498db;
        }
        @media (max-width: 768px) {
            .payment-main, .payment-sidebar { padding: 25px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Indicateur d'étape -->
        <div class="step-indicator">
            <div class="step completed">
                <div class="step-number">1</div>
                <span class="step-text">Panier</span>
            </div>
            <div class="step completed">
                <div class="step-number">2</div>
                <span class="step-text">Livraison</span>
            </div>
            <div class="step active">
                <div class="step-number">3</div>
                <span class="step-text">Paiement</span>
            </div>
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
            <!-- Section principale -->
            <div class="payment-main">
                <h2><i class="fas fa-credit-card"></i> Mode de paiement</h2>

                <!-- Adresse de livraison -->
                <?php if (!empty($adresse)): ?>
                <div class="adresse-card">
                    <h3 style="margin-bottom: 10px;"><i class="fas fa-map-marker-alt"></i> Adresse de livraison</h3>
                    <p class="adresse-line"><strong><?php echo htmlspecialchars($adresse['prenom'] . ' ' . $adresse['nom']); ?></strong></p>
                    <p class="adresse-line"><?php echo htmlspecialchars($adresse['adresse']); ?></p>
                    <?php if (!empty($adresse['complement'])): ?>
                        <p class="adresse-line"><?php echo htmlspecialchars($adresse['complement']); ?></p>
                    <?php endif; ?>
                    <p class="adresse-line"><?php echo htmlspecialchars($adresse['code_postal'] . ' ' . $adresse['ville']); ?></p>
                    <p class="adresse-line"><?php echo htmlspecialchars($adresse['pays']); ?></p>
                    <p class="adresse-line"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($adresse['email']); ?></p>
                    <?php if (!empty($adresse['telephone'])): ?>
                        <p class="adresse-line"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($adresse['telephone']); ?></p>
                    <?php endif; ?>
                    <a href="livraison_form.php" style="color: #e74c3c; margin-top: 10px; display: inline-block;">
                        <i class="fas fa-edit"></i> Modifier
                    </a>
                </div>
                <?php endif; ?>

                <!-- Options de paiement -->
                <div class="payment-options">
                    <div class="payment-option selected" id="optionPaypal">
                        <div class="option-header">
                            <i class="fab fa-paypal" style="font-size: 28px; color: #003087;"></i>
                            <span>PayPal</span>
                        </div>
                        <p style="margin-top: 10px; color: #7f8c8d; font-size: 0.9rem;">
                            Paiement sécurisé par PayPal - Carte bancaire acceptée
                        </p>
                    </div>
                </div>

                <button id="btnPayer" class="btn-payer">
                    <i class="fas fa-lock"></i> Payer <?php echo number_format($total_commande, 2, ',', ' '); ?> €
                </button>

                <div class="security-badge">
                    <i class="fas fa-shield-alt"></i> Paiement 100% sécurisé - Cryptage SSL
                </div>
            </div>

            <!-- Sidebar - Récapitulatif -->
            <div class="payment-sidebar">
                <h3><i class="fas fa-receipt"></i> Récapitulatif</h3>
                
                <div id="cart-items">
                    <?php foreach ($panier_details as $item): ?>
                    <div class="cart-item-mini">
                        <div class="mini-image">
                            <i class="fas fa-gift" style="font-size: 24px; color: #e74c3c;"></i>
                        </div>
                        <div class="mini-details">
                            <h4><?php echo htmlspecialchars($item['nom']); ?></h4>
                            <div style="display: flex; justify-content: space-between;">
                                <span><?php echo $item['quantite']; ?> x <?php echo number_format($item['prix_unitaire'], 2, ',', ' '); ?> €</span>
                                <span class="mini-price"><?php echo number_format($item['prix_total'], 2, ',', ' '); ?> €</span>
                            </div>
                        </div>
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
                    <i class="fas fa-lock"></i> Transactions sécurisées
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configuration PayPal sandbox pour test
        const PAYPAL_CLIENT_ID = 'Aac1-P0VrxBQ_5REVeo4f557_-p6BDeXA_hyiuVZfi21sILMWccBFfTidQ6nnhQathCbWaCSQaDmxJw5';
        const totalMontant = <?php echo $total_commande; ?>;
        
        document.getElementById('btnPayer').addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création de la commande...';
            btn.disabled = true;
            
            // Créer la commande avant paiement
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    process_payment: true, 
                    methode_paiement: 'paypal',
                    montant_total: totalMontant
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirection vers la page de confirmation (qui simulera PayPal ou redirigera)
                    window.location.href = data.redirect;
                } else {
                    alert('Erreur: ' + (data.message || 'Impossible de créer la commande'));
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur de connexion. Veuillez réessayer.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        });
    </script>
</body>
</html>
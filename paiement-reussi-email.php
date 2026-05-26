<?php
// ============================================
// PAGE DE CONFIRMATION DE PAIEMENT RÉUSSI AVEC ENVOI FACTURE
// Version corrigée - Accessible via ?commande=XXX&token=XXX
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';
require_once __DIR__ . '/smtp_config.php';

// Récupérer les paramètres
$commande_id = isset($_GET['commande']) ? intval($_GET['commande']) : 0;
$token = $_GET['token'] ?? '';

// Si pas de commande_id, rediriger vers l'accueil
if ($commande_id <= 0) {
    header('Location: index.html');
    exit;
}

// Connexion BDD
$pdo = getPDOConnection();

// Variables pour l'affichage
$commande_valide = false;
$email_envoye = false;
$commande = null;
$items = [];
$total = 0;

if ($pdo) {
    try {
        // Récupérer la commande complète
        $stmt = $pdo->prepare("
            SELECT 
                c.idCommande,
                c.dateCommande,
                c.montantTotal,
                c.fraisDePort,
                c.modeReglement,
                c.statut,
                cl.email,
                cl.nom,
                cl.prenom,
                a_liv.adresse as adresse_livraison,
                a_liv.codePostal as cp_livraison,
                a_liv.ville as ville_livraison,
                a_liv.pays as pays_livraison
            FROM Commande c
            JOIN Client cl ON c.idClient = cl.idClient
            JOIN Adresse a_liv ON c.idAdresseLivraison = a_liv.idAdresse
            WHERE c.idCommande = ?
        ");
        $stmt->execute([$commande_id]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($commande) {
            $commande_valide = true;
            
            // Récupérer les articles de la commande
            $stmt_items = $pdo->prepare("
                SELECT 
                    lc.quantite,
                    lc.prixUnitaire,
                    o.nom as produit_nom,
                    o.description
                FROM LigneCommande lc
                JOIN Origami o ON lc.idOrigami = o.idOrigami
                WHERE lc.idCommande = ?
            ");
            $stmt_items->execute([$commande_id]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculer le total
            foreach ($items as $item) {
                $total += $item['quantite'] * $item['prixUnitaire'];
            }
            
            // ============================================
            // ENVOI DE L'EMAIL AVEC FACTURE (si non déjà fait)
            // ============================================
            $email_deja_envoye = false;
            
            // Vérifier si l'email a déjà été envoyé (table logs ou flag en session)
            if (isset($_SESSION['facture_envoyee_' . $commande_id]) && $_SESSION['facture_envoyee_' . $commande_id] === true) {
                $email_deja_envoye = true;
                error_log("Email déjà envoyé pour commande #$commande_id (flag session)");
            }
            
            // Vérifier via la BDD (table paiement ou logs)
            if (!$email_deja_envoye && $pdo) {
                try {
                    $stmt_check = $pdo->prepare("
                        SELECT COUNT(*) as count FROM Paiement 
                        WHERE idCommande = ? AND email_envoye = 1
                    ");
                    $stmt_check->execute([$commande_id]);
                    $check = $stmt_check->fetch();
                    if ($check && $check['count'] > 0) {
                        $email_deja_envoye = true;
                        error_log("Email déjà envoyé pour commande #$commande_id (BDD)");
                    }
                } catch (Exception $e) {
                    // La colonne email_envoye peut ne pas exister
                    error_log("Vérification email_envoye ignorée: " . $e->getMessage());
                }
            }
            
            // Envoyer l'email si pas déjà fait
            if (!$email_deja_envoye) {
                // Inclure la fonction de génération PDF
                if (file_exists('genererFacturePDF.php')) {
                    require_once 'genererFacturePDF.php';
                    
                    // Générer la facture PDF
                    $cheminFacture = genererFacturePDF($pdo, $commande_id);
                    
                    if ($cheminFacture && file_exists($cheminFacture)) {
                        error_log("✅ PDF généré: " . $cheminFacture);
                        
                        // Envoyer l'email avec la facture en pièce jointe
                        $email_envoye = envoyerFactureParEmail($commande['email'], $cheminFacture, $commande_id);
                        
                        if ($email_envoye) {
                            // Marquer comme envoyé en session
                            $_SESSION['facture_envoyee_' . $commande_id] = true;
                            
                            // Optionnel: marquer dans la BDD
                            try {
                                $stmt_update = $pdo->prepare("
                                    UPDATE Paiement SET email_envoye = 1 
                                    WHERE idCommande = ?
                                ");
                                $stmt_update->execute([$commande_id]);
                            } catch (Exception $e) {
                                // Ignorer si colonne n'existe pas
                            }
                            
                            error_log("✅ Email avec facture envoyé pour commande #$commande_id à " . $commande['email']);
                        } else {
                            error_log("❌ Échec envoi email pour commande #$commande_id");
                        }
                    } else {
                        error_log("❌ Échec génération PDF pour commande #$commande_id");
                    }
                } else {
                    error_log("❌ genererFacturePDF.php non trouvé");
                }
            }
        } else {
            error_log("Commande #$commande_id non trouvée");
        }
        
    } catch (Exception $e) {
        error_log("Erreur dans paiement-reussi-email.php: " . $e->getMessage());
    }
}

// Si commande non valide, essayer de rediriger
if (!$commande_valide) {
    try {
        $stmt = $pdo->prepare("SELECT idCommande FROM Commande WHERE idCommande = ?");
        $stmt->execute([$commande_id]);
        if (!$stmt->fetch()) {
            header('Location: index.html');
            exit;
        }
    } catch (Exception $e) {
        header('Location: index.html');
        exit;
    }
}

// ============================================
// VIDER LE PANIER (S'IL EXISTE ENCORE)
// ============================================
unset($_SESSION[SESSION_KEY_PANIER]);
unset($_SESSION[SESSION_KEY_PANIER_ID]);
unset($_SESSION[SESSION_KEY_CHECKOUT]);
unset($_SESSION[SESSION_KEY_COMMANDE]);

// Régénérer l'ID de session pour sécurité
session_regenerate_id(true);

// Calcul de la TVA (taux 20%)
$taux_tva = 20;
$montant_ht = $total / (1 + $taux_tva / 100);
$montant_tva = $total - $montant_ht;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Réussi - Youki and Co</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .confirmation-container {
            max-width: 800px;
            width: 100%;
        }
        .confirmation-card {
            background: white;
            border-radius: 30px;
            padding: 50px 40px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .confirmation-icon {
            font-size: 100px;
            color: #27ae60;
            margin-bottom: 20px;
            text-align: center;
        }
        h1 {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 15px;
            text-align: center;
        }
        .confirmation-message {
            color: #7f8c8d;
            font-size: 1.1rem;
            margin-bottom: 30px;
            text-align: center;
        }
        .email-status {
            margin: 20px 0;
            padding: 15px 20px;
            border-radius: 12px;
            text-align: center;
        }
        .email-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .email-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .email-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .confirmation-details {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 20px;
            margin: 25px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #7f8c8d;
            font-weight: 500;
        }
        .detail-value {
            color: #2c3e50;
            font-weight: 600;
        }
        .detail-value strong {
            color: #27ae60;
        }
        .order-items {
            margin: 20px 0;
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .order-item:last-child {
            border-bottom: none;
        }
        .item-name {
            flex: 2;
        }
        .item-qty {
            flex: 1;
            text-align: center;
        }
        .item-price {
            flex: 1;
            text-align: right;
            font-weight: 600;
        }
        .order-total {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
            font-weight: 800;
            font-size: 1.2rem;
        }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            margin: 10px;
            text-align: center;
        }
        .btn-primary {
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
            border: none;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(39,174,96,0.3);
        }
        .btn-secondary {
            background: #f8f9fa;
            color: #2c3e50;
            border: 2px solid #e0e0e0;
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        .btn-download {
            background: #3498db;
            color: white;
            border: none;
        }
        .btn-download:hover {
            background: #2980b9;
        }
        .confirmation-actions {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }
        .confirmation-info {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
        }
        .confirmation-info p {
            margin: 10px 0;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        .confirmation-info i {
            margin-right: 10px;
            color: #27ae60;
        }
        .print-section {
            text-align: center;
            margin-top: 20px;
        }
        .print-btn {
            background: none;
            border: none;
            color: #7f8c8d;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .print-btn:hover {
            color: #2c3e50;
        }
        @media (max-width: 600px) {
            .confirmation-card { padding: 30px 20px; }
            h1 { font-size: 1.5rem; }
            .btn { padding: 10px 20px; font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-card">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <h1>Paiement Réussi !</h1>
            <p class="confirmation-message">
                Merci pour votre commande. Votre paiement a été traité avec succès.
            </p>
            
            <!-- Status de l'email -->
            <div class="email-status <?php 
                echo $email_envoye ? 'email-success' : 
                    ($commande_valide ? 'email-warning' : 'email-error'); 
            ?>">
                <?php if ($email_envoye): ?>
                    <i class="fas fa-envelope"></i> 
                    Un email de confirmation avec votre facture a été envoyé à <strong><?php echo htmlspecialchars($commande['email']); ?></strong>
                <?php elseif ($commande_valide): ?>
                    <i class="fas fa-exclamation-triangle"></i> 
                    L'email de confirmation n'a pas pu être envoyé automatiquement.
                    <br>
                    <button onclick="renvoyerFactureEmail(<?php echo $commande_id; ?>)" class="btn-download" style="margin-top: 10px; padding: 8px 16px; font-size: 0.9rem;">
                        <i class="fas fa-envelope"></i> Renvoyer la facture par email
                    </button>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle"></i> 
                    Une erreur est survenue. Veuillez contacter le service client.
                <?php endif; ?>
            </div>
            
            <!-- Détails de la commande -->
            <div class="confirmation-details">
                <div class="detail-row">
                    <span class="detail-label">Numéro de commande</span>
                    <span class="detail-value">#<?php echo $commande_id; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date</span>
                    <span class="detail-value"><?php echo date('d/m/Y à H:i', strtotime($commande['dateCommande'] ?? 'now')); ?></span>
                </div>
                <?php if (!empty($token)): ?>
                <div class="detail-row">
                    <span class="detail-label">Référence transaction</span>
                    <span class="detail-value"><?php echo htmlspecialchars(substr($token, 0, 25)) . '...'; ?></span>
                </div>
                <?php endif; ?>
                
                <div class="detail-row">
                    <span class="detail-label">Mode de paiement</span>
                    <span class="detail-value"><?php echo htmlspecialchars($commande['modeReglement'] ?? 'PayPal'); ?></span>
                </div>
                
                <!-- Articles commandés -->
                <?php if (!empty($items)): ?>
                <div style="margin-top: 20px;">
                    <h3 style="margin-bottom: 15px; color: #2c3e50; font-size: 1.1rem;">
                        <i class="fas fa-box"></i> Détail de votre commande
                    </h3>
                    <?php foreach ($items as $item): ?>
                    <div class="order-item">
                        <span class="item-name"><?php echo htmlspecialchars($item['produit_nom']); ?></span>
                        <span class="item-qty">x<?php echo $item['quantite']; ?></span>
                        <span class="item-price"><?php echo number_format($item['quantite'] * $item['prixUnitaire'], 2, ',', ' '); ?> €</span>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="order-item" style="margin-top: 10px;">
                        <span class="item-name">Frais de livraison</span>
                        <span class="item-qty"></span>
                        <span class="item-price"><?php echo number_format($commande['fraisDePort'] ?? 0, 2, ',', ' '); ?> €</span>
                    </div>
                    
                    <div class="order-total">
                        <span>Total TTC</span>
                        <span><strong><?php echo number_format($total, 2, ',', ' '); ?> €</strong></span>
                    </div>
                    
                    <div style="margin-top: 10px; font-size: 0.85rem; color: #7f8c8d; text-align: right;">
                        <small>dont TVA (20%) : <?php echo number_format($montant_tva, 2, ',', ' '); ?> €</small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Adresse de livraison -->
            <?php if (!empty($commande['adresse_livraison'])): ?>
            <div class="confirmation-details" style="margin-top: 0;">
                <h3 style="margin-bottom: 15px; color: #2c3e50; font-size: 1rem;">
                    <i class="fas fa-truck"></i> Adresse de livraison
                </h3>
                <p><?php echo htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']); ?><br>
                <?php echo htmlspecialchars($commande['adresse_livraison']); ?><br>
                <?php echo htmlspecialchars($commande['cp_livraison'] . ' ' . $commande['ville_livraison']); ?><br>
                <?php echo htmlspecialchars($commande['pays_livraison'] ?? 'France'); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Actions -->
            <div class="confirmation-actions">
                <a href="index.html" class="btn btn-primary">
                    <i class="fas fa-home"></i> Retour à l'accueil
                </a>
                <button onclick="telechargerFacture(<?php echo $commande_id; ?>)" class="btn btn-download">
                    <i class="fas fa-download"></i> Télécharger ma facture
                </button>
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Imprimer
                </button>
            </div>
            
            <div class="print-section">
                <button onclick="window.print()" class="print-btn">
                    <i class="fas fa-print"></i> Imprimer cette page
                </button>
            </div>
            
            <div class="confirmation-info">
                <p><i class="fas fa-truck"></i> Livraison estimée : 3-5 jours ouvrés</p>
                <p><i class="fas fa-headset"></i> Questions ? Contactez-nous : <a href="mailto:contact@youkiandco.fr">contact@youkiandco.fr</a></p>
                <p><i class="fas fa-shield-alt"></i> Paiement sécurisé - Vos données sont protégées</p>
            </div>
        </div>
    </div>
    
    <script>
        /**
         * Télécharge la facture PDF
         */
        function telechargerFacture(idCommande) {
            window.open('acheter.php?action=telecharger_facture&id_commande=' + idCommande, '_blank');
        }
        
        /**
         * Renvoie la facture par email
         */
        function renvoyerFactureEmail(idCommande) {
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
            btn.disabled = true;
            
            fetch('acheter.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'envoyer_facture_email',
                    id_commande: idCommande,
                    email: '<?php echo addslashes($commande['email'] ?? ''); ?>',
                    format: 'pdf'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 200) {
                    const statusDiv = document.querySelector('.email-status');
                    statusDiv.className = 'email-status email-success';
                    statusDiv.innerHTML = '<i class="fas fa-envelope"></i> La facture a été renvoyée par email à <strong><?php echo addslashes($commande['email'] ?? ''); ?></strong>';
                } else {
                    alert('Erreur: ' + (data.error || 'Impossible d\'envoyer la facture'));
                }
                btn.innerHTML = originalText;
                btn.disabled = false;
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur de connexion. Veuillez réessayer.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        // Vider le panier côté client
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem('panier');
            localStorage.removeItem('cart');
        }
        
        // Forcer la mise à jour du compteur panier sur toutes les pages
        setTimeout(() => {
            document.querySelectorAll('.cart-count, #compteur-panier').forEach(el => {
                el.textContent = '0';
                if (el.style) el.style.display = 'none';
            });
        }, 500);
        
        // Enregistrer l'événement de conversion (si Google Analytics est utilisé)
        setTimeout(() => {
            if (typeof gtag === 'function') {
                gtag('event', 'purchase', {
                    transaction_id: '<?php echo $commande_id; ?>',
                    value: <?php echo $total; ?>,
                    currency: 'EUR',
                    items: <?php 
                        $items_ga = [];
                        foreach ($items as $item) {
                            $items_ga[] = [
                                'id' => $item['produit_nom'],
                                'name' => $item['produit_nom'],
                                'quantity' => $item['quantite'],
                                'price' => $item['prixUnitaire']
                            ];
                        }
                        echo json_encode($items_ga);
                    ?>
                });
            }
        }, 1000);
    </script>
</body>
</html>
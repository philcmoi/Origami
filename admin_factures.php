<?php
// admin_factures.php - Version responsive avec scroll horizontal
require_once 'admin_protection.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once 'smtp_config.php';
require_once 'config.php';

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur connexion: " . $e->getMessage());
}

function genererFactureHTML($pdo, $idCommande) {
    $stmt = $pdo->prepare("
        SELECT 
            c.idCommande,
            c.dateCommande,
            c.montantTotal,
            c.fraisDePort,
            c.statut,
            cl.nom,
            cl.prenom,
            cl.email,
            cl.telephone,
            a_liv.adresse as adresse_livraison,
            a_liv.codePostal as cp_livraison,
            a_liv.ville as ville_livraison,
            a_liv.pays as pays_livraison
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        JOIN Adresse a_liv ON c.idAdresseLivraison = a_liv.idAdresse
        WHERE c.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) return "<h1>Commande non trouvée</h1>";
    
    $stmt = $pdo->prepare("
        SELECT lc.quantite, lc.prixUnitaire, 
               (lc.quantite * lc.prixUnitaire) as total_ligne,
               o.nom as produit_nom
        FROM LigneCommande lc
        JOIN Origami o ON lc.idOrigami = o.idOrigami
        WHERE lc.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $sousTotal = 0;
    foreach ($articles as $article) {
        $sousTotal += $article['total_ligne'];
    }
    $totalGeneral = $sousTotal + ($commande['fraisDePort'] ?? 0);
    $nomComplet = trim($commande['prenom'] . ' ' . $commande['nom']);
    
    $html = '<!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"><title>Facture #' . $commande['idCommande'] . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .invoice-box { max-width: 800px; margin: auto; background: white; border-radius: 8px; padding: 30px; }
        .header { text-align: center; border-bottom: 2px solid #d40000; padding-bottom: 20px; margin-bottom: 20px; }
        .company { font-size: 24px; font-weight: bold; color: #d40000; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #d40000; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #eee; }
        .total { text-align: right; font-size: 18px; font-weight: bold; color: #d40000; margin-top: 20px; }
        .footer { text-align: center; font-size: 11px; color: #666; margin-top: 30px; }
    </style>
    </head>
    <body>
        <div class="invoice-box">
            <div class="header">
                <div class="company">YOUKI & CO</div>
                <div>Créations artisanales japonaises</div>
                <h2>FACTURE N° ' . $commande['idCommande'] . '</h2>
                <div>Date: ' . date('d/m/Y', strtotime($commande['dateCommande'])) . '</div>
            </div>
            <div><strong>Client:</strong> ' . htmlspecialchars($nomComplet) . '<br>
            <strong>Email:</strong> ' . htmlspecialchars($commande['email']) . '</div>
            <table>
                <thead><tr><th>Produit</th><th>Prix</th><th>Qté</th><th>Total</th></tr></thead>
                <tbody>';
    foreach ($articles as $article) {
        $html .= '<tr><td>' . htmlspecialchars($article['produit_nom']) . '</td>
                  <td>' . number_format($article['prixUnitaire'], 2, ',', ' ') . ' €</td>
                  <td>' . $article['quantite'] . '</td>
                  <td>' . number_format($article['total_ligne'], 2, ',', ' ') . ' €</td></tr>';
    }
    $html .= '</tbody>
            </table>
            <div class="total">TOTAL: ' . number_format($totalGeneral, 2, ',', ' ') . ' €</div>
            <div class="footer">Exonération de TVA - Article 293 B du CGI</div>
        </div>
    </body>
    </html>';
    return $html;
}

function envoyerEmailFacture($destinataire, $sujet, $message, $factureHTML = '') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($destinataire);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        
        $fullMessage = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif;">
            <div style="max-width: 600px; margin: 0 auto;">
                <div style="background: #d40000; color: white; padding: 20px; text-align: center;">
                    <h1 style="margin: 0;">YOUKI & CO</h1>
                </div>
                <div style="padding: 20px;">
                    ' . $message . '
                    <hr style="margin: 20px 0;">
                    ' . $factureHTML . '
                </div>
                <div style="background: #f0f0f0; padding: 15px; text-align: center; font-size: 12px;">
                    Youki and Co - contact@youkiandco.fr
                </div>
            </div>
        </body>
        </html>';
        
        $mail->Body = $fullMessage;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

// Traitement POST pour l'envoi de facture
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'envoyer_facture') {
    $idCommande = $_POST['id_commande'] ?? null;
    $email = $_POST['email'] ?? '';
    
    if ($idCommande && $email) {
        $stmt = $pdo->prepare("SELECT statut FROM Commande WHERE idCommande = ?");
        $stmt->execute([$idCommande]);
        $commandeCheck = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($commandeCheck && $commandeCheck['statut'] === 'payee') {
            $factureHTML = genererFactureHTML($pdo, $idCommande);
            $sujet = "Votre facture Youki and Co - Commande #$idCommande";
            $message = "<p>Bonjour,</p><p>Merci pour votre commande. Veuillez trouver ci-dessous votre facture.</p>";
            $resultat = envoyerEmailFacture($email, $sujet, $message, $factureHTML);
            
            if ($resultat['success']) {
                $_SESSION['message_success'] = "✅ Facture #$idCommande envoyée avec succès à $email";
            } else {
                $_SESSION['message_error'] = "❌ Erreur: " . $resultat['error'];
            }
        } else {
            $_SESSION['message_error'] = "❌ La commande #$idCommande n'est pas payée";
        }
    }
    header('Location: admin_factures.php');
    exit;
}

// Action GET: Afficher la facture
if (isset($_GET['action']) && $_GET['action'] === 'generer' && isset($_GET['id'])) {
    echo genererFactureHTML($pdo, (int)$_GET['id']);
    exit;
}

// Récupérer les commandes
$stmt = $pdo->prepare("
    SELECT 
        c.idCommande, c.dateCommande, c.montantTotal, c.statut,
        cl.nom, cl.prenom, cl.email,
        (SELECT COUNT(*) FROM LigneCommande WHERE idCommande = c.idCommande) as nb_articles
    FROM Commande c
    JOIN Client cl ON c.idClient = cl.idClient
    ORDER BY c.dateCommande DESC
");
$stmt->execute();
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$totalCommandes = count($commandes);
$totalPayees = count(array_filter($commandes, function($c) { return $c['statut'] === 'payee'; }));
$caTotal = array_sum(array_column($commandes, 'montantTotal'));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Factures - Youki and Co</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f5f7fb;
            color: #1a1a2e;
        }
        
        /* Header */
        .header {
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }
        
        .logo h1 {
            color: #d40000;
            font-size: 1.5rem;
        }
        
        @media (max-width: 640px) {
            .logo h1 { font-size: 1.2rem; }
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn-logout {
            background: #d40000;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .btn-logout:hover {
            background: #b30000;
        }
        
        /* Container */
        .container {
            display: flex;
            flex-wrap: wrap;
            min-height: calc(100vh - 80px);
        }
        
        /* Sidebar responsive */
        .sidebar {
            width: 260px;
            background: white;
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                box-shadow: none;
                border-bottom: 1px solid #e0e0e0;
                padding: 12px 20px;
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
                display: flex;
                gap: 8px;
            }
            .sidebar .nav-item {
                display: inline-block;
                margin-bottom: 0;
            }
        }
        
        .nav-item {
            display: block;
            padding: 12px 16px;
            color: #555;
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 6px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .nav-item {
                display: inline-block;
                margin-bottom: 0;
            }
        }
        
        .nav-item:hover, .nav-item.active {
            background: #d40000;
            color: white;
        }
        
        /* Main content */
        .main-content {
            flex: 1;
            padding: 25px;
            min-width: 0;
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 15px; }
        }
        
        /* Messages */
        .message-success {
            background: #d4edda;
            color: #155724;
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }
        
        .message-error {
            background: #fef3f2;
            color: #dc2626;
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }
        
        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 560px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }
        
        .stat-card {
            background: white;
            padding: 18px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
        }
        
        .stat-card h3 {
            font-size: 0.7rem;
            color: #888;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-card .number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #d40000;
        }
        
        @media (max-width: 480px) {
            .stat-card .number { font-size: 1.5rem; }
        }
        
        /* Section */
        .section {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px 20px 0 20px;
        }
        
        .section-header h1 {
            font-size: 1.3rem;
            color: #d40000;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Table container avec SCROLL HORIZONTAL */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            margin: 0;
            padding: 0;
        }
        
        .table-container::-webkit-scrollbar {
            height: 6px;
        }
        
        .table-container::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 10px;
        }
        
        .table-container::-webkit-scrollbar-thumb {
            background: #d40000;
            border-radius: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.8rem;
            color: #555;
        }
        
        td {
            font-size: 0.85rem;
        }
        
        /* Status badges */
        .statut {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .statut-payee { background: #d4edda; color: #155724; }
        .statut-en_attente_paiement { background: #fff3cd; color: #856404; }
        .statut-expediee { background: #cce5ff; color: #004085; }
        .statut-livree { background: #d1ecf1; color: #0c5460; }
        .statut-annulee { background: #f8d7da; color: #721c24; }
        
        /* Buttons */
        .btn {
            padding: 6px 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        
        .btn-disabled {
            background: #adb5bd;
            color: white;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .actions-cell {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }
        
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 180px;
            background: #1a1a2e;
            color: #fff;
            text-align: center;
            border-radius: 8px;
            padding: 8px;
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.7rem;
            z-index: 100;
            white-space: normal;
        }
        
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        /* Version mobile - cartes (alternative au tableau scrollable) */
        @media (max-width: 640px) {
            .desktop-table {
                display: block;
            }
            
            /* Optionnel: pour ceux qui préfèrent les cartes, on peut les afficher à la place */
            .alternative-cards {
                display: none;
            }
            
            /* Ajustements pour les petits écrans */
            th, td {
                padding: 10px 8px;
                font-size: 0.75rem;
            }
            
            .btn {
                padding: 5px 10px;
                font-size: 0.7rem;
            }
        }
        
        /* Pour les très petits écrans, on peut afficher un message indiquant le scroll */
        .scroll-hint {
            display: none;
            text-align: center;
            padding: 10px;
            background: #f0f0f0;
            font-size: 0.7rem;
            color: #666;
        }
        
        @media (max-width: 640px) {
            .scroll-hint {
                display: block;
            }
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 0.7rem;
            color: #888;
            border-top: 1px solid #e0e0e0;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>📄 Youki and Co - Factures</h1>
        </div>
        <div class="admin-info">
            <span>👋 <?= htmlspecialchars($_SESSION['admin_email'] ?? 'Admin') ?></span>
            <a href="admin_logout.php" class="btn-logout">Déconnexion</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="nav-item">📊 Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">📦 Commandes</a>
            <a href="admin_factures.php" class="nav-item active">📄 Factures</a>
            <a href="admin_clients.php" class="nav-item">👥 Clients</a>
            <a href="admin_produits.php" class="nav-item">🎨 Produits</a>
        </div>
        
        <div class="main-content">
            <?php if (isset($_SESSION['message_success'])): ?>
                <div class="message-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $_SESSION['message_success'] ?></span>
                </div>
                <?php unset($_SESSION['message_success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['message_error'])): ?>
                <div class="message-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= $_SESSION['message_error'] ?></span>
                </div>
                <?php unset($_SESSION['message_error']); ?>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>📋 Total Factures</h3>
                    <div class="number"><?= $totalCommandes ?></div>
                </div>
                <div class="stat-card">
                    <h3>✅ Factures Payées</h3>
                    <div class="number"><?= $totalPayees ?></div>
                </div>
                <div class="stat-card">
                    <h3>💰 Chiffre d'Affaires</h3>
                    <div class="number"><?= number_format($caTotal, 0, ',', ' ') ?>€</div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-header">
                    <h1><i class="fas fa-file-invoice"></i> Liste des factures</h1>
                </div>
                
                <!-- Indicateur de scroll horizontal pour mobile -->
                <div class="scroll-hint">
                    <i class="fas fa-arrows-alt-h"></i> Faites glisser pour voir plus de colonnes →
                </div>
                
                <!-- Tableau avec scroll horizontal -->
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>N° Commande</th>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Email</th>
                                <th>Articles</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($commandes)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-inbox" style="font-size: 2rem; opacity: 0.5;"></i>
                                        <p>Aucune commande trouvée</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($commandes as $commande): ?>
                                <tr>
                                    <td><strong>#<?= $commande['idCommande'] ?></strong></td>
                                    <td><?= date('d/m/Y', strtotime($commande['dateCommande'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></strong>
                                    </td>
                                    <td style="font-size: 0.8rem; word-break: break-all;">
                                        <?= htmlspecialchars($commande['email']) ?>
                                    </td>
                                    <td class="text-center"><?= $commande['nb_articles'] ?> article(s)</td>
                                    <td>
                                        <strong><?= number_format($commande['montantTotal'], 2, ',', ' ') ?> €</strong>
                                    </td>
                                    <td>
                                        <span class="statut statut-<?= $commande['statut'] ?>">
                                            <?php 
                                            $statuts = [
                                                'payee' => 'Payée',
                                                'en_attente_paiement' => 'En attente',
                                                'expediee' => 'Expédiée',
                                                'livree' => 'Livrée',
                                                'annulee' => 'Annulée'
                                            ];
                                            echo $statuts[$commande['statut']] ?? $commande['statut'];
                                            ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <?php if ($commande['statut'] === 'payee'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="id_commande" value="<?= $commande['idCommande'] ?>">
                                                <input type="hidden" name="email" value="<?= htmlspecialchars($commande['email']) ?>">
                                                <input type="hidden" name="action" value="envoyer_facture">
                                                <button type="submit" class="btn btn-success" 
                                                        onclick="return confirm('Envoyer la facture #<?= $commande['idCommande'] ?> à <?= htmlspecialchars($commande['email']) ?> ?')">
                                                    <i class="fas fa-envelope"></i> Envoyer
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="tooltip">
                                                <button class="btn btn-disabled" disabled>
                                                    <i class="fas fa-envelope"></i> Envoyer
                                                </button>
                                                <span class="tooltiptext">La facture ne peut être envoyée que pour les commandes payées</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <a href="admin_factures.php?action=generer&id=<?= $commande['idCommande'] ?>" 
                                           class="btn btn-primary" target="_blank">
                                            <i class="fas fa-eye"></i> Voir
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; <?= date('Y') ?> Youki and Co - Créations artisanales japonaises</p>
    </div>
</body>
</html>
<?php
session_start();

require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: admin_clients.php');
    exit;
}

$client_id = intval($_GET['id']);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les informations du client
    $stmt_client = $pdo->prepare("SELECT * FROM Client WHERE idClient = ?");
    $stmt_client->execute([$client_id]);
    $client = $stmt_client->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        die("Client non trouvé");
    }

    // Récupérer les adresses
    $stmt_adresses = $pdo->prepare("
        SELECT * FROM Adresse 
        WHERE idClient = ?
        ORDER BY type, dateCreation DESC
    ");
    $stmt_adresses->execute([$client_id]);
    $adresses = $stmt_adresses->fetchAll(PDO::FETCH_ASSOC);

    $adresse_livraison = null;
    $adresse_facturation = null;
    
    foreach ($adresses as $adresse) {
        if ($adresse['type'] == 'livraison') {
            $adresse_livraison = $adresse;
        } elseif ($adresse['type'] == 'facturation') {
            $adresse_facturation = $adresse;
        }
    }

    // Récupérer les commandes
    $stmt_commandes = $pdo->prepare("
        SELECT c.*, COUNT(lc.idLigneCommande) as nb_articles
        FROM Commande c
        LEFT JOIN LigneCommande lc ON c.idCommande = lc.idCommande
        WHERE c.idClient = ?
        GROUP BY c.idCommande
        ORDER BY c.dateCommande DESC
    ");
    $stmt_commandes->execute([$client_id]);
    $commandes = $stmt_commandes->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques
    $stmt_stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_commandes,
            SUM(montantTotal) as total_depense,
            AVG(montantTotal) as moyenne_commande,
            MIN(dateCommande) as premiere_commande,
            MAX(dateCommande) as derniere_commande
        FROM Commande 
        WHERE idClient = ?
    ");
    $stmt_stats->execute([$client_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Client #<?= $client_id ?> - Youki and Co</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary: #c0392b;
            --primary-dark: #a93226;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --success: #27ae60;
            --warning: #f39c12;
            --border-radius: 12px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
            color: var(--gray-800);
        }

        .app { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--gray-200);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            overflow-y: auto;
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; z-index: 100; }
            .sidebar.open { transform: translateX(0); }
        }
        
        .sidebar-header { padding: 20px; border-bottom: 1px solid var(--gray-200); }
        .sidebar-header h2 { font-size: 1.25rem; font-weight: 700; color: var(--primary); }
        .sidebar-header p { font-size: 0.7rem; color: var(--gray-500); margin-top: 4px; }
        
        .nav-menu { padding: 16px 12px; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            color: var(--gray-700);
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 4px;
            transition: all 0.2s;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .nav-item i { width: 20px; color: var(--gray-500); }
        .nav-item:hover { background: var(--gray-100); color: var(--primary); }
        .nav-item.active { background: var(--primary); color: white; }
        .nav-item.active i { color: white; }
        
        .main-content {
            flex: 1;
            margin-left: 260px;
            min-height: 100vh;
        }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        .top-bar {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 40;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 8px;
        }
        @media (max-width: 768px) { .menu-toggle { display: block; } }
        
        .page-title h1 { font-size: 1.25rem; font-weight: 600; }
        .breadcrumb {
            font-size: 0.7rem;
            color: var(--gray-500);
            margin-top: 4px;
        }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        
        .user-info { display: flex; align-items: center; gap: 16px; }
        .user-email { font-size: 0.8rem; color: var(--gray-600); }
        .btn-logout {
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-logout:hover { background: var(--gray-200); color: var(--primary); }
        
        .content-wrapper { padding: 24px; }
        @media (max-width: 640px) { .content-wrapper { padding: 16px; } }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--gray-100);
            color: var(--gray-700);
            text-decoration: none;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-bottom: 20px;
        }
        
        .btn-back:hover { background: var(--gray-200); }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 16px;
            border: 1px solid var(--gray-200);
            text-align: center;
        }
        
        .stat-number { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .stat-label { font-size: 0.65rem; color: var(--gray-500); text-transform: uppercase; }
        
        .section {
            background: white;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .section-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .section-header h2 {
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-header h2 i { color: var(--primary); }
        
        .section-body { padding: 20px; }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-group { margin-bottom: 0; }
        .info-label {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--gray-500);
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-800);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-permanent { background: #d4edda; color: #155724; }
        .badge-temporaire { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        
        .addresses-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .addresses-comparison { grid-template-columns: 1fr; gap: 16px; }
        }
        
        .address-column {
            background: var(--gray-100);
            padding: 16px;
            border-radius: 12px;
            border-left: 3px solid var(--primary);
        }
        
        .address-title {
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }
        
        .address-content {
            font-size: 0.8rem;
            color: var(--gray-700);
            line-height: 1.5;
        }
        
        .same-address-notice {
            margin-top: 16px;
            padding: 10px 16px;
            background: #d4edda;
            color: #155724;
            border-radius: 10px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-wrapper { overflow-x: auto; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 550px;
        }
        
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        
        th {
            background: var(--gray-100);
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--gray-600);
        }
        
        td { font-size: 0.8rem; }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .status-en_attente_paiement, .status-en_attente { background: #fff3cd; color: #856404; }
        .status-payee { background: #d4edda; color: #155724; }
        .status-expediee { background: #d1ecf1; color: #0c5460; }
        .status-livree { background: #fef2f2; color: var(--primary); }
        .status-annulee { background: var(--gray-200); color: var(--gray-600); }
        
        .btn-action {
            padding: 5px 12px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-action:hover { background: var(--primary-dark); }
        
        @media (max-width: 640px) {
            .desktop-table { display: none; }
            .mobile-orders { display: flex; flex-direction: column; gap: 12px; }
            .order-card {
                background: var(--gray-100);
                border-radius: 12px;
                padding: 16px;
                border-left: 3px solid var(--primary);
            }
            .order-card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 8px;
                margin-bottom: 12px;
                padding-bottom: 10px;
                border-bottom: 1px solid var(--gray-300);
            }
            .order-id { font-weight: 700; font-size: 0.9rem; }
            .order-date { font-size: 0.7rem; color: var(--gray-500); }
            .order-details {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
                margin: 12px 0;
                font-size: 0.8rem;
            }
            .order-amount { font-weight: 700; color: var(--primary); }
        }
        
        @media (min-width: 641px) { .mobile-orders { display: none; } }
        
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 0.7rem;
            color: var(--gray-500);
            border-top: 1px solid var(--gray-200);
            margin-top: 24px;
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 90;
        }
        .sidebar-overlay.active { display: block; }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Youki & Co</h2>
                <p>Administration</p>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item"><i class="fas fa-chart-pie"></i> Tableau de bord</a>
                <a href="admin_commandes.php" class="nav-item"><i class="fas fa-shopping-cart"></i> Commandes</a>
                <a href="admin_factures.php" class="nav-item"><i class="fas fa-file-invoice"></i> Factures</a>
                <a href="admin_clients.php" class="nav-item active"><i class="fas fa-users"></i> Clients</a>
                <a href="admin_produits.php" class="nav-item"><i class="fas fa-box"></i> Produits</a>
            </nav>
        </aside>
        
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <main class="main-content">
            <div class="top-bar">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <div class="page-title">
                    <h1>Détail du client</h1>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Dashboard</a> &gt;
                        <a href="admin_clients.php">Clients</a> &gt;
                        Client #<?= $client_id ?>
                    </div>
                </div>
                <div class="user-info">
                    <span class="user-email"><?= htmlspecialchars($_SESSION['admin_email']) ?></span>
                    <a href="dashboard.php?logout=1" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                </div>
            </div>
            
            <div class="content-wrapper">
                <a href="admin_clients.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
                
                <!-- Statistiques -->
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-number"><?= $stats['total_commandes'] ?? 0 ?></div><div class="stat-label">Commandes</div></div>
                    <div class="stat-card"><div class="stat-number"><?= number_format($stats['total_depense'] ?? 0, 2, ',', ' ') ?>€</div><div class="stat-label">Total dépensé</div></div>
                    <div class="stat-card"><div class="stat-number"><?= number_format($stats['moyenne_commande'] ?? 0, 2, ',', ' ') ?>€</div><div class="stat-label">Moyenne/commande</div></div>
                    <div class="stat-card"><div class="stat-number" style="font-size: 0.9rem;"><?= $stats['premiere_commande'] ? date('d/m/Y', strtotime($stats['premiere_commande'])) : '-' ?></div><div class="stat-label">1ère commande</div></div>
                </div>
                
                <!-- Informations personnelles -->
                <div class="section">
                    <div class="section-header">
                        <h2><i class="fas fa-user"></i> Informations personnelles</h2>
                    </div>
                    <div class="section-body">
                        <div class="info-grid">
                            <div><div class="info-label">ID Client</div><div class="info-value">#<?= $client_id ?></div></div>
                            <div><div class="info-label">Nom complet</div><div class="info-value"><?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?></div></div>
                            <div><div class="info-label">Email</div><div class="info-value"><?= htmlspecialchars($client['email']) ?> <?= $client['email_confirme'] ? '<span class="badge badge-confirmed">✓ Confirmé</span>' : '<span class="badge badge-pending">✗ Non confirmé</span>' ?></div></div>
                            <div><div class="info-label">Téléphone</div><div class="info-value"><?= htmlspecialchars($client['telephone'] ?? 'Non renseigné') ?></div></div>
                            <div><div class="info-label">Type de compte</div><div class="info-value"><span class="badge badge-<?= ($client['type'] ?? 'temporaire') == 'permanent' ? 'permanent' : 'temporaire' ?>"><?= $client['type'] ?? 'temporaire' ?></span></div></div>
                            <div><div class="info-label">Date d'inscription</div><div class="info-value"><?= date('d/m/Y à H:i', strtotime($client['date_creation'])) ?></div></div>
                        </div>
                        <div style="margin-top: 20px;">
                            <a href="admin_client_edit.php?id=<?= $client_id ?>" class="btn-action"><i class="fas fa-edit"></i> Modifier les informations</a>
                        </div>
                    </div>
                </div>
                
                <!-- Adresses -->
                <div class="section">
                    <div class="section-header">
                        <h2><i class="fas fa-map-marker-alt"></i> Adresses</h2>
                    </div>
                    <div class="section-body">
                        <?php if (count($adresses) > 0): ?>
                            <div class="addresses-comparison">
                                <div class="address-column">
                                    <div class="address-title"><i class="fas fa-truck"></i> Adresse de livraison</div>
                                    <?php if ($adresse_livraison): ?>
                                        <div class="address-content">
                                            <strong><?= htmlspecialchars($adresse_livraison['prenom'] . ' ' . $adresse_livraison['nom']) ?></strong><br>
                                            <?= nl2br(htmlspecialchars($adresse_livraison['adresse'])) ?><br>
                                            <?= htmlspecialchars($adresse_livraison['codePostal'] . ' ' . $adresse_livraison['ville']) ?><br>
                                            <?= htmlspecialchars($adresse_livraison['pays']) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="address-content" style="color: var(--gray-500);">Aucune adresse de livraison</div>
                                    <?php endif; ?>
                                </div>
                                <div class="address-column">
                                    <div class="address-title"><i class="fas fa-file-invoice"></i> Adresse de facturation</div>
                                    <?php if ($adresse_facturation): ?>
                                        <div class="address-content">
                                            <strong><?= htmlspecialchars($adresse_facturation['prenom'] . ' ' . $adresse_facturation['nom']) ?></strong><br>
                                            <?= nl2br(htmlspecialchars($adresse_facturation['adresse'])) ?><br>
                                            <?= htmlspecialchars($adresse_facturation['codePostal'] . ' ' . $adresse_facturation['ville']) ?><br>
                                            <?= htmlspecialchars($adresse_facturation['pays']) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="address-content" style="color: var(--gray-500);">Aucune adresse de facturation</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($adresse_livraison && $adresse_facturation && $adresse_livraison['idAdresse'] == $adresse_facturation['idAdresse']): ?>
                                <div class="same-address-notice">
                                    <i class="fas fa-info-circle"></i> Les adresses de livraison et facturation sont identiques
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 30px; color: var(--gray-500);">
                                <i class="fas fa-map-marker-alt" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                                <p>Aucune adresse enregistrée</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Historique des commandes -->
                <div class="section">
                    <div class="section-header">
                        <h2><i class="fas fa-history"></i> Historique des commandes</h2>
                    </div>
                    <div class="section-body">
                        <?php if (count($commandes) > 0): ?>
                            <div class="desktop-table table-wrapper">
                                <table>
                                    <thead><tr><th>N°</th><th>Date</th><th>Articles</th><th>Montant</th><th>Statut</th><th></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($commandes as $commande): ?>
                                        <tr>
                                            <td>#<?= $commande['idCommande'] ?></td>
                                            <td><?= date('d/m/Y', strtotime($commande['dateCommande'])) ?></td>
                                            <td><?= $commande['nb_articles'] ?> article(s)</td>
                                            <td><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</td>
                                            <td><span class="status-badge status-<?= $commande['statut'] ?>"><?= $commande['statut'] ?></span></td>
                                            <td><a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-action"><i class="fas fa-eye"></i> Voir</a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mobile-orders">
                                <?php foreach ($commandes as $commande): ?>
                                <div class="order-card">
                                    <div class="order-card-header">
                                        <span class="order-id">#<?= $commande['idCommande'] ?></span>
                                        <span class="order-date"><?= date('d/m/Y', strtotime($commande['dateCommande'])) ?></span>
                                        <span class="status-badge status-<?= $commande['statut'] ?>"><?= $commande['statut'] ?></span>
                                    </div>
                                    <div class="order-details">
                                        <span>📦 <?= $commande['nb_articles'] ?> article(s)</span>
                                        <span class="order-amount">💰 <?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</span>
                                    </div>
                                    <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-action" style="display: inline-flex; justify-content: center; width: 100%;"><i class="fas fa-eye"></i> Voir détails</a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: var(--gray-500);">
                                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 12px; opacity: 0.5;"></i>
                                <p>Aucune commande pour ce client</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="footer">
                <p>&copy; <?= date('Y') ?> Youki and Co - Créations artisanales japonaises</p>
            </div>
        </main>
    </div>
    
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        menuToggle?.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });
        overlay?.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    </script>
</body>
</html>
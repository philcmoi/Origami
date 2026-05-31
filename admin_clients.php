<?php
require_once 'admin_protection.php';
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Recherche
    $search = $_GET['search'] ?? '';
    $conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $conditions[] = "(c.nom LIKE ? OR c.prenom LIKE ? OR c.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    $whereClause .= $whereClause ? " AND c.type = 'permanent'" : "WHERE c.type = 'permanent'";
    
    $stmt = $pdo->prepare("
        SELECT c.idClient, c.email, c.nom, c.prenom, c.telephone, c.date_creation, c.type, c.email_confirme,
               COUNT(cmd.idCommande) as nb_commandes,
               COALESCE(SUM(cmd.montantTotal), 0) as total_achats
        FROM Client c
        LEFT JOIN Commande cmd ON c.idClient = cmd.idClient
        $whereClause
        GROUP BY c.idClient
        ORDER BY c.date_creation DESC
    ");
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalClients = count($clients);
    $totalCommandes = array_sum(array_column($clients, 'nb_commandes'));
    $caTotal = array_sum(array_column($clients, 'total_achats'));
    
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Clients - Youki and Co</title>
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
        .page-title p { font-size: 0.75rem; color: var(--gray-500); margin-top: 2px; }
        
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
        .stat-label { font-size: 0.7rem; color: var(--gray-500); text-transform: uppercase; }
        
        .filters-bar {
            background: white;
            border-radius: var(--border-radius);
            padding: 16px 20px;
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
        }
        
        .search-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid var(--gray-300);
            border-radius: 10px;
            font-size: 0.85rem;
            font-family: inherit;
        }
        
        .search-input:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .btn-search, .btn-reset {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
        }
        
        .btn-search {
            background: var(--primary);
            color: white;
        }
        
        .btn-reset {
            background: var(--gray-100);
            color: var(--gray-700);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .section {
            background: white;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .section-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .section-header h2 {
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-wrapper { overflow-x: auto; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
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
        
        .btn-small {
            padding: 5px 12px;
            background: var(--gray-100);
            color: var(--gray-700);
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-small:hover { background: var(--gray-200); }
        
        .actions-cell { display: flex; gap: 8px; flex-wrap: wrap; }
        
        @media (max-width: 640px) {
            .desktop-table { display: none; }
            .mobile-cards { display: flex; flex-direction: column; gap: 12px; padding: 16px; }
            .client-card {
                background: var(--gray-100);
                border-radius: 12px;
                padding: 16px;
                border-left: 3px solid var(--primary);
            }
            .client-name { font-weight: 700; font-size: 0.95rem; margin-bottom: 4px; }
            .client-email { font-size: 0.7rem; color: var(--gray-600); word-break: break-all; margin-bottom: 8px; }
            .client-info {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                font-size: 0.7rem;
                color: var(--gray-500);
                margin-bottom: 12px;
            }
            .client-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        }
        
        @media (min-width: 641px) { .mobile-cards { display: none; } }
        
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
                    <h1>Gestion des clients</h1>
                    <p><?= $totalClients ?> client(s) inscrit(s)</p>
                </div>
                <div class="user-info">
                    <span class="user-email"><?= htmlspecialchars($_SESSION['admin_email']) ?></span>
                    <a href="dashboard.php?logout=1" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                </div>
            </div>
            
            <div class="content-wrapper">
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-number"><?= $totalClients ?></div><div class="stat-label">Clients</div></div>
                    <div class="stat-card"><div class="stat-number"><?= $totalCommandes ?></div><div class="stat-label">Commandes</div></div>
                    <div class="stat-card"><div class="stat-number"><?= number_format($caTotal, 0, ',', ' ') ?>€</div><div class="stat-label">CA total</div></div>
                </div>
                
                <div class="filters-bar">
                    <form method="GET" class="search-form">
                        <input type="text" name="search" class="search-input" placeholder="Rechercher par nom, prénom ou email..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-search"><i class="fas fa-search"></i> Rechercher</button>
                        <?php if (!empty($search)): ?>
                            <a href="admin_clients.php" class="btn-reset"><i class="fas fa-times"></i> Réinitialiser</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="section">
                    <div class="section-header">
                        <h2><i class="fas fa-list"></i> Liste des clients</h2>
                    </div>
                    
                    <?php if (empty($clients)): ?>
                        <div style="text-align: center; padding: 60px; color: var(--gray-500);">
                            <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 12px;"></i>
                            <p>Aucun client trouvé</p>
                        </div>
                    <?php else: ?>
                        <div class="desktop-table table-wrapper">
                            <table>
                                <thead>
                                    <tr><th>ID</th><th>Client</th><th>Email</th><th>Téléphone</th><th>Inscription</th><th>Commandes</th><th>Total achats</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td>#<?= $client['idClient'] ?></td>
                                        <td><strong><?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?></strong></td>
                                        <td style="font-size: 0.75rem;"><?= htmlspecialchars($client['email']) ?></td>
                                        <td><?= htmlspecialchars($client['telephone'] ?? '-') ?></td>
                                        <td><?= date('d/m/Y', strtotime($client['date_creation'])) ?></td>
                                        <td><?= $client['nb_commandes'] ?></td>
                                        <td><?= number_format($client['total_achats'], 2, ',', ' ') ?>€</td>
                                        <td class="actions-cell">
                                            <a href="admin_client_detail.php?id=<?= $client['idClient'] ?>" class="btn-small"><i class="fas fa-eye"></i> Voir</a>
                                            <a href="admin_client_edit.php?id=<?= $client['idClient'] ?>" class="btn-small"><i class="fas fa-edit"></i> Modifier</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mobile-cards">
                            <?php foreach ($clients as $client): ?>
                            <div class="client-card">
                                <div class="client-name">
                                    <strong>#<?= $client['idClient'] ?></strong> - <?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?>
                                </div>
                                <div class="client-email">📧 <?= htmlspecialchars($client['email']) ?></div>
                                <div class="client-info">
                                    <span>📞 <?= htmlspecialchars($client['telephone'] ?? '-') ?></span>
                                    <span>📅 <?= date('d/m/Y', strtotime($client['date_creation'])) ?></span>
                                    <span>📦 <?= $client['nb_commandes'] ?> commandes</span>
                                    <span>💰 <?= number_format($client['total_achats'], 2, ',', ' ') ?>€</span>
                                </div>
                                <div class="client-actions">
                                    <a href="admin_client_detail.php?id=<?= $client['idClient'] ?>" class="btn-small"><i class="fas fa-eye"></i> Voir</a>
                                    <a href="admin_client_edit.php?id=<?= $client['idClient'] ?>" class="btn-small"><i class="fas fa-edit"></i> Modifier</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
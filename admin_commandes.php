<?php
require_once 'admin_protection.php';
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if (isset($_POST['action'])) {
        $idCommande = $_POST['idCommande'] ?? null;
        switch ($_POST['action']) {
            case 'changer_statut':
                $nouveauStatut = $_POST['nouveau_statut'] ?? null;
                if ($nouveauStatut && in_array($nouveauStatut, ['en_attente_paiement', 'payee', 'expediee', 'livree', 'annulee'])) {
                    $stmt = $pdo->prepare("UPDATE Commande SET statut = ? WHERE idCommande = ?");
                    $stmt->execute([$nouveauStatut, $idCommande]);
                }
                break;
        }
        header('Location: admin_commandes.php');
        exit;
    }
    
    $statut = $_GET['statut'] ?? 'tous';
    $date_debut = $_GET['date_debut'] ?? '';
    $date_fin = $_GET['date_fin'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $conditions = [];
    $params = [];
    
    if ($statut !== 'tous') {
        $conditions[] = "c.statut = ?";
        $params[] = $statut;
    }
    if (!empty($date_debut)) {
        $conditions[] = "c.dateCommande >= ?";
        $params[] = $date_debut;
    }
    if (!empty($date_fin)) {
        $conditions[] = "c.dateCommande < ?";
        $params[] = date('Y-m-d', strtotime($date_fin . ' +1 day'));
    }
    if (!empty($search)) {
        $conditions[] = "(cl.nom LIKE ? OR cl.prenom LIKE ? OR cl.email LIKE ? OR c.idCommande LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    $stmt = $pdo->prepare("
        SELECT c.idCommande, c.dateCommande, c.montantTotal, c.statut, c.delaiLivraison,
               cl.nom, cl.prenom, cl.email,
               a.adresse, a.codePostal, a.ville
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        JOIN Adresse a ON c.idAdresseLivraison = a.idAdresse
        $where
        ORDER BY c.dateCommande DESC
    ");
    $stmt->execute($params);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiques
    $stmtTotal = $pdo->query("SELECT COUNT(*) as total FROM Commande");
    $totalCommandes = $stmtTotal->fetchColumn();
    
    $stmtAttente = $pdo->query("SELECT COUNT(*) as total FROM Commande WHERE statut = 'en_attente_paiement'");
    $enAttente = $stmtAttente->fetchColumn();
    
    $stmtPayees = $pdo->query("SELECT COUNT(*) as total FROM Commande WHERE statut = 'payee'");
    $payees = $stmtPayees->fetchColumn();
    
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Commandes - Youki and Co</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --danger: #e74c3c;
            --info: #3498db;
            --border-radius: 12px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
            color: var(--gray-800);
        }

        .app { display: flex; min-height: 100vh; }
        
        /* Sidebar (identique au dashboard) */
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
        .nav-item:hover i { color: var(--primary); }
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
        .btn-logout:hover { background: var(--gray-200); color: var(--danger); }
        
        .content-wrapper { padding: 24px; }
        @media (max-width: 640px) { .content-wrapper { padding: 16px; } }
        
        /* Stats cards */
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
        
        /* Filters */
        .filters-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            min-width: 140px;
        }
        
        .filter-group label {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--gray-500);
        }
        
        .filter-select, .filter-input {
            padding: 10px 12px;
            border: 1px solid var(--gray-300);
            border-radius: 10px;
            font-size: 0.8rem;
            background: white;
        }
        
        .filter-select:focus, .filter-input:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .btn-filter, .btn-clear {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
        }
        
        .btn-filter {
            background: var(--primary);
            color: white;
        }
        
        .btn-filter:hover { background: var(--primary-dark); }
        
        .btn-clear {
            background: var(--gray-100);
            color: var(--gray-700);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-clear:hover { background: var(--gray-200); }
        
        /* Table */
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
            min-width: 800px;
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
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            cursor: pointer;
        }
        
        .status-en_attente_paiement { background: #fff3cd; color: #856404; }
        .status-payee { background: #d4edda; color: #155724; }
        .status-expediee { background: #d1ecf1; color: #0c5460; }
        .status-livree { background: #fef2f2; color: var(--primary); }
        .status-annulee { background: var(--gray-200); color: var(--gray-600); }
        
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
        
        /* Mobile cards */
        @media (max-width: 640px) {
            .desktop-table { display: none; }
            .mobile-orders { display: flex; flex-direction: column; gap: 12px; padding: 16px; }
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
            .order-id { font-weight: 700; }
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
        
        .statut-form { display: none; }
        
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
                <a href="admin_commandes.php" class="nav-item active"><i class="fas fa-shopping-cart"></i> Commandes</a>
                <a href="admin_factures.php" class="nav-item"><i class="fas fa-file-invoice"></i> Factures</a>
                <a href="admin_clients.php" class="nav-item"><i class="fas fa-users"></i> Clients</a>
                <a href="admin_produits.php" class="nav-item"><i class="fas fa-box"></i> Produits</a>
            </nav>
        </aside>
        
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <main class="main-content">
            <div class="top-bar">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <div class="page-title">
                    <h1>Gestion des commandes</h1>
                    <p><?= count($commandes) ?> commande(s)</p>
                </div>
                <div class="user-info">
                    <span class="user-email"><?= htmlspecialchars($_SESSION['admin_email']) ?></span>
                    <a href="dashboard.php?logout=1" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                </div>
            </div>
            
            <div class="content-wrapper">
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-number"><?= $totalCommandes ?></div><div class="stat-label">Total commandes</div></div>
                    <div class="stat-card"><div class="stat-number"><?= $enAttente ?></div><div class="stat-label">En attente</div></div>
                    <div class="stat-card"><div class="stat-number"><?= $payees ?></div><div class="stat-label">Payées</div></div>
                </div>
                
                <div class="filters-card">
                    <form method="GET" class="filter-form">
                        <div class="filter-group">
                            <label>Statut</label>
                            <select name="statut" class="filter-select" onchange="this.form.submit()">
                                <option value="tous" <?= $statut === 'tous' ? 'selected' : '' ?>>Tous</option>
                                <option value="en_attente_paiement" <?= $statut === 'en_attente_paiement' ? 'selected' : '' ?>>En attente</option>
                                <option value="payee" <?= $statut === 'payee' ? 'selected' : '' ?>>Payée</option>
                                <option value="expediee" <?= $statut === 'expediee' ? 'selected' : '' ?>>Expédiée</option>
                                <option value="livree" <?= $statut === 'livree' ? 'selected' : '' ?>>Livrée</option>
                                <option value="annulee" <?= $statut === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Recherche</label>
                            <input type="text" name="search" class="filter-input" placeholder="Nom, email, ID..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="filter-group">
                            <label>Date début</label>
                            <input type="date" name="date_debut" class="filter-input" value="<?= htmlspecialchars($date_debut) ?>">
                        </div>
                        <div class="filter-group">
                            <label>Date fin</label>
                            <input type="date" name="date_fin" class="filter-input" value="<?= htmlspecialchars($date_fin) ?>">
                        </div>
                        <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filtrer</button>
                        <a href="admin_commandes.php" class="btn-clear"><i class="fas fa-times"></i> Effacer</a>
                    </form>
                </div>
                
                <div class="section">
                    <div class="section-header">
                        <h2><i class="fas fa-list"></i> Liste des commandes</h2>
                    </div>
                    
                    <?php if (empty($commandes)): ?>
                        <div style="text-align: center; padding: 60px; color: var(--gray-500);">
                            <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 12px;"></i>
                            <p>Aucune commande trouvée</p>
                        </div>
                    <?php else: ?>
                        <div class="desktop-table table-wrapper">
                            <table>
                                <thead>
                                    <tr><th>ID</th><th>Date</th><th>Client</th><th>Montant</th><th>Statut</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($commandes as $commande): ?>
                                    <tr>
                                        <td>#<?= $commande['idCommande'] ?></td>
                                        <td><?= date('d/m/Y', strtotime($commande['dateCommande'])) ?></td>
                                        <td><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></td>
                                        <td><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</td>
                                        <td>
                                            <form method="POST" class="statut-form" id="form-<?= $commande['idCommande'] ?>">
                                                <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                                <input type="hidden" name="action" value="changer_statut">
                                                <input type="hidden" name="nouveau_statut" id="nouveau-statut-<?= $commande['idCommande'] ?>">
                                            </form>
                                            <span class="status-badge status-<?= $commande['statut'] ?>" 
                                                  onclick="changerStatut(<?= $commande['idCommande'] ?>, '<?= $commande['statut'] ?>')">
                                                <?= $commande['statut'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-small">
                                                <i class="fas fa-eye"></i> Détails
                                            </a>
                                        </td>
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
                                    <span><?= date('d/m/Y', strtotime($commande['dateCommande'])) ?></span>
                                    <span class="status-badge status-<?= $commande['statut'] ?>"><?= $commande['statut'] ?></span>
                                </div>
                                <div class="order-details">
                                    <span><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></span>
                                    <span class="order-amount"><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</span>
                                </div>
                                <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-small" style="display: inline-flex; justify-content: center;">
                                    <i class="fas fa-eye"></i> Voir détails
                                </a>
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
        function changerStatut(idCommande, statutActuel) {
            let prochainStatut;
            switch(statutActuel) {
                case 'en_attente_paiement': prochainStatut = 'payee'; break;
                case 'payee': prochainStatut = 'expediee'; break;
                case 'expediee': prochainStatut = 'livree'; break;
                case 'livree': prochainStatut = 'annulee'; break;
                case 'annulee': prochainStatut = 'en_attente_paiement'; break;
                default: prochainStatut = 'en_attente_paiement';
            }
            if (confirm(`Changer le statut de "${statutActuel}" à "${prochainStatut}" ?`)) {
                document.getElementById(`nouveau-statut-${idCommande}`).value = prochainStatut;
                document.getElementById(`form-${idCommande}`).submit();
            }
        }
        
        // Mobile menu
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
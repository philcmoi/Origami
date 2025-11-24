<?php
// Inclure la protection au tout début
require_once 'admin_protection.php';

require_once 'config.php';

// Récupérer l'ID du client depuis l'URL
$client_id = $_GET['id'] ?? null;

if (!$client_id) {
    header('Location: admin_clients.php');
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les informations du client
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(cmd.idCommande) as nb_commandes,
               COALESCE(SUM(cmd.montantTotal), 0) as total_achats,
               MAX(cmd.dateCommande) as derniere_commande
        FROM Client c
        LEFT JOIN Commande cmd ON c.idClient = cmd.idClient
        WHERE c.idClient = ?
        GROUP BY c.idClient
    ");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        header('Location: admin_clients.php');
        exit;
    }

    // Récupérer les commandes du client - CORRECTION : requête simplifiée sans jointure problématique
    $stmt = $pdo->prepare("
        SELECT 
            c.idCommande,
            c.dateCommande,
            c.montantTotal,
            COUNT(lc.idLigneCommande) as nb_articles
        FROM Commande c
        LEFT JOIN LigneCommande lc ON c.idCommande = lc.idCommande
        WHERE c.idClient = ?
        GROUP BY c.idCommande
        ORDER BY c.dateCommande DESC
    ");
    $stmt->execute([$client_id]);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Client #<?= $client_id ?> - Youki and Co</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
            font-size: 14px;
        }

        /* ===== HEADER OPTIMISÉ ===== */
        .header {
            background: white;
            padding: 12px 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo h1 {
            color: #d40000;
            font-size: 18px;
            text-align: center;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .admin-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-align: center;
        }

        .admin-info span {
            font-size: 13px;
            color: #666;
        }

        .btn-logout {
            background: #d40000;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            display: inline-block;
            transition: background 0.3s;
            font-weight: 500;
        }

        .btn-logout:hover {
            background: #b30000;
        }

        /* ===== LAYOUT PRINCIPAL ===== */
        .container {
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 80px);
        }

        /* ===== MENU MOBILE OPTIMISÉ ===== */
        .mobile-menu-toggle {
            display: block;
            background: #d40000;
            color: white;
            border: none;
            padding: 12px 15px;
            border-radius: 6px;
            cursor: pointer;
            margin: 15px;
            width: calc(100% - 30px);
            font-size: 15px;
            font-weight: 500;
            transition: background 0.3s;
        }

        .mobile-menu-toggle:hover {
            background: #b30000;
        }

        .sidebar {
            background: white;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
            position: fixed;
            top: 80px;
            left: 0;
            width: 100%;
            height: calc(100vh - 80px);
            overflow-y: auto;
            z-index: 99;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .sidebar.active {
            display: block;
            transform: translateX(0);
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .nav-item:last-child {
            border-bottom: none;
        }

        .nav-item:hover, .nav-item.active {
            background: #d40000;
            color: white;
        }

        /* ===== CONTENU PRINCIPAL ===== */
        .main-content {
            flex: 1;
            padding: 15px;
        }

        /* ===== EN-TÊTE CLIENT ===== */
        .client-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        @media (min-width: 768px) {
            .client-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: flex-start;
            }
        }

        .client-identity {
            flex: 1;
        }

        .client-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }

        .client-id {
            color: #d40000;
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 15px;
        }

        .client-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        @media (min-width: 480px) {
            .client-stats {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .stat-item {
            text-align: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .stat-number {
            font-size: 18px;
            font-weight: bold;
            color: #d40000;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
        }

        .client-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-primary, .btn-secondary {
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-primary {
            background: #d40000;
            color: white;
        }

        .btn-primary:hover {
            background: #b30000;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-1px);
        }

        /* ===== SECTIONS ===== */
        .section {
            background: white;
            padding: 20px 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .section h2 {
            margin-bottom: 18px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 12px;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ===== INFORMATIONS CLIENT ===== */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        @media (min-width: 768px) {
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #d40000;
        }

        .info-group {
            margin-bottom: 12px;
        }

        .info-group:last-child {
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 14px;
            color: #333;
            word-break: break-word;
        }

        .info-value.empty {
            color: #999;
            font-style: italic;
        }

        /* ===== COMMANDES ===== */
        .table-container {
            display: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            white-space: nowrap;
            position: sticky;
            top: 0;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .btn-action {
            padding: 8px 16px;
            background: #17a2b8;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
            display: inline-block;
            transition: background 0.3s;
        }

        .btn-action:hover {
            background: #138496;
            transform: translateY(-1px);
        }

        /* ===== VERSION MOBILE (CARTES) ===== */
        .orders-mobile {
            display: block;
        }

        .order-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 15px;
            border-left: 4px solid #d40000;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .order-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .order-id {
            font-weight: bold;
            color: #d40000;
            font-size: 16px;
        }

        .order-date {
            color: #666;
            font-size: 13px;
            text-align: right;
        }

        .order-info {
            margin-bottom: 12px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }

        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 13px;
            min-width: 80px;
        }

        .info-value {
            flex: 1;
            text-align: right;
            font-size: 13px;
            word-break: break-word;
            padding-left: 10px;
        }

        /* ===== ACTIONS MOBILE ===== */
        .order-actions-mobile {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-mobile {
            padding: 10px 15px;
            background: #17a2b8;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            text-align: center;
            flex: 1;
            font-weight: 500;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-mobile:hover {
            background: #138496;
        }

        /* ===== ÉTATS ===== */
        .no-orders {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            font-style: italic;
            font-size: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
        }

        /* ===== OVERLAY MENU MOBILE ===== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 98;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        /* ===== VERSION ORDINATEUR ===== */
        @media (min-width: 1024px) {
            /* Header desktop */
            .header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 15px 25px;
            }

            .logo h1 {
                text-align: left;
                margin-bottom: 0;
                font-size: 22px;
            }

            .admin-info {
                flex-direction: row;
                text-align: left;
                gap: 15px;
            }

            /* Layout desktop */
            .container {
                flex-direction: row;
            }

            .mobile-menu-toggle {
                display: none;
            }

            .sidebar {
                display: block;
                position: static;
                width: 280px;
                height: auto;
                padding: 0;
                transform: none;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }

            .nav-item {
                padding: 18px 25px;
                font-size: 15px;
            }

            .main-content {
                padding: 25px;
                flex: 1;
                overflow-x: auto;
            }

            /* Sections desktop */
            .section {
                padding: 25px;
                margin-bottom: 25px;
            }

            .section h2 {
                font-size: 20px;
                margin-bottom: 20px;
            }

            /* Affichage conditionnel desktop/mobile */
            .table-container {
                display: block;
            }

            .orders-mobile {
                display: none;
            }

            /* Statistiques client desktop */
            .client-stats {
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
            }

            .stat-item {
                padding: 15px;
            }

            .stat-number {
                font-size: 20px;
            }
        }

        /* ===== AMÉLIORATIONS TRÈS PETITS ÉCRANS ===== */
        @media (max-width: 360px) {
            .main-content {
                padding: 12px;
            }

            .client-header {
                padding: 15px;
            }

            .client-name {
                font-size: 20px;
            }

            .client-stats {
                grid-template-columns: 1fr;
            }

            .order-card {
                padding: 14px;
            }

            .btn-mobile {
                padding: 9px 12px;
                font-size: 12px;
            }

            .nav-item {
                padding: 14px 16px;
                font-size: 14px;
            }

            .client-actions {
                flex-direction: column;
            }

            .btn-primary, .btn-secondary {
                width: 100%;
                justify-content: center;
            }
        }

        /* ===== AMÉLIORATIONS ÉCRANS MOYENS ===== */
        @media (min-width: 768px) and (max-width: 1023px) {
            .main-content {
                padding: 20px;
            }

            .section {
                padding: 25px 20px;
            }
        }

        /* ===== ANIMATIONS ET INTERACTIONS ===== */
        @media (hover: hover) {
            .order-card:hover {
                transform: translateY(-2px);
            }
        }

        /* ===== ACCESSIBILITÉ ===== */
        @media (prefers-reduced-motion: reduce) {
            .sidebar, .sidebar-overlay, .order-card {
                transition: none;
            }
        }

        /* ===== IMPRESSION ===== */
        @media print {
            .sidebar, .mobile-menu-toggle, .btn-logout, .client-actions {
                display: none;
            }

            .container {
                flex-direction: column;
            }

            .main-content {
                padding: 0;
            }

            .section, .client-header {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>Youki and Co - Détails Client</h1>
        </div>
        <div class="admin-info">
            <span>Connecté: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="admin_dashboard.php?logout=1" class="btn-logout">Déconnexion</a>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        ☰ Menu Administration
    </button>

    <div class="container">
        <div class="sidebar" id="sidebar">
            <a href="admin_dashboard.php" class="nav-item">📊 Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">📦 Gestion des Commandes</a>
            <a href="admin_factures.php" class="nav-item">📄 Gestion des Factures</a>
            <a href="admin_clients.php" class="nav-item active">👥 Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item">🎨 Gestion des Produits</a>
        </div>

        <div class="main-content">
            <!-- En-tête du client -->
            <div class="client-header">
                <div class="client-identity">
                    <div class="client-name">
                        <?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?>
                    </div>
                    <div class="client-id">
                        Client #<?= $client['idClient'] ?>
                    </div>
                    <div class="client-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?= $client['nb_commandes'] ?></div>
                            <div class="stat-label">Commandes</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?= number_format($client['total_achats'], 2, ',', ' ') ?>€</div>
                            <div class="stat-label">Total Achats</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">
                                <?= $client['derniere_commande'] ? date('d/m/Y', strtotime($client['derniere_commande'])) : 'N/A' ?>
                            </div>
                            <div class="stat-label">Dernière commande</div>
                        </div>
                    </div>
                </div>
                <div class="client-actions">
                    <a href="admin_clients.php" class="btn-secondary">
                        <span>←</span>
                        Retour aux clients
                    </a>
                </div>
            </div>

            <!-- Informations personnelles -->
            <div class="section">
                <h2>👤 Informations Personnelles</h2>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-group">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?= htmlspecialchars($client['email']) ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Téléphone</div>
                            <div class="info-value <?= empty($client['telephone']) ? 'empty' : '' ?>">
                                <?= !empty($client['telephone']) ? htmlspecialchars($client['telephone']) : 'Non renseigné' ?>
                            </div>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-group">
                            <div class="info-label">Date d'inscription</div>
                            <div class="info-value"><?= date('d/m/Y à H:i', strtotime($client['dateInscription'])) ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Type de client</div>
                            <div class="info-value">
                                Client standard
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historique des commandes -->
            <div class="section">
                <h2>📦 Historique des Commandes</h2>

                <?php if (empty($commandes)): ?>
                    <div class="no-orders">
                        Aucune commande pour ce client.
                    </div>
                <?php else: ?>

                <!-- Version Desktop (tableau) -->
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID Commande</th>
                                <th>Date</th>
                                <th>Montant</th>
                                <th>Articles</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commandes as $commande): ?>
                            <tr>
                                <td><strong>#<?= $commande['idCommande'] ?></strong></td>
                                <td><?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></td>
                                <td><strong><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</strong></td>
                                <td><?= $commande['nb_articles'] ?> article(s)</td>
                                <td>
                                    <a href="admin_factures.php?action=generer&id=<?= $commande['idCommande'] ?>" class="btn-action" target="_blank">Voir facture</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Version Mobile (cartes) -->
                <div class="orders-mobile">
                    <?php foreach ($commandes as $commande): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-id">#<?= $commande['idCommande'] ?></div>
                            <div class="order-date"><?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></div>
                        </div>

                        <div class="order-info">
                            <div class="info-row">
                                <span class="info-label">Montant:</span>
                                <span class="info-value"><strong><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</strong></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Articles:</span>
                                <span class="info-value"><?= $commande['nb_articles'] ?> article(s)</span>
                            </div>
                        </div>

                        <div class="order-actions-mobile">
                            <a href="admin_factures.php?action=generer&id=<?= $commande['idCommande'] ?>" class="btn-mobile" target="_blank">
                                <span>📄</span>
                                <span>Voir facture</span>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Gestion du menu mobile optimisée
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleMobileMenu() {
            const isActive = sidebar.classList.contains('active');
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = isActive ? '' : 'hidden';

            // Animation du bouton
            mobileMenuToggle.style.transform = isActive ? 'none' : 'scale(0.98)';
        }

        function closeMobileMenu() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
            mobileMenuToggle.style.transform = 'none';
        }

        mobileMenuToggle.addEventListener('click', toggleMobileMenu);
        sidebarOverlay.addEventListener('click', closeMobileMenu);

        // Fermer le menu en cliquant sur un lien (mobile seulement)
        sidebar.querySelectorAll('.nav-item').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    closeMobileMenu();
                }
            });
        });

        // Adapter au redimensionnement
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                closeMobileMenu();
            }
        });

        // Masquer le menu au chargement sur mobile
        window.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth < 1024) {
                closeMobileMenu();
            }
        });

        // Empêcher le scroll quand le menu est ouvert
        sidebar.addEventListener('touchmove', function(e) {
            if (sidebar.classList.contains('active')) {
                e.preventDefault();
            }
        }, { passive: false });

        // Amélioration de l'accessibilité
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                closeMobileMenu();
                mobileMenuToggle.focus();
            }
        });

        // Focus management pour l'accessibilité
        mobileMenuToggle.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleMobileMenu();
            }
        });
    </script>
</body>
</html>
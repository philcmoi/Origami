<?php
// Inclure la protection au tout début
require_once 'admin_protection.php';

// Configuration de la base de données
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les statistiques
    // Commandes en attente
    $stmt = $pdo->query("SELECT COUNT(*) FROM Commande WHERE statut = 'en_attente'");
    $commandesEnAttente = $stmt->fetchColumn();

    // Clients permanents
    $stmt = $pdo->query("SELECT COUNT(*) FROM Client WHERE type = 'permanent' OR (type IS NULL AND email NOT LIKE 'temp_%@YoukiAndCo.fr')");
    $clientsPermanents = $stmt->fetchColumn();

    // Chiffre d'affaires du mois
    $stmt = $pdo->query("SELECT SUM(montantTotal) FROM Commande WHERE MONTH(dateCommande) = MONTH(CURRENT_DATE()) AND YEAR(dateCommande) = YEAR(CURRENT_DATE())");
    $chiffreAffaires = $stmt->fetchColumn() ?? 0;

    // Récupérer les commandes récentes
    $stmt = $pdo->query("
        SELECT c.idCommande, c.dateCommande, c.montantTotal, c.statut,
               cl.nom, cl.prenom, cl.email
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        ORDER BY c.dateCommande DESC
        LIMIT 10
    ");
    $commandesRecentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Déconnexion
if (isset($_GET['logout'])) {
    // Nettoyer complètement la session
    session_unset();
    session_destroy();
    header('Location: admin_login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Admin - Youki and Co</title>
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

        /* ===== STATISTIQUES RESPONSIVES ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        @media (min-width: 400px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
            }
        }

        .stat-card {
            background: white;
            padding: 20px 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid #d40000;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #d40000;
            margin-bottom: 6px;
            line-height: 1;
        }

        .stat-label {
            color: #666;
            font-size: 13px;
            font-weight: 500;
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
        }

        /* ===== VERSION DESKTOP (TABLEAU) ===== */
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
            background: #d40000;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
            display: inline-block;
            transition: background 0.3s;
        }

        .btn-action:hover {
            background: #b30000;
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
            min-width: 70px;
        }

        .info-value {
            flex: 1;
            text-align: right;
            font-size: 13px;
            word-break: break-word;
            padding-left: 10px;
        }

        /* ===== STATUTS ===== */
        .mobile-status {
            text-align: center;
            margin: 15px 0;
            padding: 12px;
            background: rgba(212, 0, 0, 0.05);
            border-radius: 8px;
        }

        .status-text {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            min-width: 100px;
        }

        .status-en_attente {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-confirmee {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #b8daff;
        }

        .status-expediee {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-livree {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        .status-annulee {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* ===== ACTIONS MOBILE ===== */
        .order-actions-mobile {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-mobile {
            padding: 10px 15px;
            background: #d40000;
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
            background: #b30000;
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

            /* Statistiques desktop */
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 25px;
                margin-bottom: 30px;
            }

            .stat-card {
                padding: 30px 20px;
            }

            .stat-number {
                font-size: 32px;
            }

            .stat-label {
                font-size: 14px;
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
        }

        /* ===== AMÉLIORATIONS TRÈS PETITS ÉCRANS ===== */
        @media (max-width: 360px) {
            .main-content {
                padding: 12px;
            }

            .stat-card {
                padding: 18px 12px;
            }

            .stat-number {
                font-size: 22px;
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
        }

        /* ===== AMÉLIORATIONS ÉCRANS MOYENS ===== */
        @media (min-width: 768px) and (max-width: 1023px) {
            .main-content {
                padding: 20px;
            }

            .section {
                padding: 25px 20px;
            }

            .stat-card {
                padding: 25px 20px;
            }
        }

        /* ===== ANIMATIONS ET INTERACTIONS ===== */
        @media (hover: hover) {
            .stat-card:hover, .order-card:hover {
                transform: translateY(-2px);
            }
        }

        /* ===== ACCESSIBILITÉ ===== */
        @media (prefers-reduced-motion: reduce) {
            .sidebar, .sidebar-overlay, .stat-card, .order-card {
                transition: none;
            }
        }

        /* ===== IMPRESSION ===== */
        @media print {
            .sidebar, .mobile-menu-toggle, .btn-logout {
                display: none;
            }

            .container {
                flex-direction: column;
            }

            .main-content {
                padding: 0;
            }

            .stat-card, .section {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>Youki and Co - Administration</h1>
        </div>
        <div class="admin-info">
            <span>Connecté: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="?logout=1" class="btn-logout">Déconnexion</a>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        ☰ Menu Administration
    </button>

    <div class="container">
        <div class="sidebar" id="sidebar">
            <a href="admin_dashboard.php" class="nav-item active">📊 Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">📦 Gestion des Commandes</a>
            <a href="admin_factures.php" class="nav-item">📄 Gestion des Factures</a>
            <a href="admin_clients.php" class="nav-item">👥 Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item">🎨 Gestion des Produits</a>
        </div>

        <div class="main-content">
            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $commandesEnAttente ?></div>
                    <div class="stat-label">Commandes en attente</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $clientsPermanents ?></div>
                    <div class="stat-label">Clients permanents</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($chiffreAffaires, 2, ',', ' ') ?>€</div>
                    <div class="stat-label">CA ce mois</div>
                </div>
            </div>

            <!-- Commandes récentes -->
            <div class="section">
                <h2>📋 Commandes Récentes</h2>

                <?php if (empty($commandesRecentes)): ?>
                    <div class="no-orders">
                        Aucune commande récente.
                    </div>
                <?php else: ?>

                <!-- Version Desktop (tableau) -->
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID Commande</th>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commandesRecentes as $commande): ?>
                            <tr>
                                <td><strong>#<?= $commande['idCommande'] ?></strong></td>
                                <td><?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></strong>
                                    <br><small><?= htmlspecialchars($commande['email']) ?></small>
                                </td>
                                <td><strong><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</strong></td>
                                <td>
                                    <span class="status-badge status-<?= $commande['statut'] ?>">
                                        <?=
                                            str_replace(
                                                ['en_attente', 'confirmee', 'expediee', 'livree', 'annulee'],
                                                ['En attente', 'Confirmée', 'Expédiée', 'Livrée', 'Annulée'],
                                                $commande['statut']
                                            )
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-action">Voir</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Version Mobile (cartes) -->
                <div class="orders-mobile">
                    <?php foreach ($commandesRecentes as $commande): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-id">#<?= $commande['idCommande'] ?></div>
                            <div class="order-date"><?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></div>
                        </div>

                        <div class="order-info">
                            <div class="info-row">
                                <span class="info-label">Client:</span>
                                <span class="info-value"><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?= htmlspecialchars($commande['email']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Montant:</span>
                                <span class="info-value"><strong><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</strong></span>
                            </div>
                        </div>

                        <div class="mobile-status">
                            <div class="status-text">Statut de la commande</div>
                            <span class="status-badge status-<?= $commande['statut'] ?>">
                                <?=
                                    str_replace(
                                        ['en_attente', 'confirmee', 'expediee', 'livree', 'annulee'],
                                        ['En attente', 'Confirmée', 'Expédiée', 'Livrée', 'Annulée'],
                                        $commande['statut']
                                    )
                                ?>
                            </span>
                        </div>

                        <div class="order-actions-mobile">
                            <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-mobile">
                                <span>👁️</span>
                                <span>Voir détails</span>
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

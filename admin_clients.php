<?php
// Inclure la protection au tout début
require_once 'admin_protection.php';

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les clients permanents
    $stmt = $pdo->query("
        SELECT c.idClient, c.email, c.nom, c.prenom, c.telephone, c.date_creation,
               COUNT(cmd.idCommande) as nb_commandes,
               COALESCE(SUM(cmd.montantTotal), 0) as total_achats
        FROM Client c
        LEFT JOIN Commande cmd ON c.idClient = cmd.idClient
        WHERE c.type = 'permanent' OR (c.type IS NULL AND c.email NOT LIKE 'temp_%@YoukiAndCo.fr')
        GROUP BY c.idClient
        ORDER BY c.date_creation DESC
    ");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculer les statistiques
    $total_clients = count($clients);
    $total_ca = array_sum(array_column($clients, 'total_achats'));
    $total_commandes = array_sum(array_column($clients, 'nb_commandes'));
    $moyenne_achats = $total_clients > 0 ? $total_ca / $total_clients : 0;

} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Clients - Youki and Co</title>
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
                grid-template-columns: repeat(4, 1fr);
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

        /* ===== FILTRES ET RECHERCHE ===== */
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            align-items: center;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .search-box {
            flex: 1;
            min-width: 200px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #d40000;
            box-shadow: 0 0 0 2px rgba(212, 0, 0, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 16px;
        }

        .sort-options {
            display: flex;
            gap: 10px;
        }

        .sort-options select {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .sort-options select:focus {
            outline: none;
            border-color: #d40000;
        }

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .sort-options {
                justify-content: space-between;
            }
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

        .client-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-view, .btn-edit {
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-block;
            text-align: center;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-view:hover {
            background: #138496;
            transform: translateY(-1px);
        }

        .btn-edit {
            background: #ffc107;
            color: black;
        }

        .btn-edit:hover {
            background: #e0a800;
            transform: translateY(-1px);
        }

        /* ===== VERSION MOBILE (CARTES) ===== */
        .cards-container {
            display: block;
        }

        .client-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 15px;
            border-left: 4px solid #d40000;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .client-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .client-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .client-id {
            font-weight: bold;
            color: #d40000;
            font-size: 16px;
        }

        .client-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 12px;
            color: #333;
        }

        .client-info {
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
            min-width: 100px;
        }

        .info-value {
            flex: 1;
            text-align: right;
            font-size: 13px;
            word-break: break-word;
            padding-left: 10px;
        }

        /* ===== ACTIONS MOBILE ===== */
        .client-actions-mobile {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-mobile {
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            text-align: center;
            flex: 1;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-view-mobile {
            background: #17a2b8;
            color: white;
        }

        .btn-view-mobile:hover {
            background: #138496;
        }

        .btn-edit-mobile {
            background: #ffc107;
            color: black;
        }

        .btn-edit-mobile:hover {
            background: #e0a800;
        }

        /* ===== ÉTATS ===== */
        .no-clients {
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
                grid-template-columns: repeat(4, 1fr);
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

            .cards-container {
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

            .client-card {
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

            .client-card-header {
                flex-direction: column;
                gap: 8px;
            }

            .client-actions-mobile {
                flex-direction: column;
            }

            .info-row {
                flex-direction: column;
                gap: 2px;
            }

            .info-label, .info-value {
                text-align: left;
                min-width: auto;
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
            .stat-card:hover, .client-card:hover {
                transform: translateY(-2px);
            }
        }

        /* ===== ACCESSIBILITÉ ===== */
        @media (prefers-reduced-motion: reduce) {
            .sidebar, .sidebar-overlay, .stat-card, .client-card {
                transition: none;
            }
        }

        /* ===== IMPRESSION ===== */
        @media print {
            .sidebar, .mobile-menu-toggle, .btn-logout, .filters {
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
            <h1>Youki and Co - Clients</h1>
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
            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_clients ?></div>
                    <div class="stat-label">Clients permanents</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $total_commandes ?></div>
                    <div class="stat-label">Commandes totales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($total_ca, 2, ',', ' ') ?>€</div>
                    <div class="stat-label">Chiffre d'affaires</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($moyenne_achats, 2, ',', ' ') ?>€</div>
                    <div class="stat-label">Panier moyen</div>
                </div>
            </div>

            <!-- Liste des clients -->
            <div class="section">
                <h2>👥 Liste des Clients (<?= $total_clients ?>)</h2>
                
                <!-- Filtres et recherche -->
                <div class="filters">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="searchInput" placeholder="Rechercher un client...">
                    </div>
                    <div class="sort-options">
                        <select id="sortSelect">
                            <option value="date_desc">Plus récents</option>
                            <option value="date_asc">Plus anciens</option>
                            <option value="name_asc">Nom A-Z</option>
                            <option value="name_desc">Nom Z-A</option>
                            <option value="achats_desc">Achats (haut)</option>
                            <option value="achats_asc">Achats (bas)</option>
                        </select>
                    </div>
                </div>

                <?php if (empty($clients)): ?>
                    <div class="no-clients">
                        Aucun client trouvé
                    </div>
                <?php else: ?>

                <!-- Version Desktop (tableau) -->
                <div class="table-container">
                    <table id="clientsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom & Prénom</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Date d'inscription</th>
                                <th>Commandes</th>
                                <th>Total Achats</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><strong>#<?= $client['idClient'] ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($client['email']) ?></td>
                                <td><?= htmlspecialchars($client['telephone'] ?? 'Non renseigné') ?></td>
                                <td><?= date('d/m/Y', strtotime($client['date_creation'])) ?></td>
                                <td><?= $client['nb_commandes'] ?></td>
                                <td><strong><?= number_format($client['total_achats'], 2, ',', ' ') ?>€</strong></td>
                                <td>
                                    <div class="client-actions">
                                        <a href="admin_client_detail.php?id=<?= $client['idClient'] ?>" class="btn-view">Voir</a>
                                        <a href="admin_client_edit.php?id=<?= $client['idClient'] ?>" class="btn-edit">Éditer</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Version Mobile (cartes) -->
                <div class="cards-container" id="clientsCards">
                    <?php foreach ($clients as $client): ?>
                    <div class="client-card" data-client-id="<?= $client['idClient'] ?>">
                        <div class="client-card-header">
                            <div class="client-id">#<?= $client['idClient'] ?></div>
                            <div class="client-actions">
                                <a href="admin_client_detail.php?id=<?= $client['idClient'] ?>" class="btn-view">Voir</a>
                                <a href="admin_client_edit.php?id=<?= $client['idClient'] ?>" class="btn-edit">Éditer</a>
                            </div>
                        </div>

                        <div class="client-name">
                            <?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?>
                        </div>

                        <div class="client-info">
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?= htmlspecialchars($client['email']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Téléphone:</span>
                                <span class="info-value"><?= htmlspecialchars($client['telephone'] ?? 'Non renseigné') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Inscription:</span>
                                <span class="info-value"><?= date('d/m/Y', strtotime($client['date_creation'])) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Commandes:</span>
                                <span class="info-value"><?= $client['nb_commandes'] ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Total Achats:</span>
                                <span class="info-value"><strong><?= number_format($client['total_achats'], 2, ',', ' ') ?>€</strong></span>
                            </div>
                        </div>

                        <div class="client-actions-mobile">
                            <a href="admin_client_detail.php?id=<?= $client['idClient'] ?>" class="btn-mobile btn-view-mobile">
                                <span>👁️</span>
                                <span>Voir détails</span>
                            </a>
                            <a href="admin_client_edit.php?id=<?= $client['idClient'] ?>" class="btn-mobile btn-edit-mobile">
                                <span>✏️</span>
                                <span>Modifier</span>
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

        // Fonctionnalités de recherche et tri
        document.getElementById('searchInput').addEventListener('input', function() {
            filterClients();
        });

        document.getElementById('sortSelect').addEventListener('change', function() {
            sortClients();
        });

        function filterClients() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.client-card');
            const rows = document.querySelectorAll('#clientsTable tbody tr');
            
            // Filtrer les cartes (version mobile)
            cards.forEach(card => {
                const clientName = card.querySelector('.client-name').textContent.toLowerCase();
                const clientEmail = card.querySelector('.info-row:nth-child(1) .info-value').textContent.toLowerCase();
                const clientPhone = card.querySelector('.info-row:nth-child(2) .info-value').textContent.toLowerCase();
                
                if (clientName.includes(searchTerm) || clientEmail.includes(searchTerm) || clientPhone.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Filtrer les lignes du tableau (version desktop)
            rows.forEach(row => {
                const clientName = row.cells[1].textContent.toLowerCase();
                const clientEmail = row.cells[2].textContent.toLowerCase();
                const clientPhone = row.cells[3].textContent.toLowerCase();
                
                if (clientName.includes(searchTerm) || clientEmail.includes(searchTerm) || clientPhone.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function sortClients() {
            const sortValue = document.getElementById('sortSelect').value;
            const cardsContainer = document.getElementById('clientsCards');
            const tableBody = document.querySelector('#clientsTable tbody');
            
            // Récupérer toutes les cartes
            const cards = Array.from(document.querySelectorAll('.client-card'));
            
            // Trier les cartes selon le critère sélectionné
            cards.sort((a, b) => {
                switch(sortValue) {
                    case 'date_desc':
                        return new Date(getClientData(b, 'date')) - new Date(getClientData(a, 'date'));
                    case 'date_asc':
                        return new Date(getClientData(a, 'date')) - new Date(getClientData(b, 'date'));
                    case 'name_asc':
                        return getClientData(a, 'name').localeCompare(getClientData(b, 'name'));
                    case 'name_desc':
                        return getClientData(b, 'name').localeCompare(getClientData(a, 'name'));
                    case 'achats_desc':
                        return parseFloat(getClientData(b, 'achats')) - parseFloat(getClientData(a, 'achats'));
                    case 'achats_asc':
                        return parseFloat(getClientData(a, 'achats')) - parseFloat(getClientData(b, 'achats'));
                    default:
                        return 0;
                }
            });
            
            // Réorganiser les cartes dans le conteneur
            cards.forEach(card => {
                cardsContainer.appendChild(card);
            });
        }

        function getClientData(card, dataType) {
            switch(dataType) {
                case 'date':
                    return card.querySelector('.info-row:nth-child(3) .info-value').textContent.split('/').reverse().join('-');
                case 'name':
                    return card.querySelector('.client-name').textContent;
                case 'achats':
                    return card.querySelector('.info-row:nth-child(5) .info-value').textContent.replace('€', '').replace(',', '.').replace(/\s/g, '');
                default:
                    return '';
            }
        }

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

        // Animation d'apparition progressive des cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.client-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'opacity 0.5s, transform 0.5s';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });
        });
    </script>
</body>
</html>
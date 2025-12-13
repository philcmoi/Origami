<?php
// Inclure la protection au tout début
require_once 'admin_protection.php';

// Configuration de la base de données
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer uniquement les clients permanents
    $stmt = $pdo->prepare("
        SELECT 
            c.idClient,
            c.nom,
            c.prenom,
            c.email,
            c.telephone,
            c.type,
            COUNT(co.idCommande) as nb_commandes,
            SUM(co.montantTotal) as total_depense,
            MAX(co.dateCommande) as derniere_commande
        FROM Client c
        LEFT JOIN Commande co ON c.idClient = co.idClient
        WHERE c.type = 'permanent'
        GROUP BY c.idClient
        ORDER BY c.nom, c.prenom
    ");
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les statistiques pour les clients permanents uniquement
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM Client WHERE type = 'permanent'");
    $stmt->execute();
    $totalClients = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT c.idClient) as actifs 
        FROM Client c
        JOIN Commande co ON c.idClient = co.idClient
        WHERE c.type = 'permanent' 
        AND co.dateCommande >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    ");
    $stmt->execute();
    $clientsActifs = $stmt->fetchColumn();

    // Calculer la dépense moyenne des clients permanents
    $stmt = $pdo->prepare("
        SELECT 
            AVG(total_depense) as depense_moyenne,
            MAX(total_depense) as depense_max,
            MIN(total_depense) as depense_min
        FROM (
            SELECT 
                c.idClient,
                SUM(co.montantTotal) as total_depense
            FROM Client c
            LEFT JOIN Commande co ON c.idClient = co.idClient
            WHERE c.type = 'permanent'
            GROUP BY c.idClient
        ) as stats_clients
    ");
    $stmt->execute();
    $statsDepenses = $stmt->fetch(PDO::FETCH_ASSOC);

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

        /* ===== TABLEAU DES CLIENTS ===== */
        .table-container {
            display: block;
            overflow-x: auto;
            margin-bottom: 20px;
        }

        @media (min-width: 1200px) {
            .clients-mobile {
                display: none;
            }
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            min-width: 800px;
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

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            transition: all 0.3s;
            font-weight: 500;
            white-space: nowrap;
        }

        @media (max-width: 480px) {
            .btn {
                padding: 6px 10px;
                font-size: 11px;
            }
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-1px);
        }

        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background-color: #e0a800;
            transform: translateY(-1px);
        }

        .type-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            white-space: nowrap;
            display: inline-block;
            text-align: center;
        }

        .type-permanent { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb;
        }

        .client-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .client-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #d40000, #ff6b6b);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            flex-shrink: 0;
        }

        .client-details {
            flex: 1;
        }

        .client-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }

        .client-email {
            color: #666;
            font-size: 12px;
        }

        /* ===== VERSION MOBILE (CARTES) ===== */
        .clients-mobile {
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

        .client-header {
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

        .client-type {
            text-align: right;
        }

        .client-avatar-mobile {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #d40000, #ff6b6b);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            margin: 0 auto 15px;
        }

        .client-info-mobile {
            margin-bottom: 12px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            min-width: 100px;
            flex-shrink: 0;
        }

        .info-value {
            flex: 1;
            text-align: right;
            word-break: break-word;
            padding-left: 10px;
        }

        /* ===== STATISTIQUES CLIENT ===== */
        .client-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 15px 0;
            padding: 15px;
            background: rgba(212, 0, 0, 0.05);
            border-radius: 8px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 16px;
            font-weight: bold;
            color: #d40000;
            margin-bottom: 4px;
        }

        .stat-label-small {
            color: #666;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ===== ACTIONS MOBILE ===== */
        .client-actions-mobile {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
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

        .btn-mobile-primary {
            background: #007bff;
        }

        .btn-mobile-primary:hover {
            background: #0056b3;
        }

        .btn-mobile-success {
            background: #28a745;
        }

        .btn-mobile-success:hover {
            background: #218838;
        }

        .btn-mobile-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-mobile-warning:hover {
            background: #e0a800;
        }

        /* ===== PAGE TITLE ===== */
        .page-title {
            color: #d40000;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            font-size: 24px;
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 20px;
                text-align: center;
            }
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

        /* ===== FILTRES ===== */
        .filters {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .filter-info {
            text-align: center;
            padding: 12px;
            background: #d4edda;
            color: #155724;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #c3e6cb;
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

            .filters {
                padding: 20px;
            }

            .section h2 {
                font-size: 20px;
                margin-bottom: 20px;
            }

            /* Affichage conditionnel desktop/mobile */
            .table-container {
                display: block;
            }

            .clients-mobile {
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
        }

        /* ===== AMÉLIORATIONS ÉCRANS MOYENS ===== */
        @media (min-width: 768px) and (max-width: 1023px) {
            .main-content {
                padding: 20px;
            }

            .section, .filters {
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
            .sidebar, .mobile-menu-toggle, .btn-logout, .btn, .btn-mobile {
                display: none;
            }

            .container {
                flex-direction: column;
            }

            .main-content {
                padding: 0;
            }

            .stat-card, .section, .filters {
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
            <h1 class="page-title">👥 Gestion des Clients</h1>

            <!-- Filtre d'information -->
            <div class="filters">
                <div class="filter-info">
                    🔍 Affichage des clients
                </div>
            </div>

            <!-- Statistiques rapides -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $totalClients ?></div>
                    <div class="stat-label">Clients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $clientsActifs ?></div>
                    <div class="stat-label">Clients Actifs (6 mois)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?= $statsDepenses['depense_moyenne'] ? number_format($statsDepenses['depense_moyenne'], 2, ',', ' ') . '€' : '0€' ?>
                    </div>
                    <div class="stat-label">Dépense Moyenne</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?= $statsDepenses['depense_max'] ? number_format($statsDepenses['depense_max'], 2, ',', ' ') . '€' : '0€' ?>
                    </div>
                    <div class="stat-label">Dépense Maximale</div>
                </div>
            </div>

            <div class="section">
                <h2>📋 Liste des Clients (<?= count($clients) ?>)</h2>

                <?php if (empty($clients)): ?>
                    <div class="no-clients">
                        Aucun client trouvé dans la base de données.
                    </div>
                <?php else: ?>

                <!-- Version Desktop (tableau) -->
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Contact</th>
                                <th>Type</th>
                                <th>Commandes</th>
                                <th>Dépense totale</th>
                                <th>Dernière commande</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): 
                                $initials = strtoupper(substr($client['prenom'], 0, 1) . substr($client['nom'], 0, 1));
                                $totalDepense = $client['total_depense'] ? number_format($client['total_depense'], 2, ',', ' ') . '€' : '0€';
                                $derniereCommande = $client['derniere_commande'] ? date('d/m/Y', strtotime($client['derniere_commande'])) : 'Aucune';
                            ?>
                            <tr>
                                <td>
                                    <div class="client-info">
                                        <div class="client-avatar">
                                            <?= $initials ?>
                                        </div>
                                        <div class="client-details">
                                            <div class="client-name"><?= htmlspecialchars($client['prenom']) ?> <?= htmlspecialchars($client['nom']) ?></div>
                                            <div class="client-email">ID: #<?= $client['idClient'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($client['email']) ?></div>
                                    <small><?= htmlspecialchars($client['telephone'] ?? 'Non renseigné') ?></small>
                                </td>
                                <td>
                                    <span class="type-badge type-permanent">
                                        Clients
                                    </span>
                                </td>
                                <td>
                                    <strong><?= $client['nb_commandes'] ?></strong> commande<?= $client['nb_commandes'] > 1 ? 's' : '' ?>
                                </td>
                                <td>
                                    <strong><?= $totalDepense ?></strong>
                                </td>
                                <td><?= $derniereCommande ?></td>
                                <td>
                                    <div class="actions-cell" style="display: flex; gap: 6px;">
                                        <a href="admin_client_detail.php?id=<?= $client['idClient'] ?>" class="btn btn-primary">
                                            👁️ Voir
                                        </a>
                                        <a href="admin_commandes.php?id=<?= $client['idClient'] ?>" class="btn btn-success">
                                            📦 Commandes
                                        </a>
                                        <a href="admin_client_edit.php?id=<?= $client['idClient'] ?>" class="btn btn-warning">
                                            ✏️ Modifier
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Version Mobile (cartes) -->
                <div class="clients-mobile">
                    <?php foreach ($clients as $client): 
                        $initials = strtoupper(substr($client['prenom'], 0, 1) . substr($client['nom'], 0, 1));
                        $totalDepense = $client['total_depense'] ? number_format($client['total_depense'], 2, ',', ' ') . '€' : '0€';
                        $derniereCommande = $client['derniere_commande'] ? date('d/m/Y', strtotime($client['derniere_commande'])) : 'Aucune';
                    ?>
                    <div class="client-card">
                        <div class="client-header">
                            <div class="client-id">#<?= $client['idClient'] ?></div>
                            <div class="client-type">
                                <span class="type-badge type-permanent">
                                    Clients
                                </span>
                            </div>
                        </div>

                        <div class="client-avatar-mobile">
                            <?= $initials ?>
                        </div>

                        <div class="client-info-mobile">
                            <div class="info-row">
                                <span class="info-label">Nom:</span>
                                <span class="info-value"><?= htmlspecialchars($client['prenom']) ?> <?= htmlspecialchars($client['nom']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?= htmlspecialchars($client['email']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Téléphone:</span>
                                <span class="info-value"><?= htmlspecialchars($client['telephone'] ?? 'Non renseigné') ?></span>
                            </div>
                        </div>

                        <div class="client-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?= $client['nb_commandes'] ?></div>
                                <div class="stat-label-small">Commandes</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $totalDepense ?></div>
                                <div class="stat-label-small">Dépense</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $derniereCommande ?></div>
                                <div class="stat-label-small">Dernière</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">#<?= $client['idClient'] ?></div>
                                <div class="stat-label-small">ID Client</div>
                            </div>
                        </div>

                        <div class="client-actions-mobile">
                            <a href="admin_client_detail.php?id=<?= $client['idClient'] ?>" class="btn-mobile btn-mobile-primary">
                                <span>👁️</span>
                                <span>Détails</span>
                            </a>
                            <a href="admin_commandes.php?id=<?= $client['idClient'] ?>" class="btn-mobile btn-mobile-success">
                                <span>📦</span>
                                <span>Commandes</span>
                            </a>
                            <a href="admin_client_edit.php?id=<?= $client['idClient'] ?>" class="btn-mobile btn-mobile-warning">
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
        // Gestion du menu mobile optimisée (identique à admin_dashboard.php)
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

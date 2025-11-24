[file name]: admin_commandes.php
[file content begin]
<?php
// Inclure la protection au tout début
require_once 'admin_protection.php';

// Configuration de la base de données
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Gérer les actions sur les commandes
    if (isset($_POST['action'])) {
        $idCommande = $_POST['idCommande'] ?? null;

        switch ($_POST['action']) {
            case 'confirmer':
                $stmt = $pdo->prepare("UPDATE Commande SET statut = 'payee' WHERE idCommande = ?");
                $stmt->execute([$idCommande]);
                break;

            case 'expedier':
                $stmt = $pdo->prepare("UPDATE Commande SET statut = 'expediee' WHERE idCommande = ?");
                $stmt->execute([$idCommande]);
                break;

            case 'livrer':
                $stmt = $pdo->prepare("UPDATE Commande SET statut = 'livree' WHERE idCommande = ?");
                $stmt->execute([$idCommande]);
                break;

            case 'annuler':
                $stmt = $pdo->prepare("UPDATE Commande SET statut = 'annulee' WHERE idCommande = ?");
                $stmt->execute([$idCommande]);
                break;

            // Nouvelle action pour le changement direct de statut
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

    // Récupérer les commandes avec filtres
    $statut = $_GET['statut'] ?? 'tous';
    $date_debut = $_GET['date_debut'] ?? '';
    $date_fin = $_GET['date_fin'] ?? '';

    $where = "";
    $params = [];
    $conditions = [];

    if ($statut !== 'tous') {
        $conditions[] = "c.statut = ?";
        $params[] = $statut;
    }

    if (!empty($date_debut)) {
        $conditions[] = "c.dateCommande >= ?";
        $params[] = $date_debut;
    }

    if (!empty($date_fin)) {
        // Ajouter un jour pour inclure toute la journée de fin
        $date_fin_limit = date('Y-m-d', strtotime($date_fin . ' +1 day'));
        $conditions[] = "c.dateCommande < ?";
        $params[] = $date_fin_limit;
    }

    if (!empty($conditions)) {
        $where = "WHERE " . implode(" AND ", $conditions);
    }

    $stmt = $pdo->prepare("
        SELECT c.idCommande, c.dateCommande, c.montantTotal, c.statut, c.delaiLivraison,
               cl.nom, cl.prenom, cl.email, cl.telephone,
               a.adresse, a.codePostal, a.ville, a.pays
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        JOIN Adresse a ON c.idAdresseLivraison = a.idAdresse
        $where
        ORDER BY c.dateCommande DESC
    ");
    $stmt->execute($params);
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
    <title>Gestion des Commandes - Youki and Co</title>
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

        /* ===== FILTRES RESPONSIVES ===== */
        .filters {
            background: white;
            padding: 20px 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-label {
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }

        .filter-select, .filter-input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            width: 100%;
            transition: border-color 0.3s;
        }

        .filter-select:focus, .filter-input:focus {
            border-color: #d40000;
            outline: none;
        }

        .date-filters {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-filter, .btn-clear {
            padding: 12px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
            flex: 1;
            font-weight: 500;
        }

        .btn-filter {
            background: #d40000;
            color: white;
        }

        .btn-filter:hover {
            background: #b30000;
            transform: translateY(-1px);
        }

        .btn-clear {
            background: #6c757d;
            color: white;
        }

        .btn-clear:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        @media (min-width: 480px) {
            .date-filters {
                flex-direction: row;
                align-items: end;
            }
            .date-filters .filter-group {
                flex: 1;
            }
        }

        @media (min-width: 768px) {
            .filter-form {
                flex-direction: row;
                align-items: end;
                gap: 20px;
            }
            .filter-group {
                min-width: 200px;
            }
            .date-filters {
                flex: 1;
            }
            .filter-buttons {
                margin-top: 0;
                flex: 0 0 auto;
            }
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

        .table-commandes {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .table-commandes th, .table-commandes td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .table-commandes th {
            background: #f8f9fa;
            font-weight: 600;
            white-space: nowrap;
            position: sticky;
            top: 0;
        }

        .table-commandes tbody tr:hover {
            background: #f8f9fa;
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
            flex-shrink: 0;
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
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid transparent;
        }

        .status-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .status-en_attente_paiement {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }

        .status-payee {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #b8daff;
        }

        .status-expediee {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .status-livree {
            background: #28a745;
            color: white;
            border-color: #1e7e34;
        }

        .status-annulee {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        /* ===== ACTIONS MOBILE ===== */
        .order-actions-mobile {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .btn-mobile {
            padding: 10px 12px;
            background: #d40000;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 12px;
            text-align: center;
            flex: 1;
            font-weight: 500;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-width: 100px;
            border: none;
            cursor: pointer;
        }

        .btn-mobile:hover {
            background: #b30000;
            transform: translateY(-1px);
        }

        .btn-mobile-confirm {
            background: #28a745;
        }

        .btn-mobile-confirm:hover {
            background: #218838;
        }

        .btn-mobile-ship {
            background: #17a2b8;
        }

        .btn-mobile-ship:hover {
            background: #138496;
        }

        .btn-mobile-deliver {
            background: #20c997;
        }

        .btn-mobile-deliver:hover {
            background: #1ba87e;
        }

        .btn-mobile-cancel {
            background: #dc3545;
        }

        .btn-mobile-cancel:hover {
            background: #c82333;
        }

        .btn-mobile-details {
            background: #6c757d;
        }

        .btn-mobile-details:hover {
            background: #5a6268;
        }

        /* ===== ACTIONS DESKTOP ===== */
        .order-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 6px 10px;
            font-size: 11px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            white-space: nowrap;
            transition: all 0.3s;
            font-weight: 500;
        }

        .btn-small:hover {
            transform: translateY(-1px);
        }

        .btn-confirm { background: #28a745; color: white; }
        .btn-ship { background: #17a2b8; color: white; }
        .btn-deliver { background: #20c997; color: white; }
        .btn-cancel { background: #dc3545; color: white; }
        .btn-details { background: #6c757d; color: white; }

        /* ===== FORMULAIRES ===== */
        .action-form {
            display: inline;
            margin: 0;
            padding: 0;
        }

        .statut-form {
            display: none;
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

            /* Filtres desktop */
            .filters {
                padding: 25px;
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

            /* Table desktop */
            .table-commandes-container {
                overflow-x: auto;
                margin: 0;
                padding: 0;
            }

            .table-commandes {
                min-width: 1000px;
            }

            .status-badge {
                font-size: 12px;
                min-width: 120px;
            }

            .btn-small {
                font-size: 12px;
                padding: 8px 12px;
            }
        }

        /* ===== AMÉLIORATIONS TRÈS PETITS ÉCRANS ===== */
        @media (max-width: 360px) {
            .main-content {
                padding: 12px;
            }

            .filters {
                padding: 15px 12px;
            }

            .section {
                padding: 15px 12px;
            }

            .order-card {
                padding: 14px;
            }

            .btn-mobile {
                padding: 9px 10px;
                font-size: 11px;
                min-width: 90px;
            }

            .nav-item {
                padding: 14px 16px;
                font-size: 14px;
            }

            .info-label {
                min-width: 70px;
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

            .filters {
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
            .sidebar, .sidebar-overlay, .order-card, .status-badge, .btn-mobile, .btn-small {
                transition: none;
            }
        }

        /* ===== IMPRESSION ===== */
        @media print {
            .sidebar, .mobile-menu-toggle, .btn-logout, .filters, .order-actions, .order-actions-mobile {
                display: none;
            }

            .container {
                flex-direction: column;
            }

            .main-content {
                padding: 0;
            }

            .section {
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
            <a href="admin_commandes.php" class="nav-item active">📦 Gestion des Commandes</a>
            <a href="admin_factures.php" class="nav-item">📄 Gestion des Factures</a>
            <a href="admin_clients.php" class="nav-item">👥 Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item">🎨 Gestion des Produits</a>
        </div>

        <div class="main-content">
            <!-- Filtres -->
            <div class="filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label class="filter-label">Statut</label>
                        <select name="statut" class="filter-select" onchange="this.form.submit()">
                            <option value="tous" <?= $statut === 'tous' ? 'selected' : '' ?>>Tous les statuts</option>
                            <option value="en_attente_paiement" <?= $statut === 'en_attente_paiement' ? 'selected' : '' ?>>En attente paiement</option>
                            <option value="payee" <?= $statut === 'payee' ? 'selected' : '' ?>>Payée</option>
                            <option value="expediee" <?= $statut === 'expediee' ? 'selected' : '' ?>>Expédiée</option>
                            <option value="livree" <?= $statut === 'livree' ? 'selected' : '' ?>>Livrée</option>
                            <option value="annulee" <?= $statut === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                        </select>
                    </div>

                    <div class="date-filters">
                        <div class="filter-group">
                            <label class="filter-label">Date de début</label>
                            <input type="date" name="date_debut" class="filter-input" value="<?= htmlspecialchars($date_debut) ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Date de fin</label>
                            <input type="date" name="date_fin" class="filter-input" value="<?= htmlspecialchars($date_fin) ?>">
                        </div>

                        <div class="filter-buttons">
                            <button type="submit" class="btn-filter">🔍 Filtrer</button>
                            <a href="admin_commandes.php" class="btn-clear">🗑️ Effacer</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Liste des commandes -->
            <div class="section">
                <h2>📦 Liste des Commandes (<?= count($commandes) ?>)</h2>

                <?php if (empty($commandes)): ?>
                    <div class="no-orders">
                        Aucune commande trouvée pour les critères sélectionnés.
                    </div>
                <?php else: ?>

                <!-- Version Desktop (tableau) -->
                <div class="table-container">
                    <div class="table-commandes-container">
                        <table class="table-commandes">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Adresse</th>
                                    <th>Montant</th>
                                    <th>Livraison</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($commandes as $commande): ?>
                                <tr>
                                    <td><strong>#<?= $commande['idCommande'] ?></strong></td>
                                    <td><?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></strong><br>
                                        <?= htmlspecialchars($commande['email']) ?><br>
                                        <small><?= htmlspecialchars($commande['telephone']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($commande['adresse']) ?><br>
                                        <?= htmlspecialchars($commande['codePostal'] . ' ' . $commande['ville']) ?><br>
                                        <small><?= htmlspecialchars($commande['pays']) ?></small>
                                    </td>
                                    <td><strong><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</strong></td>
                                    <td><?= date('d/m/Y', strtotime($commande['delaiLivraison'])) ?></td>
                                    <td>
                                        <form method="POST" class="statut-form" id="form-<?= $commande['idCommande'] ?>">
                                            <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                            <input type="hidden" name="action" value="changer_statut">
                                            <input type="hidden" name="nouveau_statut" id="nouveau-statut-<?= $commande['idCommande'] ?>" value="">
                                        </form>
                                        <span class="status-badge status-<?= $commande['statut'] ?>"
                                              onclick="changerStatut(<?= $commande['idCommande'] ?>, '<?= $commande['statut'] ?>')"
                                              title="Cliquez pour changer le statut">
                                            <?=
                                                str_replace(
                                                    ['en_attente_paiement', 'payee', 'expediee', 'livree', 'annulee'],
                                                    ['En attente', 'Payée', 'Expédiée', 'Livrée', 'Annulée'],
                                                    $commande['statut']
                                                )
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="order-actions">
                                            <?php if ($commande['statut'] === 'en_attente_paiement'): ?>
                                                <form method="POST" class="action-form">
                                                    <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                                    <button type="submit" name="action" value="confirmer" class="btn-small btn-confirm" onclick="return confirm('Confirmer le paiement de cette commande ?')">Confirmer</button>
                                                </form>
                                                <form method="POST" class="action-form">
                                                    <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                                    <button type="submit" name="action" value="annuler" class="btn-small btn-cancel" onclick="return confirm('Annuler cette commande ?')">Annuler</button>
                                                </form>
                                            <?php elseif ($commande['statut'] === 'payee'): ?>
                                                <form method="POST" class="action-form">
                                                    <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                                    <button type="submit" name="action" value="expedier" class="btn-small btn-ship" onclick="return confirm('Marquer cette commande comme expédiée ?')">Expédier</button>
                                                </form>
                                            <?php elseif ($commande['statut'] === 'expediee'): ?>
                                                <form method="POST" class="action-form">
                                                    <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                                    <button type="submit" name="action" value="livrer" class="btn-small btn-deliver" onclick="return confirm('Marquer cette commande comme livrée ?')">Livrer</button>
                                                </form>
                                            <?php endif; ?>

                                            <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-small btn-details">Détails</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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
                                <span class="info-label">Client:</span>
                                <span class="info-value"><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?= htmlspecialchars($commande['email']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Téléphone:</span>
                                <span class="info-value"><?= htmlspecialchars($commande['telephone']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Adresse:</span>
                                <span class="info-value"><?= htmlspecialchars($commande['adresse']) ?>, <?= htmlspecialchars($commande['codePostal'] . ' ' . $commande['ville']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Montant:</span>
                                <span class="info-value"><strong><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</strong></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Livraison:</span>
                                <span class="info-value"><?= date('d/m/Y', strtotime($commande['delaiLivraison'])) ?></span>
                            </div>
                        </div>

                        <div class="mobile-status">
                            <div class="status-text">Statut de la commande</div>
                            <form method="POST" class="statut-form" id="form-mobile-<?= $commande['idCommande'] ?>">
                                <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                <input type="hidden" name="action" value="changer_statut">
                                <input type="hidden" name="nouveau_statut" id="nouveau-statut-mobile-<?= $commande['idCommande'] ?>" value="">
                            </form>
                            <span class="status-badge status-<?= $commande['statut'] ?>"
                                  onclick="changerStatut(<?= $commande['idCommande'] ?>, '<?= $commande['statut'] ?>')"
                                  title="Cliquez pour changer le statut">
                                <?=
                                    str_replace(
                                        ['en_attente_paiement', 'payee', 'expediee', 'livree', 'annulee'],
                                        ['En attente', 'Payée', 'Expédiée', 'Livrée', 'Annulée'],
                                        $commande['statut']
                                    )
                                ?>
                            </span>
                        </div>

                        <div class="order-actions-mobile">
                            <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-mobile btn-mobile-details">
                                <span>👁️</span>
                                <span>Détails</span>
                            </a>

                            <?php if ($commande['statut'] === 'en_attente_paiement'): ?>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                    <button type="submit" name="action" value="confirmer" class="btn-mobile btn-mobile-confirm" onclick="return confirm('Confirmer le paiement de cette commande ?')">
                                        <span>✅</span>
                                        <span>Confirmer</span>
                                    </button>
                                </form>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                    <button type="submit" name="action" value="annuler" class="btn-mobile btn-mobile-cancel" onclick="return confirm('Annuler cette commande ?')">
                                        <span>❌</span>
                                        <span>Annuler</span>
                                    </button>
                                </form>
                            <?php elseif ($commande['statut'] === 'payee'): ?>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                    <button type="submit" name="action" value="expedier" class="btn-mobile btn-mobile-ship" onclick="return confirm('Marquer cette commande comme expédiée ?')">
                                        <span>🚚</span>
                                        <span>Expédier</span>
                                    </button>
                                </form>
                            <?php elseif ($commande['statut'] === 'expediee'): ?>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                    <button type="submit" name="action" value="livrer" class="btn-mobile btn-mobile-deliver" onclick="return confirm('Marquer cette commande comme livrée ?')">
                                        <span>📦</span>
                                        <span>Livrer</span>
                                    </button>
                                </form>
                            <?php endif; ?>
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

        // Fonction pour changer le statut d'une commande
        function changerStatut(idCommande, statutActuel) {
            // Déterminer le prochain statut dans le cycle
            let prochainStatut;
            switch(statutActuel) {
                case 'en_attente_paiement':
                    prochainStatut = 'payee';
                    break;
                case 'payee':
                    prochainStatut = 'expediee';
                    break;
                case 'expediee':
                    prochainStatut = 'livree';
                    break;
                case 'livree':
                    prochainStatut = 'annulee';
                    break;
                case 'annulee':
                    prochainStatut = 'en_attente_paiement';
                    break;
                default:
                    prochainStatut = 'en_attente_paiement';
            }

            // Traduire les statuts pour l'affichage utilisateur
            const statutsTraduits = {
                'en_attente_paiement': 'En attente de paiement',
                'payee': 'Payée',
                'expediee': 'Expédiée',
                'livree': 'Livrée',
                'annulee': 'Annulée'
            };

            // Confirmer le changement
            if (confirm(`Changer le statut de la commande #${idCommande} de "${statutsTraduits[statutActuel]}" à "${statutsTraduits[prochainStatut]}" ?`)) {
                // Mettre à jour le champ caché et soumettre le formulaire
                const formDesktop = document.getElementById(`nouveau-statut-${idCommande}`);
                const formMobile = document.getElementById(`nouveau-statut-mobile-${idCommande}`);

                if (formDesktop) formDesktop.value = prochainStatut;
                if (formMobile) formMobile.value = prochainStatut;

                document.getElementById(`form-${idCommande}`).submit();
            }
        }
    </script>
</body>
</html>
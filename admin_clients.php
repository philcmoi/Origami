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

        /* Header optimisé mobile */
        .header {
            background: white;
            padding: 12px 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo h1 {
            color: #d40000;
            font-size: 18px;
            text-align: center;
            margin-bottom: 10px;
        }

        .admin-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-align: center;
            font-size: 13px;
        }

        .btn-logout {
            background: #d40000;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
            display: inline-block;
        }

        @media (min-width: 768px) {
            .header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 15px 20px;
            }
            .logo h1 {
                font-size: 22px;
                text-align: left;
                margin-bottom: 0;
            }
            .admin-info {
                flex-direction: row;
                align-items: center;
                gap: 15px;
                text-align: left;
            }
        }

        /* Container principal optimisé mobile */
        .container {
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 60px);
        }

        .mobile-menu-toggle {
            display: block;
            background: #d40000;
            color: white;
            border: none;
            padding: 12px 15px;
            border-radius: 0;
            cursor: pointer;
            width: 100%;
            font-size: 14px;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .sidebar {
            background: white;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
            position: sticky;
            top: 44px;
            z-index: 998;
            max-height: calc(100vh - 104px);
            overflow-y: auto;
        }

        .nav-item {
            display: block;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 5px;
            transition: background 0.3s;
            text-align: center;
            font-size: 14px;
        }

        .nav-item:hover, .nav-item.active {
            background: #d40000;
            color: white;
        }

        .main-content {
            flex: 1;
            padding: 15px;
        }

        @media (min-width: 992px) {
            .container {
                flex-direction: row;
            }
            .mobile-menu-toggle {
                display: none;
            }
            .sidebar {
                display: block;
                width: 250px;
                position: static;
                max-height: none;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }
            .nav-item {
                text-align: left;
            }
            .main-content {
                padding: 20px;
            }
        }

        /* Statistiques clients optimisées mobile */
        .client-stats {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 15px;
        }

        @media (min-width: 380px) {
            .client-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 20px;
            font-weight: bold;
            color: #d40000;
            margin-bottom: 5px;
        }

        .stat-card div:last-child {
            font-size: 13px;
            color: #666;
        }

        @media (min-width: 768px) {
            .client-stats {
                gap: 20px;
                margin-bottom: 20px;
            }
            .stat-card {
                padding: 20px;
                border-radius: 10px;
            }
            .stat-number {
                font-size: 24px;
            }
        }

        /* Section principale */
        .section {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }

        .section h2 {
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
            text-align: center;
        }

        .no-clients {
            text-align: center;
            padding: 30px 20px;
            color: #666;
            font-size: 14px;
            font-style: italic;
        }

        @media (min-width: 768px) {
            .section {
                padding: 20px;
                border-radius: 10px;
            }
            .section h2 {
                font-size: 22px;
                text-align: left;
            }
        }

        /* Tableau desktop (caché sur mobile) */
        .table-container {
            overflow-x: auto;
            display: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            white-space: nowrap;
        }

        .client-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-view, .btn-edit {
            padding: 6px 10px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            display: inline-block;
            text-align: center;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-edit {
            background: #ffc107;
            color: black;
        }

        @media (min-width: 768px) {
            th, td {
                padding: 12px;
                font-size: 14px;
            }
        }

        /* Cartes mobile (affichées par défaut) */
        .cards-container {
            display: block;
        }

        .client-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            border-left: 4px solid #d40000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .client-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ddd;
        }

        .client-id {
            font-weight: bold;
            color: #d40000;
            font-size: 15px;
        }

        .client-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 8px;
            color: #333;
        }

        .client-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            padding-bottom: 6px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        .client-label {
            font-weight: 600;
            color: #666;
            min-width: 100px;
            flex-shrink: 0;
        }

        .client-value {
            flex: 1;
            text-align: right;
            padding-left: 10px;
            word-break: break-word;
        }

        .client-actions-mobile {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .btn-mobile {
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 12px;
            flex: 1;
            text-align: center;
            min-width: 120px;
            max-width: calc(50% - 4px);
        }

        /* Affichage conditionnel desktop/mobile */
        @media (min-width: 1200px) {
            .cards-container {
                display: none;
            }
            .table-container {
                display: block;
            }
        }

        @media (max-width: 1199px) {
            .cards-container {
                display: block;
            }
            .table-container {
                display: none;
            }
        }

        /* Améliorations pour très petits écrans */
        @media (max-width: 380px) {
            .client-stats {
                grid-template-columns: 1fr;
            }

            .client-card-header {
                flex-direction: column;
                gap: 8px;
            }

            .client-actions-mobile {
                flex-direction: column;
            }

            .btn-mobile {
                max-width: 100%;
                min-width: auto;
            }

            .client-info-row {
                flex-direction: column;
                gap: 2px;
            }

            .client-label, .client-value {
                text-align: left;
                min-width: auto;
            }
        }

        /* États de hover pour mobile */
        @media (hover: hover) {
            .btn-view:hover, .btn-edit:hover {
                opacity: 0.9;
                transform: translateY(-1px);
            }

            .client-card:hover {
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
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
            <span>Admin: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="admin_dashboard.php?logout=1" class="btn-logout">Déconnexion</a>
        </div>
    </div>

    <div class="container">
        <button class="mobile-menu-toggle" id="mobileMenuToggle">☰ Menu Admin</button>

        <div class="sidebar" id="sidebar">
            <a href="admin_dashboard.php" class="nav-item">Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">Commandes</a>
            <a href="admin_factures.php" class="nav-item">Factures</a>
            <a href="admin_clients.php" class="nav-item active">Clients</a>
            <a href="admin_produits.php" class="nav-item">Produits</a>
        </div>

        <div class="main-content">
            <div class="client-stats">
                <div class="stat-card">
                    <div class="stat-number"><?= count($clients) ?></div>
                    <div>Clients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?= number_format(array_sum(array_column($clients, 'total_achats')), 2, ',', ' ') ?>€
                    </div>
                    <div>Chiffre d'affaires</div>
                </div>
            </div>

            <div class="section">
                <h2>Liste des Clients (<?= count($clients) ?>)</h2>

                <!-- Version Desktop (tableau) -->
                <div class="table-container">
                    <?php if (empty($clients)): ?>
                        <div class="no-clients">
                            Aucun client trouvé
                        </div>
                    <?php else: ?>
                        <table>
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
                                    <td>#<?= $client['idClient'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($client['email']) ?></td>
                                    <td><?= htmlspecialchars($client['telephone'] ?? 'Non renseigné') ?></td>
                                    <td><?= date('d/m/Y', strtotime($client['date_creation'])) ?></td>
                                    <td><?= $client['nb_commandes'] ?></td>
                                    <td><?= number_format($client['total_achats'], 2, ',', ' ') ?>€</td>
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
                    <?php endif; ?>
                </div>

                <!-- Version Mobile (cartes) -->
                <div class="cards-container">
                    <?php if (empty($clients)): ?>
                        <div class="no-clients">
                            Aucun client trouvé
                        </div>
                    <?php else: ?>
                        <?php foreach ($clients as $client): ?>
                        <div class="client-card">
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

                            <div class="client-info-row">
                                <span class="client-label">Email:</span>
                                <span class="client-value"><?= htmlspecialchars($client['email']) ?></span>
                            </div>

                            <div class="client-info-row">
                                <span class="client-label">Téléphone:</span>
                                <span class="client-value"><?= htmlspecialchars($client['telephone'] ?? 'Non renseigné') ?></span>
                            </div>

                            <div class="client-info-row">
                                <span class="client-label">Inscription:</span>
                                <span class="client-value"><?= date('d/m/Y', strtotime($client['date_creation'])) ?></span>
                            </div>

                            <div class="client-info-row">
                                <span class="client-label">Commandes:</span>
                                <span class="client-value"><?= $client['nb_commandes'] ?></span>
                            </div>

                            <div class="client-info-row">
                                <span class="client-label">Total Achats:</span>
                                <span class="client-value"><?= number_format($client['total_achats'], 2, ',', ' ') ?>€</span>
                            </div>

                            <div class="client-actions-mobile">
                                <a href="admin_client_detail.php?id=<?= $client['idClient'] ?>" class="btn-view btn-mobile">Voir détails</a>
                                <a href="admin_client_edit.php?id=<?= $client['idClient'] ?>" class="btn-edit btn-mobile">Modifier</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle menu mobile
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar.style.display === 'block') {
                sidebar.style.display = 'none';
            } else {
                sidebar.style.display = 'block';
            }
        });

        // Masquer le sidebar sur mobile au chargement
        window.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth < 992) {
                document.getElementById('sidebar').style.display = 'none';
            }
        });

        // Gérer le redimensionnement de la fenêtre
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth >= 992) {
                sidebar.style.display = 'block';
            } else {
                sidebar.style.display = 'none';
            }
        });

        // Fermer le menu en cliquant à l'extérieur (sur mobile)
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('mobileMenuToggle');

            if (window.innerWidth < 992 &&
                sidebar.style.display === 'block' &&
                !sidebar.contains(event.target) &&
                !menuToggle.contains(event.target)) {
                sidebar.style.display = 'none';
            }
        });

        // Amélioration du scroll pour mobile
        let touchStartY = 0;
        document.addEventListener('touchstart', function(e) {
            touchStartY = e.touches[0].clientY;
        }, { passive: true });

        // Prévenir le zoom accidentel sur les boutons
        document.addEventListener('touchmove', function(e) {
            if (e.scale !== 1) {
                e.preventDefault();
            }
        }, { passive: false });
    </script>
</body>
</html>

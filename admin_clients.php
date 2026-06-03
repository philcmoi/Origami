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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Gestion des Clients - Youki and Co</title>
    <style>
        /* Reset et base responsive */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        /* Header responsive */
        .header {
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            .logo h1 {
                font-size: 1.2rem;
            }
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
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            display: inline-block;
        }
        
        /* Container responsive */
        .container {
            display: flex;
            flex-wrap: wrap;
            min-height: calc(100vh - 80px);
        }
        
        /* Sidebar responsive */
        .sidebar {
            width: 250px;
            background: white;
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                box-shadow: none;
                border-bottom: 1px solid #eee;
                padding: 10px 20px;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .sidebar .nav-item {
                display: inline-block;
                margin-right: 10px;
                margin-bottom: 0;
            }
        }
        
        .nav-item {
            display: block;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 5px;
            transition: background 0.3s;
        }
        
        .nav-item:hover, .nav-item.active {
            background: #d40000;
            color: white;
        }
        
        /* Main content responsive */
        .main-content {
            flex: 1;
            padding: 20px;
            min-width: 0; /* Évite le débordement sur flex */
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
        }
        
        /* Statistiques responsives */
        .client-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #d40000;
            word-break: break-word;
        }
        
        @media (max-width: 480px) {
            .stat-number {
                font-size: 1.2rem;
            }
        }
        
        /* Section responsive */
        .section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow-x: auto;
        }
        
        .section h2 {
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        /* Table responsive - version scrollable */
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -5px;
            padding: 0 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        th, td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        @media (max-width: 640px) {
            th, td {
                padding: 10px 8px;
                font-size: 0.85rem;
            }
        }
        
        /* Version carte pour très petits écrans (alternative) */
        @media (max-width: 480px) {
            .client-cards {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            
            .client-card {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 15px;
                border-left: 3px solid #d40000;
            }
            
            .client-card .client-name {
                font-weight: bold;
                font-size: 1rem;
                margin-bottom: 5px;
            }
            
            .client-card .client-email {
                font-size: 0.8rem;
                color: #666;
                margin-bottom: 8px;
                word-break: break-all;
            }
            
            .client-card .client-info {
                font-size: 0.75rem;
                color: #888;
                margin-bottom: 10px;
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .client-actions {
                display: flex;
                gap: 10px;
                margin-top: 10px;
            }
            
            /* Masquer le tableau sur mobile */
            .desktop-table {
                display: none;
            }
        }
        
        /* Afficher le tableau sur les écrans > 480px */
        @media (min-width: 481px) {
            .mobile-cards {
                display: none;
            }
        }
        
        .client-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn-view { 
            background: #17a2b8; 
            color: white; 
            padding: 5px 12px; 
            text-decoration: none; 
            border-radius: 3px; 
            font-size: 0.75rem;
            display: inline-block;
        }
        
        .btn-edit { 
            background: #ffc107; 
            color: black; 
            padding: 5px 12px; 
            text-decoration: none; 
            border-radius: 3px; 
            font-size: 0.75rem;
            display: inline-block;
        }
        
        @media (max-width: 480px) {
            .btn-view, .btn-edit {
                padding: 8px 12px;
                font-size: 0.8rem;
            }
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 0.75rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>Youki and Co - Gestion des Clients</h1>
        </div>
        <div class="admin-info">
            <span>Connecté: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="admin_dashboard.php?logout=1" class="btn-logout">Déconnexion</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
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
                <h2>Clients</h2>
                
                <!-- Version tableau pour desktop -->
                <div class="desktop-table table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Inscription</th>
                                <th>Commandes</th>
                                <th>Total Achats</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                            <tr>
                                <td>#<?= $client['idClient'] ?></td>
                                <td><strong><?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?></strong></td>
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
                </div>
                
                <!-- Version cartes pour mobile -->
                <div class="mobile-cards">
                    <?php foreach ($clients as $client): ?>
                    <div class="client-card">
                        <div class="client-name">
                            <strong>#<?= $client['idClient'] ?></strong> - 
                            <?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?>
                        </div>
                        <div class="client-email">📧 <?= htmlspecialchars($client['email']) ?></div>
                        <div class="client-info">
                            <span>📞 <?= htmlspecialchars($client['telephone'] ?? 'Non renseigné') ?></span>
                            <span>📅 <?= date('d/m/Y', strtotime($client['date_creation'])) ?></span>
                            <span>📦 <?= $client['nb_commandes'] ?> commandes</span>
                            <span>💰 <?= number_format($client['total_achats'], 2, ',', ' ') ?>€</span>
                        </div>
                        <div class="client-actions">
                            <a href="admin_client_detail.php?id=<?= $client['idClient'] ?>" class="btn-view">👁️ Voir détails</a>
                            <a href="admin_client_edit.php?id=<?= $client['idClient'] ?>" class="btn-edit">✏️ Éditer</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; <?= date('Y') ?> Youki and Co - Tous droits réservés</p>
    </div>
</body>
</html>
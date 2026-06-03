<?php
session_start();

require_once 'config.php';

// Vérifier la connexion administrateur
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Vérifier que l'ID client est présent
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: admin_clients.php');
    exit;
}

$client_id = intval($_GET['id']);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les informations du client
    $stmt_client = $pdo->prepare("
        SELECT * FROM Client 
        WHERE idClient = ?
    ");
    $stmt_client->execute([$client_id]);
    $client = $stmt_client->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        die("Client non trouvé");
    }

    // Récupérer les adresses du client
    $stmt_adresses = $pdo->prepare("
        SELECT * FROM Adresse 
        WHERE idClient = ?
        ORDER BY type, dateCreation DESC
    ");
    $stmt_adresses->execute([$client_id]);
    $adresses = $stmt_adresses->fetchAll(PDO::FETCH_ASSOC);

    // Séparer les adresses de livraison et facturation
    $adresse_livraison = null;
    $adresse_facturation = null;
    
    foreach ($adresses as $adresse) {
        if ($adresse['type'] == 'livraison') {
            $adresse_livraison = $adresse;
        } elseif ($adresse['type'] == 'facturation') {
            $adresse_facturation = $adresse;
        }
    }

    // Récupérer les commandes du client
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

    // Calculer les statistiques du client
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
    die("Erreur de base de données: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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
            min-width: 0;
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
        }
        
        /* Page header responsive */
        .page-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .page-title h2 {
            font-size: 1.5rem;
            color: #333;
        }
        
        @media (max-width: 480px) {
            .page-title h2 {
                font-size: 1.2rem;
            }
        }
        
        .breadcrumb {
            font-size: 0.75rem;
            color: #666;
            word-break: break-word;
        }
        
        .breadcrumb a {
            color: #d40000;
            text-decoration: none;
        }
        
        /* Stats grid responsive */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.3rem;
            font-weight: bold;
            color: #d40000;
            word-break: break-word;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: #666;
        }
        
        @media (max-width: 480px) {
            .stat-number {
                font-size: 1rem;
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
        
        .section h3 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            font-size: 1.2rem;
        }
        
        /* Info grid responsive */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        @media (max-width: 480px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .info-group {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
            font-size: 0.7rem;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #333;
            font-size: 0.9rem;
            word-break: break-word;
        }
        
        /* Addresses comparison responsive */
        .addresses-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .addresses-comparison {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        .address-column {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        
        .address-title {
            font-weight: bold;
            color: #d40000;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
            font-size: 0.9rem;
        }
        
        /* Table responsive */
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -5px;
            padding: 0 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px;
        }
        
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        td {
            font-size: 0.85rem;
        }
        
        @media (max-width: 480px) {
            th, td {
                padding: 8px;
                font-size: 0.75rem;
            }
        }
        
        /* Badges */
        .status-badge {
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: bold;
            display: inline-block;
        }
        
        .status-en_attente { background: #fff3cd; color: #856404; }
        .status-confirmee { background: #d1ecf1; color: #0c5460; }
        .status-expediee { background: #d4edda; color: #155724; }
        .status-livree { background: #e2e3e5; color: #383d41; }
        
        .type-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .type-permanent { background: #d4edda; color: #155724; }
        .type-temporaire { background: #fff3cd; color: #856404; }
        
        .email-confirmed { color: #28a745; font-weight: bold; font-size: 0.7rem; }
        .email-pending { color: #dc3545; font-weight: bold; font-size: 0.7rem; }
        
        .btn-action {
            padding: 5px 10px;
            background: #d40000;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 0.7rem;
            display: inline-block;
        }
        
        .btn-secondary {
            padding: 5px 10px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 0.7rem;
            display: inline-block;
        }
        
        @media (max-width: 480px) {
            .btn-action, .btn-secondary {
                padding: 8px 12px;
                font-size: 0.8rem;
            }
        }
        
        .same-address-notice {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            text-align: center;
            margin-top: 15px;
            font-size: 0.8rem;
        }
        
        .address-card {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        /* Version mobile commandes en carte */
        @media (max-width: 480px) {
            .orders-cards {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            
            .order-card {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 12px;
                border-left: 3px solid #d40000;
            }
            
            .order-card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
                flex-wrap: wrap;
                gap: 8px;
            }
            
            .order-id {
                font-weight: bold;
                font-size: 0.9rem;
            }
            
            .order-date {
                font-size: 0.7rem;
                color: #666;
            }
            
            .order-details {
                font-size: 0.8rem;
                margin-bottom: 10px;
            }
            
            .desktop-table {
                display: none;
            }
        }
        
        @media (min-width: 481px) {
            .mobile-orders {
                display: none;
            }
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 0.7rem;
            color: #666;
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
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="nav-item">Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">Commandes</a>
            <a href="admin_factures.php" class="nav-item">Factures</a>
            <a href="admin_clients.php" class="nav-item active">Clients</a>
            <a href="admin_produits.php" class="nav-item">Produits</a>
        </div>
        
        <div class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <h2>Détails du Client</h2>
                    <div class="breadcrumb">
                        <a href="admin_dashboard.php">Tableau de bord</a> &gt; 
                        <a href="admin_clients.php">Clients</a> &gt; 
                        Client #<?= $client_id ?>
                    </div>
                </div>
                <div>
                    <a href="admin_clients.php" class="btn-secondary">← Retour</a>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_commandes'] ?? 0 ?></div>
                    <div class="stat-label">Commandes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['total_depense'] ?? 0, 2, ',', ' ') ?>€</div>
                    <div class="stat-label">Total dépensé</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['moyenne_commande'] ?? 0, 2, ',', ' ') ?>€</div>
                    <div class="stat-label">Moyenne/commande</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?= $stats['premiere_commande'] ? date('d/m/Y', strtotime($stats['premiere_commande'])) : 'N/A' ?>
                    </div>
                    <div class="stat-label">1ère commande</div>
                </div>
            </div>
            
            <!-- Informations personnelles -->
            <div class="section">
                <h3>Informations Personnelles</h3>
                <div class="info-grid">
                    <div class="info-group">
                        <div class="info-label">ID Client</div>
                        <div class="info-value">#<?= $client_id ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Nom complet</div>
                        <div class="info-value"><?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Email</div>
                        <div class="info-value">
                            <?= htmlspecialchars($client['email']) ?>
                            <?php if ($client['email_confirme'] == 1): ?>
                                <span class="email-confirmed">✓ Confirmé</span>
                            <?php else: ?>
                                <span class="email-pending">✗ Non confirmé</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Téléphone</div>
                        <div class="info-value"><?= htmlspecialchars($client['telephone'] ?? 'Non renseigné') ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Type de compte</div>
                        <div class="info-value">
                            <span class="type-badge type-<?= $client['type'] ?? 'temporaire' ?>">
                                <?= $client['type'] ?? 'temporaire' ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Date d'inscription</div>
                        <div class="info-value"><?= date('d/m/Y à H:i', strtotime($client['date_creation'])) ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Adresses -->
            <div class="section">
                <h3>Adresses</h3>
                <?php if (count($adresses) > 0): ?>
                    <?php 
                    $has_livraison = !is_null($adresse_livraison);
                    $has_facturation = !is_null($adresse_facturation);
                    $same_address = $has_livraison && $has_facturation && $adresse_livraison['idAdresse'] == $adresse_facturation['idAdresse'];
                    ?>
                    
                    <div class="addresses-comparison">
                        <div class="address-column">
                            <div class="address-title">📍 Livraison</div>
                            <?php if ($has_livraison): ?>
                                <div class="info-value">
                                    <strong><?= htmlspecialchars($adresse_livraison['prenom'] . ' ' . $adresse_livraison['nom']) ?></strong><br>
                                    <?= htmlspecialchars($adresse_livraison['adresse']) ?><br>
                                    <?= htmlspecialchars($adresse_livraison['codePostal'] . ' ' . $adresse_livraison['ville']) ?>
                                </div>
                            <?php else: ?>
                                <p style="color: #666; font-style: italic;">Aucune adresse</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="address-column">
                            <div class="address-title">📄 Facturation</div>
                            <?php if ($has_facturation): ?>
                                <div class="info-value">
                                    <strong><?= htmlspecialchars($adresse_facturation['prenom'] . ' ' . $adresse_facturation['nom']) ?></strong><br>
                                    <?= htmlspecialchars($adresse_facturation['adresse']) ?><br>
                                    <?= htmlspecialchars($adresse_facturation['codePostal'] . ' ' . $adresse_facturation['ville']) ?>
                                </div>
                            <?php else: ?>
                                <p style="color: #666; font-style: italic;">Aucune adresse</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($same_address): ?>
                        <div class="same-address-notice">
                            ℹ️ Les adresses de livraison et facturation sont identiques
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <p>Aucune adresse enregistrée.</p>
                <?php endif; ?>
            </div>
            
            <!-- Historique des commandes -->
            <div class="section">
                <h3>Historique des Commandes</h3>
                <?php if (count($commandes) > 0): ?>
                    
                    <!-- Version tableau desktop -->
                    <div class="desktop-table table-wrapper">
                        <table>
                            <thead>
                                <tr><th>ID</th><th>Date</th><th>Articles</th><th>Montant</th><th>Statut</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($commandes as $commande): ?>
                                <tr>
                                    <td>#<?= $commande['idCommande'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($commande['dateCommande'])) ?></td>
                                    <td><?= $commande['nb_articles'] ?> article(s)</td>
                                    <td><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</td>
                                    <td><span class="status-badge status-<?= $commande['statut'] ?>"><?= $commande['statut'] ?></span></td>
                                    <td><a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-action">Voir</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Version mobile cartes -->
                    <div class="mobile-orders">
                        <?php foreach ($commandes as $commande): ?>
                        <div class="order-card">
                            <div class="order-card-header">
                                <span class="order-id">#<?= $commande['idCommande'] ?></span>
                                <span class="order-date"><?= date('d/m/Y', strtotime($commande['dateCommande'])) ?></span>
                                <span class="status-badge status-<?= $commande['statut'] ?>"><?= $commande['statut'] ?></span>
                            </div>
                            <div class="order-details">
                                <div>📦 <?= $commande['nb_articles'] ?> article(s)</div>
                                <div>💰 <?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</div>
                            </div>
                            <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-action">👁️ Voir détails</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                <?php else: ?>
                    <p>Aucune commande pour ce client.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; <?= date('Y') ?> Youki and Co</p>
    </div>
</body>
</html>
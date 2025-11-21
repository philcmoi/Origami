<?php
session_start();

// Configuration de la base de données
require_once('config.php');

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Client #<?= $client_id ?> - Origami Zen</title>
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
        
        .header {
            background: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo h1 {
            color: #d40000;
            font-size: 24px;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .btn-logout {
            background: #d40000;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .container {
            display: flex;
            min-height: calc(100vh - 80px);
        }
        
        .sidebar {
            width: 250px;
            background: white;
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
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
        
        .main-content {
            flex: 1;
            padding: 30px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title h2 {
            color: #333;
            font-size: 28px;
        }
        
        .breadcrumb {
            color: #666;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #d40000;
            text-decoration: none;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #d40000;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 12px;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .section h3 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-group {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #333;
            font-size: 16px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-en_attente {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmee {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-expediee {
            background: #d4edda;
            color: #155724;
        }
        
        .status-livree {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .btn-action {
            padding: 6px 12px;
            background: #d40000;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
            margin-right: 5px;
        }
        
        .btn-action:hover {
            background: #b30000;
        }
        
        .btn-secondary {
            padding: 6px 12px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
            margin-right: 5px;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .type-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .type-permanent {
            background: #d4edda;
            color: #155724;
        }
        
        .type-temporaire {
            background: #fff3cd;
            color: #856404;
        }
        
        .email-confirmed {
            color: #28a745;
            font-weight: bold;
        }
        
        .email-pending {
            color: #dc3545;
            font-weight: bold;
        }
        
        .address-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .address-type {
            display: inline-block;
            padding: 2px 8px;
            background: #d40000;
            color: white;
            border-radius: 10px;
            font-size: 10px;
            margin-bottom: 5px;
        }
        
        .addresses-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
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
        }
        
        .same-address-notice {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .addresses-comparison {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>Origami Zen - Administration</h1>
        </div>
        <div class="admin-info">
            <span>Connecté en tant que: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="admin_dashboard.php?logout=1" class="btn-logout">Déconnexion</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="nav-item">Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">Gestion des Commandes</a>
            <a href="admin_clients.php" class="nav-item active">Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item">Gestion des Produits</a>
        </div>
        
        <div class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <h2>Détails du Client</h2>
                    <div class="breadcrumb">
                        <a href="admin_dashboard.php">Tableau de bord</a> &gt; 
                        <a href="admin_clients.php">Clients</a> &gt; 
                        Détails client #<?= $client_id ?>
                    </div>
                </div>
                <div>
                    <a href="admin_clients.php" class="btn-secondary">← Retour à la liste</a>
                </div>
            </div>
            
            <!-- Statistiques du client -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_commandes'] ?? 0 ?></div>
                    <div class="stat-label">Commandes totales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['total_depense'] ?? 0, 2, ',', ' ') ?>€</div>
                    <div class="stat-label">Total dépensé</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['moyenne_commande'] ?? 0, 2, ',', ' ') ?>€</div>
                    <div class="stat-label">Moyenne par commande</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?= $stats['premiere_commande'] ? date('d/m/Y', strtotime($stats['premiere_commande'])) : 'N/A' ?>
                    </div>
                    <div class="stat-label">Première commande</div>
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
        
        <!-- Affichage côte à côte pour montrer les deux adresses séparément -->
        <div class="addresses-comparison">
            <!-- Colonne Livraison -->
            <div class="address-column">
                <div class="address-title">
                    📍 Adresse de Livraison 
                    <?php if ($same_address): ?>
                        <span style="font-size: 12px; color: #28a745;">(identique à la facturation)</span>
                    <?php endif; ?>
                </div>
                <?php if ($has_livraison): ?>
                    <div class="info-value">
                        <strong><?= htmlspecialchars($adresse_livraison['prenom'] . ' ' . $adresse_livraison['nom']) ?></strong><br>
                        <?= htmlspecialchars($adresse_livraison['adresse']) ?><br>
                        <?= htmlspecialchars($adresse_livraison['codePostal'] . ' ' . $adresse_livraison['ville']) ?><br>
                        <?= htmlspecialchars($adresse_livraison['pays']) ?><br>
                        <?= htmlspecialchars($adresse_livraison['telephone']) ?>
                        <?php if (!empty($adresse_livraison['societe'])): ?>
                            <br>Société: <?= htmlspecialchars($adresse_livraison['societe']) ?>
                        <?php endif; ?>
                        <?php if (!empty($adresse_livraison['instructions'])): ?>
                            <br>Instructions: <?= htmlspecialchars($adresse_livraison['instructions']) ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #666; font-style: italic;">Aucune adresse de livraison enregistrée</p>
                <?php endif; ?>
            </div>
            
            <!-- Colonne Facturation -->
            <div class="address-column">
                <div class="address-title">
                    📄 Adresse de Facturation
                    <?php if ($same_address): ?>
                        <span style="font-size: 12px; color: #28a745;">(identique à la livraison)</span>
                    <?php endif; ?>
                </div>
                <?php if ($has_facturation): ?>
                    <div class="info-value">
                        <strong><?= htmlspecialchars($adresse_facturation['prenom'] . ' ' . $adresse_facturation['nom']) ?></strong><br>
                        <?= htmlspecialchars($adresse_facturation['adresse']) ?><br>
                        <?= htmlspecialchars($adresse_facturation['codePostal'] . ' ' . $adresse_facturation['ville']) ?><br>
                        <?= htmlspecialchars($adresse_facturation['pays']) ?><br>
                        <?= htmlspecialchars($adresse_facturation['telephone']) ?>
                        <?php if (!empty($adresse_facturation['societe'])): ?>
                            <br>Société: <?= htmlspecialchars($adresse_facturation['societe']) ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #666; font-style: italic;">Aucune adresse de facturation enregistrée</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Affichage des adresses supplémentaires s'il y en a -->
        <?php 
        $adresses_supplementaires = array_filter($adresses, function($adresse) use ($adresse_livraison, $adresse_facturation) {
            return $adresse['idAdresse'] != ($adresse_livraison['idAdresse'] ?? null) && 
                   $adresse['idAdresse'] != ($adresse_facturation['idAdresse'] ?? null);
        });
        ?>
        
        <?php if (count($adresses_supplementaires) > 0): ?>
            <div style="margin-top: 30px;">
                <h4 style="margin-bottom: 15px; color: #666;">Adresses supplémentaires</h4>
                <div class="info-grid">
                    <?php foreach ($adresses_supplementaires as $adresse): ?>
                        <div class="address-card">
                            <span class="address-type"><?= $adresse['type'] ?? 'livraison' ?></span>
                            <div class="info-value">
                                <strong><?= htmlspecialchars($adresse['prenom'] . ' ' . $adresse['nom']) ?></strong><br>
                                <?= htmlspecialchars($adresse['adresse']) ?><br>
                                <?= htmlspecialchars($adresse['codePostal'] . ' ' . $adresse['ville']) ?><br>
                                <?= htmlspecialchars($adresse['pays']) ?><br>
                                <?= htmlspecialchars($adresse['telephone']) ?>
                                <?php if (!empty($adresse['societe'])): ?>
                                    <br>Société: <?= htmlspecialchars($adresse['societe']) ?>
                                <?php endif; ?>
                                <?php if (!empty($adresse['instructions']) && $adresse['type'] == 'livraison'): ?>
                                    <br>Instructions: <?= htmlspecialchars($adresse['instructions']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <p>Aucune adresse enregistrée pour ce client.</p>
    <?php endif; ?>
</div>
            
            <!-- Historique des commandes -->
            <div class="section">
                <h3>Historique des Commandes</h3>
                <?php if (count($commandes) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID Commande</th>
                                <th>Date</th>
                                <th>Articles</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commandes as $commande): ?>
                            <tr>
                                <td>#<?= $commande['idCommande'] ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></td>
                                <td><?= $commande['nb_articles'] ?> article(s)</td>
                                <td><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</td>
                                <td>
                                    <span class="status-badge status-<?= $commande['statut'] ?>">
                                        <?= $commande['statut'] ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-action">Voir</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucune commande pour ce client.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
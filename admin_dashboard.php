<?php
// Inclure la protection au tout début
require_once 'admin_protection.php';

// Configuration de la base de données
$host = 'localhost';
$dbname = 'origami';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer les statistiques
    // Commandes en attente de paiement (uniquement visibles)
    $stmt = $pdo->query("SELECT COUNT(*) FROM Commande WHERE statut = 'en_attente_paiement' AND visible = 1");
    $commandesEnAttentePaiement = $stmt->fetchColumn();
    
    // Clients permanents (uniquement les clients permanents)
    $stmt = $pdo->query("SELECT COUNT(*) FROM Client WHERE (type = 'permanent' OR (type IS NULL AND email NOT LIKE 'temp_%@origamizen.fr'))");
    $clientsPermanents = $stmt->fetchColumn();
    
    // Chiffre d'affaires du mois (uniquement les commandes payées et visibles)
    $stmt = $pdo->query("SELECT SUM(montantTotal) FROM Commande WHERE statut = 'payee' AND visible = 1 AND MONTH(dateCommande) = MONTH(CURRENT_DATE()) AND YEAR(dateCommande) = YEAR(CURRENT_DATE())");
    $chiffreAffaires = $stmt->fetchColumn() ?? 0;
    
    // Récupérer les commandes récentes (uniquement visibles)
    $stmt = $pdo->query("
        SELECT c.idCommande, c.dateCommande, c.montantTotal, c.statut, c.statut_paiement,
               cl.nom, cl.prenom, cl.email
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        WHERE c.visible = 1
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
    <title>Tableau de Bord Admin - Origami Zen</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #d40000;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .section h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
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
        
        .status-en_attente_paiement {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-payee {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-expediee {
            background: #d4edda;
            color: #155724;
        }
        
        .status-livree {
            background: #28a745;
            color: white;
        }
        
        .status-annulee {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn-action {
            padding: 6px 12px;
            background: #d40000;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
            margin-right: 5px;
            display: inline-block;
        }
        
        .btn-action:hover {
            background: #b30000;
        }
        
        .btn-invoice {
            padding: 6px 12px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
            margin-right: 5px;
            display: inline-block;
        }
        
        .btn-invoice:hover {
            background: #218838;
        }
        
        .actions-container {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .paiement-status {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 8px;
            margin-left: 5px;
        }
        
        .paiement-payee {
            background: #d4edda;
            color: #155724;
        }
        
        .paiement-en_attente {
            background: #fff3cd;
            color: #856404;
        }
        
        .paiement-echec {
            background: #f8d7da;
            color: #721c24;
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
            <a href="?logout=1" class="btn-logout">Déconnexion</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="nav-item active">Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">Gestion des Commandes</a>
            <a href="admin_factures.php" class="nav-item">Factures</a>
            <a href="admin_clients.php" class="nav-item">Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item">Gestion des Produits</a>
        </div>
        
        <div class="main-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $commandesEnAttentePaiement ?></div>
                    <div class="stat-label">Commandes en attente de paiement</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $clientsPermanents ?></div>
                    <div class="stat-label">Clients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($chiffreAffaires, 2, ',', ' ') ?>€</div>
                    <div class="stat-label">Chiffre d'affaires ce mois (payé)</div>
                </div>
            </div>
            
            <div class="section">
                <h2>Commandes Récentes</h2>
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
                            <td>#<?= $commande['idCommande'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></td>
                            <td>
                                <?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?>
                                <br><small><?= htmlspecialchars($commande['email']) ?></small>
                            </td>
                            <td><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</td>
                            <td>
                                <span class="status-badge status-<?= $commande['statut'] ?>">
                                    <?= $commande['statut'] ?>
                                </span>
                                <?php if ($commande['statut_paiement']): ?>
                                    <span class="paiement-status paiement-<?= $commande['statut_paiement'] ?>">
                                        <?= $commande['statut_paiement'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-container">
                                    <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-action">Voir</a>
                                    
                                    <?php 
                                    // Permettre la génération de facture pour les commandes payées OU livrées
                                    // même si statut_paiement n'est pas "payee"
                                    if (in_array($commande['statut'], ['payee', 'expediee', 'livree']) || $commande['statut_paiement'] === 'payee'): ?>
                                        <a href="admin_factures.php?action=generer&id=<?= $commande['idCommande'] ?>" class="btn-invoice" target="_blank">
                                            📄 Facture
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
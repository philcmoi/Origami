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
    
    // Récupérer les clients permanents
    $stmt = $pdo->query("
        SELECT c.idClient, c.email, c.nom, c.prenom, c.telephone, c.date_creation,
               COUNT(cmd.idCommande) as nb_commandes,
               COALESCE(SUM(cmd.montantTotal), 0) as total_achats
        FROM Client c
        LEFT JOIN Commande cmd ON c.idClient = cmd.idClient
        WHERE c.type = 'permanent' OR (c.type IS NULL AND c.email NOT LIKE 'temp_%@origamizen.fr')
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
    <title>Gestion des Clients - Origami Zen</title>
    <style>
        /* Styles similaires aux autres pages */
        .client-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
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
        }
        
        .client-actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-view { background: #17a2b8; color: white; padding: 4px 8px; text-decoration: none; border-radius: 3px; font-size: 12px; }
        .btn-edit { background: #ffc107; color: black; padding: 4px 8px; text-decoration: none; border-radius: 3px; font-size: 12px; }
        
        /* Reprendre les styles de base */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; color: #333; }
        .header { background: white; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .logo h1 { color: #d40000; font-size: 24px; }
        .admin-info { display: flex; align-items: center; gap: 15px; }
        .btn-logout { background: #d40000; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-size: 14px; }
        .container { display: flex; min-height: calc(100vh - 80px); }
        .sidebar { width: 250px; background: white; padding: 20px; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .nav-item { display: block; padding: 12px 15px; color: #333; text-decoration: none; border-radius: 5px; margin-bottom: 5px; transition: background 0.3s; }
        .nav-item:hover, .nav-item.active { background: #d40000; color: white; }
        .main-content { flex: 1; padding: 30px; }
        .section { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>Origami Zen - Gestion des Clients</h1>
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
            <div class="client-stats">
                <div class="stat-card">
                    <div class="stat-number"><?= count($clients) ?></div>
                    <div>Clients permanents</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?= number_format(array_sum(array_column($clients, 'total_achats')), 2, ',', ' ') ?>€
                    </div>
                    <div>Chiffre d'affaires total</div>
                </div>
            </div>
            
            <div class="section">
                <h2>Clients</h2>
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
            </div>
        </div>
    </div>
</body>
</html>
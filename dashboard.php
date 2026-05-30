<?php
// dashboard.php - Adapté à la base de données origami
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once 'config.php';

// ============================================
// RÉCUPÉRATION DES STATISTIQUES
// ============================================
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Nombre total de produits (Origami)
    $sql = "SELECT COUNT(*) as total_produits FROM Origami WHERE visible = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $total_produits = $stmt->fetch(PDO::FETCH_ASSOC)['total_produits'];
    
    // Nombre total de commandes
    $sql = "SELECT COUNT(*) as total_commandes FROM Commande";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $total_commandes = $stmt->fetch(PDO::FETCH_ASSOC)['total_commandes'];
    
    // Nombre total de clients
    $sql = "SELECT COUNT(*) as total_clients FROM Client WHERE type = 'permanent'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $total_clients = $stmt->fetch(PDO::FETCH_ASSOC)['total_clients'];
    
    // Commandes en attente
    $sql = "SELECT COUNT(*) as commandes_attente FROM Commande WHERE statut = 'en_attente'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $commandes_attente = $stmt->fetch(PDO::FETCH_ASSOC)['commandes_attente'];
    
    // Chiffre d'affaires total (commandes payées)
    $sql = "SELECT SUM(montantTotal) as chiffre_affaires FROM Commande WHERE statut_paiement = 'payee'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $chiffre_affaires = $stmt->fetch(PDO::FETCH_ASSOC)['chiffre_affaires'] ?? 0;
    
    // Récupérer les commandes récentes
    $sql = "SELECT c.idCommande, c.dateCommande, c.montantTotal, c.statut, 
                   cl.nom, cl.prenom, cl.email
            FROM Commande c
            JOIN Client cl ON c.idClient = cl.idClient
            ORDER BY c.dateCommande DESC 
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $commandes_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error_stats = "Erreur lors du chargement des statistiques: " . $e->getMessage();
}

// Récupérer le nom de l'admin depuis la session
$admin_email = $_SESSION['admin_email'] ?? 'Administrateur';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Youki and Co</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #d40000 0%, #8b0000 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .user-info {
            text-align: right;
        }
        
        .btn-logout {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s;
            display: inline-block;
            margin-top: 10px;
        }
        
        .btn-logout:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 5px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card.products { border-left-color: #2196F3; }
        .stat-card.orders { border-left-color: #4CAF50; }
        .stat-card.clients { border-left-color: #FF9800; }
        .stat-card.revenue { border-left-color: #9C27B0; }
        .stat-card.pending { border-left-color: #FFC107; }
        
        .stat-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }
        
        .stat-card.products .stat-icon { background-color: #2196F3; }
        .stat-card.orders .stat-icon { background-color: #4CAF50; }
        .stat-card.clients .stat-icon { background-color: #FF9800; }
        .stat-card.revenue .stat-icon { background-color: #9C27B0; }
        .stat-card.pending .stat-icon { background-color: #FFC107; }
        
        .stat-title {
            font-size: 16px;
            color: #666;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-change {
            font-size: 14px;
            color: #666;
        }
        
        /* Quick Actions */
        .quick-actions {
            margin-bottom: 40px;
        }
        
        .quick-actions h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .action-card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
            border: 2px solid transparent;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            border-color: #d40000;
        }
        
        .action-icon {
            font-size: 36px;
            margin-bottom: 15px;
            color: #d40000;
        }
        
        .action-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .action-desc {
            font-size: 14px;
            color: #666;
        }
        
        /* Recent Orders */
        .recent-orders {
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
        }
        
        .recent-orders h2 {
            font-size: 24px;
            margin-bottom: 25px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .orders-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-en_attente {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-payee {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-expediee {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .btn-view {
            background-color: #d40000;
            color: white;
            padding: 5px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
        }
        
        .btn-view:hover {
            background-color: #b30000;
        }
        
        /* System Info */
        .system-info {
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        
        .system-info h2 {
            font-size: 24px;
            margin-bottom: 25px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .info-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .info-value {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .user-info {
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .orders-table {
                font-size: 14px;
            }
            
            .orders-table th,
            .orders-table td {
                padding: 8px;
            }
        }
        
        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div>
                    <h1><i class="fas fa-gift"></i> Youki and Co - Administration</h1>
                    <p>Gestion complète de votre boutique d'origami</p>
                </div>
                <div class="user-info">
                    <p>Bienvenue, <strong><?php echo htmlspecialchars($admin_email); ?></strong></p>
                    <a href="admin_logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (isset($error_stats)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_stats); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistiques principales -->
        <div class="stats-grid">
            <div class="stat-card products">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div>
                        <div class="stat-title">Produits</div>
                        <div class="stat-value"><?php echo number_format($total_produits ?? 0); ?></div>
                    </div>
                </div>
                <div class="stat-change">
                    <i class="fas fa-chart-line"></i> Produits en boutique
                </div>
            </div>
            
            <div class="stat-card orders">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div>
                        <div class="stat-title">Commandes</div>
                        <div class="stat-value"><?php echo number_format($total_commandes ?? 0); ?></div>
                    </div>
                </div>
                <div class="stat-change">
                    <i class="fas fa-chart-bar"></i> Commandes totales
                </div>
            </div>
            
            <div class="stat-card clients">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="stat-title">Clients</div>
                        <div class="stat-value"><?php echo number_format($total_clients ?? 0); ?></div>
                    </div>
                </div>
                <div class="stat-change">
                    <i class="fas fa-user-plus"></i> Clients inscrits
                </div>
            </div>
            
            <div class="stat-card revenue">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                    <div>
                        <div class="stat-title">Chiffre d'affaires</div>
                        <div class="stat-value"><?php echo number_format($chiffre_affaires ?? 0, 2, ',', ' '); ?> €</div>
                    </div>
                </div>
                <div class="stat-change">
                    <i class="fas fa-chart-pie"></i> CA total (commandes payées)
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="stat-title">Commandes en attente</div>
                        <div class="stat-value"><?php echo number_format($commandes_attente ?? 0); ?></div>
                    </div>
                </div>
                <div class="stat-change">
                    <i class="fas fa-hourglass-half"></i> À traiter
                </div>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="quick-actions">
            <h2><i class="fas fa-bolt"></i> Actions rapides</h2>
            <div class="actions-grid">
                <a href="admin_produits.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="action-title">Gérer les produits</div>
                    <div class="action-desc">Modifiez, ajoutez ou supprimez des origamis</div>
                </a>
                
                <a href="admin_commandes.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="action-title">Voir les commandes</div>
                    <div class="action-desc">Gérez les commandes clients</div>
                </a>
                
                <a href="admin_factures.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="action-title">Factures</div>
                    <div class="action-desc">Générez et envoyez des factures</div>
                </a>
                
                <a href="admin_clients.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="action-title">Gérer les clients</div>
                    <div class="action-desc">Consultez la liste des clients</div>
                </a>
            </div>
        </div>
        
        <!-- Commandes récentes -->
        <?php if (!empty($commandes_recentes)): ?>
        <div class="recent-orders">
            <h2><i class="fas fa-history"></i> Commandes récentes</h2>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commandes_recentes as $commande): ?>
                    <tr>
                        <td>#<?php echo $commande['idCommande']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($commande['dateCommande'])); ?></td>
                        <td><?php echo htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']); ?></td>
                        <td><?php echo number_format($commande['montantTotal'], 2, ',', ' '); ?> €</td>
                        <td>
                            <span class="status-badge status-<?php echo $commande['statut']; ?>">
                                <?php echo $commande['statut']; ?>
                            </span>
                        </td>
                        <td>
                            <a href="admin_commande_detail.php?id=<?php echo $commande['idCommande']; ?>" class="btn-view">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Informations système -->
        <div class="system-info">
            <h2><i class="fas fa-info-circle"></i> Informations système</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Version PHP</div>
                    <div class="info-value"><?php echo phpversion(); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Base de données</div>
                    <div class="info-value">origami</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Serveur</div>
                    <div class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Apache'; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Dernière connexion</div>
                    <div class="info-value"><?php echo date('d/m/Y H:i:s'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Lien vers l'ancien dashboard -->
        <div style="margin-top: 30px; text-align: center; padding: 20px; background-color: #f0f0f0; border-radius: 12px;">
            <a href="admin_dashboard.php" style="color: #d40000; text-decoration: none;">
                <i class="fas fa-tachometer-alt"></i> Accéder à l'ancien tableau de bord
            </a>
        </div>
    </div>
    
    <script>
        // Animation des cartes statistiques
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('fade-in');
            });
        });
    </script>
</body>
</html>
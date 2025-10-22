<?php

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Connexion à la base de données
$host = 'localhost';
$dbname = 'origami';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les données pour le tableau de bord
$commandesEnAttente = 0;
$revenuMois = 0;
$nouveauxClients = 0;

try {
    $commandesEnAttente = $pdo->query("SELECT COUNT(*) FROM Commande WHERE statut = 'en_attente'")->fetchColumn();
    $revenuMois = $pdo->query("SELECT COALESCE(SUM(montantTotal), 0) FROM Commande WHERE MONTH(dateCommande) = MONTH(CURRENT_DATE()) AND YEAR(dateCommande) = YEAR(CURRENT_DATE())")->fetchColumn();
    $nouveauxClients = $pdo->query("SELECT COUNT(*) FROM Client WHERE DATE(dateInscription) >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)")->fetchColumn();
} catch (Exception $e) {
    // Gérer l'erreur silencieusement ou logger
}

// Récupérer les commandes récentes
$commandes = [];
try {
    $stmt = $pdo->query("
        SELECT c.idCommande, cl.email, c.dateCommande, c.montantTotal, c.statut 
        FROM Commande c 
        JOIN Client cl ON c.idClient = cl.idClient 
        ORDER BY c.dateCommande DESC 
        LIMIT 10
    ");
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Gérer l'erreur
}

// Récupérer les clients récents
$clients = [];
try {
    $stmt = $pdo->query("
        SELECT email, COUNT(c.idCommande) as nbCommandes 
        FROM Client cl 
        LEFT JOIN Commande c ON cl.idClient = c.idClient 
        GROUP BY cl.idClient, cl.email
        ORDER BY MAX(COALESCE(c.dateCommande, cl.dateInscription)) DESC 
        LIMIT 10
    ");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Gérer l'erreur
}
?>
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Origami Zen</title>
    <style>
        /* Style général */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #1a2a3a 0%, #2c3e50 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .logo {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .logo h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .logo span {
            color: #e74c3c;
        }

        .nav-links {
            list-style: none;
        }

        .nav-links li {
            padding: 12px 20px;
            transition: all 0.3s;
        }

        .nav-links li:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .nav-links li.active {
            background-color: rgba(231, 76, 60, 0.2);
            border-left: 4px solid #e74c3c;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .nav-links i {
            margin-right: 10px;
            font-size: 18px;
        }

        /* Contenu principal */
        .main-content {
            flex: 1;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .header h2 {
            font-weight: 600;
            color: #2c3e50;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 15px;
            text-decoration: none;
            font-size: 14px;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        /* Cartes de statistiques */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
        }

        .stat-1 .stat-icon {
            background-color: rgba(52, 152, 219, 0.2);
            color: #3498db;
        }

        .stat-2 .stat-icon {
            background-color: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        .stat-3 .stat-icon {
            background-color: rgba(155, 89, 182, 0.2);
            color: #9b59b6;
        }

        .stat-4 .stat-icon {
            background-color: rgba(241, 196, 15, 0.2);
            color: #f1c40f;
        }

        .stat-info h3 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #7f8c8d;
            font-size: 14px;
        }

        /* Tableaux */
        .tables-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .table-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            font-weight: 600;
            color: #2c3e50;
        }

        .table-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-success {
            background-color: #2ecc71;
            color: white;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-shipped {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-delivered {
            background-color: #d4edda;
            color: #155724;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        /* Formulaire d'ajout */
        .form-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .form-container {
            background: white;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .form-header h3 {
            color: #2c3e50;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #7f8c8d;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .tables-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
            }

            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <h1>Origami<span>Zen</span></h1>
                <p>Tableau de bord</p>
            </div>
            <ul class="nav-links">
                <li class="active">
                    <a href="#"><i>📊</i> Tableau de bord</a>
                </li>
                <li>
                    <a href="#"><i>📦</i> Commandes</a>
                </li>
                <li>
                    <a href="#"><i>👥</i> Clients</a>
                </li>
                <li>
                    <a href="#"><i>📋</i> Inventaire</a>
                </li>
                <li>
                    <a href="#"><i>🚚</i> Expéditions</a>
                </li>
                <li>
                    <a href="#"><i>📈</i> Rapports</a>
                </li>
                <li>
                    <a href="#"><i>⚙️</i> Paramètres</a>
                </li>
            </ul>
        </div>

        <!-- Contenu principal -->
        <div class="main-content">
            <div class="header">
                <h2>Tableau de Bord</h2>
                <div class="user-info">
                    <img
                        src="https://randomuser.me/api/portraits/women/44.jpg"
                        alt="Admin"
                    />
                    <div>
                        <p>Marie Dubois</p>
                        <small>Administratrice</small>
                    </div>
                    <a href="logout.php" class="logout-btn">Déconnexion</a>
                </div>
            </div>

            <!-- Cartes de statistiques -->
            <div class="stats-cards">
                <div class="stat-card stat-1">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <h3><?php echo $commandesEnAttente; ?></h3>
                        <p>Commandes en attente</p>
                    </div>
                </div>
                <div class="stat-card stat-2">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3><?php echo number_format($revenuMois ?? 0, 2, ',', ' '); ?>€</h3>
                        <p>Revenu ce mois</p>
                    </div>
                </div>
                <div class="stat-card stat-3">
                    <div class="stat-icon">👥</div>
                    <div class="stat-info">
                        <h3><?php echo $nouveauxClients; ?></h3>
                        <p>Nouveaux clients</p>
                    </div>
                </div>
                <div class="stat-card stat-4">
                    <div class="stat-icon">📈</div>
                    <div class="stat-info">
                        <h3>+18%</h3>
                        <p>Croissance des ventes</p>
                    </div>
                </div>
            </div>

            <!-- Tableaux -->
            <div class="tables-container">
                <div class="table-card">
                    <div class="table-header">
                        <h3>Commandes récentes</h3>
                        <div class="table-actions">
                            <button class="btn btn-primary" id="addOrderBtn">
                                + Nouvelle commande
                            </button>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client</th>
                                <th>Date</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commandes as $commande): ?>
                            <tr>
                                <td>#ORD-<?php echo str_pad($commande['idCommande'], 3, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($commande['email']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($commande['dateCommande'])); ?></td>
                                <td><?php echo number_format($commande['montantTotal'], 2, ',', ' '); ?>€</td>
                                <td>
                                    <?php 
                                    $statusClass = '';
                                    switch($commande['statut']) {
                                        case 'en_attente': $statusClass = 'status-pending'; break;
                                        case 'expediee': $statusClass = 'status-shipped'; break;
                                        case 'livree': $statusClass = 'status-delivered'; break;
                                        default: $statusClass = 'status-pending';
                                    }
                                    ?>
                                    <span class="status <?php echo $statusClass; ?>">
                                        <?php 
                                        switch($commande['statut']) {
                                            case 'en_attente': echo 'En attente'; break;
                                            case 'expediee': echo 'Expédiée'; break;
                                            case 'livree': echo 'Livrée'; break;
                                            default: echo 'En attente';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($commande['statut'] == 'en_attente'): ?>
                                    <button class="btn btn-success">Expédier</button>
                                    <button class="btn btn-danger">Annuler</button>
                                    <?php else: ?>
                                    <button class="btn btn-success">Détails</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-card">
                    <div class="table-header">
                        <h3>Clients récents</h3>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Commandes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($client['email']); ?></td>
                                <td><?php echo $client['nbCommandes']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire d'ajout de commande -->
    <div class="form-overlay" id="orderForm">
        <div class="form-container">
            <div class="form-header">
                <h3>Nouvelle Commande</h3>
                <button class="close-btn" id="closeFormBtn">&times;</button>
            </div>
            <form id="addOrderForm">
                <div class="form-group">
                    <label for="clientName">Nom du client</label>
                    <input type="text" id="clientName" class="form-control" required />
                </div>
                <div class="form-group">
                    <label for="clientEmail">Email</label>
                    <input
                        type="email"
                        id="clientEmail"
                        class="form-control"
                        required
                    />
                </div>
                <div class="form-group">
                    <label for="clientAddress">Adresse</label>
                    <textarea
                        id="clientAddress"
                        class="form-control"
                        rows="3"
                        required
                    ></textarea>
                </div>
                <div class="form-group">
                    <label for="products">Produits commandés</label>
                    <select id="products" class="form-control" multiple>
                        <option value="grue">Grue Élégante (24€)</option>
                        <option value="fleur">Fleur de Cerisier (18€)</option>
                        <option value="dragon">Dragon Majestueux (45€)</option>
                        <option value="eventail">Éventail Traditionnel (32€)</option>
                    </select>
                </div>
                <div class="form-footer">
                    <button type="button" class="btn btn-danger" id="cancelFormBtn">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-success">
                        Créer la commande
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Gestion du formulaire
        document
            .getElementById("addOrderBtn")
            .addEventListener("click", function () {
                document.getElementById("orderForm").style.display = "flex";
            });

        document
            .getElementById("closeFormBtn")
            .addEventListener("click", function () {
                document.getElementById("orderForm").style.display = "none";
            });

        document
            .getElementById("cancelFormBtn")
            .addEventListener("click", function () {
                document.getElementById("orderForm").style.display = "none";
            });

        document
            .getElementById("addOrderForm")
            .addEventListener("submit", function (e) {
                e.preventDefault();
                alert("Commande créée avec succès!");
                document.getElementById("orderForm").style.display = "none";
                // Ici, vous ajouteriez le code pour envoyer les données au serveur
            });

        // Fermer le formulaire en cliquant en dehors
        document
            .getElementById("orderForm")
            .addEventListener("click", function (e) {
                if (e.target === this) {
                    this.style.display = "none";
                }
            });
    </script>
</body>
</html>
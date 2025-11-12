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
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'modifier_statut':
                $idCommande = $_POST['id_commande'] ?? null;
                $nouveauStatut = $_POST['nouveau_statut'] ?? '';
                
                if ($idCommande && $nouveauStatut) {
                    $stmt = $pdo->prepare("UPDATE Commande SET statut = ? WHERE idCommande = ?");
                    $stmt->execute([$nouveauStatut, $idCommande]);
                    
                    $_SESSION['message_success'] = "✅ Statut de la commande #$idCommande modifié avec succès";
                }
                break;
                
            case 'supprimer_commande':
                $idCommande = $_POST['id_commande'] ?? null;
                
                if ($idCommande) {
                    // Commencer une transaction
                    $pdo->beginTransaction();
                    
                    try {
                        // Supprimer les lignes de commande
                        $stmt = $pdo->prepare("DELETE FROM LigneCommande WHERE idCommande = ?");
                        $stmt->execute([$idCommande]);
                        
                        // Supprimer la commande
                        $stmt = $pdo->prepare("DELETE FROM Commande WHERE idCommande = ?");
                        $stmt->execute([$idCommande]);
                        
                        $pdo->commit();
                        $_SESSION['message_success'] = "✅ Commande #$idCommande supprimée avec succès";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $_SESSION['message_error'] = "❌ Erreur lors de la suppression: " . $e->getMessage();
                    }
                }
                break;
        }
        
        // Rediriger pour éviter la resoumission du formulaire
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Récupérer la liste des commandes
$stmt = $pdo->prepare("
    SELECT 
        c.idCommande,
        c.dateCommande,
        c.montantTotal,
        c.statut,
        c.fraisDePort,
        cl.nom,
        cl.prenom,
        cl.email,
        a_liv.adresse as adresse_livraison,
        a_liv.ville as ville_livraison,
        COUNT(lc.idLigneCommande) as nb_articles
    FROM Commande c
    JOIN Client cl ON c.idClient = cl.idClient
    JOIN Adresse a_liv ON c.idAdresseLivraison = a_liv.idAdresse
    LEFT JOIN LigneCommande lc ON c.idCommande = lc.idCommande
    GROUP BY c.idCommande
    ORDER BY c.dateCommande DESC
");
$stmt->execute();
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes - Origami Zen</title>
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
        
        .message-success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            border-left: 5px solid #28a745;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            border-left: 5px solid #dc3545;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-commandes {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .table-commandes th {
            background: #d40000;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .table-commandes td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .table-commandes tr:hover {
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
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
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
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-1px);
        }
        
        .btn-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background-color: #138496;
            transform: translateY(-1px);
        }
        
        .statut {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .statut-payee { background: #d4edda; color: #155724; }
        .statut-en_attente { background: #fff3cd; color: #856404; }
        .statut-expediee { background: #cce7ff; color: #004085; }
        .statut-annulee { background: #f8d7da; color: #721c24; }
        
        .form-inline {
            display: inline;
        }
        
        .select-statut {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .actions-cell {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .page-title {
            color: #d40000;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-header {
            background: #d40000;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-header:hover {
            background: #b30000;
            transform: translateY(-1px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #d40000;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .stat-card .number {
            font-size: 24px;
            font-weight: bold;
            color: #333;
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
            <a href="admin_logout.php" class="btn-logout">Déconnexion</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="nav-item">Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item active">Gestion des Commandes</a>
            <a href="admin_factures.php" class="nav-item">Factures</a>
            <a href="admin_clients.php" class="nav-item">Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item">Gestion des Produits</a>
        </div>
        
        <div class="main-content">
            <div class="page-title">
                <h1>📦 Gestion des Commandes</h1>
                <a href="admin_factures.php" class="btn-header">
                    📄 Voir les Factures
                </a>
            </div>

            <?php if (isset($_SESSION['message_success'])): ?>
                <div class="message-success">
                    <span style="font-size: 18px;">✅</span>
                    <div>
                        <strong>Succès!</strong><br>
                        <?= $_SESSION['message_success'] ?>
                    </div>
                </div>
                <?php unset($_SESSION['message_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['message_error'])): ?>
                <div class="message-error">
                    <span style="font-size: 18px;">❌</span>
                    <div>
                        <strong>Erreur!</strong><br>
                        <?= $_SESSION['message_error'] ?>
                    </div>
                </div>
                <?php unset($_SESSION['message_error']); ?>
            <?php endif; ?>

            <!-- Statistiques rapides -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Commandes</h3>
                    <div class="number"><?= count($commandes) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Commandes Payées</h3>
                    <div class="number">
                        <?= count(array_filter($commandes, function($cmd) { return $cmd['statut'] === 'payee'; })) ?>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Chiffre d'Affaires</h3>
                    <div class="number">
                        <?= number_format(array_sum(array_column($commandes, 'montantTotal')), 2, ',', ' ') ?> €
                    </div>
                </div>
            </div>

            <table class="table-commandes">
                <thead>
                    <tr>
                        <th>ID Commande</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Montant TTC</th>
                        <th>Articles</th>
                        <th>Statut</th>
                        <th>Adresse Livraison</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commandes as $commande): ?>
                        <tr>
                            <td><strong>#<?= $commande['idCommande'] ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($commande['prenom']) ?> <?= htmlspecialchars($commande['nom']) ?></strong><br>
                                <small>📧 <?= htmlspecialchars($commande['email']) ?></small>
                            </td>
                            <td><strong><?= number_format($commande['montantTotal'], 2, ',', ' ') ?> €</strong></td>
                            <td><?= $commande['nb_articles'] ?> article(s)</td>
                            <td>
                                <span class="statut statut-<?= $commande['statut'] ?>">
                                    <?= $commande['statut'] ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($commande['adresse_livraison']) ?><br>
                                <small><?= htmlspecialchars($commande['ville_livraison']) ?></small>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <!-- Modifier le statut -->
                                    <form method="POST" class="form-inline">
                                        <input type="hidden" name="id_commande" value="<?= $commande['idCommande'] ?>">
                                        <input type="hidden" name="action" value="modifier_statut">
                                        <select name="nouveau_statut" class="select-statut" onchange="this.form.submit()">
                                            <option value="en_attente" <?= $commande['statut'] == 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                            <option value="payee" <?= $commande['statut'] == 'payee' ? 'selected' : '' ?>>Payée</option>
                                            <option value="expediee" <?= $commande['statut'] == 'expediee' ? 'selected' : '' ?>>Expédiée</option>
                                            <option value="annulee" <?= $commande['statut'] == 'annulee' ? 'selected' : '' ?>>Annulée</option>
                                        </select>
                                    </form>
                                    
                                    <!-- Voir les détails -->
                                    <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn btn-primary" title="Voir détails">
                                        👁️
                                    </a>
                                    
                                    <!-- Accéder aux factures -->
                                    <a href="admin_factures.php" class="btn btn-info" title="Gérer les factures">
                                        📄
                                    </a>
                                    
                                    <!-- Supprimer -->
                                    <form method="POST" class="form-inline">
                                        <input type="hidden" name="id_commande" value="<?= $commande['idCommande'] ?>">
                                        <input type="hidden" name="action" value="supprimer_commande">
                                        <button type="submit" class="btn btn-danger" 
                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer la commande #<?= $commande['idCommande'] ?> ? Cette action est irréversible.')"
                                                title="Supprimer la commande">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message-success, .message-error');
            messages.forEach(message => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 500);
            });
        }, 5000);
        
        // Confirmation pour la suppression
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.btn-danger');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Êtes-vous sûr de vouloir supprimer cette commande ? Cette action est irréversible.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>
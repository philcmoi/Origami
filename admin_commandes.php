<?php
// admin_commandes.php - Version responsive
require_once 'admin_protection.php';
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Actions sur les commandes
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
    
    // Filtres
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
    die("Erreur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Commandes - Youki and Co</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        /* Header */
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
            .logo h1 { font-size: 1.2rem; }
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
        
        /* Container */
        .container {
            display: flex;
            flex-wrap: wrap;
            min-height: calc(100vh - 80px);
        }
        
        /* Sidebar */
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
        
        /* Main content */
        .main-content {
            flex: 1;
            padding: 20px;
            min-width: 0;
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 15px; }
        }
        
        /* Filters responsive */
        .filters {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
            min-width: 120px;
        }
        
        .filter-label {
            font-size: 0.7rem;
            font-weight: bold;
            color: #555;
        }
        
        .filter-select, .filter-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        
        .date-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-filter, .btn-clear {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .btn-filter {
            background: #d40000;
            color: white;
        }
        
        .btn-clear {
            background: #6c757d;
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        
        @media (max-width: 640px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group {
                width: 100%;
            }
            .date-filters {
                flex-direction: column;
            }
        }
        
        /* Section */
        .section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .section h2 {
            margin-bottom: 20px;
            font-size: 1.3rem;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        /* Table responsive */
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }
        
        th, td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        td {
            font-size: 0.8rem;
        }
        
        /* Status badges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: bold;
            cursor: pointer;
            display: inline-block;
        }
        
        .status-en_attente_paiement { background: #fff3cd; color: #856404; }
        .status-payee { background: #d1ecf1; color: #0c5460; }
        .status-expediee { background: #d4edda; color: #155724; }
        .status-livree { background: #28a745; color: white; }
        .status-annulee { background: #f8d7da; color: #721c24; }
        
        /* Actions buttons */
        .order-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .btn-small {
            padding: 4px 8px;
            font-size: 0.7rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-confirm { background: #28a745; color: white; }
        .btn-ship { background: #17a2b8; color: white; }
        .btn-deliver { background: #20c997; color: white; }
        .btn-cancel { background: #dc3545; color: white; }
        .btn-details { background: #6c757d; color: white; }
        
        .action-form {
            display: inline;
            margin: 0;
            padding: 0;
        }
        
        .no-orders {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }
        
        /* Version mobile - cartes commandes */
        @media (max-width: 640px) {
            .desktop-table {
                display: none;
            }
            
            .mobile-orders {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            
            .order-card {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 15px;
                border-left: 3px solid #d40000;
            }
            
            .order-card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 12px;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            
            .order-id {
                font-weight: bold;
                font-size: 1rem;
            }
            
            .order-date {
                font-size: 0.7rem;
                color: #666;
            }
            
            .order-client {
                margin-bottom: 10px;
            }
            
            .order-client-name {
                font-weight: bold;
                font-size: 0.9rem;
            }
            
            .order-client-email {
                font-size: 0.7rem;
                color: #666;
                word-break: break-all;
            }
            
            .order-details {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 12px;
                font-size: 0.85rem;
            }
            
            .order-amount {
                font-weight: bold;
                color: #d40000;
            }
            
            .order-actions {
                margin-top: 10px;
                display: flex;
                justify-content: flex-start;
            }
        }
        
        @media (min-width: 641px) {
            .mobile-orders {
                display: none;
            }
        }
        
        .statut-form {
            display: none;
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
            <h1>Youki and Co - Commandes</h1>
        </div>
        <div class="admin-info">
            <span>Connecté: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="admin_dashboard.php?logout=1" class="btn-logout">Déconnexion</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="nav-item">Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item active">Commandes</a>
            <a href="admin_factures.php" class="nav-item">Factures</a>
            <a href="admin_clients.php" class="nav-item">Clients</a>
            <a href="admin_produits.php" class="nav-item">Produits</a>
        </div>
        
        <div class="main-content">
            <div class="filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label class="filter-label">Statut</label>
                        <select name="statut" class="filter-select" onchange="this.form.submit()">
                            <option value="tous" <?= $statut === 'tous' ? 'selected' : '' ?>>Tous</option>
                            <option value="en_attente_paiement" <?= $statut === 'en_attente_paiement' ? 'selected' : '' ?>>En attente</option>
                            <option value="payee" <?= $statut === 'payee' ? 'selected' : '' ?>>Payée</option>
                            <option value="expediee" <?= $statut === 'expediee' ? 'selected' : '' ?>>Expédiée</option>
                            <option value="livree" <?= $statut === 'livree' ? 'selected' : '' ?>>Livrée</option>
                            <option value="annulee" <?= $statut === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                        </select>
                    </div>
                    
                    <div class="date-filters">
                        <div class="filter-group">
                            <label class="filter-label">Date début</label>
                            <input type="date" name="date_debut" class="filter-input" value="<?= htmlspecialchars($date_debut) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Date fin</label>
                            <input type="date" name="date_fin" class="filter-input" value="<?= htmlspecialchars($date_fin) ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-filter">Filtrer</button>
                    <a href="admin_commandes.php" class="btn-clear">Effacer</a>
                </form>
            </div>
            
            <div class="section">
                <h2>Commandes (<?= count($commandes) ?>)</h2>
                
                <?php if (empty($commandes)): ?>
                    <div class="no-orders">Aucune commande trouvée.</div>
                <?php else: ?>
                    
                    <!-- Version tableau desktop -->
                    <div class="desktop-table table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th><th>Date</th><th>Client</th><th>Adresse</th><th>Montant</th><th>Livraison</th><th>Statut</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($commandes as $commande): ?>
                                <tr>
                                    <td>#<?= $commande['idCommande'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($commande['dateCommande'])) ?></td>
                                    <td><strong><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></strong><br><small><?= htmlspecialchars($commande['email']) ?></small></td>
                                    <td><?= htmlspecialchars($commande['adresse']) ?><br><?= htmlspecialchars($commande['codePostal'] . ' ' . $commande['ville']) ?></td>
                                    <td><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</td>
                                    <td><?= date('d/m/Y', strtotime($commande['delaiLivraison'])) ?></td>
                                    <td>
                                        <form method="POST" class="statut-form" id="form-<?= $commande['idCommande'] ?>">
                                            <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                            <input type="hidden" name="action" value="changer_statut">
                                            <input type="hidden" name="nouveau_statut" id="nouveau-statut-<?= $commande['idCommande'] ?>" value="">
                                        </form>
                                        <span class="status-badge status-<?= $commande['statut'] ?>" 
                                              onclick="changerStatut(<?= $commande['idCommande'] ?>, '<?= $commande['statut'] ?>')"
                                              title="Cliquer pour changer le statut">
                                            <?= $commande['statut'] ?>
                                        </span>
                                    </td>
                                    <td class="order-actions">
                                        <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-small btn-details">Détails</a>
                                    </td>
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
                            <div class="order-client">
                                <div class="order-client-name"><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></div>
                                <div class="order-client-email"><?= htmlspecialchars($commande['email']) ?></div>
                            </div>
                            <div class="order-details">
                                <span>📦 Livraison: <?= date('d/m/Y', strtotime($commande['delaiLivraison'])) ?></span>
                                <span class="order-amount">💰 <?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</span>
                            </div>
                            <div class="order-actions">
                                <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-small btn-details">👁️ Voir détails</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; <?= date('Y') ?> Youki and Co</p>
    </div>
    
    <script>
        function changerStatut(idCommande, statutActuel) {
            let prochainStatut;
            switch(statutActuel) {
                case 'en_attente_paiement': prochainStatut = 'payee'; break;
                case 'payee': prochainStatut = 'expediee'; break;
                case 'expediee': prochainStatut = 'livree'; break;
                case 'livree': prochainStatut = 'annulee'; break;
                case 'annulee': prochainStatut = 'en_attente_paiement'; break;
                default: prochainStatut = 'en_attente_paiement';
            }
            
            if (confirm(`Changer le statut de "${statutActuel}" à "${prochainStatut}" ?`)) {
                document.getElementById(`nouveau-statut-${idCommande}`).value = prochainStatut;
                document.getElementById(`form-${idCommande}`).submit();
            }
        }
    </script>
</body>
</html>
[file name]: admin_commandes.php
[file content begin]
<?php
// Inclure la protection au tout début
require_once 'admin_protection.php';

// Configuration de la base de données
$host = '217.182.198.20';
$dbname = 'origami';
$username = 'root';
$password = 'L099339R';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Gérer les actions sur les commandes
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
                
            // Nouvelle action pour le changement direct de statut
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
    
    // Récupérer les commandes avec filtres
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
        // Ajouter un jour pour inclure toute la journée de fin
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
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes - Origami Zen</title>
    <style>
        /* Styles similaires au dashboard avec ajouts */
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-label {
            font-size: 12px;
            font-weight: bold;
            color: #555;
        }
        
        .filter-select, .filter-input {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .btn-filter {
            background: #d40000;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            align-self: flex-end;
        }
        
        .btn-clear {
            background: #6c757d;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            align-self: flex-end;
            text-decoration: none;
            display: inline-block;
        }
        
        .order-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn-small {
            padding: 4px 8px;
            font-size: 12px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-confirm { background: #28a745; color: white; }
        .btn-ship { background: #17a2b8; color: white; }
        .btn-deliver { background: #20c997; color: white; }
        .btn-cancel { background: #dc3545; color: white; }
        .btn-details { background: #6c757d; color: white; }
        
        /* Reprendre les styles du dashboard */
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
        .status-badge { 
            padding: 5px 10px; 
            border-radius: 15px; 
            font-size: 12px; 
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .status-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .status-en_attente_paiement { background: #fff3cd; color: #856404; }
        .status-payee { background: #d1ecf1; color: #0c5460; }
        .status-expediee { background: #d4edda; color: #155724; }
        .status-livree { background: #28a745; color: white; }
        .status-annulee { background: #f8d7da; color: #721c24; }
        
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
        
        /* Style pour le formulaire caché de changement de statut */
        .statut-form {
            display: none;
        }
        
        .date-filters {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>Origami Zen - Gestion des Commandes</h1>
        </div>
        <div class="admin-info">
            <span>Connecté en tant que: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="admin_dashboard.php?logout=1" class="btn-logout">Déconnexion</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="nav-item">Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item active">Gestion des Commandes</a>
            <a href="admin_factures.php" class="nav-item">Gestion des Factures</a>
            <a href="admin_clients.php" class="nav-item">Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item">Gestion des Produits</a>
        </div>
        
        <div class="main-content">
            <div class="filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label class="filter-label">Statut</label>
                        <select name="statut" class="filter-select" onchange="this.form.submit()">
                            <option value="tous" <?= $statut === 'tous' ? 'selected' : '' ?>>Tous les statuts</option>
                            <option value="en_attente_paiement" <?= $statut === 'en_attente_paiement' ? 'selected' : '' ?>>En attente paiement</option>
                            <option value="payee" <?= $statut === 'payee' ? 'selected' : '' ?>>Payée</option>
                            <option value="expediee" <?= $statut === 'expediee' ? 'selected' : '' ?>>Expédiée</option>
                            <option value="livree" <?= $statut === 'livree' ? 'selected' : '' ?>>Livrée</option>
                            <option value="annulee" <?= $statut === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                        </select>
                    </div>
                    
                    <div class="date-filters">
                        <div class="filter-group">
                            <label class="filter-label">Date de début</label>
                            <input type="date" name="date_debut" class="filter-input" value="<?= htmlspecialchars($date_debut) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Date de fin</label>
                            <input type="date" name="date_fin" class="filter-input" value="<?= htmlspecialchars($date_fin) ?>">
                        </div>
                        
                        <button type="submit" class="btn-filter">Filtrer</button>
                        <a href="admin_commandes.php" class="btn-clear">Effacer</a>
                    </div>
                </form>
            </div>
            
            <div class="section">
                <h2>Liste des Commandes (<?= count($commandes) ?>)</h2>
                
                <?php if (empty($commandes)): ?>
                    <div class="no-orders">
                        Aucune commande trouvée pour les critères sélectionnés.
                    </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Adresse</th>
                            <th>Montant</th>
                            <th>Livraison</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes as $commande): ?>
                        <tr>
                            <td>#<?= $commande['idCommande'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></strong><br>
                                <?= htmlspecialchars($commande['email']) ?><br>
                                <small><?= htmlspecialchars($commande['telephone']) ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($commande['adresse']) ?><br>
                                <?= htmlspecialchars($commande['codePostal'] . ' ' . $commande['ville']) ?><br>
                                <small><?= htmlspecialchars($commande['pays']) ?></small>
                            </td>
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
                                      title="Cliquez pour changer le statut">
                                    <?= $commande['statut'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="order-actions">
                                    <?php if ($commande['statut'] === 'en_attente_paiement'): ?>
                                        <form method="POST" class="action-form">
                                            <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                            <button type="submit" name="action" value="confirmer" class="btn-small btn-confirm" onclick="return confirm('Confirmer le paiement de cette commande ?')">Confirmer Paiement</button>
                                        </form>
                                        <form method="POST" class="action-form">
                                            <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                            <button type="submit" name="action" value="annuler" class="btn-small btn-cancel" onclick="return confirm('Annuler cette commande ?')">Annuler</button>
                                        </form>
                                    <?php elseif ($commande['statut'] === 'payee'): ?>
                                        <form method="POST" class="action-form">
                                            <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                            <button type="submit" name="action" value="expedier" class="btn-small btn-ship" onclick="return confirm('Marquer cette commande comme expédiée ?')">Expédier</button>
                                        </form>
                                    <?php elseif ($commande['statut'] === 'expediee'): ?>
                                        <form method="POST" class="action-form">
                                            <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                            <button type="submit" name="action" value="livrer" class="btn-small btn-deliver" onclick="return confirm('Marquer cette commande comme livrée ?')">Livrer</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-small btn-details">Détails</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Fonction pour changer le statut d'une commande
        function changerStatut(idCommande, statutActuel) {
            // Déterminer le prochain statut dans le cycle
            let prochainStatut;
            switch(statutActuel) {
                case 'en_attente_paiement':
                    prochainStatut = 'payee';
                    break;
                case 'payee':
                    prochainStatut = 'expediee';
                    break;
                case 'expediee':
                    prochainStatut = 'livree';
                    break;
                case 'livree':
                    prochainStatut = 'annulee';
                    break;
                case 'annulee':
                    prochainStatut = 'en_attente_paiement';
                    break;
                default:
                    prochainStatut = 'en_attente_paiement';
            }
            
            // Confirmer le changement
            if (confirm(`Changer le statut de la commande #${idCommande} de "${statutActuel}" à "${prochainStatut}" ?`)) {
                // Mettre à jour le champ caché et soumettre le formulaire
                document.getElementById(`nouveau-statut-${idCommande}`).value = prochainStatut;
                document.getElementById(`form-${idCommande}`).submit();
            }
        }
        
        // Message de confirmation pour les actions
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('.action-form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const action = this.querySelector('button[type="submit"]').value;
                    let message = '';
                    
                    switch(action) {
                        case 'confirmer':
                            message = 'Êtes-vous sûr de vouloir confirmer le paiement de cette commande ?';
                            break;
                        case 'expedier':
                            message = 'Êtes-vous sûr de vouloir marquer cette commande comme expédiée ?';
                            break;
                        case 'livrer':
                            message = 'Êtes-vous sûr de vouloir marquer cette commande comme livrée ?';
                            break;
                        case 'annuler':
                            message = 'Êtes-vous sûr de vouloir annuler cette commande ?';
                            break;
                    }
                    
                    if (!confirm(message)) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>
[file content end]
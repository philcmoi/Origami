<?php
// Inclure la protection au tout début
require_once 'admin_protection.php';

// Configuration de la base de données
require_once 'config.php';

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
    <title>Gestion des Commandes - Youki and Co</title>
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
            line-height: 1.6;
            font-size: 14px;
        }

        /* Header optimisé mobile */
        .header {
            background: white;
            padding: 12px 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo h1 {
            color: #d40000;
            font-size: 18px;
            text-align: center;
            margin-bottom: 10px;
        }

        .admin-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-align: center;
            font-size: 13px;
        }

        .btn-logout {
            background: #d40000;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
            display: inline-block;
        }

        @media (min-width: 768px) {
            .header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 15px 20px;
            }
            .logo h1 {
                font-size: 22px;
                text-align: left;
                margin-bottom: 0;
            }
            .admin-info {
                flex-direction: row;
                align-items: center;
                gap: 15px;
                text-align: left;
            }
        }

        /* Container principal optimisé mobile */
        .container {
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 60px);
        }

        .mobile-menu-toggle {
            display: block;
            background: #d40000;
            color: white;
            border: none;
            padding: 12px 15px;
            border-radius: 0;
            cursor: pointer;
            width: 100%;
            font-size: 14px;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .sidebar {
            background: white;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
            position: sticky;
            top: 44px;
            z-index: 998;
            max-height: calc(100vh - 104px);
            overflow-y: auto;
        }

        .nav-item {
            display: block;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 5px;
            transition: background 0.3s;
            text-align: center;
            font-size: 14px;
        }

        .nav-item:hover, .nav-item.active {
            background: #d40000;
            color: white;
        }

        .main-content {
            flex: 1;
            padding: 15px;
        }

        @media (min-width: 992px) {
            .container {
                flex-direction: row;
            }
            .mobile-menu-toggle {
                display: none;
            }
            .sidebar {
                display: block;
                width: 250px;
                position: static;
                max-height: none;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }
            .nav-item {
                text-align: left;
            }
            .main-content {
                padding: 20px;
            }
        }

        /* Filtres optimisés mobile */
        .filters {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
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
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            width: 100%;
        }

        .date-filters {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-filter, .btn-clear {
            padding: 10px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: background 0.3s;
            flex: 1;
        }

        .btn-filter {
            background: #d40000;
            color: white;
        }

        .btn-clear {
            background: #6c757d;
            color: white;
        }

        @media (min-width: 480px) {
            .date-filters {
                flex-direction: row;
                align-items: end;
            }
            .date-filters .filter-group {
                flex: 1;
            }
            .filter-buttons {
                flex: 0 0 auto;
            }
        }

        @media (min-width: 768px) {
            .filters {
                padding: 20px;
            }
            .filter-form {
                flex-direction: row;
                align-items: end;
            }
            .filter-group {
                min-width: 200px;
            }
            .date-filters {
                flex: 1;
            }
        }

        /* Section commandes optimisée mobile */
        .section {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }

        .section h2 {
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
            text-align: center;
        }

        .no-orders {
            text-align: center;
            padding: 30px 20px;
            color: #6c757d;
            font-style: italic;
            font-size: 14px;
        }

        /* Tableau desktop (caché sur mobile) */
        .table-container {
            overflow-x: auto;
            display: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            white-space: nowrap;
        }

        .order-actions {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 6px 10px;
            font-size: 11px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            white-space: nowrap;
        }

        .btn-confirm { background: #28a745; color: white; }
        .btn-ship { background: #17a2b8; color: white; }
        .btn-deliver { background: #20c997; color: white; }
        .btn-cancel { background: #dc3545; color: white; }
        .btn-details { background: #6c757d; color: white; }

        .status-badge {
            padding: 6px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            text-align: center;
            min-width: 90px;
        }

        .status-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
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

        .statut-form {
            display: none;
        }

        /* Cartes mobile (affichées par défaut) */
        .order-cards {
            display: block;
        }

        .order-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            border-left: 4px solid #d40000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ddd;
        }

        .order-id {
            font-weight: bold;
            color: #d40000;
            font-size: 16px;
        }

        .order-date {
            color: #666;
            font-size: 13px;
            text-align: right;
        }

        .order-info {
            margin-bottom: 10px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            padding-bottom: 6px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            min-width: 80px;
            flex-shrink: 0;
        }

        .info-value {
            flex: 1;
            text-align: right;
            padding-left: 10px;
            word-break: break-word;
        }

        .mobile-status {
            text-align: center;
            margin: 12px 0;
            padding: 8px 0;
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
        }

        .order-actions-mobile {
            display: flex;
            gap: 6px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .btn-mobile {
            padding: 8px 10px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            flex: 1;
            min-width: 100px;
        }

        /* Affichage conditionnel desktop/mobile */
        @media (min-width: 1200px) {
            .order-cards {
                display: none;
            }
            .table-container {
                display: block;
            }
            .section h2 {
                text-align: left;
                font-size: 20px;
            }
        }

        @media (min-width: 768px) {
            .section {
                padding: 20px;
            }
            .section h2 {
                font-size: 22px;
            }
            .status-badge {
                font-size: 12px;
                min-width: 100px;
            }
            .btn-small {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>Youki and Co - Commandes</h1>
        </div>
        <div class="admin-info">
            <span>Admin: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="admin_dashboard.php?logout=1" class="btn-logout">Déconnexion</a>
        </div>
    </div>

    <div class="container">
        <button class="mobile-menu-toggle" id="mobileMenuToggle">☰ Menu Admin</button>

        <div class="sidebar" id="sidebar">
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

                        <div class="filter-buttons">
                            <button type="submit" class="btn-filter">Filtrer</button>
                            <a href="admin_commandes.php" class="btn-clear">Effacer</a>
                        </div>
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

                <!-- Version Desktop (tableau) -->
                <div class="table-container">
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
                                        <?=
                                            str_replace(
                                                ['en_attente_paiement', 'payee', 'expediee', 'livree', 'annulee'],
                                                ['En attente', 'Payée', 'Expédiée', 'Livrée', 'Annulée'],
                                                $commande['statut']
                                            )
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="order-actions">
                                        <?php if ($commande['statut'] === 'en_attente_paiement'): ?>
                                            <form method="POST" class="action-form">
                                                <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                                <button type="submit" name="action" value="confirmer" class="btn-small btn-confirm" onclick="return confirm('Confirmer le paiement de cette commande ?')">Confirmer</button>
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
                </div>

                <!-- Version Mobile (cartes) -->
                <div class="order-cards">
                    <?php foreach ($commandes as $commande): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-id">#<?= $commande['idCommande'] ?></div>
                            <div class="order-date"><?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></div>
                        </div>

                        <div class="order-info">
                            <div class="info-row">
                                <span class="info-label">Client:</span>
                                <span class="info-value"><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?= htmlspecialchars($commande['email']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Téléphone:</span>
                                <span class="info-value"><?= htmlspecialchars($commande['telephone']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Adresse:</span>
                                <span class="info-value"><?= htmlspecialchars($commande['adresse']) ?>, <?= htmlspecialchars($commande['codePostal'] . ' ' . $commande['ville']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Montant:</span>
                                <span class="info-value"><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Livraison:</span>
                                <span class="info-value"><?= date('d/m/Y', strtotime($commande['delaiLivraison'])) ?></span>
                            </div>
                        </div>

                        <div class="mobile-status">
                            <form method="POST" class="statut-form" id="form-mobile-<?= $commande['idCommande'] ?>">
                                <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                <input type="hidden" name="action" value="changer_statut">
                                <input type="hidden" name="nouveau_statut" id="nouveau-statut-mobile-<?= $commande['idCommande'] ?>" value="">
                            </form>
                            <span class="status-badge status-<?= $commande['statut'] ?>"
                                  onclick="changerStatut(<?= $commande['idCommande'] ?>, '<?= $commande['statut'] ?>')"
                                  title="Cliquez pour changer le statut">
                                <?=
                                    str_replace(
                                        ['en_attente_paiement', 'payee', 'expediee', 'livree', 'annulee'],
                                        ['En attente', 'Payée', 'Expédiée', 'Livrée', 'Annulée'],
                                        $commande['statut']
                                    )
                                ?>
                            </span>
                        </div>

                        <div class="order-actions-mobile">
                            <a href="admin_commande_detail.php?id=<?= $commande['idCommande'] ?>" class="btn-mobile btn-details">Détails</a>

                            <?php if ($commande['statut'] === 'en_attente_paiement'): ?>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                    <button type="submit" name="action" value="confirmer" class="btn-mobile btn-confirm" onclick="return confirm('Confirmer le paiement de cette commande ?')">Confirmer</button>
                                </form>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                    <button type="submit" name="action" value="annuler" class="btn-mobile btn-cancel" onclick="return confirm('Annuler cette commande ?')">Annuler</button>
                                </form>
                            <?php elseif ($commande['statut'] === 'payee'): ?>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                    <button type="submit" name="action" value="expedier" class="btn-mobile btn-ship" onclick="return confirm('Marquer cette commande comme expédiée ?')">Expédier</button>
                                </form>
                            <?php elseif ($commande['statut'] === 'expediee'): ?>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                    <button type="submit" name="action" value="livrer" class="btn-mobile btn-deliver" onclick="return confirm('Marquer cette commande comme livrée ?')">Livrer</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

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

            // Traduire les statuts pour l'affichage utilisateur
            const statutsTraduits = {
                'en_attente_paiement': 'En attente de paiement',
                'payee': 'Payée',
                'expediee': 'Expédiée',
                'livree': 'Livrée',
                'annulee': 'Annulée'
            };

            // Confirmer le changement
            if (confirm(`Changer le statut de la commande #${idCommande} de "${statutsTraduits[statutActuel]}" à "${statutsTraduits[prochainStatut]}" ?`)) {
                // Mettre à jour le champ caché et soumettre le formulaire
                const formDesktop = document.getElementById(`nouveau-statut-${idCommande}`);
                const formMobile = document.getElementById(`nouveau-statut-mobile-${idCommande}`);

                if (formDesktop) formDesktop.value = prochainStatut;
                if (formMobile) formMobile.value = prochainStatut;

                document.getElementById(`form-${idCommande}`).submit();
            }
        }

        // Toggle menu mobile
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar.style.display === 'block') {
                sidebar.style.display = 'none';
            } else {
                sidebar.style.display = 'block';
            }
        });

        // Masquer le sidebar sur mobile au chargement
        window.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth < 992) {
                document.getElementById('sidebar').style.display = 'none';
            }
        });

        // Gérer le redimensionnement de la fenêtre
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth >= 992) {
                sidebar.style.display = 'block';
            } else {
                sidebar.style.display = 'none';
            }
        });

        // Fermer le menu en cliquant à l'extérieur (sur mobile)
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('mobileMenuToggle');

            if (window.innerWidth < 992 &&
                sidebar.style.display === 'block' &&
                !sidebar.contains(event.target) &&
                !menuToggle.contains(event.target)) {
                sidebar.style.display = 'none';
            }
        });
    </script>
</body>
</html>

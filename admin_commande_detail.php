<?php
require_once 'admin_protection.php';
require_once 'config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: admin_commandes.php');
    exit;
}

$idCommande = $_GET['id'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if (isset($_POST['action']) && $_POST['action'] === 'changer_statut') {
        $nouveauStatut = $_POST['nouveau_statut'] ?? null;
        if ($nouveauStatut && in_array($nouveauStatut, ['en_attente_paiement', 'payee', 'expediee', 'livree', 'annulee'])) {
            $stmt = $pdo->prepare("UPDATE Commande SET statut = ? WHERE idCommande = ?");
            $stmt->execute([$nouveauStatut, $idCommande]);
            header("Location: admin_commande_detail.php?id=$idCommande");
            exit;
        }
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            c.*, 
            cl.nom, cl.prenom, cl.email, 
            a.adresse, a.codePostal, a.ville, a.pays, a.telephone
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        JOIN Adresse a ON c.idAdresseLivraison = a.idAdresse
        WHERE c.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        die("Commande non trouvée");
    }
    
    $stmt = $pdo->prepare("
        SELECT lc.*, o.nom as produit_nom, o.prixHorsTaxe as produit_prix
        FROM LigneCommande lc
        JOIN Origami o ON lc.idOrigami = o.idOrigami
        WHERE lc.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $lignesCommande = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Commande #<?= $commande['idCommande'] ?> - Youki and Co</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
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
        }
        
        .container {
            display: flex;
            flex-wrap: wrap;
            min-height: calc(100vh - 80px);
        }
        
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
        
        .main-content {
            flex: 1;
            padding: 20px;
            min-width: 0;
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 15px; }
        }
        
        .btn-back {
            display: inline-block;
            padding: 8px 15px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }
        
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
        
        .section h3 {
            margin: 20px 0 15px 0;
            font-size: 1.1rem;
        }
        
        /* Info grid responsive */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 640px) {
            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #d40000;
        }
        
        .info-card h3 {
            margin-bottom: 12px;
            color: #d40000;
            font-size: 1rem;
        }
        
        .info-card p {
            margin-bottom: 8px;
            font-size: 0.85rem;
        }
        
        /* Table responsive */
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
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
        
        /* Status badge */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            cursor: pointer;
            display: inline-block;
        }
        
        .status-en_attente_paiement { background: #fff3cd; color: #856404; }
        .status-payee { background: #d1ecf1; color: #0c5460; }
        .status-expediee { background: #d4edda; color: #155724; }
        .status-livree { background: #28a745; color: white; }
        .status-annulee { background: #f8d7da; color: #721c24; }
        
        .montant-total {
            font-size: 1.3rem;
            font-weight: bold;
            color: #d40000;
            text-align: right;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-action {
            display: inline-block;
            padding: 10px 20px;
            background: #d40000;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .statut-form {
            display: none;
        }
        
        /* Version mobile - articles en cartes */
        @media (max-width: 640px) {
            .articles-cards {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            
            .article-card {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 12px;
            }
            
            .article-name {
                font-weight: bold;
                font-size: 0.95rem;
                margin-bottom: 8px;
            }
            
            .article-details {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
                font-size: 0.8rem;
            }
            
            .desktop-table {
                display: none;
            }
        }
        
        @media (min-width: 641px) {
            .mobile-articles {
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
            <h1>Youki and Co - Détail Commande</h1>
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
            <a href="admin_commandes.php" class="btn-back">← Retour aux commandes</a>
            
            <div class="section">
                <h2>Commande #<?= $commande['idCommande'] ?></h2>
                
                <div class="info-grid">
                    <div class="info-card">
                        <h3>Informations Commande</h3>
                        <p><strong>Date:</strong> <?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></p>
                        <p><strong>Statut:</strong> 
                            <form method="POST" class="statut-form" id="form-statut">
                                <input type="hidden" name="action" value="changer_statut">
                                <input type="hidden" name="nouveau_statut" id="nouveau-statut" value="">
                            </form>
                            <span class="status-badge status-<?= $commande['statut'] ?>" 
                                  onclick="changerStatut('<?= $commande['statut'] ?>')">
                                <?= $commande['statut'] ?>
                            </span>
                        </p>
                        <p><strong>Livraison:</strong> <?= date('d/m/Y', strtotime($commande['delaiLivraison'])) ?></p>
                        <p><strong>Règlement:</strong> <?= htmlspecialchars($commande['modeReglement']) ?></p>
                        <p><strong>Frais de port:</strong> <?= number_format($commande['fraisDePort'], 2, ',', ' ') ?>€</p>
                        <p><strong>Total:</strong> <?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</p>
                    </div>
                    
                    <div class="info-card">
                        <h3>Client</h3>
                        <p><strong>Nom:</strong> <?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($commande['email']) ?></p>
                        <p><strong>Téléphone:</strong> <?= htmlspecialchars($commande['telephone']) ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h3>Adresse de Livraison</h3>
                        <p><?= htmlspecialchars($commande['adresse']) ?></p>
                        <p><?= htmlspecialchars($commande['codePostal'] . ' ' . $commande['ville']) ?></p>
                        <p><?= htmlspecialchars($commande['pays']) ?></p>
                    </div>
                </div>
                
                <h3>Articles</h3>
                
                <!-- Version tableau desktop -->
                <div class="desktop-table table-wrapper">
                    <table>
                        <thead>
                            <tr><th>Produit</th><th>Prix unitaire</th><th>Quantité</th><th>Sous-total</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lignesCommande as $ligne): ?>
                            <tr>
                                <td><?= htmlspecialchars($ligne['produit_nom']) ?></td>
                                <td><?= number_format($ligne['prixUnitaire'], 2, ',', ' ') ?>€</td>
                                <td><?= $ligne['quantite'] ?></td>
                                <td><?= number_format($ligne['prixUnitaire'] * $ligne['quantite'], 2, ',', ' ') ?>€</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Version mobile cartes -->
                <div class="mobile-articles articles-cards">
                    <?php foreach ($lignesCommande as $ligne): ?>
                    <div class="article-card">
                        <div class="article-name"><?= htmlspecialchars($ligne['produit_nom']) ?></div>
                        <div class="article-details">
                            <span>💰 <?= number_format($ligne['prixUnitaire'], 2, ',', ' ') ?>€ l'unité</span>
                            <span>📦 Quantité: <?= $ligne['quantite'] ?></span>
                            <span><strong>Total: <?= number_format($ligne['prixUnitaire'] * $ligne['quantite'], 2, ',', ' ') ?>€</strong></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="montant-total">
                    Total TTC: <?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€
                </div>
                
                <a href="admin_commandes.php" class="btn-action">← Retour aux commandes</a>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; <?= date('Y') ?> Youki and Co</p>
    </div>
    
    <script>
        function changerStatut(statutActuel) {
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
                document.getElementById('nouveau-statut').value = prochainStatut;
                document.getElementById('form-statut').submit();
            }
        }
    </script>
</body>
</html>
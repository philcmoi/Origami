<?php
// Inclure la protection au tout début
require_once 'admin_protection.php';

// Configuration de la base de données
$host = 'localhost';
$dbname = 'origami';
$username = 'root';
$password = '';

// Vérifier si l'ID de commande est passé en paramètre
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: admin_commandes.php');
    exit;
}

$idCommande = $_GET['id'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Gérer le changement de statut
    if (isset($_POST['action']) && $_POST['action'] === 'changer_statut') {
        $nouveauStatut = $_POST['nouveau_statut'] ?? null;
        if ($nouveauStatut && in_array($nouveauStatut, ['en_attente_paiement', 'payee', 'expediee', 'livree', 'annulee'])) {
            $stmt = $pdo->prepare("UPDATE Commande SET statut = ? WHERE idCommande = ?");
            $stmt->execute([$nouveauStatut, $idCommande]);
            
            // Rediriger vers la même page pour éviter la resoumission du formulaire
            header("Location: admin_commande_detail.php?id=$idCommande");
            exit;
        }
    }
    
    // Récupérer les détails de la commande
    $stmt = $pdo->prepare("
        SELECT 
            c.*, 
            cl.nom, 
            cl.prenom, 
            cl.email, 
            a.adresse,
            a.codePostal,
            a.ville,
            a.pays,
            a.telephone
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
    
    // Récupérer les articles de la commande
    $stmt = $pdo->prepare("
        SELECT lc.*, o.nom as produit_nom, o.prixHorsTaxe as produit_prix
        FROM LigneCommande lc
        JOIN Origami o ON lc.idOrigami = o.idOrigami
        WHERE lc.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $lignesCommande = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Déconnexion
if (isset($_GET['logout'])) {
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
    <title>Détail Commande - Origami Zen</title>
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
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .status-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
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
            padding: 8px 15px;
            background: #d40000;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            margin-right: 10px;
            display: inline-block;
        }
        
        .btn-action:hover {
            background: #b30000;
        }
        
        .btn-back {
            padding: 8px 15px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #d40000;
        }
        
        .info-card h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .montant-total {
            font-size: 24px;
            font-weight: bold;
            color: #d40000;
            text-align: right;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        /* Style pour le formulaire caché de changement de statut */
        .statut-form {
            display: none;
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
            <a href="admin_dashboard.php" class="nav-item">Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">Gestion des Commandes</a>
            <a href="admin_clients.php" class="nav-item">Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item">Gestion des Produits</a>
        </div>
        
        <div class="main-content">
            <a href="admin_commandes.php" class="btn-back">← Retour aux commandes</a>
            
            <div class="section">
                <h2>Détails de la Commande #<?= $commande['idCommande'] ?></h2>
                
                <div class="info-grid">
                    <div class="info-card">
                        <h3>Informations Commande</h3>
                        <p><strong>Date:</strong> <?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></p>
                        <p><strong>Statut:</strong> 
                            <form method="POST" class="statut-form" id="form-statut">
                                <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                <input type="hidden" name="action" value="changer_statut">
                                <input type="hidden" name="nouveau_statut" id="nouveau-statut" value="">
                            </form>
                            <span class="status-badge status-<?= $commande['statut'] ?>" 
                                  onclick="changerStatut('<?= $commande['statut'] ?>')"
                                  title="Cliquez pour changer le statut">
                                <?= $commande['statut'] ?>
                            </span>
                        </p>
                        <p><strong>Délai de livraison:</strong> <?= date('d/m/Y', strtotime($commande['delaiLivraison'])) ?></p>
                        <p><strong>Mode de règlement:</strong> <?= htmlspecialchars($commande['modeReglement']) ?></p>
                        <p><strong>Frais de port:</strong> <?= number_format($commande['fraisDePort'], 2, ',', ' ') ?>€</p>
                        <p><strong>Montant Total:</strong> <?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€</p>
                    </div>
                    
                    <div class="info-card">
                        <h3>Informations Client</h3>
                        <p><strong>Nom:</strong> <?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($commande['email']) ?></p>
                        <p><strong>Téléphone:</strong> <?= htmlspecialchars($commande['telephone']) ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h3>Adresse de Livraison</h3>
                        <p><strong>Adresse:</strong> <?= htmlspecialchars($commande['adresse']) ?></p>
                        <p><strong>Code Postal:</strong> <?= htmlspecialchars($commande['codePostal']) ?></p>
                        <p><strong>Ville:</strong> <?= htmlspecialchars($commande['ville']) ?></p>
                        <p><strong>Pays:</strong> <?= htmlspecialchars($commande['pays']) ?></p>
                    </div>
                </div>
                
                <h3>Articles de la commande</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Prix unitaire</th>
                            <th>Quantité</th>
                            <th>Sous-total</th>
                        </tr>
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
                
                <div class="montant-total">
                    Total: <?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€
                </div>
                
                <div style="margin-top: 20px;">
                    <a href="admin_commandes.php" class="btn-action">Retour aux commandes</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Fonction pour changer le statut d'une commande
        function changerStatut(statutActuel) {
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
            if (confirm(`Changer le statut de la commande de "${statutActuel}" à "${prochainStatut}" ?`)) {
                // Mettre à jour le champ caché et soumettre le formulaire
                document.getElementById('nouveau-statut').value = prochainStatut;
                document.getElementById('form-statut').submit();
            }
        }
    </script>
</body>
</html>
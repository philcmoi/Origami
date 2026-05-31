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
        SELECT c.*, cl.nom, cl.prenom, cl.email, 
               a.adresse, a.codePostal, a.ville, a.pays, a.telephone
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        JOIN Adresse a ON c.idAdresseLivraison = a.idAdresse
        WHERE c.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) die("Commande non trouvée");
    
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary: #c0392b;
            --primary-dark: #a93226;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --border-radius: 12px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
            color: var(--gray-800);
        }

        .app { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--gray-200);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            overflow-y: auto;
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; z-index: 100; }
            .sidebar.open { transform: translateX(0); }
        }
        
        .sidebar-header { padding: 20px; border-bottom: 1px solid var(--gray-200); }
        .sidebar-header h2 { font-size: 1.25rem; font-weight: 700; color: var(--primary); }
        
        .nav-menu { padding: 16px 12px; }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            color: var(--gray-700);
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 4px;
            transition: all 0.2s;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .nav-item i { width: 20px; color: var(--gray-500); }
        .nav-item:hover { background: var(--gray-100); color: var(--primary); }
        .nav-item.active { background: var(--primary); color: white; }
        
        .main-content {
            flex: 1;
            margin-left: 260px;
            min-height: 100vh;
        }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        .top-bar {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 40;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 8px;
        }
        @media (max-width: 768px) { .menu-toggle { display: block; } }
        
        .page-title h1 { font-size: 1.25rem; font-weight: 600; }
        .breadcrumb {
            font-size: 0.7rem;
            color: var(--gray-500);
            margin-top: 4px;
        }
        .breadcrumb a { color: var(--primary); text-decoration: none; }
        
        .user-info { display: flex; align-items: center; gap: 16px; }
        .user-email { font-size: 0.8rem; color: var(--gray-600); }
        .btn-logout {
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .content-wrapper { padding: 24px; }
        @media (max-width: 640px) { .content-wrapper { padding: 16px; } }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--gray-100);
            color: var(--gray-700);
            text-decoration: none;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-bottom: 20px;
        }
        
        .section {
            background: white;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .section-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .section-header h2 {
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-header h2 i { color: var(--primary); }
        
        .section-body { padding: 20px; }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .info-card {
            background: var(--gray-100);
            padding: 16px;
            border-radius: 12px;
            border-left: 3px solid var(--primary);
        }
        
        .info-card h3 {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-card p { font-size: 0.8rem; margin-bottom: 6px; }
        .info-card p strong { color: var(--gray-700); }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
        }
        .status-en_attente_paiement { background: #fff3cd; color: #856404; }
        .status-payee { background: #d4edda; color: #155724; }
        .status-expediee { background: #d1ecf1; color: #0c5460; }
        .status-livree { background: #fef2f2; color: var(--primary); }
        .status-annulee { background: var(--gray-200); color: var(--gray-600); }
        
        .table-wrapper { overflow-x: auto; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px;
        }
        
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        
        th {
            background: var(--gray-100);
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--gray-600);
        }
        
        td { font-size: 0.8rem; }
        
        .montant-total {
            text-align: right;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
            margin-top: 20px;
            padding-top: 16px;
            border-top: 2px solid var(--gray-200);
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            margin-top: 20px;
        }
        
        .btn-action:hover { background: var(--primary-dark); }
        
        @media (max-width: 640px) {
            .desktop-table { display: none; }
            .mobile-articles { display: flex; flex-direction: column; gap: 12px; }
            .article-card {
                background: var(--gray-100);
                border-radius: 12px;
                padding: 14px;
                border-left: 3px solid var(--primary);
            }
            .article-name { font-weight: 700; font-size: 0.9rem; margin-bottom: 8px; }
            .article-details {
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 10px;
                font-size: 0.8rem;
            }
        }
        
        @media (min-width: 641px) { .mobile-articles { display: none; } }
        
        .statut-form { display: none; }
        
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 0.7rem;
            color: var(--gray-500);
            border-top: 1px solid var(--gray-200);
            margin-top: 24px;
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 90;
        }
        .sidebar-overlay.active { display: block; }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Youki & Co</h2>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item"><i class="fas fa-chart-pie"></i> Tableau de bord</a>
                <a href="admin_commandes.php" class="nav-item active"><i class="fas fa-shopping-cart"></i> Commandes</a>
                <a href="admin_factures.php" class="nav-item"><i class="fas fa-file-invoice"></i> Factures</a>
                <a href="admin_clients.php" class="nav-item"><i class="fas fa-users"></i> Clients</a>
                <a href="admin_produits.php" class="nav-item"><i class="fas fa-box"></i> Produits</a>
            </nav>
        </aside>
        
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <main class="main-content">
            <div class="top-bar">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <div class="page-title">
                    <h1>Détail de la commande</h1>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Dashboard</a> &gt;
                        <a href="admin_commandes.php">Commandes</a> &gt;
                        Commande #<?= $commande['idCommande'] ?>
                    </div>
                </div>
                <div class="user-info">
                    <span class="user-email"><?= htmlspecialchars($_SESSION['admin_email']) ?></span>
                    <a href="admin_dashboard.php?logout=1" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                </div>
            </div>
            
            <div class="content-wrapper">
                <a href="admin_commandes.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour aux commandes</a>
                
                <div class="section">
                    <div class="section-header">
                        <h2><i class="fas fa-receipt"></i> Commande #<?= $commande['idCommande'] ?></h2>
                    </div>
                    <div class="section-body">
                        <div class="info-grid">
                            <div class="info-card">
                                <h3><i class="fas fa-info-circle"></i> Informations commande</h3>
                                <p><strong>Date :</strong> <?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></p>
                                <p><strong>Statut :</strong> 
                                    <form method="POST" class="statut-form" id="form-statut">
                                        <input type="hidden" name="action" value="changer_statut">
                                        <input type="hidden" name="nouveau_statut" id="nouveau-statut">
                                    </form>
                                    <span class="status-badge status-<?= $commande['statut'] ?>" onclick="changerStatut('<?= $commande['statut'] ?>')">
                                        <?= $commande['statut'] ?>
                                    </span>
                                </p>
                                <p><strong>Livraison :</strong> <?= date('d/m/Y', strtotime($commande['delaiLivraison'])) ?></p>
                                <p><strong>Règlement :</strong> <?= htmlspecialchars($commande['modeReglement']) ?></p>
                                <p><strong>Frais de port :</strong> <?= number_format($commande['fraisDePort'], 2, ',', ' ') ?>€</p>
                            </div>
                            
                            <div class="info-card">
                                <h3><i class="fas fa-user"></i> Client</h3>
                                <p><strong>Nom :</strong> <?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></p>
                                <p><strong>Email :</strong> <?= htmlspecialchars($commande['email']) ?></p>
                                <p><strong>Téléphone :</strong> <?= htmlspecialchars($commande['telephone']) ?></p>
                                <p style="margin-top: 10px;"><a href="admin_client_detail.php?id=<?= $commande['idClient'] ?>" style="color: var(--primary);">Voir le profil client →</a></p>
                            </div>
                            
                            <div class="info-card">
                                <h3><i class="fas fa-truck"></i> Adresse de livraison</h3>
                                <p><?= nl2br(htmlspecialchars($commande['adresse'])) ?></p>
                                <p><?= htmlspecialchars($commande['codePostal'] . ' ' . $commande['ville']) ?></p>
                                <p><?= htmlspecialchars($commande['pays']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-header">
                        <h2><i class="fas fa-box"></i> Articles commandés</h2>
                    </div>
                    <div class="section-body">
                        <div class="desktop-table table-wrapper">
                            <table>
                                <thead>
                                    <tr><th>Produit</th><th>Prix unitaire</th><th>Quantité</th><th>Sous-total</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lignesCommande as $ligne): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($ligne['produit_nom']) ?></strong></td>
                                        <td><?= number_format($ligne['prixUnitaire'], 2, ',', ' ') ?>€</                                        <td><?= $ligne['quantite'] ?></td>
                                        <td><?= number_format($ligne['prixUnitaire'] * $ligne['quantite'], 2, ',', ' ') ?>€</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mobile-articles">
                            <?php foreach ($lignesCommande as $ligne): ?>
                            <div class="article-card">
                                <div class="article-name"><?= htmlspecialchars($ligne['produit_nom']) ?></div>
                                <div class="article-details">
                                    <span>💰 <?= number_format($ligne['prixUnitaire'], 2, ',', ' ') ?>€</span>
                                    <span>📦 x<?= $ligne['quantite'] ?></span>
                                    <span><strong><?= number_format($ligne['prixUnitaire'] * $ligne['quantite'], 2, ',', ' ') ?>€</strong></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="montant-total">
                            Total : <?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€
                        </div>
                        
                        <a href="admin_commandes.php" class="btn-action"><i class="fas fa-arrow-left"></i> Retour aux commandes</a>
                    </div>
                </div>
            </div>
            
            <div class="footer">
                <p>&copy; <?= date('Y') ?> Youki and Co - Créations artisanales japonaises</p>
            </div>
        </main>
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
        
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        menuToggle?.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });
        overlay?.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    </script>
</body>
</html>
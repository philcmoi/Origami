<?php
// Inclure la protection au tout début
require_once 'admin_protection.php';

// Configuration de la base de données
require_once 'config.php';

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
    <title>Détail Commande - Youki and Co</title>
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
            z-index: 100;
        }

        .logo h1 {
            color: #d40000;
            font-size: 18px;
            text-align: center;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .admin-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-align: center;
        }

        .admin-info span {
            font-size: 13px;
            color: #666;
        }

        .btn-logout {
            background: #d40000;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            display: inline-block;
            transition: background 0.3s;
            font-weight: 500;
        }

        .btn-logout:hover {
            background: #b30000;
        }

        /* Container principal */
        .container {
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 80px);
        }

        /* Menu mobile optimisé */
        .mobile-menu-toggle {
            display: block;
            background: #d40000;
            color: white;
            border: none;
            padding: 12px 15px;
            border-radius: 6px;
            cursor: pointer;
            margin: 15px;
            width: calc(100% - 30px);
            font-size: 15px;
            font-weight: 500;
            transition: background 0.3s;
        }

        .mobile-menu-toggle:hover {
            background: #b30000;
        }

        .sidebar {
            background: white;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
            position: fixed;
            top: 80px;
            left: 0;
            width: 100%;
            height: calc(100vh - 80px);
            overflow-y: auto;
            z-index: 99;
        }

        .sidebar.active {
            display: block;
        }

        .nav-item {
            display: block;
            padding: 16px 20px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .nav-item:last-child {
            border-bottom: none;
        }

        .nav-item:hover, .nav-item.active {
            background: #d40000;
            color: white;
        }

        /* Contenu principal optimisé mobile */
        .main-content {
            flex: 1;
            padding: 15px;
        }

        /* Bouton retour */
        .btn-back {
            padding: 12px 16px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            transition: background 0.3s;
            width: 100%;
            text-align: center;
            justify-content: center;
        }

        .btn-back:hover {
            background: #5a6268;
        }

        /* Section principale */
        .section {
            background: white;
            padding: 20px 15px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .section h2 {
            margin-bottom: 18px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 12px;
            font-size: 18px;
            font-weight: 600;
            text-align: center;
        }

        .section h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 16px;
            font-weight: 600;
        }

        /* Grille d'informations optimisée mobile */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        @media (min-width: 768px) {
            .info-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
            }
        }

        .info-card {
            background: #f8f9fa;
            padding: 18px;
            border-radius: 10px;
            border-left: 4px solid #d40000;
        }

        .info-card h3 {
            margin-bottom: 12px;
            color: #333;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-card p {
            margin-bottom: 10px;
            font-size: 14px;
            word-break: break-word;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .info-card strong {
            color: #333;
            min-width: 120px;
            font-weight: 600;
        }

        /* Badge de statut optimisé mobile */
        .status-badge {
            padding: 10px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            text-align: center;
            min-width: 140px;
            border: 2px solid transparent;
        }

        .status-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .status-en_attente_paiement {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }

        .status-payee {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #b8daff;
        }

        .status-expediee {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .status-livree {
            background: #28a745;
            color: white;
            border-color: #1e7e34;
        }

        .status-annulee {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        /* Articles de commande - Version Mobile */
        .articles-mobile {
            display: block;
            margin-top: 20px;
        }

        .article-card {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 12px;
            border-left: 4px solid #d40000;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }

        .article-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e9ecef;
        }

        .article-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .article-label {
            font-weight: 600;
            color: #666;
            font-size: 13px;
            min-width: 100px;
        }

        .article-value {
            flex: 1;
            text-align: right;
            font-size: 14px;
            word-break: break-word;
        }

        .article-total {
            font-weight: bold;
            color: #d40000;
            font-size: 15px;
        }

        /* Version Desktop (cachée sur mobile) */
        .articles-desktop {
            display: none;
        }

        /* Montant total */
        .montant-total {
            font-size: 20px;
            font-weight: bold;
            color: #d40000;
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
        }

        /* Actions */
        .actions-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-action {
            padding: 12px 20px;
            background: #d40000;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            text-align: center;
            transition: background 0.3s;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-action:hover {
            background: #b30000;
        }

        /* Overlay pour menu mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 98;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* Styles Desktop */
        @media (min-width: 1024px) {
            .container {
                flex-direction: row;
            }

            .sidebar {
                display: block;
                position: static;
                width: 250px;
                height: auto;
                padding: 20px;
            }

            .mobile-menu-toggle {
                display: none;
            }

            .main-content {
                padding: 25px;
            }

            .header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 15px 25px;
            }

            .logo h1 {
                text-align: left;
                margin-bottom: 0;
                font-size: 22px;
            }

            .admin-info {
                flex-direction: row;
                text-align: left;
            }

            .btn-back {
                width: auto;
                justify-content: flex-start;
            }

            .section h2 {
                text-align: left;
                font-size: 24px;
            }

            .section h3 {
                font-size: 20px;
            }

            .articles-desktop {
                display: block;
            }

            .articles-mobile {
                display: none;
            }

            .montant-total {
                text-align: right;
                font-size: 22px;
            }

            .actions-container {
                flex-direction: row;
            }
        }

        /* Tableau desktop */
        .table-container {
            overflow-x: auto;
            margin: 20px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            white-space: nowrap;
        }

        /* Formulaire statut caché */
        .statut-form {
            display: none;
        }

        /* Améliorations pour très petits écrans */
        @media (max-width: 360px) {
            .main-content {
                padding: 12px;
            }

            .section {
                padding: 15px 12px;
            }

            .info-card {
                padding: 15px;
            }

            .article-card {
                padding: 14px;
            }

            .status-badge {
                min-width: 120px;
                padding: 8px 12px;
                font-size: 12px;
            }

            .montant-total {
                font-size: 18px;
                padding: 12px;
            }
        }

        /* Animation pour le menu mobile */
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .sidebar.active {
            transform: translateX(0);
        }

        /* Icônes pour les cartes d'information */
        .icon-info::before { content: "📦 "; }
        .icon-client::before { content: "👤 "; }
        .icon-address::before { content: "📍 "; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>Youki and Co - Administration</h1>
        </div>
        <div class="admin-info">
            <span>Connecté: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="?logout=1" class="btn-logout">Déconnexion</a>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        ☰ Menu Administration
    </button>

    <div class="container">
        <div class="sidebar" id="sidebar">
            <a href="admin_dashboard.php" class="nav-item">📊 Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item active">📦 Gestion des Commandes</a>
            <a href="admin_factures.php" class="nav-item">📄 Gestion des Factures</a>
            <a href="admin_clients.php" class="nav-item">👥 Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item">🎨 Gestion des Produits</a>
        </div>

        <div class="main-content">
            <a href="admin_commandes.php" class="btn-back">
                ← Retour aux commandes
            </a>

            <div class="section">
                <h2>📋 Détails de la Commande #<?= $commande['idCommande'] ?></h2>

                <div class="info-grid">
                    <!-- Informations Commande -->
                    <div class="info-card">
                        <h3 class="icon-info">Informations Commande</h3>
                        <p>
                            <strong>Date:</strong>
                            <span><?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></span>
                        </p>
                        <p>
                            <strong>Statut:</strong>
                            <span>
                                <form method="POST" class="statut-form" id="form-statut">
                                    <input type="hidden" name="idCommande" value="<?= $commande['idCommande'] ?>">
                                    <input type="hidden" name="action" value="changer_statut">
                                    <input type="hidden" name="nouveau_statut" id="nouveau-statut" value="">
                                </form>
                                <span class="status-badge status-<?= $commande['statut'] ?>"
                                      onclick="changerStatut('<?= $commande['statut'] ?>')"
                                      title="Cliquez pour changer le statut">
                                    <?=
                                        str_replace(
                                            ['en_attente_paiement', 'payee', 'expediee', 'livree', 'annulee'],
                                            ['En attente', 'Payée', 'Expédiée', 'Livrée', 'Annulée'],
                                            $commande['statut']
                                        )
                                    ?>
                                </span>
                            </span>
                        </p>
                        <p>
                            <strong>Livraison:</strong>
                            <span><?= date('d/m/Y', strtotime($commande['delaiLivraison'])) ?></span>
                        </p>
                        <p>
                            <strong>Paiement:</strong>
                            <span><?= htmlspecialchars($commande['modeReglement']) ?></span>
                        </p>
                        <p>
                            <strong>Frais de port:</strong>
                            <span><?= number_format($commande['fraisDePort'], 2, ',', ' ') ?>€</span>
                        </p>
                    </div>

                    <!-- Informations Client -->
                    <div class="info-card">
                        <h3 class="icon-client">Informations Client</h3>
                        <p>
                            <strong>Nom:</strong>
                            <span><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></span>
                        </p>
                        <p>
                            <strong>Email:</strong>
                            <span><?= htmlspecialchars($commande['email']) ?></span>
                        </p>
                        <p>
                            <strong>Téléphone:</strong>
                            <span><?= htmlspecialchars($commande['telephone']) ?></span>
                        </p>
                    </div>

                    <!-- Adresse de Livraison -->
                    <div class="info-card">
                        <h3 class="icon-address">Adresse de Livraison</h3>
                        <p>
                            <strong>Adresse:</strong>
                            <span><?= htmlspecialchars($commande['adresse']) ?></span>
                        </p>
                        <p>
                            <strong>Code Postal:</strong>
                            <span><?= htmlspecialchars($commande['codePostal']) ?></span>
                        </p>
                        <p>
                            <strong>Ville:</strong>
                            <span><?= htmlspecialchars($commande['ville']) ?></span>
                        </p>
                        <p>
                            <strong>Pays:</strong>
                            <span><?= htmlspecialchars($commande['pays']) ?></span>
                        </p>
                    </div>
                </div>

                <h3>🛒 Articles de la commande</h3>

                <!-- Version Mobile -->
                <div class="articles-mobile">
                    <?php foreach ($lignesCommande as $ligne): ?>
                    <div class="article-card">
                        <div class="article-row">
                            <span class="article-label">Produit:</span>
                            <span class="article-value"><?= htmlspecialchars($ligne['produit_nom']) ?></span>
                        </div>
                        <div class="article-row">
                            <span class="article-label">Prix unitaire:</span>
                            <span class="article-value"><?= number_format($ligne['prixUnitaire'], 2, ',', ' ') ?>€</span>
                        </div>
                        <div class="article-row">
                            <span class="article-label">Quantité:</span>
                            <span class="article-value"><?= $ligne['quantite'] ?></span>
                        </div>
                        <div class="article-row">
                            <span class="article-label">Sous-total:</span>
                            <span class="article-value article-total">
                                <?= number_format($ligne['prixUnitaire'] * $ligne['quantite'], 2, ',', ' ') ?>€
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Version Desktop -->
                <div class="table-container articles-desktop">
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
                                <td><strong><?= number_format($ligne['prixUnitaire'] * $ligne['quantite'], 2, ',', ' ') ?>€</strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="montant-total">
                    💰 Total: <?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€
                </div>

                <div class="actions-container">
                    <a href="admin_commandes.php" class="btn-action">
                        ← Retour aux commandes
                    </a>
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

            // Traduire les statuts pour l'affichage utilisateur
            const statutsTraduits = {
                'en_attente_paiement': 'En attente de paiement',
                'payee': 'Payée',
                'expediee': 'Expédiée',
                'livree': 'Livrée',
                'annulee': 'Annulée'
            };

            // Confirmer le changement
            if (confirm(`Changer le statut de la commande de "${statutsTraduits[statutActuel]}" à "${statutsTraduits[prochainStatut]}" ?`)) {
                // Mettre à jour le champ caché et soumettre le formulaire
                document.getElementById('nouveau-statut').value = prochainStatut;
                document.getElementById('form-statut').submit();
            }
        }

        // Gestion du menu mobile optimisée
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleMobileMenu() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }

        mobileMenuToggle.addEventListener('click', toggleMobileMenu);
        sidebarOverlay.addEventListener('click', toggleMobileMenu);

        // Fermer le menu en cliquant sur un lien
        sidebar.querySelectorAll('.nav-item').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    toggleMobileMenu();
                }
            });
        });

        // Adapter au redimensionnement
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // Masquer le menu au chargement sur mobile
        window.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth < 1024) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            }
        });

        // Empêcher le scroll quand le menu est ouvert
        sidebar.addEventListener('touchmove', function(e) {
            e.preventDefault();
        }, { passive: false });
    </script>
</body>
</html>

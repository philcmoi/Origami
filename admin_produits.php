<?php
// Inclure la protection au tout début
require_once 'admin_protection.php';

// Configuration de la base de données
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Gérer les actions sur les produits
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'ajouter':
                $nom = $_POST['nom'] ?? '';
                $description = $_POST['description'] ?? '';
                $prixHorsTaxe = $_POST['prixHorsTaxe'] ?? 0;
                $photo = $_POST['photo'] ?? '';

                if ($nom && $description && $prixHorsTaxe > 0) {
                    $stmt = $pdo->prepare("INSERT INTO Origami (nom, description, photo, prixHorsTaxe) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nom, $description, $photo, $prixHorsTaxe]);
                    $_SESSION['message_success'] = "Produit ajouté avec succès!";
                } else {
                    $_SESSION['message_error'] = "Tous les champs obligatoires doivent être remplis";
                }
                break;

            case 'modifier':
                $idOrigami = $_POST['idOrigami'] ?? null;
                $nom = $_POST['nom'] ?? '';
                $description = $_POST['description'] ?? '';
                $prixHorsTaxe = $_POST['prixHorsTaxe'] ?? 0;
                $photo = $_POST['photo'] ?? '';

                if ($idOrigami && $nom && $description && $prixHorsTaxe > 0) {
                    $stmt = $pdo->prepare("UPDATE Origami SET nom = ?, description = ?, photo = ?, prixHorsTaxe = ? WHERE idOrigami = ?");
                    $stmt->execute([$nom, $description, $photo, $prixHorsTaxe, $idOrigami]);
                    $_SESSION['message_success'] = "Produit modifié avec succès!";
                } else {
                    $_SESSION['message_error'] = "Tous les champs obligatoires doivent être remplis";
                }
                break;

            case 'supprimer':
                $idOrigami = $_POST['idOrigami'] ?? null;
                if ($idOrigami) {
                    // Vérifier si le produit est utilisé dans des commandes
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM LigneCommande WHERE idOrigami = ?");
                    $stmt->execute([$idOrigami]);
                    $count = $stmt->fetchColumn();

                    if ($count == 0) {
                        $stmt = $pdo->prepare("DELETE FROM Origami WHERE idOrigami = ?");
                        $stmt->execute([$idOrigami]);
                        $_SESSION['message_success'] = "Produit supprimé avec succès!";
                    } else {
                        $_SESSION['message_error'] = "Impossible de supprimer ce produit : il est associé à des commandes";
                    }
                }
                break;
        }

        // Recharger la page pour voir les modifications
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // Récupérer tous les produits
    $stmt = $pdo->query("SELECT * FROM Origami ORDER BY idOrigami DESC");
    $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer un produit spécifique pour édition
    $produitEdit = null;
    if (isset($_GET['edit'])) {
        $stmt = $pdo->prepare("SELECT * FROM Origami WHERE idOrigami = ?");
        $stmt->execute([$_GET['edit']]);
        $produitEdit = $stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits - Youki and Co</title>
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

        /* ===== HEADER OPTIMISÉ ===== */
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

        /* ===== LAYOUT PRINCIPAL ===== */
        .container {
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 80px);
        }

        /* ===== MENU MOBILE OPTIMISÉ ===== */
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
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .sidebar.active {
            display: block;
            transform: translateX(0);
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
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

        /* ===== CONTENU PRINCIPAL ===== */
        .main-content {
            flex: 1;
            padding: 15px;
        }

        /* ===== MESSAGES ===== */
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

        /* ===== STATISTIQUES RESPONSIVES ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        @media (min-width: 400px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
            }
        }

        .stat-card {
            background: white;
            padding: 20px 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid #d40000;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #d40000;
            margin-bottom: 6px;
            line-height: 1;
        }

        .stat-label {
            color: #666;
            font-size: 13px;
            font-weight: 500;
        }

        /* ===== SECTIONS ===== */
        .section {
            background: white;
            padding: 20px 15px;
            border-radius: 10px;
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
        }

        /* ===== FORMULAIRE ===== */
        .form-container {
            background: white;
            padding: 20px 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-group input:focus, .form-group textarea:focus {
            border-color: #d40000;
            outline: none;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-submit {
            background: #d40000;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
            font-weight: 500;
        }

        .btn-submit:hover {
            background: #b30000;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
            font-weight: 500;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        /* ===== GRILLE DE PRODUITS ===== */
        .product-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        @media (min-width: 576px) {
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 992px) {
            .product-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
            }
        }

        @media (min-width: 1200px) {
            .product-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid #eee;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .product-image {
            height: 180px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-bottom: 1px solid #eee;
        }

        @media (max-width: 576px) {
            .product-image {
                height: 160px;
            }
        }

        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .product-info {
            padding: 15px;
        }

        .product-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            line-height: 1.3;
        }

        .product-description {
            color: #666;
            font-size: 13px;
            margin-bottom: 12px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 18px;
            font-weight: bold;
            color: #d40000;
            margin-bottom: 15px;
        }

        .product-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-edit, .btn-delete {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            text-align: center;
            flex: 1;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-edit {
            background: #ffc107;
            color: #212529;
        }

        .btn-edit:hover {
            background: #e0a800;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        /* ===== PAGE TITLE ===== */
        .page-title {
            color: #d40000;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            font-size: 24px;
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 20px;
                text-align: center;
            }
        }

        /* ===== OVERLAY MENU MOBILE ===== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 98;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        /* ===== VERSION ORDINATEUR ===== */
        @media (min-width: 1024px) {
            /* Header desktop */
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
                gap: 15px;
            }

            /* Layout desktop */
            .container {
                flex-direction: row;
            }

            .mobile-menu-toggle {
                display: none;
            }

            .sidebar {
                display: block;
                position: static;
                width: 280px;
                height: auto;
                padding: 0;
                transform: none;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }

            .nav-item {
                padding: 18px 25px;
                font-size: 15px;
            }

            .main-content {
                padding: 25px;
                flex: 1;
                overflow-x: auto;
            }

            /* Statistiques desktop */
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 25px;
                margin-bottom: 30px;
            }

            .stat-card {
                padding: 30px 20px;
            }

            .stat-number {
                font-size: 32px;
            }

            .stat-label {
                font-size: 14px;
            }

            /* Sections desktop */
            .section {
                padding: 25px;
                margin-bottom: 25px;
            }

            .form-container {
                padding: 25px;
            }

            .section h2 {
                font-size: 20px;
                margin-bottom: 20px;
            }

            /* Formulaires desktop */
            .form-group input, .form-group textarea {
                font-size: 14px;
                padding: 10px 12px;
            }

            .btn-submit, .btn-cancel {
                padding: 12px 30px;
                font-size: 15px;
            }
        }

        /* ===== AMÉLIORATIONS TRÈS PETITS ÉCRANS ===== */
        @media (max-width: 360px) {
            .main-content {
                padding: 12px;
            }

            .stat-card {
                padding: 18px 12px;
            }

            .stat-number {
                font-size: 22px;
            }

            .product-card {
                padding: 14px;
            }

            .btn-edit, .btn-delete {
                padding: 10px 12px;
                font-size: 12px;
            }

            .nav-item {
                padding: 14px 16px;
                font-size: 14px;
            }
        }

        /* ===== AMÉLIORATIONS ÉCRANS MOYENS ===== */
        @media (min-width: 768px) and (max-width: 1023px) {
            .main-content {
                padding: 20px;
            }

            .section, .form-container {
                padding: 25px 20px;
            }

            .stat-card {
                padding: 25px 20px;
            }
        }

        /* ===== ÉTATS VIDES ===== */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            font-style: italic;
            font-size: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
            grid-column: 1 / -1;
        }

        /* ===== ANIMATIONS ET INTERACTIONS ===== */
        @media (hover: hover) {
            .stat-card:hover, .product-card:hover {
                transform: translateY(-2px);
            }
        }

        /* ===== ACCESSIBILITÉ ===== */
        @media (prefers-reduced-motion: reduce) {
            .sidebar, .sidebar-overlay, .stat-card, .product-card {
                transition: none;
            }
        }

        /* ===== IMPRESSION ===== */
        @media print {
            .sidebar, .mobile-menu-toggle, .btn-logout, .btn-edit, .btn-delete, .btn-submit, .btn-cancel {
                display: none;
            }

            .container {
                flex-direction: column;
            }

            .main-content {
                padding: 0;
            }

            .stat-card, .section, .form-container, .product-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>Youki and Co - Administration</h1>
        </div>
        <div class="admin-info">
            <span>Connecté: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="admin_dashboard.php?logout=1" class="btn-logout">Déconnexion</a>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        ☰ Menu Administration
    </button>

    <div class="container">
        <div class="sidebar" id="sidebar">
            <a href="admin_dashboard.php" class="nav-item">📊 Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">📦 Gestion des Commandes</a>
            <a href="admin_factures.php" class="nav-item">📄 Gestion des Factures</a>
            <a href="admin_clients.php" class="nav-item">👥 Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item active">🎨 Gestion des Produits</a>
        </div>

        <div class="main-content">
            <h1 class="page-title">🎨 Gestion des Produits</h1>

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
                    <div class="stat-number"><?= count($produits) ?></div>
                    <div class="stat-label">Total Produits</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?php
                        $prixMoyen = count($produits) > 0 
                            ? array_sum(array_column($produits, 'prixHorsTaxe')) / count($produits) 
                            : 0;
                        echo number_format($prixMoyen, 2, ',', ' ') . '€';
                        ?>
                    </div>
                    <div class="stat-label">Prix Moyen HT</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?php
                        $produitsAvecImage = count(array_filter($produits, function($p) {
                            return !empty($p['photo']);
                        }));
                        echo $produitsAvecImage . '/' . count($produits);
                        ?>
                    </div>
                    <div class="stat-label">Avec Image</div>
                </div>
            </div>

            <div class="form-container">
                <h2><?= $produitEdit ? 'Modifier le Produit' : 'Ajouter un Nouveau Produit' ?></h2>
                <form method="POST">
                    <?php if ($produitEdit): ?>
                        <input type="hidden" name="idOrigami" value="<?= $produitEdit['idOrigami'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="nom">Nom du produit *</label>
                        <input type="text" id="nom" name="nom"
                               value="<?= htmlspecialchars($produitEdit['nom'] ?? '') ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" required><?= htmlspecialchars($produitEdit['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="prixHorsTaxe">Prix Hors Taxe (€) *</label>
                        <input type="number" id="prixHorsTaxe" name="prixHorsTaxe"
                               step="0.01" min="0"
                               value="<?= htmlspecialchars($produitEdit['prixHorsTaxe'] ?? '') ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="photo">URL de l'image</label>
                        <input type="text" id="photo" name="photo"
                               value="<?= htmlspecialchars($produitEdit['photo'] ?? '') ?>"
                               placeholder="ex: img/nom-image.jpg">
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="action" value="<?= $produitEdit ? 'modifier' : 'ajouter' ?>" class="btn-submit">
                            <?= $produitEdit ? 'Modifier le Produit' : 'Ajouter le Produit' ?>
                        </button>

                        <?php if ($produitEdit): ?>
                            <a href="admin_produits.php" class="btn-cancel">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="section">
                <h2>Catalogue des Produits (<?= count($produits) ?>)</h2>

                <div class="product-grid">
                    <?php foreach ($produits as $produit): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if ($produit['photo']): ?>
                                <img src="<?= htmlspecialchars($produit['photo']) ?>"
                                     alt="<?= htmlspecialchars($produit['nom']) ?>"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjhjYzNjIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5MzMwMCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk9yaWdhbWk8L3RleHQ+PC9zdmc+'">
                            <?php else: ?>
                                <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjhjYzNjIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5MzMwMCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk9yaWdhbWk8L3RleHQ+PC9zdmc+" alt="Image par défaut">
                            <?php endif; ?>
                        </div>

                        <div class="product-info">
                            <div class="product-name"><?= htmlspecialchars($produit['nom']) ?></div>
                            <div class="product-description"><?= htmlspecialchars($produit['description']) ?></div>
                            <div class="product-price"><?= number_format($produit['prixHorsTaxe'], 2, ',', ' ') ?>€ HT</div>

                            <div class="product-actions">
                                <a href="admin_produits.php?edit=<?= $produit['idOrigami'] ?>" class="btn-edit">Modifier</a>

                                <form method="POST" style="display: inline; width: 100%;">
                                    <input type="hidden" name="idOrigami" value="<?= $produit['idOrigami'] ?>">
                                    <button type="submit" name="action" value="supprimer" class="btn-delete">
                                        Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (empty($produits)): ?>
                        <div class="empty-state">
                            <p style="color: #666; font-size: 18px;">Aucun produit trouvé dans le catalogue.</p>
                            <p style="color: #999;">Utilisez le formulaire ci-dessus pour ajouter votre premier produit.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Gestion du menu mobile optimisée (identique à admin_dashboard.php)
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleMobileMenu() {
            const isActive = sidebar.classList.contains('active');
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = isActive ? '' : 'hidden';

            // Animation du bouton
            mobileMenuToggle.style.transform = isActive ? 'none' : 'scale(0.98)';
        }

        function closeMobileMenu() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
            mobileMenuToggle.style.transform = 'none';
        }

        mobileMenuToggle.addEventListener('click', toggleMobileMenu);
        sidebarOverlay.addEventListener('click', closeMobileMenu);

        // Fermer le menu en cliquant sur un lien (mobile seulement)
        sidebar.querySelectorAll('.nav-item').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    closeMobileMenu();
                }
            });
        });

        // Adapter au redimensionnement
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                closeMobileMenu();
            }
        });

        // Masquer le menu au chargement sur mobile
        window.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth < 1024) {
                closeMobileMenu();
            }
        });

        // Empêcher le scroll quand le menu est ouvert
        sidebar.addEventListener('touchmove', function(e) {
            if (sidebar.classList.contains('active')) {
                e.preventDefault();
            }
        }, { passive: false });

        // Amélioration de l'accessibilité
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                closeMobileMenu();
                mobileMenuToggle.focus();
            }
        });

        // Focus management pour l'accessibilité
        mobileMenuToggle.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleMobileMenu();
            }
        });

        // Confirmation avant suppression
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.btn-delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.')) {
                        e.preventDefault();
                    }
                });
            });
        });

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message-success, .message-error');
            messages.forEach(message => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
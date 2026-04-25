<?php
// admin_produits.php - Gestion des produits origami (adapté à la structure existante)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'admin_protection.php';

$bdd = getConnexionBD();

// Créer le dossier d'upload s'il n'existe pas
$upload_dir = 'uploads/origami/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ==============================================
// TRAITEMENT DES ACTIONS
// ==============================================

// Ajouter un produit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'ajouter') {
        $nom = trim($_POST['nom']);
        $description = trim($_POST['description']);
        $prix = floatval($_POST['prix']);
        
        // Gestion de l'upload d'image
        $photo = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photoName = uploadImage($_FILES['photo']);
            if ($photoName) {
                $photo = 'uploads/origami/' . $photoName; // Chemin complet
            }
        }
        
        try {
            $stmt = $bdd->prepare("
                INSERT INTO Origami (nom, description, photo, prixHorsTaxe, visible)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([$nom, $description, $photo, $prix]);
            $_SESSION['success'] = "Produit ajouté avec succès !";
        } catch (PDOException $e) {
            error_log("Erreur ajout produit: " . $e->getMessage());
            $_SESSION['error'] = "Erreur lors de l'ajout : " . $e->getMessage();
        }
        header('Location: admin_produits.php');
        exit;
    }
    
    elseif ($action === 'modifier') {
        $id = intval($_POST['id']);
        $nom = trim($_POST['nom']);
        $description = trim($_POST['description']);
        $prix = floatval($_POST['prix']);
        
        // Gestion de l'upload d'image
        $photo = $_POST['photo_actuelle'] ?? '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            // Supprimer l'ancienne image si elle existe
            if ($photo && file_exists($photo)) {
                unlink($photo);
            }
            $photoName = uploadImage($_FILES['photo']);
            if ($photoName) {
                $photo = 'uploads/origami/' . $photoName;
            }
        }
        
        try {
            $stmt = $bdd->prepare("
                UPDATE Origami 
                SET nom = ?, description = ?, photo = ?, prixHorsTaxe = ?
                WHERE idOrigami = ?
            ");
            $stmt->execute([$nom, $description, $photo, $prix, $id]);
            $_SESSION['success'] = "Produit modifié avec succès !";
        } catch (PDOException $e) {
            error_log("Erreur modification produit: " . $e->getMessage());
            $_SESSION['error'] = "Erreur lors de la modification : " . $e->getMessage();
        }
        header('Location: admin_produits.php');
        exit;
    }
}

// Actions GET
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    // Masquer un produit (NOUVEAU)
    if ($action === 'masquer' && $id > 0) {
        try {
            $stmt = $bdd->prepare("UPDATE Origami SET visible = 0 WHERE idOrigami = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Produit masqué avec succès ! Il n'apparaîtra plus dans la boutique.";
        } catch (PDOException $e) {
            error_log("Erreur masquage produit: " . $e->getMessage());
            $_SESSION['error'] = "Erreur lors du masquage.";
        }
        header('Location: admin_produits.php' . (isset($_GET['afficher_masques']) ? '?afficher_masques=1' : ''));
        exit;
    }
    
    // Réactiver un produit (NOUVEAU)
    if ($action === 'reactiver' && $id > 0) {
        try {
            $stmt = $bdd->prepare("UPDATE Origami SET visible = 1 WHERE idOrigami = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Produit réactivé avec succès !";
        } catch (PDOException $e) {
            error_log("Erreur réactivation produit: " . $e->getMessage());
            $_SESSION['error'] = "Erreur lors de la réactivation.";
        }
        header('Location: admin_produits.php');
        exit;
    }
    
    // Supprimer un produit (conservé pour les produits sans commandes)
    if ($action === 'supprimer' && $id > 0) {
        try {
            // Vérifier si le produit est dans des commandes
            $stmt = $bdd->prepare("SELECT COUNT(*) as total FROM LigneCommande WHERE idOrigami = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetch()['total'];
            
            if ($count > 0) {
                $_SESSION['warning'] = "Ce produit ne peut pas être supprimé car il est référencé dans des commandes. Il a été masqué à la place.";
                $stmt = $bdd->prepare("UPDATE Origami SET visible = 0 WHERE idOrigami = ?");
                $stmt->execute([$id]);
            } else {
                // Vérifier aussi dans les paniers
                $stmt = $bdd->prepare("SELECT COUNT(*) as total FROM LignePanier WHERE idOrigami = ?");
                $stmt->execute([$id]);
                $countPanier = $stmt->fetch()['total'];
                
                if ($countPanier > 0) {
                    // Supprimer d'abord les lignes panier
                    $stmt = $bdd->prepare("DELETE FROM LignePanier WHERE idOrigami = ?");
                    $stmt->execute([$id]);
                }
                
                // Supprimer l'image
                $stmt = $bdd->prepare("SELECT photo FROM Origami WHERE idOrigami = ?");
                $stmt->execute([$id]);
                $produit = $stmt->fetch();
                if ($produit && $produit['photo'] && file_exists($produit['photo'])) {
                    unlink($produit['photo']);
                }
                
                $stmt = $bdd->prepare("DELETE FROM Origami WHERE idOrigami = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = "Produit supprimé définitivement !";
            }
        } catch (PDOException $e) {
            error_log("Erreur suppression produit: " . $e->getMessage());
            $_SESSION['error'] = "Erreur lors de la suppression.";
        }
        header('Location: admin_produits.php');
        exit;
    }
}

// ==============================================
// FONCTION D'UPLOAD D'IMAGE
// ==============================================
function uploadImage($file) {
    $dossier_upload = 'uploads/origami/';
    if (!file_exists($dossier_upload)) {
        mkdir($dossier_upload, 0755, true);
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extensions_autorisees = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($extension, $extensions_autorisees)) {
        $_SESSION['error'] = "Format d'image non autorisé. Utilisez JPG, PNG, GIF ou WEBP.";
        return '';
    }
    
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        $_SESSION['error'] = "L'image ne doit pas dépasser 5 Mo.";
        return '';
    }
    
    $nom_fichier = 'produit_' . uniqid() . '_' . date('Ymd_His') . '.' . $extension;
    $chemin_destination = $dossier_upload . $nom_fichier;
    
    if (move_uploaded_file($file['tmp_name'], $chemin_destination)) {
        return $nom_fichier; // Retourne juste le nom, le chemin sera ajouté au moment du stockage
    }
    
    return '';
}

// ==============================================
// PAGINATION ET FILTRES
// ==============================================
$page_courante = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$recherche = isset($_GET['search']) ? trim($_GET['search']) : '';
$afficher_masques = isset($_GET['afficher_masques']) && $_GET['afficher_masques'] == '1';
$elements_par_page = 15;

// Construction de la requête
$where_conditions = [];
$params = [];

if (!empty($recherche)) {
    $where_conditions[] = "(nom LIKE :search OR description LIKE :search)";
    $params[':search'] = "%$recherche%";
}

// Filtre visibilité
if (!$afficher_masques) {
    $where_conditions[] = "visible = 1";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Pagination
$count_sql = "SELECT COUNT(*) as total FROM Origami $where_clause";
$stmt = $bdd->prepare($count_sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_elements = $stmt->fetch()['total'];
$total_pages = ceil($total_elements / $elements_par_page);
$offset = ($page_courante - 1) * $elements_par_page;

// Récupération des produits
$sql = "SELECT * FROM Origami $where_clause ORDER BY idOrigami DESC LIMIT :offset, :limit";
$stmt = $bdd->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $elements_par_page, PDO::PARAM_INT);
$stmt->execute();
$produits = $stmt->fetchAll();

// Statistiques
$stmt = $bdd->query("SELECT COUNT(*) as total FROM Origami WHERE visible = 1");
$stats_visibles = $stmt->fetch()['total'];
$stmt = $bdd->query("SELECT COUNT(*) as total FROM Origami WHERE visible = 0");
$stats_masques = $stmt->fetch()['total'];
$stmt = $bdd->query("SELECT COUNT(*) as total FROM Origami");
$stats_total = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des produits - Youki and Co</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #d40000;
            --primary-dark: #b30000;
            --secondary: #764ba2;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #1a1a2e;
            --light: #f8f9fa;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --border-radius: 16px;
            --box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .logo h1 {
            color: var(--primary);
            font-size: 24px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-logout {
            background: var(--primary);
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn-logout:hover {
            background: var(--primary-dark);
        }

        /* Navigation */
        .nav-admin {
            background: white;
            border-radius: var(--border-radius);
            padding: 15px 20px;
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-item {
            padding: 10px 20px;
            color: #333;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .nav-item:hover, .nav-item.active {
            background: var(--primary);
            color: white;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
        }

        .stat-card .label {
            color: var(--gray);
            font-size: 0.85rem;
        }

        /* Filters Bar */
        .filters-bar {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .search-box {
            display: flex;
            gap: 10px;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 30px;
            font-family: 'Montserrat', sans-serif;
        }

        .search-box button {
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 8px 15px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.85rem;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: var(--border-radius);
            overflow-x: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .product-image-placeholder {
            width: 60px;
            height: 60px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.8rem;
        }

        .btn-edit {
            background: var(--info);
            color: white;
        }

        .btn-hide {
            background: var(--warning);
            color: #333;
        }

        .btn-reactiver {
            background: var(--success);
            color: white;
        }

        .btn-delete {
            background: var(--danger);
            color: white;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .status-visible {
            background: #d4edda;
            color: #155724;
        }

        .status-hidden {
            background: #f8d7da;
            color: #721c24;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            padding: 8px 14px;
            background: white;
            border-radius: 8px;
            text-decoration: none;
            color: var(--primary);
            font-weight: 500;
        }

        .pagination .current {
            background: var(--primary);
            color: white;
        }

        /* Alertes */
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger);
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid var(--warning);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            margin: 50px auto;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            background: var(--primary);
            color: white;
            padding: 20px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header .close {
            font-size: 28px;
            cursor: pointer;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
        }

        .image-preview {
            max-width: 150px;
            margin-top: 10px;
            border-radius: 8px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            color: var(--gray);
            font-size: 0.8rem;
        }
        
        /* Loading spinner */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,.1);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .filter-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-toggle label {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <h1>Youki and Co - Gestion des Produits</h1>
            </div>
            <div class="user-info">
                <span>Connecté en tant que: <?= htmlspecialchars($_SESSION['admin_email'] ?? 'Admin') ?></span>
                <a href="admin_dashboard.php?logout=1" class="btn-logout">Déconnexion</a>
            </div>
        </div>

        <!-- Navigation -->
        <div class="nav-admin">
            <a href="admin_dashboard.php" class="nav-item">Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">Commandes</a>
            <a href="admin_factures.php" class="nav-item">Factures</a>
            <a href="admin_clients.php" class="nav-item">Clients</a>
            <a href="admin_produits.php" class="nav-item active">Produits</a>
        </div>

        <!-- Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['warning'])): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <?= $_SESSION['warning']; unset($_SESSION['warning']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='admin_produits.php'">
                <i class="fas fa-box"></i>
                <div class="number"><?= $stats_visibles ?></div>
                <div class="label">Produits visibles</div>
            </div>
            <div class="stat-card" onclick="window.location.href='admin_produits.php?afficher_masques=1'">
                <i class="fas fa-eye-slash"></i>
                <div class="number"><?= $stats_masques ?></div>
                <div class="label">Produits masqués</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-database"></i>
                <div class="number"><?= $stats_total ?></div>
                <div class="label">Total produits</div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filters-bar">
            <form method="GET" class="search-box">
                <input type="text" name="search" placeholder="Rechercher un produit..." 
                       value="<?= htmlspecialchars($recherche) ?>">
                <?php if($afficher_masques): ?>
                    <input type="hidden" name="afficher_masques" value="1">
                <?php endif; ?>
                <button type="submit"><i class="fas fa-search"></i></button>
                <?php if(!empty($recherche) || $afficher_masques): ?>
                    <a href="admin_produits.php" class="btn-outline">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                <?php endif; ?>
            </form>
            
            <div class="filter-toggle">
                <input type="checkbox" id="afficherMasques" <?= $afficher_masques ? 'checked' : '' ?>
                       onchange="window.location.href='admin_produits.php?afficher_masques=' + (this.checked ? 1 : 0)">
                <label for="afficherMasques">
                    <i class="fas fa-eye-slash"></i> Afficher les produits masqués
                </label>
            </div>
            
            <button onclick="openAjoutModal()" class="btn-primary">
                <i class="fas fa-plus"></i> Ajouter un produit
            </button>
        </div>

        <!-- Table des produits -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Nom</th>
                        <th>Description</th>
                        <th>Prix HT</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($produits)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.5;"></i>
                                <p>Aucun produit trouvé</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($produits as $p): ?>
                        <tr>
                            <td><strong>#<?= $p['idOrigami'] ?></strong></td>
                            <td>
                                <?php if($p['photo'] && file_exists($p['photo'])): ?>
                                    <img src="<?= htmlspecialchars($p['photo']) ?>" class="product-image" alt="<?= htmlspecialchars($p['nom']) ?>">
                                <?php else: ?>
                                    <div class="product-image-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($p['nom']) ?></strong>
                                <?php if($afficher_masques && isset($p['visible']) && $p['visible'] == 0): ?>
                                    <br><span class="status-badge status-hidden"><i class="fas fa-eye-slash"></i> Masqué</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(substr($p['description'], 0, 80)) ?>...</td>
                            <td><?= number_format($p['prixHorsTaxe'], 2) ?> €</td>
                            <td>
                                <?php if(isset($p['visible']) && $p['visible'] == 0): ?>
                                    <span class="status-badge status-hidden">Masqué</span>
                                <?php else: ?>
                                    <span class="status-badge status-visible">Visible</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <button onclick="editProduit(<?= $p['idOrigami'] ?>)" class="btn-icon btn-edit" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if(isset($p['visible']) && $p['visible'] == 0): ?>
                                    <a href="?action=reactiver&id=<?= $p['idOrigami'] ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>" 
                                       class="btn-icon btn-reactiver" 
                                       onclick="return confirm('Réactiver ce produit ? Il réapparaîtra dans la boutique.')" 
                                       title="Réactiver">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="?action=masquer&id=<?= $p['idOrigami'] ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>" 
                                       class="btn-icon btn-hide" 
                                       onclick="return confirm('Masquer ce produit ? Il n\'apparaîtra plus dans la boutique (mais restera dans l\'historique des commandes).')" 
                                       title="Masquer">
                                        <i class="fas fa-eye-slash"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="?action=supprimer&id=<?= $p['idOrigami'] ?>" 
                                   class="btn-icon btn-delete" 
                                   onclick="return confirm('Supprimer définitivement ce produit ? (uniquement possible s\'il n\'a pas de commandes associées)')" 
                                   title="Supprimer">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php if($page_courante > 1): ?>
                    <a href="?page=<?= $page_courante - 1 ?>&search=<?= urlencode($recherche) ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>

                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if($i == $page_courante): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($recherche) ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if($page_courante < $total_pages): ?>
                    <a href="?page=<?= $page_courante + 1 ?>&search=<?= urlencode($recherche) ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="footer">
            <p>&copy; <?= date('Y') ?> Youki and Co - Tous droits réservés</p>
        </div>
    </div>

    <!-- MODAL AJOUT/MODIFICATION PRODUIT -->
    <div id="modalProduit" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-plus"></i> Ajouter un produit</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="produitForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="ajouter">
                <input type="hidden" name="id" id="produitId" value="">
                <input type="hidden" name="photo_actuelle" id="photoActuelle" value="">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nom du produit *</label>
                        <input type="text" name="nom" id="nom" required placeholder="ex: Grue en origami">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="description" rows="3" placeholder="Description détaillée du produit..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Prix HT (€) *</label>
                        <input type="number" name="prix" id="prix" step="0.01" min="0" required placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label>Photo du produit</label>
                        <input type="file" name="photo" id="photo" accept="image/*">
                        <div id="previewImage"></div>
                        <small>Formats acceptés : JPG, PNG, GIF, WEBP — Taille max : 5 Mo</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn-primary" id="submitBtn">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let isLoading = false;
        
        function openAjoutModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> Ajouter un produit';
            document.getElementById('formAction').value = 'ajouter';
            document.getElementById('produitId').value = '';
            document.getElementById('photoActuelle').value = '';
            document.getElementById('produitForm').reset();
            document.getElementById('previewImage').innerHTML = '';
            document.getElementById('modalProduit').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function editProduit(id) {
            if(isLoading) return;
            isLoading = true;
            
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="loading"></span> Chargement...';
            submitBtn.disabled = true;
            
            console.log('Chargement du produit ID:', id);
            
            fetch('get_produit.php?id=' + id)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur HTTP: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Données reçues:', data);
                    if(data.success) {
                        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Modifier le produit';
                        document.getElementById('formAction').value = 'modifier';
                        document.getElementById('produitId').value = id;
                        document.getElementById('photoActuelle').value = data.photo || '';
                        document.getElementById('nom').value = data.nom || '';
                        document.getElementById('description').value = data.description || '';
                        document.getElementById('prix').value = data.prixHorsTaxe || '';
                        
                        const previewDiv = document.getElementById('previewImage');
                        previewDiv.innerHTML = '';
                        if(data.photo && data.photo !== '') {
                            const img = document.createElement('img');
                            img.src = data.photo;
                            img.className = 'image-preview';
                            img.onerror = function() {
                                console.log('Image non trouvée:', data.photo);
                                previewDiv.innerHTML = '<p style="color: #dc3545; font-size: 0.8rem;">Image actuelle introuvable</p>';
                            };
                            previewDiv.appendChild(img);
                        }
                        
                        document.getElementById('modalProduit').style.display = 'block';
                        document.body.style.overflow = 'hidden';
                    } else {
                        alert('Erreur: ' + (data.error || 'Produit non trouvé'));
                    }
                })
                .catch(error => {
                    console.error('Erreur détaillée:', error);
                    alert('Erreur lors du chargement: ' + error.message + '\n\nVérifiez que le fichier get_produit.php existe et est accessible.');
                })
                .finally(() => {
                    isLoading = false;
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
        }
        
        function closeModal() {
            document.getElementById('modalProduit').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        window.onclick = function(event) {
            if(event.target.classList.contains('modal')) {
                closeModal();
            }
        }
        
        document.getElementById('photo').addEventListener('change', function(e) {
            const preview = document.getElementById('previewImage');
            if(e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const img = document.createElement('img');
                    img.src = ev.target.result;
                    img.className = 'image-preview';
                    if(preview.querySelector('img')) {
                        preview.replaceChild(img, preview.querySelector('img'));
                    } else {
                        preview.appendChild(img);
                    }
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
        
        // Validation du formulaire avant soumission
        document.getElementById('produitForm').addEventListener('submit', function(e) {
            const nom = document.getElementById('nom').value.trim();
            const prix = document.getElementById('prix').value;
            
            if(!nom) {
                e.preventDefault();
                alert('Veuillez saisir un nom de produit');
                return false;
            }
            
            if(!prix || parseFloat(prix) < 0) {
                e.preventDefault();
                alert('Veuillez saisir un prix valide');
                return false;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<span class="loading"></span> Enregistrement...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>
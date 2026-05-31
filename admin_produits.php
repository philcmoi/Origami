<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'admin_protection.php';

if (!function_exists('getConnexionBD')) {
    function getConnexionBD() {
        global $host, $dbname, $username, $password;
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        } catch (PDOException $e) {
            die("Erreur de connexion : " . $e->getMessage());
        }
    }
}

$bdd = getConnexionBD();

$upload_dir = 'uploads/origami/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

function uploadImage($file) {
    $dossier_upload = 'uploads/origami/';
    if (!file_exists($dossier_upload)) {
        mkdir($dossier_upload, 0755, true);
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extensions_autorisees = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($extension, $extensions_autorisees)) {
        $_SESSION['error'] = "Format d'image non autorisé (JPG, PNG, GIF, WEBP).";
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
        return $nom_fichier;
    }
    return '';
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'ajouter') {
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $prix = floatval($_POST['prix'] ?? 0);
            
            // Validation des champs
            if (empty($nom)) {
                $_SESSION['error'] = "Le nom du produit est obligatoire.";
            } elseif ($prix <= 0) {
                $_SESSION['error'] = "Le prix doit être supérieur à 0.";
            } else {
                $photo = '';
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $photoName = uploadImage($_FILES['photo']);
                    if ($photoName) {
                        $photo = 'uploads/origami/' . $photoName;
                    }
                }
                
                try {
                    $stmt = $bdd->prepare("
                        INSERT INTO Origami (nom, description, photo, prixHorsTaxe, visible)
                        VALUES (?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$nom, $description, $photo, $prix]);
                    $_SESSION['success'] = "Produit \"$nom\" ajouté avec succès !";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Erreur lors de l'ajout : " . $e->getMessage();
                }
            }
            header('Location: admin_produits.php');
            exit;
        }
        
        elseif ($action === 'modifier') {
            $id = intval($_POST['id'] ?? 0);
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $prix = floatval($_POST['prix'] ?? 0);
            
            if (empty($nom)) {
                $_SESSION['error'] = "Le nom du produit est obligatoire.";
            } elseif ($prix <= 0) {
                $_SESSION['error'] = "Le prix doit être supérieur à 0.";
            } else {
                $photo = $_POST['photo_actuelle'] ?? '';
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
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
                    $_SESSION['success'] = "Produit \"$nom\" modifié avec succès !";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Erreur lors de la modification : " . $e->getMessage();
                }
            }
            header('Location: admin_produits.php');
            exit;
        }
    }
}

// Actions GET
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($action === 'masquer' && $id > 0) {
        try {
            $stmt = $bdd->prepare("UPDATE Origami SET visible = 0 WHERE idOrigami = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Produit masqué avec succès !";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors du masquage.";
        }
        header('Location: admin_produits.php' . (isset($_GET['afficher_masques']) ? '?afficher_masques=1' : ''));
        exit;
    }
    
    if ($action === 'reactiver' && $id > 0) {
        try {
            $stmt = $bdd->prepare("UPDATE Origami SET visible = 1 WHERE idOrigami = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Produit réactivé avec succès !";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de la réactivation.";
        }
        header('Location: admin_produits.php');
        exit;
    }
    
    if ($action === 'supprimer' && $id > 0) {
        try {
            $stmt = $bdd->prepare("SELECT COUNT(*) as total FROM LigneCommande WHERE idOrigami = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetch()['total'];
            
            if ($count > 0) {
                $_SESSION['warning'] = "Ce produit ne peut pas être supprimé car il est référencé dans des commandes. Il a été masqué.";
                $stmt = $bdd->prepare("UPDATE Origami SET visible = 0 WHERE idOrigami = ?");
                $stmt->execute([$id]);
            } else {
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
            $_SESSION['error'] = "Erreur lors de la suppression.";
        }
        header('Location: admin_produits.php');
        exit;
    }
}

// Pagination et filtres
$page_courante = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$recherche = isset($_GET['search']) ? trim($_GET['search']) : '';
$afficher_masques = isset($_GET['afficher_masques']) && $_GET['afficher_masques'] == '1';
$elements_par_page = 12;

$where_conditions = [];
$params = [];

if (!empty($recherche)) {
    $where_conditions[] = "(nom LIKE :search OR description LIKE :search)";
    $params[':search'] = "%$recherche%";
}

if (!$afficher_masques) {
    $where_conditions[] = "visible = 1";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$count_sql = "SELECT COUNT(*) as total FROM Origami $where_clause";
$stmt = $bdd->prepare($count_sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_elements = $stmt->fetch()['total'];
$total_pages = ceil($total_elements / $elements_par_page);
$offset = ($page_courante - 1) * $elements_par_page;

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Produits - Youki and Co</title>
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
        .nav-item.active i { color: white; }
        
        /* Lien retour dashboard */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
            margin-right: 16px;
            transition: all 0.2s;
        }
        
        .back-link:hover {
            background: var(--gray-200);
            color: var(--primary);
        }
        
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
        
        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
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
        .page-title p { font-size: 0.75rem; color: var(--gray-500); margin-top: 2px; }
        
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
        .btn-logout:hover { background: var(--gray-200); color: var(--primary); }
        
        .content-wrapper { padding: 24px; }
        @media (max-width: 640px) { .content-wrapper { padding: 16px; } }
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 16px;
            border: 1px solid var(--gray-200);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .stat-number { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .stat-label { font-size: 0.65rem; color: var(--gray-500); text-transform: uppercase; }
        
        /* Filters */
        .filters-bar {
            background: white;
            border-radius: var(--border-radius);
            padding: 16px 20px;
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            flex: 1;
            max-width: 400px;
        }
        
        .search-box input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid var(--gray-300);
            border-radius: 10px;
            font-size: 0.85rem;
        }
        
        .search-box input:focus { border-color: var(--primary); outline: none; }
        .search-box button {
            padding: 10px 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }
        
        .filter-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-toggle label { font-size: 0.8rem; cursor: pointer; }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        
        .btn-primary:hover { background: var(--primary-dark); }
        
        /* Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }
        .alert-success { background: #d4edda; color: #155724; border-left: 3px solid var(--success); }
        .alert-error { background: #fef3f2; color: var(--danger); border-left: 3px solid var(--danger); }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 3px solid var(--warning); }
        
        /* Products grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .product-card {
            background: white;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: all 0.2s;
        }
        
        .product-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        
        .product-image {
            height: 180px;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-image-placeholder {
            font-size: 3rem;
            color: var(--gray-400);
        }
        
        .product-info {
            padding: 16px;
        }
        
        .product-title {
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
            align-items: start;
        }
        
        .product-price {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.1rem;
            margin: 8px 0;
        }
        
        .product-desc {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-bottom: 12px;
            line-height: 1.4;
        }
        
        .product-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .status-visible { background: #d4edda; color: #155724; }
        .status-hidden { background: #fef3f2; color: var(--danger); }
        
        .product-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--gray-200);
            flex-wrap: wrap;
        }
        
        .btn-icon {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            border: none;
        }
        .btn-edit { background: var(--info); color: white; }
        .btn-hide { background: var(--warning); color: #333; }
        .btn-reactiver { background: var(--success); color: white; }
        .btn-delete { background: var(--danger); color: white; }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 14px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            text-decoration: none;
            color: var(--primary);
            font-size: 0.8rem;
        }
        
        .pagination .current {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Modals */
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
            border-radius: 16px;
            width: 90%;
            max-width: 550px;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 480px) {
            .modal-content { margin: 20px auto; width: 95%; }
        }
        
        .modal-header {
            background: var(--primary);
            color: white;
            padding: 16px 20px;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 { font-size: 1.1rem; }
        .modal-header .close { font-size: 24px; cursor: pointer; }
        
        .modal-body { padding: 20px; }
        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.75rem;
            margin-bottom: 6px;
            color: var(--gray-700);
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gray-300);
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.85rem;
        }
        .form-group input:focus, .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .image-preview {
            max-width: 100px;
            margin-top: 10px;
            border-radius: 8px;
        }
        
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
        
        .btn-outline {
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 8px 16px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
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
                <a href="admin_commandes.php" class="nav-item"><i class="fas fa-shopping-cart"></i> Commandes</a>
                <a href="admin_factures.php" class="nav-item"><i class="fas fa-file-invoice"></i> Factures</a>
                <a href="admin_clients.php" class="nav-item"><i class="fas fa-users"></i> Clients</a>
                <a href="admin_produits.php" class="nav-item active"><i class="fas fa-box"></i> Produits</a>
            </nav>
        </aside>
        
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <main class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                    <a href="dashboard.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                    </a>
                    <div class="page-title">
                        <h1>Gestion des produits</h1>
                        <p><?= $stats_total ?> produit(s) au total</p>
                    </div>
                </div>
                <div class="user-info">
                    <span class="user-email"><?= htmlspecialchars($_SESSION['admin_email']) ?></span>
                    <a href="dashboard.php?logout=1" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                </div>
            </div>
            
            <div class="content-wrapper">
                <!-- Messages -->
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                <?php if(isset($_SESSION['warning'])): ?>
                    <div class="alert alert-warning"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($_SESSION['warning']); unset($_SESSION['warning']); ?></div>
                <?php endif; ?>
                
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card" onclick="window.location.href='admin_produits.php'">
                        <div class="stat-number"><?= $stats_visibles ?></div>
                        <div class="stat-label">Visibles</div>
                    </div>
                    <div class="stat-card" onclick="window.location.href='admin_produits.php?afficher_masques=1'">
                        <div class="stat-number"><?= $stats_masques ?></div>
                        <div class="stat-label">Masqués</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats_total ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filters-bar">
                    <form method="GET" class="search-box">
                        <input type="text" name="search" placeholder="Rechercher un produit..." value="<?= htmlspecialchars($recherche) ?>">
                        <?php if($afficher_masques): ?>
                            <input type="hidden" name="afficher_masques" value="1">
                        <?php endif; ?>
                        <button type="submit"><i class="fas fa-search"></i></button>
                        <?php if(!empty($recherche) || $afficher_masques): ?>
                            <a href="admin_produits.php" class="btn-outline"><i class="fas fa-times"></i> Réinitialiser</a>
                        <?php endif; ?>
                    </form>
                    
                    <div class="filter-toggle">
                        <input type="checkbox" id="afficherMasques" <?= $afficher_masques ? 'checked' : '' ?>
                               onchange="window.location.href='admin_produits.php?afficher_masques=' + (this.checked ? 1 : 0)">
                        <label for="afficherMasques">Afficher les produits masqués</label>
                    </div>
                    
                    <button onclick="openAjoutModal()" class="btn-primary"><i class="fas fa-plus"></i> Ajouter</button>
                </div>
                
                <!-- Products Grid -->
                <?php if(empty($produits)): ?>
                    <div style="text-align: center; padding: 60px; background: white; border-radius: var(--border-radius); border: 1px solid var(--gray-200);">
                        <i class="fas fa-box-open" style="font-size: 3rem; color: var(--gray-400); margin-bottom: 16px;"></i>
                        <p>Aucun produit trouvé</p>
                        <button onclick="openAjoutModal()" class="btn-primary" style="margin-top: 16px;"><i class="fas fa-plus"></i> Ajouter votre premier produit</button>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach($produits as $p): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php if(!empty($p['photo']) && file_exists($p['photo'])): ?>
                                    <img src="<?= htmlspecialchars($p['photo']) ?>" alt="<?= htmlspecialchars($p['nom']) ?>">
                                <?php else: ?>
                                    <div class="product-image-placeholder"><i class="fas fa-image"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <div class="product-title">
                                    <span><?= htmlspecialchars($p['nom']) ?></span>
                                    <span class="product-status <?= ($p['visible'] ?? 1) ? 'status-visible' : 'status-hidden' ?>">
                                        <?= ($p['visible'] ?? 1) ? 'Visible' : 'Masqué' ?>
                                    </span>
                                </div>
                                <div class="product-price"><?= number_format($p['prixHorsTaxe'], 2) ?> €</div>
                                <div class="product-desc"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 80)) ?>...</div>
                                <div class="product-actions">
                                    <button onclick="editProduit(<?= $p['idOrigami'] ?>)" class="btn-icon btn-edit"><i class="fas fa-edit"></i> Modifier</button>
                                    <?php if(isset($p['visible']) && $p['visible'] == 0): ?>
                                        <a href="?action=reactiver&id=<?= $p['idOrigami'] ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>" class="btn-icon btn-reactiver" onclick="return confirm('Réactiver ce produit ?')"><i class="fas fa-eye"></i> Réactiver</a>
                                    <?php else: ?>
                                        <a href="?action=masquer&id=<?= $p['idOrigami'] ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>" class="btn-icon btn-hide" onclick="return confirm('Masquer ce produit ?')"><i class="fas fa-eye-slash"></i> Masquer</a>
                                    <?php endif; ?>
                                    <a href="?action=supprimer&id=<?= $p['idOrigami'] ?>" class="btn-icon btn-delete" onclick="return confirm('⚠️ Supprimer définitivement ce produit ? Action irréversible.')"><i class="fas fa-trash-alt"></i> Supprimer</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php if($page_courante > 1): ?>
                        <a href="?page=<?= $page_courante - 1 ?>&search=<?= urlencode($recherche) ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if($i == $page_courante): ?>
                            <span class="current"><?= $i ?></span>
                        <?php elseif($i <= 5 || $i > $total_pages - 2 || ($i >= $page_courante - 2 && $i <= $page_courante + 2)): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($recherche) ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if($page_courante < $total_pages): ?>
                        <a href="?page=<?= $page_courante + 1 ?>&search=<?= urlencode($recherche) ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="footer">
                <p>&copy; <?= date('Y') ?> Youki and Co - Créations artisanales japonaises</p>
            </div>
        </main>
    </div>
    
    <!-- Modal Ajout -->
    <div id="modalAjout" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus"></i> Ajouter un produit</h2>
                <span class="close" onclick="closeModal('modalAjout')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data" id="formAjout">
                <input type="hidden" name="action" value="ajouter">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nom du produit *</label>
                        <input type="text" name="nom" required placeholder="Ex: Grue en papier">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="4" placeholder="Description détaillée du produit..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Prix HT (€) *</label>
                        <input type="number" name="prix" step="0.01" min="0" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Image</label>
                        <input type="file" name="photo" accept="image/*" onchange="previewImage(this, 'previewAjout')">
                        <img id="previewAjout" class="image-preview" style="display: none;">
                        <p style="font-size: 0.7rem; color: #888; margin-top: 5px;">Formats acceptés: JPG, PNG, GIF, WEBP (max 5 Mo)</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('modalAjout')">Annuler</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Ajouter le produit</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Modification -->
    <div id="modalEdit" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Modifier le produit</h2>
                <span class="close" onclick="closeModal('modalEdit')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data" id="formEdit">
                <input type="hidden" name="action" value="modifier">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nom du produit *</label>
                        <input type="text" name="nom" id="edit_nom" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Prix HT (€) *</label>
                        <input type="number" name="prix" id="edit_prix" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Image actuelle</label>
                        <div id="current_image_container"></div>
                        <input type="hidden" name="photo_actuelle" id="edit_photo_actuelle">
                        <label style="margin-top: 10px;">Changer d'image</label>
                        <input type="file" name="photo" accept="image/*" onchange="previewImage(this, 'previewEdit')">
                        <img id="previewEdit" class="image-preview" style="display: none;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('modalEdit')">Annuler</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Données des produits pour l'édition
        const produits = <?php 
            $data = [];
            foreach($produits as $p) {
                $data[$p['idOrigami']] = [
                    'nom' => $p['nom'],
                    'description' => $p['description'] ?? '',
                    'prix' => $p['prixHorsTaxe'],
                    'photo' => $p['photo']
                ];
            }
            echo json_encode($data);
        ?>;
        
        function openAjoutModal() {
            document.getElementById('modalAjout').style.display = 'block';
            document.getElementById('formAjout').reset();
            document.getElementById('previewAjout').style.display = 'none';
            document.getElementById('previewAjout').src = '';
        }
        
        function editProduit(id) {
            const produit = produits[id];
            if (!produit) return;
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nom').value = produit.nom;
            document.getElementById('edit_description').value = produit.description || '';
            document.getElementById('edit_prix').value = produit.prix;
            document.getElementById('edit_photo_actuelle').value = produit.photo || '';
            
            const container = document.getElementById('current_image_container');
            if (produit.photo && produit.photo !== '') {
                container.innerHTML = `<img src="${produit.photo}" class="image-preview" style="max-width: 100px;">`;
            } else {
                container.innerHTML = '<p style="color: #999; font-size: 0.75rem;">Aucune image</p>';
            }
            
            document.getElementById('previewEdit').style.display = 'none';
            document.getElementById('modalEdit').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
                preview.src = '';
            }
        }
        
        // Fermer modal en cliquant en dehors
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Menu mobile
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            });
        }
        
        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            });
        }
    </script>
</body>
</html>
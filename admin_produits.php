<?php
// admin_produits.php - Gestion des produits origami (version responsive)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'admin_protection.php';

// Fonction de connexion si elle n'existe pas dans config.php
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

// Créer le dossier d'upload s'il n'existe pas
$upload_dir = 'uploads/origami/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ==============================================
// FONCTIONS
// ==============================================

function uploadImage($file) {
    $dossier_upload = 'uploads/origami/';
    if (!file_exists($dossier_upload)) {
        mkdir($dossier_upload, 0755, true);
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extensions_autorisees = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($extension, $extensions_autorisees)) {
        $_SESSION['error'] = "Format d'image non autorisé (JPG, PNG, GIF, WEBP uniquement).";
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

// ==============================================
// TRAITEMENT DES ACTIONS
// ==============================================

// Ajouter un produit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'ajouter') {
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $prix = floatval($_POST['prix'] ?? 0);
            
            // Gestion de l'upload d'image
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
                $_SESSION['success'] = "Produit ajouté avec succès !";
            } catch (PDOException $e) {
                error_log("Erreur ajout produit: " . $e->getMessage());
                $_SESSION['error'] = "Erreur lors de l'ajout : " . $e->getMessage();
            }
            header('Location: admin_produits.php');
            exit;
        }
        
        elseif ($action === 'modifier') {
            $id = intval($_POST['id'] ?? 0);
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $prix = floatval($_POST['prix'] ?? 0);
            
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
                $_SESSION['success'] = "Produit modifié avec succès !";
            } catch (PDOException $e) {
                error_log("Erreur modification produit: " . $e->getMessage());
                $_SESSION['error'] = "Erreur lors de la modification : " . $e->getMessage();
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
            // Vérifier si le produit est utilisé dans des commandes
            $stmt = $bdd->prepare("SELECT COUNT(*) as total FROM LigneCommande WHERE idOrigami = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetch()['total'];
            
            if ($count > 0) {
                $_SESSION['warning'] = "Ce produit ne peut pas être supprimé car il est référencé dans des commandes. Il a été masqué.";
                $stmt = $bdd->prepare("UPDATE Origami SET visible = 0 WHERE idOrigami = ?");
                $stmt->execute([$id]);
            } else {
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
            $_SESSION['error'] = "Erreur lors de la suppression.";
        }
        header('Location: admin_produits.php');
        exit;
    }
}

// ==============================================
// PAGINATION ET FILTRES
// ==============================================
$page_courante = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$recherche = isset($_GET['search']) ? trim($_GET['search']) : '';
$afficher_masques = isset($_GET['afficher_masques']) && $_GET['afficher_masques'] == '1';
$elements_par_page = 15;

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

// Compter le total
$count_sql = "SELECT COUNT(*) as total FROM Origami $where_clause";
$stmt = $bdd->prepare($count_sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_elements = $stmt->fetch()['total'];
$total_pages = ceil($total_elements / $elements_par_page);
$offset = ($page_courante - 1) * $elements_par_page;

// Récupérer les produits
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
    <title>Gestion des produits - Youki and Co</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --gray: #6c757d;
            --border-radius: 16px;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f5f7fb;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }
        }

        /* Header */
        .header {
            background: white;
            border-radius: var(--border-radius);
            padding: 15px 20px;
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .logo h1 {
            color: var(--primary);
            font-size: 1.5rem;
        }

        @media (max-width: 640px) {
            .logo h1 {
                font-size: 1.2rem;
            }
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn-logout {
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background: var(--primary-dark);
        }

        /* Navigation responsive */
        .nav-admin {
            background: white;
            border-radius: var(--border-radius);
            padding: 12px 20px;
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        @media (max-width: 640px) {
            .nav-admin {
                overflow-x: auto;
                white-space: nowrap;
                flex-wrap: nowrap;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }
        }

        .nav-item {
            padding: 10px 20px;
            color: #333;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .nav-item:hover,
        .nav-item.active {
            background: var(--primary);
            color: white;
        }

        /* Stats Grid responsive */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        @media (max-width: 560px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 18px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .stat-card i {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .stat-card .number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1a1a2e;
        }

        .stat-card .label {
            color: var(--gray);
            font-size: 0.8rem;
        }

        /* Filters Bar responsive */
        .filters-bar {
            background: white;
            border-radius: var(--border-radius);
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        @media (max-width: 640px) {
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
        }

        .search-box {
            display: flex;
            gap: 10px;
            flex: 1;
            max-width: 400px;
        }

        @media (max-width: 640px) {
            .search-box {
                max-width: 100%;
            }
        }

        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 30px;
            font-family: inherit;
            font-size: 0.9rem;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .search-box button {
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 500;
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
            font-size: 0.9rem;
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .filter-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-toggle label {
            cursor: pointer;
            font-size: 0.85rem;
        }

        /* Table container avec scroll horizontal */
        .table-container {
            background: white;
            border-radius: var(--border-radius);
            overflow-x: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        th,
        td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.85rem;
            color: #555;
        }

        td {
            font-size: 0.85rem;
        }

        @media (max-width: 480px) {
            th,
            td {
                padding: 10px 8px;
                font-size: 0.75rem;
            }
        }

        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }

        @media (max-width: 480px) {
            .product-image {
                width: 40px;
                height: 40px;
            }
        }

        .product-image-placeholder {
            width: 50px;
            height: 50px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .btn-icon {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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
            padding: 3px 10px;
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

        /* Version mobile - produits en cartes */
        @media (max-width: 640px) {
            .desktop-table {
                display: none;
            }

            .mobile-products {
                display: flex;
                flex-direction: column;
                gap: 12px;
                padding: 5px;
            }

            .product-card {
                background: #f8f9fa;
                border-radius: 12px;
                padding: 15px;
                border-left: 3px solid var(--primary);
            }

            .product-card-header {
                display: flex;
                gap: 12px;
                margin-bottom: 12px;
            }

            .product-card-image {
                width: 60px;
                height: 60px;
                border-radius: 8px;
                object-fit: cover;
                background: #e9ecef;
            }

            .product-card-info {
                flex: 1;
            }

            .product-card-name {
                font-weight: bold;
                font-size: 1rem;
                margin-bottom: 4px;
            }

            .product-card-price {
                color: var(--primary);
                font-weight: bold;
                font-size: 0.9rem;
            }

            .product-card-desc {
                font-size: 0.75rem;
                color: #666;
                margin: 8px 0;
                line-height: 1.4;
            }

            .product-card-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid #ddd;
            }
        }

        @media (min-width: 641px) {
            .mobile-products {
                display: none;
            }
        }

        /* Pagination responsive */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 8px 14px;
            background: white;
            border-radius: 8px;
            text-decoration: none;
            color: var(--primary);
            font-weight: 500;
            font-size: 0.85rem;
        }

        .pagination .current {
            background: var(--primary);
            color: white;
        }

        /* Alertes */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
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

        /* Modal responsive */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            margin: 50px auto;
            border-radius: 20px;
            width: 90%;
            max-width: 550px;
            animation: modalSlideIn 0.3s ease;
        }

        @media (max-width: 480px) {
            .modal-content {
                margin: 20px auto;
                width: 95%;
            }
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: var(--primary);
            color: white;
            padding: 18px 20px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 1.2rem;
        }

        .modal-header .close {
            font-size: 28px;
            cursor: pointer;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
        }

        .image-preview {
            max-width: 120px;
            margin-top: 10px;
            border-radius: 8px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            color: var(--gray);
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <h1>🎨 Youki and Co - Produits</h1>
            </div>
            <div class="user-info">
                <span>👋 <?= htmlspecialchars($_SESSION['admin_email'] ?? 'Admin') ?></span>
                <a href="admin_dashboard.php?logout=1" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>

        <!-- Navigation -->
        <div class="nav-admin">
            <a href="admin_dashboard.php" class="nav-item"><i class="fas fa-chart-line"></i> Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item"><i class="fas fa-truck"></i> Commandes</a>
            <a href="admin_factures.php" class="nav-item"><i class="fas fa-file-invoice"></i> Factures</a>
            <a href="admin_clients.php" class="nav-item"><i class="fas fa-users"></i> Clients</a>
            <a href="admin_produits.php" class="nav-item active"><i class="fas fa-box"></i> Produits</a>
        </div>

        <!-- Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['warning'])): ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                <?= htmlspecialchars($_SESSION['warning']); unset($_SESSION['warning']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='admin_produits.php'">
                <i class="fas fa-eye"></i>
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

        <!-- Version tableau desktop -->
        <div class="desktop-table table-container">
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
                                <i class="fas fa-box-open" style="font-size: 2rem; opacity: 0.5;"></i>
                                <p>Aucun produit trouvé</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($produits as $p): ?>
                        <tr>
                            <td><strong>#<?= $p['idOrigami'] ?></strong></td>
                            <td>
                                <?php if(!empty($p['photo']) && file_exists($p['photo'])): ?>
                                    <img src="<?= htmlspecialchars($p['photo']) ?>" class="product-image" alt="<?= htmlspecialchars($p['nom']) ?>">
                                <?php else: ?>
                                    <div class="product-image-placeholder"><i class="fas fa-image"></i></div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
                            <td><?= htmlspecialchars(substr($p['description'] ?? '', 0, 60)) ?>...</td>
                            <td><?= number_format($p['prixHorsTaxe'], 2) ?> €</td>
                            <td>
                                <?php if(isset($p['visible']) && $p['visible'] == 0): ?>
                                    <span class="status-badge status-hidden"><i class="fas fa-eye-slash"></i> Masqué</span>
                                <?php else: ?>
                                    <span class="status-badge status-visible"><i class="fas fa-eye"></i> Visible</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <button onclick="editProduit(<?= $p['idOrigami'] ?>)" class="btn-icon btn-edit" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if(isset($p['visible']) && $p['visible'] == 0): ?>
                                    <a href="?action=reactiver&id=<?= $p['idOrigami'] ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>" 
                                       class="btn-icon btn-reactiver" 
                                       onclick="return confirm('Réactiver ce produit ?')">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="?action=masquer&id=<?= $p['idOrigami'] ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>" 
                                       class="btn-icon btn-hide" 
                                       onclick="return confirm('Masquer ce produit ?')">
                                        <i class="fas fa-eye-slash"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="?action=supprimer&id=<?= $p['idOrigami'] ?>" 
                                   class="btn-icon btn-delete" 
                                   onclick="return confirm('⚠️ Supprimer définitivement ce produit ? Cette action est irréversible.')">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Version mobile cartes -->
        <div class="mobile-products">
            <?php if(empty($produits)): ?>
                <div style="text-align: center; padding: 40px; background: white; border-radius: 16px;">
                    <i class="fas fa-box-open" style="font-size: 2rem; opacity: 0.5;"></i>
                    <p>Aucun produit trouvé</p>
                </div>
            <?php else: ?>
                <?php foreach($produits as $p): ?>
                <div class="product-card">
                    <div class="product-card-header">
                        <?php if(!empty($p['photo']) && file_exists($p['photo'])): ?>
                            <img src="<?= htmlspecialchars($p['photo']) ?>" class="product-card-image" alt="<?= htmlspecialchars($p['nom']) ?>">
                        <?php else: ?>
                            <div class="product-card-image" style="display: flex; align-items: center; justify-content: center; background: #e9ecef;">
                                <i class="fas fa-image" style="color: #999;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="product-card-info">
                            <div class="product-card-name">#<?= $p['idOrigami'] ?> - <?= htmlspecialchars($p['nom']) ?></div>
                            <div class="product-card-price"><?= number_format($p['prixHorsTaxe'], 2) ?> €</div>
                            <?php if(isset($p['visible']) && $p['visible'] == 0): ?>
                                <span class="status-badge status-hidden" style="margin-top: 5px; display: inline-block;">
                                    <i class="fas fa-eye-slash"></i> Masqué
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-visible" style="margin-top: 5px; display: inline-block;">
                                    <i class="fas fa-eye"></i> Visible
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="product-card-desc"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 80)) ?>...</div>
                    <div class="product-card-actions">
                        <button onclick="editProduit(<?= $p['idOrigami'] ?>)" class="btn-icon btn-edit">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                        <?php if(isset($p['visible']) && $p['visible'] == 0): ?>
                            <a href="?action=reactiver&id=<?= $p['idOrigami'] ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>" 
                               class="btn-icon btn-reactiver" 
                               onclick="return confirm('Réactiver ce produit ?')">
                                <i class="fas fa-eye"></i> Réactiver
                            </a>
                        <?php else: ?>
                            <a href="?action=masquer&id=<?= $p['idOrigami'] ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>" 
                               class="btn-icon btn-hide" 
                               onclick="return confirm('Masquer ce produit ?')">
                                <i class="fas fa-eye-slash"></i> Masquer
                            </a>
                        <?php endif; ?>
                        <a href="?action=supprimer&id=<?= $p['idOrigami'] ?>" 
                           class="btn-icon btn-delete" 
                           onclick="return confirm('⚠️ Supprimer définitivement ce produit ?')">
                            <i class="fas fa-trash-alt"></i> Supprimer
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page_courante > 1): ?>
                <a href="?page=<?= $page_courante - 1 ?>&search=<?= urlencode($recherche) ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>">
                    <i class="fas fa-chevron-left"></i> Précédent
                </a>
            <?php endif; ?>
            
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <?php if($i == $page_courante): ?>
                    <span class="current"><?= $i ?></span>
                <?php elseif($i <= 5 || $i > $total_pages - 2 || ($i >= $page_courante - 2 && $i <= $page_courante + 2)): ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($recherche) ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if($page_courante < $total_pages): ?>
                <a href="?page=<?= $page_courante + 1 ?>&search=<?= urlencode($recherche) ?><?= $afficher_masques ? '&afficher_masques=1' : '' ?>">
                    Suivant <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>&copy; <?= date('Y') ?> Youki and Co - Créations artisanales japonaises</p>
        </div>
    </div>

    <!-- MODAL AJOUT -->
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
                        <input type="text" name="nom" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Prix HT (€) *</label>
                        <input type="number" name="prix" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Image</label>
                        <input type="file" name="photo" accept="image/*" onchange="previewImage(this, 'previewAjout')">
                        <img id="previewAjout" class="image-preview" style="display: none;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('modalAjout')">Annuler</button>
                    <button type="submit" class="btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL MODIFICATION -->
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
                        <label>Changer d'image</label>
                        <input type="file" name="photo" accept="image/*" onchange="previewImage(this, 'previewEdit')">
                        <img id="previewEdit" class="image-preview" style="display: none;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('modalEdit')">Annuler</button>
                    <button type="submit" class="btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Données des produits pour l'édition
        const produits = <?php 
            $produitsData = [];
            foreach($produits as $p) {
                $produitsData[$p['idOrigami']] = [
                    'nom' => $p['nom'],
                    'description' => $p['description'] ?? '',
                    'prix' => $p['prixHorsTaxe'],
                    'photo' => $p['photo']
                ];
            }
            echo json_encode($produitsData);
        ?>;

        function openAjoutModal() {
            document.getElementById('modalAjout').style.display = 'block';
            document.getElementById('formAjout').reset();
            document.getElementById('previewAjout').style.display = 'none';
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
            if (produit.photo) {
                container.innerHTML = `<img src="${produit.photo}" class="image-preview">`;
            } else {
                container.innerHTML = '<p style="color:#999;">Aucune image</p>';
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
            }
        }

        // Fermer modal en cliquant en dehors
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
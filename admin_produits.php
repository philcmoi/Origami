<?php
// Inclure la protection au tout début
require_once 'admin_protection.php';

// Configuration de la base de données
require_once 'config.php';

// Configuration de l'upload d'images
$upload_dir = 'uploads/produits/';
$upload_max_size = 5 * 1024 * 1024; // 5 Mo
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// Créer le dossier d'upload s'il n'existe pas
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Fonction pour gérer l'upload d'image
function uploadImage($file, $upload_dir, $allowed_types, $allowed_extensions, $max_size) {
    $result = [
        'success' => false,
        'message' => '',
        'filename' => ''
    ];
    
    // Vérifier s'il y a une erreur d'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par PHP',
            UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le formulaire',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été téléchargé',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque',
            UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté le téléchargement'
        ];
        
        $result['message'] = isset($upload_errors[$file['error']]) 
            ? $upload_errors[$file['error']] 
            : 'Erreur inconnue lors de l\'upload';
        return $result;
    }
    
    // Vérifier la taille du fichier
    if ($file['size'] > $max_size) {
        $result['message'] = 'Le fichier est trop volumineux (max ' . ($max_size / 1024 / 1024) . ' Mo)';
        return $result;
    }
    
    // Vérifier le type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        $result['message'] = 'Type de fichier non autorisé. Types acceptés: JPG, PNG, GIF, WEBP';
        return $result;
    }
    
    // Vérifier l'extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_extensions)) {
        $result['message'] = 'Extension de fichier non autorisée';
        return $result;
    }
    
    // Générer un nom de fichier unique
    $new_filename = uniqid('produit_', true) . '.' . $extension;
    $upload_path = $upload_dir . $new_filename;
    
    // Déplacer le fichier uploadé
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        $result['success'] = true;
        $result['message'] = 'Image uploadée avec succès';
        $result['filename'] = $upload_path; // Chemin complet pour la BDD
    } else {
        $result['message'] = 'Erreur lors du déplacement du fichier uploadé';
    }
    
    return $result;
}

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
                $photo = '';
                
                // Gérer l'upload d'image
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload_result = uploadImage($_FILES['photo'], $upload_dir, $allowed_types, $allowed_extensions, $upload_max_size);
                    
                    if ($upload_result['success']) {
                        $photo = $upload_result['filename'];
                    } else {
                        $error = "Erreur upload image: " . $upload_result['message'];
                        break;
                    }
                } else {
                    // Image par défaut si aucune n'est uploadée
                    $photo = 'img/placeholder.jpg';
                }
                
                if ($nom && $description && $prixHorsTaxe > 0) {
                    $stmt = $pdo->prepare("INSERT INTO Origami (nom, description, photo, prixHorsTaxe) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nom, $description, $photo, $prixHorsTaxe]);
                    $success = "Produit ajouté avec succès!";
                } else {
                    $error = "Tous les champs obligatoires doivent être remplis";
                }
                break;
                
            case 'modifier':
                $idOrigami = $_POST['idOrigami'] ?? null;
                $nom = $_POST['nom'] ?? '';
                $description = $_POST['description'] ?? '';
                $prixHorsTaxe = $_POST['prixHorsTaxe'] ?? 0;
                
                if ($idOrigami && $nom && $description && $prixHorsTaxe > 0) {
                    // Récupérer l'ancienne photo
                    $stmt = $pdo->prepare("SELECT photo FROM Origami WHERE idOrigami = ?");
                    $stmt->execute([$idOrigami]);
                    $ancien_produit = $stmt->fetch(PDO::FETCH_ASSOC);
                    $photo = $ancien_produit['photo'];
                    
                    // Gérer le nouvel upload si fourni
                    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $upload_result = uploadImage($_FILES['photo'], $upload_dir, $allowed_types, $allowed_extensions, $upload_max_size);
                        
                        if ($upload_result['success']) {
                            // Supprimer l'ancienne image si ce n'est pas l'image par défaut
                            if ($photo && $photo != 'img/placeholder.jpg' && file_exists($photo)) {
                                unlink($photo);
                            }
                            $photo = $upload_result['filename'];
                        } else {
                            $error = "Erreur upload image: " . $upload_result['message'];
                            break;
                        }
                    }
                    
                    $stmt = $pdo->prepare("UPDATE Origami SET nom = ?, description = ?, photo = ?, prixHorsTaxe = ? WHERE idOrigami = ?");
                    $stmt->execute([$nom, $description, $photo, $prixHorsTaxe, $idOrigami]);
                    $success = "Produit modifié avec succès!";
                } else {
                    $error = "Tous les champs obligatoires doivent être remplis";
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
                        // Récupérer le chemin de la photo avant suppression
                        $stmt = $pdo->prepare("SELECT photo FROM Origami WHERE idOrigami = ?");
                        $stmt->execute([$idOrigami]);
                        $produit = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Supprimer le produit de la BDD
                        $stmt = $pdo->prepare("DELETE FROM Origami WHERE idOrigami = ?");
                        $stmt->execute([$idOrigami]);
                        
                        // Supprimer l'image associée si ce n'est pas l'image par défaut
                        if ($produit && $produit['photo'] != 'img/placeholder.jpg' && file_exists($produit['photo'])) {
                            unlink($produit['photo']);
                        }
                        
                        $success = "Produit supprimé avec succès!";
                    } else {
                        $error = "Impossible de supprimer ce produit : il est associé à des commandes";
                    }
                }
                break;
        }
        
        // Recharger la page pour voir les modifications
        header('Location: admin_produits.php');
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
        /* Styles spécifiques à la gestion des produits */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .product-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .product-image {
            height: 200px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-image .image-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .product-info {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .product-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.4;
            flex: 1;
        }
        
        .product-price {
            font-size: 20px;
            font-weight: bold;
            color: #d40000;
            margin-bottom: 15px;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }
        
        .btn-edit, .btn-delete {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            flex: 1;
            text-align: center;
        }
        
        .btn-edit {
            background: #ffc107;
            color: black;
        }
        
        .btn-edit:hover {
            background: #e0a800;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: #d40000;
            box-shadow: 0 0 0 3px rgba(212, 0, 0, 0.1);
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .form-group input[type="file"] {
            padding: 8px;
            background: #f8f9fa;
            border: 1px dashed #d40000;
        }
        
        .form-group input[type="file"]:hover {
            background: #f0f0f0;
        }
        
        .image-preview {
            margin-top: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            text-align: center;
            border: 2px dashed #ddd;
        }
        
        .image-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .image-preview p {
            margin: 10px 0 0;
            color: #666;
            font-size: 12px;
        }
        
        .image-info {
            display: inline-block;
            padding: 5px 10px;
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 4px;
            color: #004085;
            font-size: 13px;
            margin-top: 5px;
        }
        
        .btn-submit {
            background: #d40000;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }
        
        .btn-submit:hover {
            background: #b30000;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            margin-left: 10px;
            transition: background 0.3s;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Reprendre les styles de base */
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
            transition: background 0.3s;
        }
        
        .btn-logout:hover {
            background: #b30000;
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
            transition: all 0.3s; 
        }
        
        .nav-item:hover, .nav-item.active { 
            background: #d40000; 
            color: white; 
        }
        
        .main-content { 
            flex: 1; 
            padding: 30px; 
        }
        
        .upload-info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            color: #004085;
            font-size: 14px;
        }
        
        .upload-info i {
            margin-right: 8px;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: normal;
            background: #e0e0e0;
            color: #333;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>Youki and Co - Gestion des Produits</h1>
        </div>
        <div class="admin-info">
            <span>Connecté en tant que: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="admin_dashboard.php?logout=1" class="btn-logout">Déconnexion</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="nav-item">Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">Gestion des Commandes</a>
            <a href="admin_factures.php" class="nav-item">Gestion des Factures</a>
            <a href="admin_clients.php" class="nav-item">Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item active">Gestion des Produits</a>
        </div>
        
        <div class="main-content">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <!-- Information sur l'upload -->
            <div class="upload-info">
                <i>📸</i> 
                Formats acceptés : JPG, PNG, GIF, WEBP - Taille max : 5 Mo
                <?php if ($produitEdit && $produitEdit['photo'] && $produitEdit['photo'] != 'img/placeholder.jpg'): ?>
                    <br><i>📁</i> Image actuelle : <code><?= basename($produitEdit['photo']) ?></code>
                <?php endif; ?>
            </div>
            
            <div class="form-container">
                <h2><?= $produitEdit ? 'Modifier le Produit' : 'Ajouter un Nouveau Produit' ?></h2>
                <form method="POST" enctype="multipart/form-data">
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
                        <label for="photo">Image du produit</label>
                        <input type="file" id="photo" name="photo" 
                               accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                        <small style="color: #666; display: block; margin-top: 5px;">
                            Laissez vide pour conserver l'image actuelle (en mode modification)
                        </small>
                    </div>
                    
                    <!-- Aperçu de l'image actuelle -->
                    <?php if ($produitEdit && $produitEdit['photo']): ?>
                    <div class="image-preview">
                        <p>Image actuelle :</p>
                        <img src="<?= htmlspecialchars($produitEdit['photo']) ?>" 
                             alt="Aperçu" 
                             onerror="this.src='img/placeholder.jpg'">
                        <p>
                            <span class="badge badge-warning">
                                <?= basename($produitEdit['photo']) ?>
                            </span>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 20px;">
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
                <h2 style="margin-bottom: 20px;">Catalogue des Produits (<?= count($produits) ?>)</h2>
                
                <div class="product-grid">
                    <?php foreach ($produits as $produit): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="<?= htmlspecialchars($produit['photo']) ?>" 
                                 alt="<?= htmlspecialchars($produit['nom']) ?>" 
                                 onerror="this.src='img/placeholder.jpg'">
                            <div class="image-badge">
                                <span>📸</span>
                                <?= basename($produit['photo']) ?>
                            </div>
                        </div>
                        
                        <div class="product-info">
                            <div class="product-name"><?= htmlspecialchars($produit['nom']) ?></div>
                            <div class="product-description"><?= htmlspecialchars($produit['description']) ?></div>
                            <div class="product-price"><?= number_format($produit['prixHorsTaxe'], 2, ',', ' ') ?>€</div>
                            
                            <div class="product-actions">
                                <a href="admin_produits.php?edit=<?= $produit['idOrigami'] ?>" class="btn-edit">✏️ Modifier</a>
                                
                                <form method="POST" style="display: inline; flex: 1;">
                                    <input type="hidden" name="idOrigami" value="<?= $produit['idOrigami'] ?>">
                                    <button type="submit" name="action" value="supprimer" class="btn-delete" 
                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.')">
                                        🗑️ Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($produits)): ?>
                    <div style="text-align: center; padding: 60px; background: white; border-radius: 10px;">
                        <div style="font-size: 48px; margin-bottom: 20px;">📦</div>
                        <p style="color: #666; font-size: 18px; margin-bottom: 10px;">Aucun produit trouvé dans le catalogue.</p>
                        <p style="color: #999;">Utilisez le formulaire ci-dessus pour ajouter votre premier produit.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
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
        
        // Aperçu de l'image avant upload
        document.getElementById('photo')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Vérifier la taille
                if (file.size > 5 * 1024 * 1024) {
                    alert('Le fichier est trop volumineux (max 5 Mo)');
                    this.value = '';
                    return;
                }
                
                // Vérifier le type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WEBP');
                    this.value = '';
                    return;
                }
                
                // Créer un aperçu
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Chercher ou créer le conteneur d'aperçu
                    let previewDiv = document.querySelector('.image-preview.upload-preview');
                    if (!previewDiv) {
                        previewDiv = document.createElement('div');
                        previewDiv.className = 'image-preview upload-preview';
                        document.querySelector('.form-group:last-of-type').appendChild(previewDiv);
                    }
                    
                    previewDiv.innerHTML = `
                        <p>Nouvelle image :</p>
                        <img src="${e.target.result}" alt="Aperçu">
                        <p>
                            <span class="badge">${file.name}</span>
                            <span class="badge">${(file.size / 1024).toFixed(1)} Ko</span>
                        </p>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
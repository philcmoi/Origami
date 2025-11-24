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
                $photo = $_POST['photo'] ?? '';

                if ($idOrigami && $nom && $description && $prixHorsTaxe > 0) {
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
                        $stmt = $pdo->prepare("DELETE FROM Origami WHERE idOrigami = ?");
                        $stmt->execute([$idOrigami]);
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
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
            }
        }

        @media (max-width: 480px) {
            .product-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }

        .product-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
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
        }

        @media (max-width: 480px) {
            .product-image {
                height: 180px;
            }
        }

        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .product-info {
            padding: 20px;
        }

        @media (max-width: 480px) {
            .product-info {
                padding: 15px;
            }
        }

        .product-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        @media (max-width: 480px) {
            .product-name {
                font-size: 16px;
            }
        }

        .product-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 20px;
            font-weight: bold;
            color: #d40000;
            margin-bottom: 15px;
        }

        @media (max-width: 480px) {
            .product-price {
                font-size: 18px;
            }
        }

        .product-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        @media (max-width: 480px) {
            .product-actions {
                flex-direction: column;
            }
        }

        .btn-edit, .btn-delete {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            text-align: center;
            flex: 1;
        }

        @media (max-width: 480px) {
            .btn-edit, .btn-delete {
                padding: 10px 15px;
                font-size: 14px;
            }
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

        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .form-container {
                padding: 15px;
            }
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

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        @media (max-width: 480px) {
            .form-group input, .form-group textarea {
                padding: 10px;
                font-size: 16px; /* Empêche le zoom sur iOS */
            }
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        @media (max-width: 480px) {
            .btn-submit, .btn-cancel {
                padding: 12px 20px;
                font-size: 14px;
                flex: 1;
            }

            .form-actions {
                flex-direction: column;
            }
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

        /* Reprendre les styles de base avec améliorations responsive */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; color: #333; }

        .header {
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }
        }

        .logo h1 {
            color: #d40000;
            font-size: 24px;
        }

        @media (max-width: 480px) {
            .logo h1 {
                font-size: 20px;
            }
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }

        @media (max-width: 480px) {
            .admin-info {
                flex-direction: column;
                gap: 10px;
            }
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

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
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
                order: 2;
                padding: 15px;
                display: none;
            }

            .sidebar.active {
                display: block;
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
            padding: 30px;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
                order: 1;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
        }

        .section h2 {
            color: #d40000;
            margin-bottom: 20px;
            font-size: 24px;
        }

        @media (max-width: 480px) {
            .section h2 {
                font-size: 20px;
                text-align: center;
            }
        }

        /* Menu mobile */
        .mobile-menu-toggle {
            display: none;
            background: #d40000;
            color: white;
            border: none;
            padding: 12px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 15px;
            width: 100%;
            text-align: center;
            transition: background 0.3s;
        }

        .mobile-menu-toggle:hover {
            background: #b30000;
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
        }

        /* Message vide */
        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            grid-column: 1 / -1;
        }

        @media (max-width: 480px) {
            .empty-state {
                padding: 30px 20px;
            }
        }

        .empty-state p {
            color: #666;
            font-size: 18px;
            margin-bottom: 10px;
        }

        @media (max-width: 480px) {
            .empty-state p {
                font-size: 16px;
            }
        }

        .empty-state p:last-child {
            color: #999;
            font-size: 14px;
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
        <button class="mobile-menu-toggle" onclick="toggleSidebar()">☰ Menu Administration</button>

        <div class="sidebar" id="sidebar">
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
                            <div class="product-price"><?= number_format($produit['prixHorsTaxe'], 2, ',', ' ') ?>€</div>

                            <div class="product-actions">
                                <a href="admin_produits.php?edit=<?= $produit['idOrigami'] ?>" class="btn-edit">Modifier</a>

                                <form method="POST" style="display: inline; width: 100%;">
                                    <input type="hidden" name="idOrigami" value="<?= $produit['idOrigami'] ?>">
                                    <button type="submit" name="action" value="supprimer" class="btn-delete"
                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')">
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

        // Toggle sidebar on mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.mobile-menu-toggle');

            if (window.innerWidth <= 768 && sidebar.classList.contains('active') &&
                !sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>

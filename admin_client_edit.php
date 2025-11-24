<?php
session_start();

require_once 'config.php';

// Vérifier la connexion administrateur
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Vérifier que l'ID client est présent
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: admin_clients.php');
    exit;
}

$client_id = intval($_GET['id']);

$message_success = '';
$message_erreur = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les informations du client
    $stmt_client = $pdo->prepare("
        SELECT * FROM Client
        WHERE idClient = ?
    ");
    $stmt_client->execute([$client_id]);
    $client = $stmt_client->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        die("Client non trouvé");
    }

    // Traitement du formulaire de modification
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $type = $_POST['type'] ?? 'temporaire';
        $email_confirme = isset($_POST['email_confirme']) ? 1 : 0;

        // Validation des données
        if (empty($nom) || empty($prenom) || empty($email)) {
            $message_erreur = "Les champs nom, prénom et email sont obligatoires.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message_erreur = "L'adresse email n'est pas valide.";
        } else {
            try {
                // Vérifier si l'email existe déjà pour un autre client
                $stmt_check_email = $pdo->prepare("
                    SELECT idClient FROM Client
                    WHERE email = ? AND idClient != ?
                ");
                $stmt_check_email->execute([$email, $client_id]);

                if ($stmt_check_email->fetch()) {
                    $message_erreur = "Cette adresse email est déjà utilisée par un autre client.";
                } else {
                    // Mettre à jour le client
                    $stmt_update = $pdo->prepare("
                        UPDATE Client
                        SET nom = ?, prenom = ?, email = ?, telephone = ?,
                            type = ?, email_confirme = ?
                        WHERE idClient = ?
                    ");

                    $stmt_update->execute([
                        $nom,
                        $prenom,
                        $email,
                        $telephone,
                        $type,
                        $email_confirme,
                        $client_id
                    ]);

                    // Recharger les données du client
                    $stmt_client->execute([$client_id]);
                    $client = $stmt_client->fetch(PDO::FETCH_ASSOC);

                    $message_success = "Les informations du client ont été mises à jour avec succès.";
                }
            } catch (PDOException $e) {
                $message_erreur = "Erreur lors de la mise à jour: " . $e->getMessage();
            }
        }
    }

} catch (PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Client #<?= $client_id ?> - Youki and Co</title>
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
        }

        .header {
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        @media (min-width: 768px) {
            .header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
            }
        }

        .logo h1 {
            color: #d40000;
            font-size: 20px;
            text-align: center;
        }

        @media (min-width: 768px) {
            .logo h1 {
                font-size: 24px;
                text-align: left;
            }
        }

        .admin-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-align: center;
        }

        @media (min-width: 768px) {
            .admin-info {
                flex-direction: row;
                align-items: center;
                gap: 15px;
                text-align: left;
            }
        }

        .btn-logout {
            background: #d40000;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            display: inline-block;
        }

        .container {
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 80px);
        }

        @media (min-width: 992px) {
            .container {
                flex-direction: row;
            }
        }

        .sidebar {
            background: white;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            order: 2;
        }

        @media (min-width: 992px) {
            .sidebar {
                width: 250px;
                order: 1;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
                padding: 20px;
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
            text-align: center;
        }

        @media (min-width: 992px) {
            .nav-item {
                text-align: left;
            }
        }

        .nav-item:hover, .nav-item.active {
            background: #d40000;
            color: white;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            order: 1;
        }

        @media (min-width: 992px) {
            .main-content {
                padding: 30px;
                order: 2;
            }
        }

        .page-header {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 25px;
        }

        @media (min-width: 768px) {
            .page-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 30px;
            }
        }

        .page-title h2 {
            color: #333;
            font-size: 24px;
            margin-bottom: 8px;
        }

        @media (min-width: 768px) {
            .page-title h2 {
                font-size: 28px;
            }
        }

        .breadcrumb {
            color: #666;
            font-size: 13px;
            line-height: 1.4;
        }

        @media (min-width: 768px) {
            .breadcrumb {
                font-size: 14px;
            }
        }

        .breadcrumb a {
            color: #d40000;
            text-decoration: none;
        }

        .section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        @media (min-width: 768px) {
            .section {
                padding: 25px;
                border-radius: 10px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
        }

        .section h3 {
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            font-size: 18px;
        }

        @media (min-width: 768px) {
            .section h3 {
                margin-bottom: 20px;
                font-size: 20px;
            }
        }

        .form-group {
            margin-bottom: 15px;
        }

        @media (min-width: 768px) {
            .form-group {
                margin-bottom: 20px;
            }
        }

        .form-label {
            display: block;
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: #d40000;
            outline: none;
        }

        .form-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: white;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
        }

        .form-check-label {
            font-size: 14px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
            }
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: background-color 0.3s;
            width: 100%;
            margin-bottom: 8px;
        }

        @media (min-width: 480px) {
            .btn {
                width: auto;
                margin-bottom: 0;
            }
        }

        .btn-primary {
            background: #d40000;
            color: white;
        }

        .btn-primary:hover {
            background: #b30000;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        .form-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        @media (min-width: 480px) {
            .form-actions {
                flex-direction: row;
            }
        }

        .message {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .client-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: center;
        }

        @media (min-width: 768px) {
            .client-info {
                padding: 15px;
                font-size: 14px;
                text-align: left;
            }
        }

        .client-info strong {
            color: #d40000;
        }

        .actions-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        @media (min-width: 480px) {
            .actions-grid {
                flex-direction: row;
            }
        }

        .mobile-menu-toggle {
            display: block;
            background: #d40000;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 15px;
            width: 100%;
            font-size: 14px;
        }

        @media (min-width: 992px) {
            .mobile-menu-toggle {
                display: none;
            }
        }

        .sidebar-mobile-hidden {
            display: none;
        }

        @media (min-width: 992px) {
            .sidebar-mobile-hidden {
                display: block;
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
            <span>Connecté en tant que: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="admin_dashboard.php?logout=1" class="btn-logout">Déconnexion</a>
        </div>
    </div>

    <div class="container">
        <button class="mobile-menu-toggle" id="mobileMenuToggle">☰ Menu Administration</button>

        <div class="sidebar" id="sidebar">
            <a href="admin_dashboard.php" class="nav-item">Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">Gestion des Commandes</a>
            <a href="admin_clients.php" class="nav-item active">Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item">Gestion des Produits</a>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <h2>Modifier le Client</h2>
                    <div class="breadcrumb">
                        <a href="admin_dashboard.php">Tableau de bord</a> &gt;
                        <a href="admin_clients.php">Clients</a> &gt;
                        <a href="admin_client_detail.php?id=<?= $client_id ?>">Détails client #<?= $client_id ?></a> &gt;
                        Modifier
                    </div>
                </div>
                <div>
                    <a href="admin_client_detail.php?id=<?= $client_id ?>" class="btn btn-secondary">← Retour aux détails</a>
                </div>
            </div>

            <?php if (!empty($message_success)): ?>
                <div class="message success"><?= $message_success ?></div>
            <?php endif; ?>

            <?php if (!empty($message_erreur)): ?>
                <div class="message error"><?= $message_erreur ?></div>
            <?php endif; ?>

            <div class="client-info">
                <strong>Client #<?= $client_id ?></strong> -
                Inscrit le <?= date('d/m/Y à H:i', strtotime($client['date_creation'])) ?>
            </div>

            <div class="section">
                <h3>Informations Personnelles</h3>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="nom">Nom *</label>
                            <input type="text" id="nom" name="nom" class="form-control"
                                   value="<?= htmlspecialchars($client['nom'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="prenom">Prénom *</label>
                            <input type="text" id="prenom" name="prenom" class="form-control"
                                   value="<?= htmlspecialchars($client['prenom'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Email *</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($client['email']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="telephone">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" class="form-control"
                                   value="<?= htmlspecialchars($client['telephone'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="type">Type de compte</label>
                            <select id="type" name="type" class="form-select">
                                <option value="temporaire" <?= ($client['type'] ?? 'temporaire') == 'temporaire' ? 'selected' : '' ?>>Temporaire</option>
                                <option value="permanent" <?= ($client['type'] ?? 'temporaire') == 'permanent' ? 'selected' : '' ?>>Permanent</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" id="email_confirme" name="email_confirme"
                                       class="form-check-input" value="1"
                                       <?= ($client['email_confirme'] == 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="email_confirme">
                                    Email confirmé
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                        <a href="admin_client_detail.php?id=<?= $client_id ?>" class="btn btn-secondary">Annuler</a>
                    </div>
                </form>
            </div>

            <div class="section">
                <h3>Actions Administratives</h3>
                <div class="actions-grid">
                    <a href="admin_client_detail.php?id=<?= $client_id ?>" class="btn btn-secondary">
                        Voir les détails complets
                    </a>
                    <a href="admin_clients.php" class="btn btn-secondary">
                        Retour à la liste des clients
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle menu mobile
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar.style.display === 'block') {
                sidebar.style.display = 'none';
            } else {
                sidebar.style.display = 'block';
            }
        });

        // Masquer le sidebar sur mobile au chargement
        window.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth < 992) {
                document.getElementById('sidebar').style.display = 'none';
            }
        });

        // Gérer le redimensionnement de la fenêtre
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth >= 992) {
                sidebar.style.display = 'block';
            } else {
                sidebar.style.display = 'none';
            }
        });
    </script>
</body>
</html>

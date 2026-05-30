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

    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $type = $_POST['type'] ?? 'temporaire';
        $email_confirme = isset($_POST['email_confirme']) ? 1 : 0;

        if (empty($nom) || empty($prenom) || empty($email)) {
            $message_erreur = "Les champs nom, prénom et email sont obligatoires.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message_erreur = "L'adresse email n'est pas valide.";
        } else {
            try {
                $stmt_check_email = $pdo->prepare("
                    SELECT idClient FROM Client 
                    WHERE email = ? AND idClient != ?
                ");
                $stmt_check_email->execute([$email, $client_id]);
                
                if ($stmt_check_email->fetch()) {
                    $message_erreur = "Cette adresse email est déjà utilisée.";
                } else {
                    $stmt_update = $pdo->prepare("
                        UPDATE Client 
                        SET nom = ?, prenom = ?, email = ?, telephone = ?, 
                            type = ?, email_confirme = ?
                        WHERE idClient = ?
                    ");
                    
                    $stmt_update->execute([
                        $nom, $prenom, $email, $telephone, 
                        $type, $email_confirme, $client_id
                    ]);
                    
                    $stmt_client->execute([$client_id]);
                    $client = $stmt_client->fetch(PDO::FETCH_ASSOC);
                    $message_success = "Client modifié avec succès.";
                }
            } catch (PDOException $e) {
                $message_erreur = "Erreur: " . $e->getMessage();
            }
        }
    }

} catch (PDOException $e) {
    die("Erreur base de données: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Modifier Client #<?= $client_id ?> - Youki and Co</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        /* Header responsive */
        .header {
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }
        
        .logo h1 {
            color: #d40000;
            font-size: 1.5rem;
        }
        
        @media (max-width: 640px) {
            .logo h1 { font-size: 1.2rem; }
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn-logout {
            background: #d40000;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }
        
        /* Layout responsive */
        .container {
            display: flex;
            flex-wrap: wrap;
            min-height: calc(100vh - 80px);
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
                box-shadow: none;
                border-bottom: 1px solid #eee;
                padding: 10px 20px;
                overflow-x: auto;
                white-space: nowrap;
            }
            .sidebar .nav-item {
                display: inline-block;
                margin-right: 10px;
                margin-bottom: 0;
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
            padding: 20px;
            min-width: 0;
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 15px; }
        }
        
        /* Page header */
        .page-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .page-title h2 {
            font-size: 1.5rem;
            color: #333;
        }
        
        @media (max-width: 480px) {
            .page-title h2 { font-size: 1.2rem; }
        }
        
        .breadcrumb {
            font-size: 0.7rem;
            color: #666;
            word-break: break-word;
        }
        
        .breadcrumb a {
            color: #d40000;
            text-decoration: none;
        }
        
        /* Section */
        .section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .section h3 {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            font-size: 1.2rem;
        }
        
        /* Form responsive */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        
        @media (max-width: 480px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
            font-size: 0.8rem;
        }
        
        .form-control, .form-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
            font-family: inherit;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #d40000;
            outline: none;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        /* Messages */
        .message {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.85rem;
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
        
        /* Buttons */
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: background 0.3s;
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
        
        .client-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }
        
        .client-info strong {
            color: #d40000;
        }
        
        /* Actions section */
        .actions-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 0.7rem;
            color: #666;
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
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="nav-item">Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">Commandes</a>
            <a href="admin_factures.php" class="nav-item">Factures</a>
            <a href="admin_clients.php" class="nav-item active">Clients</a>
            <a href="admin_produits.php" class="nav-item">Produits</a>
        </div>
        
        <div class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <h2>Modifier le Client</h2>
                    <div class="breadcrumb">
                        <a href="admin_dashboard.php">Dashboard</a> &gt; 
                        <a href="admin_clients.php">Clients</a> &gt; 
                        <a href="admin_client_detail.php?id=<?= $client_id ?>">Client #<?= $client_id ?></a> &gt; 
                        Modifier
                    </div>
                </div>
                <div>
                    <a href="admin_client_detail.php?id=<?= $client_id ?>" class="btn btn-secondary">← Retour</a>
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
                                <label class="form-check-label" for="email_confirme">Email confirmé</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
                        <a href="admin_client_detail.php?id=<?= $client_id ?>" class="btn btn-secondary">Annuler</a>
                    </div>
                </form>
            </div>
            
            <div class="section">
                <h3>Actions</h3>
                <div class="actions-group">
                    <a href="admin_client_detail.php?id=<?= $client_id ?>" class="btn btn-secondary">
                        👁️ Voir les détails
                    </a>
                    <a href="admin_clients.php" class="btn btn-secondary">
                        📋 Retour à la liste
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; <?= date('Y') ?> Youki and Co</p>
    </div>
</body>
</html>
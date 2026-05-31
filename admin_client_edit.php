<?php
session_start();

require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

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

    $stmt_client = $pdo->prepare("SELECT * FROM Client WHERE idClient = ?");
    $stmt_client->execute([$client_id]);
    $client = $stmt_client->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        die("Client non trouvé");
    }

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
                $stmt_check_email = $pdo->prepare("SELECT idClient FROM Client WHERE email = ? AND idClient != ?");
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
                    
                    $stmt_update->execute([$nom, $prenom, $email, $telephone, $type, $email_confirme, $client_id]);
                    
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
    die("Erreur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Modifier Client #<?= $client_id ?> - Youki and Co</title>
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
            --danger: #e74c3c;
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
        
        .message {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }
        .message.success { background: #d4edda; color: #155724; border-left: 3px solid var(--success); }
        .message.error { background: #fef3f2; color: var(--danger); border-left: 3px solid var(--danger); }
        
        .client-info-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 16px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .section {
            background: white;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
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
        
        .section-body { padding: 20px; }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group { margin-bottom: 0; }
        .form-label {
            display: block;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--gray-600);
            margin-bottom: 6px;
        }
        .form-label .required { color: var(--danger); }
        
        .form-control, .form-select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--gray-300);
            border-radius: 10px;
            font-size: 0.85rem;
            font-family: inherit;
            transition: all 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(192,57,43,0.1);
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
            accent-color: var(--primary);
        }
        
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-200);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary:hover { background: var(--primary-dark); }
        
        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 10px 24px;
            text-decoration: none;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-secondary:hover { background: var(--gray-200); }
        
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
                <a href="admin_commandes.php" class="nav-item"><i class="fas fa-shopping-cart"></i> Commandes</a>
                <a href="admin_factures.php" class="nav-item"><i class="fas fa-file-invoice"></i> Factures</a>
                <a href="admin_clients.php" class="nav-item active"><i class="fas fa-users"></i> Clients</a>
                <a href="admin_produits.php" class="nav-item"><i class="fas fa-box"></i> Produits</a>
            </nav>
        </aside>
        
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <main class="main-content">
            <div class="top-bar">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <div class="page-title">
                    <h1>Modifier le client</h1>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Dashboard</a> &gt;
                        <a href="admin_clients.php">Clients</a> &gt;
                        <a href="admin_client_detail.php?id=<?= $client_id ?>">Client #<?= $client_id ?></a> &gt;
                        Modifier
                    </div>
                </div>
                <div class="user-info">
                    <span class="user-email"><?= htmlspecialchars($_SESSION['admin_email']) ?></span>
                    <a href="dashboard.php?logout=1" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                </div>
            </div>
            
            <div class="content-wrapper">
                <a href="admin_client_detail.php?id=<?= $client_id ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Retour au détail</a>
                
                <?php if (!empty($message_success)): ?>
                    <div class="message success"><i class="fas fa-check-circle"></i> <?= $message_success ?></div>
                <?php endif; ?>
                
                <?php if (!empty($message_erreur)): ?>
                    <div class="message error"><i class="fas fa-exclamation-triangle"></i> <?= $message_erreur ?></div>
                <?php endif; ?>
                
                <div class="client-info-card">
                    <span><i class="fas fa-id-card"></i> Client #<?= $client_id ?></span>
                    <span><i class="fas fa-calendar-alt"></i> Inscrit le <?= date('d/m/Y à H:i', strtotime($client['date_creation'])) ?></span>
                </div>
                
                <div class="section">
                    <div class="section-header">
                        <h2><i class="fas fa-user"></i> Informations personnelles</h2>
                    </div>
                    <div class="section-body">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="nom">Nom <span class="required">*</span></label>
                                    <input type="text" id="nom" name="nom" class="form-control" value="<?= htmlspecialchars($client['nom'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="prenom">Prénom <span class="required">*</span></label>
                                    <input type="text" id="prenom" name="prenom" class="form-control" value="<?= htmlspecialchars($client['prenom'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="email">Email <span class="required">*</span></label>
                                    <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($client['email']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="telephone">Téléphone</label>
                                    <input type="tel" id="telephone" name="telephone" class="form-control" value="<?= htmlspecialchars($client['telephone'] ?? '') ?>">
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
                                        <input type="checkbox" id="email_confirme" name="email_confirme" class="form-check-input" value="1" <?= ($client['email_confirme'] == 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="email_confirme">Email confirmé</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                                <a href="admin_client_detail.php?id=<?= $client_id ?>" class="btn-secondary"><i class="fas fa-times"></i> Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="footer">
                <p>&copy; <?= date('Y') ?> Youki and Co - Créations artisanales japonaises</p>
            </div>
        </main>
    </div>
    
    <script>
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
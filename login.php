<?php
session_start();

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Connexion à la base de données
$host = 'localhost';
$dbname = 'origami';
$username = 'root';
$password = '';

$pdo = null;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur de connexion à la base de données: " . $e->getMessage());
    $error = "Erreur de connexion à la base de données. Veuillez réessayer.";
}

// Traitement du formulaire de connexion
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $motDePasse = $_POST['password'] ?? '';
    
    if (!empty($email) && !empty($motDePasse)) {
        if ($pdo) {
            try {
                // Vérifier si la table Administrateur existe
                $tableExists = $pdo->query("SHOW TABLES LIKE 'Administrateur'")->rowCount() > 0;
                
                if (!$tableExists) {
                    $error = "Table Administrateur non trouvée. Veuillez exécuter le script d'installation.";
                } else {
                    // Rechercher l'administrateur dans la base de données
                    $stmt = $pdo->prepare("SELECT idAdmin, email, motDePasse FROM Administrateur WHERE email = ?");
                    $stmt->execute([$email]);
                    $admin = $stmt->fetch();
                    
                    if ($admin) {
                        // Vérifier si le mot de passe est haché
                        $isHashed = password_get_info($admin['motDePasse'])['algo'] !== 0;
                        
                        if ($isHashed) {
                            // Vérifier avec password_verify
                            if (password_verify($motDePasse, $admin['motDePasse'])) {
                                // Connexion réussie
                                $_SESSION['admin_logged_in'] = true;
                                $_SESSION['admin_id'] = $admin['idAdmin'];
                                $_SESSION['admin_email'] = $admin['email'];
                                
                                header('Location: dashboard.php');
                                exit;
                            } else {
                                $error = "Email ou mot de passe incorrect.";
                            }
                        } else {
                            // Vérifier en texte clair
                            if ($motDePasse === $admin['motDePasse']) {
                                // Hacher le mot de passe pour la prochaine fois
                                $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
                                $updateStmt = $pdo->prepare("UPDATE Administrateur SET motDePasse = ? WHERE idAdmin = ?");
                                $updateStmt->execute([$hash, $admin['idAdmin']]);
                                
                                // Connexion réussie
                                $_SESSION['admin_logged_in'] = true;
                                $_SESSION['admin_id'] = $admin['idAdmin'];
                                $_SESSION['admin_email'] = $admin['email'];
                                
                                header('Location: dashboard.php');
                                exit;
                            } else {
                                $error = "Email ou mot de passe incorrect.";
                            }
                        }
                    } else {
                        $error = "Email ou mot de passe incorrect.";
                    }
                }
            } catch (PDOException $e) {
                error_log("Erreur lors de la requête: " . $e->getMessage());
                $error = "Erreur lors de la connexion. Veuillez réessayer.";
            }
        } else {
            $error = "Erreur de connexion à la base de données.";
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Origami Zen</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1a2a3a 0%, #2c3e50 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            padding: 40px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .logo span {
            color: #e74c3c;
        }
        
        .logo p {
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #f5c6cb;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #3498db;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .back-link a:hover {
            color: #2980b9;
            text-decoration: underline;
        }

        .debug-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            font-size: 12px;
            color: #6c757d;
        }

        .install-link {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .install-link a {
            color: #e74c3c;
            text-decoration: none;
            font-size: 14px;
        }

        .install-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Origami<span>Zen</span></h1>
            <p>Tableau de bord - Connexion</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" required 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn">Se connecter</button>
        </form>
        
        <div class="back-link">
            <a href="../Origami/index.html">← Retour au site principal</a>
        </div>

        <div class="install-link">
            <a href="install_admin.php">Première installation ? Créer un compte administrateur</a>
        </div>

        <?php if (isset($_GET['debug'])): ?>
        <div class="debug-info">
            <strong>Informations de débogage:</strong><br>
            PHP Version: <?php echo phpversion(); ?><br>
            Session ID: <?php echo session_id(); ?><br>
            Database: <?php echo $pdo ? 'Connectée' : 'Non connectée'; ?><br>
            Table Administrateur: <?php 
                if ($pdo) {
                    $tableExists = $pdo->query("SHOW TABLES LIKE 'Administrateur'")->rowCount() > 0;
                    echo $tableExists ? 'Existe' : 'N\'existe pas';
                } else {
                    echo 'Non vérifié';
                }
            ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
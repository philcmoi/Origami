<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Rediriger si déjà connecté
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $motDePasse = $_POST['motDePasse'] ?? '';
    
    require_once 'config.php';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT idAdmin, email, motDePasse FROM Administrateur WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($motDePasse, $admin['motDePasse'])) {
            $_SESSION['admin_id'] = $admin['idAdmin'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['last_activity'] = time();
            $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'];
            
            header('Location: admin_dashboard.php');
            exit;
        } else {
            $error = "Identifiants incorrects";
        }
    } catch (PDOException $e) {
        $error = "Erreur de connexion: " . $e->getMessage();
    }
}

if (isset($_GET['expired'])) {
    $message = "Votre session a expiré. Veuillez vous reconnecter.";
} elseif (isset($_GET['security'])) {
    $message = "Problème de sécurité détecté. Veuillez vous reconnecter.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Connexion Administrateur - Youki and Co</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            max-width: 420px;
            width: 100%;
            background: white;
            border-radius: 24px;
            padding: 32px 28px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            animation: fadeInUp 0.5s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 24px 20px;
                border-radius: 20px;
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #d40000 0%, #8b0000 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .logo span {
            font-size: 32px;
            color: white;
        }
        
        @media (max-width: 480px) {
            .logo {
                width: 60px;
                height: 60px;
                border-radius: 16px;
            }
            .logo span {
                font-size: 28px;
            }
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #666;
            font-size: 0.85rem;
        }
        
        @media (max-width: 480px) {
            .login-header h1 {
                font-size: 1.5rem;
            }
        }
        
        .form-group {
            margin-bottom: 22px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 0.85rem;
            color: #333;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 1rem;
        }
        
        input {
            width: 100%;
            padding: 12px 16px 12px 42px;
            border: 1.5px solid #e0e0e0;
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        input:focus {
            border-color: #d40000;
            outline: none;
            box-shadow: 0 0 0 3px rgba(212, 0, 0, 0.1);
        }
        
        @media (max-width: 480px) {
            input {
                padding: 10px 14px 10px 40px;
                font-size: 0.9rem;
            }
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #d40000 0%, #b30000 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(212, 0, 0, 0.3);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        @media (max-width: 480px) {
            button {
                padding: 12px;
                font-size: 0.95rem;
            }
        }
        
        .message {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.info {
            background: #e3f2fd;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .message.error {
            background: #fef3f2;
            color: #dc2626;
            border-left: 4px solid #dc3545;
        }
        
        .message i {
            font-size: 1rem;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 0.7rem;
            color: #999;
        }
        
        /* Animation pour les messages */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .shake {
            animation: shake 0.3s ease-in-out;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <span>🎨</span>
            </div>
            <h1>Youki & Co</h1>
            <p>Administration • Connexion sécurisée</p>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message info">
                <i class="fas fa-info-circle"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="message error shake">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Email</label>
                <div class="input-group">
                    <i>📧</i>
                    <input type="email" id="email" name="email" placeholder="admin@youkiandco.fr" required autocomplete="off">
                </div>
            </div>
            
            <div class="form-group">
                <label>Mot de passe</label>
                <div class="input-group">
                    <i>🔒</i>
                    <input type="password" id="motDePasse" name="motDePasse" placeholder="••••••••" required>
                </div>
            </div>
            
            <button type="submit">
                <span>Se connecter</span>
            </button>
        </form>
        
        <div class="login-footer">
            <p>© <?= date('Y') ?> Youki and Co - Créations artisanales japonaises</p>
            <p style="margin-top: 8px;">Accès réservé aux administrateurs</p>
        </div>
    </div>
    
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script>
        // Animation sur les messages d'erreur
        document.addEventListener('DOMContentLoaded', function() {
            const errorMsg = document.querySelector('.message.error');
            if (errorMsg) {
                setTimeout(() => {
                    errorMsg.classList.remove('shake');
                }, 300);
            }
        });
    </script>
</body>
</html>
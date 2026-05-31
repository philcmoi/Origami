<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Rediriger si déjà connecté
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
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
            
            header('Location: dashboard.php');
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.05"><path fill="white" d="M20,20 L30,10 L40,20 L30,30 Z M60,60 L70,50 L80,60 L70,70 Z M40,80 L45,75 L50,80 L45,85 Z"/></svg>') repeat;
            pointer-events: none;
        }
        
        .login-container {
            max-width: 440px;
            width: 100%;
            background: white;
            border-radius: 32px;
            padding: 40px 32px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            animation: fadeInUp 0.6s ease-out;
            position: relative;
            z-index: 1;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 28px 20px;
                border-radius: 28px;
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 36px;
        }
        
        .logo-wrapper {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #d40000 0%, #8b0000 100%);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 25px -5px rgba(212,0,0,0.3);
        }
        
        @media (max-width: 480px) {
            .logo-wrapper {
                width: 70px;
                height: 70px;
                border-radius: 20px;
            }
            .logo-wrapper span {
                font-size: 32px;
            }
        }
        
        .logo-wrapper span {
            font-size: 40px;
            color: white;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #6b7280;
            font-size: 0.85rem;
        }
        
        @media (max-width: 480px) {
            .login-header h1 {
                font-size: 1.5rem;
            }
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            color: #374151;
        }
        
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-group i {
            position: absolute;
            left: 16px;
            color: #9ca3af;
            font-size: 1rem;
            pointer-events: none;
        }
        
        input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 1.5px solid #e5e7eb;
            border-radius: 16px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s;
            background: #f9fafb;
        }
        
        input:focus {
            border-color: #d40000;
            outline: none;
            background: white;
            box-shadow: 0 0 0 4px rgba(212, 0, 0, 0.1);
        }
        
        @media (max-width: 480px) {
            input {
                padding: 12px 14px 12px 44px;
                font-size: 0.9rem;
                border-radius: 14px;
            }
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #d40000 0%, #b30000 100%);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
            margin-top: 8px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(212, 0, 0, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        @media (max-width: 480px) {
            button {
                padding: 12px;
                font-size: 0.95rem;
                border-radius: 14px;
            }
        }
        
        .message {
            padding: 12px 16px;
            border-radius: 14px;
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
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #f0f0f0;
        }
        
        .login-footer p {
            font-size: 0.7rem;
            color: #9ca3af;
            line-height: 1.5;
        }
        
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
            font-size: 0.7rem;
            color: #9ca3af;
        }
        
        .security-badge i {
            color: #10b981;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }
        
        .shake {
            animation: shake 0.3s ease-in-out;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo-wrapper">
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
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="admin@youkiandco.fr" required autocomplete="off">
                </div>
            </div>
            
            <div class="form-group">
                <label>Mot de passe</label>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="motDePasse" name="motDePasse" placeholder="••••••••" required>
                </div>
            </div>
            
            <button type="submit">
                <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i>
                Se connecter
            </button>
        </form>
        
        <div class="login-footer">
            <p>© <?= date('Y') ?> Youki and Co - Créations artisanales japonaises</p>
            <div class="security-badge">
                <i class="fas fa-shield-alt"></i>
                <span>Accès sécurisé • Session chiffrée</span>
            </div>
        </div>
    </div>
    
    <script>
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
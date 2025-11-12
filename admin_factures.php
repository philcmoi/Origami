<?php
// Inclure la protection au tout début - COMME DANS admin_dashboard.php
require_once 'admin_protection.php';

// Inclure PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Configuration de la base de données
$host = 'localhost';
$dbname = 'origami';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Fonction pour envoyer un email avec PHPMailer
function envoyerEmail($destinataire, $sujet, $message) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration du serveur SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'lhpp.philippe@gmail.com';
        $mail->Password = 'lvpk zqjt vuon qyrz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        
        // Options de sécurité
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Destinataires
        $mail->setFrom('lhpp.philippe@gmail.com', 'Origami Zen');
        $mail->addAddress($destinataire);
        $mail->addReplyTo('lhpp.philippe@gmail.com', 'Origami Zen');
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        if ($mail->send()) {
            return ['success' => true, 'message' => 'Email envoyé avec succès'];
        } else {
            return ['success' => false, 'error' => 'Échec de l\'envoi sans exception'];
        }
        
    } catch (Exception $e) {
        error_log("Erreur PHPMailer: " . $mail->ErrorInfo);
        return ['success' => false, 'error' => 'Erreur PHPMailer: ' . $e->getMessage()];
    }
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'envoyer_facture':
                $idCommande = $_POST['id_commande'] ?? null;
                $email = $_POST['email'] ?? '';
                
                if ($idCommande && $email) {
                    // Récupérer les détails de la commande avec l'adresse de FACTURATION
                    $stmt = $pdo->prepare("
                        SELECT 
                            c.idCommande,
                            c.dateCommande,
                            c.montantTotal,
                            cl.nom,
                            cl.prenom,
                            cl.email,
                            a_fact.adresse as adresse_facturation,
                            a_fact.codePostal as cp_facturation,
                            a_fact.ville as ville_facturation,
                            a_fact.pays as pays_facturation,
                            a_liv.adresse as adresse_livraison,
                            a_liv.codePostal as cp_livraison,
                            a_liv.ville as ville_livraison
                        FROM Commande c
                        JOIN Client cl ON c.idClient = cl.idClient
                        JOIN Adresse a_fact ON c.idAdresseFacturation = a_fact.idAdresse
                        JOIN Adresse a_liv ON c.idAdresseLivraison = a_liv.idAdresse
                        WHERE c.idCommande = ?
                    ");
                    $stmt->execute([$idCommande]);
                    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($commande) {
                        // Générer le contenu HTML de la facture
                        $sujet = "Facture - Commande #" . $commande['idCommande'] . " - Origami Zen";
                        $message = genererFactureHTML($commande);
                        
                        // Envoyer l'email avec PHPMailer
                        $resultat = envoyerEmail($email, $sujet, $message);
                        
                        if ($resultat['success']) {
                            $_SESSION['message_success'] = "✅ Facture #" . $commande['idCommande'] . " envoyée avec succès à " . $email;
                        } else {
                            $_SESSION['message_error'] = "❌ Erreur lors de l'envoi: " . $resultat['error'];
                        }
                    } else {
                        $_SESSION['message_error'] = "❌ Commande non trouvée";
                    }
                } else {
                    $_SESSION['message_error'] = "❌ Données manquantes";
                }
                break;
        }
        
        // Rediriger pour éviter la resoumission du formulaire
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Gestion de l'action GET pour générer une facture
if (isset($_GET['action']) && $_GET['action'] === 'generer' && isset($_GET['id'])) {
    $idCommande = $_GET['id'];
    
    // Récupérer les détails de la commande avec l'adresse de FACTURATION
    $stmt = $pdo->prepare("
        SELECT 
            c.idCommande,
            c.dateCommande,
            c.montantTotal,
            cl.nom,
            cl.prenom,
            cl.email,
            a_fact.adresse as adresse_facturation,
            a_fact.codePostal as cp_facturation,
            a_fact.ville as ville_facturation,
            a_fact.pays as pays_facturation,
            a_liv.adresse as adresse_livraison,
            a_liv.codePostal as cp_livraison,
            a_liv.ville as ville_livraison
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        JOIN Adresse a_fact ON c.idAdresseFacturation = a_fact.idAdresse
        JOIN Adresse a_liv ON c.idAdresseLivraison = a_liv.idAdresse
        WHERE c.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($commande) {
        // Afficher la facture
        header('Content-Type: text/html; charset=UTF-8');
        echo genererFactureHTML($commande);
        exit;
    }
}

// Fonction pour générer le HTML de la facture
function genererFactureHTML($commande) {
    // Calculs financiers - le montantTotal est TTC
    $montantTTC = $commande['montantTotal'];
    $tauxTVA = 0.20; // 20%
    $montantHT = $montantTTC / (1 + $tauxTVA);
    $montantTVA = $montantTTC - $montantHT;
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Facture #" . $commande['idCommande'] . " - Origami Zen</title>
        <style>
            body { 
                font-family: 'Helvetica Neue', Arial, sans-serif; 
                margin: 0; 
                padding: 0; 
                color: #333;
                background-color: #f9f9f9;
                line-height: 1.6;
            }
            .container { 
                max-width: 700px; 
                background: white; 
                padding: 30px; 
                margin: 0 auto;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header { 
                text-align: center; 
                color: #d40000; 
                margin-bottom: 25px; 
                border-bottom: 2px solid #f0f0f0;
                padding-bottom: 20px;
            }
            .info-section {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 25px;
            }
            .info-box {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #e9ecef;
            }
            .info-box h3 {
                margin-top: 0;
                color: #d40000;
                border-bottom: 1px solid #dee2e6;
                padding-bottom: 10px;
                margin-bottom: 15px;
                font-size: 16px;
            }
            .address-block {
                margin-bottom: 8px;
                padding-left: 10px;
            }
            .table-facture {
                width: 100%;
                border-collapse: collapse;
                margin: 25px 0;
                background: white;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .table-facture th {
                background: #d40000;
                color: white;
                padding: 15px;
                text-align: left;
                font-weight: bold;
                font-size: 14px;
            }
            .table-facture td {
                padding: 15px;
                border-bottom: 1px solid #ddd;
                font-size: 14px;
            }
            .total-section {
                margin-top: 25px;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 8px;
                border: 1px solid #e9ecef;
            }
            .total-line {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
                padding: 5px 0;
            }
            .total-final {
                display: flex;
                justify-content: space-between;
                margin-top: 12px;
                padding-top: 12px;
                border-top: 2px solid #d40000;
                font-size: 16px;
                font-weight: bold;
                color: #d40000;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #eee;
                color: #666;
                text-align: center;
                font-size: 12px;
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
            }
            .entreprise-info {
                background: #d40000;
                color: white;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 25px;
                text-align: center;
            }
            .badge {
                background: #28a745;
                color: white;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
                margin-left: 10px;
            }
            .client-info {
                margin-bottom: 8px;
                padding-left: 10px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='entreprise-info'>
                <h1 style='margin: 0; color: white; font-size: 24px;'>Origami Zen</h1>
                <p style='margin: 5px 0 0 0; opacity: 0.9;'>Créations artisanales japonaises</p>
            </div>
            
            <div class='header'>
                <h2 style='margin: 0 0 8px 0; font-size: 20px;'>FACTURE #" . $commande['idCommande'] . "</h2>
                <p style='margin: 0; font-size: 14px;'>Date d'émission: " . date('d/m/Y', strtotime($commande['dateCommande'])) . "</p>
                <span class='badge'>PAYÉE</span>
            </div>
            
            <div class='info-section'>
                <div class='info-box'>
                    <h3>👤 INFORMATIONS CLIENT</h3>
                    <div class='client-info'><strong>" . htmlspecialchars($commande['prenom']) . " " . htmlspecialchars($commande['nom']) . "</strong></div>
                    <div class='client-info'>📧 " . htmlspecialchars($commande['email']) . "</div>
                </div>
                
                <div class='info-box'>
                    <h3>🏢 ADRESSE DE FACTURATION</h3>
                    <div class='address-block'><strong>" . htmlspecialchars($commande['prenom']) . " " . htmlspecialchars($commande['nom']) . "</strong></div>
                    <div class='address-block'>" . htmlspecialchars($commande['adresse_facturation']) . "</div>
                    <div class='address-block'>" . htmlspecialchars($commande['cp_facturation']) . " " . htmlspecialchars($commande['ville_facturation']) . "</div>
                    <div class='address-block'>" . htmlspecialchars($commande['pays_facturation'] ?? 'France') . "</div>
                </div>
            </div>

            <div class='info-section'>
                <div class='info-box'>
                    <h3>📦 ADRESSE DE LIVRAISON</h3>
                    <div class='address-block'>" . htmlspecialchars($commande['adresse_livraison']) . "</div>
                    <div class='address-block'>" . htmlspecialchars($commande['cp_livraison']) . " " . htmlspecialchars($commande['ville_livraison']) . "</div>
                </div>
            </div>
            
            <table class='table-facture'>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Quantité</th>
                        <th>Prix Unitaire TTC</th>
                        <th>Total TTC</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>Commande #" . $commande['idCommande'] . "</strong><br>
                            <small style='color: #666;'>Origamis artisanaux - Créations japonaises</small>
                        </td>
                        <td>1</td>
                        <td>" . number_format($commande['montantTotal'], 2, ',', ' ') . " €</td>
                        <td><strong>" . number_format($commande['montantTotal'], 2, ',', ' ') . " €</strong></td>
                    </tr>
                </tbody>
            </table>
            
            <div class='total-section'>
                <h3 style='margin: 0 0 15px 0; color: #d40000; text-align: center; font-size: 16px;'>DÉTAIL DU MONTANT</h3>
                
                <div class='total-line'>
                    <span>Sous-total HT:</span>
                    <span>" . number_format($montantHT, 2, ',', ' ') . " €</span>
                </div>
                
                <div class='total-line'>
                    <span>TVA (" . ($tauxTVA * 100) . "%):</span>
                    <span>" . number_format($montantTVA, 2, ',', ' ') . " €</span>
                </div>
                
                <div class='total-final'>
                    <span>TOTAL TTC:</span>
                    <span>" . number_format($montantTTC, 2, ',', ' ') . " €</span>
                </div>
            </div>
            
            <div class='footer'>
                <p style='margin: 0 0 8px 0; font-weight: bold; font-size: 14px;'>Origami Zen - Créations artisanales japonaises</p>
                <p style='margin: 4px 0;'>📧 contact@origamizen.fr | 📞 +33 1 23 45 67 89</p>
                <p style='margin: 4px 0;'>123 Rue du Papier, 75000 Paris, France</p>
                <p style='margin: 4px 0;'>SIRET: 123 456 789 00012 | APE: 1234Z | TVA: FR12345678901</p>
                <p style='margin-top: 12px; font-size: 11px; color: #999;'>
                    Facture émise le " . date('d/m/Y à H:i') . "
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
}

// Récupérer la liste des commandes avec l'adresse de FACTURATION
$stmt = $pdo->prepare("
    SELECT 
        c.idCommande,
        c.dateCommande,
        c.montantTotal,
        c.statut,
        cl.nom,
        cl.prenom,
        cl.email,
        a_fact.adresse as adresse_facturation,
        a_fact.ville as ville_facturation,
        a_liv.adresse as adresse_livraison,
        a_liv.ville as ville_livraison
    FROM Commande c
    JOIN Client cl ON c.idClient = cl.idClient
    JOIN Adresse a_fact ON c.idAdresseFacturation = a_fact.idAdresse
    JOIN Adresse a_liv ON c.idAdresseLivraison = a_liv.idAdresse
    ORDER BY c.dateCommande DESC
");
$stmt->execute();
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration des Factures - Origami Zen</title>
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
        
        .message-success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            border-left: 5px solid #28a745;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            border-left: 5px solid #dc3545;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-commandes {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .table-commandes th {
            background: #d40000;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .table-commandes td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .table-commandes tr:hover {
            background: #f8f9fa;
        }
        
        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
        }
        
        .statut {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .statut-payee { background: #d4edda; color: #155724; }
        .statut-en_attente { background: #fff3cd; color: #856404; }
        .statut-expediee { background: #cce7ff; color: #004085; }
        .statut-annulee { background: #f8d7da; color: #721c24; }
        
        .form-inline {
            display: inline;
        }
        
        .actions-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .page-title {
            color: #d40000;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #d40000;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .stat-card .number {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>Origami Zen - Administration</h1>
        </div>
        <div class="admin-info">
            <span>Connecté en tant que: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="admin_logout.php" class="btn-logout">Déconnexion</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="nav-item">Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">Gestion des Commandes</a>
            <a href="admin_factures.php" class="nav-item active">Factures</a>
            <a href="admin_clients.php" class="nav-item">Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item">Gestion des Produits</a>
        </div>
        
        <div class="main-content">
            <h1 class="page-title">📄 Administration des Factures</h1>

            <?php if (isset($_SESSION['message_success'])): ?>
                <div class="message-success">
                    <span style="font-size: 18px;">✅</span>
                    <div>
                        <strong>Succès!</strong><br>
                        <?= $_SESSION['message_success'] ?>
                    </div>
                </div>
                <?php unset($_SESSION['message_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['message_error'])): ?>
                <div class="message-error">
                    <span style="font-size: 18px;">❌</span>
                    <div>
                        <strong>Erreur!</strong><br>
                        <?= $_SESSION['message_error'] ?>
                    </div>
                </div>
                <?php unset($_SESSION['message_error']); ?>
            <?php endif; ?>

            <!-- Statistiques rapides -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Factures</h3>
                    <div class="number"><?= count($commandes) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Factures Payées</h3>
                    <div class="number">
                        <?= count(array_filter($commandes, function($cmd) { return $cmd['statut'] === 'payee'; })) ?>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Chiffre d'Affaires</h3>
                    <div class="number">
                        <?= number_format(array_sum(array_column($commandes, 'montantTotal')), 2, ',', ' ') ?> €
                    </div>
                </div>
            </div>

            <table class="table-commandes">
                <thead>
                    <tr>
                        <th>ID Commande</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Montant TTC</th>
                        <th>Statut</th>
                        <th>Adresse Facturation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commandes as $commande): ?>
                        <tr>
                            <td><strong>#<?= $commande['idCommande'] ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($commande['prenom']) ?> <?= htmlspecialchars($commande['nom']) ?></strong><br>
                                <small>📧 <?= htmlspecialchars($commande['email']) ?></small>
                            </td>
                            <td><strong><?= number_format($commande['montantTotal'], 2, ',', ' ') ?> € TTC</strong></td>
                            <td>
                                <span class="statut statut-<?= $commande['statut'] ?>">
                                    <?= $commande['statut'] ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($commande['adresse_facturation']) ?><br>
                                <small><?= htmlspecialchars($commande['ville_facturation']) ?></small>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <form method="POST" class="form-inline">
                                        <input type="hidden" name="id_commande" value="<?= $commande['idCommande'] ?>">
                                        <input type="hidden" name="email" value="<?= htmlspecialchars($commande['email']) ?>">
                                        <input type="hidden" name="action" value="envoyer_facture">
                                        <button type="submit" class="btn btn-success" onclick="return confirm('Envoyer la facture #<?= $commande['idCommande'] ?> à <?= htmlspecialchars($commande['email']) ?> ?')">
                                            📧 Envoyer
                                        </button>
                                    </form>
                                    <a href="admin_factures.php?action=generer&id=<?= $commande['idCommande'] ?>" class="btn btn-primary" target="_blank">
                                        👁️ Voir
                                    </a>
                                    <a href="generer_facture.php?id=<?= $commande['idCommande'] ?>" class="btn btn-warning" target="_blank">
                                        📄 PDF
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message-success, .message-error');
            messages.forEach(message => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
<?php
// Inclure la protection au tout début - COMME DANS admin_dashboard.php
require_once 'admin_protection.php';

require_once 'smtp_config.php';

// Inclure PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Configuration de la base de données
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Fonction pour envoyer un email avec PHPMailer
function envoyerEmail($destinataire, $sujet, $message, $pieceJointe = null) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($destinataire);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        if ($pieceJointe && file_exists($pieceJointe)) {
            $mail->addAttachment($pieceJointe, 'facture_' . basename($pieceJointe));
        }
        
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
                    $stmt = $pdo->prepare("
                        SELECT 
                            c.idCommande,
                            c.dateCommande,
                            c.montantTotal,
                            c.statut,
                            cl.nom,
                            cl.prenom,
                            cl.email
                        FROM Commande c
                        JOIN Client cl ON c.idClient = cl.idClient
                        WHERE c.idCommande = ?
                    ");
                    $stmt->execute([$idCommande]);
                    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($commande) {
                        if ($commande['statut'] !== 'payee') {
                            $_SESSION['message_error'] = "❌ Impossible d'envoyer la facture : La commande #" . $commande['idCommande'] . " n'est pas payée (statut: " . $commande['statut'] . ")";
                            break;
                        }
                        
                        require_once 'genererFacturePDF.php';
                        $cheminPDF = genererFacturePDF($pdo, $idCommande);
                        
                        if ($cheminPDF && file_exists($cheminPDF)) {
                            $sujet = "Votre facture Youki and Co - Commande #" . $commande['idCommande'];
                            $message = "
                            <html>
                            <head>
                                <style>
                                    body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                                    .container { max-width: 600px; margin: 0 auto; }
                                    .header { background: #d40000; color: white; padding: 20px; text-align: center; }
                                    .content { padding: 20px; background: #f9f9f9; }
                                    .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; background: #f0f0f0; }
                                    .info-box { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #d40000; }
                                </style>
                            </head>
                            <body>
                                <div class='container'>
                                    <div class='header'>
                                        <h1>Youki and Co</h1>
                                        <p>Créations artisanales japonaises</p>
                                    </div>
                                    <div class='content'>
                                        <h2>Merci pour votre commande !</h2>
                                        <p>Bonjour <strong>" . htmlspecialchars($commande['prenom']) . " " . htmlspecialchars($commande['nom']) . "</strong>,</p>
                                        
                                        <div class='info-box'>
                                            <h3>📦 Détails de votre commande</h3>
                                            <p><strong>Commande #" . $commande['idCommande'] . "</strong></p>
                                            <p>Date : " . date('d/m/Y', strtotime($commande['dateCommande'])) . "</p>
                                            <p><strong>Montant total : " . number_format($commande['montantTotal'], 2, ',', ' ') . " €</strong></p>
                                        </div>
                                        
                                        <p>Votre facture détaillée est jointe à cet email au format PDF.</p>
                                        <p>Nous vous remercions pour votre confiance et espérons vous revoir très bientôt !</p>
                                        <br>
                                        <p>Cordialement,<br>L'équipe Youki and Co</p>
                                    </div>
                                    <div class='footer'>
                                        <p><strong>Youki and Co - Créations artisanales japonaises</strong></p>
                                        <p>📧 " . SMTP_FROM_EMAIL . " | 📞 +33 1 23 45 67 89</p>
                                        <p>123 Rue du Papier, 75000 Paris, France</p>
                                        <p><em>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</em></p>
                                    </div>
                                </div>
                            </body>
                            </html>
                            ";
                            
                            $resultat = envoyerEmail($email, $sujet, $message, $cheminPDF);
                            
                            if ($resultat['success']) {
                                $_SESSION['message_success'] = "✅ Facture #" . $commande['idCommande'] . " envoyée avec succès à " . $email;
                            } else {
                                $_SESSION['message_error'] = "❌ Erreur lors de l'envoi: " . $resultat['error'];
                            }
                        } else {
                            $_SESSION['message_error'] = "❌ Erreur lors de la génération du PDF";
                        }
                    } else {
                        $_SESSION['message_error'] = "❌ Commande non trouvée";
                    }
                } else {
                    $_SESSION['message_error'] = "❌ Données manquantes";
                }
                break;
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Gestion de l'action GET pour générer une facture
if (isset($_GET['action']) && $_GET['action'] === 'generer' && isset($_GET['id'])) {
    $idCommande = $_GET['id'];
    
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
        header('Content-Type: text/html; charset=UTF-8');
        echo genererFactureHTML($pdo, $idCommande);
        exit;
    }
}

// Fonction pour générer le HTML de la facture AVEC DÉTAIL DES ARTICLES
function genererFactureHTML($pdo, $idCommande) {
    // Récupérer les détails de la commande
    $stmt = $pdo->prepare("
        SELECT 
            c.idCommande,
            c.dateCommande,
            c.montantTotal,
            c.fraisDePort,
            c.statut,
            cl.nom,
            cl.prenom,
            cl.email,
            cl.telephone,
            a_fact.adresse as adresse_facturation,
            a_fact.codePostal as cp_facturation,
            a_fact.ville as ville_facturation,
            a_fact.pays as pays_facturation,
            a_liv.adresse as adresse_livraison,
            a_liv.codePostal as cp_livraison,
            a_liv.ville as ville_livraison,
            a_liv.pays as pays_livraison
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        JOIN Adresse a_fact ON c.idAdresseFacturation = a_fact.idAdresse
        JOIN Adresse a_liv ON c.idAdresseLivraison = a_liv.idAdresse
        WHERE c.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        return "<h1>Commande non trouvée</h1>";
    }
    
    // Récupérer les articles de la commande
    $stmt = $pdo->prepare("
        SELECT 
            lc.quantite,
            lc.prixUnitaire,
            (lc.quantite * lc.prixUnitaire) as total_ligne,
            o.nom as produit_nom
        FROM LigneCommande lc
        JOIN Origami o ON lc.idOrigami = o.idOrigami
        WHERE lc.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcul des totaux
    $sousTotal = 0;
    foreach ($articles as $article) {
        $sousTotal += $article['total_ligne'];
    }
    $totalGeneral = $sousTotal + ($commande['fraisDePort'] ?? 0);
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Facture #" . $commande['idCommande'] . " - Youki and Co</title>
        <style>
            body { 
                font-family: 'Helvetica Neue', Arial, sans-serif; 
                margin: 0; 
                padding: 20px; 
                color: #333;
                background-color: #f9f9f9;
                line-height: 1.6;
            }
            .container { 
                max-width: 900px; 
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
            .header h1 { margin: 0; font-size: 28px; }
            .entreprise-info {
                background: #d40000;
                color: white;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 25px;
                text-align: center;
            }
            .entreprise-info h1 { margin: 0; color: white; font-size: 24px; }
            .badge {
                background: #28a745;
                color: white;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
                display: inline-block;
                margin-top: 10px;
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
            }
            .table-facture th {
                background: #d40000;
                color: white;
                padding: 12px;
                text-align: left;
                font-weight: bold;
                font-size: 14px;
            }
            .table-facture td {
                padding: 12px;
                border-bottom: 1px solid #ddd;
                font-size: 14px;
            }
            .table-facture .text-right {
                text-align: right;
            }
            .totals {
                text-align: right;
                margin-top: 20px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 8px;
            }
            .total-line {
                padding: 5px 0;
            }
            .grand-total {
                font-size: 18px;
                font-weight: bold;
                margin-top: 10px;
                padding-top: 10px;
                border-top: 2px solid #d40000;
                color: #d40000;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #eee;
                text-align: center;
                font-size: 11px;
                color: #999;
            }
            .legal-mention {
                margin-top: 20px;
                padding: 10px;
                background: #fafafa;
                font-size: 11px;
                color: #666;
                text-align: center;
            }
            @media print {
                body { background: white; padding: 0; }
                .container { box-shadow: none; padding: 15px; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='entreprise-info'>
                <h1>YOUKI & CO</h1>
                <p>Créations artisanales japonaises</p>
            </div>
            
            <div class='header'>
                <h2>FACTURE N° " . $commande['idCommande'] . "</h2>
                <p>Date d'émission: " . date('d/m/Y', strtotime($commande['dateCommande'])) . "</p>
                " . ($commande['statut'] === 'payee' ? '<div class="badge">✓ PAYÉE</div>' : '<div class="badge" style="background:#ffc107;color:#333;">⚠️ NON PAYÉE</div>') . "
            </div>
            
            <div class='info-section'>
                <div class='info-box'>
                    <h3>👤 CLIENT</h3>
                    <div><strong>" . htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) . "</strong></div>
                    <div>📧 " . htmlspecialchars($commande['email']) . "</div>
                    " . (!empty($commande['telephone']) ? "<div>📞 " . htmlspecialchars($commande['telephone']) . "</div>" : "") . "
                </div>
                <div class='info-box'>
                    <h3>🏢 ADRESSE DE FACTURATION</h3>
                    <div>" . htmlspecialchars($commande['adresse_facturation']) . "</div>
                    <div>" . htmlspecialchars($commande['cp_facturation'] . ' ' . $commande['ville_facturation']) . "</div>
                    <div>" . htmlspecialchars($commande['pays_facturation'] ?? 'France') . "</div>
                </div>
            </div>
            
            <div class='info-section'>
                <div class='info-box'>
                    <h3>📦 ADRESSE DE LIVRAISON</h3>
                    <div>" . htmlspecialchars($commande['adresse_livraison']) . "</div>
                    <div>" . htmlspecialchars($commande['cp_livraison'] . ' ' . $commande['ville_livraison']) . "</div>
                    <div>" . htmlspecialchars($commande['pays_livraison'] ?? 'France') . "</div>
                </div>
                <div class='info-box'>
                    <h3>📅 INFORMATIONS COMMANDE</h3>
                    <div>Date: " . date('d/m/Y H:i', strtotime($commande['dateCommande'])) . "</div>
                    <div>Mode de règlement: PayPal / Carte bancaire</div>
                    <div>Frais de port: " . number_format($commande['fraisDePort'] ?? 0, 2, ',', ' ') . " €</div>
                </div>
            </div>
            
            <h3 style='margin: 20px 0 10px 0;'>📋 DÉTAIL DES ARTICLES</h3>
            
            <table class='table-facture'>
                <thead>
                    <tr>
                        <th>Désignation</th>
                        <th class='text-right'>Prix unitaire</th>
                        <th class='text-right'>Quantité</th>
                        <th class='text-right'>Total</th>
                    </tr>
                </thead>
                <tbody>";
    
    foreach ($articles as $article) {
        $html .= "
                    <tr>
                        <td><strong>" . htmlspecialchars($article['produit_nom']) . "</strong></td>
                        <td class='text-right'>" . number_format($article['prixUnitaire'], 2, ',', ' ') . " €</td>
                        <td class='text-right'>" . $article['quantite'] . "</td>
                        <td class='text-right'>" . number_format($article['total_ligne'], 2, ',', ' ') . " €</td>
                    </tr>";
    }
    
    $html .= "
                </tbody>
            </table>
            
            <div class='totals'>
                <div class='total-line'>Sous-total : " . number_format($sousTotal, 2, ',', ' ') . " €</div>
                <div class='total-line'>Frais de port : " . number_format($commande['fraisDePort'] ?? 0, 2, ',', ' ') . " €</div>
                <div class='grand-total'>TOTAL : " . number_format($totalGeneral, 2, ',', ' ') . " €</div>
            </div>
            
            <div class='legal-mention'>
                Exonération de TVA - Article 293 B du Code Général des Impôts
            </div>
            
            <div class='footer'>
                Youki and Co - Créations artisanales japonaises<br>
                contact@youkiandco.fr - SIRET: 123 456 789 00012<br>
                Facture émise le " . date('d/m/Y à H:i') . "
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}

// Récupérer la liste des commandes avec les articles pour l'affichage
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
        a_liv.ville as ville_livraison,
        (SELECT COUNT(*) FROM LigneCommande WHERE idCommande = c.idCommande) as nb_articles
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
    <title>Administration des Factures - Youki and Co</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; color: #333; }
        .header { background: white; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .logo h1 { color: #d40000; font-size: 24px; }
        .admin-info { display: flex; align-items: center; gap: 15px; }
        .btn-logout { background: #d40000; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-size: 14px; }
        .container { display: flex; min-height: calc(100vh - 80px); }
        .sidebar { width: 250px; background: white; padding: 20px; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .nav-item { display: block; padding: 12px 15px; color: #333; text-decoration: none; border-radius: 5px; margin-bottom: 5px; transition: background 0.3s; }
        .nav-item:hover, .nav-item.active { background: #d40000; color: white; }
        .main-content { flex: 1; padding: 30px; }
        .message-success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid #28a745; display: flex; align-items: center; gap: 10px; }
        .message-error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid #dc3545; display: flex; align-items: center; gap: 10px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; }
        .stat-card h3 { color: #d40000; margin-bottom: 10px; font-size: 14px; text-transform: uppercase; }
        .stat-card .number { font-size: 28px; font-weight: bold; color: #333; }
        .table-commandes { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .table-commandes th { background: #d40000; color: white; padding: 15px; text-align: left; font-weight: 600; }
        .table-commandes td { padding: 15px; border-bottom: 1px solid #eee; }
        .table-commandes tr:hover { background: #f8f9fa; }
        .btn { padding: 10px 16px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-size: 14px; font-weight: 500; transition: all 0.3s; }
        .btn-success { background-color: #28a745; color: white; }
        .btn-success:hover { background-color: #218838; transform: translateY(-1px); }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-primary:hover { background-color: #0056b3; transform: translateY(-1px); }
        .btn-warning { background-color: #ffc107; color: #212529; }
        .btn-warning:hover { background-color: #e0a800; transform: translateY(-1px); }
        .btn-disabled { background-color: #6c757d; color: white; cursor: not-allowed; opacity: 0.6; }
        .statut { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .statut-payee { background: #d4edda; color: #155724; }
        .statut-en_attente { background: #fff3cd; color: #856404; }
        .statut-expediee { background: #cce7ff; color: #004085; }
        .statut-annulee { background: #f8d7da; color: #721c24; }
        .actions-cell { display: flex; gap: 8px; flex-wrap: wrap; }
        .page-title { color: #d40000; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .tooltip { position: relative; display: inline-block; }
        .tooltip .tooltiptext { visibility: hidden; width: 220px; background-color: #555; color: #fff; text-align: center; border-radius: 6px; padding: 8px; position: absolute; z-index: 1; bottom: 125%; left: 50%; margin-left: -110px; opacity: 0; transition: opacity 0.3s; font-size: 12px; font-weight: normal; }
        .tooltip:hover .tooltiptext { visibility: visible; opacity: 1; }
        @media (max-width: 768px) { .sidebar { width: 200px; } .table-commandes { font-size: 12px; } .btn { padding: 6px 10px; font-size: 11px; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo"><h1>Youki and Co - Administration</h1></div>
        <div class="admin-info">
            <span>Connecté en tant que: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="admin_logout.php" class="btn-logout">Déconnexion</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="nav-item">Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">Gestion des Commandes</a>
            <a href="admin_factures.php" class="nav-item active">Gestion des Factures</a>
            <a href="admin_clients.php" class="nav-item">Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item">Gestion des Produits</a>
        </div>
        
        <div class="main-content">
            <h1 class="page-title">📄 Administration des Factures</h1>

            <?php if (isset($_SESSION['message_success'])): ?>
                <div class="message-success">✅ <div><strong>Succès!</strong><br><?= $_SESSION['message_success'] ?></div></div>
                <?php unset($_SESSION['message_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['message_error'])): ?>
                <div class="message-error">❌ <div><strong>Erreur!</strong><br><?= $_SESSION['message_error'] ?></div></div>
                <?php unset($_SESSION['message_error']); ?>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card"><h3>Total Factures</h3><div class="number"><?= count($commandes) ?></div></div>
                <div class="stat-card"><h3>Factures Payées</h3><div class="number"><?= count(array_filter($commandes, function($cmd) { return $cmd['statut'] === 'payee'; })) ?></div></div>
                <div class="stat-card"><h3>Chiffre d'Affaires</h3><div class="number"><?= number_format(array_sum(array_column($commandes, 'montantTotal')), 2, ',', ' ') ?> €</div></div>
            </div>

            <table class="table-commandes">
                <thead>
                    <tr>
                        <th>ID Commande</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Articles</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commandes as $commande): ?>
                    <tr>
                        <td><strong>#<?= $commande['idCommande'] ?></strong></td>
                        <td><?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></td>
                        <td><strong><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></strong><br><small><?= htmlspecialchars($commande['email']) ?></small></td>
                        <td class="text-center"><?= $commande['nb_articles'] ?? 0 ?> article(s)</td>
                        <td><strong><?= number_format($commande['montantTotal'], 2, ',', ' ') ?> €</strong></td>
                        <td><span class="statut statut-<?= $commande['statut'] ?>"><?= $commande['statut'] ?></span></td>
                        <td class="actions-cell">
                            <?php if ($commande['statut'] === 'payee'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id_commande" value="<?= $commande['idCommande'] ?>">
                                    <input type="hidden" name="email" value="<?= htmlspecialchars($commande['email']) ?>">
                                    <input type="hidden" name="action" value="envoyer_facture">
                                    <button type="submit" class="btn btn-success" onclick="return confirm('Envoyer la facture #<?= $commande['idCommande'] ?> à <?= htmlspecialchars($commande['email']) ?> ?')">📧 Envoyer</button>
                                </form>
                            <?php else: ?>
                                <div class="tooltip">
                                    <button type="button" class="btn btn-disabled">📧 Envoyer</button>
                                    <span class="tooltiptext">La facture ne peut être envoyée que pour les commandes payées</span>
                                </div>
                            <?php endif; ?>
                            <a href="admin_factures.php?action=generer&id=<?= $commande['idCommande'] ?>" class="btn btn-primary" target="_blank">👁️ Voir</a>
                            <a href="generer_facture.php?id=<?= $commande['idCommande'] ?>" class="btn btn-warning" target="_blank">📄 PDF</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
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
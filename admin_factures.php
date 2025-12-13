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
        // Configuration du serveur SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
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

        // Destinataires - UTILISER LES CONSTANTES DE CONFIGURATION
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($destinataire);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // Pièce jointe si fournie
        if ($pieceJointe && file_exists($pieceJointe)) {
            $mail->addAttachment($pieceJointe, 'facture_' . basename($pieceJointe));
        }

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
                    // Récupérer les détails de la commande avec l'adresse de FACTURATION et vérifier le statut
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
                        // VÉRIFIER SI LA COMMANDE EST PAYÉE
                        if ($commande['statut'] !== 'payee') {
                            $_SESSION['message_error'] = "❌ Impossible d'envoyer la facture : La commande #" . $commande['idCommande'] . " n'est pas payée (statut: " . $commande['statut'] . ")";
                            break;
                        }

                        // Inclure et utiliser la vraie fonction de génération PDF
                        require_once 'genererFacturePDF.php';
                        $cheminPDF = genererFacturePDF($pdo, $idCommande);

                        if ($cheminPDF && file_exists($cheminPDF)) {
                            // Générer le contenu HTML de l'email
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
                                            <p><strong>Montant total : " . number_format($commande['montantTotal'], 2, ',', ' ') . " € TTC</strong></p>
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

                            // Envoyer l'email avec PHPMailer et la pièce jointe PDF
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
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Facture #" . $commande['idCommande'] . " - Youki and Co</title>
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
                padding: 20px;
                margin: 0 auto;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            @media (max-width: 768px) {
                .container {
                    padding: 15px;
                    margin: 10px;
                    max-width: none;
                }
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
            @media (max-width: 768px) {
                .info-section {
                    grid-template-columns: 1fr;
                    gap: 15px;
                }
            }
            .info-box {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                border: 1px solid #e9ecef;
            }
            @media (max-width: 480px) {
                .info-box {
                    padding: 12px;
                }
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
            @media (max-width: 768px) {
                .table-facture {
                    display: block;
                    overflow-x: auto;
                    white-space: nowrap;
                }
            }
            .table-facture th {
                background: #d40000;
                color: white;
                padding: 12px;
                text-align: left;
                font-weight: bold;
                font-size: 14px;
            }
            @media (max-width: 480px) {
                .table-facture th {
                    padding: 8px;
                    font-size: 12px;
                }
            }
            .table-facture td {
                padding: 12px;
                border-bottom: 1px solid #ddd;
                font-size: 14px;
            }
            @media (max-width: 480px) {
                .table-facture td {
                    padding: 8px;
                    font-size: 12px;
                }
            }
            .total-section {
                margin-top: 25px;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 8px;
                border: 1px solid #e9ecef;
            }
            @media (max-width: 480px) {
                .total-section {
                    padding: 15px;
                }
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
            @media (max-width: 480px) {
                .total-final {
                    font-size: 14px;
                flex-direction: column;
                    gap: 5px;
                }
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
            .statut-non-paye {
                background: #ffc107;
                color: #212529;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
                margin-left: 10px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='entreprise-info'>
                <h1 style='margin: 0; color: white; font-size: 24px;'>Youki and Co</h1>
                <p style='margin: 5px 0 0 0; opacity: 0.9;'>Créations artisanales japonaises</p>
            </div>

            <div class='header'>
                <h2 style='margin: 0 0 8px 0; font-size: 20px;'>FACTURE #" . $commande['idCommande'] . "</h2>
                <p style='margin: 0; font-size: 14px;'>Date d'émission: " . date('d/m/Y', strtotime($commande['dateCommande'])) . "</p>
                " . ($commande['statut'] === 'payee' ?
                    '<span class="badge">PAYÉE</span>' :
                    '<span class="statut-non-paye">NON PAYÉE</span>') . "
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
                <p style='margin: 0 0 8px 0; font-weight: bold; font-size: 14px;'>Youki and Co - Créations artisanales japonaises</p>
                <p style='margin: 4px 0;'>📧 contact@YoukiAndCo.fr | 📞 +33 1 23 45 67 89</p>
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
    <title>Administration des Factures - Youki and Co</title>
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
            font-size: 14px;
        }

        /* ===== HEADER OPTIMISÉ ===== */
        .header {
            background: white;
            padding: 12px 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo h1 {
            color: #d40000;
            font-size: 18px;
            text-align: center;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .admin-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-align: center;
        }

        .admin-info span {
            font-size: 13px;
            color: #666;
        }

        .btn-logout {
            background: #d40000;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            display: inline-block;
            transition: background 0.3s;
            font-weight: 500;
        }

        .btn-logout:hover {
            background: #b30000;
        }

        /* ===== LAYOUT PRINCIPAL ===== */
        .container {
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 80px);
        }

        /* ===== MENU MOBILE OPTIMISÉ ===== */
        .mobile-menu-toggle {
            display: block;
            background: #d40000;
            color: white;
            border: none;
            padding: 12px 15px;
            border-radius: 6px;
            cursor: pointer;
            margin: 15px;
            width: calc(100% - 30px);
            font-size: 15px;
            font-weight: 500;
            transition: background 0.3s;
        }

        .mobile-menu-toggle:hover {
            background: #b30000;
        }

        .sidebar {
            background: white;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
            position: fixed;
            top: 80px;
            left: 0;
            width: 100%;
            height: calc(100vh - 80px);
            overflow-y: auto;
            z-index: 99;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .sidebar.active {
            display: block;
            transform: translateX(0);
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .nav-item:last-child {
            border-bottom: none;
        }

        .nav-item:hover, .nav-item.active {
            background: #d40000;
            color: white;
        }

        /* ===== CONTENU PRINCIPAL ===== */
        .main-content {
            flex: 1;
            padding: 15px;
        }

        /* ===== MESSAGES ===== */
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

        /* ===== STATISTIQUES RESPONSIVES ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        @media (min-width: 400px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
            }
        }

        .stat-card {
            background: white;
            padding: 20px 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid #d40000;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #d40000;
            margin-bottom: 6px;
            line-height: 1;
        }

        .stat-label {
            color: #666;
            font-size: 13px;
            font-weight: 500;
        }

        /* ===== SECTIONS ===== */
        .section {
            background: white;
            padding: 20px 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .section h2 {
            margin-bottom: 18px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 12px;
            font-size: 18px;
            font-weight: 600;
        }

        /* ===== TABLEAU DES FACTURES ===== */
        .table-container {
            display: block;
            overflow-x: auto;
            margin-bottom: 20px;
        }

        @media (min-width: 1200px) {
            .orders-mobile {
                display: none;
            }
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            min-width: 800px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            white-space: nowrap;
            position: sticky;
            top: 0;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            transition: all 0.3s;
            font-weight: 500;
            white-space: nowrap;
        }

        @media (max-width: 480px) {
            .btn {
                padding: 6px 10px;
                font-size: 11px;
            }
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

        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background-color: #e0a800;
            transform: translateY(-1px);
        }

        .btn-disabled {
            background-color: #6c757d;
            color: white;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-disabled:hover {
            background-color: #6c757d;
            transform: none;
        }

        .statut {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            white-space: nowrap;
        }

        @media (max-width: 480px) {
            .statut {
                font-size: 10px;
                padding: 3px 6px;
            }
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
            gap: 6px;
            flex-wrap: wrap;
        }

        @media (max-width: 480px) {
            .actions-cell {
                gap: 4px;
                flex-direction: column;
            }
        }

        .page-title {
            color: #d40000;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            font-size: 24px;
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 20px;
                text-align: center;
            }
        }

        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            font-weight: normal;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        /* ===== VERSION MOBILE (CARTES) ===== */
        .orders-mobile {
            display: block;
        }

        .order-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 15px;
            border-left: 4px solid #d40000;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .order-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .order-id {
            font-weight: bold;
            color: #d40000;
            font-size: 16px;
        }

        .order-date {
            color: #666;
            font-size: 13px;
            text-align: right;
        }

        .order-info {
            margin-bottom: 12px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }

        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 13px;
            min-width: 70px;
        }

        .info-value {
            flex: 1;
            text-align: right;
            font-size: 13px;
            word-break: break-word;
            padding-left: 10px;
        }

        /* ===== STATUTS MOBILE ===== */
        .mobile-status {
            text-align: center;
            margin: 15px 0;
            padding: 12px;
            background: rgba(212, 0, 0, 0.05);
            border-radius: 8px;
        }

        .status-text {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            min-width: 100px;
        }

        .status-en_attente { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-payee { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-expediee { background: #d1ecf1; color: #0c5460; border: 1px solid #b8daff; }
        .status-annulee { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* ===== ACTIONS MOBILE ===== */
        .order-actions-mobile {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .btn-mobile {
            padding: 10px 15px;
            background: #d40000;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            text-align: center;
            flex: 1;
            font-weight: 500;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-mobile:hover {
            background: #b30000;
        }

        .btn-mobile-success {
            background: #28a745;
        }

        .btn-mobile-success:hover {
            background: #218838;
        }

        .btn-mobile-primary {
            background: #007bff;
        }

        .btn-mobile-primary:hover {
            background: #0056b3;
        }

        .btn-mobile-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-mobile-warning:hover {
            background: #e0a800;
        }

        .btn-mobile-disabled {
            background: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-mobile-disabled:hover {
            background: #6c757d;
        }

        /* ===== ÉTATS ===== */
        .no-orders {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            font-style: italic;
            font-size: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
        }

        /* ===== OVERLAY MENU MOBILE ===== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 98;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        /* ===== VERSION ORDINATEUR ===== */
        @media (min-width: 1024px) {
            /* Header desktop */
            .header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 15px 25px;
            }

            .logo h1 {
                text-align: left;
                margin-bottom: 0;
                font-size: 22px;
            }

            .admin-info {
                flex-direction: row;
                text-align: left;
                gap: 15px;
            }

            /* Layout desktop */
            .container {
                flex-direction: row;
            }

            .mobile-menu-toggle {
                display: none;
            }

            .sidebar {
                display: block;
                position: static;
                width: 280px;
                height: auto;
                padding: 0;
                transform: none;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }

            .nav-item {
                padding: 18px 25px;
                font-size: 15px;
            }

            .main-content {
                padding: 25px;
                flex: 1;
                overflow-x: auto;
            }

            /* Statistiques desktop */
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 25px;
                margin-bottom: 30px;
            }

            .stat-card {
                padding: 30px 20px;
            }

            .stat-number {
                font-size: 32px;
            }

            .stat-label {
                font-size: 14px;
            }

            /* Sections desktop */
            .section {
                padding: 25px;
                margin-bottom: 25px;
            }

            .section h2 {
                font-size: 20px;
                margin-bottom: 20px;
            }

            /* Affichage conditionnel desktop/mobile */
            .table-container {
                display: block;
            }

            .orders-mobile {
                display: none;
            }
        }

        /* ===== AMÉLIORATIONS TRÈS PETITS ÉCRANS ===== */
        @media (max-width: 360px) {
            .main-content {
                padding: 12px;
            }

            .stat-card {
                padding: 18px 12px;
            }

            .stat-number {
                font-size: 22px;
            }

            .order-card {
                padding: 14px;
            }

            .btn-mobile {
                padding: 9px 12px;
                font-size: 12px;
            }

            .nav-item {
                padding: 14px 16px;
                font-size: 14px;
            }
        }

        /* ===== AMÉLIORATIONS ÉCRANS MOYENS ===== */
        @media (min-width: 768px) and (max-width: 1023px) {
            .main-content {
                padding: 20px;
            }

            .section {
                padding: 25px 20px;
            }

            .stat-card {
                padding: 25px 20px;
            }
        }

        /* ===== ANIMATIONS ET INTERACTIONS ===== */
        @media (hover: hover) {
            .stat-card:hover, .order-card:hover {
                transform: translateY(-2px);
            }
        }

        /* ===== ACCESSIBILITÉ ===== */
        @media (prefers-reduced-motion: reduce) {
            .sidebar, .sidebar-overlay, .stat-card, .order-card {
                transition: none;
            }
        }

        /* ===== IMPRESSION ===== */
        @media print {
            .sidebar, .mobile-menu-toggle, .btn-logout, .btn, .btn-mobile {
                display: none;
            }

            .container {
                flex-direction: column;
            }

            .main-content {
                padding: 0;
            }

            .stat-card, .section {
                box-shadow: none;
                border: 1px solid #ddd;
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
            <span>Connecté: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
            <a href="admin_dashboard.php?logout=1" class="btn-logout">Déconnexion</a>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        ☰ Menu Administration
    </button>

    <div class="container">
        <div class="sidebar" id="sidebar">
            <a href="admin_dashboard.php" class="nav-item">📊 Tableau de Bord</a>
            <a href="admin_commandes.php" class="nav-item">📦 Gestion des Commandes</a>
            <a href="admin_factures.php" class="nav-item active">📄 Gestion des Factures</a>
            <a href="admin_clients.php" class="nav-item">👥 Gestion des Clients</a>
            <a href="admin_produits.php" class="nav-item">🎨 Gestion des Produits</a>
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
                    <div class="stat-number"><?= count($commandes) ?></div>
                    <div class="stat-label">Total Factures</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?= count(array_filter($commandes, function($cmd) { return $cmd['statut'] === 'payee'; })) ?>
                    </div>
                    <div class="stat-label">Factures Payées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?= number_format(array_sum(array_column($commandes, 'montantTotal')), 2, ',', ' ') ?> €
                    </div>
                    <div class="stat-label">Chiffre d'Affaires</div>
                </div>
            </div>

            <div class="section">
                <h2>📋 Liste des Factures (<?= count($commandes) ?>)</h2>

                <?php if (empty($commandes)): ?>
                    <div class="no-orders">
                        Aucune facture disponible.
                    </div>
                <?php else: ?>

                <!-- Version Desktop (tableau) -->
                <div class="table-container">
                    <table>
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
                                        <?php if ($commande['statut'] === 'payee'): ?>
                                            <form method="POST" class="form-inline">
                                                <input type="hidden" name="id_commande" value="<?= $commande['idCommande'] ?>">
                                                <input type="hidden" name="email" value="<?= htmlspecialchars($commande['email']) ?>">
                                                <input type="hidden" name="action" value="envoyer_facture">
                                                <button type="submit" class="btn btn-success" onclick="return confirm('Envoyer la facture #<?= $commande['idCommande'] ?> à <?= htmlspecialchars($commande['email']) ?> ?')">
                                                    📧 Envoyer
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="tooltip">
                                                <button type="button" class="btn btn-disabled">
                                                    📧 Envoyer
                                                </button>
                                                <span class="tooltiptext">La facture ne peut être envoyée que pour les commandes payées</span>
                                            </div>
                                        <?php endif; ?>

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

                <!-- Version Mobile (cartes) -->
                <div class="orders-mobile">
                    <?php foreach ($commandes as $commande): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-id">#<?= $commande['idCommande'] ?></div>
                            <div class="order-date"><?= date('d/m/Y H:i', strtotime($commande['dateCommande'])) ?></div>
                        </div>

                        <div class="order-info">
                            <div class="info-row">
                                <span class="info-label">Client:</span>
                                <span class="info-value"><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?= htmlspecialchars($commande['email']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Montant:</span>
                                <span class="info-value"><strong><?= number_format($commande['montantTotal'], 2, ',', ' ') ?>€ TTC</strong></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Adresse:</span>
                                <span class="info-value"><?= htmlspecialchars($commande['adresse_facturation']) ?>, <?= htmlspecialchars($commande['ville_facturation']) ?></span>
                            </div>
                        </div>

                        <div class="mobile-status">
                            <div class="status-text">Statut de la commande</div>
                            <span class="status-badge status-<?= $commande['statut'] ?>">
                                <?= $commande['statut'] ?>
                            </span>
                        </div>

                        <div class="order-actions-mobile">
                            <a href="admin_factures.php?action=generer&id=<?= $commande['idCommande'] ?>" class="btn-mobile btn-mobile-primary" target="_blank">
                                <span>👁️</span>
                                <span>Voir</span>
                            </a>
                            <a href="generer_facture.php?id=<?= $commande['idCommande'] ?>" class="btn-mobile btn-mobile-warning" target="_blank">
                                <span>📄</span>
                                <span>PDF</span>
                            </a>

                            <?php if ($commande['statut'] === 'payee'): ?>
                                <form method="POST" class="form-inline" style="flex: 1;">
                                    <input type="hidden" name="id_commande" value="<?= $commande['idCommande'] ?>">
                                    <input type="hidden" name="email" value="<?= htmlspecialchars($commande['email']) ?>">
                                    <input type="hidden" name="action" value="envoyer_facture">
                                    <button type="submit" class="btn-mobile btn-mobile-success" onclick="return confirm('Envoyer la facture #<?= $commande['idCommande'] ?> à <?= htmlspecialchars($commande['email']) ?> ?')">
                                        <span>📧</span>
                                        <span>Envoyer</span>
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="tooltip" style="flex: 1;">
                                    <button type="button" class="btn-mobile btn-mobile-disabled">
                                        <span>📧</span>
                                        <span>Envoyer</span>
                                    </button>
                                    <span class="tooltiptext">La facture ne peut être envoyée que pour les commandes payées</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Gestion du menu mobile optimisée (identique à admin_dashboard.php)
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleMobileMenu() {
            const isActive = sidebar.classList.contains('active');
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = isActive ? '' : 'hidden';

            // Animation du bouton
            mobileMenuToggle.style.transform = isActive ? 'none' : 'scale(0.98)';
        }

        function closeMobileMenu() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
            mobileMenuToggle.style.transform = 'none';
        }

        mobileMenuToggle.addEventListener('click', toggleMobileMenu);
        sidebarOverlay.addEventListener('click', closeMobileMenu);

        // Fermer le menu en cliquant sur un lien (mobile seulement)
        sidebar.querySelectorAll('.nav-item').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    closeMobileMenu();
                }
            });
        });

        // Adapter au redimensionnement
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                closeMobileMenu();
            }
        });

        // Masquer le menu au chargement sur mobile
        window.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth < 1024) {
                closeMobileMenu();
            }
        });

        // Empêcher le scroll quand le menu est ouvert
        sidebar.addEventListener('touchmove', function(e) {
            if (sidebar.classList.contains('active')) {
                e.preventDefault();
            }
        }, { passive: false });

        // Amélioration de l'accessibilité
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                closeMobileMenu();
                mobileMenuToggle.focus();
            }
        });

        // Focus management pour l'accessibilité
        mobileMenuToggle.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleMobileMenu();
            }
        });

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

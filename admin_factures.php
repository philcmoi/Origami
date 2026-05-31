<?php
require_once 'admin_protection.php';
require_once 'config.php';
require_once 'smtp_config.php';

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur connexion: " . $e->getMessage());
}

function genererFactureHTML($pdo, $idCommande) {
    $stmt = $pdo->prepare("
        SELECT 
            c.idCommande, c.dateCommande, c.montantTotal, c.fraisDePort, c.statut,
            cl.nom, cl.prenom, cl.email, cl.telephone,
            a_liv.adresse as adresse_livraison, a_liv.codePostal as cp_livraison,
            a_liv.ville as ville_livraison, a_liv.pays as pays_livraison
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        JOIN Adresse a_liv ON c.idAdresseLivraison = a_liv.idAdresse
        WHERE c.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) return "<h1>Commande non trouvée</h1>";
    
    $stmt = $pdo->prepare("
        SELECT lc.quantite, lc.prixUnitaire, 
               (lc.quantite * lc.prixUnitaire) as total_ligne,
               o.nom as produit_nom
        FROM LigneCommande lc
        JOIN Origami o ON lc.idOrigami = o.idOrigami
        WHERE lc.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $sousTotal = 0;
    foreach ($articles as $article) {
        $sousTotal += $article['total_ligne'];
    }
    $totalGeneral = $sousTotal + ($commande['fraisDePort'] ?? 0);
    $nomComplet = trim($commande['prenom'] . ' ' . $commande['nom']);
    
    $html = '<!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"><title>Facture #' . $commande['idCommande'] . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Inter", Arial, sans-serif; margin: 0; padding: 20px; background: #f5f7fb; }
        .invoice-box { max-width: 800px; margin: auto; background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 2px solid #c0392b; padding-bottom: 20px; margin-bottom: 20px; }
        .company { font-size: 28px; font-weight: 700; color: #c0392b; }
        .company-sub { font-size: 12px; color: #6c757d; margin-top: 4px; }
        .invoice-title { font-size: 20px; font-weight: 600; margin: 20px 0 10px; }
        .info-section { display: flex; flex-wrap: wrap; gap: 20px; margin: 20px 0; }
        .info-box { flex: 1; background: #f8f9fa; padding: 15px; border-radius: 12px; min-width: 200px; }
        .info-box h3 { font-size: 12px; color: #c0392b; margin-bottom: 10px; text-transform: uppercase; }
        .info-box p { font-size: 13px; margin: 5px 0; color: #495057; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #c0392b; color: white; padding: 10px; text-align: left; font-size: 12px; }
        td { padding: 10px; border-bottom: 1px solid #e9ecef; font-size: 13px; }
        .total { text-align: right; margin-top: 20px; padding-top: 15px; border-top: 2px solid #e9ecef; }
        .total-amount { font-size: 20px; font-weight: 700; color: #c0392b; }
        .footer { text-align: center; font-size: 10px; color: #6c757d; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef; }
        @media print {
            body { background: white; padding: 0; }
            .invoice-box { box-shadow: none; padding: 0; }
        }
    </style>
    </head>
    <body>
        <div class="invoice-box">
            <div class="header">
                <div class="company">YOUKI & CO</div>
                <div class="company-sub">Créations artisanales japonaises</div>
                <div class="invoice-title">FACTURE N° ' . $commande['idCommande'] . '</div>
                <div style="font-size: 12px; color: #6c757d;">Date: ' . date('d/m/Y', strtotime($commande['dateCommande'])) . '</div>
            </div>
            
            <div class="info-section">
                <div class="info-box">
                    <h3>CLIENT</h3>
                    <p><strong>' . htmlspecialchars($nomComplet) . '</strong></p>
                    <p>' . htmlspecialchars($commande['email']) . '</p>
                    <p>' . htmlspecialchars($commande['telephone'] ?? '') . '</p>
                </div>
                <div class="info-box">
                    <h3>LIVRAISON</h3>
                    <p>' . nl2br(htmlspecialchars($commande['adresse_livraison'])) . '</p>
                    <p>' . htmlspecialchars($commande['cp_livraison'] . ' ' . $commande['ville_livraison']) . '</p>
                </div>
            </div>
            
            <table>
                <thead><tr><th>Produit</th><th>Prix unitaire</th><th>Quantité</th><th>Total</th></tr></thead>
                <tbody>';
    foreach ($articles as $article) {
        $html .= '<tr>
                    <td>' . htmlspecialchars($article['produit_nom']) . '</td>
                    <td>' . number_format($article['prixUnitaire'], 2, ',', ' ') . ' €</td>
                    <td>' . $article['quantite'] . '</td>
                    <td>' . number_format($article['total_ligne'], 2, ',', ' ') . ' €</td>
                </tr>';
    }
    $html .= '</tbody>
            </table>
            
            <div class="total">
                <p>Sous-total : ' . number_format($sousTotal, 2, ',', ' ') . ' €</p>
                <p>Frais de port : ' . number_format($commande['fraisDePort'] ?? 0, 2, ',', ' ') . ' €</p>
                <p class="total-amount">TOTAL : ' . number_format($totalGeneral, 2, ',', ' ') . ' €</p>
            </div>
            
            <div class="footer">
                <p>Exonération de TVA - Article 293 B du CGI</p>
                <p>Youki and Co - contact@youkiandco.fr</p>
            </div>
        </div>
    </body>
    </html>';
    return $html;
}

function envoyerEmailFacture($destinataire, $sujet, $message, $factureHTML = '') {
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
        
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        
        $fullMessage = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif;">
            <div style="max-width: 600px; margin: 0 auto;">
                <div style="background: #c0392b; color: white; padding: 20px; text-align: center;">
                    <h1 style="margin: 0;">YOUKI & CO</h1>
                </div>
                <div style="padding: 20px;">
                    ' . $message . '
                    <hr style="margin: 20px 0;">
                    ' . $factureHTML . '
                </div>
                <div style="background: #f8f9fa; padding: 15px; text-align: center; font-size: 11px; color: #6c757d;">
                    Youki and Co - contact@youkiandco.fr
                </div>
            </div>
        </body>
        </html>';
        
        $mail->Body = $fullMessage;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'envoyer_facture') {
    $idCommande = $_POST['id_commande'] ?? null;
    $email = $_POST['email'] ?? '';
    
    if ($idCommande && $email) {
        $stmt = $pdo->prepare("SELECT statut FROM Commande WHERE idCommande = ?");
        $stmt->execute([$idCommande]);
        $commandeCheck = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($commandeCheck && $commandeCheck['statut'] === 'payee') {
            $factureHTML = genererFactureHTML($pdo, $idCommande);
            $sujet = "Votre facture Youki and Co - Commande #$idCommande";
            $message = "<p>Bonjour,</p><p>Merci pour votre commande. Veuillez trouver ci-dessous votre facture.</p>";
            $resultat = envoyerEmailFacture($email, $sujet, $message, $factureHTML);
            
            if ($resultat['success']) {
                $_SESSION['message_success'] = "✅ Facture #$idCommande envoyée avec succès à $email";
            } else {
                $_SESSION['message_error'] = "❌ Erreur: " . $resultat['error'];
            }
        } else {
            $_SESSION['message_error'] = "❌ La commande #$idCommande n'est pas payée";
        }
    }
    header('Location: admin_factures.php');
    exit;
}

// Action GET
if (isset($_GET['action']) && $_GET['action'] === 'generer' && isset($_GET['id'])) {
    echo genererFactureHTML($pdo, (int)$_GET['id']);
    exit;
}

// Récupérer les commandes
$search = $_GET['search'] ?? '';
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(cl.nom LIKE ? OR cl.prenom LIKE ? OR cl.email LIKE ? OR c.idCommande LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$stmt = $pdo->prepare("
    SELECT 
        c.idCommande, c.dateCommande, c.montantTotal, c.statut,
        cl.nom, cl.prenom, cl.email,
        (SELECT COUNT(*) FROM LigneCommande WHERE idCommande = c.idCommande) as nb_articles
    FROM Commande c
    JOIN Client cl ON c.idClient = cl.idClient
    $whereClause
    ORDER BY c.dateCommande DESC
");
$stmt->execute($params);
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalCommandes = count($commandes);
$totalPayees = count(array_filter($commandes, function($c) { return $c['statut'] === 'payee'; }));
$caTotal = array_sum(array_column($commandes, 'montantTotal'));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Factures - Youki and Co</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary: #c0392b;
            --primary-dark: #a93226;
            --gray-50: #f8f9fa;
            --gray-100: #f1f3f5;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --success: #27ae60;
            --success-light: #d4edda;
            --warning-light: #fff3cd;
            --danger: #e74c3c;
            --danger-light: #fef3f2;
            --info: #3498db;
            --info-light: #d1ecf1;
            --border-radius: 12px;
            --card-shadow-hover: 0 4px 12px rgba(0,0,0,0.1);
            --transition: all 0.2s ease;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.5;
        }

        .app { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--gray-200);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 50;
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; z-index: 100; transition: var(--transition); }
            .sidebar.open { transform: translateX(0); }
        }
        
        .sidebar-header { padding: 20px; border-bottom: 1px solid var(--gray-200); }
        .sidebar-header h2 { font-size: 1.25rem; font-weight: 700; color: var(--primary); }
        .sidebar-header p { font-size: 0.7rem; color: var(--gray-500); margin-top: 4px; }
        
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
            transition: var(--transition);
            font-size: 0.875rem;
            font-weight: 500;
        }
        .nav-item i { width: 20px; color: var(--gray-500); font-size: 1rem; }
        .nav-item:hover { background: var(--gray-100); color: var(--primary); }
        .nav-item:hover i { color: var(--primary); }
        .nav-item.active { background: var(--primary); color: white; }
        .nav-item.active i { color: white; }
        
        /* Main content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            min-height: 100vh;
        }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
        
        /* Top bar */
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
        
        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 8px;
            color: var(--gray-700);
        }
        @media (max-width: 768px) { .menu-toggle { display: block; } }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .back-link:hover {
            background: var(--gray-200);
            color: var(--primary);
        }
        
        .back-link i { font-size: 0.75rem; }
        
        .page-title h1 { font-size: 1.25rem; font-weight: 600; color: var(--gray-800); }
        .page-title p { font-size: 0.75rem; color: var(--gray-500); margin-top: 2px; }
        
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
            transition: var(--transition);
        }
        .btn-logout:hover { background: var(--gray-200); color: var(--danger); }
        
        .content-wrapper { padding: 24px; }
        @media (max-width: 640px) { .content-wrapper { padding: 16px; } }
        
        /* Stats cards - PAS de scroll ici */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        
        @media (max-width: 560px) {
            .stats-grid { grid-template-columns: 1fr; gap: 12px; }
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            border: 1px solid var(--gray-200);
            transition: var(--transition);
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            background: var(--gray-100);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
        }
        
        .stat-icon i { font-size: 1.25rem; color: var(--primary); }
        
        .stat-number { font-size: 1.75rem; font-weight: 700; color: var(--gray-800); margin-bottom: 4px; }
        .stat-label { font-size: 0.7rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 500; }
        
        /* Filters bar */
        .filters-bar {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            border: 1px solid var(--gray-200);
            margin-bottom: 24px;
        }
        
        .search-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid var(--gray-300);
            border-radius: 10px;
            font-size: 0.85rem;
            font-family: inherit;
            transition: var(--transition);
            background: var(--gray-50);
        }
        
        .search-input:focus {
            border-color: var(--primary);
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(192,57,43,0.1);
        }
        
        .btn-search, .btn-reset {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-search {
            background: var(--primary);
            color: white;
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-reset {
            background: var(--gray-100);
            color: var(--gray-700);
            text-decoration: none;
        }
        
        .btn-reset:hover {
            background: var(--gray-200);
        }
        
        /* Messages */
        .message-success, .message-error {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-success { background: var(--success-light); color: #155724; border-left: 3px solid var(--success); }
        .message-error { background: var(--danger-light); color: var(--danger); border-left: 3px solid var(--danger); }
        
        /* Section factures */
        .section {
            background: white;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .section-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--gray-200);
            background: white;
        }
        
        .section-header h2 {
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-800);
        }
        
        .section-header h2 i { color: var(--primary); }
        
        /* ============================================
           SCROLL HORIZONTAL UNIQUEMENT POUR LE TABLEAU
           ============================================ */
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }
        
        .table-wrapper::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-wrapper::-webkit-scrollbar-track {
            background: var(--gray-200);
            border-radius: 10px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
        
        /* Indicateur visuel pour mobile */
        .scroll-hint {
            display: none;
            text-align: center;
            padding: 10px;
            background: var(--gray-50);
            font-size: 0.7rem;
            color: var(--gray-500);
            border-bottom: 1px solid var(--gray-200);
        }
        
        @media (max-width: 768px) {
            .scroll-hint { display: block; }
        }
        
        /* Le tableau a une largeur minimale pour forcer le scroll */
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        
        th {
            background: var(--gray-50);
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-600);
        }
        
        td {
            font-size: 0.8rem;
            color: var(--gray-700);
        }
        
        /* Statut badges */
        .statut {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .statut-payee { background: var(--success-light); color: #155724; }
        .statut-en_attente_paiement { background: var(--warning-light); color: #856404; }
        .statut-expediee { background: var(--info-light); color: #0c5460; }
        .statut-livree { background: var(--danger-light); color: var(--primary); }
        .statut-annulee { background: var(--gray-200); color: var(--gray-600); }
        
        /* Buttons */
        .btn {
            padding: 6px 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #219a52;
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background: var(--info);
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }
        
        .btn-disabled {
            background: var(--gray-400);
            color: var(--gray-600);
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .actions-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }
        
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 220px;
            background: var(--gray-800);
            color: white;
            text-align: center;
            border-radius: 8px;
            padding: 6px 10px;
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.7rem;
            z-index: 100;
            white-space: normal;
            pointer-events: none;
        }
        
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 0.7rem;
            color: var(--gray-500);
            border-top: 1px solid var(--gray-200);
            margin-top: 24px;
        }
        
        /* Overlay mobile */
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
        
        @media (max-width: 768px) {
            .sidebar-overlay.active { display: block; }
        }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Youki & Co</h2>
                <p>Administration</p>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item"><i class="fas fa-chart-pie"></i> Tableau de bord</a>
                <a href="admin_commandes.php" class="nav-item"><i class="fas fa-shopping-cart"></i> Commandes</a>
                <a href="admin_factures.php" class="nav-item active"><i class="fas fa-file-invoice"></i> Factures</a>
                <a href="admin_clients.php" class="nav-item"><i class="fas fa-users"></i> Clients</a>
                <a href="admin_produits.php" class="nav-item"><i class="fas fa-box"></i> Produits</a>
            </nav>
        </aside>
        
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <main class="main-content">
            <div class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                    <a href="dashboard.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                    </a>
                    <div class="page-title">
                        <h1>Gestion des factures</h1>
                        <p><?= $totalCommandes ?> facture(s) au total</p>
                    </div>
                </div>
                <div class="user-info">
                    <span class="user-email"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['admin_email'] ?? 'Admin') ?></span>
                    <a href="dashboard.php?logout=1" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                </div>
            </div>
            
            <div class="content-wrapper">
                <!-- Messages -->
                <?php if (isset($_SESSION['message_success'])): ?>
                    <div class="message-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['message_success']; unset($_SESSION['message_success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['message_error'])): ?>
                    <div class="message-error"><i class="fas fa-exclamation-triangle"></i> <?= $_SESSION['message_error']; unset($_SESSION['message_error']); ?></div>
                <?php endif; ?>
                
                <!-- Statistiques (PAS de scroll) -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
                        <div class="stat-number"><?= $totalCommandes ?></div>
                        <div class="stat-label">Total factures</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-number"><?= $totalPayees ?></div>
                        <div class="stat-label">Factures payées</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-euro-sign"></i></div>
                        <div class="stat-number"><?= number_format($caTotal, 0, ',', ' ') ?>€</div>
                        <div class="stat-label">Chiffre d'affaires</div>
                    </div>
                </div>
                
                <!-- Barre de recherche -->
                <div class="filters-bar">
                    <form method="GET" class="search-form">
                        <input type="text" name="search" class="search-input" placeholder="Rechercher par client, email ou numéro de commande..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-search"><i class="fas fa-search"></i> Rechercher</button>
                        <?php if (!empty($search)): ?>
                            <a href="admin_factures.php" class="btn-reset"><i class="fas fa-times"></i> Réinitialiser</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Liste des factures avec scroll horizontal UNIQUEMENT ici -->
                <div class="section">
                    <div class="section-header">
                        <h2><i class="fas fa-list"></i> Liste des factures</h2>
                    </div>
                    <div class="scroll-hint">
                        <i class="fas fa-arrows-alt-h"></i> Faites glisser pour voir plus de colonnes →
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>N° Commande</th>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Email</th>
                                    <th>Articles</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($commandes)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 60px;">
                                            <i class="fas fa-inbox" style="font-size: 2.5rem; color: var(--gray-400); margin-bottom: 12px; display: block;"></i>
                                            <p style="color: var(--gray-500);">Aucune commande trouvée</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($commandes as $commande): ?>
                                    <tr>
                                        <td><strong>#<?= $commande['idCommande'] ?></strong></td>
                                        <td><?= date('d/m/Y', strtotime($commande['dateCommande'])) ?></td>
                                        <td><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></td>
                                        <td style="font-size: 0.75rem; word-break: break-all;"><?= htmlspecialchars($commande['email']) ?></td>
                                        <td><?= $commande['nb_articles'] ?> article(s)</td>
                                        <td><strong><?= number_format($commande['montantTotal'], 2, ',', ' ') ?> €</strong></td>
                                        <td>
                                            <span class="statut statut-<?= $commande['statut'] ?>">
                                                <?php 
                                                $statuts = [
                                                    'payee' => 'Payée',
                                                    'en_attente_paiement' => 'En attente',
                                                    'expediee' => 'Expédiée',
                                                    'livree' => 'Livrée',
                                                    'annulee' => 'Annulée'
                                                ];
                                                echo $statuts[$commande['statut']] ?? $commande['statut'];
                                                ?>
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <?php if ($commande['statut'] === 'payee'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="id_commande" value="<?= $commande['idCommande'] ?>">
                                                    <input type="hidden" name="email" value="<?= htmlspecialchars($commande['email']) ?>">
                                                    <input type="hidden" name="action" value="envoyer_facture">
                                                    <button type="submit" class="btn btn-success" onclick="return confirm('Envoyer la facture #<?= $commande['idCommande'] ?> à <?= htmlspecialchars($commande['email']) ?> ?')">
                                                        <i class="fas fa-envelope"></i> Envoyer
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <div class="tooltip">
                                                    <button class="btn btn-disabled" disabled>
                                                        <i class="fas fa-envelope"></i> Envoyer
                                                    </button>
                                                    <span class="tooltiptext">La facture ne peut être envoyée que pour les commandes payées</span>
                                                </div>
                                            <?php endif; ?>
                                            <a href="admin_factures.php?action=generer&id=<?= $commande['idCommande'] ?>" class="btn btn-primary" target="_blank">
                                                <i class="fas fa-eye"></i> Voir
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="footer">
                <p>&copy; <?= date('Y') ?> Youki and Co - Créations artisanales japonaises</p>
            </div>
        </main>
    </div>
    
    <script>
        // Menu mobile toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            });
        }
        
        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            });
        }
        
        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.message-success, .message-error').forEach(el => {
                el.style.transition = 'opacity 0.5s';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
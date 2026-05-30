<?php
// telecharger-facture.php - Téléchargement automatique de la facture
// Version HTML - Fonctionne immédiatement sans bibliothèque externe
// La mention "TTC" a été supprimée du total

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration BDD
define('DB_HOST', 'localhost');
define('DB_NAME', 'origami');
define('DB_USER', 'Philippe');
define('DB_PASS', 'l@99339R');

// Fonction de connexion PDO
function getConnexionBDLocale() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erreur connexion BDD: " . $e->getMessage());
        return null;
    }
}

// Récupérer et valider l'ID commande
$commande_id = isset($_GET['commande_id']) ? intval($_GET['commande_id']) : 0;

if ($commande_id <= 0) {
    die('❌ ID commande invalide');
}

$pdo = getConnexionBDLocale();
if (!$pdo) {
    die('❌ Erreur de connexion à la base de données');
}

try {
    // ============================================
    // VÉRIFICATION DE LA COMMANDE
    // ============================================
    
    $stmt = $pdo->prepare("
        SELECT 
            c.idCommande,
            c.dateCommande,
            c.montantTotal,
            c.fraisDePort,
            c.modeReglement,
            c.statut,
            cl.idClient,
            cl.email,
            cl.nom as client_nom,
            cl.prenom as client_prenom,
            a.idAdresse,
            a.nom as livraison_nom,
            a.prenom as livraison_prenom,
            a.adresse as livraison_adresse,
            a.codePostal as livraison_codePostal,
            a.ville as livraison_ville,
            a.pays as livraison_pays,
            a.telephone as livraison_telephone
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        JOIN Adresse a ON c.idAdresseLivraison = a.idAdresse
        WHERE c.idCommande = ?
    ");
    $stmt->execute([$commande_id]);
    $commande = $stmt->fetch();
    
    if (!$commande) {
        die('❌ Commande #' . $commande_id . ' non trouvée');
    }
    
    // Vérification d'autorisation
    $autorise = false;
    
    if (isset($_SESSION['client_id']) && $_SESSION['client_id'] == $commande['idClient']) {
        $autorise = true;
    } elseif (isset($_SESSION['client_email']) && $_SESSION['client_email'] == $commande['email']) {
        $autorise = true;
    } elseif (isset($_SESSION['commande_recente']) && $_SESSION['commande_recente'] == $commande_id) {
        $autorise = true;
    } elseif (isset($_GET['token']) && !empty($_GET['token'])) {
        $autorise = true;
    } elseif (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0) {
        $autorise = true;
    }
    
    if (!$autorise) {
        die('❌ Accès non autorisé à cette facture.');
    }
    
    // ============================================
    // RÉCUPÉRATION DES ARTICLES
    // ============================================
    
    $stmt = $pdo->prepare("
        SELECT 
            lc.idLigneCommande,
            lc.idOrigami,
            lc.quantite,
            lc.prixUnitaire,
            o.nom as produit_nom,
            o.description as produit_description
        FROM LigneCommande lc
        JOIN Origami o ON lc.idOrigami = o.idOrigami
        WHERE lc.idCommande = ?
    ");
    $stmt->execute([$commande_id]);
    $items = $stmt->fetchAll();
    
    if (empty($items)) {
        die('❌ Aucun article trouvé pour cette commande');
    }
    
    // ============================================
    // CALCUL DES TOTAUX
    // ============================================
    
    $sous_total = 0;
    foreach ($items as $item) {
        $sous_total += $item['quantite'] * $item['prixUnitaire'];
    }
    $frais_livraison = floatval($commande['fraisDePort'] ?? 0);
    $total_general = $sous_total + $frais_livraison;
    
    // Nom du client
    $client_nom_complet = trim(($commande['client_prenom'] ?? '') . ' ' . ($commande['client_nom'] ?? ''));
    if (empty($client_nom_complet)) {
        $client_nom_complet = ($commande['livraison_prenom'] ?? '') . ' ' . ($commande['livraison_nom'] ?? '');
    }
    
    // Calcul TVA (20%) pour information
    $taux_tva = 20;
    $montant_ht = $sous_total / (1 + $taux_tva / 100);
    $montant_tva = $sous_total - $montant_ht;
    
    // ============================================
    // GÉNÉRATION DU CONTENU HTML DE LA FACTURE
    // ============================================
    
    $html_content = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture #' . $commande_id . ' - Youki and Co</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            background: #f5f5f5;
            padding: 40px 20px;
        }
        .invoice {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .invoice-header {
            background: #2c3e50;
            color: white;
            padding: 35px 40px;
            text-align: center;
        }
        .invoice-header h1 {
            font-size: 32px;
            font-weight: 300;
            letter-spacing: 3px;
            margin-bottom: 8px;
        }
        .invoice-header p {
            font-size: 13px;
            opacity: 0.8;
        }
        .invoice-body {
            padding: 40px;
        }
        .invoice-title {
            text-align: center;
            margin-bottom: 35px;
        }
        .invoice-title h2 {
            font-size: 26px;
            color: #e74c3c;
            margin-bottom: 5px;
        }
        .invoice-title .invoice-number {
            color: #7f8c8d;
            font-size: 13px;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 35px;
            flex-wrap: wrap;
            gap: 20px;
        }
        .info-box {
            flex: 1;
            background: #f8f9fa;
            padding: 18px 20px;
            border-radius: 8px;
            border-left: 3px solid #e74c3c;
        }
        .info-box h3 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #7f8c8d;
            margin-bottom: 12px;
        }
        .info-box p {
            font-size: 14px;
            color: #2c3e50;
            margin-bottom: 5px;
            line-height: 1.5;
        }
        .info-box p:last-child {
            margin-bottom: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        th {
            background: #f8f9fa;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #2c3e50;
            border-bottom: 2px solid #e0e0e0;
        }
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            color: #555;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals {
            text-align: right;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        .totals p {
            margin: 8px 0;
            font-size: 14px;
            color: #555;
        }
        .grand-total {
            font-size: 20px;
            font-weight: bold;
            color: #e74c3c;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 2px solid #e0e0e0;
        }
        .legal-mention {
            margin: 30px 0 20px;
            padding: 15px;
            background: #f8f9fa;
            text-align: center;
            font-size: 11px;
            color: #7f8c8d;
            border-radius: 6px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            border-top: 1px solid #f0f0f0;
            font-size: 11px;
            color: #95a5a6;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .invoice {
                box-shadow: none;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="invoice">
        <div class="invoice-header">
            <h1>YOUKI & CO</h1>
            <p>Créations artisanales japonaises d\'origami</p>
        </div>
        
        <div class="invoice-body">
            <div class="invoice-title">
                <h2>FACTURE</h2>
                <div class="invoice-number">N° ' . $commande_id . ' | Date: ' . date('d/m/Y', strtotime($commande['dateCommande'] ?? 'now')) . '</div>
            </div>
            
            <div class="info-section">
                <div class="info-box">
                    <h3>CLIENT</h3>
                    <p><strong>' . htmlspecialchars($client_nom_complet) . '</strong></p>
                    <p>' . htmlspecialchars($commande['email']) . '</p>
                    ' . (!empty($commande['livraison_telephone']) ? '<p>Tél: ' . htmlspecialchars($commande['livraison_telephone']) . '</p>' : '') . '
                </div>
                <div class="info-box">
                    <h3>ADRESSE DE LIVRAISON</h3>
                    <p>' . htmlspecialchars($commande['livraison_prenom'] . ' ' . $commande['livraison_nom']) . '</p>
                    <p>' . htmlspecialchars($commande['livraison_adresse']) . '</p>
                    <p>' . htmlspecialchars($commande['livraison_codePostal'] . ' ' . $commande['livraison_ville']) . '</p>
                    <p>' . htmlspecialchars($commande['livraison_pays'] ?? 'France') . '</p>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>DÉSIGNATION</th>
                        <th class="text-right">PRIX UNIT.</th>
                        <th class="text-center">QTÉ</th>
                        <th class="text-right">TOTAL</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($items as $item) {
        $total_ligne = $item['quantite'] * $item['prixUnitaire'];
        $html_content .= '
                    <tr>
                        <td>' . htmlspecialchars($item['produit_nom']) . '</td>
                        <td class="text-right">' . number_format($item['prixUnitaire'], 2, ',', ' ') . ' €</td>
                        <td class="text-center">' . $item['quantite'] . '</td>
                        <td class="text-right">' . number_format($total_ligne, 2, ',', ' ') . ' €</td>
                    </tr>';
    }
    
    $html_content .= '
                </tbody>
            </table>
            
            <div class="totals">
                <p>Sous-total : ' . number_format($sous_total, 2, ',', ' ') . ' €</p>
                <p>Frais de port : ' . number_format($frais_livraison, 2, ',', ' ') . ' €</p>
                <div style="margin: 5px 0; font-size: 11px; color: #95a5a6;">
                    <small>dont TVA (20%) : ' . number_format($montant_tva, 2, ',', ' ') . ' €</small>
                </div>
                <p class="grand-total">TOTAL : ' . number_format($total_general, 2, ',', ' ') . ' €</p>
            </div>
            
            <div class="legal-mention">
                <strong>Exonération de TVA - Article 293 B du Code Général des Impôts</strong><br>
                SIRET: 123 456 789 00012 - RCS Paris
            </div>
            
            <div class="footer">
                Youki & Co - contact@youkiandco.fr<br>
                Facture générée le ' . date('d/m/Y à H:i') . '
            </div>
        </div>
    </div>
</body>
</html>';
    
    // ============================================
    // FORCER LE TÉLÉCHARGEMENT DU FICHIER HTML
    // ============================================
    
    $filename = 'facture_' . $commande_id . '_' . date('Ymd') . '.html';
    
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($html_content));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    echo $html_content;
    exit;
    
} catch (Exception $e) {
    error_log("Erreur telecharger-facture.php: " . $e->getMessage());
    die('❌ Erreur technique: ' . $e->getMessage());
}
?>
<?php
require_once 'admin_protection.php';

// Configuration de la base de données
$host = '217.182.198.20';
$dbname = 'origami';
$username = 'root';
$password = 'L099339R';

// Vérifier si l'ID de commande est passé en paramètre
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID de commande non spécifié");
}

$idCommande = $_GET['id'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer les informations de la commande
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            cl.nom as client_nom,
            cl.prenom as client_prenom,
            cl.email as client_email,
            cl.telephone as client_telephone,
            al.adresse as adresse_livraison,
            al.codePostal as codePostal_livraison,
            al.ville as ville_livraison,
            al.pays as pays_livraison,
            af.adresse as adresse_facturation,
            af.codePostal as codePostal_facturation,
            af.ville as ville_facturation,
            af.pays as pays_facturation
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        JOIN Adresse al ON c.idAdresseLivraison = al.idAdresse
        JOIN Adresse af ON c.idAdresseFacturation = af.idAdresse
        WHERE c.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        die("Commande non trouvée");
    }
    
    // Récupérer les articles de la commande
    $stmt = $pdo->prepare("
        SELECT lc.*, o.nom as produit_nom, o.description as produit_description
        FROM LigneCommande lc
        JOIN Origami o ON lc.idOrigami = o.idOrigami
        WHERE lc.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $lignesCommande = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Vérifier si TCPDF est disponible
$tcpdf_path = 'tcpdf/tcpdf.php';
if (file_exists($tcpdf_path)) {
    generatePDFInvoice($commande, $lignesCommande);
} else {
    generateHTMLInvoice($commande, $lignesCommande);
}

function generatePDFInvoice($commande, $lignesCommande) {
    require_once('tcpdf/tcpdf.php');
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Yougi and Go');
    $pdf->SetAuthor('Yougi and Go');
    $pdf->SetTitle('Facture #' . $commande['idCommande']);
    
    $pdf->SetMargins(15, 25, 15);
    $pdf->AddPage();
    
    $html = generateInvoiceContent($commande, $lignesCommande);
    $pdf->writeHTML($html, true, false, true, false, '');
    
    $filename = 'facture_origamizen_' . $commande['idCommande'] . '.pdf';
    $pdf->Output($filename, 'D');
    exit;
}

function generateHTMLInvoice($commande, $lignesCommande) {
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Facture #' . $commande['idCommande'] . ' - Origami Zen</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 20px; 
                color: #333;
                line-height: 1.6;
                background: #f9f9f9;
            }
            .invoice-container {
                max-width: 900px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header { 
                border-bottom: 2px solid #d40000; 
                padding-bottom: 25px; 
                margin-bottom: 25px; 
            }
            .company-info { 
                color: #d40000; 
                font-size: 18px; 
                font-weight: bold;
                margin-bottom: 10px;
            }
            .invoice-title { 
                font-size: 24px; 
                font-weight: bold; 
                text-align: center; 
                margin: 20px 0; 
                color: #d40000;
            }
            .client-section {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
                margin: 25px 0;
            }
            .address-box {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #e9ecef;
            }
            .address-title {
                color: #d40000;
                font-weight: bold;
                margin-bottom: 15px;
                border-bottom: 1px solid #dee2e6;
                padding-bottom: 8px;
            }
            .address-line {
                margin-bottom: 8px;
                padding-left: 10px;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 25px 0; 
            }
            th, td { 
                padding: 12px 15px; 
                text-align: left; 
                border-bottom: 1px solid #ddd; 
            }
            th { 
                background: #d40000; 
                color: white; 
                font-weight: 600;
            }
            .total-row { 
                background: #f9f9f9; 
                font-weight: bold; 
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
                margin-bottom: 10px;
                padding: 8px 0;
            }
            .total-final {
                display: flex;
                justify-content: space-between;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 2px solid #d40000;
                font-size: 18px;
                color: #d40000;
            }
            .no-print { 
                text-align: center; 
                margin: 20px 0; 
                padding: 20px; 
                background: #fff3cd; 
                border: 1px solid #ffeaa7;
                border-radius: 8px;
            }
            .print-btn { 
                background: #d40000; 
                color: white; 
                padding: 12px 24px; 
                border: none; 
                border-radius: 5px; 
                cursor: pointer; 
                margin: 5px;
                font-size: 14px;
                transition: background 0.3s;
            }
            .print-btn:hover {
                background: #b30000;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
                text-align: center;
                color: #666;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="no-print">
            <strong>📄 Facture HTML - TCPDF non installé</strong><br>
            <button class="print-btn" onclick="window.print()">🖨️ Imprimer</button>
            <button class="print-btn" onclick="window.close()">❌ Fermer</button>
        </div>';
    
    echo '<div class="invoice-container">';
    echo generateInvoiceContent($commande, $lignesCommande);
    echo '</div>';
    
    echo '</body></html>';
    exit;
}

function generateInvoiceContent($commande, $lignesCommande) {
    $total = 0;
    
    foreach ($lignesCommande as $ligne) {
        $total += $ligne['prixUnitaire'] * $ligne['quantite'];
    }
    
    $totalGeneral = $total + $commande['fraisDePort'];
    
    $html = '
    <div class="header">
        <table>
            <tr>
                <td width="50%">
                    <div class="company-info">ORIGAMI ZEN</div>
                    <div>contact@origamizen.fr - SIRET: 123 456 789 00012</div>
                </td>
                <td width="50%" style="text-align: right;">
                    <div class="invoice-title">FACTURE</div>
                    <div><strong>N°: ' . $commande['idCommande'] . '</strong></div>
                    <div>Date: ' . date('d/m/Y', strtotime($commande['dateCommande'])) . '</div>
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-bottom: 20px;">
        <div style="font-weight: bold; margin-bottom: 10px; color: #d40000;">CLIENT</div>
        <div><strong>' . htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) . '</strong></div>
        <div>📧 ' . htmlspecialchars($commande['client_email']) . '</div>
        ' . ($commande['client_telephone'] ? '<div>📞 ' . htmlspecialchars($commande['client_telephone']) . '</div>' : '') . '
    </div>

    <div class="client-section">
        <div class="address-box">
            <div class="address-title">🏢 ADRESSE DE FACTURATION</div>
            <div class="address-line"><strong>' . htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) . '</strong></div>
            <div class="address-line">' . htmlspecialchars($commande['adresse_facturation']) . '</div>
            <div class="address-line">' . htmlspecialchars($commande['codePostal_facturation'] . ' ' . $commande['ville_facturation']) . '</div>
            <div class="address-line">' . htmlspecialchars($commande['pays_facturation']) . '</div>
        </div>
        
        <div class="address-box">
            <div class="address-title">📦 ADRESSE DE LIVRAISON</div>
            <div class="address-line"><strong>' . htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) . '</strong></div>
            <div class="address-line">' . htmlspecialchars($commande['adresse_livraison']) . '</div>
            <div class="address-line">' . htmlspecialchars($commande['codePostal_livraison'] . ' ' . $commande['ville_livraison']) . '</div>
            <div class="address-line">' . htmlspecialchars($commande['pays_livraison']) . '</div>
        </div>
    </div>

    <table>
        <tr>
            <th>Produit</th>
            <th>Prix unitaire</th>
            <th>Quantité</th>
            <th>Total</th>
        </tr>';
    
    foreach ($lignesCommande as $ligne) {
        $prixUnitaire = $ligne['prixUnitaire'];
        $sousTotal = $prixUnitaire * $ligne['quantite'];
        
        $html .= '
        <tr>
            <td>' . htmlspecialchars($ligne['produit_nom']) . '<br>
                <small style="color: #666;">' . htmlspecialchars(substr($ligne['produit_description'], 0, 100)) . '...</small>
            </td>
            <td>' . number_format($prixUnitaire, 2, ',', ' ') . ' €</td>
            <td>' . $ligne['quantite'] . '</td>
            <td><strong>' . number_format($sousTotal, 2, ',', ' ') . ' €</strong></td>
        </tr>';
    }
    
    $html .= '
    </table>

    <div class="total-section">
        <div class="total-line">
            <span>Sous-total produits:</span>
            <span>' . number_format($total, 2, ',', ' ') . ' €</span>
        </div>
        
        <div class="total-line">
            <span>Frais de port:</span>
            <span>' . number_format($commande['fraisDePort'], 2, ',', ' ') . ' €</span>
        </div>
        
        <div class="total-final">
            <span><strong>TOTAL</strong></span>
            <span><strong>' . number_format($totalGeneral, 2, ',', ' ') . ' €</strong></span>
        </div>
    </div>

    <div class="footer">
        Origami Zen - RCS Paris 123 456 789 - Exonération de TVA, art. 293 B du CGI<br>
        Facture générée le ' . date('d/m/Y à H:i') . '
    </div>';
    
    return $html;
}
?>
<?php
require_once 'admin_protection.php';
require_once 'config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID de commande non spécifié");
}

$idCommande = $_GET['id'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
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
    
    $stmt = $pdo->prepare("
        SELECT lc.*, o.nom as produit_nom, o.description as produit_description
        FROM LigneCommande lc
        JOIN Origami o ON lc.idOrigami = o.idOrigami
        WHERE lc.idCommande = ?
    ");
    $stmt->execute([$idCommande]);
    $lignesCommande = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erreur de connexion: " . $e->getMessage());
}

$tcpdf_path = 'tcpdf/tcpdf.php';
if (file_exists($tcpdf_path)) {
    generatePDFInvoice($commande, $lignesCommande);
} else {
    generateHTMLInvoice($commande, $lignesCommande);
}

function generatePDFInvoice($commande, $lignesCommande) {
    require_once('tcpdf/tcpdf.php');
    
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Youki & Co');
    $pdf->SetAuthor('Youki & Co');
    $pdf->SetTitle('Facture #' . $commande['idCommande']);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    $html = generateInvoiceContent($commande, $lignesCommande);
    $pdf->writeHTML($html, true, false, true, false, '');
    
    $pdf->Output('facture_YoukiAndCo_' . $commande['idCommande'] . '.pdf', 'D');
    exit;
}

function generateHTMLInvoice($commande, $lignesCommande) {
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Facture #' . $commande['idCommande'] . ' - Youki & Co</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 4px; padding: 40px; }
            .no-print { text-align: center; margin-bottom: 20px; }
            .print-btn { background: #333; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin: 0 5px; }
            .print-btn:hover { background: #444; }
        </style>
    </head>
    <body>
        <div class="no-print">
            <button class="print-btn" onclick="window.print()">🖨️ Imprimer</button>
            <button class="print-btn" onclick="window.close()">❌ Fermer</button>
        </div>
        <div class="container">';
    
    echo generateInvoiceContent($commande, $lignesCommande);
    
    echo '</div></body></html>';
    exit;
}

function generateInvoiceContent($commande, $lignesCommande) {
    $total = 0;
    foreach ($lignesCommande as $ligne) {
        $total += $ligne['prixUnitaire'] * $ligne['quantite'];
    }
    $totalGeneral = $total + $commande['fraisDePort'];
    
    $html = '
    <style>
        body { font-family: helvetica; color: #333; }
        .header { margin-bottom: 30px; }
        .company-name { font-size: 28px; color: #333; letter-spacing: 2px; }
        .company-details { color: #666; font-size: 11px; margin-top: 5px; }
        .invoice-info { text-align: right; }
        .invoice-title { font-size: 20px; color: #333; margin-bottom: 5px; }
        .address-section { display: flex; gap: 20px; margin: 20px 0; }
        .address-box { flex: 1; background: #fafafa; padding: 15px; border: 1px solid #f0f0f0; }
        .address-label { font-weight: bold; color: #555; margin-bottom: 10px; font-size: 11px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #f5f5f5; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 500; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        .totals { text-align: right; margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f0; }
        .grand-total { font-size: 16px; font-weight: bold; margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd; }
        .footer { margin-top: 40px; text-align: center; color: #999; font-size: 10px; border-top: 1px solid #f0f0f0; padding-top: 20px; }
    </style>
    
    <div class="header">
        <table width="100%">
            <tr>
                <td width="60%">
                    <div class="company-name">YOUKI & CO</div>
                    <div class="company-details">contact@youkiandco.fr - SIRET: 123 456 789 00012</div>
                </td>
                <td width="40%" class="invoice-info">
                    <div class="invoice-title">FACTURE</div>
                    <div><strong>N°: ' . $commande['idCommande'] . '</strong></div>
                    <div>Date: ' . date('d/m/Y', strtotime($commande['dateCommande'])) . '</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="address-section">
        <div class="address-box">
            <div class="address-label">CLIENT</div>
            ' . htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) . '<br>
            ' . htmlspecialchars($commande['client_email']) . '<br>
            ' . ($commande['client_telephone'] ? htmlspecialchars($commande['client_telephone']) : '') . '
        </div>
        <div class="address-box">
            <div class="address-label">ADRESSE DE FACTURATION</div>
            ' . htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) . '<br>
            ' . htmlspecialchars($commande['adresse_facturation']) . '<br>
            ' . htmlspecialchars($commande['codePostal_facturation'] . ' ' . $commande['ville_facturation']) . '<br>
            ' . htmlspecialchars($commande['pays_facturation']) . '
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
        $sousTotal = $ligne['prixUnitaire'] * $ligne['quantite'];
        $html .= '
        <tr>
            <td>' . htmlspecialchars($ligne['produit_nom']) . '</td>
            <td>' . number_format($ligne['prixUnitaire'], 2, ',', ' ') . ' €</td>
            <td>' . $ligne['quantite'] . '</td>
            <td>' . number_format($sousTotal, 2, ',', ' ') . ' €</td>
        </tr>';
    }
    
    $html .= '
    </table>

    <div class="totals">
        <div>Sous-total: ' . number_format($total, 2, ',', ' ') . ' €</div>
        <div>Frais de port: ' . number_format($commande['fraisDePort'], 2, ',', ' ') . ' €</div>
        <div class="grand-total">TOTAL: ' . number_format($totalGeneral, 2, ',', ' ') . ' €</div>
    </div>

    <div style="background: #f9f9f9; padding: 10px; margin: 20px 0; text-align: center; color: #666; border: 1px solid #f0f0f0;">
        Exonération de TVA - Art. 293 B du CGI
    </div>

    <div class="footer">
        Youki & Co - RCS Paris 123 456 789 - Exonération de TVA, art. 293 B du CGI<br>
        Facture générée le ' . date('d/m/Y à H:i') . '
    </div>';
    
    return $html;
}
?>
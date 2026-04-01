<?php
// facture.php - Génération et affichage de la facture
error_log("🎯 facture.php - Génération facture");

require_once('tcpdf/tcpdf.php');
require_once 'config.php';

// Accepter les requêtes POST pour l'appel automatique
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $idCommande = $input['id_commande'] ?? null;
    
    if ($idCommande) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $fichierFacture = genererFacturePDF($pdo, $idCommande);
            
            if ($fichierFacture) {
                error_log("✅ Facture PDF créée: " . $fichierFacture);
                echo json_encode([
                    'status' => 'success',
                    'fichier_facture' => $fichierFacture,
                    'message' => 'Facture générée avec succès'
                ]);
            } else {
                throw new Exception("Échec de la génération du PDF");
            }
        } catch (Exception $e) {
            error_log("❌ Erreur génération facture: " . $e->getMessage());
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur connexion BD: " . $e->getMessage());
}

$idCommande = $_GET['id'] ?? $_POST['id_commande'] ?? null;

if (!$idCommande) {
    die("❌ ID de commande manquant");
}

/**
 * Génère une facture PDF avec colonnes calibrées
 */
function genererFacturePDF($pdo, $idCommande) {
    error_log("🔄 Génération PDF pour commande: " . $idCommande);
    
    try {
        // Récupérer les informations de la commande
        $stmt = $pdo->prepare("
            SELECT 
                c.idCommande,
                c.dateCommande,
                c.montantTotal,
                c.fraisDePort,
                c.statut,
                cl.nom as client_nom,
                cl.prenom as client_prenom,
                cl.email as client_email,
                cl.telephone as client_telephone,
                a_liv.adresse as adresse_livraison,
                a_liv.codePostal as cp_livraison,
                a_liv.ville as ville_livraison,
                a_liv.pays as pays_livraison,
                a_fact.adresse as adresse_facturation,
                a_fact.codePostal as cp_facturation,
                a_fact.ville as ville_facturation,
                a_fact.pays as pays_facturation
            FROM Commande c
            JOIN Client cl ON c.idClient = cl.idClient
            JOIN Adresse a_liv ON c.idAdresseLivraison = a_liv.idAdresse
            JOIN Adresse a_fact ON c.idAdresseFacturation = a_fact.idAdresse
            WHERE c.idCommande = ?
        ");
        $stmt->execute([$idCommande]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$commande) {
            throw new Exception("Commande non trouvée: " . $idCommande);
        }
        
        // Récupérer les articles
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
        
        // Calculer les totaux
        $sousTotal = 0;
        foreach ($articles as $article) {
            $sousTotal += $article['total_ligne'];
        }
        $totalGeneral = $sousTotal + $commande['fraisDePort'];
        
        // Création du PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        $pdf->SetCreator('Youki and Co');
        $pdf->SetAuthor('Youki and Co');
        $pdf->SetTitle('Facture #' . $idCommande);
        $pdf->SetSubject('Facture');
        
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(TRUE, 25);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        $pdf->AddPage();
        
        $nomCompletClient = ($commande['client_nom'] ?? '') . ' ' . ($commande['client_prenom'] ?? '');
        
        // HTML avec colonnes calibrées
        $html = '
        <style>
            body { font-family: helvetica; font-size: 10pt; color: #333; line-height: 1.4; margin: 0; padding: 0; }
            .header { margin-bottom: 35px; border-bottom: 1px solid #ddd; padding-bottom: 15px; }
            .company { font-size: 18pt; font-weight: 300; letter-spacing: 2px; color: #333; }
            .company-details { font-size: 8pt; color: #666; margin-top: 5px; }
            .invoice-title { font-size: 16pt; font-weight: 300; color: #333; }
            .invoice-number { font-size: 10pt; color: #666; }
            .client-label { font-size: 8pt; font-weight: bold; text-transform: uppercase; color: #999; margin-bottom: 5px; }
            .client-name { font-size: 11pt; font-weight: 500; margin-bottom: 3px; }
            .client-info { font-size: 9pt; color: #666; }
            .address-section { margin: 20px 0; }
            .address-table { width: 100%; margin: 15px 0; border-collapse: collapse; }
            .address-table td { width: 50%; vertical-align: top; padding: 5px 0; }
            .address-label { font-size: 8pt; font-weight: bold; text-transform: uppercase; color: #999; margin-bottom: 5px; }
            .address-content { font-size: 9pt; color: #666; line-height: 1.4; }
            .products-table { width: 100%; border-collapse: collapse; margin: 25px 0; }
            .products-table th { 
                background: #f8f8f8; 
                padding: 10px 8px; 
                text-align: left; 
                border-bottom: 1px solid #e0e0e0; 
                font-size: 9pt; 
                font-weight: 600; 
                color: #555;
            }
            .products-table td { padding: 10px 8px; border-bottom: 1px solid #f0f0f0; font-size: 9pt; }
            .col-produit { width: 50%; }
            .col-prix { width: 15%; text-align: right; }
            .col-qte { width: 10%; text-align: center; }
            .col-total { width: 25%; text-align: right; }
            .totals { text-align: right; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e0e0e0; }
            .grand-total { font-size: 11pt; font-weight: bold; margin-top: 8px; color: #333; }
            .legal { margin-top: 30px; padding: 10px; background: #fafafa; font-size: 7pt; color: #666; text-align: center; }
            .footer { margin-top: 30px; text-align: center; font-size: 7pt; color: #999; border-top: 1px solid #eee; padding-top: 15px; }
        </style>
        
        <div class="header">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="60%">
                        <div class="company">YOUKI & CO</div>
                        <div class="company-details">Créations artisanales japonaises</div>
                    </td>
                    <td width="40%" style="text-align: right;">
                        <div class="invoice-title">FACTURE</div>
                        <div class="invoice-number">N° ' . $idCommande . '</div>
                        <div class="invoice-number">' . date('d/m/Y', strtotime($commande['dateCommande'])) . '</div>
                    </td>
                </tr>
            </table>
        </div>
        
        <div>
            <div class="client-label">CLIENT</div>
            <div class="client-name">' . htmlspecialchars($nomCompletClient) . '</div>
            <div class="client-info">' . htmlspecialchars($commande['client_email']) . '</div>';
        
        if (!empty($commande['client_telephone'])) {
            $html .= '<div class="client-info">' . htmlspecialchars($commande['client_telephone']) . '</div>';
        }
        
        $html .= '</div>
        
        <div class="address-section">
            <table class="address-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <div class="address-label">LIVRAISON</div>
                        <div class="address-content">' . htmlspecialchars($commande['adresse_livraison']) . '<br>
                        ' . htmlspecialchars($commande['cp_livraison'] . ' ' . $commande['ville_livraison']) . '<br>
                        ' . htmlspecialchars($commande['pays_livraison']) . '</div>
                    </td>
                    <td>
                        <div class="address-label">FACTURATION</div>
                        <div class="address-content">' . htmlspecialchars($commande['adresse_facturation']) . '<br>
                        ' . htmlspecialchars($commande['cp_facturation'] . ' ' . $commande['ville_facturation']) . '<br>
                        ' . htmlspecialchars($commande['pays_facturation']) . '</div>
                    </td>
                </tr>
            </table>
        </div>
        
        <table class="products-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th class="col-produit">DÉSIGNATION</th>
                    <th class="col-prix">PRIX UNIT.</th>
                    <th class="col-qte">QTÉ</th>
                    <th class="col-total">TOTAL</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($articles as $article) {
            $html .= '
                <tr>
                    <td class="col-produit">' . htmlspecialchars($article['produit_nom']) . '</td>
                    <td class="col-prix">' . number_format($article['prixUnitaire'], 2, ',', ' ') . ' €</td>
                    <td class="col-qte">' . $article['quantite'] . '</td>
                    <td class="col-total">' . number_format($article['total_ligne'], 2, ',', ' ') . ' €</td>
                </tr>';
        }
        
        $html .= '
            </tbody>
        </table>
        
        <div class="totals">
            <div>Sous-total : ' . number_format($sousTotal, 2, ',', ' ') . ' €</div>
            <div>Frais de port : ' . number_format($commande['fraisDePort'], 2, ',', ' ') . ' €</div>
            <div class="grand-total">TOTAL : ' . number_format($totalGeneral, 2, ',', ' ') . ' €</div>
        </div>
        
        <div class="legal">
            Exonération de TVA - Article 293 B du Code Général des Impôts
        </div>
        
        <div class="footer">
            Youki & Co - contact@youkiandco.fr<br>
            Facture générée le ' . date('d/m/Y à H:i') . '
        </div>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Sauvegarder le PDF
        if (!is_dir('factures')) {
            mkdir('factures', 0755, true);
        }
        
        $filename = 'factures/facture_' . $idCommande . '.pdf';
        $pdf->Output(__DIR__ . '/' . $filename, 'F');
        
        if (file_exists($filename)) {
            error_log("✅ PDF créé: " . $filename);
            return $filename;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("❌ Erreur PDF: " . $e->getMessage());
        return false;
    }
}

/**
 * Affiche la facture HTML avec colonnes calibrées
 */
function afficherFactureHTML($pdo, $idCommande) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.idCommande,
                c.dateCommande,
                c.montantTotal,
                c.fraisDePort,
                c.statut,
                cl.nom as client_nom,
                cl.prenom as client_prenom,
                cl.email as client_email,
                a_fact.adresse as adresse_facturation,
                a_fact.codePostal as cp_facturation,
                a_fact.ville as ville_facturation,
                a_fact.pays as pays_facturation
            FROM Commande c
            JOIN Client cl ON c.idClient = cl.idClient
            JOIN Adresse a_fact ON c.idAdresseFacturation = a_fact.idAdresse
            WHERE c.idCommande = ?
        ");
        $stmt->execute([$idCommande]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$commande) {
            throw new Exception("Commande non trouvée");
        }
        
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
        
        $sousTotal = 0;
        foreach ($articles as $article) {
            $sousTotal += $article['total_ligne'];
        }
        $totalGeneral = $sousTotal + $commande['fraisDePort'];
        
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Facture #<?= $idCommande ?> - Youki & Co</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #f5f5f5;
                    padding: 30px 20px;
                }
                .container {
                    max-width: 900px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 4px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                }
                .invoice {
                    padding: 35px;
                }
                .header {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 35px;
                    border-bottom: 1px solid #eaeaea;
                    padding-bottom: 20px;
                }
                .company h1 {
                    font-size: 24px;
                    font-weight: 300;
                    letter-spacing: 2px;
                    color: #333;
                    margin-bottom: 5px;
                }
                .company p {
                    color: #888;
                    font-size: 11px;
                }
                .invoice-info {
                    text-align: right;
                }
                .invoice-info h2 {
                    font-size: 20px;
                    font-weight: 300;
                    color: #333;
                    margin-bottom: 5px;
                }
                .invoice-info p {
                    color: #888;
                    font-size: 12px;
                }
                .client-section {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 30px;
                    margin-bottom: 30px;
                }
                .address h3 {
                    font-size: 10px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    color: #aaa;
                    margin-bottom: 8px;
                    font-weight: 500;
                }
                .address p {
                    font-size: 13px;
                    color: #444;
                    line-height: 1.5;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 25px 0;
                }
                th {
                    text-align: left;
                    padding: 12px 8px;
                    border-bottom: 1px solid #eaeaea;
                    color: #888;
                    font-weight: 500;
                    font-size: 11px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                td {
                    padding: 12px 8px;
                    border-bottom: 1px solid #f0f0f0;
                    color: #444;
                    font-size: 13px;
                }
                .col-produit { width: 45%; }
                .col-prix { width: 20%; text-align: right; }
                .col-qte { width: 15%; text-align: center; }
                .col-total { width: 20%; text-align: right; }
                .totals {
                    text-align: right;
                    margin-top: 25px;
                    padding-top: 20px;
                    border-top: 1px solid #eaeaea;
                }
                .totals p {
                    margin-bottom: 6px;
                    color: #666;
                    font-size: 13px;
                }
                .grand-total {
                    font-size: 16px;
                    font-weight: 500;
                    color: #333;
                    margin-top: 8px;
                    padding-top: 8px;
                    border-top: 1px solid #ddd;
                }
                .legal-mention {
                    margin: 25px 0;
                    padding: 12px;
                    background: #fafafa;
                    color: #888;
                    font-size: 11px;
                    text-align: center;
                    border: 1px solid #f0f0f0;
                }
                .footer {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #eaeaea;
                    text-align: center;
                    color: #aaa;
                    font-size: 10px;
                }
                .actions {
                    margin-top: 30px;
                    text-align: center;
                }
                .btn {
                    display: inline-block;
                    padding: 8px 16px;
                    margin: 0 5px;
                    background: white;
                    border: 1px solid #ddd;
                    border-radius: 3px;
                    color: #555;
                    text-decoration: none;
                    font-size: 12px;
                    transition: all 0.2s;
                }
                .btn:hover {
                    background: #f8f8f8;
                    border-color: #ccc;
                }
                .btn-primary {
                    background: #333;
                    border-color: #333;
                    color: white;
                }
                .btn-primary:hover {
                    background: #444;
                }
                @media print {
                    body { background: white; padding: 0; }
                    .container { box-shadow: none; }
                    .actions { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="invoice">
                    <div class="header">
                        <div class="company">
                            <h1>YOUKI & CO</h1>
                            <p>Créations artisanales japonaises</p>
                        </div>
                        <div class="invoice-info">
                            <h2>FACTURE</h2>
                            <p>N° <?= $idCommande ?><br>Date: <?= date('d/m/Y', strtotime($commande['dateCommande'])) ?></p>
                        </div>
                    </div>

                    <div class="client-section">
                        <div class="address">
                            <h3>Client</h3>
                            <p><?= htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) ?><br>
                            <?= htmlspecialchars($commande['client_email']) ?></p>
                        </div>
                        <div class="address">
                            <h3>Adresse de facturation</h3>
                            <p><?= htmlspecialchars($commande['adresse_facturation']) ?><br>
                            <?= htmlspecialchars($commande['cp_facturation'] . ' ' . $commande['ville_facturation']) ?><br>
                            <?= htmlspecialchars($commande['pays_facturation']) ?></p>
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th class="col-produit">Désignation</th>
                                <th class="col-prix">Prix unitaire</th>
                                <th class="col-qte">Quantité</th>
                                <th class="col-total">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articles as $article): ?>
                            <tr>
                                <td class="col-produit"><?= htmlspecialchars($article['produit_nom']) ?></td>
                                <td class="col-prix"><?= number_format($article['prixUnitaire'], 2, ',', ' ') ?> €</td>
                                <td class="col-qte"><?= $article['quantite'] ?></td>
                                <td class="col-total"><?= number_format($article['total_ligne'], 2, ',', ' ') ?> €</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="totals">
                        <p>Sous-total : <?= number_format($sousTotal, 2, ',', ' ') ?> €</p>
                        <p>Frais de port : <?= number_format($commande['fraisDePort'], 2, ',', ' ') ?> €</p>
                        <p class="grand-total">TOTAL : <?= number_format($totalGeneral, 2, ',', ' ') ?> €</p>
                    </div>

                    <div class="legal-mention">
                        Exonération de TVA - Article 293 B du Code Général des Impôts
                    </div>

                    <div class="footer">
                        Youki & Co - contact@youkiandco.fr<br>
                        Facture générée le <?= date('d/m/Y à H:i') ?>
                    </div>

                    <div class="actions">
                        <a href="facture.php?format=pdf&id=<?= $idCommande ?>" class="btn btn-primary">📥 Télécharger PDF</a>
                        <a href="envoi.php?id=<?= $idCommande ?>" class="btn">📧 Envoyer par email</a>
                        <a href="index.html" class="btn">← Retour</a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        
    } catch (Exception $e) {
        echo "<div style='padding:20px;color:#721c24;background:#f8d7da'>Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Gestion des requêtes PDF
if (isset($_GET['format']) && $_GET['format'] === 'pdf') {
    $fichierPDF = genererFacturePDF($pdo, $idCommande);
    if ($fichierPDF && file_exists($fichierPDF)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="facture_' . $idCommande . '.pdf"');
        readfile($fichierPDF);
        exit;
    } else {
        die("Erreur lors de la génération du PDF");
    }
}

// Affichage HTML par défaut
afficherFactureHTML($pdo, $idCommande);
?>
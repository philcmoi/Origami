<?php
// facture.php - Génération et affichage de la facture
error_log("🎯 facture.php - Génération facture");

// Inclure TCPDF pour la génération de PDF
require_once('tcpdf/tcpdf.php');

// Configuration de la base de données
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
 * Génère une facture PDF sobre et professionnelle
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
                o.nom as produit_nom,
                o.description
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
        
        // Informations du document
        $pdf->SetCreator('Youki and Co');
        $pdf->SetAuthor('Youki and Co');
        $pdf->SetTitle('Facture #' . $idCommande);
        $pdf->SetSubject('Facture');
        
        // Marges
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);
        
        // Désactiver les en-têtes/pieds de page par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Style sobre et professionnel
        $html = '
        <style>
            body { font-family: helvetica; font-size: 10pt; line-height: 1.4; }
            h1 { font-size: 24pt; color: #333; margin: 0 0 5px 0; font-weight: normal; }
            h2 { font-size: 14pt; color: #555; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin: 20px 0 10px 0; }
            .header { margin-bottom: 30px; }
            .company-name { font-size: 28pt; color: #333; letter-spacing: 2px; }
            .company-details { color: #666; font-size: 9pt; margin-top: 5px; }
            .invoice-info { text-align: right; }
            .invoice-title { font-size: 18pt; color: #333; margin-bottom: 5px; }
            .invoice-number { font-size: 12pt; color: #666; }
            .address-box { border: 1px solid #eee; padding: 10px; margin: 5px 0; }
            .address-label { font-weight: bold; color: #555; margin-bottom: 5px; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th { background: #f5f5f5; color: #333; font-weight: bold; padding: 8px; text-align: left; border-bottom: 2px solid #ddd; }
            td { padding: 8px; border-bottom: 1px solid #eee; }
            .totals { text-align: right; margin-top: 20px; }
            .total-line { padding: 5px 0; }
            .grand-total { font-size: 12pt; font-weight: bold; border-top: 2px solid #333; padding-top: 10px; margin-top: 10px; }
            .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #999; font-size: 8pt; }
            .no-tva { background: #f9f9f9; padding: 10px; margin: 20px 0; border: 1px solid #eee; color: #666; font-size: 9pt; text-align: center; }
        </style>
        
        <!-- En-tête -->
        <table class="header" width="100%">
            <tr>
                <td width="60%">
                    <div class="company-name">YOUKI & CO</div>
                    <div class="company-details">
                        Créations artisanales japonaises<br>
                        contact@youkiandco.fr<br>
                        SIRET 123 456 789 00012
                    </div>
                </td>
                <td width="40%" class="invoice-info">
                    <div class="invoice-title">FACTURE</div>
                    <div class="invoice-number">N° ' . $idCommande . '</div>
                    <div style="color: #666; margin-top: 5px;">Date: ' . date('d/m/Y', strtotime($commande['dateCommande'])) . '</div>
                </td>
            </tr>
        </table>
        
        <!-- Client et adresses -->
        <table width="100%">
            <tr>
                <td width="33%" valign="top">
                    <div class="address-box">
                        <div class="address-label">CLIENT</div>
                        ' . htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) . '<br>
                        ' . htmlspecialchars($commande['client_email']) . '<br>';
        
        if ($commande['client_telephone']) {
            $html .= htmlspecialchars($commande['client_telephone']) . '<br>';
        }
        
        $html .= '
                    </div>
                </td>
                <td width="33%" valign="top">
                    <div class="address-box">
                        <div class="address-label">ADRESSE DE LIVRAISON</div>
                        ' . htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) . '<br>
                        ' . htmlspecialchars($commande['adresse_livraison']) . '<br>
                        ' . htmlspecialchars($commande['cp_livraison'] . ' ' . $commande['ville_livraison']) . '<br>
                        ' . htmlspecialchars($commande['pays_livraison']) . '
                    </div>
                </td>
                <td width="33%" valign="top">
                    <div class="address-box">
                        <div class="address-label">ADRESSE DE FACTURATION</div>
                        ' . htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) . '<br>
                        ' . htmlspecialchars($commande['adresse_facturation']) . '<br>
                        ' . htmlspecialchars($commande['cp_facturation'] . ' ' . $commande['ville_facturation']) . '<br>
                        ' . htmlspecialchars($commande['pays_facturation']) . '
                    </div>
                </td>
            </tr>
        </table>
        
        <h2>DÉTAIL DE LA COMMANDE</h2>
        
        <!-- Tableau des articles -->
        <table>
            <thead>
                <tr>
                    <th width="50%">Produit</th>
                    <th width="15%">Prix unitaire</th>
                    <th width="15%">Quantité</th>
                    <th width="20%">Total</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($articles as $article) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($article['produit_nom']) . '<br>
                        <small style="color: #999;">' . htmlspecialchars(substr($article['description'], 0, 60)) . '...</small>
                    </td>
                    <td>' . number_format($article['prixUnitaire'], 2, ',', ' ') . ' €</td>
                    <td>' . $article['quantite'] . '</td>
                    <td>' . number_format($article['total_ligne'], 2, ',', ' ') . ' €</td>
                </tr>';
        }
        
        $html .= '
            </tbody>
        </table>
        
        <!-- Totaux -->
        <div class="totals">
            <div class="total-line">Sous-total: ' . number_format($sousTotal, 2, ',', ' ') . ' €</div>
            <div class="total-line">Frais de port: ' . number_format($commande['fraisDePort'], 2, ',', ' ') . ' €</div>
            <div class="grand-total">TOTAL: ' . number_format($totalGeneral, 2, ',', ' ') . ' €</div>
        </div>
        
        <!-- Mention TVA -->
        <div class="no-tva">
            Exonération de TVA - Art. 293 B du CGI
        </div>
        
        <!-- Pied de page -->
        <div class="footer">
            Youki & Co - RCS Paris 123 456 789 - Exonération de TVA, art. 293 B du CGI<br>
            Document généré le ' . date('d/m/Y à H:i') . '
        </div>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Sauvegarder le PDF
        if (!is_dir('factures')) {
            mkdir('factures', 0755, true);
        }
        
        $filename = 'factures/facture_' . $idCommande . '_' . date('YmdHis') . '.pdf';
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
 * Affiche la facture HTML sobre
 */
function afficherFactureHTML($pdo, $idCommande) {
    try {
        // Récupérer les informations
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
                    padding: 20px;
                }
                .container {
                    max-width: 800px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 4px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                }
                .invoice {
                    padding: 40px;
                }
                .header {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 40px;
                }
                .company h1 {
                    font-size: 32px;
                    font-weight: 300;
                    color: #333;
                    letter-spacing: 2px;
                    margin-bottom: 5px;
                }
                .company p {
                    color: #666;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .invoice-info {
                    text-align: right;
                }
                .invoice-info h2 {
                    font-size: 24px;
                    font-weight: 300;
                    color: #333;
                    margin-bottom: 5px;
                }
                .invoice-info p {
                    color: #666;
                    font-size: 12px;
                }
                .client-section {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 20px;
                    margin-bottom: 30px;
                    background: #fafafa;
                    padding: 20px;
                    border-radius: 4px;
                }
                .address h3 {
                    font-size: 11px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    color: #999;
                    margin-bottom: 10px;
                }
                .address p {
                    font-size: 13px;
                    color: #333;
                    line-height: 1.6;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 30px 0;
                }
                th {
                    text-align: left;
                    padding: 12px 8px;
                    border-bottom: 2px solid #eee;
                    color: #666;
                    font-weight: 500;
                    font-size: 12px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                td {
                    padding: 12px 8px;
                    border-bottom: 1px solid #f0f0f0;
                    color: #333;
                    font-size: 13px;
                }
                .totals {
                    text-align: right;
                    margin-top: 20px;
                    padding-top: 20px;
                    border-top: 2px solid #f0f0f0;
                }
                .totals p {
                    margin-bottom: 8px;
                    color: #666;
                    font-size: 13px;
                }
                .grand-total {
                    font-size: 18px;
                    font-weight: 400;
                    color: #333;
                    margin-top: 10px;
                    padding-top: 10px;
                    border-top: 1px solid #ddd;
                }
                .tva-mention {
                    margin: 30px 0;
                    padding: 15px;
                    background: #fafafa;
                    border: 1px solid #f0f0f0;
                    color: #666;
                    font-size: 12px;
                    text-align: center;
                }
                .footer {
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 1px solid #f0f0f0;
                    text-align: center;
                    color: #999;
                    font-size: 11px;
                }
                .actions {
                    margin-top: 30px;
                    text-align: center;
                }
                .btn {
                    display: inline-block;
                    padding: 10px 20px;
                    margin: 0 5px;
                    background: white;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    color: #333;
                    text-decoration: none;
                    font-size: 13px;
                    transition: all 0.2s;
                }
                .btn:hover {
                    background: #f5f5f5;
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
                            <p>Créations artisanales japonaises<br>
                            contact@youkiandco.fr<br>
                            SIRET 123 456 789 00012</p>
                        </div>
                        <div class="invoice-info">
                            <h2>FACTURE</h2>
                            <p>N° <?= $idCommande ?><br>
                            Date: <?= date('d/m/Y', strtotime($commande['dateCommande'])) ?></p>
                        </div>
                    </div>

                    <div class="client-section">
                        <div class="address">
                            <h3>Client</h3>
                            <p><?= htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) ?><br>
                            <?= htmlspecialchars($commande['client_email']) ?><br>
                            <?= htmlspecialchars($commande['client_telephone'] ?? '') ?></p>
                        </div>
                        <div class="address">
                            <h3>Facturation</h3>
                            <p><?= htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) ?><br>
                            <?= htmlspecialchars($commande['adresse_facturation']) ?><br>
                            <?= htmlspecialchars($commande['cp_facturation'] . ' ' . $commande['ville_facturation']) ?><br>
                            <?= htmlspecialchars($commande['pays_facturation']) ?></p>
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Prix unitaire</th>
                                <th>Quantité</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articles as $article): ?>
                            <tr>
                                <td><?= htmlspecialchars($article['produit_nom']) ?></td>
                                <td><?= number_format($article['prixUnitaire'], 2, ',', ' ') ?> €</td>
                                <td><?= $article['quantite'] ?></td>
                                <td><?= number_format($article['total_ligne'], 2, ',', ' ') ?> €</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="totals">
                        <p>Sous-total: <?= number_format($sousTotal, 2, ',', ' ') ?> €</p>
                        <p>Frais de port: <?= number_format($commande['fraisDePort'], 2, ',', ' ') ?> €</p>
                        <p class="grand-total">TOTAL: <?= number_format($totalGeneral, 2, ',', ' ') ?> €</p>
                    </div>

                    <div class="tva-mention">
                        Exonération de TVA - Art. 293 B du CGI
                    </div>

                    <div class="footer">
                        Youki & Co - RCS Paris 123 456 789 - Exonération de TVA, art. 293 B du CGI<br>
                        Document généré le <?= date('d/m/Y à H:i') ?>
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
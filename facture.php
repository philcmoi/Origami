<?php
// facture.php - Génération et affichage direct de la facture
error_log("🎯 Facture.php - Génération et affichage direct");

// Inclure TCPDF pour la génération de PDF
require_once('tcpdf/tcpdf.php');

// Configuration de la base de données
$host = 'localhost';
$dbname = 'origami';
$username = 'root';
$password = '';

// Accepter les requêtes POST pour l'appel automatique
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $idCommande = $input['id_commande'] ?? null;
    
    if ($idCommande) {
        try {
            // Connexion à la base de données
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Générer la vraie facture PDF
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

// Récupérer l'ID de commande
$idCommande = $_GET['id'] ?? $_POST['id_commande'] ?? null;

if (!$idCommande) {
    die("❌ ID de commande manquant. Utilisez: facture.php?id=123");
}

/**
 * Génère une vraie facture PDF
 */
function genererFacturePDF($pdo, $idCommande) {
    error_log("🔄 GENERER FACTURE PDF - Début pour commande: " . $idCommande);
    
    try {
        // Récupérer les informations complètes de la commande
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
        
        error_log("📊 Données commande récupérées: " . ($commande ? 'OUI' : 'NON'));
        
        if (!$commande) {
            throw new Exception("Commande non trouvée: " . $idCommande);
        }
        
        // Récupérer les articles de la commande
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
        
        error_log("📦 Articles commande récupérés: " . count($articles));
        
        // Calculer les totaux
        $sousTotal = 0;
        foreach ($articles as $article) {
            $sousTotal += $article['total_ligne'];
        }
        $fraisPort = $commande['fraisDePort'];
        $totalGeneral = $sousTotal + $fraisPort;
        
        // Créer un nouveau PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Information du document
        $pdf->SetCreator('Origami Zen');
        $pdf->SetAuthor('Origami Zen');
        $pdf->SetTitle('Facture #' . $idCommande);
        $pdf->SetSubject('Facture');
        
        // Marges
        $pdf->SetMargins(15, 25, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        
        // Supprimer le header et footer par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Couleurs
        $couleur_principale = array(212, 0, 0); // Rouge #d40000
        $couleur_fond = array(248, 249, 250);   // Gris clair #f8f9fa
        
        // En-tête de la facture
        $html = '
        <style>
            .header { background-color: #d40000; color: white; padding: 15px; text-align: center; }
            .company-info { background-color: #f8f9fa; padding: 15px; margin-bottom: 10px; }
            .invoice-meta { text-align: right; }
            .invoice-number { font-size: 18px; font-weight: bold; color: #d40000; }
            .section-title { background-color: #d40000; color: white; padding: 8px; margin: 15px 0 10px 0; }
            .client-section { margin: 10px 0; }
            .address-box { border: 1px solid #ddd; padding: 10px; margin: 5px 0; }
            .table-header { background-color: #d40000; color: white; font-weight: bold; }
            .table-row { border-bottom: 1px solid #ddd; }
            .total-line { border-top: 2px solid #d40000; padding-top: 10px; margin-top: 10px; }
            .no-tva { background-color: #fff3cd; padding: 10px; text-align: center; margin: 10px 0; border: 1px solid #ffeaa7; }
        </style>
        
        <div class="header">
            <h1>ORIGAMI ZEN</h1>
            <p><em>Créations artisanales japonaises</em></p>
        </div>
        
        <div class="company-info">
            <table width="100%">
                <tr>
                    <td width="60%">
                        <strong>🎎 Origami Zen</strong><br>
                        116 rue de Javel, 75015 Paris<br>
                        📧 contact@origamizen.fr<br>
                        📞 +33 1 23 45 67 89<br>
                        SIRET: 123 456 789 00012
                    </td>
                    <td width="40%" class="invoice-meta">
                        <div class="invoice-number">FACTURE N° ' . $idCommande . '</div>
                        <div>Date: ' . date('d/m/Y', strtotime($commande['dateCommande'])) . '</div>
                        <div>Statut: ' . strtoupper($commande['statut']) . '</div>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="no-tva">
            <strong>🏢 EXONÉRATION DE TVA - Article 293 B du CGI</strong><br>
            <small>Tous les montants sont indiqués hors taxes</small>
        </div>
        ';
        
        // Informations client
        $html .= '
        <div class="section-title">INFORMATIONS CLIENT</div>
        
        <div class="client-section">
            <table width="100%">
                <tr>
                    <td width="50%">
                        <div class="address-box">
                            <strong>👤 CLIENT</strong><br>
                            ' . htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) . '<br>
                            📧 ' . htmlspecialchars($commande['client_email']) . '<br>';
        
        if ($commande['client_telephone']) {
            $html .= '📞 ' . htmlspecialchars($commande['client_telephone']) . '<br>';
        }
        
        $html .= '
                        </div>
                    </td>
                    <td width="50%">
                        <div class="address-box">
                            <strong>📦 ADRESSE DE LIVRAISON</strong><br>
                            ' . htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) . '<br>
                            📍 ' . htmlspecialchars($commande['adresse_livraison']) . '<br>
                            🏙️ ' . htmlspecialchars($commande['cp_livraison'] . ' ' . $commande['ville_livraison']) . '<br>
                            🌍 ' . htmlspecialchars($commande['pays_livraison']) . '
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        ';
        
        // Détail des produits
        $html .= '
        <div class="section-title">DÉTAIL DE LA COMMANDE</div>
        
        <table width="100%" cellpadding="5" border="1" style="border-collapse: collapse;">
            <thead>
                <tr class="table-header">
                    <th width="45%">Produit</th>
                    <th width="15%">Prix unitaire HT</th>
                    <th width="15%">Quantité</th>
                    <th width="25%">Total HT</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($articles as $article) {
            $html .= '
                <tr class="table-row">
                    <td>
                        <strong>' . htmlspecialchars($article['produit_nom']) . '</strong><br>
                        <small>' . htmlspecialchars(substr($article['description'], 0, 80)) . '...</small>
                    </td>
                    <td>' . number_format($article['prixUnitaire'], 2, ',', ' ') . ' €</td>
                    <td>' . $article['quantite'] . '</td>
                    <td><strong>' . number_format($article['total_ligne'], 2, ',', ' ') . ' €</strong></td>
                </tr>';
        }
        
        $html .= '
            </tbody>
        </table>
        ';
        
        // Totaux
        $html .= '
        <div style="margin-top: 20px;">
            <table width="100%">
                <tr>
                    <td width="70%"></td>
                    <td width="30%">
                        <div style="border-top: 1px solid #ddd; padding: 5px 0;">
                            Sous-total produits: ' . number_format($sousTotal, 2, ',', ' ') . ' €
                        </div>
                        <div style="border-top: 1px solid #ddd; padding: 5px 0;">
                            Frais de port: ' . number_format($fraisPort, 2, ',', ' ') . ' €
                        </div>
                        <div class="total-line">
                            <strong>TOTAL FACTURE: ' . number_format($totalGeneral, 2, ',', ' ') . ' €</strong>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="no-tva" style="margin-top: 20px;">
            <strong>⚠️ MONTANT HORS TAXES</strong><br>
            Exonération de TVA applicable - Article 293 B du CGI
        </div>
        
        <div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666;">
            <p><strong>Origami Zen - Créations artisanales japonaises</strong></p>
            <p>116 Rue de Javel, 75015 Paris - contact@origamizen.fr - +33 1 23 45 67 89</p>
            <p>SIRET: 123 456 789 00012 - RCS Paris - Exonération de TVA, art. 293 B du CGI</p>
            <p>Facture générée le ' . date('d/m/Y à H:i') . '</p>
        </div>
        ';
        
        // Écrire le contenu HTML
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Créer le répertoire factures s'il n'existe pas
        if (!is_dir('factures')) {
            mkdir('factures', 0755, true);
        }
        
        // Sauvegarder le PDF
        $filename = 'factures/facture_' . $idCommande . '_' . date('YmdHis') . '.pdf';
        $result = $pdf->Output(__DIR__ . '/' . $filename, 'F');
        
        if (file_exists($filename)) {
            $size = filesize($filename);
            error_log("✅ PDF créé avec succès: " . $filename . " (" . $size . " bytes)");
            return $filename;
        } else {
            error_log("❌ PDF non créé: " . $filename);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("❌ ERREUR génération facture PDF: " . $e->getMessage());
        return false;
    }
}

/**
 * Génère et affiche la facture HTML directement
 */
function afficherFactureHTML($pdo, $idCommande) {
    // ... (le reste de la fonction afficherFactureHTML reste identique)
    // [Le code existant de afficherFactureHTML reste inchangé]
}

// Si on demande spécifiquement un PDF via le paramètre format=pdf
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

// Afficher la facture HTML par défaut
afficherFactureHTML($pdo, $idCommande);
?>
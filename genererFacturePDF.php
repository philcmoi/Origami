<?php
// genererFacturePDF.php - CORRIGÉ
// Ne charge TCPDF que s'il n'est pas déjà chargé

// Vérification stricte avant d'inclure TCPDF
$tcpdf_already_loaded = false;

// Vérification multiple
if (class_exists('TCPDF', false)) {
    $tcpdf_already_loaded = true;
}

if (defined('PDF_PAGE_ORIENTATION')) {
    $tcpdf_already_loaded = true;
}

// Vérifier les fichiers déjà inclus
$included = get_included_files();
foreach ($included as $file) {
    if (strpos($file, 'tcpdf.php') !== false) {
        $tcpdf_already_loaded = true;
        break;
    }
}

// Inclure TCPDF seulement si non chargé
if (!$tcpdf_already_loaded) {
    if (file_exists(dirname(__FILE__) . '/tcpdf/tcpdf.php')) {
        require_once(dirname(__FILE__) . '/tcpdf/tcpdf.php');
    } elseif (file_exists('/usr/share/php/tcpdf/tcpdf.php')) {
        // Ne pas inclure la version globale si elle cause des problèmes
        // On va plutôt utiliser la classe factice
        error_log("TCPDF global trouvé mais on utilise la classe factice pour éviter les conflits");
    }
}

// Définir la fonction si elle n'existe pas
if (!function_exists('genererFacturePDF')) {

function genererFacturePDF($pdo, $idCommande) {
    error_log("🔄 GENERER FACTURE PDF - Début pour commande: " . $idCommande);
    
    // ============================================
    // 🔐 CRÉATION ET VÉRIFICATION DU DOSSIER FACTURES
    // ============================================
    $factureDir = __DIR__ . '/factures';
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($factureDir)) {
        if (mkdir($factureDir, 0755, true)) {
            error_log("📁 Dossier factures créé avec succès: " . $factureDir);
        } else {
            error_log("❌ Échec création dossier factures: " . $factureDir);
            return false;
        }
    }
    
    // Vérifier les droits d'écriture
    if (!is_writable($factureDir)) {
        error_log("❌ Dossier factures non accessible en écriture: " . $factureDir);
        @chmod($factureDir, 0755);
        if (!is_writable($factureDir)) {
            error_log("❌ Échec correction permissions pour: " . $factureDir);
            return false;
        }
    }
    
    try {
        // Récupérer les informations complètes de la commande
        $stmt = $pdo->prepare("
            SELECT 
                c.idCommande,
                c.dateCommande,
                c.montantTotal,
                c.fraisDePort,
                c.statut,
                a_fact.nom as client_nom,
                a_fact.prenom as client_prenom,
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
            LEFT JOIN Adresse a_fact ON c.idAdresseFacturation = a_fact.idAdresse
            WHERE c.idCommande = ?
        ");
        $stmt->execute([$idCommande]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
        
        // Créer un PDF avec la classe disponible (réelle ou factice)
        // Si TCPDF n'est pas disponible, utiliser une classe simple
        if (class_exists('TCPDF', false)) {
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            
            // Information du document
            $pdf->SetCreator('Youki and Co');
            $pdf->SetAuthor('Youki and Co');
            $pdf->SetTitle('Facture #' . $idCommande);
            $pdf->SetSubject('Facture');
            
            // Marges calibrées
            $pdf->SetMargins(20, 20, 20);
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            // Supprimer header/footer par défaut
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Ajouter une page
            $pdf->AddPage();
            
            // Police par défaut
            $pdf->SetFont('helvetica', '', 10);
        } else {
            // Classe PDF simplifiée si TCPDF n'est pas disponible
            $pdf = new SimplePDF();
            error_log("⚠️ Utilisation de SimplePDF car TCPDF non disponible");
        }
        
        // Nom complet du client
        $nomCompletClient = ($commande['client_nom'] ?? '') . ' ' . ($commande['client_prenom'] ?? '');
        if (empty(trim($nomCompletClient))) {
            $nomCompletClient = 'Client';
        }
        
        // Calcul des totaux
        $sousTotal = 0;
        foreach ($articles as $article) {
            $sousTotal += $article['total_ligne'];
        }
        $totalGeneral = $sousTotal + ($commande['fraisDePort'] ?? 0);
        
        // HTML avec colonnes calibrées
        $html = '
        <style>
            body {
                font-family: helvetica;
                font-size: 10pt;
                line-height: 1.4;
                color: #333;
                margin: 0;
                padding: 0;
            }
            .header {
                margin-bottom: 35px;
                border-bottom: 1px solid #ddd;
                padding-bottom: 15px;
            }
            .company {
                font-size: 20pt;
                font-weight: 300;
                letter-spacing: 2px;
                color: #333;
                margin-bottom: 5px;
            }
            .company-details {
                font-size: 8pt;
                color: #666;
            }
            .invoice-title {
                font-size: 18pt;
                font-weight: 300;
                color: #333;
                margin-bottom: 5px;
            }
            .invoice-number {
                font-size: 10pt;
                color: #666;
            }
            .client-section {
                margin-bottom: 30px;
            }
            .client-box {
                margin-bottom: 15px;
            }
            .client-label {
                font-size: 8pt;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #999;
                margin-bottom: 6px;
            }
            .client-name {
                font-size: 11pt;
                font-weight: 500;
                margin-bottom: 4px;
            }
            .client-info {
                font-size: 9pt;
                color: #666;
                line-height: 1.4;
            }
            .address-table {
                width: 100%;
                margin: 15px 0;
                border-collapse: collapse;
            }
            .address-table td {
                width: 50%;
                vertical-align: top;
                padding: 5px 0;
            }
            .address-label-small {
                font-size: 8pt;
                font-weight: bold;
                text-transform: uppercase;
                color: #999;
                margin-bottom: 5px;
            }
            .address-content {
                font-size: 9pt;
                color: #666;
                line-height: 1.4;
            }
            .products-table {
                width: 100%;
                border-collapse: collapse;
                margin: 25px 0;
            }
            .products-table th {
                background: #f8f8f8;
                padding: 10px 6px;
                text-align: left;
                border-bottom: 1px solid #e0e0e0;
                font-size: 9pt;
                font-weight: 600;
                color: #555;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .products-table td {
                padding: 10px 6px;
                border-bottom: 1px solid #f0f0f0;
                font-size: 9pt;
                color: #444;
            }
            .product-col { width: 45%; }
            .price-col { width: 15%; text-align: right; }
            .qty-col { width: 10%; text-align: center; }
            .total-col { width: 15%; text-align: right; }
            .totals {
                text-align: right;
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #e0e0e0;
            }
            .total-line {
                padding: 5px 0;
                font-size: 9pt;
                color: #666;
            }
            .grand-total {
                font-size: 11pt;
                font-weight: bold;
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px solid #ccc;
                color: #333;
            }
            .footer {
                margin-top: 40px;
                padding-top: 15px;
                border-top: 1px solid #eee;
                text-align: center;
                font-size: 7pt;
                color: #999;
            }
            .legal-mention {
                margin-top: 20px;
                padding: 10px;
                background: #fafafa;
                font-size: 7pt;
                color: #666;
                text-align: center;
            }
            .clearfix { clear: both; }
        </style>
        
        <!-- En-tête -->
        <div class="header">
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                <tr>
                    <td width="60%" style="vertical-align: top;">
                        <div class="company">YOUKI & CO</div>
                        <div class="company-details">Créations artisanales japonaises</div>
                    </td>
                    <td width="40%" style="text-align: right; vertical-align: top;">
                        <div class="invoice-title">FACTURE</div>
                        <div class="invoice-number">N° ' . $idCommande . '</div>
                        <div class="invoice-number">' . date('d/m/Y', strtotime($commande['dateCommande'])) . '</div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Informations client -->
        <div class="client-section">
            <div class="client-box">
                <div class="client-label">CLIENT</div>
                <div class="client-name">' . htmlspecialchars($nomCompletClient) . '</div>
                <div class="client-info">' . htmlspecialchars($commande['client_email'] ?? '') . '</div>';
        
        if (!empty($commande['client_telephone'])) {
            $html .= '<div class="client-info">' . htmlspecialchars($commande['client_telephone']) . '</div>';
        }
        
        $html .= '
            </div>
            
            <table class="address-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <div class="address-label-small">ADRESSE DE LIVRAISON</div>
                        <div class="address-content">' . htmlspecialchars($nomCompletClient) . '<br>
                        ' . htmlspecialchars($commande['adresse_livraison'] ?? '') . '<br>
                        ' . htmlspecialchars(($commande['cp_livraison'] ?? '') . ' ' . ($commande['ville_livraison'] ?? '')) . '<br>
                        ' . htmlspecialchars($commande['pays_livraison'] ?? '') . '</div>
                    </td>
                    <tr>
                        <div class="address-label-small">ADRESSE DE FACTURATION</div>
                        <div class="address-content">' . htmlspecialchars($nomCompletClient) . '<br>
                        ' . htmlspecialchars($commande['adresse_facturation'] ?? '') . '<br>
                        ' . htmlspecialchars(($commande['cp_facturation'] ?? '') . ' ' . ($commande['ville_facturation'] ?? '')) . '<br>
                        ' . htmlspecialchars($commande['pays_facturation'] ?? '') . '</div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Tableau des produits avec colonnes calibrées -->
        <table class="products-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th class="product-col">DÉSIGNATION</th>
                    <th class="price-col">PRIX UNIT.</th>
                    <th class="qty-col">QTÉ</th>
                    <th class="total-col">TOTAL</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($articles as $article) {
            $html .= '
                <tr>
                    <td class="product-col">' . htmlspecialchars($article['produit_nom']) . '</td>
                    <td class="price-col">' . number_format($article['prixUnitaire'], 2, ',', ' ') . ' €</td>
                    <td class="qty-col">' . $article['quantite'] . '</td>
                    <td class="total-col">' . number_format($article['total_ligne'], 2, ',', ' ') . ' €</td>
                </tr>';
        }
        
        $html .= '
            </tbody>
        </table>
        
        <!-- Totaux -->
        <div class="totals">
            <div class="total-line">Sous-total : ' . number_format($sousTotal, 2, ',', ' ') . ' €</div>
            <div class="total-line">Frais de port : ' . number_format(($commande['fraisDePort'] ?? 0), 2, ',', ' ') . ' €</div>
            <div class="grand-total">TOTAL : ' . number_format($totalGeneral, 2, ',', ' ') . ' €</div>
        </div>
        
        <!-- Mentions légales -->
        <div class="legal-mention">
            Exonération de TVA - Article 293 B du Code Général des Impôts
        </div>
        
        <!-- Pied de page -->
        <div class="footer">
            Youki & Co - SIRET 123 456 789 00012<br>
            contact@youkiandco.fr<br>
            Facture générée le ' . date('d/m/Y à H:i') . '
        </div>';
        
        // Écrire le contenu HTML
        if (method_exists($pdf, 'writeHTML')) {
            $pdf->writeHTML($html, true, false, true, false, '');
        }
        
        // Nom du fichier
        $filename = 'facture_' . $idCommande . '_' . date('Ymd_His') . '.pdf';
        $filepath = $factureDir . '/' . $filename;
        
        // Sauvegarder le PDF
        if (method_exists($pdf, 'Output')) {
            $pdf->Output($filepath, 'F');
        } else {
            // Si pas de méthode Output, sauvegarder le HTML en fichier texte
            file_put_contents($filepath . '.html', $html);
            error_log("⚠️ PDF non généré, sauvegarde HTML: " . $filepath . '.html');
            return false;
        }
        
        // Vérifier que le fichier a bien été créé
        if (file_exists($filepath) && filesize($filepath) > 0) {
            error_log("✅ PDF créé avec succès: " . $filepath . " (" . filesize($filepath) . " octets)");
            return $filepath;
        } else {
            throw new Exception("Le fichier PDF n'a pas été créé correctement");
        }
        
    } catch (Exception $e) {
        error_log("❌ ERREUR génération facture PDF: " . $e->getMessage());
        return false;
    }
}

} // Fin de if (!function_exists('genererFacturePDF'))

// Classe PDF simplifiée si TCPDF n'est pas disponible
if (!class_exists('SimplePDF', false)) {
    class SimplePDF {
        private $html = '';
        
        public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4') {}
        public function AddPage() {}
        public function SetFont($family, $style = '', $size = null) {}
        public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '') {}
        public function Output($name = 'doc.pdf', $dest = 'I') { return ''; }
        public function writeHTML($html) { $this->html = $html; }
        public function SetMargins($left, $top, $right = -1) {}
        public function SetAutoPageBreak($auto, $margin = 0) {}
        public function setPrintHeader($value) {}
        public function setPrintFooter($value) {}
    }
}

/**
 * Affiche la facture PDF directement dans le navigateur
 */
function afficherFacturePDFDirect($pdo, $idCommande) {
    $filepath = genererFacturePDF($pdo, $idCommande);
    
    if ($filepath && file_exists($filepath)) {
        $filename = 'facture_' . $idCommande . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        header('Content-Type: text/html');
        echo "Erreur: Impossible de générer la facture PDF";
        exit;
    }
}

/**
 * Envoie la facture par email
 */
function envoyerFactureParEmail($emailClient, $cheminFichier, $idCommande) {
    try {
        error_log("📧 Envoi facture par email à: " . $emailClient);
        
        if (!file_exists($cheminFichier)) {
            error_log("❌ Fichier PDF introuvable: " . $cheminFichier);
            return false;
        }
        
        $filename = basename($cheminFichier);
        
        $sujet = "Votre facture Youki and Co - Commande #" . $idCommande;
        
        $messageHTML = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 25px; }
                .logo { font-size: 24px; font-weight: 300; letter-spacing: 2px; color: #333; }
                .content { margin-bottom: 30px; }
                .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>YOUKI & CO</div>
                </div>
                <div class='content'>
                    <p>Bonjour,</p>
                    <p>Nous vous remercions pour votre commande n°<strong>" . $idCommande . "</strong>.</p>
                    <p>Votre facture est disponible en pièce jointe au format PDF.</p>
                    <p>Pour toute question, n'hésitez pas à nous contacter.</p>
                    <p>Cordialement,<br>L'équipe Youki & Co</p>
                </div>
                <div class='footer'>
                    Youki & Co - Créations artisanales japonaises<br>
                    contact@youkiandco.fr
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Utiliser la fonction d'envoi avec pièce jointe
        if (function_exists('envoyerEmailAvecPieceJointe')) {
            $resultat = envoyerEmailAvecPieceJointe($emailClient, $sujet, $messageHTML, $cheminFichier);
            if ($resultat['success']) {
                error_log("✅ Email envoyé avec succès à: " . $emailClient);
                return true;
            }
        }
        
        // Fallback avec PHPMailer si disponible
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($emailClient);
            $mail->addAttachment($cheminFichier);
            $mail->isHTML(true);
            $mail->Subject = $sujet;
            $mail->Body = $messageHTML;
            $mail->send();
            error_log("✅ Email envoyé avec succès (PHPMailer) à: " . $emailClient);
            return true;
        }
        
        error_log("❌ Aucune méthode d'envoi d'email disponible");
        return false;
        
    } catch (Exception $e) {
        error_log("❌ ERREUR envoi email facture: " . $e->getMessage());
        return false;
    }
}

/**
 * Traite un paiement réussi
 */
function traiterPaiementReussi($pdo, $idCommande) {
    error_log("💰 TRAITEMENT PAIEMENT RÉUSSI - Commande: " . $idCommande);
    
    try {
        $stmt = $pdo->prepare("UPDATE Commande SET statut = 'payee', datePaiement = NOW() WHERE idCommande = ?");
        $stmt->execute([$idCommande]);
        
        $resultatFacture = genererFacturePDF($pdo, $idCommande);
        
        // Récupérer l'email du client pour envoi automatique
        $stmt = $pdo->prepare("
            SELECT cl.email 
            FROM Commande c
            JOIN Client cl ON c.idClient = cl.idClient
            WHERE c.idCommande = ?
        ");
        $stmt->execute([$idCommande]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultatFacture && $client && !empty($client['email'])) {
            envoyerFactureParEmail($client['email'], $resultatFacture, $idCommande);
        }
        
        if ($resultatFacture) {
            error_log("✅ Paiement traité avec succès - Facture générée: " . $resultatFacture);
            return true;
        } else {
            error_log("⚠️ Paiement traité mais problème avec la facture");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("❌ Erreur traitement paiement: " . $e->getMessage());
        return false;
    }
}
?>
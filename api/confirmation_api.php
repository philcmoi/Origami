<?php
// api/confirmation_api.php
// ============================================
// API D'ENVOI DE LIEN DE CONFIRMATION
// ============================================

$email = $data['email'] ?? '';
$nom = $data['nom'] ?? 'Client';
$prenom = $data['prenom'] ?? '';
$telephone = $data['telephone'] ?? '';

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 400, 'error' => 'Email invalide']);
    exit;
}

// Vérifier si l'email existe déjà
try {
    $stmt = $pdo->prepare("SELECT idClient, nom, prenom FROM Client WHERE email = ? AND type = 'permanent'");
    $stmt->execute([$email]);
    $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Column not found") !== false) {
        $stmt = $pdo->prepare("SELECT idClient, nom, prenom FROM Client WHERE email = ?");
        $stmt->execute([$email]);
        $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        throw $e;
    }
}

$clientExistant = ($clientExist !== false);
$idClient = null;

// Si le client n'existe pas, le créer
if (!$clientExistant) {
    $motDePasse = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone, type) VALUES (?, ?, ?, ?, ?, 'permanent')");
        $stmt->execute([$email, $motDePasse, $nom, $prenom, $telephone]);
    } catch (Exception $e) {
        $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$email, $motDePasse, $nom, $prenom, $telephone]);
    }
    $idClient = $pdo->lastInsertId();
} else {
    $idClient = $clientExist['idClient'];
}

// Gestion du panier
$idClientTemporaire = $_SESSION['client_id'] ?? null;

// Vérifier si le client permanent a déjà un panier
$stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
$stmt->execute([$idClient]);
$panierPermanent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$panierPermanent) {
    $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
    $stmt->execute([$idClient]);
    $panierPermanent = ['idPanier' => $pdo->lastInsertId()];
}

// Transférer les articles du panier temporaire si nécessaire
if ($idClientTemporaire && $idClientTemporaire != $idClient) {
    try {
        $stmt = $pdo->prepare("
            SELECT lp.idLignePanier, lp.idOrigami, lp.quantite, lp.prixUnitaire 
            FROM LignePanier lp 
            JOIN Panier p ON lp.idPanier = p.idPanier 
            WHERE p.idClient = ?
        ");
        $stmt->execute([$idClientTemporaire]);
        $articlesTemporaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($articlesTemporaires)) {
            foreach ($articlesTemporaires as $article) {
                $stmt = $pdo->prepare("SELECT idLignePanier, quantite FROM LignePanier WHERE idPanier = ? AND idOrigami = ?");
                $stmt->execute([$panierPermanent['idPanier'], $article['idOrigami']]);
                $articleExistant = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($articleExistant) {
                    $nouvelleQuantite = $articleExistant['quantite'] + $article['quantite'];
                    $stmt = $pdo->prepare("UPDATE LignePanier SET quantite = ? WHERE idLignePanier = ?");
                    $stmt->execute([$nouvelleQuantite, $articleExistant['idLignePanier']]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO LignePanier (idPanier, idOrigami, quantite, prixUnitaire) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$panierPermanent['idPanier'], $article['idOrigami'], $article['quantite'], $article['prixUnitaire']]);
                }
            }
            
            // Supprimer le panier temporaire
            $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier IN (SELECT idPanier FROM Panier WHERE idClient = ?)");
            $stmt->execute([$idClientTemporaire]);
            $stmt = $pdo->prepare("DELETE FROM Panier WHERE idClient = ?");
            $stmt->execute([$idClientTemporaire]);
            $stmt = $pdo->prepare("DELETE FROM Client WHERE idClient = ? AND (type = 'temporaire' OR email LIKE 'temp_%@origamizen.fr')");
            $stmt->execute([$idClientTemporaire]);
            
            error_log("🔄 Articles transférés du client temporaire " . $idClientTemporaire . " vers le client permanent " . $idClient);
        }
        
        $_SESSION['client_id'] = $idClient;
    } catch (Exception $e) {
        error_log("Erreur transfert panier: " . $e->getMessage());
    }
}

// Récupérer le récapitulatif du panier
$stmt = $pdo->prepare("
    SELECT 
        lp.idLignePanier,
        lp.idOrigami,
        lp.quantite,
        lp.prixUnitaire,
        o.nom,
        o.description,
        o.photo,
        (lp.quantite * lp.prixUnitaire) as totalLigne
    FROM LignePanier lp
    JOIN Origami o ON lp.idOrigami = o.idOrigami
    WHERE lp.idPanier = ? AND o.visible = 1
");
$stmt->execute([$panierPermanent['idPanier']]);
$articlesPanier = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer le total du panier
$totalPanier = 0;
$totalQuantites = 0;
foreach ($articlesPanier as $article) {
    $totalPanier += $article['totalLigne'];
    $totalQuantites += $article['quantite'];
}

$fraisDePort = 0.0;
$montantTotal = $totalPanier + $fraisDePort;

$tokenConfirmation = genererTokenConfirmation();

// Stocker le token
try {
    $stmt = $pdo->prepare("INSERT INTO tokens_confirmation (token, email, id_client, expiration, utilise) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)");
    $stmt->execute([$tokenConfirmation, $email, $idClient]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("Échec de l'insertion du token");
    }
    
    error_log("Token créé pour client ID: " . $idClient . ", email: " . $email);
} catch (Exception $e) {
    error_log("ERREUR insertion token: " . $e->getMessage());
    echo json_encode(['status' => 500, 'error' => 'Erreur technique lors de la création du lien de confirmation']);
    exit;
}

$urlConfirmation = "http://" . $_SERVER['HTTP_HOST'] . "/acheter.php?action=confirmer_commande&token=" . $tokenConfirmation;

// Préparer l'email HTML
$sujet = "Confirmez votre commande - Youki and Co";
$messageHTML = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Confirmation de commande</title>
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .header { text-align: center; color: #d40000; margin-bottom: 30px; border-bottom: 2px solid #f0f0f0; padding-bottom: 20px; }
        .btn-confirmation { display: block; width: 280px; margin: 30px auto; padding: 15px 30px; background-color: #a6a2dcff; color: white; text-decoration: none; text-align: center; border-radius: 5px; font-size: 18px; font-weight: bold; }
        .btn-confirmation:hover { background-color: #16b005ff; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 14px; text-align: center; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin: 20px 0; color: #856404; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Youki and Co</h1>
            <h2>Confirmation de votre commande</h2>
        </div>
        <div class='content'>
            <p>Bonjour <strong>" . htmlspecialchars($nom) . "</strong>,</p>
            <p>Pour finaliser votre commande, veuillez cliquer sur le bouton ci-dessous :</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . $urlConfirmation . "' class='btn-confirmation'>Confirmer ma commande</a>
            </div>
            <div class='warning'>
                <strong>⚠️ Important :</strong> Ce lien est valable pendant <strong>15 minutes</strong> seulement.
            </div>
            <p>Si vous n'avez pas initié cette demande, veuillez ignorer cet email.</p>
        </div>
        <div class='footer'>
            <p><strong>YOUKI and Co - Créations artisanales japonaises</strong></p>
        </div>
    </div>
</body>
</html>
";

$resultatEmail = envoyerEmail($email, $sujet, $messageHTML);

if ($resultatEmail['success']) {
    echo json_encode([
        'status' => 200,
        'data' => [
            'message' => 'Lien de confirmation envoyé',
            'client_existant' => $clientExistant,
            'id_client' => $idClient,
            'recap_panier' => [
                'articles' => $articlesPanier,
                'total' => $totalPanier,
                'frais_port' => $fraisDePort,
                'total_general' => $montantTotal,
                'quantite_total' => $totalQuantites
            ]
        ]
    ]);
} else {
    error_log("Échec envoi email à: " . $email . " - Erreur: " . $resultatEmail['error']);
    echo json_encode([
        'status' => 500, 
        'error' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.',
        'debug' => $resultatEmail['error']
    ]);
}
exit;
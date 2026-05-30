<?php
// ============================================
// API DE GESTION DU PANIER
// ============================================

$actionsNecessitantClient = ['ajouter_au_panier', 'get_panier', 'modifier_quantite', 'supprimer_du_panier', 'vider_panier'];

if (in_array($action, $actionsNecessitantClient)) {
    $idClient = getOrCreateClient($pdo);
} else {
    $idClient = null;
}

if ($action == 'ajouter_au_panier') {
    if (!$idClient) {
        echo json_encode(['status' => 400, 'error' => 'Client non initialisé']);
        exit;
    }

    $idOrigami = $data['idOrigami'] ?? null;
    $quantite = $data['quantite'] ?? 1;

    if (!$idOrigami) {
        echo json_encode(['status' => 400, 'error' => 'ID origami manquant']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
        $stmt->execute([$idClient]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$panier) {
            $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
            $stmt->execute([$idClient]);
            $idPanier = $pdo->lastInsertId();
        } else {
            $idPanier = $panier['idPanier'];
        }

        // Nettoyer les éventuelles lignes orphelines
        $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ? AND idOrigami IS NULL");
        $stmt->execute([$idPanier]);

        // Récupérer le prix de l'origami
        $stmt = $pdo->prepare("SELECT prixHorsTaxe FROM Origami WHERE idOrigami = ? AND visible = 1");
        $stmt->execute([$idOrigami]);
        $origami = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$origami) {
            throw new Exception('Origami non trouvé');
        }

        $prixUnitaire = $origami['prixHorsTaxe'];

        // Vérifier si l'article est déjà dans le panier
        $stmt = $pdo->prepare("SELECT idLignePanier, quantite FROM LignePanier WHERE idPanier = ? AND idOrigami = ?");
        $stmt->execute([$idPanier, $idOrigami]);
        $ligneExistante = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ligneExistante) {
            $nouvelleQuantite = $ligneExistante['quantite'] + $quantite;
            $stmt = $pdo->prepare("UPDATE LignePanier SET quantite = ?, prixUnitaire = ? WHERE idLignePanier = ?");
            $stmt->execute([$nouvelleQuantite, $prixUnitaire, $ligneExistante['idLignePanier']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO LignePanier (idPanier, idOrigami, quantite, prixUnitaire) VALUES (?, ?, ?, ?)");
            $stmt->execute([$idPanier, $idOrigami, $quantite, $prixUnitaire]);
        }

        // Mettre à jour la date de modification du panier
        $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
        $stmt->execute([$idPanier]);
        
        $pdo->commit();

        echo json_encode(['status' => 200, 'message' => 'Article ajouté au panier']);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur ajout panier: " . $e->getMessage());
        echo json_encode(['status' => 500, 'error' => 'Erreur: ' . $e->getMessage()]);
    }
}

elseif ($action == 'get_panier') {
    if (!$idClient) {
        echo json_encode(['status' => 200, 'data' => ['articles' => [], 'total' => 0, 'totalQuantites' => 0]]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT p.idPanier FROM Panier p WHERE p.idClient = ?");
    $stmt->execute([$idClient]);
    $panier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$panier) {
        echo json_encode(['status' => 200, 'data' => ['articles' => [], 'total' => 0, 'totalQuantites' => 0]]);
        exit;
    }

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
    $stmt->execute([$panier['idPanier']]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    $totalQuantites = 0;
    foreach ($articles as $article) {
        $total += $article['totalLigne'];
        $totalQuantites += $article['quantite'];
    }

    echo json_encode([
        'status' => 200,
        'data' => [
            'articles' => $articles,
            'total' => $total,
            'totalQuantites' => $totalQuantites
        ]
    ]);
}

elseif ($action == 'modifier_quantite') {
    if (!$idClient) {
        echo json_encode(['status' => 400, 'error' => 'Client non initialisé']);
        exit;
    }

    $idLignePanier = $data['idLignePanier'] ?? null;
    $quantite = $data['quantite'] ?? null;

    if (!$idLignePanier || !$quantite) {
        echo json_encode(['status' => 400, 'error' => 'ID ligne panier ou quantité manquant']);
        exit;
    }

    if ($quantite < 1) {
        echo json_encode(['status' => 400, 'error' => 'La quantité doit être au moins 1']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE LignePanier SET quantite = ? WHERE idLignePanier = ?");
    $stmt->execute([$quantite, $idLignePanier]);

    echo json_encode(['status' => 200, 'message' => 'Quantité modifiée']);
}

elseif ($action == 'supprimer_du_panier') {
    if (!$idClient) {
        echo json_encode(['status' => 400, 'error' => 'Client non initialisé']);
        exit;
    }

    $idLignePanier = $data['idLignePanier'] ?? null;

    if (!$idLignePanier) {
        echo json_encode(['status' => 400, 'error' => 'ID ligne panier manquant']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idLignePanier = ?");
    $stmt->execute([$idLignePanier]);

    echo json_encode(['status' => 200, 'message' => 'Article supprimé du panier']);
}

elseif ($action == 'vider_panier') {
    if (!$idClient) {
        echo json_encode(['status' => 400, 'error' => 'Client non initialisé']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
    $stmt->execute([$idClient]);
    $panier = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($panier) {
        $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ?");
        $stmt->execute([$panier['idPanier']]);
        
        $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
        $stmt->execute([$panier['idPanier']]);
    }

    echo json_encode(['status' => 200, 'message' => 'Panier vidé']);
}
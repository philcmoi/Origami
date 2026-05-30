<?php
// includes/fonctions_commande.php

function finaliserCommande($pdo, $idClient, $idAdresseLivraison, $idAdresseFacturation) {
    // Récupérer le panier
    $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
    $stmt->execute([$idClient]);
    $panier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$panier) {
        throw new Exception("Panier non trouvé");
    }
    
    // Récupérer les articles
    $stmt = $pdo->prepare("
        SELECT lp.idOrigami, lp.quantite, lp.prixUnitaire, (lp.quantite * lp.prixUnitaire) as totalLigne
        FROM LignePanier lp
        JOIN Origami o ON lp.idOrigami = o.idOrigami
        WHERE lp.idPanier = ? AND o.visible = 1
    ");
    $stmt->execute([$panier['idPanier']]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($articles)) {
        throw new Exception("Panier vide");
    }
    
    // Calculer le total
    $total = 0;
    foreach ($articles as $article) {
        $total += $article['totalLigne'];
    }
    
    $delaiLivraison = date('Y-m-d', strtotime('+5 days'));
    $montantTotal = $total;
    
    // Créer la commande
    $stmt = $pdo->prepare("
        INSERT INTO Commande 
        (idClient, idAdresseLivraison, idAdresseFacturation, dateCommande, delaiLivraison, fraisDePort, montantTotal, statut) 
        VALUES (?, ?, ?, NOW(), ?, 0, ?, 'en_attente_paiement')
    ");
    $stmt->execute([$idClient, $idAdresseLivraison, $idAdresseFacturation, $delaiLivraison, $montantTotal]);
    $idCommande = $pdo->lastInsertId();
    
    // Créer les lignes de commande
    $stmtLigne = $pdo->prepare("INSERT INTO LigneCommande (idCommande, idOrigami, quantite, prixUnitaire) VALUES (?, ?, ?, ?)");
    foreach ($articles as $article) {
        $stmtLigne->execute([$idCommande, $article['idOrigami'], $article['quantite'], $article['prixUnitaire']]);
    }
    
    // Vider le panier
    $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ?");
    $stmt->execute([$panier['idPanier']]);
    
    return $idCommande;
}

function creerCommandeAvecAdresseExistante($pdo, $idClient, $idAdresseLivraison) {
    return finaliserCommande($pdo, $idClient, $idAdresseLivraison, $idAdresseLivraison);
}
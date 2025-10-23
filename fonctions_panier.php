<?php
// fonctions_panier.php

/**
 * Vérifie si le panier est vide
 */
function panierEstVide() {
    if (!isset($_SESSION['panier'])) {
        return true;
    }
    
    // Si le panier est un tableau de produits
    if (is_array($_SESSION['panier'])) {
        if (empty($_SESSION['panier'])) {
            return true;
        }
        
        // Vérifier si au moins un produit a une quantité > 0
        foreach ($_SESSION['panier'] as $produit) {
            if (isset($produit['quantite']) && $produit['quantite'] > 0) {
                return false;
            }
            // Si pas de quantité définie, considérer comme 1 article
            if (!isset($produit['quantite'])) {
                return false;
            }
        }
        return true;
    }
    
    // Si le panier est un objet
    if (is_object($_SESSION['panier'])) {
        if (isset($_SESSION['panier']->produits) && !empty($_SESSION['panier']->produits)) {
            return false;
        }
        if (isset($_SESSION['panier']->items) && !empty($_SESSION['panier']->items)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Calcule le nombre total d'articles dans le panier
 */
function getNombreArticlesPanier() {
    if (!isset($_SESSION['panier']) || empty($_SESSION['panier'])) {
        return 0;
    }
    
    $total = 0;
    
    if (is_array($_SESSION['panier'])) {
        foreach ($_SESSION['panier'] as $produit) {
            if (isset($produit['quantite'])) {
                $total += $produit['quantite'];
            } else {
                $total += 1; // Si pas de quantité, compter comme 1
            }
        }
    }
    
    return $total;
}

/**
 * Calcule le total du panier
 */
function calculerTotalPanier() {
    if (panierEstVide()) {
        return 0;
    }
    
    $total = 0;
    
    if (is_array($_SESSION['panier'])) {
        foreach ($_SESSION['panier'] as $produit) {
            $quantite = $produit['quantite'] ?? 1;
            $prix = $produit['prix'] ?? 0;
            $total += $quantite * $prix;
        }
    }
    
    return $total;
}

/**
 * Récupère les détails du panier pour l'email
 */
function getDetailsPanierPourEmail() {
    if (panierEstVide()) {
        return "Aucun article dans le panier";
    }
    
    $details = "";
    
    if (is_array($_SESSION['panier'])) {
        foreach ($_SESSION['panier'] as $id => $produit) {
            $nom = $produit['nom'] ?? "Produit #$id";
            $quantite = $produit['quantite'] ?? 1;
            $prix = $produit['prix'] ?? 0;
            $sousTotal = $quantite * $prix;
            
            $details .= "<tr>
                <td>$nom</td>
                <td>$quantite</td>
                <td>" . number_format($prix, 2) . " €</td>
                <td>" . number_format($sousTotal, 2) . " €</td>
            </tr>";
        }
    }
    
    return $details;
}
?>
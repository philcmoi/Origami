<?php
$commande_id = isset($_GET['commande']) ? intval($_GET['commande']) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paiement Annulé</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 50px; }
        .error { color: red; font-size: 3rem; }
    </style>
</head>
<body>
    <div class="error">✗</div>
    <h1>Paiement Annulé</h1>
    <p>Vous avez annulé le paiement.</p>
    <a href="panier.html">Retour au panier</a> | 
    <a href="index.html">Retour à l'accueil</a>
</body>
</html>
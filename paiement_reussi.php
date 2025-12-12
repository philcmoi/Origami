<?php
session_start();

// Récupérer l'ID de commande
$commande_id = isset($_GET['commande']) ? intval($_GET['commande']) : 0;

// Connexion BDD
$servername = "localhost";
$username = "Philippe";
$password = "l@99339R";
$dbname = "heureducadeau";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($commande_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM commandes WHERE id_commande = :id");
        $stmt->bindParam(':id', $commande_id, PDO::PARAM_INT);
        $stmt->execute();
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    $commande = null;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paiement Réussi - HEURE DU CADEAU</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 50px; }
        .success { color: green; font-size: 3rem; }
        .info { background: #f0f8ff; padding: 20px; border-radius: 10px; margin: 20px auto; max-width: 500px; }
    </style>
</head>
<body>
    <div class="success">✓</div>
    <h1>Paiement Réussi !</h1>
    <p>Merci pour votre commande.</p>
    
    <?php if ($commande): ?>
    <div class="info">
        <h3>Commande #<?php echo htmlspecialchars($commande['numero_commande']); ?></h3>
        <p>Montant: <?php echo number_format($commande['total_ttc'], 2, ',', ' '); ?> €</p>
        <p>Date: <?php echo date('d/m/Y H:i', strtotime($commande['date_paiement'])); ?></p>
    </div>
    <?php endif; ?>
    
    <a href="index.html">Retour à l'accueil</a>
</body>
</html>
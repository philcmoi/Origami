<?php
// clean_client.php - Suppression propre d'un client
// À placer à la racine de votre projet

// Forcer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration de la base de données
$host = 'localhost';
$dbname = 'heureducadeau';
$username = 'Philippe';
$password = 'l@99339R';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "✅ Connexion à la base de données réussie !<br>\n";
} catch (PDOException $e) {
    die("❌ Erreur de connexion : " . $e->getMessage());
}

// ID du client à supprimer (MODIFIEZ ICI)
$idClient = 844; // 👈 Changez ce chiffre selon le client à supprimer

echo "<h2>🗑️ Suppression du client #$idClient</h2>\n";

try {
    $pdo->beginTransaction();
    echo "📦 Début de la transaction...<br>\n";

    // 1. Vérifier si le client existe
    $stmt = $pdo->prepare("SELECT email, nom, prenom, type FROM Client WHERE idClient = ?");
    $stmt->execute([$idClient]);
    $client = $stmt->fetch();
    
    if (!$client) {
        throw new Exception("Client #$idClient non trouvé !");
    }
    
    echo "👤 Client trouvé : " . htmlspecialchars($client['email']) . " (" . htmlspecialchars($client['type']) . ")<br>\n";

    // 2. Supprimer les lignes du panier
    $stmt = $pdo->prepare("
        DELETE lp FROM LignePanier lp 
        INNER JOIN Panier p ON lp.idPanier = p.idPanier 
        WHERE p.idClient = ?
    ");
    $stmt->execute([$idClient]);
    $count = $stmt->rowCount();
    echo "🗑️ Lignes panier supprimées : $count<br>\n";
    
    // 3. Supprimer le panier
    $stmt = $pdo->prepare("DELETE FROM Panier WHERE idClient = ?");
    $stmt->execute([$idClient]);
    $count = $stmt->rowCount();
    echo "🗑️ Panier supprimé : $count<br>\n";
    
    // 4. Supprimer les adresses
    $stmt = $pdo->prepare("DELETE FROM Adresse WHERE idClient = ?");
    $stmt->execute([$idClient]);
    $count = $stmt->rowCount();
    echo "🗑️ Adresses supprimées : $count<br>\n";
    
    // 5. Supprimer les lignes de commande
    $stmt = $pdo->prepare("
        DELETE lc FROM LigneCommande lc 
        INNER JOIN Commande c ON lc.idCommande = c.idCommande 
        WHERE c.idClient = ?
    ");
    $stmt->execute([$idClient]);
    $count = $stmt->rowCount();
    echo "🗑️ Lignes de commande supprimées : $count<br>\n";
    
    // 6. Supprimer les paiements associés
    $stmt = $pdo->prepare("
        DELETE p FROM Paiement p 
        INNER JOIN Commande c ON p.idCommande = c.idCommande 
        WHERE c.idClient = ?
    ");
    $stmt->execute([$idClient]);
    $count = $stmt->rowCount();
    echo "🗑️ Paiements supprimés : $count<br>\n";
    
    // 7. Supprimer les commandes
    $stmt = $pdo->prepare("DELETE FROM Commande WHERE idClient = ?");
    $stmt->execute([$idClient]);
    $count = $stmt->rowCount();
    echo "🗑️ Commandes supprimées : $count<br>\n";
    
    // 8. Supprimer les tokens de confirmation
    $stmt = $pdo->prepare("DELETE FROM tokens_confirmation WHERE id_client = ?");
    $stmt->execute([$idClient]);
    $count = $stmt->rowCount();
    echo "🗑️ Tokens supprimés : $count<br>\n";
    
    // 9. Enfin supprimer le client
    $stmt = $pdo->prepare("DELETE FROM Client WHERE idClient = ?");
    $stmt->execute([$idClient]);
    $count = $stmt->rowCount();
    echo "🗑️ Client supprimé : $count<br>\n";
    
    // Valider la transaction
    $pdo->commit();
    
    echo "<hr>\n";
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-top: 20px;'>";
    echo "✅ <strong>Client #$idClient supprimé avec succès !</strong><br>\n";
    echo "📧 Email : " . htmlspecialchars($client['email']) . "<br>\n";
    echo "👤 Nom : " . htmlspecialchars($client['prenom'] . ' ' . $client['nom']) . "<br>\n";
    echo "🏷️ Type : " . htmlspecialchars($client['type']) . "<br>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-top: 20px;'>";
    echo "❌ <strong>Erreur :</strong> " . htmlspecialchars($e->getMessage()) . "<br>\n";
    echo "</div>\n";
}

// Afficher un récapitulatif des clients restants
echo "<hr>\n";
echo "<h3>📊 Clients restants dans la base :</h3>\n";

$stmt = $pdo->query("SELECT idClient, email, nom, prenom, type FROM Client ORDER BY idClient DESC LIMIT 20");
$clients = $stmt->fetchAll();

if (count($clients) > 0) {
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Email</th><th>Nom</th><th>Prénom</th><th>Type</th><th>Action</th></tr>";
    foreach ($clients as $c) {
        echo "<tr>";
        echo "<td>" . $c['idClient'] . "</td>";
        echo "<td>" . htmlspecialchars($c['email']) . "</td>";
        echo "<td>" . htmlspecialchars($c['nom'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($c['prenom'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($c['type']) . "</td>";
        echo "<td><a href='?id=" . $c['idClient'] . "' style='color: #dc3545;'>🗑️ Supprimer</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun client restant.</p>\n";
}

// Lien pour supprimer un autre client via l'URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    echo "<script>window.location.href = '?id=" . $_GET['id'] . "';</script>";
}
?>
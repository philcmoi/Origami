<?php
// Script pour créer un administrateur par défaut (à exécuter une seule fois)
$host = '217.182.198.20';
$dbname = 'origami';
$username = 'root';
$password = 'L099339R';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vérifier si des administrateurs existent déjà
    $stmt = $pdo->query("SELECT COUNT(*) FROM Administrateur");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Créer un administrateur par défaut
        $email = "lhpp.philippe@gmail.com";
        $motDePasse = password_hash("007", PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO Administrateur (email, motDePasse) VALUES (?, ?)");
        $stmt->execute([$email, $motDePasse]);
        
        echo "Administrateur créé avec succès!<br>";
        echo "Email: admin@origamizen.fr<br>";
        echo "Mot de passe: admin123<br>";
        echo "<strong>Attention: Changez le mot de passe après la première connexion!</strong>";
    } else {
        echo "Des administrateurs existent déjà dans la base de données.";
    }
    
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage();
}
?>
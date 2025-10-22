<?php
// Script d'installation pour créer la table Administrateur et un compte par défaut
$host = 'localhost';
$dbname = 'origami';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Créer la table Administrateur
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Administrateur (
            idAdmin BIGINT NOT NULL AUTO_INCREMENT,
            email VARCHAR(50) NOT NULL,
            motDePasse VARCHAR(255) NOT NULL,
            dateCreation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT Administrateur_PK PRIMARY KEY (idAdmin),
            UNIQUE KEY unique_email (email)
        ) ENGINE=InnoDB
    ");
    
    echo "<h2>Installation de la table Administrateur</h2>";
    echo "<p>Table créée ou déjà existante.</p>";
    
    // Vérifier si un administrateur existe déjà
    $stmt = $pdo->query("SELECT COUNT(*) FROM Administrateur");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Créer un administrateur par défaut
        $plainPassword = 'admin123';
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO Administrateur (email, motDePasse) VALUES (?, ?)");
        $stmt->execute(['admin@origamizen.fr', $hashedPassword]);
        
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
        echo "<h3>Compte administrateur créé avec succès !</h3>";
        echo "<p><strong>Email:</strong> admin@origamizen.fr</p>";
        echo "<p><strong>Mot de passe:</strong> admin123</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
        echo "<p>Un ou plusieurs administrateurs existent déjà dans la base de données.</p>";
        echo "</div>";
    }
    
    echo "<p><a href='login.php'>Retour à la page de connexion</a></p>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h3>Erreur lors de l'installation</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
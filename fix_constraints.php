<?php
// fix_constraints.php - Vérifier et réparer les contraintes FK
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h1>🔧 Vérification des contraintes de clés étrangères</h1>\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Vérifier les paniers orphelins
    echo "<h2>📊 Étape 1: Nettoyage des paniers orphelins</h2>\n";
    $stmt = $pdo->query("
        SELECT p.idPanier, p.idClient 
        FROM Panier p 
        LEFT JOIN Client c ON p.idClient = c.idClient 
        WHERE c.idClient IS NULL
    ");
    $orphelins = $stmt->fetchAll();
    
    if (count($orphelins) > 0) {
        echo "<p>⚠️ " . count($orphelins) . " panier(s) orphelin(s) trouvé(s):</p>\n";
        echo "<ul>\n";
        foreach ($orphelins as $p) {
            echo "<li>Panier #{$p['idPanier']} (client ID {$p['idClient']} inexistant)</li>\n";
        }
        echo "</ul>\n";
        
        // Supprimer les lignes des paniers orphelins d'abord
        foreach ($orphelins as $p) {
            $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ?");
            $stmt->execute([$p['idPanier']]);
            echo "<li>🗑️ Lignes du panier #{$p['idPanier']} supprimées</li>\n";
        }
        
        // Puis supprimer les paniers orphelins
        $stmt = $pdo->query("DELETE FROM Panier WHERE idPanier IN (SELECT idPanier FROM (SELECT p.idPanier FROM Panier p LEFT JOIN Client c ON p.idClient = c.idClient WHERE c.idClient IS NULL) AS tmp)");
        echo "<li>✅ " . $stmt->rowCount() . " panier(s) orphelin(s) supprimé(s)</li>\n";
    } else {
        echo "<p>✅ Aucun panier orphelin trouvé</p>\n";
    }
    
    // 2. Vérifier les clients temporaires invalides
    echo "<h2>📊 Étape 2: Nettoyage des clients temporaires invalides</h2>\n";
    $stmt = $pdo->query("
        SELECT idClient, email, date_creation 
        FROM Client 
        WHERE email LIKE 'temp_%@YoukiAndCo.fr' 
        AND date_creation < DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    $clientsTemp = $stmt->fetchAll();
    
    if (count($clientsTemp) > 0) {
        echo "<p>⚠️ " . count($clientsTemp) . " client(s) temporaire(s) obsolète(s):</p>\n";
        foreach ($clientsTemp as $c) {
            // Vérifier s'ils ont des commandes
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Commande WHERE idClient = ?");
            $stmtCheck->execute([$c['idClient']]);
            $hasCommandes = $stmtCheck->fetchColumn() > 0;
            
            if (!$hasCommandes) {
                $stmtDel = $pdo->prepare("DELETE FROM Client WHERE idClient = ?");
                $stmtDel->execute([$c['idClient']]);
                echo "<li>🗑️ Client #{$c['idClient']} ({$c['email']}) supprimé</li>\n";
            } else {
                echo "<li>⚠️ Client #{$c['idClient']} a des commandes - conservé</li>\n";
            }
        }
    } else {
        echo "<p>✅ Aucun client temporaire obsolète</p>\n";
    }
    
    // 3. Vérifier la contrainte FK
    echo "<h2>📊 Étape 3: Vérification de la contrainte FK</h2>\n";
    $stmt = $pdo->query("SHOW CREATE TABLE Panier");
    $createTable = $stmt->fetch();
    
    if (strpos($createTable[1], 'FOREIGN KEY') !== false) {
        echo "<p>✅ La contrainte de clé étrangère existe</p>\n";
        
        // Optionnel: recréer la contrainte avec ON DELETE CASCADE
        echo "<p>💡 Pour activer la suppression en cascade, exécutez:</p>\n";
        echo "<pre style='background:#f0f0f0;padding:10px;'>\n";
        echo "ALTER TABLE Panier DROP FOREIGN KEY Panier_idClient_FK;\n";
        echo "ALTER TABLE Panier ADD CONSTRAINT Panier_idClient_FK FOREIGN KEY (idClient) REFERENCES Client(idClient) ON DELETE CASCADE;\n";
        echo "</pre>\n";
    }
    
    // 4. Récapitulatif final
    echo "<h2>📊 Récapitulatif final</h2>\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM Client WHERE type = 'permanent'");
    $permanents = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) FROM Client WHERE email LIKE 'temp_%@YoukiAndCo.fr'");
    $temporaires = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) FROM Panier");
    $paniers = $stmt->fetchColumn();
    
    echo "<ul>\n";
    echo "<li>👤 Clients permanents: $permanents</li>\n";
    echo "<li>👥 Clients temporaires: $temporaires</li>\n";
    echo "<li>🛒 Paniers actifs: $paniers</li>\n";
    echo "</ul>\n";
    
    echo "<p style='color:green;font-weight:bold;'>✅ Nettoyage terminé!</p>\n";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Erreur: " . $e->getMessage() . "</p>\n";
}
?>
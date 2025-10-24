<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Configuration de la base de données
$host = 'localhost';
$dbname = 'origami';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['status' => 500, 'error' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]);
    exit;
}

// Récupération des données JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Vérification de l'action
$action = $data['action'] ?? ($_GET['action'] ?? '');

if (!$action) {
    echo json_encode(['status' => 400, 'error' => 'Action non spécifiée']);
    exit;
}

try {
    if ($action == 'creer_client') {
        // Action spécifique pour créer un client depuis la modal
        $email = $data['email'] ?? '';
        $nom = $data['nom'] ?? '';
        $prenom = $data['prenom'] ?? '';
        $telephone = $data['telephone'] ?? '';
        $motDePasse = $data['motDePasse'] ?? '';

        if (!$email || !$nom || !$prenom || !$motDePasse) {
            echo json_encode(['status' => 400, 'error' => 'Tous les champs obligatoires doivent être remplis']);
            exit;
        }

        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE email = ?");
        $stmt->execute([$email]);
        $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($clientExist) {
            echo json_encode(['status' => 409, 'error' => 'Un compte avec cet email existe déjà']);
            exit;
        }

        // Créer le nouveau client
        $motDePasseHash = password_hash($motDePasse, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$email, $motDePasseHash, $nom, $prenom, $telephone]);
        $idClient = $pdo->lastInsertId();

        // Créer un panier pour le nouveau client
        $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
        $stmt->execute([$idClient]);

        echo json_encode([
            'status' => 201, 
            'data' => [
                'idClient' => $idClient,
                'message' => 'Compte créé avec succès'
            ]
        ]);

    } elseif ($action == 'ajouter_au_panier') {
        $idClient = $data['idClient'] ?? null;
        $idOrigami = $data['idOrigami'] ?? null;
        $quantite = $data['quantite'] ?? 1;

        if (!$idClient || !$idOrigami) {
            echo json_encode(['status' => 400, 'error' => 'ID client ou ID origami manquant']);
            exit;
        }

        // Vérifier si le client existe
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE idClient = ?");
        $stmt->execute([$idClient]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            echo json_encode(['status' => 404, 'error' => 'Client non trouvé']);
            exit;
        }

        // Vérifier si le panier existe, sinon le créer
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

        // Récupérer le prix de l'origami
        $stmt = $pdo->prepare("SELECT prixHorsTaxe FROM Origami WHERE idOrigami = ?");
        $stmt->execute([$idOrigami]);
        $origami = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$origami) {
            echo json_encode(['status' => 404, 'error' => 'Origami non trouvé']);
            exit;
        }

        $prixUnitaire = $origami['prixHorsTaxe'];

        // Vérifier si l'article est déjà dans le panier
        $stmt = $pdo->prepare("SELECT idLignePanier, quantite FROM LignePanier WHERE idPanier = ? AND idOrigami = ?");
        $stmt->execute([$idPanier, $idOrigami]);
        $ligneExistante = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ligneExistante) {
            // Mettre à jour la quantité
            $nouvelleQuantite = $ligneExistante['quantite'] + $quantite;
            $stmt = $pdo->prepare("UPDATE LignePanier SET quantite = ?, prixUnitaire = ? WHERE idLignePanier = ?");
            $stmt->execute([$nouvelleQuantite, $prixUnitaire, $ligneExistante['idLignePanier']]);
        } else {
            // Ajouter une nouvelle ligne
            $stmt = $pdo->prepare("INSERT INTO LignePanier (idPanier, idOrigami, quantite, prixUnitaire) VALUES (?, ?, ?, ?)");
            $stmt->execute([$idPanier, $idOrigami, $quantite, $prixUnitaire]);
        }

        // Mettre à jour la date de modification du panier
        $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
        $stmt->execute([$idPanier]);

        echo json_encode(['status' => 200, 'message' => 'Article ajouté au panier']);

    } elseif ($action == 'get_panier') {
        $idClient = $_GET['idClient'] ?? null;

        if (!$idClient) {
            echo json_encode(['status' => 400, 'error' => 'ID client manquant']);
            exit;
        }

        // Vérifier si le client existe
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE idClient = ?");
        $stmt->execute([$idClient]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            echo json_encode(['status' => 404, 'error' => 'Client non trouvé']);
            exit;
        }

        // Récupérer le panier
        $stmt = $pdo->prepare("
            SELECT p.idPanier 
            FROM Panier p 
            WHERE p.idClient = ?
        ");
        $stmt->execute([$idClient]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si le panier n'existe pas, le créer et retourner un panier vide
        if (!$panier) {
            $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
            $stmt->execute([$idClient]);
            
            echo json_encode([
                'status' => 200, 
                'data' => [
                    'articles' => [], 
                    'total' => 0, 
                    'totalQuantites' => 0
                ]
            ]);
            exit;
        }

        // Récupérer les articles du panier avec les détails des origamis
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
            WHERE lp.idPanier = ?
        ");
        $stmt->execute([$panier['idPanier']]);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculer le total
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

    } elseif ($action == 'modifier_quantite') {
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

        // Mettre à jour la date du panier
        $stmt = $pdo->prepare("
            UPDATE Panier 
            SET dateModification = NOW() 
            WHERE idPanier = (SELECT idPanier FROM LignePanier WHERE idLignePanier = ?)
        ");
        $stmt->execute([$idLignePanier]);

        echo json_encode(['status' => 200, 'message' => 'Quantité modifiée']);

    } elseif ($action == 'supprimer_du_panier') {
        $idLignePanier = $data['idLignePanier'] ?? null;

        if (!$idLignePanier) {
            echo json_encode(['status' => 400, 'error' => 'ID ligne panier manquant']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idLignePanier = ?");
        $stmt->execute([$idLignePanier]);

        echo json_encode(['status' => 200, 'message' => 'Article supprimé du panier']);

    } elseif ($action == 'vider_panier') {
        $idClient = $data['idClient'] ?? null;

        if (!$idClient) {
            echo json_encode(['status' => 400, 'error' => 'ID client manquant']);
            exit;
        }

        // Récupérer l'ID du panier
        $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
        $stmt->execute([$idClient]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($panier) {
            $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ?");
            $stmt->execute([$panier['idPanier']]);

            // Mettre à jour la date de modification
            $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
            $stmt->execute([$panier['idPanier']]);
        }

        echo json_encode(['status' => 200, 'message' => 'Panier vidé']);

    } elseif ($action == 'creer_ou_maj_client') {
        $idClient = $data['idClient'] ?? null;
        $email = $data['email'] ?? '';
        $nom = $data['nom'] ?? '';
        $prenom = $data['prenom'] ?? '';
        $telephone = $data['telephone'] ?? '';

        if ((!$idClient && !$email) || !$nom || !$prenom) {
            echo json_encode(['status' => 400, 'error' => 'Champs obligatoires manquants']);
            exit;
        }

        // Si un ID client est fourni, mettre à jour ce client spécifique
        if ($idClient) {
            // Vérifier que le client existe
            $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE idClient = ?");
            $stmt->execute([$idClient]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($client) {
                // Mettre à jour le client existant
                $stmt = $pdo->prepare("UPDATE Client SET email = ?, nom = ?, prenom = ?, telephone = ? WHERE idClient = ?");
                $stmt->execute([$email, $nom, $prenom, $telephone, $idClient]);
                
                // S'assurer que le panier existe
                $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
                $stmt->execute([$idClient]);
                $panier = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$panier) {
                    $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
                    $stmt->execute([$idClient]);
                }
                
                echo json_encode([
                    'status' => 200, 
                    'data' => [
                        'idClient' => $idClient, 
                        'action' => 'updated',
                        'message' => 'Client mis à jour'
                    ]
                ]);
            } else {
                echo json_encode(['status' => 404, 'error' => 'Client non trouvé']);
            }
        } else {
            // Logique existante pour créer/mettre à jour par email
            $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE email = ?");
            $stmt->execute([$email]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($client) {
                // Mettre à jour le client existant
                $stmt = $pdo->prepare("UPDATE Client SET nom = ?, prenom = ?, telephone = ? WHERE idClient = ?");
                $stmt->execute([$nom, $prenom, $telephone, $client['idClient']]);
                
                // S'assurer que le panier existe
                $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
                $stmt->execute([$client['idClient']]);
                $panier = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$panier) {
                    $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
                    $stmt->execute([$client['idClient']]);
                }
                
                echo json_encode([
                    'status' => 200, 
                    'data' => [
                        'idClient' => $client['idClient'], 
                        'action' => 'updated',
                        'message' => 'Client mis à jour'
                    ]
                ]);
            } else {
                // Créer un nouveau client
                $motDePasse = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$email, $motDePasse, $nom, $prenom, $telephone]);
                $idClient = $pdo->lastInsertId();
                
                // Créer un panier pour le nouveau client
                $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
                $stmt->execute([$idClient]);
                
                echo json_encode([
                    'status' => 201, 
                    'data' => [
                        'idClient' => $idClient, 
                        'action' => 'created',
                        'message' => 'Client créé'
                    ]
                ]);
            }
        }

    } elseif ($action == 'creer_ou_maj_adresse') {
        $idClient = $data['idClient'] ?? null;
        $nom = $data['nom'] ?? '';
        $prenom = $data['prenom'] ?? '';
        $adresse = $data['adresse'] ?? '';
        $codePostal = $data['codePostal'] ?? '';
        $ville = $data['ville'] ?? '';
        $pays = $data['pays'] ?? 'France';
        $telephone = $data['telephone'] ?? '';
        $societe = $data['societe'] ?? '';

        if (!$idClient || !$nom || !$prenom || !$adresse || !$codePostal || !$ville) {
            echo json_encode(['status' => 400, 'error' => 'Champs obligatoires manquants']);
            exit;
        }

        // Vérifier si une adresse existe déjà pour ce client
        $stmt = $pdo->prepare("SELECT idAdresse FROM Adresse WHERE idClient = ?");
        $stmt->execute([$idClient]);
        $adresseExistante = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($adresseExistante) {
            // Mettre à jour l'adresse existante
            $stmt = $pdo->prepare("UPDATE Adresse SET nom = ?, prenom = ?, adresse = ?, codePostal = ?, ville = ?, pays = ?, telephone = ?, societe = ? WHERE idAdresse = ?");
            $stmt->execute([$nom, $prenom, $adresse, $codePostal, $ville, $pays, $telephone, $societe, $adresseExistante['idAdresse']]);
            echo json_encode([
                'status' => 200, 
                'data' => [
                    'idAdresse' => $adresseExistante['idAdresse'], 
                    'action' => 'updated',
                    'message' => 'Adresse mise à jour'
                ]
            ]);
        } else {
            // Créer une nouvelle adresse
            $stmt = $pdo->prepare("INSERT INTO Adresse (idClient, nom, prenom, adresse, codePostal, ville, pays, telephone, societe) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$idClient, $nom, $prenom, $adresse, $codePostal, $ville, $pays, $telephone, $societe]);
            $idAdresse = $pdo->lastInsertId();
            echo json_encode([
                'status' => 201, 
                'data' => [
                    'idAdresse' => $idAdresse, 
                    'action' => 'created',
                    'message' => 'Adresse créée'
                ]
            ]);
        }

    } elseif ($action == 'creer_commande') {
        $idClient = $data['idClient'] ?? null;
        $idAdresseLivraison = $data['idAdresseLivraison'] ?? null;
        $modeReglement = $data['modeReglement'] ?? 'CB';
        $fraisDePort = $data['fraisDePort'] ?? 5.9;

        if (!$idClient || !$idAdresseLivraison) {
            echo json_encode(['status' => 400, 'error' => 'ID client ou adresse de livraison manquant']);
            exit;
        }

        // Récupérer le panier du client
        $stmt = $pdo->prepare("
            SELECT p.idPanier 
            FROM Panier p 
            WHERE p.idClient = ?
        ");
        $stmt->execute([$idClient]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$panier) {
            echo json_encode(['status' => 404, 'error' => 'Panier non trouvé']);
            exit;
        }

        // Récupérer les articles du panier
        $stmt = $pdo->prepare("
            SELECT lp.idOrigami, lp.quantite, lp.prixUnitaire, o.nom
            FROM LignePanier lp
            JOIN Origami o ON lp.idOrigami = o.idOrigami
            WHERE lp.idPanier = ?
        ");
        $stmt->execute([$panier['idPanier']]);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($articles)) {
            echo json_encode(['status' => 400, 'error' => 'Le panier est vide']);
            exit;
        }

        // Calculer le total des articles
        $totalArticles = 0;
        foreach ($articles as $article) {
            $totalArticles += $article['quantite'] * $article['prixUnitaire'];
        }

        $montantTotal = $totalArticles + $fraisDePort;
        $delaiLivraison = date('Y-m-d', strtotime('+7 days')); // Livraison dans 7 jours

        // Démarrer une transaction
        $pdo->beginTransaction();

        try {
            // Créer la commande
            $stmt = $pdo->prepare("
                INSERT INTO Commande (idClient, idAdresseLivraison, dateCommande, modeReglement, delaiLivraison, fraisDePort, montantTotal, statut) 
                VALUES (?, ?, NOW(), ?, ?, ?, ?, 'en_attente')
            ");
            $stmt->execute([$idClient, $idAdresseLivraison, $modeReglement, $delaiLivraison, $fraisDePort, $montantTotal]);
            $idCommande = $pdo->lastInsertId();

            // Créer les lignes de commande
            $stmt = $pdo->prepare("
                INSERT INTO LigneCommande (idCommande, idOrigami, quantite, prixUnitaire) 
                VALUES (?, ?, ?, ?)
            ");

            foreach ($articles as $article) {
                $stmt->execute([$idCommande, $article['idOrigami'], $article['quantite'], $article['prixUnitaire']]);
            }

            // Vider le panier
            $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ?");
            $stmt->execute([$panier['idPanier']]);

            // Mettre à jour la date de modification du panier
            $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
            $stmt->execute([$panier['idPanier']]);

            // Valider la transaction
            $pdo->commit();

            echo json_encode([
                'status' => 201,
                'data' => [
                    'numeroCommande' => $idCommande,
                    'montantTotal' => $montantTotal,
                    'delaiLivraison' => $delaiLivraison,
                    'message' => 'Commande créée avec succès'
                ]
            ]);

        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            $pdo->rollBack();
            throw $e;
        }

    } else {
        echo json_encode(['status' => 400, 'error' => 'Action non reconnue: ' . $action]);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 500, 'error' => 'Erreur base de données: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['status' => 500, 'error' => 'Erreur: ' . $e->getMessage()]);
}
?>
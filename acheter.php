<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Inclure PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

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

// Fonction pour envoyer un email avec PHPMailer
function envoyerEmail($destinataire, $sujet, $message) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration du serveur SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'lhpp.philippe@gmail.com';
        $mail->Password = 'lvpk zqjt vuon qyrz';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        
        // Options de sécurité
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Destinataires
        $mail->setFrom('lhpp.philippe@gmail.com', 'Origami Zen');
        $mail->addAddress($destinataire);
        $mail->addReplyTo('contact@origamizen.fr', 'Origami Zen');
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        if ($mail->send()) {
            return ['success' => true, 'message' => 'Email envoyé avec succès'];
        } else {
            return ['success' => false, 'error' => 'Échec de l\'envoi sans exception'];
        }
        
    } catch (Exception $e) {
        error_log("Erreur PHPMailer: " . $mail->ErrorInfo);
        return ['success' => false, 'error' => 'Erreur PHPMailer: ' . $e->getMessage()];
    }
}

// Fonction pour générer un token de confirmation
function genererTokenConfirmation() {
    return bin2hex(random_bytes(32));
}

// Gestion des clients temporaires
function getOrCreateClient($pdo) {
    if (isset($_SESSION['client_id'])) {
        return $_SESSION['client_id'];
    }
    
    // Créer un client temporaire
    $stmt = $pdo->prepare("INSERT INTO Client (email, nom, prenom, type, date_creation, session_id) VALUES (?, 'Invité', 'Client', 'temporaire', NOW(), ?)");
    $emailTemp = 'temp_' . uniqid() . '@origamizen.fr';
    $sessionId = session_id();
    $stmt->execute([$emailTemp, $sessionId]);
    $clientId = $pdo->lastInsertId();
    
    $_SESSION['client_id'] = $clientId;
    return $clientId;
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
    // Récupérer l'ID client (créer si nécessaire) - pour toutes les actions sauf création client
    if ($action !== 'creer_client' && $action !== 'creer_ou_maj_client' && $action !== 'envoyer_lien_confirmation' && $action !== 'confirmer_commande') {
        $idClient = getOrCreateClient($pdo);
    }

    if ($action == 'envoyer_lien_confirmation') {
        $email = $data['email'] ?? '';
        $nom = $data['nom'] ?? 'Client';

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 400, 'error' => 'Email invalide']);
            exit;
        }

        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT idClient, nom, prenom FROM Client WHERE email = ? AND type = 'permanent'");
        $stmt->execute([$email]);
        $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);

        $clientExistant = ($clientExist !== false);
        $tokenConfirmation = genererTokenConfirmation();

        // Stocker le token dans la session
        $_SESSION['token_confirmation'] = $tokenConfirmation;
        $_SESSION['email_confirmation'] = $email;
        $_SESSION['token_expiration'] = time() + 900; // 15 minutes

        // URL de confirmation (à adapter selon votre domaine)
        $urlConfirmation = "https://votresite.com/confirmer-commande?token=" . $tokenConfirmation;

        // Préparer l'email HTML avec le lien de confirmation
        $sujet = "Confirmez votre commande - Origami Zen";
        $messageHTML = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Confirmation de commande</title>
            <style>
                body { 
                    font-family: 'Helvetica Neue', Arial, sans-serif; 
                    background-color: #f9f9f9; 
                    margin: 0; 
                    padding: 0; 
                    color: #333;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white; 
                    padding: 40px; 
                    border-radius: 8px; 
                    box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
                }
                .header { 
                    text-align: center; 
                    color: #d40000; 
                    margin-bottom: 30px; 
                    border-bottom: 2px solid #f0f0f0;
                    padding-bottom: 20px;
                }
                .header h1 { 
                    font-size: 28px; 
                    margin: 0; 
                    font-weight: 300;
                }
                .btn-confirmation { 
                    display: block; 
                    width: 250px; 
                    margin: 30px auto; 
                    padding: 15px 30px; 
                    background-color: #d40000; 
                    color: white; 
                    text-decoration: none; 
                    text-align: center; 
                    border-radius: 5px; 
                    font-size: 18px; 
                    font-weight: bold;
                }
                .btn-confirmation:hover {
                    background-color: #b30000;
                }
                .footer { 
                    margin-top: 40px; 
                    padding-top: 20px; 
                    border-top: 1px solid #eee; 
                    color: #666; 
                    font-size: 14px; 
                    text-align: center;
                }
                .content { 
                    line-height: 1.6; 
                    font-size: 16px;
                }
                .warning { 
                    background: #fff3cd; 
                    border: 1px solid #ffeaa7; 
                    padding: 15px; 
                    border-radius: 4px; 
                    margin: 20px 0; 
                    color: #856404;
                }
                .url-backup {
                    word-break: break-all;
                    font-size: 14px;
                    color: #666;
                    text-align: center;
                    margin-top: 10px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Origami Zen</h1>
                    <h2>Confirmation de votre commande</h2>
                </div>
                
                <div class='content'>
                    <p>Bonjour <strong>" . htmlspecialchars($nom) . "</strong>,</p>
                    
                    <p>Pour finaliser votre commande sur Origami Zen, veuillez cliquer sur le bouton de confirmation ci-dessous :</p>
                    
                    <a href='" . $urlConfirmation . "' class='btn-confirmation'>Confirmer ma commande</a>
                    
                    <div class='url-backup'>
                        Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
                        " . $urlConfirmation . "
                    </div>
                    
                    <div class='warning'>
                        <strong>⚠️ Important :</strong> Ce lien est valable pendant <strong>15 minutes</strong> seulement.
                    </div>
                    
                    <p>Si vous n'avez pas initié cette demande, veuillez ignorer cet email.</p>
                </div>
                
                <div class='footer'>
                    <p><strong>Origami Zen - Créations artisanales japonaises</strong></p>
                    <p>📧 contact@origamizen.fr | 📞 +33 1 23 45 67 89</p>
                    <p>123 Rue du Papier, 75000 Paris, France</p>
                </div>
            </div>
        </body>
        </html>
        ";

        // Envoyer l'email avec PHPMailer
        $resultatEmail = envoyerEmail($email, $sujet, $messageHTML);

        if ($resultatEmail['success']) {
            echo json_encode([
                'status' => 200,
                'data' => [
                    'message' => 'Lien de confirmation envoyé',
                    'client_existant' => $clientExistant
                ]
            ]);
        } else {
            error_log("Échec envoi email à: " . $email . " - Erreur: " . $resultatEmail['error']);
            echo json_encode([
                'status' => 500, 
                'error' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.',
                'debug' => $resultatEmail['error'] // À retirer en production
            ]);
        }

    } elseif ($action == 'confirmer_commande') {
        $token = $data['token'] ?? '';
        $email = $data['email'] ?? '';

        if (!$token || !$email) {
            echo json_encode(['status' => 400, 'error' => 'Token ou email manquant']);
            exit;
        }

        // Vérifier le token dans la session
        $tokenStocke = $_SESSION['token_confirmation'] ?? '';
        $emailStocke = $_SESSION['email_confirmation'] ?? '';
        $expiration = $_SESSION['token_expiration'] ?? 0;

        if ($email !== $emailStocke) {
            echo json_encode(['status' => 400, 'error' => 'Email ne correspond pas']);
            exit;
        }

        if (time() > $expiration) {
            echo json_encode(['status' => 400, 'error' => 'Lien expiré. Veuillez demander un nouveau lien.']);
            exit;
        }

        if ($token === $tokenStocke) {
            // Token correct - nettoyer la session et confirmer la commande
            unset($_SESSION['token_confirmation'], $_SESSION['token_expiration']);
            
            // Ici vous pouvez ajouter la logique pour finaliser la commande
            // Par exemple, créer la commande en base de données, etc.
            
            echo json_encode([
                'status' => 200,
                'data' => [
                    'message' => 'Commande confirmée avec succès',
                    'email' => $email
                ]
            ]);
        } else {
            echo json_encode(['status' => 400, 'error' => 'Token invalide']);
        }

    } elseif ($action == 'creer_client') {
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
        $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone, type) VALUES (?, ?, ?, ?, ?, 'permanent')");
        $stmt->execute([$email, $motDePasseHash, $nom, $prenom, $telephone]);
        $idClient = $pdo->lastInsertId();

        // Créer un panier pour le nouveau client
        $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
        $stmt->execute([$idClient]);

        // Envoyer un email de bienvenue
        $sujetBienvenue = "Bienvenue chez Origami Zen !";
        $messageBienvenue = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
                .header { text-align: center; color: #d40000; margin-bottom: 30px; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 14px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Origami Zen</h1>
                    <h2>Bienvenue dans notre communauté !</h2>
                </div>
                
                <p>Bonjour " . htmlspecialchars($prenom) . ",</p>
                
                <p>Merci d'avoir créé un compte sur Origami Zen. Nous sommes ravis de vous accueillir !</p>
                
                <p>Vous pouvez maintenant :</p>
                <ul>
                    <li>Accéder à votre historique de commandes</li>
                    <li>Enregistrer vos adresses de livraison</li>
                    <li>Bénéficier de nos offres exclusives</li>
                </ul>
                
                <p>N'hésitez pas à découvrir nos dernières créations d'origami.</p>
                
                <div class='footer'>
                    <p>Cordialement,<br>L'équipe Origami Zen</p>
                    <p>contact@origamizen.fr</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $resultatEmail = envoyerEmail($email, $sujetBienvenue, $messageBienvenue);
        if (!$resultatEmail['success']) {
            error_log("Échec envoi email de bienvenue à: " . $email);
        }

        echo json_encode([
            'status' => 201, 
            'data' => [
                'idClient' => $idClient,
                'message' => 'Compte créé avec succès'
            ]
        ]);

    } elseif ($action == 'ajouter_au_panier') {
        $idOrigami = $data['idOrigami'] ?? null;
        $quantite = $data['quantite'] ?? 1;

        if (!$idOrigami) {
            echo json_encode(['status' => 400, 'error' => 'ID origami manquant']);
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
        $email = $data['email'] ?? '';
        $nom = $data['nom'] ?? '';
        $prenom = $data['prenom'] ?? '';
        $telephone = $data['telephone'] ?? '';

        if (!$email || !$nom || !$prenom) {
            echo json_encode(['status' => 400, 'error' => 'Champs obligatoires manquants']);
            exit;
        }

        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE email = ?");
        $stmt->execute([$email]);
        $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($clientExist) {
            // Mettre à jour le client existant
            $stmt = $pdo->prepare("UPDATE Client SET nom = ?, prenom = ?, telephone = ?, type = 'permanent' WHERE idClient = ?");
            $stmt->execute([$nom, $prenom, $telephone, $clientExist['idClient']]);
            
            // Si l'utilisateur actuel a un panier temporaire, le transférer vers le client permanent
            if (isset($_SESSION['client_id'])) {
                $stmt = $pdo->prepare("UPDATE Panier SET idClient = ? WHERE idClient = ?");
                $stmt->execute([$clientExist['idClient'], $_SESSION['client_id']]);
                $_SESSION['client_id'] = $clientExist['idClient'];
            }
            
            echo json_encode([
                'status' => 200, 
                'data' => [
                    'idClient' => $clientExist['idClient'], 
                    'action' => 'updated',
                    'message' => 'Client mis à jour'
                ]
            ]);
        } else {
            // Créer un nouveau client permanent
            $motDePasse = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone, type) VALUES (?, ?, ?, ?, ?, 'permanent')");
            $stmt->execute([$email, $motDePasse, $nom, $prenom, $telephone]);
            $nouveauClientId = $pdo->lastInsertId();
            
            // Si l'utilisateur actuel a un panier temporaire, le transférer vers le nouveau client
            if (isset($_SESSION['client_id'])) {
                $stmt = $pdo->prepare("UPDATE Panier SET idClient = ? WHERE idClient = ?");
                $stmt->execute([$nouveauClientId, $_SESSION['client_id']]);
                $_SESSION['client_id'] = $nouveauClientId;
            } else {
                // Créer un panier pour le nouveau client
                $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
                $stmt->execute([$nouveauClientId]);
            }
            
            echo json_encode([
                'status' => 201, 
                'data' => [
                    'idClient' => $nouveauClientId, 
                    'action' => 'created',
                    'message' => 'Client créé'
                ]
            ]);
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
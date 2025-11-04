<?php
session_start();

// DÉBUT CRITIQUE : Gestion intelligente des en-têtes
$is_html_response = false;

// Détecter si c'est une requête de confirmation HTML
if (isset($_GET['token']) && (!isset($_POST['action']))) {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
} else {
    // Pour toutes les autres requêtes (AJAX/API)
    header('Content-Type: application/json');
}

// En-têtes CORS pour toutes les requêtes
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}
// FIN CRITIQUE

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
    if ($is_html_response) {
        echo "Erreur de connexion à la base de données";
        exit;
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]);
        exit;
    }
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
        $mail->addReplyTo('lhpp.philippe@gmail.com', 'Origami Zen');
        
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

// Fonction pour nettoyer les tokens expirés
function nettoyerTokensExpires($pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM tokens_confirmation WHERE expiration < NOW() OR utilise = 1");
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Erreur nettoyage tokens: " . $e->getMessage());
    }
}

// Fonction pour nettoyer les clients temporaires anciens
function nettoyerClientsTemporaires($pdo) {
    try {
        // Vérifier si la colonne type existe avant de nettoyer
        $stmt = $pdo->prepare("DELETE FROM Client WHERE type = 'temporaire' AND date_creation < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $stmt->execute();
        $count = $stmt->rowCount();
        if ($count > 0) {
            error_log("Nettoyage clients temporaires: " . $count . " clients supprimés");
        }
    } catch (Exception $e) {
        error_log("Erreur nettoyage clients temporaires (colonne type probablement manquante): " . $e->getMessage());
    }
}

// Gestion des clients temporaires - VERSION COMPATIBLE (avec ou sans colonne type)
function getOrCreateClient($pdo) {
    // Vérifier d'abord si on a déjà un client_id en session valide
    if (isset($_SESSION['client_id'])) {
        // Vérifier que ce client existe encore en base
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE idClient = ?");
        $stmt->execute([$_SESSION['client_id']]);
        if ($stmt->fetch()) {
            return $_SESSION['client_id'];
        }
        // Si n'existe pas, nettoyer la session
        unset($_SESSION['client_id']);
    }
    
    // Vérifier s'il existe un client temporaire avec cette session
    $sessionId = session_id();
    
    try {
        // Essayer de chercher avec la colonne type
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE session_id = ? AND type = 'temporaire'");
        $stmt->execute([$sessionId]);
        $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Si échec (colonne type manquante), chercher simplement par session_id
        error_log("Colonne type non trouvée, utilisation de session_id uniquement");
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($clientExist) {
        $_SESSION['client_id'] = $clientExist['idClient'];
        return $clientExist['idClient'];
    }
    
    // Créer un nouveau client temporaire
    try {
        // Essayer d'insérer avec la colonne type
        $stmt = $pdo->prepare("INSERT INTO Client (email, nom, prenom, type, date_creation, session_id) VALUES (?, 'Invité', 'Client', 'temporaire', NOW(), ?)");
        $emailTemp = 'temp_' . uniqid() . '@origamizen.fr';
        $stmt->execute([$emailTemp, $sessionId]);
    } catch (Exception $e) {
        // Si échec (colonne type manquante), insérer sans type
        error_log("Insertion sans colonne type: " . $e->getMessage());
        try {
            // Essayer avec date_creation
            $stmt = $pdo->prepare("INSERT INTO Client (email, nom, prenom, date_creation, session_id) VALUES (?, 'Invité', 'Client', NOW(), ?)");
            $emailTemp = 'temp_' . uniqid() . '@origamizen.fr';
            $stmt->execute([$emailTemp, $sessionId]);
        } catch (Exception $e2) {
            // Si échec (date_creation manquante), insérer avec les colonnes minimales
            error_log("Insertion avec colonnes minimales: " . $e2->getMessage());
            $stmt = $pdo->prepare("INSERT INTO Client (email, nom, prenom, session_id) VALUES (?, 'Invité', 'Client', ?)");
            $emailTemp = 'temp_' . uniqid() . '@origamizen.fr';
            $stmt->execute([$emailTemp, $sessionId]);
        }
    }
    
    $clientId = $pdo->lastInsertId();
    $_SESSION['client_id'] = $clientId;
    return $clientId;
}

// Récupération des données JSON (uniquement pour les requêtes API)
if (!$is_html_response) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
} else {
    $data = [];
}

// Vérification de l'action
$action = $data['action'] ?? ($_GET['action'] ?? '');

// Si pas d'action mais un token dans GET, c'est une confirmation directe
if (!$action && isset($_GET['token'])) {
    $action = 'confirmer_commande';
    $is_html_response = true;
}

// Si action=saisir_adresse avec token, afficher formulaire HTML
if ($action == 'saisir_adresse' && isset($_GET['token'])) {
    $is_html_response = true;
    header('Content-Type: text/html; charset=UTF-8');
}

if (!$action) {
    if ($is_html_response) {
        echo "Action non spécifiée";
        exit;
    } else {
        echo json_encode(['status' => 400, 'error' => 'Action non spécifiée']);
        exit;
    }
}

try {
    // Nettoyer les tokens expirés périodiquement (1 chance sur 10)
    if (rand(1, 10) === 1) {
        nettoyerTokensExpires($pdo);
    }
    
    // Nettoyer les clients temporaires anciens périodiquement (1 chance sur 20)
    if (rand(1, 20) === 1) {
        nettoyerClientsTemporaires($pdo);
    }

    // CORRECTION CRITIQUE : Seulement créer un client pour les actions qui en ont vraiment besoin
    $actionsNecessitantClient = [
        'ajouter_au_panier', 
        'get_panier', 
        'modifier_quantite', 
        'supprimer_du_panier', 
        'vider_panier'
    ];

    if (in_array($action, $actionsNecessitantClient)) {
        $idClient = getOrCreateClient($pdo);
    } else {
        $idClient = null;
    }

    if ($action == 'envoyer_lien_confirmation') {
        $email = $data['email'] ?? '';
        $nom = $data['nom'] ?? 'Client';
        $prenom = $data['prenom'] ?? '';
        $telephone = $data['telephone'] ?? '';

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 400, 'error' => 'Email invalide']);
            exit;
        }

        // Vérifier si l'email existe déjà - VERSION COMPATIBLE
        try {
        $stmt = $pdo->prepare("SELECT idClient, nom, prenom FROM Client WHERE email = ? AND type = 'permanent'");
        $stmt->execute([$email]);
        $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
    // Attraper spécifiquement PDOException
    if (strpos($e->getMessage(), "Column not found") !== false || strpos($e->getMessage(), "Unknown column") !== false) {
        error_log("Colonne type manquante, recherche par email uniquement");
        $stmt = $pdo->prepare("SELECT idClient, nom, prenom FROM Client WHERE email = ?");
        $stmt->execute([$email]);
        $clientExist = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Relancer l'exception si c'est une autre erreur
        throw $e;
    }
    }

        $clientExistant = ($clientExist !== false);
        
        // SI LE CLIENT N'EXISTE PAS, LE CRÉER MAINTENANT - VERSION COMPATIBLE
        if (!$clientExistant) {
            // Créer le client permanent - VERSION COMPATIBLE
            $motDePasse = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone, type) VALUES (?, ?, ?, ?, ?, 'permanent')");
                $stmt->execute([$email, $motDePasse, $nom, $prenom, $telephone]);
            } catch (Exception $e) {
                // Si colonne type manquante, insérer sans type
                error_log("Insertion client sans colonne type");
                $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$email, $motDePasse, $nom, $prenom, $telephone]);
            }
            $idClient = $pdo->lastInsertId();
        } else {
            $idClient = $clientExist['idClient'];
        }

        // GESTION DU PANIER SANS DOUBLONS
        $idClientTemporaire = $_SESSION['client_id'] ?? null;
        
        // Vérifier si le client permanent a déjà un panier
        $stmt = $pdo->prepare("SELECT idPanier FROM Panier WHERE idClient = ?");
        $stmt->execute([$idClient]);
        $panierPermanent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$panierPermanent) {
            // Le client permanent n'a pas de panier, on en crée un
            $stmt = $pdo->prepare("INSERT INTO Panier (idClient, dateModification) VALUES (?, NOW())");
            $stmt->execute([$idClient]);
            $panierPermanent = ['idPanier' => $pdo->lastInsertId()];
        }
        
        // Si on a un client temporaire avec un panier, transférer les articles
        if ($idClientTemporaire && $idClientTemporaire != $idClient) {
            try {
                // Vérifier si le client temporaire a des articles dans son panier
                $stmt = $pdo->prepare("
                    SELECT lp.idLignePanier, lp.idOrigami, lp.quantite, lp.prixUnitaire 
                    FROM LignePanier lp 
                    JOIN Panier p ON lp.idPanier = p.idPanier 
                    WHERE p.idClient = ?
                ");
                $stmt->execute([$idClientTemporaire]);
                $articlesTemporaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($articlesTemporaires)) {
                    // Transférer chaque article du panier temporaire vers le panier permanent
                    foreach ($articlesTemporaires as $article) {
                        // Vérifier si l'article existe déjà dans le panier permanent
                        $stmt = $pdo->prepare("SELECT idLignePanier, quantite FROM LignePanier WHERE idPanier = ? AND idOrigami = ?");
                        $stmt->execute([$panierPermanent['idPanier'], $article['idOrigami']]);
                        $articleExistant = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($articleExistant) {
                            // Mettre à jour la quantité
                            $nouvelleQuantite = $articleExistant['quantite'] + $article['quantite'];
                            $stmt = $pdo->prepare("UPDATE LignePanier SET quantite = ? WHERE idLignePanier = ?");
                            $stmt->execute([$nouvelleQuantite, $articleExistant['idLignePanier']]);
                        } else {
                            // Ajouter un nouvel article
                            $stmt = $pdo->prepare("INSERT INTO LignePanier (idPanier, idOrigami, quantite, prixUnitaire) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$panierPermanent['idPanier'], $article['idOrigami'], $article['quantite'], $article['prixUnitaire']]);
                        }
                    }
                    
                    // Supprimer le panier temporaire
                    $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier IN (SELECT idPanier FROM Panier WHERE idClient = ?)");
                    $stmt->execute([$idClientTemporaire]);
                    
                    $stmt = $pdo->prepare("DELETE FROM Panier WHERE idClient = ?");
                    $stmt->execute([$idClientTemporaire]);
                    
                    error_log("Articles transférés du client temporaire " . $idClientTemporaire . " vers le client permanent " . $idClient);
                }
                
                // Mettre à jour la session
                $_SESSION['client_id'] = $idClient;
                
            } catch (Exception $e) {
                error_log("Erreur transfert panier: " . $e->getMessage());
            }
        }

        $tokenConfirmation = genererTokenConfirmation();
        //$expiration = date('Y-m-d H:i:s', time() + 900); // 15 minutes

        // Stocker le token avec vérification
        try {
            $stmt = $pdo->prepare("INSERT INTO tokens_confirmation (token, email, id_client, expiration, utilise) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)");
            $stmt->execute([$tokenConfirmation, $email, $idClient]);
            // Vérifier que l'insertion a réussi
            if ($stmt->rowCount() === 0) {
                throw new Exception("Échec de l'insertion du token");
            }
            
            error_log("Token créé pour client ID: " . $idClient . ", email: " . $email);
            
        } catch (Exception $e) {
            error_log("ERREUR insertion token: " . $e->getMessage());
            echo json_encode(['status' => 500, 'error' => 'Erreur technique lors de la création du lien de confirmation']);
            exit;
        }

        // URL de confirmation pointant vers acheter.php
        $urlConfirmation = "http://" . $_SERVER['HTTP_HOST'] . "/Origami/acheter.php?action=confirmer_commande&token=" . $tokenConfirmation;

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
                    'client_existant' => $clientExistant,
                    'id_client' => $idClient
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

    } elseif ($action == 'saisir_adresse' && isset($_GET['token'])) {
        // Afficher le formulaire de saisie d'adresse
        $token = $_GET['token'];
        
        // Vérifier la validité du token avec plus de détails
        $stmt = $pdo->prepare("SELECT email, id_client, expiration, utilise FROM tokens_confirmation WHERE token = ?");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData) {
            echo "<script>alert('Lien invalide'); window.location.href = 'index.html';</script>";
            exit;
        }

        if ($tokenData['utilise'] == 1) {
            echo "<script>alert('Ce lien a déjà été utilisé'); window.location.href = 'index.html';</script>";
            exit;
        }

        if (strtotime($tokenData['expiration']) < time()) {
            echo "<script>alert('Lien expiré'); window.location.href = 'index.html';</script>";
            exit;
        }

        // Récupérer les infos du client pour pré-remplir le formulaire
        $stmt = $pdo->prepare("SELECT nom, prenom, email FROM Client WHERE idClient = ?");
        $stmt->execute([$tokenData['id_client']]);
        $clientInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Saisie d'adresse - Origami Zen</title>
            <style>
                body { 
                    font-family: 'Helvetica Neue', Arial, sans-serif; 
                    background-color: #f9f9f9; 
                    margin: 0; 
                    padding: 20px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }
                .container { 
                    max-width: 500px; 
                    background: white; 
                    padding: 40px; 
                    border-radius: 8px; 
                    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                }
                .header { 
                    text-align: center; 
                    color: #d40000; 
                    margin-bottom: 30px; 
                }
                .form-group { 
                    margin-bottom: 20px; 
                }
                label { 
                    display: block; 
                    margin-bottom: 5px; 
                    font-weight: bold; 
                }
                input, select { 
                    width: 100%; 
                    padding: 10px; 
                    border: 1px solid #ddd; 
                    border-radius: 4px; 
                    box-sizing: border-box;
                }
                .btn { 
                    background-color: #d40000; 
                    color: white; 
                    padding: 12px 30px; 
                    border: none; 
                    border-radius: 4px; 
                    cursor: pointer; 
                    width: 100%; 
                    font-size: 16px;
                }
                .btn:hover {
                    background-color: #b30000;
                }
                .required { 
                    color: #d40000; 
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📦 Adresse de Livraison</h1>
                    <p>Complétez votre adresse pour finaliser la commande</p>
                </div>
                
                <form id="formAdresse">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="action" value="sauvegarder_adresse">
                    
                    <div class="form-group">
                        <label for="nom">Nom <span class="required">*</span></label>
                        <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($clientInfo['nom'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="prenom">Prénom <span class="required">*</span></label>
                        <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($clientInfo['prenom'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="rue">Adresse <span class="required">*</span></label>
                        <input type="text" id="adresse" name="adresse" placeholder="Numéro et nom de rue" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="codePostal">Code Postal <span class="required">*</span></label>
                        <input type="text" id="codePostal" name="codePostal" pattern="[0-9]{5}" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="ville">Ville <span class="required">*</span></label>
                        <input type="text" id="ville" name="ville" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="pays">Pays</label>
                        <select id="pays" name="pays">
                            <option value="France" selected>France</option>
                            <option value="Belgique">Belgique</option>
                            <option value="Suisse">Suisse</option>
                            <option value="Luxembourg">Luxembourg</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone" placeholder="+33 1 23 45 67 89">
                    </div>
                    
                    <button type="submit" class="btn">Finaliser la commande</button>
                </form>
            </div>

            <script>
                document.getElementById('formAdresse').addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const data = Object.fromEntries(formData.entries());
                    
                    try {
                        const response = await fetch('acheter.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(data)
                        });
                        
                        const result = await response.json();
                        
                        if (result.status === 200) {
                            // Rediriger vers la page de confirmation
                            window.location.href = 'acheter.php?action=confirmer_commande&token=' + data.token;
                        } else {
                            alert('Erreur: ' + result.error);
                        }
                    } catch (error) {
                        alert('Erreur lors de l\'envoi des données');
                    }
                });
            </script>
        </body>
        </html>
        <?php
        exit;

    } elseif ($action == 'sauvegarder_adresse') {
        // Sauvegarder l'adresse et continuer vers la confirmation
        $token = $data['token'] ?? '';
        $nom = $data['nom'] ?? '';
        $prenom = $data['prenom'] ?? '';
        $adresse = $data['adresse'] ?? '';
        $codePostal = $data['codePostal'] ?? '';
        $ville = $data['ville'] ?? '';
        $pays = $data['pays'] ?? 'France';
        $telephone = $data['telephone'] ?? '';

        if (!$token || !$nom || !$prenom || !$rue || !$codePostal || !$ville) {
            echo json_encode(['status' => 400, 'error' => 'Tous les champs obligatoires doivent être remplis']);
            exit;
        }

        // Vérifier le token avec plus de détails
        $stmt = $pdo->prepare("SELECT id_client, email, expiration, utilise FROM tokens_confirmation WHERE token = ?");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData) {
            error_log("Token non trouvé lors de sauvegarde adresse: " . $token);
            echo json_encode(['status' => 400, 'error' => 'Token invalide']);
            exit;
        }

        if ($tokenData['utilise'] == 1) {
            error_log("Token déjà utilisé lors de sauvegarde adresse: " . $token);
            echo json_encode(['status' => 400, 'error' => 'Ce lien a déjà été utilisé']);
            exit;
        }

        if (strtotime($tokenData['expiration']) < time()) {
            error_log("Token expiré lors de sauvegarde adresse: " . $token . ", expiration: " . $tokenData['expiration']);
            echo json_encode(['status' => 400, 'error' => 'Lien expiré. Veuillez demander un nouveau lien.']);
            exit;
        }

        $idClient = $tokenData['id_client'];

        // Créer l'adresse
        $stmt = $pdo->prepare("
            INSERT INTO Adresse 
            (idClient, type, nom, prenom, adresse, codePostal, ville, pays, telephone, dateCreation) 
            VALUES (?, 'livraison', ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$idClient, $nom, $prenom, $rue, $codePostal, $ville, $pays, $telephone]);

        echo json_encode(['status' => 200, 'message' => 'Adresse sauvegardée']);

    } elseif ($action == 'confirmer_commande') {
        // Forcer le type HTML si c'est une confirmation directe
        if (isset($_GET['token']) && !isset($data['token'])) {
            $is_html_response = true;
            header('Content-Type: text/html; charset=UTF-8');
        }
        
        // Récupérer le token depuis GET ou POST
        $token = $data['token'] ?? ($_GET['token'] ?? '');
        
        if (!$token) {
            // Si requête directe sans token, afficher erreur HTML
            if (isset($_GET['token'])) {
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Erreur de Confirmation</title>
                    <style>
                        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                        .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
                        .error { color: #dc3545; }
                        .btn { 
                            display: inline-block;
                            background-color: #d40000; 
                            color: white; 
                            padding: 12px 30px; 
                            text-decoration: none; 
                            border-radius: 2px; 
                            margin-top: 20px;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1 class="error">❌ Lien Invalide</h1>
                        <p>Le lien de confirmation est incomplet ou invalide.</p>
                        <a href="index.html" class="btn">Retour à l'accueil</a>
                    </div>
                </body>
                </html>
                <?php
                exit;
            } else {
                echo json_encode(['status' => 400, 'error' => 'Token manquant']);
                exit;
            }
        }

        // Vérifier le token avec plus de détails
        $stmt = $pdo->prepare("SELECT email, id_client, expiration, utilise FROM tokens_confirmation WHERE token = ?");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData) {
            // Token non trouvé
            error_log("Token non trouvé: " . $token);
            if (isset($_GET['token'])) {
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Lien Invalide</title>
                    <style>
                        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                        .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
                        .error { color: #dc3545; }
                        .btn { 
                            display: inline-block;
                            background-color: #d40000; 
                            color: white; 
                            padding: 12px 30px; 
                            text-decoration: none; 
                            border-radius: 2px; 
                            margin-top: 20px;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1 class="error">❌ Lien Invalide</h1>
                        <p>Ce lien de confirmation est invalide ou a déjà été utilisé.</p>
                        <a href="index.html" class="btn">Retour à l'accueil</a>
                    </div>
                </body>
                </html>
                <?php
                exit;
            } else {
                echo json_encode(['status' => 400, 'error' => 'Token invalide']);
                exit;
            }
        }

        // Vérifier si le token a déjà été utilisé
        if ($tokenData['utilise'] == 1) {
            error_log("Token déjà utilisé: " . $token);
            if (isset($_GET['token'])) {
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Lien Déjà Utilisé</title>
                    <style>
                        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                        .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
                        .warning { color: #856404; }
                        .btn { 
                            display: inline-block;
                            background-color: #d40000; 
                            color: white; 
                            padding: 12px 30px; 
                            text-decoration: none; 
                            border-radius: 2px; 
                            margin-top: 20px;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1 class="warning">⚠️ Lien Déjà Utilisé</h1>
                        <p>Ce lien de confirmation a déjà été utilisé.</p>
                        <a href="index.html" class="btn">Retour à l'accueil</a>
                    </div>
                </body>
                </html>
                <?php
                exit;
            } else {
                echo json_encode(['status' => 400, 'error' => 'Token déjà utilisé']);
                exit;
            }
        }

        // Vérifier l'expiration
        if (strtotime($tokenData['expiration']) < time()) {
            error_log("Token expiré: " . $token . ", expiration: " . $tokenData['expiration']);
            if (isset($_GET['token'])) {
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Lien Expiré</title>
                    <style>
                        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                        .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
                        .warning { color: #856404; }
                        .btn { 
                            display: inline-block;
                            background-color: #d40000; 
                            color: white; 
                            padding: 12px 30px; 
                            text-decoration: none; 
                            border-radius: 2px; 
                            margin-top: 20px;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1 class="warning">⚠️ Lien Expiré</h1>
                        <p>Ce lien de confirmation a expiré. Veuillez demander un nouveau lien.</p>
                        <a href="index.html" class="btn">Retour à l'accueil</a>
                    </div>
                </body>
                </html>
                <?php
                exit;
            } else {
                echo json_encode(['status' => 400, 'error' => 'Lien expiré. Veuillez demander un nouveau lien.']);
                exit;
            }
        }

        // Token valide - marquer comme utilisé
        $stmt = $pdo->prepare("UPDATE tokens_confirmation SET utilise = 1 WHERE token = ?");
        $stmt->execute([$token]);

        $emailStocke = $tokenData['email'];
        $idClient = $tokenData['id_client'];
        
        // DÉBUT - Logique pour finaliser la commande
        
        // 1. Récupérer le panier du client
        $stmt = $pdo->prepare("
            SELECT p.idPanier 
            FROM Panier p 
            WHERE p.idClient = ?
        ");
        $stmt->execute([$idClient]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$panier) {
            if (isset($_GET['token'])) {
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Erreur</title>
                    <style>
                        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                        .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
                        .error { color: #dc3545; }
                        .btn { 
                            display: inline-block;
                            background-color: #d40000; 
                            color: white; 
                            padding: 12px 30px; 
                            text-decoration: none; 
                            border-radius: 2px; 
                            margin-top: 20px;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1 class="error">❌ Panier vide</h1>
                        <p>Votre panier est vide ou n'existe pas.</p>
                        <a href="index.html" class="btn">Retour à l'accueil</a>
                    </div>
                </body>
                </html>
                <?php
                exit;
            } else {
                echo json_encode(['status' => 404, 'error' => 'Panier non trouvé']);
                exit;
            }
        }
        
        // 2. Récupérer les articles du panier
        $stmt = $pdo->prepare("
            SELECT 
                lp.idOrigami,
                lp.quantite,
                lp.prixUnitaire,
                (lp.quantite * lp.prixUnitaire) as totalLigne
            FROM LignePanier lp
            WHERE lp.idPanier = ?
        ");
        $stmt->execute([$panier['idPanier']]);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($articles)) {
            if (isset($_GET['token'])) {
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Erreur</title>
                    <style>
                        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                        .container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
                        .error { color: #dc3545; }
                        .btn { 
                            display: inline-block;
                            background-color: #d40000; 
                            color: white; 
                            padding: 12px 30px; 
                            text-decoration: none; 
                            border-radius: 2px; 
                            margin-top: 20px;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1 class="error">❌ Panier vide</h1>
                        <p>Votre panier ne contient aucun article.</p>
                        <a href="index.html" class="btn">Retour à l'accueil</a>
                    </div>
                </body>
                </html>
                <?php
                exit;
            } else {
                echo json_encode(['status' => 400, 'error' => 'Panier vide']);
                exit;
            }
        }
        
        // 3. Calculer le total de la commande
        $total = 0;
        foreach ($articles as $article) {
            $total += $article['totalLigne'];
        }
        
        // 4. Récupérer l'adresse de livraison du client
        $stmt = $pdo->prepare("
            SELECT idAdresse 
            FROM Adresse 
            WHERE idClient = ? 
            ORDER BY idAdresse DESC 
            LIMIT 1
        ");
        $stmt->execute([$idClient]);
        $adresse = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$adresse) {
            // REDIRECTION VERS LE FORMULAIRE DE SAISIE D'ADRESSE
            if (isset($_GET['token'])) {
                // Remettre le token comme non utilisé pour permettre la saisie d'adresse
                $stmt = $pdo->prepare("UPDATE tokens_confirmation SET utilise = 0 WHERE token = ?");
                $stmt->execute([$token]);
                
                header('Location: acheter.php?action=saisir_adresse&token=' . $token);
                exit;
            } else {
                echo json_encode([
                    'status' => 400, 
                    'error' => 'Adresse manquante',
                    'redirect' => 'acheter.php?action=saisir_adresse&token=' . $token
                ]);
                exit;
            }
        }
        
        $idAdresseLivraison = $adresse['idAdresse'];
        
        // 5. Définir les paramètres de la commande
        $fraisDePort = 5.90; // Frais de port fixes
        $delaiLivraison = date('Y-m-d', strtotime('+5 days')); // Délai de 5 jours
        $montantTotal = $total + $fraisDePort;
        
        // 6. Créer la commande
        $stmt = $pdo->prepare("
            INSERT INTO Commande 
            (idClient, idAdresseLivraison, dateCommande, modeReglement, delaiLivraison, fraisDePort, montantTotal, statut) 
            VALUES (?, ?, NOW(), 'CB', ?, ?, ?, 'confirmee')
        ");
        $stmt->execute([$idClient, $idAdresseLivraison, $delaiLivraison, $fraisDePort, $montantTotal]);
        $idCommande = $pdo->lastInsertId();
        
        // 7. Créer les lignes de commande
        $stmtLigne = $pdo->prepare("
            INSERT INTO LigneCommande 
            (idCommande, idOrigami, quantite, prixUnitaire) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($articles as $article) {
            $stmtLigne->execute([
                $idCommande, 
                $article['idOrigami'], 
                $article['quantite'], 
                $article['prixUnitaire']
            ]);
        }
        
        // 8. Vider le panier
        $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idPanier = ?");
        $stmt->execute([$panier['idPanier']]);
        
        // 9. Mettre à jour la date de modification du panier
        $stmt = $pdo->prepare("UPDATE Panier SET dateModification = NOW() WHERE idPanier = ?");
        $stmt->execute([$panier['idPanier']]);
        
        // FIN - Logique pour finaliser la commande
        
        // Si c'est une requête directe (clic depuis email), afficher une page HTML
        if (isset($_GET['token'])) {
            ?>
            <!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Commande Confirmée - Origami Zen</title>
                <style>
                    body { 
                        font-family: 'Helvetica Neue', Arial, sans-serif; 
                        background-color: #f9f9f9; 
                        margin: 0; 
                        padding: 20px;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                    }
                    .container { 
                        max-width: 600px; 
                        background: white; 
                        padding: 40px; 
                        border-radius: 8px; 
                        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                        text-align: center;
                    }
                    .success { color: #28a745; }
                    .btn { 
                        display: inline-block;
                        background-color: #d40000; 
                        color: white; 
                        padding: 12px 30px; 
                        text-decoration: none; 
                        border-radius: 2px; 
                        margin-top: 20px;
                    }
                    .details {
                        text-align: left;
                        background: #f8f9fa;
                        padding: 20px;
                        border-radius: 4px;
                        margin: 20px 0;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1 class="success">✅ Commande Confirmée !</h1>
                    <p>Votre commande a été confirmée avec succès.</p>
                    
                    <div class="details">
                        <p><strong>Numéro de commande :</strong> #<?= $idCommande ?></p>
                        <p><strong>Montant total :</strong> <?= number_format($montantTotal, 2, ',', ' ') ?>€</p>
                        <p><strong>Livraison prévue :</strong> <?= date('d/m/Y', strtotime($delaiLivraison)) ?></p>
                        <p><strong>Email :</strong> <?= htmlspecialchars($emailStocke) ?></p>
                    </div>
                    
                    <p>Vous recevrez un email de confirmation sous peu.</p>
                    <a href="index.html" class="btn">Retour à l'accueil</a>
                </div>
            </body>
            </html>
            <?php
            exit; // Important : arrêter l'exécution après l'affichage HTML
        } else {
            // Réponse JSON pour les appels AJAX
            echo json_encode([
                'status' => 200,
                'data' => [
                    'message' => 'Commande confirmée avec succès',
                    'email' => $emailStocke,
                    'idCommande' => $idCommande,
                    'montantTotal' => $montantTotal,
                    'delaiLivraison' => $delaiLivraison
                ]
            ]);
        }
        
    } elseif ($action == 'creer_client') {
        // CODE POUR CREER_CLIENT - VERSION COMPATIBLE
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

        // Créer le nouveau client - VERSION COMPATIBLE
        $motDePasseHash = password_hash($motDePasse, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone, type) VALUES (?, ?, ?, ?, ?, 'permanent')");
            $stmt->execute([$email, $motDePasseHash, $nom, $prenom, $telephone]);
        } catch (Exception $e) {
            // Si colonne type manquante, insérer sans type
            error_log("Insertion client sans colonne type");
            $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$email, $motDePasseHash, $nom, $prenom, $telephone]);
        }
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
        // Vérifier qu'on a bien un client
        if (!$idClient) {
            echo json_encode(['status' => 400, 'error' => 'Client non initialisé']);
            exit;
        }

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
        // Vérifier qu'on a bien un client
        if (!$idClient) {
            // Retourner un panier vide si pas de client
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

        // Récupérer le panier
        $stmt = $pdo->prepare("
            SELECT p.idPanier 
            FROM Panier p 
            WHERE p.idClient = ?
        ");
        $stmt->execute([$idClient]);
        $panier = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si le panier n'existe pas, retourner un panier vide
        if (!$panier) {
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
        // Vérifier qu'on a bien un client
        if (!$idClient) {
            echo json_encode(['status' => 400, 'error' => 'Client non initialisé']);
            exit;
        }

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
        // Vérifier qu'on a bien un client
        if (!$idClient) {
            echo json_encode(['status' => 400, 'error' => 'Client non initialisé']);
            exit;
        }

        $idLignePanier = $data['idLignePanier'] ?? null;

        if (!$idLignePanier) {
            echo json_encode(['status' => 400, 'error' => 'ID ligne panier manquant']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM LignePanier WHERE idLignePanier = ?");
        $stmt->execute([$idLignePanier]);

        echo json_encode(['status' => 200, 'message' => 'Article supprimé du panier']);

    } elseif ($action == 'vider_panier') {
        // Vérifier qu'on a bien un client
        if (!$idClient) {
            echo json_encode(['status' => 400, 'error' => 'Client non initialisé']);
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
        // CODE POUR CREER_OU_MAJ_CLIENT - VERSION COMPATIBLE
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
            // Mettre à jour le client existant - VERSION COMPATIBLE
            try {
                $stmt = $pdo->prepare("UPDATE Client SET nom = ?, prenom = ?, telephone = ?, type = 'permanent' WHERE idClient = ?");
                $stmt->execute([$nom, $prenom, $telephone, $clientExist['idClient']]);
            } catch (Exception $e) {
                // Si colonne type manquante, mettre à jour sans type
                error_log("Mise à jour client sans colonne type");
                $stmt = $pdo->prepare("UPDATE Client SET nom = ?, prenom = ?, telephone = ? WHERE idClient = ?");
                $stmt->execute([$nom, $prenom, $telephone, $clientExist['idClient']]);
            }
            
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
            // Créer un nouveau client permanent - VERSION COMPATIBLE
            $motDePasse = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone, type) VALUES (?, ?, ?, ?, ?, 'permanent')");
                $stmt->execute([$email, $motDePasse, $nom, $prenom, $telephone]);
            } catch (Exception $e) {
                // Si colonne type manquante, insérer sans type
                error_log("Insertion client sans colonne type");
                $stmt = $pdo->prepare("INSERT INTO Client (email, motDePasse, nom, prenom, telephone) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$email, $motDePasse, $nom, $prenom, $telephone]);
            }
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
    if ($is_html_response) {
        echo "Erreur base de données: " . $e->getMessage();
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur base de données: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    if ($is_html_response) {
        echo "Erreur: " . $e->getMessage();
    } else {
        echo json_encode(['status' => 500, 'error' => 'Erreur: ' . $e->getMessage()]);
    }
}
?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Configuration de la base de données
$host = 'localhost';
$dbname = 'origami';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Configuration PayPal
$paypal_config = [
    'client_id' => 'Aac1-P0VrxBQ_5REVeo4f557_-p6BDeXA_hyiuVZfi21sILMWccBFfTidQ6nnhQathCbWaCSQaDmxJw5',
    'client_secret' => 'EJxech0i1faRYlo0-ln2sU09ecx5rP3XEOGUTeTduI2t-I0j4xoSPqRRFQTxQsJoSBbSL8aD1b1GPPG1',
    'environment' => 'sandbox',
    'currency' => 'EUR'
];

// Récupérer l'ID de commande
$idCommande = $_GET['commande'] ?? $_SESSION['paypal_commande_id'] ?? null;

if (!$idCommande) {
    header('Location: index.html');
    exit;
}

// Récupérer les informations de la commande
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.idCommande,
            c.montantTotal,
            c.statut,
            cl.email,
            cl.prenom,
            cl.nom,
            a_liv.adresse as adresse_livraison,
            a_liv.codePostal as cp_livraison,
            a_liv.ville as ville_livraison
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        JOIN Adresse a_liv ON c.idAdresseLivraison = a_liv.idAdresse
        WHERE c.idCommande = ? AND c.statut = 'en_attente_paiement'
    ");
    $stmt->execute([$idCommande]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        die("Commande non trouvée ou déjà traitée");
    }
} catch (Exception $e) {
    die("Erreur lors de la récupération de la commande: " . $e->getMessage());
}

// Fonction pour envoyer l'email de confirmation
function envoyerEmailConfirmationPayPal($commande, $reference) {
    require_once 'PHPMailer/src/Exception.php';
    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'lhpp.philippe@gmail.com';
        $mail->Password = 'lvpk zqjt vuon qyrz';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Destinataires
        $mail->setFrom('lhpp.philippe@gmail.com', 'Origami Zen');
        $mail->addAddress($commande['email']);
        $mail->addReplyTo('lhpp.philippe@gmail.com', 'Origami Zen');
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = "Confirmation de paiement PayPal - Commande #" . $commande['idCommande'];
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; }
                .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
                .header { text-align: center; color: #d40000; margin-bottom: 20px; }
                .success { color: #28a745; font-size: 24px; }
                .details { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Origami Zen</h1>
                </div>
                
                <h2 class='success'>✅ Paiement PayPal Confirmé</h2>
                
                <p>Bonjour " . htmlspecialchars($commande['prenom']) . ",</p>
                
                <p>Votre paiement PayPal pour la commande <strong>#" . $commande['idCommande'] . "</strong> a été traité avec succès.</p>
                
                <div class='details'>
                    <p><strong>Référence PayPal :</strong> " . $reference . "</p>
                    <p><strong>Montant :</strong> " . number_format($commande['montantTotal'], 2, ',', ' ') . " €</p>
                    <p><strong>Mode de paiement :</strong> PayPal</p>
                </div>
                
                <p>Votre commande est en cours de préparation et vous sera livrée à l'adresse :</p>
                <p><strong>" . htmlspecialchars($commande['adresse_livraison']) . "<br>
                " . $commande['cp_livraison'] . " " . htmlspecialchars($commande['ville_livraison']) . "</strong></p>
                
                <p>Merci pour votre confiance !</p>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 14px;'>
                    <p><strong>Origami Zen</strong><br>
                    📧 contact@origamizen.fr | 📞 +33 1 23 45 67 89<br>
                    123 Rue du Papier, 75000 Paris, France</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        
    } catch (Exception $e) {
        error_log("Erreur envoi email PayPal: " . $e->getMessage());
    }
}

// Traitement du retour PayPal
if (isset($_GET['success']) && $_GET['success'] === 'true' && isset($_GET['token'])) {
    $order_id = $_GET['token'];
    
    try {
        // Fonction pour capturer le paiement PayPal (identique à celle dans acheter.php)
        function capturePayPalPayment($access_token, $order_id, $environment) {
            $url = $environment === 'live' 
                ? 'https://api.paypal.com/v2/checkout/orders/' . $order_id . '/capture'
                : 'https://api.sandbox.paypal.com/v2/checkout/orders/' . $order_id . '/capture';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token
            ]);
            
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 201) {
                return json_decode($result, true);
            } else {
                error_log("Erreur capture PayPal: " . $result);
                return false;
            }
        }
        
        // Fonction pour obtenir l'access token
        function getPayPalAccessToken($client_id, $client_secret, $environment) {
            $url = $environment === 'live' 
                ? 'https://api.paypal.com/v1/oauth2/token'
                : 'https://api.sandbox.paypal.com/v1/oauth2/token';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $client_id . ":" . $client_secret);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
            
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200) {
                $json = json_decode($result);
                return $json->access_token;
            } else {
                error_log("Erreur PayPal Access Token: " . $result);
                return false;
            }
        }
        
        // Capturer le paiement
        $access_token = getPayPalAccessToken(
            $paypal_config['client_id'],
            $paypal_config['client_secret'],
            $paypal_config['environment']
        );
        
        if (!$access_token) {
            throw new Exception("Erreur de connexion à PayPal");
        }
        
        $capture = capturePayPalPayment($access_token, $order_id, $paypal_config['environment']);
        
        if ($capture && isset($capture['status']) && $capture['status'] === 'COMPLETED') {
            // Paiement réussi
            $pdo->beginTransaction();
            
            // Mettre à jour le statut de la commande
            $stmt = $pdo->prepare("UPDATE Commande SET statut = 'payee', modeReglement = 'PayPal' WHERE idCommande = ?");
            $stmt->execute([$idCommande]);
            
            // Enregistrer le paiement
            $montant = $capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? $commande['montantTotal'];
            $transaction_id = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? $order_id;
            
            $stmt = $pdo->prepare("
                INSERT INTO Paiement 
                (idCommande, montant, currency, statut, methode_paiement, reference, date_creation) 
                VALUES (?, ?, 'EUR', 'payee', 'PayPal', ?, NOW())
            ");
            $stmt->execute([$idCommande, $montant, $transaction_id]);
            
            $pdo->commit();
            
            // Envoyer email de confirmation
            envoyerEmailConfirmationPayPal($commande, $transaction_id);
            
            // Nettoyer la session
            unset($_SESSION['paypal_order_id']);
            unset($_SESSION['paypal_commande_id']);
            
            // Afficher la confirmation
            afficherConfirmationPayPal($commande, $transaction_id);
            exit;
            
        } else {
            throw new Exception("Échec de la capture du paiement PayPal");
        }
        
    } catch (Exception $e) {
        $erreur = "Erreur lors du traitement du paiement: " . $e->getMessage();
    }
}

// Fonction pour afficher la confirmation
function afficherConfirmationPayPal($commande, $reference) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Paiement Confirmé - Origami Zen</title>
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
            .success { 
                color: #28a745; 
                font-size: 48px;
                margin-bottom: 20px;
            }
            .details {
                text-align: left;
                background: #f8f9fa;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
            }
            .btn { 
                display: inline-block;
                background-color: #d40000; 
                color: white; 
                padding: 12px 30px; 
                text-decoration: none; 
                border-radius: 4px; 
                margin-top: 20px;
                border: none;
                cursor: pointer;
                font-size: 16px;
            }
            .btn:hover {
                background-color: #b30000;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success">✅</div>
            <h1>Paiement PayPal Confirmé !</h1>
            
            <p>Votre paiement PayPal a été traité avec succès.</p>
            
            <div class="details">
                <p><strong>Commande :</strong> #<?= $commande['idCommande'] ?></p>
                <p><strong>Référence PayPal :</strong> <?= $reference ?></p>
                <p><strong>Montant :</strong> <?= number_format($commande['montantTotal'], 2, ',', ' ') ?> €</p>
                <p><strong>Mode de paiement :</strong> PayPal</p>
            </div>
            
            <p>Un email de confirmation a été envoyé à <strong><?= htmlspecialchars($commande['email']) ?></strong>.</p>
            <p>Votre commande est en cours de préparation.</p>
            
            <a href="index.html" class="btn">Retour à l'accueil</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Si on arrive ici, afficher le formulaire de paiement PayPal
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement PayPal - Origami Zen</title>
    
    <!-- SDK PayPal -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?= $paypal_config['client_id'] ?>&currency=EUR&intent=capture"></script>
    
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
            text-align: center;
        }
        .header { 
            color: #d40000; 
            margin-bottom: 30px; 
        }
        .details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
        }
        #paypal-button-container {
            margin: 30px 0;
            min-height: 200px;
        }
        .loading {
            display: none;
            margin: 20px 0;
            color: #666;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .security-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            color: #856404;
            font-size: 14px;
            text-align: left;
        }
        .btn-back {
            display: inline-block;
            color: #666;
            text-decoration: none;
            margin-top: 20px;
            padding: 10px 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn-back:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Paiement PayPal</h1>
            <p>Connectez-vous à votre compte PayPal</p>
        </div>
        
        <div class="details">
            <p><strong>Commande :</strong> #<?= $commande['idCommande'] ?></p>
            <p><strong>Montant :</strong> <?= number_format($commande['montantTotal'], 2, ',', ' ') ?> €</p>
        </div>

        <?php if (isset($erreur)): ?>
            <div class="error">
                <strong>Erreur :</strong> <?= htmlspecialchars($erreur) ?>
            </div>
        <?php endif; ?>

        <div class="security-notice">
            <strong>🔒 Paiement 100% sécurisé</strong><br>
            • Connectez-vous avec votre compte PayPal<br>
            • Ou payez par carte sans créer de compte<br>
            • Aucune information bancaire stockée sur nos serveurs
        </div>

        <div id="paypal-button-container"></div>
        
        <div class="loading" id="loading">
            <p>Redirection vers PayPal...</p>
        </div>

        <a href="acheter.php?action=paypal_cancel&commande=<?= $idCommande ?>" class="btn-back">
            ← Retour aux options de paiement
        </a>
    </div>

    <script>
        // Initialiser les boutons PayPal
        paypal.Buttons({
            style: {
                layout: 'vertical',
                color:  'blue',
                shape:  'rect',
                label:  'paypal',
                height: 55
            },
            
            // Créer la transaction
            createOrder: function(data, actions) {
                document.getElementById('loading').style.display = 'block';
                
                return fetch('acheter.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'creer_commande_paypal',
                        montant: <?= $commande['montantTotal'] ?>,
                        id_commande: <?= $idCommande ?>
                    })
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.status === 200) {
                        return data.data.order_id;
                    } else {
                        throw new Error(data.error || 'Erreur création commande PayPal');
                    }
                })
                .catch(function(error) {
                    document.getElementById('loading').style.display = 'none';
                    alert('Erreur: ' + error.message);
                    throw error;
                });
            },
            
            // Finaliser la transaction
            onApprove: function(data, actions) {
                return actions.order.capture().then(function(details) {
                    // Rediriger vers la page de succès avec l'ID de commande
                    window.location.href = 'paiement_paypal.php?success=true&token=' + data.orderID + '&commande=<?= $idCommande ?>';
                });
            },
            
            // Gérer les erreurs
            onError: function(err) {
                console.error('Erreur PayPal:', err);
                document.getElementById('loading').style.display = 'none';
                alert('Une erreur est survenue avec PayPal. Veuillez réessayer ou choisir une autre méthode de paiement.');
            },
            
            // Annulation
            onCancel: function(data) {
                document.getElementById('loading').style.display = 'none';
                window.location.href = 'acheter.php?action=paypal_cancel&commande=<?= $idCommande ?>';
            },
            
            // Clic sur le bouton
            onClick: function() {
                document.getElementById('loading').style.display = 'block';
            }
            
        }).render('#paypal-button-container');

        // Cacher le loading si le bouton n'est pas rendu
        setTimeout(function() {
            document.getElementById('loading').style.display = 'none';
        }, 3000);
    </script>
</body>
</html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/wamp64/logs/paypal_errors.log');
session_start();

// Configuration de la base de données
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}


// Configuration PayPal Production
$paypal_config = [
    'client_id' => 'Aac1-P0VrxBQ_5REVeo4f557_-p6BDeXA_hyiuVZfi21sILMWccBFfTidQ6nnhQathCbWaCSQaDmxJw5',
    'client_secret' => 'EJxech0i1faRYlo0-ln2sU09ecx5rP3XEOGUTeTduI2t-I0j4xoSPqRRFQTxQsJoSBbSL8aD1b1GPPG1',
    'environment' => 'sandbox', // ← 'sandbox' pour les tests locaux
    'currency' => 'EUR'
];
// Vérifier si une commande est spécifiée
$idCommande = $_GET['commande'] ?? $_POST['id_commande'] ?? null;

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

// Fonction pour obtenir l'access token PayPal - CORRIGÉE POUR WAMP

// FONCTION DE DIAGNOSTIC PAYPAL - À AJOUTER
function diagnostiquerPayPal($paypal_config) {
    error_log("=== DIAGNOSTIC PAYPAL ===");
    error_log("Environment: " . $paypal_config['environment']);
    error_log("Client ID: " . substr($paypal_config['client_id'], 0, 10) . "...");
    error_log("Client Secret: " . substr($paypal_config['client_secret'], 0, 10) . "...");
    
    // Test de connexion
    $access_token = getPayPalAccessToken(
        $paypal_config['client_id'],
        $paypal_config['client_secret'], 
        $paypal_config['environment']
    );
    
    if ($access_token) {
        error_log("✅ Connexion PayPal OK - Token obtenu");
        return true;
    } else {
        error_log("❌ Échec connexion PayPal");
        return false;
    }
}

// Exécuter le diagnostic
diagnostiquerPayPal($paypal_config);

// Fonction pour obtenir l'access token PayPal - VERSION CORRIGÉE
function getPayPalAccessToken($client_id, $client_secret, $environment) {
    $url = $environment === 'live' 
        ? 'https://api.paypal.com/v1/oauth2/token'
        : 'https://api.sandbox.paypal.com/v1/oauth2/token';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    
    // Désactiver la vérification SSL en local
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ":" . $client_secret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Debug
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // SUPPRIMER TOUS LES COMMENTAIRES QUI BLOQUENT LE CODE ICI
    
    if ($result === false) {
        $error_msg = "cURL Error: " . curl_error($ch);
        error_log("PAYPAL ACCESS TOKEN ERROR: " . $error_msg);
        
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        error_log("cURL Verbose: " . $verboseLog);
        
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    if ($http_code == 200) {
        $json = json_decode($result);
        return $json->access_token;
    } else {
        error_log("PAYPAL ACCESS TOKEN HTTP ERROR: $http_code - Response: " . $result);
        
        $error_response = json_decode($result, true);
        if (isset($error_response['error_description'])) {
            error_log("PayPal Error Description: " . $error_response['error_description']);
        }
        if (isset($error_response['error'])) {
            error_log("PayPal Error: " . $error_response['error']);
        }
        
        return false;
    }
}
// Fonction pour traiter le paiement par carte via PayPal - AVEC SIMULATION WAMP
function traiterPaiementPayPalCB($donnees, $paypal_config) {
    // SIMULATION POUR WAMP - DÉCOMMENTER POUR TESTER
    
    sleep(2);
    return [
        'success' => true,
        'reference' => 'SIMU_WAMP_' . time() . '_' . $donnees['commande']['idCommande'],
        'response' => ['state' => 'approved', 'simulated' => true]
    ];
    
    
    try {
        // Obtenir l'access token
        $access_token = getPayPalAccessToken(
            $paypal_config['client_id'],
            $paypal_config['client_secret'],
            $paypal_config['environment']
        );
        
        if (!$access_token) {
            return [
                'success' => false,
                'error' => 'Erreur de connexion à PayPal - Vérifiez les identifiants',
                'reference' => null
            ];
        }
        
        // Préparer les données de la carte
        $numeroCarte = str_replace(' ', '', $donnees['numero_carte']);
        $exp_mois = explode('/', $donnees['date_expiration'])[0];
        $exp_annee = '20' . explode('/', $donnees['date_expiration'])[1];
        
        // URL de l'API PayPal
        $url = $paypal_config['environment'] === 'live' 
            ? 'https://api.paypal.com/v1/payments/payment'
            : 'https://api.sandbox.paypal.com/v1/payments/payment';
        
        // Données du paiement
        $data = [
            'intent' => 'sale',
            'payer' => [
                'payment_method' => 'credit_card',
                'funding_instruments' => [[
                    'credit_card' => [
                        'number' => $numeroCarte,
                        'type' => detecterTypeCarte($numeroCarte),
                        'expire_month' => $exp_mois,
                        'expire_year' => $exp_annee,
                        'cvv2' => $donnees['cryptogramme'],
                        'first_name' => explode(' ', $donnees['titulaire'])[0] ?? '',
                        'last_name' => explode(' ', $donnees['titulaire'])[1] ?? $donnees['titulaire'],
                        'billing_address' => [
                            'line1' => 'Adresse de facturation',
                            'city' => 'Ville',
                            'country_code' => 'FR',
                            'postal_code' => '75000'
                        ]
                    ]
                ]]
            ],
            'transactions' => [[
                'amount' => [
                    'total' => number_format($donnees['montant'], 2, '.', ''),
                    'currency' => $paypal_config['currency'],
                    'details' => [
                        'subtotal' => number_format($donnees['montant'], 2, '.', ''),
                        'shipping' => '0.00',
                        'tax' => '0.00'
                    ]
                ],
                'description' => 'Commande #' . $donnees['commande']['idCommande'] . ' - Youki and Go',
                'custom' => $donnees['commande']['idCommande'],
                'invoice_number' => 'CMD-' . $donnees['commande']['idCommande'] . '-' . time()
            ]],
            'note_to_payer' => 'Merci pour votre commande sur Youki and Go',
            'redirect_urls' => [
                'return_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/Origami/paiement_cb.php?commande=' . $donnees['commande']['idCommande'] . '&status=success',
                'cancel_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/Origami/paiement_cb.php?commande=' . $donnees['commande']['idCommande'] . '&status=cancel'
            ]
        ];
        
        // Exécuter la requête PayPal avec corrections WAMP
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // DEBUG WAMP
        if ($result === false) {
            error_log("cURL Error paiement: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($http_code == 201) {
            $response = json_decode($result, true);
            
            // Vérifier si le paiement est approuvé
            if ($response['state'] === 'approved') {
                $transaction_id = $response['transactions'][0]['related_resources'][0]['sale']['id'];
                
                return [
                    'success' => true,
                    'reference' => $transaction_id,
                    'response' => $response
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Paiement non approuvé par PayPal',
                    'reference' => null
                ];
            }
        } else {
            $error_response = json_decode($result, true);
            $error_message = 'Erreur PayPal: ';
            
            if (isset($error_response['details'][0]['description'])) {
                $error_message .= $error_response['details'][0]['description'];
            } else if (isset($error_response['message'])) {
                $error_message .= $error_response['message'];
            } else {
            $error_message .= 'Erreur inconnue - HTTP: ' . $http_code . ' - Full response: ' . $result;            }
            
            error_log("Erreur paiement PayPal CB: " . $result);
            
            return [
                'success' => false,
                'error' => $error_message,
                'reference' => null
            ];
        }
        
    } catch (Exception $e) {
        error_log("Exception paiement PayPal CB: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Erreur technique: ' . $e->getMessage(),
            'reference' => null
        ];
    }
}


// Fonction pour détecter le type de carte
function detecterTypeCarte($numero) {
    $numero = str_replace(' ', '', $numero);
    
    // Visa
    if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $numero)) {
        return 'visa';
    }
    // MasterCard
    if (preg_match('/^5[1-5][0-9]{14}$/', $numero)) {
        return 'mastercard';
    }
    // American Express
    if (preg_match('/^3[47][0-9]{13}$/', $numero)) {
        return 'amex';
    }
    // Discover
    if (preg_match('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $numero)) {
        return 'discover';
    }
    
    return 'visa'; // Par défaut
}

// Fonction pour valider le numéro de carte avec l'algorithme de Luhn
function validerNumeroCarte($numero) {
    $numero = str_replace(' ', '', $numero);
    
    // Vérifier la longueur
    if (strlen($numero) < 13 || strlen($numero) > 19) {
        return false;
    }
    
    // Vérifier que ce sont des chiffres
    if (!ctype_digit($numero)) {
        return false;
    }
    
    // Algorithme de Luhn
    $somme = 0;
    $inverse = strrev($numero);
    
    for ($i = 0; $i < strlen($inverse); $i++) {
        $chiffre = (int)$inverse[$i];
        
        if ($i % 2 == 1) {
            $chiffre *= 2;
            if ($chiffre > 9) {
                $chiffre -= 9;
            }
        }
        
        $somme += $chiffre;
    }
    
    return ($somme % 10 == 0);
}

// Fonction pour valider la date d'expiration
function validerDateExpiration($date) {
    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $date, $matches)) {
        return false;
    }
    
    $mois = (int)$matches[1];
    $annee = (int)$matches[2];
    $anneeComplete = 2000 + $annee;
    
    // Vérifier si la carte n'est pas expirée
    $aujourdhui = new DateTime();
    $dateExpiration = new DateTime("$anneeComplete-$mois-01");
    $dateExpiration->modify('last day of this month');
    
    return $dateExpiration > $aujourdhui;
}

// Fonction pour envoyer l'email de confirmation
function envoyerEmailConfirmationCB($commande, $reference) {
    // Inclure PHPMailer
    require_once 'PHPMailer/src/Exception.php';
    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
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
        $mail->setFrom('lhpp.philippe@gmail.com', 'Youki and Go');
        $mail->addAddress($commande['email']);
        $mail->addReplyTo('lhpp.philippe@gmail.com', 'Youki and Go');
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = "Confirmation de paiement - Commande #" . $commande['idCommande'];
        
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
                    <h1>Youki and Go</h1>
                </div>
                
                <h2 class='success'>✅ Paiement Confirmé</h2>
                
                <p>Bonjour " . htmlspecialchars($commande['prenom']) . ",</p>
                
                <p>Votre paiement par carte bancaire pour la commande <strong>#" . $commande['idCommande'] . "</strong> a été traité avec succès via PayPal.</p>
                
                <div class='details'>
                    <p><strong>Référence de transaction :</strong> " . $reference . "</p>
                    <p><strong>Montant :</strong> " . number_format($commande['montantTotal'], 2, ',', ' ') . " €</p>
                    <p><strong>Mode de paiement :</strong> Carte Bancaire (PayPal)</p>
                </div>
                
                <p>Votre commande est en cours de préparation et vous sera livrée à l'adresse :</p>
                <p><strong>" . htmlspecialchars($commande['adresse_livraison']) . "<br>
                " . $commande['cp_livraison'] . " " . htmlspecialchars($commande['ville_livraison']) . "</strong></p>
                
                <p>Merci pour votre confiance !</p>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 14px;'>
                    <p><strong>Youki and Go</strong><br>
                    📧 contact@YoukiandGo.fr | 📞 +33 1 23 45 67 89<br>
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        
    } catch (Exception $e) {
        error_log("Erreur envoi email CB: " . $e->getMessage());
    }
}

// Fonction pour afficher la confirmation
// Fonction pour afficher la confirmation
function afficherConfirmationCB($commande, $reference) {
    // Récupérer l'ID de commande depuis les données de la commande
    $idCommande = $commande['idCommande'];
    
    // Générer l'URL de la facture HTML
    $urlFactureHTML = "http://$host/Origami/facture.php?id=" . $idCommande;
    
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Paiement Confirmé - Youki and Go</title>
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
            .btn-facture { 
                background-color: #28a745; 
                color: white; 
                padding: 10px 20px; 
                text-decoration: none; 
                border-radius: 4px; 
                margin: 5px;
                display: inline-block;
                border: none;
                cursor: pointer;
                font-size: 14px;
            }
            .btn-facture:hover {
                background-color: #218838;
            }
            .facture-options {
                background: #e7f3ff;
                border: 1px solid #b3d9ff;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success">✅</div>
            <h1>Paiement Confirmé !</h1>
            
            <p>Votre paiement par carte bancaire a été traité avec succès via PayPal.</p>
            
            <div class="details">
                <p><strong>Commande :</strong> #<?= $commande['idCommande'] ?></p>
                <p><strong>Référence PayPal :</strong> <?= $reference ?></p>
                <p><strong>Montant :</strong> <?= number_format($commande['montantTotal'], 2, ',', ' ') ?> €</p>
                <p><strong>Mode de paiement :</strong> Carte Bancaire (PayPal)</p>
            </div>
            
            <p>Un email de confirmation a été envoyé à <strong><?= htmlspecialchars($commande['email']) ?></strong>.</p>
            <p>Votre commande est en cours de préparation.</p>

            <!-- Section Options de Facture -->
            <div class="facture-options">
                <h3>📄 Options de facture</h3>
                <p>Vous pouvez déjà télécharger votre facture :</p>
                <!--<a href="<?= $urlFactureHTML ?>" target="_blank" class="btn-facture">👁️ Voir la facture HTML</a>-->
                <button onclick="telechargerFacturePDF(<?= $idCommande ?>)" class="btn-facture">📥 Télécharger PDF</button>
            </div>
            
            <a href="index.html" class="btn">Retour à l'accueil</a>
        </div>

        <script>
            function telechargerFacturePDF(idCommande) {
                window.open('acheter.php?action=telecharger_facture&id_commande=' + idCommande, '_blank');
            }
        </script>
    </body>
    </html>
    <?php
}
// Traitement du formulaire de paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'traiter_paiement_cb') {
    
    // Récupération des données du formulaire
    $numeroCarte = str_replace(' ', '', $_POST['numero_carte'] ?? '');
    $dateExpiration = $_POST['date_expiration'] ?? '';
    $cryptogramme = $_POST['cryptogramme'] ?? '';
    $titulaire = $_POST['titulaire_carte'] ?? '';
    
    // Validation des données
    $erreurs = [];
    
    if (!validerNumeroCarte($numeroCarte)) {
        $erreurs[] = "Numéro de carte invalide";
    }
    
    if (!validerDateExpiration($dateExpiration)) {
        $erreurs[] = "Date d'expiration invalide ou carte expirée";
    }
    
    if (strlen($cryptogramme) !== 3 || !is_numeric($cryptogramme)) {
        $erreurs[] = "Cryptogramme invalide";
    }
    
    if (empty($titulaire) || strlen($titulaire) < 2) {
        $erreurs[] = "Nom du titulaire invalide";
    }
    
    if (empty($erreurs)) {
        // Traitement réel du paiement avec PayPal
        $resultatPaiement = traiterPaiementPayPalCB([
            'numero_carte' => $numeroCarte,
            'date_expiration' => $dateExpiration,
            'cryptogramme' => $cryptogramme,
            'titulaire' => $titulaire,
            'montant' => $commande['montantTotal'],
            'commande' => $commande
        ], $paypal_config);
        
        if ($resultatPaiement['success']) {
            // Paiement réussi
            try {
                // Démarrer une transaction
                $pdo->beginTransaction();
                
                // Mettre à jour le statut de la commande
                $stmt = $pdo->prepare("UPDATE Commande SET statut = 'payee', modeReglement = 'Carte Bancaire (PayPal)' WHERE idCommande = ?");
                $stmt->execute([$idCommande]);
                
                // CORRECTION : Enregistrer le paiement avec les bons noms de colonnes
                $stmt = $pdo->prepare("
                    INSERT INTO Paiement 
                    (idCommande, montant, currency, statut, methode_paiement, reference, date_creation) 
                    VALUES (?, ?, 'EUR', 'payee', 'Carte Bancaire (PayPal)', ?, NOW())
                ");
                $reference = $resultatPaiement['reference'];
                $stmt->execute([$idCommande, $commande['montantTotal'], $reference]);
                
                // Valider la transaction
                $pdo->commit();
                
                // Envoyer un email de confirmation
                envoyerEmailConfirmationCB($commande, $reference);
                
                // Afficher la page de confirmation
                afficherConfirmationCB($commande, $reference);
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $erreurs[] = "Erreur lors de l'enregistrement du paiement: " . $e->getMessage();
            }
        } else {
            $erreurs[] = $resultatPaiement['error'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Carte Bancaire - Youki and Co</title>
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
            margin-bottom: 8px; 
            font-weight: bold; 
            font-size: 14px;
        }
        input, select { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box;
            font-size: 16px;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .btn { 
            background-color: #d40000; 
            color: white; 
            padding: 15px 30px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            width: 100%; 
            font-size: 16px;
            margin-top: 10px;
        }
        .btn:hover {
            background-color: #b30000;
        }
        .btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .error {
            color: #dc3545;
            font-size: 14px;
            margin-top: 5px;
        }
        .details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .card-icons {
            text-align: center;
            margin: 15px 0;
            font-size: 24px;
        }
        .security-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            color: #856404;
            font-size: 14px;
        }
        .paypal-badge {
            text-align: center;
            margin: 10px 0;
        }
        .test-info {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            color: #155724;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💳 Paiement Sécurisé</h1>
            <p>Finalisez votre commande #<?= $commande['idCommande'] ?></p>
        </div>
        
        <div class="details">
            <p><strong>Montant à payer :</strong> <?= number_format($commande['montantTotal'], 2, ',', ' ') ?> €</p>
        </div>

          <div class="paypal-badge">
            <img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/cc-badges-ppmcvdam.png" alt="PayPal" style="height: 30px;">
            <p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">Paiement sécurisé par PayPal</p>
        </div>

        <?php if (!empty($erreurs)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <strong>Erreurs :</strong>
                <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                    <?php foreach ($erreurs as $erreur): ?>
                        <li><?= htmlspecialchars($erreur) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="security-notice">
            <strong>🔒 Paiement 100% sécurisé par PayPal</strong><br>
            Vos données bancaires sont cryptées et traitées directement par PayPal. Aucune information n'est stockée sur nos serveurs.
        </div>

        <form id="formPaiementCB" method="POST">
            <input type="hidden" name="action" value="traiter_paiement_cb">
            <input type="hidden" name="id_commande" value="<?= $idCommande ?>">
            
            <div class="form-group">
                <label for="numero_carte">Numéro de carte <span style="color: #d40000;">*</span></label>
                <input type="text" id="numero_carte" name="numero_carte" 
                       placeholder="1234 5678 9012 3456" 
                       maxlength="19"
                       pattern="[0-9\s]{16,19}"
                       required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="date_expiration">Date d'expiration <span style="color: #d40000;">*</span></label>
                    <input type="text" id="date_expiration" name="date_expiration" 
                           placeholder="MM/AA" 
                           maxlength="5"
                           pattern="(0[1-9]|1[0-2])\/[0-9]{2}"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="cryptogramme">Cryptogramme <span style="color: #d40000;">*</span></label>
                    <input type="text" id="cryptogramme" name="cryptogramme" 
                           placeholder="123" 
                           maxlength="3"
                           pattern="[0-9]{3}"
                           required>
                </div>
            </div>
            
            <!-- PAR CE BLOC UNIQUE -->
            <div class="form-group">
            <label for="titulaire_carte">Nom du titulaire <span style="color: #d40000;">*</span></label>
            <input type="text" id="titulaire_carte" name="titulaire_carte" 
            placeholder="M. DUPONT Jean" 
            value="<?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?>" required>
            </div>
            <div class="card-icons">
                💳 • • • • • • • • • • • • • • • •
            </div>
            
            <button type="submit" class="btn" id="btnPayer">
                Payer <?= number_format($commande['montantTotal'], 2, ',', ' ') ?> € avec PayPal
            </button>
            
            <p style="text-align: center; margin-top: 15px; font-size: 12px; color: #666;">
                En cliquant sur "Payer", vous serez redirigé vers le système sécurisé PayPal.
            </p>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="acheter.php?action=paypal_success&commande=<?= $idCommande ?>" style="color: #666; text-decoration: none;">
                ← Retour aux options de paiement
            </a>
        </div>
    </div>

    <script>
        // Formatage automatique du numéro de carte
        document.getElementById('numero_carte').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
            let formattedValue = '';
            
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            
            e.target.value = formattedValue.substring(0, 19);
        });

        // Formatage automatique de la date d'expiration
        document.getElementById('date_expiration').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                e.target.value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
        });

        // Validation en temps réel
        document.getElementById('formPaiementCB').addEventListener('submit', function(e) {
            const btn = document.getElementById('btnPayer');
            btn.disabled = true;
            btn.innerHTML = 'Traitement en cours avec PayPal...';
        });

        // Empêcher la soumission avec la touche Entrée
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const target = e.target;
                if (target.form && target.type !== 'textarea') {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>
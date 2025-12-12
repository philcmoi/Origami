<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/paiement_cb.log');
session_start();

// Configuration de la base de données HEURE DU CADEAU
require_once 'config.php'; // Assurez-vous que ce fichier existe avec vos infos de connexion

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Configuration PayPal (à mettre dans config.php)
$paypal_config = [
    'client_id' => PAYPAL_CLIENT_ID, // Défini dans config.php
    'client_secret' => PAYPAL_CLIENT_SECRET, // Défini dans config.php
    'environment' => PAYPAL_ENVIRONMENT, // 'sandbox' ou 'live'
    'currency' => 'EUR'
];

// Vérifier si une commande est spécifiée
$id_commande = $_GET['commande'] ?? $_POST['id_commande'] ?? null;

if (!$id_commande) {
    header('Location: index.html');
    exit;
}

// Récupérer les informations de la commande depuis la base heureducadeau
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id_commande,
            c.numero_commande,
            c.total_ttc,
            c.statut,
            c.statut_paiement,
            cl.email,
            cl.prenom,
            cl.nom,
            a_liv.adresse as adresse_livraison,
            a_liv.code_postal as cp_livraison,
            a_liv.ville as ville_livraison
        FROM commandes c
        JOIN clients cl ON c.id_client = cl.id_client
        JOIN adresses a_liv ON c.id_adresse_livraison = a_liv.id_adresse
        WHERE c.id_commande = ? 
        AND c.statut_paiement IN ('en_attente', 'echec')
    ");
    $stmt->execute([$id_commande]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        die("Commande non trouvée ou déjà traitée");
    }
} catch (Exception $e) {
    die("Erreur lors de la récupération de la commande: " . $e->getMessage());
}

// Fonction pour obtenir l'access token PayPal
function getPayPalAccessToken($client_id, $client_secret, $environment) {
    $url = $environment === 'live' 
        ? 'https://api.paypal.com/v1/oauth2/token'
        : 'https://api.sandbox.paypal.com/v1/oauth2/token';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    
    // Pour WAMP, désactiver la vérification SSL en développement
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ":" . $client_secret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($result === false) {
        error_log("cURL Error PayPal: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    if ($http_code == 200) {
        $json = json_decode($result);
        return $json->access_token;
    } else {
        error_log("Erreur PayPal token: HTTP $http_code - $result");
        return false;
    }
}

// Fonction pour traiter le paiement par carte via PayPal
function traiterPaiementPayPalCB($donnees, $paypal_config) {
    // SIMULATION POUR TESTS (décommenter pour PayPal réel)
    // return simulerPaiementCB($donnees); // Pour tests
    
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
                        'last_name' => explode(' ', $donnees['titulaire'])[1] ?? $donnees['titulaire']
                    ]
                ]]
            ],
            'transactions' => [[
                'amount' => [
                    'total' => number_format($donnees['montant'], 2, '.', ''),
                    'currency' => $paypal_config['currency']
                ],
                'description' => 'Commande #' . $donnees['commande']['numero_commande'] . ' - HEURE DU CADEAU',
                'custom' => $donnees['commande']['id_commande'],
                'invoice_number' => $donnees['commande']['numero_commande']
            ]]
        ];
        
        // Exécuter la requête PayPal
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
        
        if ($result === false) {
            error_log("cURL Error paiement: " . curl_error($ch));
            curl_close($ch);
            return [
                'success' => false,
                'error' => 'Erreur de connexion au serveur de paiement',
                'reference' => null
            ];
        }
        
        curl_close($ch);
        
        if ($http_code == 201) {
            $response = json_decode($result, true);
            
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
                $error_message .= 'Erreur inconnue - HTTP: ' . $http_code;
            }
            
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

// Fonction de simulation pour tests (à utiliser quand PayPal n'est pas configuré)
function simulerPaiementCB($donnees) {
    sleep(2); // Simuler un délai de traitement
    
    // Simuler un succès dans 90% des cas
    if (rand(1, 10) <= 9) {
        return [
            'success' => true,
            'reference' => 'CB_SIM_' . time() . '_' . $donnees['commande']['id_commande'],
            'response' => ['state' => 'approved', 'simulated' => true]
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Transaction refusée par la banque (simulation)',
            'reference' => null
        ];
    }
}

// Fonction pour détecter le type de carte
function detecterTypeCarte($numero) {
    $numero = str_replace(' ', '', $numero);
    
    if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $numero)) {
        return 'visa';
    }
    if (preg_match('/^5[1-5][0-9]{14}$/', $numero)) {
        return 'mastercard';
    }
    if (preg_match('/^3[47][0-9]{13}$/', $numero)) {
        return 'amex';
    }
    
    return 'visa';
}

// Fonctions de validation
function validerNumeroCarte($numero) {
    $numero = str_replace(' ', '', $numero);
    
    if (strlen($numero) < 13 || strlen($numero) > 19) {
        return false;
    }
    
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

function validerDateExpiration($date) {
    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $date, $matches)) {
        return false;
    }
    
    $mois = (int)$matches[1];
    $annee = (int)$matches[2];
    $anneeComplete = 2000 + $annee;
    
    $aujourdhui = new DateTime();
    $dateExpiration = new DateTime("$anneeComplete-$mois-01");
    $dateExpiration->modify('last day of this month');
    
    return $dateExpiration > $aujourdhui;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'traiter_paiement_cb') {
    
    $numeroCarte = str_replace(' ', '', $_POST['numero_carte'] ?? '');
    $dateExpiration = $_POST['date_expiration'] ?? '';
    $cryptogramme = $_POST['cryptogramme'] ?? '';
    $titulaire = $_POST['titulaire_carte'] ?? '';
    
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
        $resultatPaiement = traiterPaiementPayPalCB([
            'numero_carte' => $numeroCarte,
            'date_expiration' => $dateExpiration,
            'cryptogramme' => $cryptogramme,
            'titulaire' => $titulaire,
            'montant' => $commande['total_ttc'],
            'commande' => $commande
        ], $paypal_config);
        
        if ($resultatPaiement['success']) {
            try {
                $pdo->beginTransaction();
                
                // Mettre à jour la commande
                $stmt = $pdo->prepare("
                    UPDATE commandes 
                    SET statut_paiement = 'paye', 
                        mode_paiement = 'carte',
                        reference_paiement = ?,
                        date_paiement = NOW()
                    WHERE id_commande = ?
                ");
                $reference = $resultatPaiement['reference'];
                $stmt->execute([$reference, $id_commande]);
                
                // Mettre à jour le statut de la commande
                $stmt = $pdo->prepare("
                    UPDATE commandes 
                    SET statut = 'confirmee'
                    WHERE id_commande = ?
                ");
                $stmt->execute([$id_commande]);
                
                // Enregistrer un log
                $stmt = $pdo->prepare("
                    INSERT INTO logs 
                    (type_log, niveau, message, utilisateur_id, ip_address, metadata) 
                    VALUES 
                    ('paiement', 'info', ?, ?, ?, ?)
                ");
                
                $metadata = json_encode([
                    'commande_id' => $id_commande,
                    'reference' => $reference,
                    'montant' => $commande['total_ttc'],
                    'methode' => 'carte_bleue_paypal'
                ]);
                
                $stmt->execute([
                    'Paiement CB PayPal réussi - Commande #' . $commande['numero_commande'],
                    $commande['id_client'] ?? null,
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    $metadata
                ]);
                
                $pdo->commit();
                
                // Rediriger vers la page de succès
                header('Location: paiement_cb_success.php?commande=' . $id_commande . '&ref=' . urlencode($reference));
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $erreurs[] = "Erreur lors de l'enregistrement: " . $e->getMessage();
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
    <title>Paiement Carte Bancaire - HEURE DU CADEAU</title>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .header { 
            text-align: center; 
            color: #764ba2; 
            margin-bottom: 30px; 
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #764ba2;
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            font-size: 14px;
            color: #333;
        }
        input { 
            width: 100%; 
            padding: 12px 15px; 
            border: 2px solid #e1e5e9; 
            border-radius: 8px; 
            box-sizing: border-box;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input:focus {
            border-color: #764ba2;
            outline: none;
            box-shadow: 0 0 0 3px rgba(118, 75, 162, 0.1);
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .btn { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            padding: 16px 30px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            width: 100%; 
            font-size: 16px;
            font-weight: 600;
            margin-top: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(118, 75, 162, 0.3);
        }
        .btn:disabled {
            background: #cccccc;
            transform: none;
            box-shadow: none;
            cursor: not-allowed;
        }
        .error-box {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
        }
        .error-box ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        .security-info {
            background: #e8f4f8;
            border: 1px solid #b3e0f2;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            color: #0066cc;
        }
        .card-icons {
            text-align: center;
            margin: 20px 0;
            font-size: 32px;
            color: #666;
        }
        .paypal-badge {
            text-align: center;
            margin: 20px 0;
        }
        .paypal-badge img {
            height: 35px;
        }
        .test-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 12px;
            border-radius: 6px;
            margin: 15px 0;
            color: #856404;
            font-size: 13px;
            text-align: center;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #764ba2;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💳 Paiement Carte Bancaire</h1>
            <p>HEURE DU CADEAU - Commande #<?= htmlspecialchars($commande['numero_commande']) ?></p>
        </div>
        
        <div class="details">
            <p><strong>🛒 Commande :</strong> #<?= htmlspecialchars($commande['numero_commande']) ?></p>
            <p><strong>💰 Montant à payer :</strong> <span style="font-size: 20px; font-weight: bold; color: #764ba2;">
                <?= number_format($commande['total_ttc'], 2, ',', ' ') ?> €
            </span></p>
        </div>

        <div class="paypal-badge">
            <img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/cc-badges-ppmcvdam.png" alt="PayPal">
            <p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">
                🔒 Paiement sécurisé par PayPal
            </p>
        </div>

        <?php if (!empty($erreurs)): ?>
            <div class="error-box">
                <strong>❌ Des erreurs sont survenues :</strong>
                <ul>
                    <?php foreach ($erreurs as $erreur): ?>
                        <li><?= htmlspecialchars($erreur) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="security-info">
            <strong>🔐 Sécurité maximale</strong><br>
            Vos données bancaires sont cryptées et traitées directement par PayPal.
            Aucune information sensible n'est stockée sur nos serveurs.
        </div>

        <?php if ($paypal_config['environment'] === 'sandbox'): ?>
            <div class="test-notice">
                <strong>🧪 MODE TEST ACTIVÉ</strong><br>
                Vous êtes en environnement de test. Aucun paiement réel ne sera effectué.
            </div>
        <?php endif; ?>

        <form id="formPaiementCB" method="POST">
            <input type="hidden" name="action" value="traiter_paiement_cb">
            <input type="hidden" name="id_commande" value="<?= $id_commande ?>">
            
            <div class="form-group">
                <label for="numero_carte">Numéro de carte *</label>
                <input type="text" id="numero_carte" name="numero_carte" 
                       placeholder="1234 5678 9012 3456" 
                       maxlength="19"
                       required
                       autocomplete="cc-number">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="date_expiration">Date expiration *</label>
                    <input type="text" id="date_expiration" name="date_expiration" 
                           placeholder="MM/AA" 
                           maxlength="5"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="cryptogramme">Cryptogramme *</label>
                    <input type="text" id="cryptogramme" name="cryptogramme" 
                           placeholder="123" 
                           maxlength="3"
                           required
                           autocomplete="cc-csc">
                </div>
            </div>
            
            <div class="form-group">
                <label for="titulaire_carte">Nom sur la carte *</label>
                <input type="text" id="titulaire_carte" name="titulaire_carte" 
                       placeholder="M. DUPONT Jean" 
                       value="<?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?>" 
                       required
                       autocomplete="cc-name">
            </div>
            
            <div class="card-icons">
                💳 • • • • • • • • • • • • • • • •
            </div>
            
            <button type="submit" class="btn" id="btnPayer">
                <span id="btnText">Payer <?= number_format($commande['total_ttc'], 2, ',', ' ') ?> €</span>
                <span id="btnLoading" style="display: none;">⏳ Traitement en cours...</span>
            </button>
            
            <p style="text-align: center; margin-top: 15px; font-size: 12px; color: #666;">
                En cliquant sur "Payer", vous acceptez les 
                <a href="#" style="color: #764ba2;">conditions générales de vente</a>.
            </p>
        </form>
        
        <div class="back-link">
            <a href="panier.php">← Retour au panier</a>
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
            
            // Validation visuelle
            if (value.length >= 13 && value.length <= 19) {
                e.target.style.borderColor = '#28a745';
            } else {
                e.target.style.borderColor = '#e1e5e9';
            }
        });

        // Formatage automatique de la date d'expiration
        document.getElementById('date_expiration').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                e.target.value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            
            // Validation basique
            if (/^(0[1-9]|1[0-2])\/[0-9]{2}$/.test(e.target.value)) {
                e.target.style.borderColor = '#28a745';
            } else {
                e.target.style.borderColor = '#e1e5e9';
            }
        });

        // Validation du cryptogramme
        document.getElementById('cryptogramme').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value.substring(0, 3);
            
            if (value.length === 3) {
                e.target.style.borderColor = '#28a745';
            } else {
                e.target.style.borderColor = '#e1e5e9';
            }
        });

        // Gestion de la soumission
        document.getElementById('formPaiementCB').addEventListener('submit', function(e) {
            const btn = document.getElementById('btnPayer');
            const btnText = document.getElementById('btnText');
            const btnLoading = document.getElementById('btnLoading');
            
            btn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline';
            
            // Validation supplémentaire
            const numeroCarte = document.getElementById('numero_carte').value.replace(/\s/g, '');
            const dateExp = document.getElementById('date_expiration').value;
            const cvv = document.getElementById('cryptogramme').value;
            
            if (numeroCarte.length < 13 || numeroCarte.length > 19) {
                alert('Numéro de carte invalide');
                btn.disabled = false;
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
                e.preventDefault();
                return;
            }
            
            if (!/^(0[1-9]|1[0-2])\/[0-9]{2}$/.test(dateExp)) {
                alert('Format de date invalide (MM/AA requis)');
                btn.disabled = false;
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
                e.preventDefault();
                return;
            }
            
            if (cvv.length !== 3) {
                alert('Cryptogramme invalide (3 chiffres requis)');
                btn.disabled = false;
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
                e.preventDefault();
                return;
            }
        });

        // Empêcher les espaces dans les champs numériques
        document.addEventListener('keypress', function(e) {
            if (e.target.id === 'cryptogramme' || 
                e.target.id === 'numero_carte' && e.target.selectionStart === 0) {
                if (e.key === ' ') {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>
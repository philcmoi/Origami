<?php
// ============================================
// TRAITEMENT DU FORMULAIRE LIVRAISON - VERSION CORRIGÉE
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';

// Détection du type de requête
$is_api_request = (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) ||
                  isset($_SERVER['HTTP_X_REQUESTED_WITH']);

// Récupération des données
$input = [];
if ($is_api_request) {
    $jsonInput = file_get_contents('php://input');
    if (!empty($jsonInput)) {
        $input = json_decode($jsonInput, true);
    }
}
if (empty($input)) {
    $input = $_POST;
}

// Si ce n'est pas une soumission POST, rediriger
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($is_api_request) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    } else {
        header('Location: livraison_form.php');
    }
    exit;
}

// Initialisation de la réponse
$response = [
    'success' => false,
    'message' => '',
    'errors' => [],
    'missing' => []
];

try {
    $pdo = getPDOConnection();
    if (!$pdo) {
        throw new Exception('Impossible de se connecter à la base de données');
    }
    
    $session_id = session_id();
    
    // Synchronisation du panier
    synchroniserPanierSessionBDD($pdo, $session_id);
    
    // Récupération du token si présent
    $token = $input['token'] ?? $_GET['token'] ?? null;
    if (!empty($token)) {
        // Vérifier que le token est valide et marqué comme utilisé
        $stmt = $pdo->prepare("SELECT id, email, id_client FROM tokens_confirmation WHERE token = ? AND utilise = 1");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tokenData) {
            // Pré-remplir l'email si non déjà présent
            if (empty($input['email'])) {
                $input['email'] = $tokenData['email'];
            }
            if (empty($_SESSION['client_id']) && !empty($tokenData['id_client'])) {
                $_SESSION['client_id'] = $tokenData['id_client'];
            }
        }
    }
    
    // Validation des données
    $errors = [];
    $donnees_valides = [];
    
    $required_fields = [
        'nom' => 'Nom',
        'prenom' => 'Prénom',
        'email' => 'Email',
        'adresse' => 'Adresse',
        'code_postal' => 'Code postal',
        'ville' => 'Ville',
        'pays' => 'Pays'
    ];
    
    foreach ($required_fields as $field => $label) {
        if (empty(trim($input[$field] ?? ''))) {
            $errors[] = "Le champ \"$label\" est obligatoire";
            $response['missing'][] = $field;
        } else {
            $donnees_valides[$field] = trim($input[$field]);
        }
    }
    
    // Validation email
    if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide";
        $response['missing'][] = 'email';
    }
    
    // Validation code postal
    if (!empty($input['code_postal']) && !preg_match('/^\d{5}$/', $input['code_postal'])) {
        $errors[] = "Le code postal doit contenir 5 chiffres";
    }
    
    // Validation téléphone (optionnel mais format)
    if (!empty($input['telephone']) && !preg_match('/^[0-9\s\+]{10,15}$/', $input['telephone'])) {
        $errors[] = "Le numéro de téléphone n'est pas valide";
    }
    
    // Si erreurs, retour
    if (!empty($errors)) {
        $response['message'] = 'Des erreurs ont été trouvées';
        $response['errors'] = $errors;
        
        if ($is_api_request) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } else {
            addSessionMessage(implode('<br>', $errors), 'error');
            $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison'] = $input;
            header('Location: livraison_form.php' . (!empty($token) ? '?token=' . urlencode($token) : ''));
            exit;
        }
    }
    
    // Panier ID
    $panier_id = $input['panier_id'] ?? $_SESSION[SESSION_KEY_PANIER_ID] ?? null;
    
    if (!$panier_id) {
        // Créer un panier si inexistant
        $stmt = $pdo->prepare("
            INSERT INTO Panier (idClient, dateModification) 
            SELECT idClient, NOW() FROM Client WHERE session_id = ? LIMIT 1
        ");
        $stmt->execute([$session_id]);
        $panier_id = $pdo->lastInsertId();
        $_SESSION[SESSION_KEY_PANIER_ID] = $panier_id;
    }
    
    // Gestion du client
    $email = trim($input['email']);
    $client_id = $_SESSION[SESSION_KEY_CLIENT_ID] ?? null;
    
    if (!$client_id) {
        // Vérifier si client existe déjà
        $stmt = $pdo->prepare("SELECT idClient FROM Client WHERE email = ?");
        $stmt->execute([$email]);
        $client_existant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($client_existant) {
            $client_id = $client_existant['idClient'];
            // Mettre à jour les informations
            $stmt = $pdo->prepare("
                UPDATE Client SET nom = ?, prenom = ?, telephone = ?, session_id = ? WHERE idClient = ?
            ");
            $stmt->execute([
                trim($input['nom']),
                trim($input['prenom']),
                !empty($input['telephone']) ? trim($input['telephone']) : null,
                $session_id,
                $client_id
            ]);
        } else {
            // Créer un nouveau client permanent
            $motDePasse = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO Client (email, motDePasse, nom, prenom, telephone, type, session_id, date_creation) 
                VALUES (?, ?, ?, ?, ?, 'permanent', ?, NOW())
            ");
            $stmt->execute([
                $email,
                $motDePasse,
                trim($input['nom']),
                trim($input['prenom']),
                !empty($input['telephone']) ? trim($input['telephone']) : null,
                $session_id
            ]);
            $client_id = $pdo->lastInsertId();
        }
        
        $_SESSION[SESSION_KEY_CLIENT_ID] = $client_id;
        $_SESSION['client_email'] = $email;
    }
    
    // Sauvegarde de l'adresse de livraison
    $stmt = $pdo->prepare("
        INSERT INTO Adresse (idClient, nom, prenom, adresse, codePostal, ville, pays, telephone, instructions, societe, type, dateCreation) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'livraison', NOW())
    ");
    
    $stmt->execute([
        $client_id,
        trim($input['nom']),
        trim($input['prenom']),
        trim($input['adresse']),
        trim($input['code_postal']),
        trim($input['ville']),
        trim($input['pays']),
        !empty($input['telephone']) ? trim($input['telephone']) : null,
        !empty($input['instructions']) ? trim($input['instructions']) : null,
        !empty($input['societe']) ? trim($input['societe']) : null
    ]);
    
    $adresse_livraison_id = $pdo->lastInsertId();
    
    // Adresse de facturation
    $meme_adresse = isset($input['meme_adresse_facturation']) && $input['meme_adresse_facturation'] == '1';
    
    if ($meme_adresse) {
        $adresse_facturation_id = $adresse_livraison_id;
        $adresse_facturation_data = null;
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO Adresse (idClient, nom, prenom, adresse, codePostal, ville, pays, societe, type, dateCreation) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'facturation', NOW())
        ");
        $stmt->execute([
            $client_id,
            trim($input['facturation_nom'] ?? $input['nom']),
            trim($input['facturation_prenom'] ?? $input['prenom']),
            trim($input['facturation_adresse'] ?? $input['adresse']),
            trim($input['facturation_code_postal'] ?? $input['code_postal']),
            trim($input['facturation_ville'] ?? $input['ville']),
            trim($input['facturation_pays'] ?? $input['pays']),
            !empty($input['facturation_societe']) ? trim($input['facturation_societe']) : null
        ]);
        $adresse_facturation_id = $pdo->lastInsertId();
        
        $adresse_facturation_data = [
            'id' => $adresse_facturation_id,
            'nom' => trim($input['facturation_nom'] ?? $input['nom']),
            'prenom' => trim($input['facturation_prenom'] ?? $input['prenom']),
            'societe' => !empty($input['facturation_societe']) ? trim($input['facturation_societe']) : null,
            'adresse' => trim($input['facturation_adresse'] ?? $input['adresse']),
            'complement' => !empty($input['facturation_complement']) ? trim($input['facturation_complement']) : null,
            'code_postal' => trim($input['facturation_code_postal'] ?? $input['code_postal']),
            'ville' => trim($input['facturation_ville'] ?? $input['ville']),
            'pays' => trim($input['facturation_pays'] ?? $input['pays'])
        ];
    }
    
    // Options de livraison
    $mode_livraison = $input['mode_livraison'] ?? 'standard';
    $emballage_cadeau = isset($input['emballage_cadeau']) && $input['emballage_cadeau'] == '1' ? 1 : 0;
    $instructions = !empty($input['instructions']) ? trim($input['instructions']) : null;
    
    // Mise à jour de la session centralisée
    $_SESSION[SESSION_KEY_CHECKOUT] = [
        'panier_id' => $panier_id,
        'client_id' => $client_id,
        'client_email' => $email,
        'adresse_livraison' => [
            'id' => $adresse_livraison_id,
            'nom' => trim($input['nom']),
            'prenom' => trim($input['prenom']),
            'email' => $email,
            'telephone' => !empty($input['telephone']) ? trim($input['telephone']) : null,
            'societe' => !empty($input['societe']) ? trim($input['societe']) : null,
            'adresse' => trim($input['adresse']),
            'complement' => !empty($input['complement']) ? trim($input['complement']) : null,
            'code_postal' => trim($input['code_postal']),
            'ville' => trim($input['ville']),
            'pays' => trim($input['pays']),
            'instructions' => $instructions
        ],
        'adresse_facturation' => $adresse_facturation_data,
        'mode_livraison' => $mode_livraison,
        'emballage_cadeau' => (bool)$emballage_cadeau,
        'instructions' => $instructions,
        'etape' => 'paiement',
        'date_modification' => date('Y-m-d H:i:s'),
        'token' => $token
    ];
    
    // Sauvegarde dans commande_temporaire (si la table existe)
    try {
        $donnees_livraison = json_encode($_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']);
        
        $stmt = $pdo->prepare("SELECT id FROM commande_temporaire WHERE panier_id = ?");
        $stmt->execute([$panier_id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            $stmt = $pdo->prepare("
                UPDATE commande_temporaire 
                SET donnees_livraison = ?, mode_livraison = ?, emballage_cadeau = ?, instructions = ?, date_modification = NOW()
                WHERE panier_id = ?
            ");
            $stmt->execute([$donnees_livraison, $mode_livraison, $emballage_cadeau, $instructions, $panier_id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO commande_temporaire (panier_id, donnees_livraison, mode_livraison, emballage_cadeau, instructions, date_creation) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$panier_id, $donnees_livraison, $mode_livraison, $emballage_cadeau, $instructions]);
        }
    } catch (Exception $e) {
        // Table commande_temporaire peut ne pas exister
        error_log("Note: Table commande_temporaire non disponible: " . $e->getMessage());
    }
    
    $response['success'] = true;
    $response['message'] = 'Adresse enregistrée avec succès';
    $response['redirect'] = 'paiement.php';
    $response['data'] = [
        'client_id' => $client_id,
        'panier_id' => $panier_id,
        'adresse_livraison_id' => $adresse_livraison_id
    ];
    
} catch (Exception $e) {
    $response['message'] = 'Une erreur est survenue: ' . $e->getMessage();
    $response['errors'] = [$e->getMessage()];
    error_log("Erreur livraison.php: " . $e->getMessage());
}

if ($is_api_request) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} else {
    if ($response['success']) {
        header('Location: paiement.php');
    } else {
        addSessionMessage($response['message'], 'error');
        if (isset($input)) {
            $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison'] = $input;
        }
        $redirectUrl = 'livraison_form.php';
        if (!empty($token)) {
            $redirectUrl .= '?token=' . urlencode($token);
        }
        header('Location: ' . $redirectUrl);
    }
    exit;
}
?>
<?php
header('Content-Type: application/json');

// Activer le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Journaliser la requête
file_put_contents('debug_log.txt', print_r($_SERVER, true) . "\n", FILE_APPEND);
file_put_contents('debug_log.txt', "Method: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
file_put_contents('debug_log.txt', "Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'Non défini') . "\n", FILE_APPEND);

// Lire les données POST brutes
$input = file_get_contents('php://input');
file_put_contents('debug_log.txt', "Raw input: " . $input . "\n", FILE_APPEND);

// Décoder le JSON
$data = json_decode($input, true);
file_put_contents('debug_log.txt', "Decoded data: " . print_r($data, true) . "\n", FILE_APPEND);

if ($data === null) {
    // Si le JSON est invalide, on essaie avec les données POST standard
    $data = $_POST;
    file_put_contents('debug_log.txt', "Using POST: " . print_r($data, true) . "\n", FILE_APPEND);
}

$action = $data['action'] ?? '';

if ($action === 'envoyer_lien_confirmation') {
    $email = $data['email'] ?? '';
    $type = $data['type'] ?? '';

    // Simulation d'envoi
    $lien_confirmation = "http://localhost/ORIGAMI/confirmer.php?email=" . urlencode($email) . "&token=" . bin2hex(random_bytes(16));

    // Log
    file_put_contents('email_log.txt', date('Y-m-d H:i:s') . " - Email: $email, Lien: $lien_confirmation\n", FILE_APPEND);

    echo json_encode([
        'status' => 200,
        'message' => 'Lien de confirmation envoyé (simulation)',
        'debug' => [
            'email' => $email,
            'lien' => $lien_confirmation
        ]
    ]);
} else {
    echo json_encode([
        'status' => 400,
        'error' => 'Action non reconnue',
        'debug' => [
            'action_received' => $action,
            'data_received' => $data,
            'raw_input' => $input
        ]
    ]);
}
?>
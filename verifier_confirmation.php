<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

session_start();

$email = $_GET['email'] ?? '';

function sendResponse($status, $data = null, $error = null) {
    http_response_code($status);
    echo json_encode([
        'status' => $status,
        'data' => $data,
        'error' => $error
    ]);
    exit;
}

if (isset($_SESSION['email_confirme']) && $_SESSION['email_confirme'] && $_SESSION['email_verifie'] === $email) {
    sendResponse(200, ["confirme" => true]);
} else {
    sendResponse(200, ["confirme" => false]);
}
?>
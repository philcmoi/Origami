<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test PayPal WAMP</h2>";

// Test cURL
if (!function_exists('curl_version')) {
    die("cURL n'est pas activé");
}

echo "cURL est activé<br>";

$client_id = 'ARwZp4LWznNuNvv6pe4OFzGCf-LVqUIQbeMfP4BegaoGuQcSEnqmUIB962mBP7TZ7yftDbO2ZCEsvldX';
$client_secret = 'EIQrOYfJe25BK1_ZKe01uk4-liK3FsJzj_2FGXS10K_n4IwPIn6bmtKMW2PffCawtf0DARJhCOZrO4E1';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.sandbox.paypal.com/v1/oauth2/token');
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $client_id . ":" . $client_secret);
curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

echo "Envoi de la requête...<br>";
$result = curl_exec($ch);

if ($result === false) {
    echo "Erreur cURL: " . curl_error($ch) . "<br>";
} else {
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "Code HTTP: $http_code<br>";
    echo "Réponse: " . htmlspecialchars($result) . "<br>";
}

curl_close($ch);
?>
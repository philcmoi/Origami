<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test Spécifique PayPal API</h2>";

// Identifiants Sandbox de test
$client_id = 'Aac1-P0VrxBQ_5REVeo4f557_-p6BDeXA_hyiuVZfi21sILMWccBFfTidQ6nnhQathCbWaCSQaDmxJw5';
$client_secret = 'EJxech0i1faRYlo0-ln2sU09ecx5rP3XEOGUTeTduI2t-I0j4xoSPqRRFQTxQsJoSBbSL8aD1b1GPPG1';

$url = 'https://api.sandbox.paypal.com/v1/oauth2/token';

echo "<h3>1. Test avec cURL</h3>";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_HEADER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $client_id . ":" . $client_secret,
    CURLOPT_POSTFIELDS => "grant_type=client_credentials",
    CURLOPT_TIMEOUT => 30,
    CURLOPT_VERBOSE => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Accept-Language: en_US',
        'Content-Type: application/x-www-form-urlencoded'
    ]
]);

$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($result, 0, $header_size);
$body = substr($result, $header_size);

echo "<strong>HTTP Code:</strong> " . $http_code . "<br>";
echo "<strong>Headers:</strong><br><pre>" . htmlspecialchars($headers) . "</pre>";
echo "<strong>Body:</strong><br><pre>" . htmlspecialchars($body) . "</pre>";

if (curl_error($ch)) {
    echo "<strong>cURL Error:</strong> " . curl_error($ch) . "<br>";
}
curl_close($ch);

echo "<h3>2. Vérification des identifiants</h3>";
echo "Client ID: " . substr($client_id, 0, 15) . "..." . "<br>";
echo "Client Secret: " . substr($client_secret, 0, 10) . "..." . "<br>";
echo "Longueur Client ID: " . strlen($client_id) . "<br>";
echo "Longueur Client Secret: " . strlen($client_secret) . "<br>";

// Test avec des identifiants volontairement erronés
echo "<h3>3. Test avec identifiants erronés (pour comparaison)</h3>";
$ch2 = curl_init();
curl_setopt_array($ch2, [
    CURLOPT_URL => $url,
    CURLOPT_HEADER => false,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => "mauvais_id:mauvais_secret",
    CURLOPT_POSTFIELDS => "grant_type=client_credentials",
    CURLOPT_TIMEOUT => 10
]);

$result2 = curl_exec($ch2);
$http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "Avec identifiants erronés - HTTP Code: " . $http_code2 . "<br>";
?>
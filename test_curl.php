<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de Connexion Avancé</h2>";

// Test 1: cURL basique
echo "<h3>1. Test cURL basique</h3>";
$ch = curl_init('https://www.google.com');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
]);
$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $http_code . "<br>";
echo "Error: " . ($error ? $error : 'Aucune erreur') . "<br>";

// Test 2: file_get_contents
echo "<h3>2. Test file_get_contents</h3>";
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

try {
    $result2 = file_get_contents('https://www.google.com', false, $context);
    echo "✅ file_get_contents fonctionne<br>";
} catch (Exception $e) {
    echo "❌ file_get_contents erreur: " . $e->getMessage() . "<br>";
}

// Test 3: DNS
echo "<h3>3. Test DNS</h3>";
$ip = gethostbyname('www.google.com');
echo "IP Google: " . ($ip === 'www.google.com' ? 'DNS échoué' : $ip) . "<br>";

// Test 4: Ports
echo "<h3>4. Test ports sortants</h3>";
$ports = [80, 443, 21, 25];
foreach ($ports as $port) {
    $fp = @fsockopen('www.google.com', $port, $errno, $errstr, 5);
    if ($fp) {
        echo "✅ Port $port ouvert<br>";
        fclose($fp);
    } else {
        echo "❌ Port $port fermé ($errstr)<br>";
    }
}
?>

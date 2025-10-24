<?php
// test-verification.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'ReservationMailer.php';

echo "<h1>Vérification des méthodes ReservationMailer</h1>";

$methods = get_class_methods('ReservationMailer');
echo "<h2>Méthodes disponibles :</h2>";
echo "<ul>";
foreach ($methods as $method) {
    echo "<li>" . $method . "</li>";
}
echo "</ul>";

// Vérification spécifique
if (method_exists('ReservationMailer', 'buildEmailContent')) {
    echo "<p style='color: green;'>✅ buildEmailContent() existe</p>";
} else {
    echo "<p style='color: red;'>❌ buildEmailContent() n'existe pas</p>";
}

if (method_exists('ReservationMailer', 'buildTextEmailContent')) {
    echo "<p style='color: green;'>✅ buildTextEmailContent() existe</p>";
} else {
    echo "<p style='color: red;'>❌ buildTextEmailContent() n'existe pas</p>";
}

if (method_exists('ReservationMailer', 'sendConfirmationEmail')) {
    echo "<p style='color: green;'>✅ sendConfirmationEmail() existe</p>";
} else {
    echo "<p style='color: red;'>❌ sendConfirmationEmail() n'existe pas</p>";
}
?>
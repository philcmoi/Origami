<?php
// Créez un fichier localisation.php avec ce contenu
echo "Script actuel : " . __FILE__ . "<br>";
echo "Répertoire actuel : " . getcwd() . "<br>";
echo "URL demandée : " . $_SERVER['REQUEST_URI'] . "<br>";
?>
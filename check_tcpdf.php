<?php
// check_tcpdf.php
$tcpdf_path = 'tcpdf/tcpdf.php';

if (file_exists($tcpdf_path)) {
    echo "✅ TCPDF est installé à: " . $tcpdf_path . "<br>";
    
    // Tester l'inclusion
    require_once($tcpdf_path);
    $pdf = new TCPDF();
    echo "✅ TCPDF fonctionne correctement";
} else {
    echo "❌ TCPDF n'est pas installé à: " . $tcpdf_path . "<br>";
    echo "Téléchargez TCPDF depuis: https://github.com/tecnickcom/tcpdf<br>";
    echo "Et placez-le dans le dossier 'tcpdf/' de votre projet";
}
?>
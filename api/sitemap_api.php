<?php
// api/sitemap_api.php
// ============================================
// GÉNÉRATION DU SITEMAP XML (SEO)
// ============================================

header("Content-Type: application/xml");
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

// Page d'accueil
echo '<url><loc>https://youkiandco.fr/</loc><priority>1.0</priority></url>';

// Produits visibles
$stmt = $pdo->query("SELECT idOrigami, nom, dateModification FROM Origami WHERE visible = 1");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $row['nom']), '-'));
    echo '<url>';
    echo '<loc>https://youkiandco.fr/produit.php?id=' . $row['idOrigami'] . '&slug=' . $slug . '</loc>';
    echo '<lastmod>' . date('Y-m-d', strtotime($row['dateModification'] ?? 'now')) . '</lastmod>';
    echo '<priority>0.8</priority>';
    echo '</url>';
}

echo '</urlset>';
exit;
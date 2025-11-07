<?php
/**
 * Visualisation des échantillons de données importées
 */

require_once __DIR__ . '/config/database.php';

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║        ÉCHANTILLON DES DONNÉES IMPORTÉES MINEE-OSM           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "🏆 TOP 5 - Meilleur matching (score ≥ 90%)\n";
echo str_repeat("─", 70) . "\n";
$stmt = $pdo->query("
    SELECT numero, nom_demandeur, ville, region, score_matching_osm, coordonnees_gps
    FROM dossiers
    WHERE est_historique = 1 AND score_matching_osm >= 90
    ORDER BY score_matching_osm DESC
    LIMIT 5
");
$count = 1;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "\n#{$count}. {$row['numero']}\n";
    echo "   Opérateur     : {$row['nom_demandeur']}\n";
    echo "   Localisation  : {$row['ville']} ({$row['region']})\n";
    echo "   Score matching: {$row['score_matching_osm']}%\n";
    echo "   GPS           : {$row['coordonnees_gps']}\n";
    $count++;
}

echo "\n\n❓ ÉCHANTILLON - Sans matching (0%)\n";
echo str_repeat("─", 70) . "\n";
$stmt = $pdo->query("
    SELECT numero, nom_demandeur, ville, region, quartier
    FROM dossiers
    WHERE est_historique = 1 AND (score_matching_osm = 0 OR score_matching_osm IS NULL)
    LIMIT 5
");
$count = 1;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "\n#{$count}. {$row['numero']}\n";
    echo "   Opérateur     : {$row['nom_demandeur']}\n";
    echo "   Localisation  : {$row['ville']} - {$row['quartier']} ({$row['region']})\n";
    echo "   GPS           : Non disponible\n";
    $count++;
}

echo "\n\n";

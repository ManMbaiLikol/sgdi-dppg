<?php
/**
 * Vérification de la cohérence géographique des coordonnées GPS
 * Détecte les stations avec des GPS incohérents par rapport à leur région déclarée
 */

require_once __DIR__ . '/config/database.php';

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║    VÉRIFICATION COHÉRENCE GÉOGRAPHIQUE                        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Définir les bounding boxes approximatives des régions du Cameroun
$region_bounds = [
    'Adamaoua' => ['lat_min' => 6.0, 'lat_max' => 8.5, 'lng_min' => 11.5, 'lng_max' => 15.5],
    'Centre' => ['lat_min' => 3.0, 'lat_max' => 5.0, 'lng_min' => 10.5, 'lng_max' => 13.0],
    'Est' => ['lat_min' => 2.0, 'lat_max' => 6.0, 'lng_min' => 13.0, 'lng_max' => 16.5],
    'Extrême-Nord' => ['lat_min' => 10.0, 'lat_max' => 13.1, 'lng_min' => 13.5, 'lng_max' => 15.5],
    'Extrême_Nord' => ['lat_min' => 10.0, 'lat_max' => 13.1, 'lng_min' => 13.5, 'lng_max' => 15.5],
    'Littoral' => ['lat_min' => 3.5, 'lat_max' => 5.0, 'lng_min' => 9.0, 'lng_max' => 10.5],
    'Nord' => ['lat_min' => 7.5, 'lat_max' => 10.5, 'lng_min' => 12.5, 'lng_max' => 15.0],
    'Nord-Ouest' => ['lat_min' => 5.0, 'lat_max' => 7.0, 'lng_min' => 9.5, 'lng_max' => 11.5],
    'Nord_Ouest' => ['lat_min' => 5.0, 'lat_max' => 7.0, 'lng_min' => 9.5, 'lng_max' => 11.5],
    'Ouest' => ['lat_min' => 4.5, 'lat_max' => 6.5, 'lng_min' => 9.5, 'lng_max' => 11.5],
    'Sud' => ['lat_min' => 2.0, 'lat_max' => 3.5, 'lng_min' => 9.5, 'lng_max' => 13.0],
    'Sud-Ouest' => ['lat_min' => 3.5, 'lat_max' => 5.5, 'lng_min' => 8.5, 'lng_max' => 10.5],
    'Sud_Ouest' => ['lat_min' => 3.5, 'lat_max' => 5.5, 'lng_min' => 8.5, 'lng_max' => 10.5],
];

function is_in_region($lat, $lng, $region, $region_bounds) {
    if (!isset($region_bounds[$region])) {
        return null; // Région inconnue, on ne peut pas vérifier
    }

    $bounds = $region_bounds[$region];
    return (
        $lat >= $bounds['lat_min'] && $lat <= $bounds['lat_max'] &&
        $lng >= $bounds['lng_min'] && $lng <= $bounds['lng_max']
    );
}

// Récupérer toutes les stations avec GPS
$stmt = $pdo->query("
    SELECT
        numero,
        nom_demandeur,
        region,
        ville,
        coordonnees_gps,
        score_matching_osm
    FROM dossiers
    WHERE est_historique = 1
    AND coordonnees_gps IS NOT NULL
    AND coordonnees_gps != ''
    ORDER BY region, ville
");

$stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📊 ANALYSE DE " . count($stations) . " STATIONS AVEC GPS\n";
echo str_repeat('─', 70) . "\n\n";

$stats = [
    'total' => count($stations),
    'coherent' => 0,
    'incoherent' => 0,
    'unknown_region' => 0
];

$incoherences = [];

foreach ($stations as $station) {
    $coords = explode(',', $station['coordonnees_gps']);
    if (count($coords) != 2) continue;

    $lat = floatval(trim($coords[0]));
    $lng = floatval(trim($coords[1]));
    $region = $station['region'];

    $is_coherent = is_in_region($lat, $lng, $region, $region_bounds);

    if ($is_coherent === null) {
        $stats['unknown_region']++;
    } elseif ($is_coherent) {
        $stats['coherent']++;
    } else {
        $stats['incoherent']++;
        $incoherences[] = [
            'numero' => $station['numero'],
            'operateur' => $station['nom_demandeur'],
            'region_declaree' => $region,
            'ville' => $station['ville'],
            'lat' => $lat,
            'lng' => $lng,
            'score' => $station['score_matching_osm']
        ];
    }
}

// Statistiques
echo "✅ RÉSULTATS\n";
echo str_repeat('─', 70) . "\n";
$pct_coherent = $stats['total'] > 0 ? round(($stats['coherent'] / $stats['total']) * 100, 1) : 0;
$pct_incoherent = $stats['total'] > 0 ? round(($stats['incoherent'] / $stats['total']) * 100, 1) : 0;

echo "Cohérents              : {$stats['coherent']} ({$pct_coherent}%)\n";
echo "Incohérents            : {$stats['incoherent']} ({$pct_incoherent}%)\n";
echo "Région inconnue        : {$stats['unknown_region']}\n";
echo "\n";

if ($stats['incoherent'] > 0) {
    echo "⚠️  INCOHÉRENCES DÉTECTÉES (" . count($incoherences) . ")\n";
    echo str_repeat('─', 70) . "\n\n";

    foreach (array_slice($incoherences, 0, 20) as $idx => $inc) {
        echo ($idx + 1) . ". {$inc['numero']} - {$inc['operateur']}\n";
        echo "   📍 Déclaré : {$inc['ville']} ({$inc['region_declaree']})\n";
        echo "   🗺️  GPS    : {$inc['lat']}, {$inc['lng']}\n";
        echo "   📊 Score  : {$inc['score']}%\n";

        // Déterminer dans quelle région se trouve réellement le GPS
        $real_region = 'Inconnue';
        foreach ($region_bounds as $r => $bounds) {
            if (is_in_region($inc['lat'], $inc['lng'], $r, $region_bounds)) {
                $real_region = $r;
                break;
            }
        }
        echo "   ⚠️  GPS semble être en région : $real_region\n\n";
    }

    if (count($incoherences) > 20) {
        echo "... et " . (count($incoherences) - 20) . " autres incohérences\n\n";
    }

    echo "💡 RECOMMANDATION\n";
    echo str_repeat('─', 70) . "\n";
    echo "Les incohérences géographiques détectées indiquent que:\n";
    echo "1. L'algorithme de matching doit encore être affiné\n";
    echo "2. Certaines stations OSM sont mal géolocalisées\n";
    echo "3. Il faut peut-être augmenter le seuil de matching régional\n";
} else {
    echo "✅ EXCELLENT ! Aucune incohérence géographique détectée.\n";
    echo "Toutes les stations ont des coordonnées GPS cohérentes avec leur région.\n";
}

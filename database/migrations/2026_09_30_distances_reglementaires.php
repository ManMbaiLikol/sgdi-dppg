<?php
/**
 * Migration : distances réglementaires entre une station-service et les sites protégés
 *
 *   1 000 m : Présidence de la République, Services du Premier Ministre, Assemblée nationale, Sénat,
 *             services du Gouverneur, préfectures, sous-préfectures ;
 *     100 m : établissements d'enseignement, centres hospitaliers et de santé, lieux de culte,
 *             terrains de sport, places de marché, bâtiments administratifs (mairies comprises).
 * La réglementation ne prévoit pas de distance réduite en zone rurale pour ces sites :
 * la distance « rurale » est alignée sur la distance urbaine.
 *
 * Idempotent. Usage : php database/migrations/2026_09_30_distances_reglementaires.php [--dry-run]
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Script à exécuter en ligne de commande uniquement.\n");
}

require_once __DIR__ . '/../../config/database.php';

$dry_run = in_array('--dry-run', $argv, true);
$regles = [
    1000 => ['presidence', 'services_pm', 'assemblee_nationale', 'senat', 'services_gouverneur', 'prefecture', 'sous_prefecture'],
    100 => ['etablissement_enseignement', 'infrastructure_sanitaire', 'lieu_culte', 'terrain_sport', 'place_marche', 'batiment_administratif', 'mairie'],
];

echo "Distances réglementaires des sites protégés" . ($dry_run ? " (simulation)" : "") . "\n";
echo str_repeat('-', 60) . "\n";

$categories = $pdo->query("SELECT code, nom, distance_min_metres, distance_min_rural_metres FROM categories_poi")->fetchAll(PDO::FETCH_UNIQUE);
$maj = $pdo->prepare("UPDATE categories_poi SET distance_min_metres = ?, distance_min_rural_metres = ? WHERE code = ?");
$modifications = 0;
foreach ($regles as $distance => $codes) {
    foreach ($codes as $code) {
        if (!isset($categories[$code])) { echo "  $code : catégorie absente, ignorée\n"; continue; }
        $c = $categories[$code];
        if ((int) $c['distance_min_metres'] === $distance && (int) $c['distance_min_rural_metres'] === $distance) {
            echo "  " . str_pad($c['nom'], 34) . " $distance m (déjà conforme)\n";
            continue;
        }
        echo "  " . str_pad($c['nom'], 34) . " {$c['distance_min_metres']} m / rural {$c['distance_min_rural_metres']} m  →  $distance m\n";
        if (!$dry_run) $maj->execute([$distance, $distance, $code]);
        $modifications++;
    }
}
echo str_repeat('-', 60) . "\n";
echo $modifications ? ($dry_run ? "$modifications catégorie(s) à modifier, rien n'a été écrit.\n" : "$modifications catégorie(s) modifiée(s).\n") : "Rien à faire.\n";

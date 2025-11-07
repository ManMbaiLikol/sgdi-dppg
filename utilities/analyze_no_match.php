<?php
/**
 * Analyse des stations MINEE sans match OSM
 * Identifie les raisons de l'échec du matching
 */

set_time_limit(300);
ini_set('memory_limit', '512M');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Analyse Stations Sans Match</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1600px; margin: 0 auto; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
    h1 { color: #2c3e50; border-bottom: 4px solid #e74c3c; padding-bottom: 15px; }
    h2 { color: #34495e; margin-top: 40px; padding: 15px; background: linear-gradient(90deg, #e74c3c, #f39c12); color: white; border-radius: 8px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; font-size: 0.9em; }
    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
    th { background: #3498db; color: white; position: sticky; top: 0; }
    tr:nth-child(even) { background: #f9f9f9; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 25px 0; }
    .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 10px; text-align: center; }
    .stat-value { font-size: 2.5em; font-weight: bold; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔍 Analyse des Stations Sans Match OSM</h1>";

// Charger le fichier fusionné
$fusion_file = __DIR__ . '/exports/fusion_minee_osm_2025-10-31_105035.csv';

$handle = fopen($fusion_file, 'r');
fseek($handle, 3); // Skip BOM
$headers = fgetcsv($handle, 0, ';');

$no_match = [];
$with_match = [];

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    if (count($row) === count($headers)) {
        $station = array_combine($headers, $row);

        if (empty($station['latitude']) || empty($station['longitude'])) {
            $no_match[] = $station;
        } else {
            $with_match[] = $station;
        }
    }
}
fclose($handle);

echo "<h2>📊 Vue d'ensemble</h2>";
echo "<div class='stats'>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);'>";
echo "<div class='stat-value'>" . count($with_match) . "</div>";
echo "<div>Avec GPS</div>";
echo "</div>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);'>";
echo "<div class='stat-value'>" . count($no_match) . "</div>";
echo "<div>Sans GPS</div>";
echo "</div>";
echo "</div>";

// Analyser par opérateur
echo "<h2>🏢 Stations sans match par opérateur</h2>";
$operators_no_match = [];
foreach ($no_match as $station) {
    $op = $station['nom_demandeur'] ?? 'Inconnu';
    if (!isset($operators_no_match[$op])) {
        $operators_no_match[$op] = 0;
    }
    $operators_no_match[$op]++;
}
arsort($operators_no_match);

echo "<table>";
echo "<tr><th>Opérateur</th><th>Stations sans GPS</th><th>%</th></tr>";
foreach (array_slice($operators_no_match, 0, 15, true) as $op => $count) {
    $pct = round(($count / count($no_match)) * 100, 1);
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($op) . "</strong></td>";
    echo "<td>" . $count . "</td>";
    echo "<td>" . $pct . "%</td>";
    echo "</tr>";
}
echo "</table>";

// Analyser par région
echo "<h2>🗺️ Stations sans match par région</h2>";
$regions_no_match = [];
foreach ($no_match as $station) {
    $region = $station['region'] ?? 'Inconnu';
    if (!isset($regions_no_match[$region])) {
        $regions_no_match[$region] = 0;
    }
    $regions_no_match[$region]++;
}
arsort($regions_no_match);

echo "<table>";
echo "<tr><th>Région</th><th>Stations sans GPS</th><th>%</th></tr>";
foreach ($regions_no_match as $region => $count) {
    $pct = round(($count / count($no_match)) * 100, 1);
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($region) . "</strong></td>";
    echo "<td>" . $count . "</td>";
    echo "<td>" . $pct . "%</td>";
    echo "</tr>";
}
echo "</table>";

// Exemples de stations sans match
echo "<h2>📋 Exemples de stations sans match (20 premiers)</h2>";
echo "<table>";
echo "<tr><th>Opérateur</th><th>Région</th><th>Ville</th><th>Quartier</th></tr>";
foreach (array_slice($no_match, 0, 20) as $station) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($station['nom_demandeur'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($station['region'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($station['ville'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($station['quartier'] ?? '') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "</div></body></html>";

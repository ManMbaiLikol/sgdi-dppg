<?php
/**
 * Script d'extraction des stations-service depuis OpenStreetMap (OSM)
 * Récupère toutes les stations-service du Cameroun
 *
 * Source: Overpass API (OpenStreetMap)
 * Licence: ODbL (Open Database License)
 *
 * Usage:
 * - Via navigateur: http://localhost/dppg-implantation/extract_osm_stations.php
 * - Via CLI: php extract_osm_stations.php
 */

// Sécurité : Accessible uniquement aux admins et chefs de service
require_once '../../includes/auth.php';
requireAnyRole(['admin', 'chef_service']);

set_time_limit(300); // 5 minutes max

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Extraction Stations OSM - Cameroun</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
    .step { background: #ecf0f1; padding: 15px; margin: 15px 0; border-left: 4px solid #3498db; }
    .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: #3498db; color: white; }
    tr:nth-child(even) { background: #f9f9f9; }
    .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
    .btn:hover { background: #2980b9; }
    .btn-success { background: #27ae60; }
    .btn-success:hover { background: #229954; }
    .loading { text-align: center; padding: 20px; }
    .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🗺️ Extraction Stations-Service OSM - Cameroun</h1>";

// Étape 1: Requête Overpass API
echo "<div class='step'>";
echo "<h2>📡 Étape 1: Connexion à OpenStreetMap (Overpass API)</h2>";

$overpass_url = "https://overpass-api.de/api/interpreter";

// Query Overpass QL pour récupérer toutes les stations-service au Cameroun
$query = '
[out:json][timeout:180];
area["ISO3166-1"="CM"][admin_level=2]->.cameroun;
(
  node["amenity"="fuel"](area.cameroun);
  way["amenity"="fuel"](area.cameroun);
  relation["amenity"="fuel"](area.cameroun);
);
out center tags;
';

echo "<p><strong>Query Overpass:</strong></p>";
echo "<pre style='background:#f8f9fa;padding:10px;border-radius:5px;overflow-x:auto;'>" . htmlspecialchars($query) . "</pre>";
echo "<p>⏳ Envoi de la requête à l'API Overpass...</p>";
echo "</div>";

// Envoi de la requête
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $overpass_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, "data=" . urlencode($query));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 180);
curl_setopt($ch, CURLOPT_USERAGENT, 'SGDI-MINEE-DPPG/1.0');
// Désactiver vérification SSL pour environnement local
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo "<div class='error'>❌ Erreur CURL: " . htmlspecialchars($curl_error) . "</div>";
    echo "</div></body></html>";
    exit;
}

if ($http_code !== 200) {
    echo "<div class='error'>❌ Erreur HTTP $http_code</div>";
    echo "</div></body></html>";
    exit;
}

// Étape 2: Parsing des données
echo "<div class='step'>";
echo "<h2>📊 Étape 2: Parsing des données JSON</h2>";

$data = json_decode($response, true);

if (!$data || !isset($data['elements'])) {
    echo "<div class='error'>❌ Erreur: Impossible de parser les données JSON</div>";
    echo "</div></body></html>";
    exit;
}

$stations = [];
$stats = [
    'total' => count($data['elements']),
    'avec_nom' => 0,
    'avec_operateur' => 0,
    'avec_adresse' => 0,
    'par_region' => []
];

echo "<p>✅ <strong>" . $stats['total'] . " éléments</strong> récupérés depuis OSM</p>";

// Mapping des régions du Cameroun (approximatif par coordonnées)
$regions_map = [
    'Littoral' => ['lat_min' => 3.5, 'lat_max' => 5.0, 'lon_min' => 9.0, 'lon_max' => 10.5],
    'Centre' => ['lat_min' => 3.0, 'lat_max' => 5.0, 'lon_min' => 10.5, 'lon_max' => 12.5],
    'Sud' => ['lat_min' => 2.0, 'lat_max' => 3.5, 'lon_min' => 9.5, 'lon_max' => 12.0],
    'Est' => ['lat_min' => 3.0, 'lat_max' => 6.0, 'lon_min' => 12.5, 'lon_max' => 16.0],
    'Adamaoua' => ['lat_min' => 6.0, 'lat_max' => 8.0, 'lon_min' => 11.5, 'lon_max' => 15.0],
    'Nord' => ['lat_min' => 8.0, 'lat_max' => 10.0, 'lon_min' => 13.0, 'lon_max' => 15.5],
    'Extrême-Nord' => ['lat_min' => 10.0, 'lat_max' => 13.0, 'lon_min' => 13.5, 'lon_max' => 15.5],
    'Ouest' => ['lat_min' => 5.0, 'lat_max' => 6.5, 'lon_min' => 9.5, 'lon_max' => 11.0],
    'Nord-Ouest' => ['lat_min' => 5.5, 'lat_max' => 7.0, 'lon_min' => 9.5, 'lon_max' => 11.0],
    'Sud-Ouest' => ['lat_min' => 3.5, 'lat_max' => 5.5, 'lon_min' => 8.5, 'lon_max' => 10.0],
];

function getRegionFromCoords($lat, $lon, $regions_map) {
    foreach ($regions_map as $region => $bounds) {
        if ($lat >= $bounds['lat_min'] && $lat <= $bounds['lat_max'] &&
            $lon >= $bounds['lon_min'] && $lon <= $bounds['lon_max']) {
            return $region;
        }
    }
    return 'Non déterminé';
}

// Traitement de chaque élément
foreach ($data['elements'] as $element) {
    $tags = $element['tags'] ?? [];

    // Récupérer les coordonnées
    $lat = null;
    $lon = null;

    if ($element['type'] === 'node') {
        $lat = $element['lat'] ?? null;
        $lon = $element['lon'] ?? null;
    } elseif (isset($element['center'])) {
        $lat = $element['center']['lat'] ?? null;
        $lon = $element['center']['lon'] ?? null;
    }

    if (!$lat || !$lon) continue;

    // Déterminer la région
    $region = getRegionFromCoords($lat, $lon, $regions_map);

    // Extraire les informations
    $nom = $tags['name'] ?? 'Station sans nom';
    $operateur = $tags['operator'] ?? $tags['brand'] ?? '';
    $adresse = $tags['addr:street'] ?? '';
    $ville = $tags['addr:city'] ?? '';
    $quartier = $tags['addr:suburb'] ?? '';

    // Statistiques
    if (!empty($tags['name'])) $stats['avec_nom']++;
    if (!empty($operateur)) $stats['avec_operateur']++;
    if (!empty($adresse)) $stats['avec_adresse']++;
    if (!isset($stats['par_region'][$region])) $stats['par_region'][$region] = 0;
    $stats['par_region'][$region]++;

    $stations[] = [
        'osm_id' => $element['type'] . '/' . $element['id'],
        'nom' => $nom,
        'operateur' => $operateur,
        'latitude' => $lat,
        'longitude' => $lon,
        'ville' => $ville,
        'quartier' => $quartier,
        'adresse' => $adresse,
        'region' => $region,
        'osm_type' => $element['type'],
        'tags' => json_encode($tags, JSON_UNESCAPED_UNICODE)
    ];
}

echo "<p>✅ <strong>" . count($stations) . " stations-service</strong> extraites avec coordonnées valides</p>";
echo "</div>";

// Étape 3: Statistiques
echo "<div class='step'>";
echo "<h2>📈 Étape 3: Statistiques</h2>";

echo "<table>";
echo "<tr><th>Métrique</th><th>Valeur</th><th>Pourcentage</th></tr>";
echo "<tr><td>Total stations OSM</td><td><strong>" . $stats['total'] . "</strong></td><td>100%</td></tr>";
echo "<tr><td>Avec coordonnées valides</td><td><strong>" . count($stations) . "</strong></td><td>" . round(count($stations)/$stats['total']*100, 1) . "%</td></tr>";
echo "<tr><td>Avec nom</td><td>" . $stats['avec_nom'] . "</td><td>" . round($stats['avec_nom']/count($stations)*100, 1) . "%</td></tr>";
echo "<tr><td>Avec opérateur</td><td>" . $stats['avec_operateur'] . "</td><td>" . round($stats['avec_operateur']/count($stations)*100, 1) . "%</td></tr>";
echo "<tr><td>Avec adresse</td><td>" . $stats['avec_adresse'] . "</td><td>" . round($stats['avec_adresse']/count($stations)*100, 1) . "%</td></tr>";
echo "</table>";

echo "<h3>Répartition par région:</h3>";
echo "<table>";
echo "<tr><th>Région</th><th>Nombre de stations</th></tr>";
arsort($stats['par_region']);
foreach ($stats['par_region'] as $region => $count) {
    echo "<tr><td>$region</td><td><strong>$count</strong></td></tr>";
}
echo "</table>";
echo "</div>";

// Étape 4: Export CSV
echo "<div class='step'>";
echo "<h2>💾 Étape 4: Génération fichier CSV</h2>";

$csv_filename = 'stations_osm_cameroun_' . date('Y-m-d_His') . '.csv';
$csv_path = __DIR__ . '/exports/' . $csv_filename;

// Créer le dossier exports si n'existe pas
if (!is_dir(__DIR__ . '/exports')) {
    mkdir(__DIR__ . '/exports', 0755, true);
}

$fp = fopen($csv_path, 'w');

// BOM UTF-8 pour Excel
fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

// En-têtes
fputcsv($fp, [
    'osm_id',
    'nom',
    'operateur',
    'latitude',
    'longitude',
    'ville',
    'quartier',
    'adresse',
    'region',
    'numero_autorisation', // À remplir manuellement
    'date_autorisation',   // À remplir manuellement
    'statut',              // À remplir: historique_autorise
    'type_infrastructure', // À remplir: station_service
    'sous_type',           // À remplir: implantation ou reprise
    'osm_type',
    'remarques'            // Notes diverses
], ';');

// Données
foreach ($stations as $station) {
    fputcsv($fp, [
        $station['osm_id'],
        $station['nom'],
        $station['operateur'],
        $station['latitude'],
        $station['longitude'],
        $station['ville'],
        $station['quartier'],
        $station['adresse'],
        $station['region'],
        '', // numero_autorisation
        '', // date_autorisation
        'historique_autorise', // statut par défaut
        'station_service',     // type par défaut
        'implantation',        // sous_type par défaut
        $station['osm_type'],
        ''  // remarques
    ], ';');
}

fclose($fp);

echo "<div class='success'>";
echo "<h3>✅ Fichier CSV généré avec succès!</h3>";
echo "<p><strong>Nom:</strong> $csv_filename</p>";
echo "<p><strong>Emplacement:</strong> exports/$csv_filename</p>";
echo "<p><strong>Taille:</strong> " . number_format(filesize($csv_path)) . " octets</p>";
echo "<p><strong>Nombre de lignes:</strong> " . (count($stations) + 1) . " (1 en-tête + " . count($stations) . " stations)</p>";
echo "</div>";

echo "<a href='exports/$csv_filename' class='btn btn-success' download>📥 Télécharger le fichier CSV</a>";
echo "</div>";

// Étape 5: Aperçu des données
echo "<div class='step'>";
echo "<h2>👀 Étape 5: Aperçu des premières stations (10 premières)</h2>";

echo "<table>";
echo "<tr><th>Nom</th><th>Opérateur</th><th>Ville</th><th>Région</th><th>Coordonnées</th></tr>";
$preview = array_slice($stations, 0, 10);
foreach ($preview as $s) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($s['nom']) . "</td>";
    echo "<td>" . htmlspecialchars($s['operateur']) . "</td>";
    echo "<td>" . htmlspecialchars($s['ville']) . "</td>";
    echo "<td>" . htmlspecialchars($s['region']) . "</td>";
    echo "<td>" . number_format($s['latitude'], 6) . ", " . number_format($s['longitude'], 6) . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "<p><em>... et " . (count($stations) - 10) . " autres stations</em></p>";
echo "</div>";

// Instructions finales
echo "<div class='info'>";
echo "<h2>📋 Prochaines étapes</h2>";
echo "<ol>";
echo "<li><strong>Téléchargez le fichier CSV</strong> (bouton ci-dessus)</li>";
echo "<li><strong>Ouvrez-le dans Excel ou LibreOffice</strong></li>";
echo "<li><strong>Complétez les colonnes manquantes:</strong>";
echo "<ul>";
echo "<li><code>numero_autorisation</code> - Numéro d'arrêté MINEE (ex: HIST-SS-LT-2015-001)</li>";
echo "<li><code>date_autorisation</code> - Date au format YYYY-MM-DD</li>";
echo "<li><code>sous_type</code> - Modifier si nécessaire (implantation ou reprise)</li>";
echo "<li><code>remarques</code> - Notes diverses</li>";
echo "</ul></li>";
echo "<li><strong>Validez chaque station:</strong>";
echo "<ul>";
echo "<li>Vérifiez que la station est bien autorisée MINEE</li>";
echo "<li>Supprimez les lignes non autorisées</li>";
echo "<li>Corrigez les informations inexactes</li>";
echo "</ul></li>";
echo "<li><strong>Importez dans SGDI:</strong>";
echo "<ul>";
echo "<li>Module: <a href='modules/import_historique/'>Import Historique</a></li>";
echo "<li>Format compatible avec le module existant</li>";
echo "</ul></li>";
echo "</ol>";
echo "</div>";

echo "<div class='info'>";
echo "<h2>⚠️ Important - Licence et attribution</h2>";
echo "<p><strong>Source des données:</strong> © OpenStreetMap contributors</p>";
echo "<p><strong>Licence:</strong> Open Database License (ODbL)</p>";
echo "<p><strong>Attribution requise:</strong> Les données proviennent d'OpenStreetMap et doivent être créditées</p>";
echo "<p><strong>Plus d'infos:</strong> <a href='https://www.openstreetmap.org/copyright' target='_blank'>https://www.openstreetmap.org/copyright</a></p>";
echo "</div>";

echo "<div style='text-align:center;margin-top:30px;'>";
echo "<a href='extract_osm_stations.php' class='btn'>🔄 Relancer l'extraction</a>";
echo "<a href='../../modules/import_historique/' class='btn'>📥 Aller au module Import</a>";
echo "<a href='../../dashboard.php' class='btn'>🏠 Retour Dashboard</a>";
echo "</div>";

echo "</div>"; // container
echo "</body></html>";

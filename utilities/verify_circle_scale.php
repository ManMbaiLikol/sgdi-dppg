<?php
/**
 * Script de vérification de l'échelle des cercles de contrainte
 * Calcule la distance réelle affichée par les cercles Leaflet à différents zooms
 */

require_once 'config/database.php';

// Fonction de calcul de distance Haversine (en mètres)
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Rayon de la Terre en mètres

    $lat1Rad = deg2rad($lat1);
    $lat2Rad = deg2rad($lat2);
    $deltaLatRad = deg2rad($lat2 - $lat1);
    $deltaLonRad = deg2rad($lon2 - $lon1);

    $a = sin($deltaLatRad / 2) * sin($deltaLatRad / 2) +
         cos($lat1Rad) * cos($lat2Rad) *
         sin($deltaLonRad / 2) * sin($deltaLonRad / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}

// Récupérer toutes les infrastructures autorisées pour analyse complète
$stmt = $pdo->query("
    SELECT
        d.numero,
        d.nom_demandeur,
        d.type_infrastructure,
        d.coordonnees_gps,
        d.statut,
        d.region
    FROM dossiers d
    WHERE d.coordonnees_gps IS NOT NULL
    AND d.coordonnees_gps != ''
    AND d.statut IN ('autorise', 'historique_autorise')
    ORDER BY d.id
");

$dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Extraire latitude et longitude des coordonnées GPS
$stations = [];
foreach ($dossiers as $dossier) {
    $coords = parseGPSCoordinates($dossier['coordonnees_gps']);
    if ($coords) {
        $stations[] = [
            'nom_station' => $dossier['nom_demandeur'],
            'numero' => $dossier['numero'],
            'latitude' => $coords['latitude'],
            'longitude' => $coords['longitude'],
            'type' => $dossier['type_infrastructure'],
            'statut' => $dossier['statut'],
            'region' => $dossier['region']
        ];
    }
}

// Statistiques sur le jeu de données
$total_stations = count($stations);
$regions_uniques = array_unique(array_column($stations, 'region'));

// Fonction pour parser les coordonnées GPS (formats variés)
function parseGPSCoordinates($gps_string) {
    if (empty($gps_string)) return null;

    // Format: "lat, lon" ou "lat,lon"
    $parts = array_map('trim', explode(',', $gps_string));
    if (count($parts) === 2) {
        $lat = floatval($parts[0]);
        $lon = floatval($parts[1]);

        // Vérifier que les coordonnées sont valides (Cameroun)
        if ($lat >= 1.5 && $lat <= 13.5 && $lon >= 8.0 && $lon <= 16.5) {
            return ['latitude' => $lat, 'longitude' => $lon];
        }
    }

    return null;
}

echo "<!DOCTYPE html>\n";
echo "<html>\n<head>\n";
echo "<title>Vérification Échelle Cercles - DPPG</title>\n";
echo "<meta charset='UTF-8'>\n";
echo "<link rel='stylesheet' href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css' />\n";
echo "<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background: #f5f5f5;
}
.container {
    max-width: 1400px;
    margin: 0 auto;
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
h1 {
    color: #2c3e50;
    border-bottom: 3px solid #3498db;
    padding-bottom: 10px;
}
.info-box {
    background: #e8f4f8;
    border-left: 4px solid #3498db;
    padding: 15px;
    margin: 20px 0;
}
.warning-box {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 15px;
    margin: 20px 0;
}
.error-box {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
    padding: 15px;
    margin: 20px 0;
}
.success-box {
    background: #d4edda;
    border-left: 4px solid #28a745;
    padding: 15px;
    margin: 20px 0;
}
#map {
    height: 600px;
    margin: 20px 0;
    border: 2px solid #ddd;
    border-radius: 4px;
}
.controls {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}
.controls label {
    display: inline-block;
    margin-right: 10px;
    font-weight: bold;
}
.controls select, .controls input {
    padding: 5px 10px;
    margin-right: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
}
th, td {
    padding: 12px;
    text-align: left;
    border: 1px solid #ddd;
}
th {
    background: #3498db;
    color: white;
    font-weight: bold;
}
tr:nth-child(even) {
    background: #f8f9fa;
}
.metric {
    display: inline-block;
    margin: 10px 20px 10px 0;
}
.metric-label {
    font-weight: bold;
    color: #555;
}
.metric-value {
    font-size: 1.2em;
    color: #2c3e50;
    margin-left: 5px;
}
.ruler {
    position: relative;
    height: 30px;
    background: linear-gradient(to right, #3498db 0%, #3498db 50%, transparent 50%);
    background-size: 100px 100%;
    border: 1px solid #3498db;
    margin: 10px 0;
}
.ruler-label {
    position: absolute;
    top: 35px;
    font-size: 0.9em;
    color: #666;
}
</style>
</head>\n<body>\n";

echo "<div class='container'>\n";
echo "<h1>🔍 Vérification de l'Échelle des Cercles de Contrainte (500m)</h1>\n";

echo "<div class='info-box'>\n";
echo "<strong>Objectif :</strong> Vérifier que les cercles de 500m affichés sur la carte correspondent bien à 500 mètres réels sur le terrain.<br>\n";
echo "<strong>Méthode :</strong> Comparaison entre le rayon Leaflet et les distances calculées par la formule de Haversine.<br>\n";
echo "<strong>Données analysées :</strong> $total_stations infrastructures autorisées dans " . count($regions_uniques) . " régions.\n";
echo "</div>\n";

// Analyse théorique
echo "<h2>📐 Analyse Théorique</h2>\n";
echo "<div class='warning-box'>\n";
echo "<strong>Problème potentiel identifié :</strong><br>\n";
echo "Les cercles L.circle() de Leaflet utilisent une projection Web Mercator (EPSG:3857) qui déforme les distances, surtout à mesure qu'on s'éloigne de l'équateur.<br><br>\n";
echo "<strong>À Douala (latitude ~4°) :</strong><br>\n";
echo "• 1 degré de latitude ≈ 111 km<br>\n";
echo "• 1 degré de longitude ≈ 111 km × cos(4°) ≈ 110.7 km<br>\n";
echo "• 500m = 0.0045° de latitude ou longitude<br><br>\n";
echo "<strong>Sur votre capture :</strong> Les cercles semblent faire ~1-2 km de diamètre au lieu de 1 km (2×500m), ce qui suggère un facteur d'échelle de 2-4×.\n";
echo "</div>\n";

// Carte de test
echo "<h2>🗺️ Carte de Test Interactive (Toutes les stations)</h2>\n";
echo "<div class='info-box'>\n";
echo "<strong>Instructions :</strong><br>\n";
echo "1. Utilisez l'<strong>échelle métrique</strong> en bas à gauche pour vérifier que les cercles correspondent bien à 500m<br>\n";
echo "2. Zoomez sur des <strong>groupes de stations proches</strong> pour voir les chevauchements<br>\n";
echo "3. Testez différents <strong>rayons</strong> pour trouver la valeur visuellement correcte<br>\n";
echo "4. Les cercles rouges qui se chevauchent indiquent des <strong>violations potentielles</strong> de la distance minimale\n";
echo "</div>\n";
echo "<div class='controls'>\n";
echo "<label>Rayon du cercle (m):</label>\n";
echo "<input type='number' id='radiusInput' value='500' min='100' max='2000' step='50'>\n";
echo "<button onclick='updateCircleRadius()' style='padding: 5px 15px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;'>Mettre à jour</button>\n";
echo "<button onclick='showOnlyClose()' style='padding: 5px 15px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;'>Afficher seulement les proches (&lt;1km)</button>\n";
echo "<span style='margin-left: 20px;'>Zoom actuel: <strong id='currentZoom'>11</strong></span>\n";
echo "<span style='margin-left: 10px;'>Stations affichées: <strong id='stationsCount'>$total_stations</strong></span>\n";
echo "</div>\n";
echo "<div id='map'></div>\n";

// Calculs de distance pour chaque paire de stations
echo "<h2>📊 Analyse des Distances Entre Stations</h2>\n";

$distanceAnalysis = [];
$tooCloseCount = 0;

for ($i = 0; $i < count($stations); $i++) {
    for ($j = $i + 1; $j < count($stations); $j++) {
        $station1 = $stations[$i];
        $station2 = $stations[$j];

        $distance = haversineDistance(
            $station1['latitude'], $station1['longitude'],
            $station2['latitude'], $station2['longitude']
        );

        if ($distance < 5000) { // Seulement les stations à moins de 5 km
            $distanceAnalysis[] = [
                'station1' => $station1['nom_station'],
                'station2' => $station2['nom_station'],
                'distance' => $distance,
                'respecte_500m' => $distance >= 500
            ];

            if ($distance < 500) {
                $tooCloseCount++;
            }
        }
    }
}

// Trier par distance
usort($distanceAnalysis, function($a, $b) {
    return $a['distance'] <=> $b['distance'];
});

// Afficher les statistiques
echo "<div class='metric'>\n";
echo "<span class='metric-label'>Paires analysées:</span>\n";
echo "<span class='metric-value'>" . count($distanceAnalysis) . "</span>\n";
echo "</div>\n";

echo "<div class='metric'>\n";
echo "<span class='metric-label'>Violations 500m:</span>\n";
echo "<span class='metric-value' style='color: " . ($tooCloseCount > 0 ? '#dc3545' : '#28a745') . ";'>" . $tooCloseCount . "</span>\n";
echo "</div>\n";

if (count($distanceAnalysis) > 0) {
    echo "<table>\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th>Station 1</th>\n";
    echo "<th>Station 2</th>\n";
    echo "<th>Distance (m)</th>\n";
    echo "<th>Distance (km)</th>\n";
    echo "<th>Respecte 500m</th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";

    foreach (array_slice($distanceAnalysis, 0, 20) as $analysis) {
        $statusClass = $analysis['respecte_500m'] ? 'success' : 'danger';
        $statusText = $analysis['respecte_500m'] ? '✅ Oui' : '❌ Non';
        $rowStyle = !$analysis['respecte_500m'] ? 'background: #f8d7da;' : '';

        echo "<tr style='$rowStyle'>\n";
        echo "<td>" . htmlspecialchars($analysis['station1']) . "</td>\n";
        echo "<td>" . htmlspecialchars($analysis['station2']) . "</td>\n";
        echo "<td>" . number_format($analysis['distance'], 1) . " m</td>\n";
        echo "<td>" . number_format($analysis['distance'] / 1000, 2) . " km</td>\n";
        echo "<td><strong>$statusText</strong></td>\n";
        echo "</tr>\n";
    }

    echo "</tbody>\n";
    echo "</table>\n";
}

// Recommandations
echo "<h2>💡 Recommandations</h2>\n";

if ($tooCloseCount > 0) {
    echo "<div class='error-box'>\n";
    echo "<strong>⚠️ ATTENTION :</strong> $tooCloseCount paire(s) de stations ne respectent pas la distance minimale de 500m.<br>\n";
    echo "Cela peut indiquer :\n";
    echo "<ul>\n";
    echo "<li>Des données GPS imprécises</li>\n";
    echo "<li>Des autorisations exceptionnelles accordées</li>\n";
    echo "<li>Des erreurs de saisie dans la base de données</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
} else {
    echo "<div class='success-box'>\n";
    echo "<strong>✅ PARFAIT :</strong> Toutes les stations analysées respectent la distance minimale de 500m.\n";
    echo "</div>\n";
}

echo "<div class='info-box'>\n";
echo "<strong>Solution au problème visuel :</strong><br>\n";
echo "Si les cercles apparaissent trop grands sur la carte, deux options :\n";
echo "<ol>\n";
echo "<li><strong>Utiliser L.circle avec CRS approprié :</strong> Vérifier que Leaflet utilise bien EPSG:3857</li>\n";
echo "<li><strong>Ajuster le rayon visuellement :</strong> Diviser le rayon par un facteur correctif basé sur la latitude</li>\n";
echo "<li><strong>Utiliser L.geodesicCircle :</strong> Plugin qui gère correctement les distances géodésiques</li>\n";
echo "</ol>\n";
echo "</div>\n";

// JavaScript pour la carte interactive
echo "<script src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'></script>\n";
echo "<script>\n";
echo "const stations = " . json_encode($stations) . ";\n";
echo "\n";
echo "// Initialiser la carte sur Douala\n";
echo "const map = L.map('map').setView([4.0511, 9.7679], 11);\n";
echo "\n";
echo "L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {\n";
echo "    attribution: '&copy; OpenStreetMap contributors',\n";
echo "    maxZoom: 19\n";
echo "}).addTo(map);\n";
echo "\n";
echo "// Stocker les cercles\n";
echo "let circles = [];\n";
echo "\n";
echo "// Ajouter les stations et leurs cercles\n";
echo "stations.forEach(function(station) {\n";
echo "    // Marqueur\n";
echo "    const marker = L.marker([station.latitude, station.longitude])\n";
echo "        .bindPopup('<strong>' + station.nom_station + '</strong><br>Type: ' + station.type + '<br>Statut: ' + station.statut)\n";
echo "        .addTo(map);\n";
echo "    \n";
echo "    // Cercle de contrainte\n";
echo "    const circle = L.circle([station.latitude, station.longitude], {\n";
echo "        color: '#dc3545',\n";
echo "        fillColor: '#dc3545',\n";
echo "        fillOpacity: 0.1,\n";
echo "        radius: 500,\n";
echo "        weight: 1,\n";
echo "        opacity: 0.5\n";
echo "    }).addTo(map);\n";
echo "    \n";
echo "    circle.bindTooltip('Zone 500m<br>' + station.nom_station, {\n";
echo "        permanent: false,\n";
echo "        direction: 'center'\n";
echo "    });\n";
echo "    \n";
echo "    circles.push({obj: circle, marker: marker});\n";
echo "});\n";
echo "\n";
echo "// Ajouter une échelle métrique\n";
echo "L.control.scale({\n";
echo "    metric: true,\n";
echo "    imperial: false,\n";
echo "    position: 'bottomleft'\n";
echo "}).addTo(map);\n";
echo "\n";
echo "// Mettre à jour l'affichage du zoom\n";
echo "map.on('zoomend', function() {\n";
echo "    document.getElementById('currentZoom').textContent = map.getZoom();\n";
echo "});\n";
echo "\n";
echo "// Fonction pour mettre à jour le rayon\n";
echo "function updateCircleRadius() {\n";
echo "    const newRadius = parseInt(document.getElementById('radiusInput').value);\n";
echo "    circles.forEach(function(circleData) {\n";
echo "        circleData.obj.setRadius(newRadius);\n";
echo "    });\n";
echo "    console.log('Rayon mis à jour:', newRadius, 'm');\n";
echo "}\n";
echo "\n";
echo "// Fonction pour n'afficher que les stations proches (<1km)\n";
echo "let showingOnlyClose = false;\n";
echo "function showOnlyClose() {\n";
echo "    if (showingOnlyClose) {\n";
echo "        // Réafficher toutes les stations\n";
echo "        circles.forEach(function(circleData) {\n";
echo "            if (!map.hasLayer(circleData.obj)) {\n";
echo "                map.addLayer(circleData.obj);\n";
echo "                map.addLayer(circleData.marker);\n";
echo "            }\n";
echo "        });\n";
echo "        document.getElementById('stationsCount').textContent = stations.length;\n";
echo "        showingOnlyClose = false;\n";
echo "    } else {\n";
echo "        // Calculer les paires proches\n";
echo "        const closeStations = new Set();\n";
echo "        for (let i = 0; i < stations.length; i++) {\n";
echo "            for (let j = i + 1; j < stations.length; j++) {\n";
echo "                const distance = haversineDistance(\n";
echo "                    stations[i].latitude, stations[i].longitude,\n";
echo "                    stations[j].latitude, stations[j].longitude\n";
echo "                );\n";
echo "                if (distance < 1000) {\n";
echo "                    closeStations.add(i);\n";
echo "                    closeStations.add(j);\n";
echo "                }\n";
echo "            }\n";
echo "        }\n";
echo "\n";
echo "        // Masquer les stations non proches\n";
echo "        circles.forEach(function(circleData, idx) {\n";
echo "            if (!closeStations.has(idx)) {\n";
echo "                map.removeLayer(circleData.obj);\n";
echo "                map.removeLayer(circleData.marker);\n";
echo "            }\n";
echo "        });\n";
echo "\n";
echo "        document.getElementById('stationsCount').textContent = closeStations.size;\n";
echo "        showingOnlyClose = true;\n";
echo "\n";
echo "        // Zoomer sur le premier groupe\n";
echo "        if (closeStations.size > 0) {\n";
echo "            const firstIdx = Array.from(closeStations)[0];\n";
echo "            map.setView([stations[firstIdx].latitude, stations[firstIdx].longitude], 14);\n";
echo "        }\n";
echo "    }\n";
echo "}\n";
echo "\n";
echo "// Fonction Haversine côté client\n";
echo "function haversineDistance(lat1, lon1, lat2, lon2) {\n";
echo "    const R = 6371000; // Rayon de la Terre en mètres\n";
echo "    const phi1 = lat1 * Math.PI / 180;\n";
echo "    const phi2 = lat2 * Math.PI / 180;\n";
echo "    const deltaPhi = (lat2 - lat1) * Math.PI / 180;\n";
echo "    const deltaLambda = (lon2 - lon1) * Math.PI / 180;\n";
echo "\n";
echo "    const a = Math.sin(deltaPhi/2) * Math.sin(deltaPhi/2) +\n";
echo "              Math.cos(phi1) * Math.cos(phi2) *\n";
echo "              Math.sin(deltaLambda/2) * Math.sin(deltaLambda/2);\n";
echo "    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));\n";
echo "\n";
echo "    return R * c;\n";
echo "}\n";
echo "\n";
echo "// Log pour diagnostic\n";
echo "console.log('Carte initialisée avec', stations.length, 'stations');\n";
echo "console.log('CRS utilisé:', map.options.crs);\n";
echo "</script>\n";

echo "</div>\n";
echo "</body>\n</html>\n";
?>

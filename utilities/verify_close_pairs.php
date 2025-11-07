<?php
/**
 * Vérification visuelle des paires GPS proches
 * Affiche les deux stations sur la même carte pour comparaison
 */

require_once 'config/database.php';

// Fonction Haversine
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000;
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

function parseGPSCoordinates($gps_string) {
    if (empty($gps_string)) return null;
    $parts = array_map('trim', explode(',', $gps_string));
    if (count($parts) === 2) {
        $lat = floatval($parts[0]);
        $lon = floatval($parts[1]);
        if ($lat >= 1.5 && $lat <= 13.5 && $lon >= 8.0 && $lon <= 16.5) {
            return ['latitude' => $lat, 'longitude' => $lon];
        }
    }
    return null;
}

// Récupérer toutes les stations
$stmt = $pdo->query("
    SELECT d.id, d.numero, d.nom_demandeur, d.coordonnees_gps, d.statut, d.region
    FROM dossiers d
    WHERE d.coordonnees_gps IS NOT NULL
    AND d.coordonnees_gps != ''
    AND d.statut IN ('autorise', 'historique_autorise')
    ORDER BY d.nom_demandeur
");

$all_stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stations = [];

foreach ($all_stations as $station) {
    $coords = parseGPSCoordinates($station['coordonnees_gps']);
    if ($coords) {
        $stations[] = array_merge($station, $coords);
    }
}

// Détecter les paires très proches (<10m)
$close_pairs = [];
for ($i = 0; $i < count($stations); $i++) {
    for ($j = $i + 1; $j < count($stations); $j++) {
        $s1 = $stations[$i];
        $s2 = $stations[$j];

        $distance = haversineDistance(
            $s1['latitude'], $s1['longitude'],
            $s2['latitude'], $s2['longitude']
        );

        if ($distance < 10 && strtolower($s1['nom_demandeur']) !== strtolower($s2['nom_demandeur'])) {
            $close_pairs[] = [
                'station1' => $s1,
                'station2' => $s2,
                'distance' => $distance,
                'severity' => $distance < 2 ? 'critical' : ($distance < 5 ? 'high' : 'medium')
            ];
        }
    }
}

usort($close_pairs, fn($a, $b) => $a['distance'] <=> $b['distance']);

$pair_index = isset($_GET['pair']) ? intval($_GET['pair']) : 0;
$current_pair = $close_pairs[$pair_index] ?? null;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Vérification Visuelle des Paires GPS - DPPG</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header h1 { margin: 0; }
        .container { max-width: 1600px; margin: 0 auto; padding: 20px; }
        .navigation { background: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn { padding: 12px 25px; margin: 5px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .comparison { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .station-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .station-card h3 { margin-top: 0; }
        .station-card.s1 { border-left: 5px solid #3498db; }
        .station-card.s2 { border-left: 5px solid #e74c3c; }
        #map { height: 600px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .distance-badge { display: inline-block; padding: 10px 20px; border-radius: 20px; font-size: 1.2em; font-weight: bold; margin: 10px 0; }
        .critical { background: #e74c3c; color: white; }
        .high { background: #f39c12; color: white; }
        .medium { background: #f39c12; color: white; }
        .info-box { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .verdict { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .verdict h2 { margin-top: 0; }
    </style>
</head>
<body>

<div class="header">
    <h1>🔍 Vérification Visuelle des Paires GPS Proches</h1>
</div>

<div class="container">
    <?php if (!$current_pair): ?>
        <div class="info-box">
            <strong>✅ Toutes les paires ont été vérifiées !</strong><br>
            Retournez au diagnostic pour voir les résultats.
        </div>
        <a href="detect_gps_collisions.php" class="btn btn-primary">← Retour au Diagnostic</a>
    <?php else: ?>
        <div class="navigation">
            <span style="font-size: 1.2em; font-weight: bold;">
                Paire <?php echo $pair_index + 1; ?> / <?php echo count($close_pairs); ?>
            </span>
            <br><br>
            <?php if ($pair_index > 0): ?>
                <a href="?pair=<?php echo $pair_index - 1; ?>" class="btn btn-secondary">← Précédente</a>
            <?php endif; ?>

            <a href="detect_gps_collisions.php" class="btn btn-primary">📊 Diagnostic</a>

            <?php if ($pair_index < count($close_pairs) - 1): ?>
                <a href="?pair=<?php echo $pair_index + 1; ?>" class="btn btn-secondary">Suivante →</a>
            <?php endif; ?>
        </div>

        <div class="info-box">
            <strong>Instructions :</strong><br>
            1. 🗺️ Regardez la carte ci-dessous (utilisez la vue satellite en bas à gauche)<br>
            2. 🔍 Identifiez s'il y a <strong>une ou deux stations physiques</strong> à cet endroit<br>
            3. ✅ Prenez votre décision : Doublon ou Stations distinctes
        </div>

        <div class="comparison">
            <div class="station-card s1">
                <h3>🔵 STATION 1</h3>
                <p><strong>Nom :</strong> <?php echo htmlspecialchars($current_pair['station1']['nom_demandeur']); ?></p>
                <p><strong>Numéro :</strong> <?php echo $current_pair['station1']['numero']; ?></p>
                <p><strong>GPS :</strong> <?php echo $current_pair['station1']['coordonnees_gps']; ?></p>
                <p><strong>Région :</strong> <?php echo htmlspecialchars($current_pair['station1']['region'] ?? 'N/A'); ?></p>
                <p><strong>Statut :</strong> <?php echo $current_pair['station1']['statut']; ?></p>
            </div>

            <div class="station-card s2">
                <h3>🔴 STATION 2</h3>
                <p><strong>Nom :</strong> <?php echo htmlspecialchars($current_pair['station2']['nom_demandeur']); ?></p>
                <p><strong>Numéro :</strong> <?php echo $current_pair['station2']['numero']; ?></p>
                <p><strong>GPS :</strong> <?php echo $current_pair['station2']['coordonnees_gps']; ?></p>
                <p><strong>Région :</strong> <?php echo htmlspecialchars($current_pair['station2']['region'] ?? 'N/A'); ?></p>
                <p><strong>Statut :</strong> <?php echo $current_pair['station2']['statut']; ?></p>
            </div>
        </div>

        <div style="text-align: center;">
            <span class="distance-badge <?php echo $current_pair['severity']; ?>">
                Distance : <?php echo round($current_pair['distance'], 1); ?> mètres
            </span>
            <?php if ($current_pair['distance'] < 2): ?>
                <br><span style="color: #e74c3c; font-weight: bold;">⚠️ DOUBLON TRÈS PROBABLE</span>
            <?php elseif ($current_pair['distance'] < 5): ?>
                <br><span style="color: #f39c12; font-weight: bold;">⚠️ VÉRIFICATION NÉCESSAIRE</span>
            <?php endif; ?>
        </div>

        <div id="map"></div>

        <div class="verdict">
            <h2>🎯 Votre Verdict</h2>
            <p style="font-size: 1.1em; margin: 20px 0;">
                Qu'avez-vous observé sur la carte ?
            </p>

            <a href="execute_merge.php?merge=<?php echo $current_pair['station2']['id']; ?>&keep=<?php echo $current_pair['station1']['id']; ?>"
               class="btn btn-danger"
               style="font-size: 1.1em;">
                🔄 C'EST UN DOUBLON → Fusionner
            </a>

            <a href="?pair=<?php echo $pair_index + 1; ?>"
               class="btn btn-success"
               style="font-size: 1.1em;">
                ✅ STATIONS DISTINCTES → Passer à la suivante
            </a>
        </div>

        <div class="warning-box">
            <strong>💡 Astuce :</strong><br>
            • Si vous voyez <strong>une seule pompe à essence</strong> → Doublon à fusionner<br>
            • Si vous voyez <strong>deux bâtiments/pompes distincts</strong> → Stations légitimes (même si très proches)<br>
            • En cas de doute, utilisez Google Street View pour voir les enseignes
        </div>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
<?php if ($current_pair): ?>
    // Calculer le centre entre les deux stations
    const lat1 = <?php echo $current_pair['station1']['latitude']; ?>;
    const lon1 = <?php echo $current_pair['station1']['longitude']; ?>;
    const lat2 = <?php echo $current_pair['station2']['latitude']; ?>;
    const lon2 = <?php echo $current_pair['station2']['longitude']; ?>;

    const centerLat = (lat1 + lat2) / 2;
    const centerLon = (lon1 + lon2) / 2;

    // Créer la carte
    const map = L.map('map').setView([centerLat, centerLon], 19);

    // Couche OpenStreetMap par défaut
    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 20
    }).addTo(map);

    // Couche satellite (Esri)
    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; Esri',
        maxZoom: 20
    });

    // Contrôle de couches
    const baseMaps = {
        "🗺️ Carte Standard": osmLayer,
        "🛰️ Vue Satellite": satelliteLayer
    };

    L.control.layers(baseMaps).addTo(map);

    // Ajouter les marqueurs
    const marker1 = L.marker([lat1, lon1], {
        icon: L.divIcon({
            className: 'custom-icon',
            html: '<div style="background: #3498db; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">1</div>',
            iconSize: [30, 30]
        })
    }).addTo(map);

    marker1.bindPopup(`
        <strong>🔵 STATION 1</strong><br>
        <?php echo htmlspecialchars($current_pair['station1']['nom_demandeur']); ?><br>
        <small><?php echo $current_pair['station1']['numero']; ?></small>
    `);

    const marker2 = L.marker([lat2, lon2], {
        icon: L.divIcon({
            className: 'custom-icon',
            html: '<div style="background: #e74c3c; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">2</div>',
            iconSize: [30, 30]
        })
    }).addTo(map);

    marker2.bindPopup(`
        <strong>🔴 STATION 2</strong><br>
        <?php echo htmlspecialchars($current_pair['station2']['nom_demandeur']); ?><br>
        <small><?php echo $current_pair['station2']['numero']; ?></small>
    `);

    // Ligne entre les deux points
    L.polyline([[lat1, lon1], [lat2, lon2]], {
        color: '#f39c12',
        weight: 3,
        opacity: 0.7,
        dashArray: '10, 10'
    }).addTo(map);

    // Cercle de 10m autour du centre
    L.circle([centerLat, centerLon], {
        color: '#e74c3c',
        fillColor: '#e74c3c',
        fillOpacity: 0.1,
        radius: 10,
        weight: 2
    }).addTo(map).bindPopup('Zone de 10 mètres');

    // Échelle
    L.control.scale({metric: true, imperial: false}).addTo(map);

    // Ouvrir les popups
    marker1.openPopup();
<?php endif; ?>
</script>

</body>
</html>

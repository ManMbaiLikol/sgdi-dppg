<?php
/**
 * Détection des collisions GPS
 * Identifie les stations DIFFÉRENTES avec les MÊMES coordonnées GPS
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

// Parser GPS
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

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Détection des Collisions GPS - DPPG</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #2c3e50; border-bottom: 3px solid #e74c3c; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; border-left: 4px solid #e74c3c; padding-left: 10px; }
        .critical-box { background: #f8d7da; border: 2px solid #dc3545; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
        .info-box { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0; }
        .success-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #e74c3c; text-align: center; }
        .stat-value { font-size: 2.5em; font-weight: bold; color: #e74c3c; }
        .stat-label { color: #7f8c8d; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 0.85em; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #e74c3c; color: white; position: sticky; top: 0; }
        tr:nth-child(even) { background: #f8f9fa; }
        tr.severe { background: #f8d7da; font-weight: bold; }
        .collision-group { background: #fff; padding: 15px; margin: 15px 0; border: 2px solid #e74c3c; border-radius: 8px; }
        .collision-header { background: #e74c3c; color: white; padding: 10px; margin: -15px -15px 15px -15px; border-radius: 6px 6px 0 0; font-weight: bold; }
        .station-item { padding: 10px; margin: 5px 0; background: #f8f9fa; border-left: 4px solid #3498db; border-radius: 4px; }
        .station-item.original { border-left-color: #27ae60; }
        .station-item.duplicate-gps { border-left-color: #e74c3c; }
        .btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #3498db; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-warning { background: #f39c12; color: white; }
    </style>
</head>
<body>

<div class="container">
    <h1>🎯 Détection des Collisions GPS</h1>

    <div class="info-box">
        <strong>Objectif :</strong> Identifier les stations DIFFÉRENTES qui partagent les MÊMES coordonnées GPS.<br>
        <strong>Problème :</strong> GPS copiés/collés, géolocalisation approximative, ou coordonnées par défaut.<br>
        <strong>Impact :</strong> Fausse impression de violations de distance, affichage incorrect sur la carte.
    </div>

    <?php
    // Récupérer toutes les stations avec GPS
    $stmt = $pdo->query("
        SELECT
            d.id,
            d.numero,
            d.nom_demandeur,
            d.coordonnees_gps,
            d.statut,
            d.region,
            d.ville,
            d.type_infrastructure,
            d.date_creation
        FROM dossiers d
        WHERE d.coordonnees_gps IS NOT NULL
        AND d.coordonnees_gps != ''
        AND d.statut IN ('autorise', 'historique_autorise')
        ORDER BY d.coordonnees_gps, d.date_creation
    ");

    $all_stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Regrouper par coordonnées GPS
    $gps_groups = [];
    $stations_with_coords = [];

    foreach ($all_stations as $station) {
        $coords = parseGPSCoordinates($station['coordonnees_gps']);
        if ($coords) {
            $gps_key = round($coords['latitude'], 6) . ',' . round($coords['longitude'], 6);

            if (!isset($gps_groups[$gps_key])) {
                $gps_groups[$gps_key] = [];
            }

            $gps_groups[$gps_key][] = array_merge($station, $coords);
            $stations_with_coords[] = array_merge($station, $coords, ['gps_key' => $gps_key]);
        }
    }

    // Identifier les collisions (GPS identiques, mais stations différentes)
    $collisions = [];
    $collision_count = 0;
    $stations_affected = 0;

    foreach ($gps_groups as $gps_key => $stations) {
        if (count($stations) > 1) {
            // Vérifier si ce sont vraiment des stations différentes (pas juste un doublon de nom)
            $unique_names = array_unique(array_column($stations, 'nom_demandeur'));

            if (count($unique_names) > 1) {
                $collisions[$gps_key] = [
                    'gps' => $gps_key,
                    'count' => count($stations),
                    'stations' => $stations
                ];
                $collision_count++;
                $stations_affected += count($stations);
            }
        }
    }

    // Détecter aussi les GPS très proches (<10m) avec des noms différents
    $near_collisions = [];
    for ($i = 0; $i < count($stations_with_coords); $i++) {
        for ($j = $i + 1; $j < count($stations_with_coords); $j++) {
            $s1 = $stations_with_coords[$i];
            $s2 = $stations_with_coords[$j];

            // Ignorer si même GPS exact (déjà dans collisions)
            if ($s1['gps_key'] === $s2['gps_key']) continue;

            $distance = haversineDistance(
                $s1['latitude'], $s1['longitude'],
                $s2['latitude'], $s2['longitude']
            );

            // GPS différents mais très proches (<10m) et noms différents
            if ($distance < 10 && strtolower($s1['nom_demandeur']) !== strtolower($s2['nom_demandeur'])) {
                $near_collisions[] = [
                    'station1' => $s1,
                    'station2' => $s2,
                    'distance' => $distance
                ];
            }
        }
    }

    // GPS suspects (coordonnées rondes ou communes)
    $suspect_gps = [];
    foreach ($stations_with_coords as $station) {
        $lat = $station['latitude'];
        $lon = $station['longitude'];

        // Coordonnées trop rondes (ex: 4.05, 9.70 = centre de Douala par défaut)
        $lat_decimal = abs($lat - floor($lat));
        $lon_decimal = abs($lon - floor($lon));

        if ($lat_decimal == 0 || $lon_decimal == 0 ||
            ($lat_decimal < 0.01 && $lon_decimal < 0.01)) {
            $suspect_gps[] = $station;
        }
    }
    ?>

    <!-- Statistiques -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-value"><?php echo $collision_count; ?></div>
            <div class="stat-label">Points GPS partagés</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stations_affected; ?></div>
            <div class="stat-label">Stations affectées</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($near_collisions); ?></div>
            <div class="stat-label">GPS très proches (&lt;10m)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($suspect_gps); ?></div>
            <div class="stat-label">GPS suspects (trop ronds)</div>
        </div>
    </div>

    <!-- Évaluation -->
    <?php if ($collision_count === 0 && count($near_collisions) === 0 && count($suspect_gps) === 0): ?>
        <div class="success-box">
            <strong>✅ EXCELLENT !</strong> Aucune collision GPS détectée.<br>
            Toutes les stations ont des coordonnées GPS uniques et précises.
        </div>
    <?php else: ?>
        <div class="critical-box">
            <strong>🚨 PROBLÈME CRITIQUE DÉTECTÉ</strong><br><br>
            <?php if ($collision_count > 0): ?>
                • <strong><?php echo $collision_count; ?> points GPS</strong> sont partagés par <?php echo $stations_affected; ?> stations différentes<br>
            <?php endif; ?>
            <?php if (count($near_collisions) > 0): ?>
                • <strong><?php echo count($near_collisions); ?> paires</strong> de stations ont des GPS quasi-identiques (&lt;10m)<br>
            <?php endif; ?>
            <?php if (count($suspect_gps) > 0): ?>
                • <strong><?php echo count($suspect_gps); ?> stations</strong> ont des coordonnées GPS suspectes (trop rondes)<br>
            <?php endif; ?>
            <br>
            <strong>Conséquence :</strong> Affichage incorrect sur la carte, fausses violations de distance, confusion pour les utilisateurs.
        </div>
    <?php endif; ?>

    <!-- Collisions exactes -->
    <?php if ($collision_count > 0): ?>
        <h2>🎯 Collisions GPS Exactes (Coordonnées Identiques)</h2>

        <div class="warning-box">
            <strong>Explication :</strong> Ces stations DIFFÉRENTES partagent exactement les MÊMES coordonnées GPS.<br>
            <strong>Causes probables :</strong>
            <ul>
                <li>GPS copié/collé d'une station à l'autre</li>
                <li>Géolocalisation approximative au niveau du quartier/ville</li>
                <li>Coordonnées par défaut utilisées par erreur</li>
                <li>Données importées avec GPS génériques</li>
            </ul>
            <strong>Solution :</strong> Géocoder à nouveau chaque station individuellement depuis son adresse précise.
        </div>

        <?php
        // Trier par nombre de stations (les plus gros groupes en premier)
        uasort($collisions, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        foreach ($collisions as $collision):
            $stations = $collision['stations'];
            $first_station = $stations[0];
        ?>
            <div class="collision-group">
                <div class="collision-header">
                    📍 GPS: <?php echo $collision['gps']; ?> |
                    <?php echo $collision['count']; ?> stations différentes au même endroit |
                    Région: <?php echo htmlspecialchars($first_station['region'] ?? 'N/A'); ?>
                </div>

                <?php foreach ($stations as $idx => $station): ?>
                    <div class="station-item <?php echo $idx === 0 ? 'original' : 'duplicate-gps'; ?>">
                        <strong><?php echo $idx === 0 ? '🟢 PREMIÈRE (à garder)' : '🔴 COLLISION #' . $idx; ?></strong><br>
                        <strong>Nom :</strong> <?php echo htmlspecialchars($station['nom_demandeur']); ?><br>
                        <strong>N° :</strong> <?php echo $station['numero']; ?> |
                        <strong>Type :</strong> <?php echo $station['type_infrastructure']; ?> |
                        <strong>Statut :</strong> <?php echo $station['statut']; ?><br>
                        <strong>Ville :</strong> <?php echo htmlspecialchars($station['ville'] ?? 'N/A'); ?><br>
                        <strong>Date création :</strong> <?php echo $station['date_creation']; ?>

                        <?php if ($idx > 0): ?>
                            <br><br>
                            <button class="btn btn-warning" onclick="window.open('https://nominatim.openstreetmap.org/search?q=<?php echo urlencode($station['nom_demandeur'] . ', ' . ($station['ville'] ?? '') . ', ' . ($station['region'] ?? '') . ', Cameroun'); ?>&format=json', '_blank')">
                                🌍 Géocoder cette station
                            </button>
                            <button class="btn btn-primary" onclick="promptNewGPS(<?php echo $station['id']; ?>, '<?php echo htmlspecialchars($station['nom_demandeur'], ENT_QUOTES); ?>')">
                                ✏️ Saisir nouveau GPS
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- GPS très proches -->
    <?php if (count($near_collisions) > 0): ?>
        <h2>⚠️ GPS Très Proches (&lt;10m) - Vérification Recommandée</h2>

        <table>
            <thead>
                <tr>
                    <th>Station 1</th>
                    <th>GPS 1</th>
                    <th>Station 2</th>
                    <th>GPS 2</th>
                    <th>Distance</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($near_collisions, 0, 30) as $nc): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($nc['station1']['nom_demandeur']); ?></strong><br>
                            <small><?php echo $nc['station1']['numero']; ?></small>
                        </td>
                        <td><small><?php echo $nc['station1']['coordonnees_gps']; ?></small></td>
                        <td>
                            <strong><?php echo htmlspecialchars($nc['station2']['nom_demandeur']); ?></strong><br>
                            <small><?php echo $nc['station2']['numero']; ?></small>
                        </td>
                        <td><small><?php echo $nc['station2']['coordonnees_gps']; ?></small></td>
                        <td><strong><?php echo round($nc['distance'], 1); ?> m</strong></td>
                        <td>
                            <button class="btn btn-primary" onclick="window.open('https://www.openstreetmap.org/#map=19/<?php echo $nc['station1']['latitude']; ?>/<?php echo $nc['station1']['longitude']; ?>', '_blank')">
                                🗺️ Voir
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- GPS suspects -->
    <?php if (count($suspect_gps) > 0): ?>
        <h2>🔍 GPS Suspects (Coordonnées Trop Rondes)</h2>

        <div class="info-box">
            <strong>Explication :</strong> Ces coordonnées sont arrondies ou correspondent à des centres de villes (GPS génériques).<br>
            <strong>Exemple :</strong> 4.0500, 9.7000 = centre exact de Douala (probablement pas l'adresse réelle de la station).
        </div>

        <table>
            <thead>
                <tr>
                    <th>Station</th>
                    <th>GPS</th>
                    <th>Ville/Région</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($suspect_gps, 0, 30) as $station): ?>
                    <tr class="severe">
                        <td>
                            <strong><?php echo htmlspecialchars($station['nom_demandeur']); ?></strong><br>
                            <small><?php echo $station['numero']; ?></small>
                        </td>
                        <td><?php echo $station['coordonnees_gps']; ?></td>
                        <td><?php echo htmlspecialchars(($station['ville'] ?? '') . ' / ' . ($station['region'] ?? '')); ?></td>
                        <td>
                            <button class="btn btn-warning" onclick="window.open('https://nominatim.openstreetmap.org/search?q=<?php echo urlencode($station['nom_demandeur'] . ', ' . ($station['ville'] ?? '') . ', Cameroun'); ?>&format=json', '_blank')">
                                🌍 Géocoder
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Recommandations -->
    <h2>💡 Recommandations et Solutions</h2>

    <div class="info-box">
        <strong>Actions à entreprendre :</strong><br><br>

        <strong>1. Pour les collisions GPS exactes :</strong>
        <ul>
            <li>✅ Garder la première station avec ce GPS (la plus ancienne)</li>
            <li>🔄 Géocoder à nouveau les autres stations individuellement</li>
            <li>📍 Utiliser l'adresse précise de chaque station</li>
        </ul>

        <strong>2. Pour les GPS très proches (&lt;10m) :</strong>
        <ul>
            <li>🔍 Vérifier sur OpenStreetMap si ce sont vraiment des stations différentes</li>
            <li>✅ Si même bâtiment → Peut-être un doublon (fusionner)</li>
            <li>❌ Si bâtiments différents → GPS OK, garder tel quel</li>
        </ul>

        <strong>3. Pour les GPS suspects (trop ronds) :</strong>
        <ul>
            <li>🌍 Géocoder depuis l'adresse précise</li>
            <li>📝 Demander confirmation GPS à l'opérateur</li>
            <li>🗺️ Utiliser Google Maps / OpenStreetMap pour localiser précisément</li>
        </ul>

        <strong>4. Prévention future :</strong>
        <ul>
            <li>✅ Validation GPS en temps réel lors de création de dossiers</li>
            <li>❌ Bloquer les GPS déjà utilisés par une autre station</li>
            <li>🎯 Imposer une précision minimale (6 décimales minimum)</li>
            <li>🔍 Alerter si GPS trop proche d'une station existante</li>
        </ul>
    </div>

    <div class="warning-box">
        <strong>⚠️ IMPORTANT :</strong><br>
        Ne PAS fusionner ces stations ! Ce ne sont pas des doublons, mais des stations différentes avec des GPS incorrects.<br>
        La solution est de <strong>corriger les GPS</strong>, pas de supprimer les stations.
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="geocode_collisions.php" class="btn btn-success" style="font-size: 1.2em;">
            🚀 Lancer le Géocodage Automatique
        </a>
        <a href="diagnostic_data_quality.php" class="btn btn-primary">
            📊 Retour au Diagnostic
        </a>
    </div>
</div>

<script>
    function promptNewGPS(stationId, stationName) {
        const newGPS = prompt(`Entrez les nouvelles coordonnées GPS pour:\n${stationName}\n\nFormat: latitude, longitude\nExemple: 4.0511, 9.7679`);

        if (newGPS && newGPS.includes(',')) {
            if (confirm(`Confirmer le nouveau GPS pour la station #${stationId}:\n${newGPS}`)) {
                window.location.href = `update_gps.php?id=${stationId}&gps=${encodeURIComponent(newGPS)}`;
            }
        } else if (newGPS) {
            alert('Format invalide. Utilisez: latitude, longitude');
        }
    }
</script>

</body>
</html>

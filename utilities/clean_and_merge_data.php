<?php
/**
 * Script de nettoyage et fusion intelligente des données GPS
 *
 * Stratégie :
 * 1. Partir des données MINEE comme source de vérité administrative
 * 2. Utiliser OSM uniquement pour enrichir/corriger les GPS
 * 3. Fusionner les doublons détectés
 * 4. Marquer les données nécessitant validation manuelle
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

// Similarité de texte améliorée
function similarityScore($str1, $str2) {
    $str1 = strtolower(trim($str1));
    $str2 = strtolower(trim($str2));

    // Nettoyage
    $str1 = preg_replace('/[^a-z0-9\s]/', '', $str1);
    $str2 = preg_replace('/[^a-z0-9\s]/', '', $str2);

    similar_text($str1, $str2, $percent);
    return $percent;
}

// Validation géographique
function validateGeographicCoherence($lat, $lon, $region) {
    $regions = [
        'Littoral' => ['lat' => 4.05, 'lon' => 9.70, 'radius' => 100000],
        'Centre' => ['lat' => 3.87, 'lon' => 11.52, 'radius' => 150000],
        'Ouest' => ['lat' => 5.47, 'lon' => 10.42, 'radius' => 100000],
        'Nord-Ouest' => ['lat' => 5.96, 'lon' => 10.15, 'radius' => 100000],
        'Sud' => ['lat' => 2.92, 'lon' => 11.52, 'radius' => 100000],
        'Est' => ['lat' => 4.37, 'lon' => 13.58, 'radius' => 150000],
        'Adamaoua' => ['lat' => 6.45, 'lon' => 13.35, 'radius' => 150000],
        'Nord' => ['lat' => 9.30, 'lon' => 13.40, 'radius' => 150000],
        'Extrême-Nord' => ['lat' => 10.60, 'lon' => 14.27, 'radius' => 150000],
        'Sud-Ouest' => ['lat' => 4.15, 'lon' => 9.23, 'radius' => 100000]
    ];

    if (!$region || !isset($regions[$region])) return false;

    $center = $regions[$region];
    $distance = haversineDistance($lat, $lon, $center['lat'], $center['lon']);

    return $distance <= $center['radius'];
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nettoyage et Fusion des Données - DPPG</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #2c3e50; border-bottom: 3px solid #27ae60; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; border-left: 4px solid #27ae60; padding-left: 10px; }
        .step { background: #f8f9fa; padding: 20px; margin: 20px 0; border-left: 4px solid #3498db; border-radius: 4px; }
        .step-title { font-size: 1.2em; font-weight: bold; margin-bottom: 10px; color: #2c3e50; }
        .step-status { display: inline-block; padding: 5px 10px; border-radius: 3px; font-size: 0.9em; margin-left: 10px; }
        .status-pending { background: #f39c12; color: white; }
        .status-running { background: #3498db; color: white; }
        .status-success { background: #27ae60; color: white; }
        .status-error { background: #e74c3c; color: white; }
        .progress-bar { width: 100%; height: 30px; background: #ecf0f1; border-radius: 15px; overflow: hidden; margin: 10px 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #27ae60, #2ecc71); transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.85em; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #27ae60; color: white; }
        tr:nth-child(even) { background: #f8f9fa; }
        .action-btn { padding: 8px 15px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .info-box { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0; }
        .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
        .success-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
        .error-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0; }
        .merge-preview { display: grid; grid-template-columns: 1fr auto 1fr; gap: 20px; align-items: center; margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; }
        .station-card { padding: 15px; background: white; border: 2px solid #ddd; border-radius: 4px; }
        .station-card.old { border-color: #e74c3c; }
        .station-card.new { border-color: #27ae60; }
        .merge-arrow { font-size: 2em; color: #3498db; text-align: center; }
    </style>
</head>
<body>

<div class="container">
    <h1>🧹 Nettoyage et Fusion Intelligente des Données GPS</h1>

    <div class="info-box">
        <strong>Objectif :</strong> Nettoyer les données GPS, fusionner les doublons OSM/MINEE, et corriger les incohérences.<br>
        <strong>Stratégie :</strong> Données MINEE = source de vérité administrative | OSM = enrichissement GPS uniquement<br>
        <strong>Mode :</strong> Ce script analyse et propose des actions - <strong>aucune modification automatique sans validation</strong>
    </div>

    <?php
    // ÉTAPE 1 : Analyse des doublons
    echo "<div class='step' id='step1'>\n";
    echo "<div class='step-title'>📊 Étape 1 : Détection des Doublons <span class='step-status status-running'>EN COURS</span></div>\n";

    $stmt = $pdo->query("
        SELECT
            d.id,
            d.numero,
            d.nom_demandeur,
            d.coordonnees_gps,
            d.statut,
            d.region,
            d.ville,
            d.type_infrastructure
        FROM dossiers d
        WHERE d.statut IN ('autorise', 'historique_autorise')
        ORDER BY d.nom_demandeur
    ");

    $stations = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $dossier) {
        $coords = parseGPSCoordinates($dossier['coordonnees_gps']);
        if ($coords) {
            $stations[] = array_merge($dossier, $coords);
        }
    }

    // Recherche de doublons
    $doublons = [];
    for ($i = 0; $i < count($stations); $i++) {
        for ($j = $i + 1; $j < count($stations); $j++) {
            $s1 = $stations[$i];
            $s2 = $stations[$j];

            $distance = haversineDistance(
                $s1['latitude'], $s1['longitude'],
                $s2['latitude'], $s2['longitude']
            );

            $similarity = similarityScore($s1['nom_demandeur'], $s2['nom_demandeur']);

            // Critères de doublon :
            // 1. GPS très proches (<100m) OU
            // 2. Nom très similaire (>80%) ET même région
            if ($distance < 100 ||
                ($similarity > 80 && $s1['region'] === $s2['region'])) {

                $doublons[] = [
                    'station1' => $s1,
                    'station2' => $s2,
                    'distance' => $distance,
                    'similarity' => $similarity,
                    'confidence' => ($distance < 50 && $similarity > 70) ? 'high' :
                                   (($distance < 100 && $similarity > 60) ? 'medium' : 'low')
                ];
            }
        }
    }

    echo "<p>✅ Analyse terminée : <strong>" . count($doublons) . " doublons potentiels</strong> détectés sur " . count($stations) . " stations.</p>\n";
    echo "</div>\n";

    // ÉTAPE 2 : Classification des doublons
    echo "<div class='step' id='step2'>\n";
    echo "<div class='step-title'>🔍 Étape 2 : Classification et Priorisation <span class='step-status status-success'>TERMINÉ</span></div>\n";

    $high_confidence = array_filter($doublons, fn($d) => $d['confidence'] === 'high');
    $medium_confidence = array_filter($doublons, fn($d) => $d['confidence'] === 'medium');
    $low_confidence = array_filter($doublons, fn($d) => $d['confidence'] === 'low');

    echo "<ul>\n";
    echo "<li><strong style='color: #e74c3c;'>Haute confiance :</strong> " . count($high_confidence) . " doublons (fusion recommandée)</li>\n";
    echo "<li><strong style='color: #f39c12;'>Confiance moyenne :</strong> " . count($medium_confidence) . " doublons (vérification suggérée)</li>\n";
    echo "<li><strong style='color: #3498db;'>Faible confiance :</strong> " . count($low_confidence) . " doublons (à investiguer)</li>\n";
    echo "</ul>\n";
    echo "</div>\n";

    // ÉTAPE 3 : Propositions de fusion
    if (count($high_confidence) > 0) {
        echo "<div class='step' id='step3'>\n";
        echo "<div class='step-title'>🔄 Étape 3 : Propositions de Fusion (Haute Confiance)</div>\n";

        echo "<div class='warning-box'>\n";
        echo "<strong>⚠️ ATTENTION :</strong> Les fusions ci-dessous nécessitent une validation manuelle.<br>\n";
        echo "Vérifiez chaque proposition avant d'approuver.\n";
        echo "</div>\n";

        foreach (array_slice($high_confidence, 0, 20) as $idx => $doublon) {
            $s1 = $doublon['station1'];
            $s2 = $doublon['station2'];

            // Déterminer quelle station garder (priorité à historique_autorise + GPS le plus précis)
            $keep = $s1;
            $merge = $s2;

            if ($s2['statut'] === 'historique_autorise' && $s1['statut'] !== 'historique_autorise') {
                $keep = $s2;
                $merge = $s1;
            }

            echo "<div class='merge-preview'>\n";

            // Station à supprimer
            echo "<div class='station-card old'>\n";
            echo "<strong style='color: #e74c3c;'>❌ À SUPPRIMER</strong><br>\n";
            echo "<strong>" . htmlspecialchars($merge['nom_demandeur']) . "</strong><br>\n";
            echo "<small>N° " . $merge['numero'] . "</small><br>\n";
            echo "<small>GPS: " . round($merge['latitude'], 6) . ", " . round($merge['longitude'], 6) . "</small><br>\n";
            echo "<small>Statut: " . $merge['statut'] . "</small>\n";
            echo "</div>\n";

            // Flèche
            echo "<div class='merge-arrow'>→</div>\n";

            // Station à garder
            echo "<div class='station-card new'>\n";
            echo "<strong style='color: #27ae60;'>✅ À CONSERVER</strong><br>\n";
            echo "<strong>" . htmlspecialchars($keep['nom_demandeur']) . "</strong><br>\n";
            echo "<small>N° " . $keep['numero'] . "</small><br>\n";
            echo "<small>GPS: " . round($keep['latitude'], 6) . ", " . round($keep['longitude'], 6) . "</small><br>\n";
            echo "<small>Statut: " . $keep['statut'] . "</small>\n";
            echo "</div>\n";

            echo "</div>\n";

            echo "<p style='margin-left: 20px;'>\n";
            echo "<strong>Analyse :</strong> Distance = " . round($doublon['distance'], 1) . "m | ";
            echo "Similarité nom = " . round($doublon['similarity'], 1) . "%<br>\n";
            echo "<button class='action-btn btn-success' onclick='approveMerge(" . $merge['id'] . ", " . $keep['id'] . ")'>✅ Approuver la fusion</button>\n";
            echo "<button class='action-btn btn-warning' onclick='skipMerge(" . $idx . ")'>⏭️ Ignorer</button>\n";
            echo "<button class='action-btn btn-danger' onclick='rejectMerge(" . $idx . ")'>❌ Rejeter</button>\n";
            echo "</p>\n";

            echo "<hr>\n";
        }

        if (count($high_confidence) > 20) {
            echo "<p><em>Affichage limité aux 20 premières propositions (total: " . count($high_confidence) . ")</em></p>\n";
        }

        echo "</div>\n";
    }

    // ÉTAPE 4 : Incohérences géographiques
    echo "<div class='step' id='step4'>\n";
    echo "<div class='step-title'>🗺️ Étape 4 : Correction des Incohérences Géographiques</div>\n";

    $incoherences = [];
    foreach ($stations as $station) {
        if ($station['region']) {
            $coherent = validateGeographicCoherence(
                $station['latitude'],
                $station['longitude'],
                $station['region']
            );

            if (!$coherent) {
                $incoherences[] = $station;
            }
        }
    }

    echo "<p>⚠️ <strong>" . count($incoherences) . " stations</strong> avec incohérence géographique détectée.</p>\n";

    if (count($incoherences) > 0) {
        echo "<table>\n";
        echo "<thead><tr>\n";
        echo "<th>Station</th><th>Région déclarée</th><th>GPS</th><th>Action recommandée</th><th>Action</th>\n";
        echo "</tr></thead>\n";
        echo "<tbody>\n";

        foreach (array_slice($incoherences, 0, 30) as $station) {
            echo "<tr>\n";
            echo "<td><strong>" . htmlspecialchars($station['nom_demandeur']) . "</strong><br><small>" . $station['numero'] . "</small></td>\n";
            echo "<td>" . htmlspecialchars($station['region']) . "</td>\n";
            echo "<td>" . round($station['latitude'], 6) . ", " . round($station['longitude'], 6) . "</td>\n";
            echo "<td><small>Vérifier manuellement sur carte</small></td>\n";
            echo "<td><button class='action-btn btn-primary' onclick='openMap(" . $station['latitude'] . ", " . $station['longitude'] . ")'>🗺️ Voir</button></td>\n";
            echo "</tr>\n";
        }

        if (count($incoherences) > 30) {
            echo "<tr><td colspan='5' style='text-align: center; font-style: italic;'>Affichage limité aux 30 premières (total: " . count($incoherences) . ")</td></tr>\n";
        }

        echo "</tbody>\n</table>\n";
    }

    echo "</div>\n";

    // ÉTAPE 5 : GPS manquants
    $stmt_missing = $pdo->query("
        SELECT COUNT(*) as count
        FROM dossiers
        WHERE (coordonnees_gps IS NULL OR coordonnees_gps = '')
        AND statut IN ('autorise', 'historique_autorise')
    ");
    $missing_count = $stmt_missing->fetch()['count'];

    echo "<div class='step' id='step5'>\n";
    echo "<div class='step-title'>📍 Étape 5 : GPS Manquants</div>\n";
    echo "<p><strong>$missing_count stations</strong> autorisées sans coordonnées GPS.</p>\n";

    if ($missing_count > 0) {
        echo "<div class='info-box'>\n";
        echo "<strong>Solutions proposées :</strong><br>\n";
        echo "1. <strong>Géocodage automatique</strong> depuis l'adresse (si disponible)<br>\n";
        echo "2. <strong>Enrichissement OSM</strong> par matching de nom + région<br>\n";
        echo "3. <strong>Saisie manuelle</strong> via interface carte interactive\n";
        echo "</div>\n";

        echo "<button class='action-btn btn-primary' onclick='location.href=\"geocode_missing.php\"'>🌍 Lancer le géocodage</button>\n";
    }

    echo "</div>\n";

    // Résumé et actions
    echo "<h2>📋 Résumé et Actions Recommandées</h2>\n";

    $total_issues = count($doublons) + count($incoherences) + $missing_count;

    if ($total_issues === 0) {
        echo "<div class='success-box'>\n";
        echo "<strong>✅ EXCELLENT !</strong> Aucun problème détecté. Les données sont propres et cohérentes.\n";
        echo "</div>\n";
    } else {
        echo "<div class='warning-box'>\n";
        echo "<strong>Actions prioritaires :</strong><br><br>\n";

        if (count($high_confidence) > 0) {
            echo "1. <strong>PRIORITÉ HAUTE :</strong> Fusionner les " . count($high_confidence) . " doublons haute confiance<br>\n";
        }

        if (count($incoherences) > 20) {
            echo "2. <strong>PRIORITÉ HAUTE :</strong> Corriger les " . count($incoherences) . " incohérences géographiques<br>\n";
        }

        if ($missing_count > 0) {
            echo "3. <strong>PRIORITÉ MOYENNE :</strong> Compléter les $missing_count GPS manquants<br>\n";
        }

        if (count($medium_confidence) > 0) {
            echo "4. <strong>PRIORITÉ BASSE :</strong> Vérifier les " . count($medium_confidence) . " doublons confiance moyenne<br>\n";
        }

        echo "</div>\n";

        echo "<div class='info-box'>\n";
        echo "<strong>💡 Recommandation stratégique :</strong><br>\n";
        echo "Pour éviter ces problèmes à l'avenir :<br>\n";
        echo "• Partir <strong>uniquement des données MINEE</strong> comme source de vérité<br>\n";
        echo "• Utiliser OSM <strong>uniquement pour enrichir</strong> les GPS manquants (pas d'import brut)<br>\n";
        echo "• Implémenter une <strong>validation GPS</strong> lors de la création de dossiers<br>\n";
        echo "• Ajouter une <strong>détection de doublons</strong> en temps réel\n";
        echo "</div>\n";
    }
    ?>

    <script>
        function approveMerge(mergeId, keepId) {
            if (confirm('Confirmer la fusion de la station #' + mergeId + ' vers #' + keepId + ' ?')) {
                window.location.href = 'execute_merge.php?merge=' + mergeId + '&keep=' + keepId;
            }
        }

        function skipMerge(idx) {
            alert('Fusion ignorée pour cette session.');
        }

        function rejectMerge(idx) {
            if (confirm('Marquer cette paire comme NON-doublon ?')) {
                alert('Marquée comme non-doublon (fonctionnalité à implémenter).');
            }
        }

        function openMap(lat, lon) {
            window.open('https://www.openstreetmap.org/#map=15/' + lat + '/' + lon, '_blank');
        }
    </script>

</div>
</body>
</html>

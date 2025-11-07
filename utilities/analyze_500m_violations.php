<?php
/**
 * Analyse détaillée des violations de la distance minimale de 500m
 * Identifie les stations qui ne respectent pas la règle et leur contexte
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// Fonction de calcul de distance Haversine
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

// Fonction pour parser les coordonnées GPS
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

echo "<!DOCTYPE html>\n";
echo "<html>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<title>Analyse des Violations 500m - DPPG</title>\n";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
h1 { color: #2c3e50; border-bottom: 3px solid #e74c3c; padding-bottom: 10px; }
.warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
.info-box { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 20px 0; }
.error-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 0.9em; }
th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
th { background: #dc3545; color: white; font-weight: bold; position: sticky; top: 0; }
tr:nth-child(even) { background: #f8f9fa; }
tr.severe { background: #f8d7da; }
tr.moderate { background: #fff3cd; }
.metric { display: inline-block; margin: 10px 20px 10px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; }
.metric-label { font-weight: bold; color: #555; display: block; }
.metric-value { font-size: 1.5em; color: #dc3545; margin-top: 5px; }
.tabs { display: flex; border-bottom: 2px solid #ddd; margin: 20px 0; }
.tab { padding: 10px 20px; cursor: pointer; background: #f8f9fa; margin-right: 5px; border: 1px solid #ddd; border-bottom: none; }
.tab.active { background: white; border-bottom: 2px solid white; margin-bottom: -2px; font-weight: bold; }
.tab-content { display: none; }
.tab-content.active { display: block; }
</style>
</head>\n<body>\n";

echo "<div class='container'>\n";
echo "<h1>🚨 Analyse des Violations de Distance Minimale (500m)</h1>\n";

// Récupérer toutes les infrastructures
$stmt = $pdo->query("
    SELECT
        d.id,
        d.numero,
        d.nom_demandeur,
        d.type_infrastructure,
        d.coordonnees_gps,
        d.statut,
        d.region,
        d.date_creation
    FROM dossiers d
    WHERE d.coordonnees_gps IS NOT NULL
    AND d.coordonnees_gps != ''
    AND d.statut IN ('autorise', 'historique_autorise')
    ORDER BY d.id
");

$dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Parser les coordonnées
$stations = [];
foreach ($dossiers as $dossier) {
    $coords = parseGPSCoordinates($dossier['coordonnees_gps']);
    if ($coords) {
        $stations[] = array_merge($dossier, $coords);
    }
}

echo "<div class='info-box'>\n";
echo "<strong>Données analysées :</strong> " . count($stations) . " infrastructures autorisées avec coordonnées GPS valides.\n";
echo "</div>\n";

// Analyser toutes les paires
$violations = [];
$violationsByStation = [];

for ($i = 0; $i < count($stations); $i++) {
    for ($j = $i + 1; $j < count($stations); $j++) {
        $station1 = $stations[$i];
        $station2 = $stations[$j];

        $distance = haversineDistance(
            $station1['latitude'], $station1['longitude'],
            $station2['latitude'], $station2['longitude']
        );

        if ($distance < 500) {
            $violations[] = [
                'station1' => $station1,
                'station2' => $station2,
                'distance' => $distance,
                'severity' => $distance < 250 ? 'severe' : 'moderate'
            ];

            // Compter par station
            if (!isset($violationsByStation[$station1['id']])) {
                $violationsByStation[$station1['id']] = [
                    'station' => $station1,
                    'count' => 0,
                    'min_distance' => 999999
                ];
            }
            if (!isset($violationsByStation[$station2['id']])) {
                $violationsByStation[$station2['id']] = [
                    'station' => $station2,
                    'count' => 0,
                    'min_distance' => 999999
                ];
            }

            $violationsByStation[$station1['id']]['count']++;
            $violationsByStation[$station2['id']]['count']++;
            $violationsByStation[$station1['id']]['min_distance'] = min($violationsByStation[$station1['id']]['min_distance'], $distance);
            $violationsByStation[$station2['id']]['min_distance'] = min($violationsByStation[$station2['id']]['min_distance'], $distance);
        }
    }
}

// Statistiques globales
$severeViolations = array_filter($violations, fn($v) => $v['severity'] === 'severe');
$historicalCount = count(array_filter($stations, fn($s) => $s['statut'] === 'historique_autorise'));
$newCount = count(array_filter($stations, fn($s) => $s['statut'] === 'autorise'));

echo "<h2>📊 Statistiques Globales</h2>\n";
echo "<div class='metric'>\n";
echo "<span class='metric-label'>Total violations (&lt;500m)</span>\n";
echo "<span class='metric-value'>" . count($violations) . "</span>\n";
echo "</div>\n";

echo "<div class='metric'>\n";
echo "<span class='metric-label'>Violations graves (&lt;250m)</span>\n";
echo "<span class='metric-value' style='color: #dc3545;'>" . count($severeViolations) . "</span>\n";
echo "</div>\n";

echo "<div class='metric'>\n";
echo "<span class='metric-label'>Stations concernées</span>\n";
echo "<span class='metric-value'>" . count($violationsByStation) . "</span>\n";
echo "</div>\n";

echo "<div class='metric'>\n";
echo "<span class='metric-label'>Stations historiques</span>\n";
echo "<span class='metric-value' style='color: #f39c12;'>$historicalCount</span>\n";
echo "</div>\n";

echo "<div class='metric'>\n";
echo "<span class='metric-label'>Stations nouvelles</span>\n";
echo "<span class='metric-value' style='color: #27ae60;'>$newCount</span>\n";
echo "</div>\n";

// Analyse par type de conflit
$historicalVsHistorical = 0;
$historicalVsNew = 0;
$newVsNew = 0;

foreach ($violations as $v) {
    $s1_historical = $v['station1']['statut'] === 'historique_autorise';
    $s2_historical = $v['station2']['statut'] === 'historique_autorise';

    if ($s1_historical && $s2_historical) {
        $historicalVsHistorical++;
    } elseif (!$s1_historical && !$s2_historical) {
        $newVsNew++;
    } else {
        $historicalVsNew++;
    }
}

echo "<h2>🔍 Répartition des Violations</h2>\n";
echo "<div class='info-box'>\n";
echo "<strong>Historique vs Historique :</strong> $historicalVsHistorical violations<br>\n";
echo "<strong>Historique vs Nouvelle :</strong> $historicalVsNew violations<br>\n";
echo "<strong>Nouvelle vs Nouvelle :</strong> $newVsNew violations\n";
echo "</div>\n";

if ($newVsNew > 0) {
    echo "<div class='error-box'>\n";
    echo "<strong>⚠️ ATTENTION CRITIQUE :</strong> $newVsNew paires de stations nouvellement autorisées ne respectent pas la distance minimale !<br>\n";
    echo "Cela indique un problème dans le processus de validation actuel.\n";
    echo "</div>\n";
}

// Onglets
echo "<div class='tabs'>\n";
echo "<div class='tab active' onclick='showTab(\"violations\")'>Toutes les Violations</div>\n";
echo "<div class='tab' onclick='showTab(\"stations\")'>Stations Problématiques</div>\n";
echo "<div class='tab' onclick='showTab(\"severe\")'>Violations Graves (&lt;250m)</div>\n";
echo "</div>\n";

// Onglet 1: Toutes les violations
echo "<div id='violations' class='tab-content active'>\n";
echo "<h2>📋 Liste Complète des Violations</h2>\n";
echo "<table>\n";
echo "<thead><tr>\n";
echo "<th>Station 1</th><th>Statut 1</th><th>Station 2</th><th>Statut 2</th><th>Distance</th><th>Gravité</th>\n";
echo "</tr></thead>\n";
echo "<tbody>\n";

usort($violations, fn($a, $b) => $a['distance'] <=> $b['distance']);

foreach (array_slice($violations, 0, 100) as $v) {
    $rowClass = $v['severity'];
    $gravite = $v['severity'] === 'severe' ? '🔴 Grave' : '🟡 Modérée';

    echo "<tr class='$rowClass'>\n";
    echo "<td>" . htmlspecialchars($v['station1']['nom_demandeur']) . "<br><small>" . $v['station1']['numero'] . "</small></td>\n";
    echo "<td>" . ($v['station1']['statut'] === 'historique_autorise' ? '📜 Historique' : '🆕 Nouvelle') . "</td>\n";
    echo "<td>" . htmlspecialchars($v['station2']['nom_demandeur']) . "<br><small>" . $v['station2']['numero'] . "</small></td>\n";
    echo "<td>" . ($v['station2']['statut'] === 'historique_autorise' ? '📜 Historique' : '🆕 Nouvelle') . "</td>\n";
    echo "<td><strong>" . number_format($v['distance'], 1) . " m</strong></td>\n";
    echo "<td>$gravite</td>\n";
    echo "</tr>\n";
}

if (count($violations) > 100) {
    echo "<tr><td colspan='6' style='text-align: center; background: #f8f9fa; font-style: italic;'>\n";
    echo "Affichage limité aux 100 premières violations (total: " . count($violations) . ")\n";
    echo "</td></tr>\n";
}

echo "</tbody>\n</table>\n";
echo "</div>\n";

// Onglet 2: Stations problématiques
echo "<div id='stations' class='tab-content'>\n";
echo "<h2>🏪 Stations avec le Plus de Violations</h2>\n";
usort($violationsByStation, fn($a, $b) => $b['count'] <=> $a['count']);

echo "<table>\n";
echo "<thead><tr>\n";
echo "<th>Station</th><th>Statut</th><th>Nombre de violations</th><th>Distance minimale</th>\n";
echo "</tr></thead>\n";
echo "<tbody>\n";

foreach (array_slice($violationsByStation, 0, 50) as $data) {
    $station = $data['station'];
    echo "<tr>\n";
    echo "<td><strong>" . htmlspecialchars($station['nom_demandeur']) . "</strong><br><small>" . $station['numero'] . "</small></td>\n";
    echo "<td>" . ($station['statut'] === 'historique_autorise' ? '📜 Historique' : '🆕 Nouvelle') . "</td>\n";
    echo "<td><strong>" . $data['count'] . "</strong></td>\n";
    echo "<td>" . number_format($data['min_distance'], 1) . " m</td>\n";
    echo "</tr>\n";
}

echo "</tbody>\n</table>\n";
echo "</div>\n";

// Onglet 3: Violations graves
echo "<div id='severe' class='tab-content'>\n";
echo "<h2>🔴 Violations Graves (&lt;250m)</h2>\n";
echo "<table>\n";
echo "<thead><tr>\n";
echo "<th>Station 1</th><th>Station 2</th><th>Distance</th><th>Contexte</th>\n";
echo "</tr></thead>\n";
echo "<tbody>\n";

foreach ($severeViolations as $v) {
    echo "<tr class='severe'>\n";
    echo "<td>" . htmlspecialchars($v['station1']['nom_demandeur']) . "</td>\n";
    echo "<td>" . htmlspecialchars($v['station2']['nom_demandeur']) . "</td>\n";
    echo "<td><strong style='color: #dc3545;'>" . number_format($v['distance'], 1) . " m</strong></td>\n";
    $context = ($v['station1']['statut'] === 'historique_autorise' && $v['station2']['statut'] === 'historique_autorise')
        ? 'Deux stations historiques'
        : 'Au moins une nouvelle station';
    echo "<td>$context</td>\n";
    echo "</tr>\n";
}

echo "</tbody>\n</table>\n";
echo "</div>\n";

// Recommandations
echo "<h2>💡 Recommandations</h2>\n";

echo "<div class='warning-box'>\n";
echo "<strong>Interprétation des résultats :</strong><br><br>\n";
echo "<strong>1. Stations historiques proches :</strong> Ces stations existaient avant l'application stricte de la règle des 500m. ";
echo "Elles bénéficient probablement de droits acquis.<br><br>\n";
echo "<strong>2. Nouvelles stations en violation :</strong> ";
if ($newVsNew > 0) {
    echo "<span style='color: #dc3545;'>⚠️ PROBLÈME DÉTECTÉ</span> - Le système de validation doit être renforcé !<br><br>\n";
} else {
    echo "✅ Aucune nouvelle station ne viole la règle - le système fonctionne correctement.<br><br>\n";
}
echo "<strong>3. Précision GPS :</strong> Une marge d'erreur de ±10-50m est normale selon la source des données.\n";
echo "</div>\n";

echo "<div class='info-box'>\n";
echo "<strong>Actions recommandées :</strong><br>\n";
echo "<ol>\n";
echo "<li>Marquer clairement les stations historiques avec droits acquis dans l'interface</li>\n";
echo "<li>Renforcer la validation GPS lors de la création de nouveaux dossiers</li>\n";
echo "<li>Ajouter une alerte visuelle sur la carte pour les zones de contrainte</li>\n";
echo "<li>Permettre aux inspecteurs de signaler les imprécisions GPS</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "<script>\n";
echo "function showTab(tabName) {\n";
echo "    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));\n";
echo "    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));\n";
echo "    event.target.classList.add('active');\n";
echo "    document.getElementById(tabName).classList.add('active');\n";
echo "}\n";
echo "</script>\n";

echo "</div>\n";
echo "</body>\n</html>\n";
?>

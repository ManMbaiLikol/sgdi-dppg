<?php
/**
 * Diagnostic complet de la qualité des données
 * Identifie les problèmes GPS, les doublons potentiels, et la cohérence géographique
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

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

// Régions du Cameroun avec leurs coordonnées approximatives
$regions_cameroun = [
    'Littoral' => ['lat' => 4.05, 'lon' => 9.70, 'radius' => 100000], // Douala
    'Centre' => ['lat' => 3.87, 'lon' => 11.52, 'radius' => 150000], // Yaoundé
    'Ouest' => ['lat' => 5.47, 'lon' => 10.42, 'radius' => 100000], // Bafoussam
    'Nord-Ouest' => ['lat' => 5.96, 'lon' => 10.15, 'radius' => 100000], // Bamenda
    'Sud' => ['lat' => 2.92, 'lon' => 11.52, 'radius' => 100000], // Ebolowa
    'Est' => ['lat' => 4.37, 'lon' => 13.58, 'radius' => 150000], // Bertoua
    'Adamaoua' => ['lat' => 6.45, 'lon' => 13.35, 'radius' => 150000], // Ngaoundéré
    'Nord' => ['lat' => 9.30, 'lon' => 13.40, 'radius' => 150000], // Garoua
    'Extrême-Nord' => ['lat' => 10.60, 'lon' => 14.27, 'radius' => 150000], // Maroua
    'Sud-Ouest' => ['lat' => 4.15, 'lon' => 9.23, 'radius' => 100000] // Buea
];

echo "<!DOCTYPE html>\n<html>\n<head>\n<meta charset='UTF-8'>\n";
echo "<title>Diagnostic Qualité des Données - DPPG</title>\n";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
h2 { color: #34495e; margin-top: 30px; border-left: 4px solid #3498db; padding-left: 10px; }
.critical { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
.success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
.info { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.85em; }
th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
th { background: #3498db; color: white; position: sticky; top: 0; }
tr:nth-child(even) { background: #f8f9fa; }
tr.error { background: #f8d7da; }
tr.warning { background: #fff3cd; }
.metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
.metric-card { background: #f8f9fa; padding: 15px; border-radius: 4px; border-left: 4px solid #3498db; }
.metric-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
.metric-label { color: #7f8c8d; font-size: 0.9em; }
.tabs { display: flex; border-bottom: 2px solid #ddd; margin: 20px 0; }
.tab { padding: 10px 20px; cursor: pointer; background: #f8f9fa; margin-right: 5px; }
.tab.active { background: white; border-bottom: 2px solid white; margin-bottom: -2px; font-weight: bold; }
.tab-content { display: none; }
.tab-content.active { display: block; }
</style>
</head>\n<body>\n";

echo "<div class='container'>\n";
echo "<h1>🔬 Diagnostic Complet de la Qualité des Données GPS</h1>\n";

// Récupérer toutes les stations
$stmt = $pdo->query("
    SELECT
        d.id,
        d.numero,
        d.nom_demandeur,
        d.type_infrastructure,
        d.coordonnees_gps,
        d.statut,
        d.region,
        d.ville,
        d.date_creation
    FROM dossiers d
    WHERE d.statut IN ('autorise', 'historique_autorise')
    ORDER BY d.region, d.nom_demandeur
");

$dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compteurs
$total_dossiers = count($dossiers);
$avec_gps = 0;
$sans_gps = 0;
$gps_invalide = 0;
$region_incoherente = 0;
$doublons_potentiels = [];
$stations = [];

// Analyser chaque dossier
foreach ($dossiers as $dossier) {
    if (empty($dossier['coordonnees_gps'])) {
        $sans_gps++;
    } else {
        $coords = parseGPSCoordinates($dossier['coordonnees_gps']);
        if ($coords) {
            $avec_gps++;

            // Vérifier cohérence géographique
            $region_declared = $dossier['region'];
            $region_coherente = false;

            if ($region_declared && isset($regions_cameroun[$region_declared])) {
                $region_center = $regions_cameroun[$region_declared];
                $distance_to_center = haversineDistance(
                    $coords['latitude'], $coords['longitude'],
                    $region_center['lat'], $region_center['lon']
                );

                if ($distance_to_center <= $region_center['radius']) {
                    $region_coherente = true;
                }
            }

            $stations[] = array_merge($dossier, $coords, [
                'region_coherente' => $region_coherente
            ]);

            if (!$region_coherente && $region_declared) {
                $region_incoherente++;
            }
        } else {
            $gps_invalide++;
        }
    }
}

// Recherche de doublons potentiels (même nom similaire ou GPS très proches)
$doublons_nom = [];
$doublons_gps = [];

for ($i = 0; $i < count($stations); $i++) {
    for ($j = $i + 1; $j < count($stations); $j++) {
        $s1 = $stations[$i];
        $s2 = $stations[$j];

        // Similarité de nom (simple)
        $nom1 = strtolower(trim($s1['nom_demandeur']));
        $nom2 = strtolower(trim($s2['nom_demandeur']));

        similar_text($nom1, $nom2, $percent);

        if ($percent > 80) {
            $doublons_nom[] = [
                'station1' => $s1,
                'station2' => $s2,
                'similarity' => $percent
            ];
        }

        // GPS très proches (<50m)
        $distance = haversineDistance(
            $s1['latitude'], $s1['longitude'],
            $s2['latitude'], $s2['longitude']
        );

        if ($distance < 50) {
            $doublons_gps[] = [
                'station1' => $s1,
                'station2' => $s2,
                'distance' => $distance
            ];
        }
    }
}

// Affichage des métriques
echo "<div class='metric-grid'>\n";
echo "<div class='metric-card'>\n";
echo "<div class='metric-value'>$total_dossiers</div>\n";
echo "<div class='metric-label'>Total dossiers autorisés</div>\n";
echo "</div>\n";

echo "<div class='metric-card'>\n";
echo "<div class='metric-value' style='color: #27ae60;'>$avec_gps</div>\n";
echo "<div class='metric-label'>Avec GPS valide</div>\n";
echo "</div>\n";

echo "<div class='metric-card'>\n";
echo "<div class='metric-value' style='color: #e74c3c;'>$sans_gps</div>\n";
echo "<div class='metric-label'>Sans GPS</div>\n";
echo "</div>\n";

echo "<div class='metric-card'>\n";
echo "<div class='metric-value' style='color: #f39c12;'>$gps_invalide</div>\n";
echo "<div class='metric-label'>GPS invalide</div>\n";
echo "</div>\n";

echo "<div class='metric-card'>\n";
echo "<div class='metric-value' style='color: #dc3545;'>$region_incoherente</div>\n";
echo "<div class='metric-label'>Région incohérente</div>\n";
echo "</div>\n";

echo "<div class='metric-card'>\n";
echo "<div class='metric-value' style='color: #e67e22;'>" . count($doublons_gps) . "</div>\n";
echo "<div class='metric-label'>Doublons GPS (&lt;50m)</div>\n";
echo "</div>\n";
echo "</div>\n";

// Analyse de la qualité
$taux_gps = ($total_dossiers > 0) ? round(($avec_gps / $total_dossiers) * 100, 1) : 0;
$taux_coherence = ($avec_gps > 0) ? round((($avec_gps - $region_incoherente) / $avec_gps) * 100, 1) : 0;
$taux_doublon = ($avec_gps > 0) ? round((count($doublons_gps) / $avec_gps) * 100, 1) : 0;

echo "<h2>📊 Évaluation Globale de la Qualité</h2>\n";

if ($taux_gps >= 90 && $taux_coherence >= 90 && $taux_doublon < 5) {
    echo "<div class='success'>\n";
    echo "<strong>✅ Qualité EXCELLENTE</strong><br>\n";
    echo "Les données GPS sont fiables et peuvent être utilisées pour l'analyse des distances.\n";
    echo "</div>\n";
} elseif ($taux_gps >= 70 && $taux_coherence >= 70 && $taux_doublon < 15) {
    echo "<div class='warning'>\n";
    echo "<strong>⚠️ Qualité MOYENNE</strong><br>\n";
    echo "Les données nécessitent un nettoyage mais sont utilisables avec précautions.\n";
    echo "</div>\n";
} else {
    echo "<div class='critical'>\n";
    echo "<strong>🚨 Qualité CRITIQUE</strong><br>\n";
    echo "Les données GPS sont trop imprécises pour être fiables. Un nettoyage majeur est nécessaire.\n";
    echo "</div>\n";
}

echo "<ul>\n";
echo "<li><strong>Taux de couverture GPS :</strong> $taux_gps% ($avec_gps/$total_dossiers)</li>\n";
echo "<li><strong>Taux de cohérence géographique :</strong> $taux_coherence%</li>\n";
echo "<li><strong>Taux de doublons potentiels :</strong> $taux_doublon%</li>\n";
echo "</ul>\n";

// Onglets
echo "<div class='tabs'>\n";
echo "<div class='tab active' onclick='showTab(\"incoherences\")'>Incohérences Géographiques</div>\n";
echo "<div class='tab' onclick='showTab(\"doublons\")'>Doublons Potentiels</div>\n";
echo "<div class='tab' onclick='showTab(\"manquants\")'>GPS Manquants</div>\n";
echo "</div>\n";

// Onglet 1 : Incohérences
echo "<div id='incoherences' class='tab-content active'>\n";
echo "<h2>🗺️ Stations avec Incohérence Géographique</h2>\n";
echo "<p>Stations dont les coordonnées GPS ne correspondent pas à leur région déclarée.</p>\n";

if ($region_incoherente > 0) {
    echo "<table>\n<thead><tr>\n";
    echo "<th>Station</th><th>Région déclarée</th><th>GPS</th><th>Statut</th>\n";
    echo "</tr></thead>\n<tbody>\n";

    foreach ($stations as $station) {
        if (!$station['region_coherente']) {
            echo "<tr class='error'>\n";
            echo "<td><strong>" . htmlspecialchars($station['nom_demandeur']) . "</strong><br><small>" . $station['numero'] . "</small></td>\n";
            echo "<td>" . htmlspecialchars($station['region'] ?? 'Non renseignée') . "</td>\n";
            echo "<td>" . $station['latitude'] . ", " . $station['longitude'] . "</td>\n";
            echo "<td>" . ($station['statut'] === 'historique_autorise' ? '📜 Historique' : '🆕 Nouvelle') . "</td>\n";
            echo "</tr>\n";
        }
    }

    echo "</tbody>\n</table>\n";
} else {
    echo "<div class='success'>✅ Aucune incohérence géographique détectée !</div>\n";
}
echo "</div>\n";

// Onglet 2 : Doublons
echo "<div id='doublons' class='tab-content'>\n";
echo "<h2>🔄 Doublons Potentiels (GPS &lt;50m)</h2>\n";
echo "<p>Paires de stations avec des coordonnées GPS très proches - possibles doublons OSM/MINEE.</p>\n";

if (count($doublons_gps) > 0) {
    echo "<table>\n<thead><tr>\n";
    echo "<th>Station 1</th><th>Station 2</th><th>Distance</th><th>Même nom?</th><th>Action suggérée</th>\n";
    echo "</tr></thead>\n<tbody>\n";

    foreach ($doublons_gps as $doublon) {
        $s1 = $doublon['station1'];
        $s2 = $doublon['station2'];

        $nom1 = strtolower(trim($s1['nom_demandeur']));
        $nom2 = strtolower(trim($s2['nom_demandeur']));
        similar_text($nom1, $nom2, $percent);

        $meme_nom = $percent > 70 ? "✅ Oui ($percent%)" : "❌ Non ($percent%)";
        $action = $percent > 70 ? "🔄 Fusionner" : "🔍 Vérifier sur terrain";

        echo "<tr class='warning'>\n";
        echo "<td><strong>" . htmlspecialchars($s1['nom_demandeur']) . "</strong><br><small>" . $s1['numero'] . "</small></td>\n";
        echo "<td><strong>" . htmlspecialchars($s2['nom_demandeur']) . "</strong><br><small>" . $s2['numero'] . "</small></td>\n";
        echo "<td><strong>" . number_format($doublon['distance'], 1) . " m</strong></td>\n";
        echo "<td>$meme_nom</td>\n";
        echo "<td>$action</td>\n";
        echo "</tr>\n";
    }

    echo "</tbody>\n</table>\n";
} else {
    echo "<div class='success'>✅ Aucun doublon GPS détecté !</div>\n";
}
echo "</div>\n";

// Onglet 3 : GPS manquants
echo "<div id='manquants' class='tab-content'>\n";
echo "<h2>📍 Stations Sans GPS ou GPS Invalide</h2>\n";

$manquants = array_filter($dossiers, function($d) {
    if (empty($d['coordonnees_gps'])) return true;
    return parseGPSCoordinates($d['coordonnees_gps']) === null;
});

if (count($manquants) > 0) {
    echo "<p>$sans_gps stations sans GPS + $gps_invalide avec GPS invalide = " . count($manquants) . " total</p>\n";
    echo "<table>\n<thead><tr>\n";
    echo "<th>Station</th><th>Région</th><th>Ville</th><th>Problème</th>\n";
    echo "</tr></thead>\n<tbody>\n";

    foreach (array_slice($manquants, 0, 50) as $station) {
        $probleme = empty($station['coordonnees_gps']) ? '❌ GPS manquant' : '⚠️ GPS invalide: ' . htmlspecialchars($station['coordonnees_gps']);

        echo "<tr class='warning'>\n";
        echo "<td><strong>" . htmlspecialchars($station['nom_demandeur']) . "</strong><br><small>" . $station['numero'] . "</small></td>\n";
        echo "<td>" . htmlspecialchars($station['region'] ?? 'N/A') . "</td>\n";
        echo "<td>" . htmlspecialchars($station['ville'] ?? 'N/A') . "</td>\n";
        echo "<td>$probleme</td>\n";
        echo "</tr>\n";
    }

    if (count($manquants) > 50) {
        echo "<tr><td colspan='4' style='text-align: center; font-style: italic;'>Affichage limité aux 50 premiers (total: " . count($manquants) . ")</td></tr>\n";
    }

    echo "</tbody>\n</table>\n";
} else {
    echo "<div class='success'>✅ Toutes les stations ont un GPS valide !</div>\n";
}
echo "</div>\n";

// Recommandations
echo "<h2>💡 Recommandations</h2>\n";

echo "<div class='info'>\n";
echo "<strong>Stratégie de nettoyage recommandée :</strong><br><br>\n";

if ($region_incoherente > 20) {
    echo "<strong>1. PRIORITÉ HAUTE - Corriger les incohérences géographiques</strong><br>\n";
    echo "→ Vérifier manuellement les $region_incoherente stations avec région incohérente<br>\n";
    echo "→ Option A : Corriger la région déclarée<br>\n";
    echo "→ Option B : Corriger les coordonnées GPS<br><br>\n";
}

if (count($doublons_gps) > 10) {
    echo "<strong>2. PRIORITÉ HAUTE - Fusionner les doublons</strong><br>\n";
    echo "→ " . count($doublons_gps) . " doublons potentiels détectés<br>\n";
    echo "→ Créer un script de fusion semi-automatique<br>\n";
    echo "→ Privilégier les données MINEE (administratives) + GPS OSM (précis)<br><br>\n";
}

if ($sans_gps > 20) {
    echo "<strong>3. PRIORITÉ MOYENNE - Compléter les GPS manquants</strong><br>\n";
    echo "→ $sans_gps stations sans GPS<br>\n";
    echo "→ Utiliser géocodage depuis adresse (Nominatim/Google)<br>\n";
    echo "→ Demander GPS aux opérateurs concernés<br><br>\n";
}

echo "<strong>4. Stratégie d'import à revoir</strong><br>\n";
echo "→ <strong>Recommandation :</strong> Partir uniquement des données MINEE<br>\n";
echo "→ Utiliser OSM seulement pour enrichir/corriger les GPS manquants<br>\n";
echo "→ Éviter l'import brut qui crée des doublons<br>\n";
echo "</div>\n";

echo "<script>\n";
echo "function showTab(tabName) {\n";
echo "    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));\n";
echo "    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));\n";
echo "    event.target.classList.add('active');\n";
echo "    document.getElementById(tabName).classList.add('active');\n";
echo "}\n";
echo "</script>\n";

echo "</div>\n</body>\n</html>\n";
?>

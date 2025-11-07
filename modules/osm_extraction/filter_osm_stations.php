<?php
/**
 * Script de filtrage intelligent des stations OSM
 * Extrait uniquement les stations de haute qualité
 *
 * Critères de qualité:
 * - Niveau 1 (Excellent): Nom + Opérateur + Ville
 * - Niveau 2 (Bon): Nom + Opérateur
 * - Niveau 3 (Moyen): Nom seulement
 */

// Sécurité : Accessible uniquement aux admins et chefs de service
require_once '../../includes/auth.php';
requireAnyRole(['admin', 'chef_service']);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Filtrage Stations OSM - Qualité</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
    .filters { background: #ecf0f1; padding: 20px; margin: 20px 0; border-radius: 5px; }
    .quality-box { display: inline-block; padding: 15px 20px; margin: 10px; border-radius: 5px; cursor: pointer; transition: all 0.3s; }
    .quality-box:hover { transform: scale(1.05); }
    .quality-excellent { background: #27ae60; color: white; }
    .quality-good { background: #3498db; color: white; }
    .quality-medium { background: #f39c12; color: white; }
    .quality-all { background: #95a5a6; color: white; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
    .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; }
    .stat-value { font-size: 2.5em; font-weight: bold; }
    .stat-label { font-size: 0.9em; opacity: 0.9; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; font-size: 0.9em; }
    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
    th { background: #3498db; color: white; position: sticky; top: 0; }
    tr:nth-child(even) { background: #f9f9f9; }
    tr:hover { background: #e8f4f8; }
    .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 0.85em; font-weight: bold; }
    .badge-excellent { background: #27ae60; color: white; }
    .badge-good { background: #3498db; color: white; }
    .badge-medium { background: #f39c12; color: white; }
    .badge-poor { background: #e74c3c; color: white; }
    .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; cursor: pointer; border: none; }
    .btn:hover { background: #2980b9; }
    .btn-success { background: #27ae60; }
    .btn-success:hover { background: #229954; }
    .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🎯 Filtrage Intelligent - Stations OSM de Qualité</h1>";

// Rechercher le dernier fichier CSV exporté
$exports_dir = __DIR__ . '/exports/';
$csv_files = glob($exports_dir . 'stations_osm_cameroun_*.csv');

if (empty($csv_files)) {
    echo "<div class='error'>❌ Aucun fichier CSV trouvé. Veuillez d'abord exécuter l'extraction OSM.</div>";
    echo "<a href='extract_osm_stations.php' class='btn'>🔄 Lancer extraction OSM</a>";
    echo "</div></body></html>";
    exit;
}

// Prendre le fichier le plus récent
rsort($csv_files);
$csv_file = $csv_files[0];
$csv_filename = basename($csv_file);

echo "<p><strong>📂 Fichier source:</strong> $csv_filename</p>";

// Lire le CSV
$stations = [];
$handle = fopen($csv_file, 'r');

// Ignorer le BOM UTF-8
fseek($handle, 3);

// Lire l'en-tête
$headers = fgetcsv($handle, 0, ';');

// Lire toutes les lignes
while (($data = fgetcsv($handle, 0, ';')) !== false) {
    if (count($data) === count($headers)) {
        $station = array_combine($headers, $data);
        $stations[] = $station;
    }
}
fclose($handle);

echo "<p>✅ <strong>" . count($stations) . " stations</strong> chargées depuis le fichier CSV</p>";

// Analyse de qualité
$quality_levels = [
    'excellent' => [], // Nom + Opérateur + Ville
    'good' => [],      // Nom + Opérateur
    'medium' => [],    // Nom seulement
    'poor' => []       // Sans nom
];

$stats_by_region = [];
$stats_by_operator = [];

foreach ($stations as $station) {
    $has_name = !empty($station['nom']) && $station['nom'] !== 'Station sans nom';
    $has_operator = !empty($station['operateur']);
    $has_ville = !empty($station['ville']);

    // Classement par qualité
    if ($has_name && $has_operator && $has_ville) {
        $quality_levels['excellent'][] = $station;
        $station['quality'] = 'excellent';
    } elseif ($has_name && $has_operator) {
        $quality_levels['good'][] = $station;
        $station['quality'] = 'good';
    } elseif ($has_name) {
        $quality_levels['medium'][] = $station;
        $station['quality'] = 'medium';
    } else {
        $quality_levels['poor'][] = $station;
        $station['quality'] = 'poor';
    }

    // Stats par région
    $region = $station['region'] ?? 'Non déterminé';
    if (!isset($stats_by_region[$region])) {
        $stats_by_region[$region] = 0;
    }
    $stats_by_region[$region]++;

    // Stats par opérateur
    if ($has_operator) {
        $operator = $station['operateur'];
        if (!isset($stats_by_operator[$operator])) {
            $stats_by_operator[$operator] = 0;
        }
        $stats_by_operator[$operator]++;
    }
}

// Affichage des statistiques de qualité
echo "<div class='filters'>";
echo "<h2>📊 Répartition par niveau de qualité</h2>";

echo "<div class='stats'>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #27ae60 0%, #229954 100%);'>";
echo "<div class='stat-value'>" . count($quality_levels['excellent']) . "</div>";
echo "<div class='stat-label'>Excellent<br><small>Nom + Opérateur + Ville</small></div>";
echo "</div>";

echo "<div class='stat-card' style='background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);'>";
echo "<div class='stat-value'>" . count($quality_levels['good']) . "</div>";
echo "<div class='stat-label'>Bon<br><small>Nom + Opérateur</small></div>";
echo "</div>";

echo "<div class='stat-card' style='background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);'>";
echo "<div class='stat-value'>" . count($quality_levels['medium']) . "</div>";
echo "<div class='stat-label'>Moyen<br><small>Nom seulement</small></div>";
echo "</div>";

echo "<div class='stat-card' style='background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);'>";
echo "<div class='stat-value'>" . count($quality_levels['poor']) . "</div>";
echo "<div class='stat-label'>Faible<br><small>Sans nom</small></div>";
echo "</div>";
echo "</div>";

echo "</div>";

// TOP opérateurs
echo "<div class='filters'>";
echo "<h2>🏢 TOP 10 Opérateurs</h2>";
arsort($stats_by_operator);
$top_operators = array_slice($stats_by_operator, 0, 10, true);

echo "<table>";
echo "<tr><th>Rang</th><th>Opérateur</th><th>Nombre de stations</th><th>%</th></tr>";
$rank = 1;
foreach ($top_operators as $operator => $count) {
    $percent = round($count / count($stations) * 100, 1);
    echo "<tr>";
    echo "<td><strong>#$rank</strong></td>";
    echo "<td>$operator</td>";
    echo "<td><strong>$count</strong></td>";
    echo "<td>$percent%</td>";
    echo "</tr>";
    $rank++;
}
echo "</table>";
echo "</div>";

// Formulaire de filtrage
echo "<div class='filters'>";
echo "<h2>🎯 Sélectionnez le niveau de qualité à exporter</h2>";

echo "<form method='POST' action=''>";
echo "<div style='margin: 20px 0;'>";

echo "<label style='display: block; margin: 10px 0;'>";
echo "<input type='radio' name='quality_filter' value='excellent' checked> ";
echo "<span class='badge badge-excellent'>EXCELLENT</span> ";
echo "(" . count($quality_levels['excellent']) . " stations) - Nom + Opérateur + Ville";
echo "</label>";

echo "<label style='display: block; margin: 10px 0;'>";
echo "<input type='radio' name='quality_filter' value='good'> ";
echo "<span class='badge badge-good'>BON</span> ";
echo "(" . count($quality_levels['good']) . " stations) - Nom + Opérateur";
echo "</label>";

echo "<label style='display: block; margin: 10px 0;'>";
echo "<input type='radio' name='quality_filter' value='excellent_good'> ";
echo "<span class='badge badge-excellent'>EXCELLENT</span> + <span class='badge badge-good'>BON</span> ";
echo "(" . (count($quality_levels['excellent']) + count($quality_levels['good'])) . " stations) - Recommandé ✅";
echo "</label>";

echo "<label style='display: block; margin: 10px 0;'>";
echo "<input type='radio' name='quality_filter' value='all'> ";
echo "<span class='badge badge-medium'>TOUTES</span> ";
echo "(" . count($stations) . " stations) - Sans filtrage";
echo "</label>";

echo "</div>";

echo "<button type='submit' name='export' class='btn btn-success'>📥 Générer CSV Filtré</button>";
echo "</form>";
echo "</div>";

// Traitement de l'export
if (isset($_POST['export'])) {
    $quality_filter = $_POST['quality_filter'] ?? 'excellent';

    $filtered_stations = [];

    switch ($quality_filter) {
        case 'excellent':
            $filtered_stations = $quality_levels['excellent'];
            $filter_name = 'Excellent';
            break;
        case 'good':
            $filtered_stations = $quality_levels['good'];
            $filter_name = 'Bon';
            break;
        case 'excellent_good':
            $filtered_stations = array_merge($quality_levels['excellent'], $quality_levels['good']);
            $filter_name = 'Excellent+Bon';
            break;
        case 'all':
            $filtered_stations = $stations;
            $filter_name = 'Toutes';
            break;
    }

    // Générer le nouveau CSV
    $new_csv_filename = 'stations_osm_filtrees_' . strtolower($filter_name) . '_' . date('Y-m-d_His') . '.csv';
    $new_csv_path = $exports_dir . $new_csv_filename;

    $fp = fopen($new_csv_path, 'w');

    // BOM UTF-8
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

    // En-têtes
    fputcsv($fp, $headers, ';');

    // Données filtrées
    foreach ($filtered_stations as $station) {
        $row = [];
        foreach ($headers as $header) {
            $row[] = $station[$header] ?? '';
        }
        fputcsv($fp, $row, ';');
    }

    fclose($fp);

    echo "<div class='success'>";
    echo "<h3>✅ Fichier CSV filtré généré avec succès!</h3>";
    echo "<p><strong>Filtre appliqué:</strong> $filter_name</p>";
    echo "<p><strong>Fichier:</strong> $new_csv_filename</p>";
    echo "<p><strong>Nombre de stations:</strong> " . count($filtered_stations) . " / " . count($stations) . "</p>";
    echo "<p><strong>Taille:</strong> " . number_format(filesize($new_csv_path)) . " octets</p>";
    echo "<a href='exports/$new_csv_filename' class='btn btn-success' download>📥 Télécharger CSV Filtré</a>";
    echo "</div>";

    // Aperçu
    echo "<h3>👀 Aperçu des 20 premières stations</h3>";
    echo "<table>";
    echo "<tr><th>Nom</th><th>Opérateur</th><th>Ville</th><th>Région</th><th>Coordonnées</th><th>Qualité</th></tr>";

    $preview = array_slice($filtered_stations, 0, 20);
    foreach ($preview as $s) {
        // Déterminer qualité
        $has_name = !empty($s['nom']) && $s['nom'] !== 'Station sans nom';
        $has_operator = !empty($s['operateur']);
        $has_ville = !empty($s['ville']);

        if ($has_name && $has_operator && $has_ville) {
            $quality_badge = "<span class='badge badge-excellent'>Excellent</span>";
        } elseif ($has_name && $has_operator) {
            $quality_badge = "<span class='badge badge-good'>Bon</span>";
        } elseif ($has_name) {
            $quality_badge = "<span class='badge badge-medium'>Moyen</span>";
        } else {
            $quality_badge = "<span class='badge badge-poor'>Faible</span>";
        }

        echo "<tr>";
        echo "<td>" . htmlspecialchars($s['nom']) . "</td>";
        echo "<td>" . htmlspecialchars($s['operateur']) . "</td>";
        echo "<td>" . htmlspecialchars($s['ville']) . "</td>";
        echo "<td>" . htmlspecialchars($s['region']) . "</td>";
        echo "<td>" . number_format($s['latitude'], 6) . ", " . number_format($s['longitude'], 6) . "</td>";
        echo "<td>$quality_badge</td>";
        echo "</tr>";
    }
    echo "</table>";

    if (count($filtered_stations) > 20) {
        echo "<p><em>... et " . (count($filtered_stations) - 20) . " autres stations</em></p>";
    }
}

echo "<div style='text-align:center;margin-top:30px;'>";
echo "<a href='filter_osm_stations.php' class='btn'>🔄 Réinitialiser</a>";
echo "<a href='extract_osm_stations.php' class='btn'>🗺️ Nouvelle extraction</a>";
echo "<a href='../../modules/import_historique/' class='btn'>📥 Module Import</a>";
echo "<a href='../../dashboard.php' class='btn'>🏠 Retour Dashboard</a>";
echo "</div>";

echo "</div>"; // container
echo "</body></html>";

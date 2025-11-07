<?php
/**
 * Analyse détaillée de la structure et qualité des données MINEE
 * Fichier: Stations_Service-1.csv
 */

set_time_limit(300);
ini_set('memory_limit', '512M');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Analyse Données MINEE</title>";
echo "<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .container { max-width: 1600px; margin: 0 auto; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
    h1 { color: #2c3e50; border-bottom: 4px solid #3498db; padding-bottom: 15px; margin-bottom: 30px; }
    h2 { color: #34495e; margin-top: 40px; padding: 15px; background: linear-gradient(90deg, #3498db, #2ecc71); color: white; border-radius: 8px; }
    h3 { color: #2c3e50; margin-top: 25px; border-left: 4px solid #3498db; padding-left: 15px; }
    .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0; }
    .card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; }
    .card.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .card.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
    .card.blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    .card.purple { background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); }
    .card-value { font-size: 3em; font-weight: bold; margin: 10px 0; }
    .card-label { font-size: 1.1em; opacity: 0.95; text-transform: uppercase; letter-spacing: 1px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; font-size: 0.9em; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; position: sticky; top: 0; font-weight: 600; }
    tr:nth-child(even) { background: #f8f9fa; }
    tr:hover { background: #e3f2fd; transition: 0.3s; }
    .progress-bar { background: #e9ecef; border-radius: 10px; overflow: hidden; height: 30px; margin: 10px 0; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, #3498db, #2ecc71); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; transition: width 0.3s; }
    .quality-excellent { background: #d4edda !important; color: #155724; font-weight: bold; }
    .quality-good { background: #d1ecf1 !important; color: #0c5460; }
    .quality-warning { background: #fff3cd !important; color: #856404; }
    .quality-poor { background: #f8d7da !important; color: #721c24; font-weight: bold; }
    .badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 0.85em; font-weight: bold; margin: 2px; }
    .badge-success { background: #28a745; color: white; }
    .badge-warning { background: #ffc107; color: #000; }
    .badge-danger { background: #dc3545; color: white; }
    .badge-info { background: #17a2b8; color: white; }
    .alert { padding: 20px; border-radius: 8px; margin: 20px 0; }
    .alert-info { background: #d1ecf1; color: #0c5460; border-left: 5px solid #17a2b8; }
    .alert-warning { background: #fff3cd; color: #856404; border-left: 5px solid #ffc107; }
    .alert-danger { background: #f8d7da; color: #721c24; border-left: 5px solid #dc3545; }
    .chart { margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 10px; }
    .bar-chart { margin: 20px 0; }
    .bar-row { display: flex; align-items: center; margin: 10px 0; }
    .bar-label { min-width: 200px; font-weight: 500; }
    .bar-container { flex: 1; background: #e9ecef; border-radius: 5px; height: 35px; position: relative; margin: 0 10px; }
    .bar-fill { height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); border-radius: 5px; display: flex; align-items: center; padding-left: 10px; color: white; font-weight: bold; transition: width 0.5s; }
    .bar-value { min-width: 60px; text-align: right; font-weight: bold; color: #2c3e50; }
    code { background: #f8f9fa; padding: 3px 8px; border-radius: 4px; font-family: 'Courier New', monospace; color: #e83e8c; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>📊 Analyse Détaillée des Données MINEE</h1>";
echo "<p style='color: #7f8c8d; font-size: 1.1em;'>Fichier: <code>Stations_Service-1.csv</code> | Date: " . date('d/m/Y H:i') . "</p>";

// Charger les données
$file_path = 'F:/PROJETS DPPG/Stations_Service-1.csv';

if (!file_exists($file_path)) {
    echo "<div class='alert alert-danger'>";
    echo "<h3>❌ Erreur</h3>";
    echo "<p>Fichier introuvable: <code>$file_path</code></p>";
    echo "</div></div></body></html>";
    exit;
}

echo "<div class='alert alert-info'>";
echo "<strong>✅ Fichier trouvé:</strong> " . number_format(filesize($file_path) / 1024, 2) . " KB | ";
echo "Dernière modification: " . date('d/m/Y H:i:s', filemtime($file_path));
echo "</div>";

// Lire le fichier CSV
$handle = fopen($file_path, 'r');
$headers = fgetcsv($handle, 0, ';');

// Nettoyer les en-têtes et gérer l'encodage ISO-8859-1
$headers = array_map(function($h) {
    $h = trim(str_replace("\xEF\xBB\xBF", '', $h));
    // Convertir ISO-8859-1 vers UTF-8 si nécessaire
    if (!mb_check_encoding($h, 'UTF-8')) {
        $h = mb_convert_encoding($h, 'UTF-8', 'ISO-8859-1');
    }
    return $h;
}, $headers);

$data = [];
$line_number = 1;

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $line_number++;
    if (count($row) === count($headers)) {
        // Convertir chaque valeur ISO-8859-1 vers UTF-8
        $row = array_map(function($value) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }
            return $value;
        }, $row);
        $data[] = array_combine($headers, $row);
    }
}
fclose($handle);

$total_records = count($data);

// ========================================
// 1. RÉSUMÉ GÉNÉRAL
// ========================================
echo "<h2>📋 1. Résumé Général</h2>";

$unique_marketers = array_unique(array_column($data, 'Marketer'));
$unique_regions = array_unique(array_filter(array_column($data, 'Région')));
$unique_departments = array_unique(array_filter(array_column($data, 'Département')));
$unique_cities = array_unique(array_filter(array_column($data, 'Ville/Localité')));

echo "<div class='summary'>";
echo "<div class='card blue'>";
echo "<div class='card-value'>" . number_format($total_records) . "</div>";
echo "<div class='card-label'>Total Stations</div>";
echo "</div>";

echo "<div class='card green'>";
echo "<div class='card-value'>" . count($unique_marketers) . "</div>";
echo "<div class='card-label'>Opérateurs</div>";
echo "</div>";

echo "<div class='card purple'>";
echo "<div class='card-value'>" . count($unique_regions) . "</div>";
echo "<div class='card-label'>Régions</div>";
echo "</div>";

echo "<div class='card orange'>";
echo "<div class='card-value'>" . count($unique_cities) . "</div>";
echo "<div class='card-label'>Villes/Localités</div>";
echo "</div>";
echo "</div>";

// ========================================
// 2. STRUCTURE DES DONNÉES
// ========================================
echo "<h2>🔍 2. Structure des Données</h2>";

echo "<h3>Colonnes disponibles (" . count($headers) . ")</h3>";
echo "<table>";
echo "<tr><th>#</th><th>Nom de la colonne</th><th>Type détecté</th><th>Valeurs renseignées</th><th>Taux remplissage</th><th>Qualité</th></tr>";

foreach ($headers as $index => $header) {
    $values = array_column($data, $header);
    $non_empty = array_filter($values, function($v) { return !empty(trim($v)); });
    $fill_rate = ($total_records > 0) ? (count($non_empty) / $total_records) * 100 : 0;

    // Détection du type
    $sample_value = !empty($non_empty) ? reset($non_empty) : '';
    $type = 'Texte';
    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $sample_value)) {
        $type = 'Date';
    } elseif (is_numeric($sample_value)) {
        $type = 'Nombre';
    }

    // Qualité
    $quality_class = '';
    $quality_label = '';
    if ($fill_rate >= 90) {
        $quality_class = 'quality-excellent';
        $quality_label = '<span class="badge badge-success">Excellent</span>';
    } elseif ($fill_rate >= 70) {
        $quality_class = 'quality-good';
        $quality_label = '<span class="badge badge-info">Bon</span>';
    } elseif ($fill_rate >= 50) {
        $quality_class = 'quality-warning';
        $quality_label = '<span class="badge badge-warning">Moyen</span>';
    } else {
        $quality_class = 'quality-poor';
        $quality_label = '<span class="badge badge-danger">Faible</span>';
    }

    echo "<tr class='$quality_class'>";
    echo "<td>" . ($index + 1) . "</td>";
    echo "<td><strong>" . htmlspecialchars($header) . "</strong></td>";
    echo "<td>$type</td>";
    echo "<td>" . number_format(count($non_empty)) . " / " . number_format($total_records) . "</td>";
    echo "<td>";
    echo "<div class='progress-bar'>";
    echo "<div class='progress-fill' style='width: {$fill_rate}%'>" . round($fill_rate) . "%</div>";
    echo "</div>";
    echo "</td>";
    echo "<td>$quality_label</td>";
    echo "</tr>";
}

echo "</table>";

// ========================================
// 3. ANALYSE PAR OPÉRATEUR
// ========================================
echo "<h2>🏢 3. Répartition par Opérateur</h2>";

$marketers_count = array_count_values(array_column($data, 'Marketer'));
arsort($marketers_count);

echo "<div class='chart'>";
echo "<h3>Top 15 Opérateurs</h3>";
echo "<div class='bar-chart'>";

$top_15 = array_slice($marketers_count, 0, 15, true);
$max_count = max($top_15);

foreach ($top_15 as $marketer => $count) {
    $percentage = ($max_count > 0) ? ($count / $max_count) * 100 : 0;
    echo "<div class='bar-row'>";
    echo "<div class='bar-label'>" . htmlspecialchars($marketer) . "</div>";
    echo "<div class='bar-container'>";
    echo "<div class='bar-fill' style='width: {$percentage}%'>{$count}</div>";
    echo "</div>";
    echo "<div class='bar-value'>" . round(($count / $total_records) * 100, 1) . "%</div>";
    echo "</div>";
}

echo "</div>";
echo "</div>";

// Statistiques opérateurs
echo "<h3>Statistiques Opérateurs</h3>";
echo "<table>";
echo "<tr><th>Opérateur</th><th>Nombre de stations</th><th>% du total</th><th>Rang</th></tr>";

$rank = 1;
foreach (array_slice($marketers_count, 0, 20, true) as $marketer => $count) {
    $percentage = ($count / $total_records) * 100;
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($marketer) . "</strong></td>";
    echo "<td>" . number_format($count) . "</td>";
    echo "<td>" . round($percentage, 2) . "%</td>";
    echo "<td>#$rank</td>";
    echo "</tr>";
    $rank++;
}

echo "</table>";

// ========================================
// 4. ANALYSE PAR RÉGION
// ========================================
echo "<h2>🗺️ 4. Répartition par Région</h2>";

$regions_count = array_count_values(array_filter(array_column($data, 'Région')));
arsort($regions_count);

echo "<div class='chart'>";
echo "<h3>Distribution géographique</h3>";

if (empty($regions_count)) {
    echo "<div class='alert alert-warning'>";
    echo "<p>⚠️ Aucune région trouvée dans les données.</p>";
    echo "</div>";
} else {
    echo "<div class='bar-chart'>";
    $max_count = max($regions_count);

foreach ($regions_count as $region => $count) {
    $percentage = ($max_count > 0) ? ($count / $max_count) * 100 : 0;
    echo "<div class='bar-row'>";
    echo "<div class='bar-label'>" . htmlspecialchars($region) . "</div>";
    echo "<div class='bar-container'>";
    echo "<div class='bar-fill' style='width: {$percentage}%'>{$count}</div>";
    echo "</div>";
    echo "<div class='bar-value'>" . round(($count / $total_records) * 100, 1) . "%</div>";
    echo "</div>";
}

    echo "</div>";
    echo "</div>";
}

// ========================================
// 5. QUALITÉ DES DONNÉES
// ========================================
echo "<h2>⚠️ 5. Analyse de Qualité</h2>";

// Enregistrements sans région
$no_region = array_filter($data, function($row) {
    return empty(trim($row['Région'] ?? ''));
});

// Enregistrements sans ville
$no_city = array_filter($data, function($row) {
    return empty(trim($row['Ville/Localité'] ?? ''));
});

// Enregistrements sans date
$no_date = array_filter($data, function($row) {
    return empty(trim($row['Date de Mise en service'] ?? ''));
});

// Enregistrements complets
$complete_records = array_filter($data, function($row) {
    return !empty(trim($row['Région'] ?? '')) &&
           !empty(trim($row['Ville/Localité'] ?? '')) &&
           !empty(trim($row['Quartier'] ?? ''));
});

echo "<div class='summary'>";
echo "<div class='card green'>";
echo "<div class='card-value'>" . count($complete_records) . "</div>";
echo "<div class='card-label'>Enregistrements Complets</div>";
echo "<div style='font-size: 0.9em; margin-top: 10px;'>" . round((count($complete_records) / $total_records) * 100, 1) . "% du total</div>";
echo "</div>";

echo "<div class='card orange'>";
echo "<div class='card-value'>" . count($no_region) . "</div>";
echo "<div class='card-label'>Sans Région</div>";
echo "<div style='font-size: 0.9em; margin-top: 10px;'>" . round((count($no_region) / $total_records) * 100, 1) . "% du total</div>";
echo "</div>";

echo "<div class='card orange'>";
echo "<div class='card-value'>" . count($no_city) . "</div>";
echo "<div class='card-label'>Sans Ville</div>";
echo "<div style='font-size: 0.9em; margin-top: 10px;'>" . round((count($no_city) / $total_records) * 100, 1) . "% du total</div>";
echo "</div>";

echo "<div class='card orange'>";
echo "<div class='card-value'>" . count($no_date) . "</div>";
echo "<div class='card-label'>Sans Date</div>";
echo "<div style='font-size: 0.9em; margin-top: 10px;'>" . round((count($no_date) / $total_records) * 100, 1) . "% du total</div>";
echo "</div>";
echo "</div>";

// ========================================
// 6. DOUBLONS POTENTIELS
// ========================================
echo "<h2>🔄 6. Détection de Doublons</h2>";

$potential_duplicates = [];
$seen = [];

foreach ($data as $index => $row) {
    $key = strtolower(trim($row['Marketer'] ?? '')) . '|' .
           strtolower(trim($row['Ville/Localité'] ?? '')) . '|' .
           strtolower(trim($row['Quartier'] ?? ''));

    if (isset($seen[$key])) {
        if (!isset($potential_duplicates[$key])) {
            $potential_duplicates[$key] = [$seen[$key]];
        }
        $potential_duplicates[$key][] = $index;
    } else {
        $seen[$key] = $index;
    }
}

$duplicate_count = count($potential_duplicates);

if ($duplicate_count > 0) {
    echo "<div class='alert alert-warning'>";
    echo "<h3>⚠️ Doublons Détectés</h3>";
    echo "<p><strong>" . $duplicate_count . " groupes de doublons potentiels</strong> trouvés (même opérateur + même ville + même quartier)</p>";
    echo "</div>";

    echo "<h3>Exemples de doublons (10 premiers)</h3>";
    echo "<table>";
    echo "<tr><th>Groupe</th><th>Opérateur</th><th>Ville</th><th>Quartier</th><th>Occurrences</th></tr>";

    $group = 1;
    foreach (array_slice($potential_duplicates, 0, 10, true) as $key => $indices) {
        $first_record = $data[$indices[0]];
        echo "<tr>";
        echo "<td><strong>#{$group}</strong></td>";
        echo "<td>" . htmlspecialchars($first_record['Marketer']) . "</td>";
        echo "<td>" . htmlspecialchars($first_record['Ville/Localité']) . "</td>";
        echo "<td>" . htmlspecialchars($first_record['Quartier']) . "</td>";
        echo "<td><span class='badge badge-warning'>" . count($indices) . " fois</span></td>";
        echo "</tr>";
        $group++;
    }

    echo "</table>";
} else {
    echo "<div class='alert alert-info'>";
    echo "<h3>✅ Aucun Doublon Détecté</h3>";
    echo "<p>Tous les enregistrements semblent uniques.</p>";
    echo "</div>";
}

// ========================================
// 7. ANALYSE DES DATES
// ========================================
echo "<h2>📅 7. Analyse des Dates de Mise en Service</h2>";

$dates = array_filter(array_column($data, 'Date de Mise en service'));
$valid_dates = [];
$invalid_dates = [];

foreach ($dates as $date_str) {
    $date_str = trim($date_str);
    if (empty($date_str)) continue;

    // Essayer de parser la date
    if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $date_str, $matches)) {
        $valid_dates[] = $date_str;
    } else {
        $invalid_dates[] = $date_str;
    }
}

echo "<div class='summary'>";
echo "<div class='card green'>";
echo "<div class='card-value'>" . count($valid_dates) . "</div>";
echo "<div class='card-label'>Dates Valides</div>";
echo "</div>";

echo "<div class='card orange'>";
echo "<div class='card-value'>" . count($invalid_dates) . "</div>";
echo "<div class='card-label'>Dates Invalides</div>";
echo "</div>";

echo "<div class='card blue'>";
echo "<div class='card-value'>" . ($total_records - count($dates)) . "</div>";
echo "<div class='card-label'>Sans Date</div>";
echo "</div>";
echo "</div>";

if (count($invalid_dates) > 0) {
    echo "<h3>Exemples de dates invalides</h3>";
    echo "<table>";
    echo "<tr><th>#</th><th>Date invalide</th></tr>";
    foreach (array_slice($invalid_dates, 0, 10) as $idx => $date) {
        echo "<tr>";
        echo "<td>" . ($idx + 1) . "</td>";
        echo "<td><code>" . htmlspecialchars($date) . "</code></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// ========================================
// 8. ZONES D'IMPLANTATION
// ========================================
echo "<h2>🏘️ 8. Zones d'Implantation</h2>";

$zones = array_count_values(array_filter(array_column($data, 'Zone d\'implantation')));
arsort($zones);

echo "<table>";
echo "<tr><th>Zone</th><th>Nombre</th><th>%</th></tr>";

foreach ($zones as $zone => $count) {
    $percentage = ($count / $total_records) * 100;
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($zone) . "</strong></td>";
    echo "<td>" . number_format($count) . "</td>";
    echo "<td>" . round($percentage, 2) . "%</td>";
    echo "</tr>";
}

echo "</table>";

// ========================================
// 9. RECOMMANDATIONS
// ========================================
echo "<h2>💡 9. Recommandations</h2>";

$recommendations = [];

if (count($no_region) > 0) {
    $recommendations[] = [
        'type' => 'warning',
        'title' => 'Régions manquantes',
        'message' => count($no_region) . ' enregistrements sans région. Ces données doivent être complétées pour l\'import.'
    ];
}

if (count($no_city) > 0) {
    $recommendations[] = [
        'type' => 'warning',
        'title' => 'Villes manquantes',
        'message' => count($no_city) . ' enregistrements sans ville/localité. Information critique pour la géolocalisation.'
    ];
}

if ($duplicate_count > 0) {
    $recommendations[] = [
        'type' => 'warning',
        'title' => 'Doublons détectés',
        'message' => $duplicate_count . ' groupes de doublons potentiels. Vérifier manuellement avant l\'import.'
    ];
}

if (count($invalid_dates) > 0) {
    $recommendations[] = [
        'type' => 'warning',
        'title' => 'Dates invalides',
        'message' => count($invalid_dates) . ' dates au format invalide. Standardiser au format JJ/MM/AAAA.'
    ];
}

$quality_score = round((count($complete_records) / $total_records) * 100, 1);

if ($quality_score >= 90) {
    $recommendations[] = [
        'type' => 'info',
        'title' => 'Excellente qualité des données',
        'message' => 'Score de qualité: ' . $quality_score . '%. Les données sont prêtes pour l\'import avec un minimum de nettoyage.'
    ];
} elseif ($quality_score >= 70) {
    $recommendations[] = [
        'type' => 'info',
        'title' => 'Bonne qualité des données',
        'message' => 'Score de qualité: ' . $quality_score . '%. Quelques corrections mineures recommandées avant l\'import.'
    ];
} else {
    $recommendations[] = [
        'type' => 'danger',
        'title' => 'Qualité à améliorer',
        'message' => 'Score de qualité: ' . $quality_score . '%. Nettoyage important recommandé avant l\'import.'
    ];
}

foreach ($recommendations as $rec) {
    $alert_class = 'alert-' . $rec['type'];
    echo "<div class='alert $alert_class'>";
    echo "<h3>" . htmlspecialchars($rec['title']) . "</h3>";
    echo "<p>" . htmlspecialchars($rec['message']) . "</p>";
    echo "</div>";
}

// ========================================
// SCORE FINAL
// ========================================
echo "<div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center; margin-top: 40px;'>";
echo "<h2 style='color: white; border: none; margin: 0;'>Score de Qualité Globale</h2>";
echo "<div style='font-size: 5em; font-weight: bold; margin: 20px 0;'>{$quality_score}%</div>";

$grade = 'F';
if ($quality_score >= 90) $grade = 'A+';
elseif ($quality_score >= 80) $grade = 'A';
elseif ($quality_score >= 70) $grade = 'B';
elseif ($quality_score >= 60) $grade = 'C';
elseif ($quality_score >= 50) $grade = 'D';

echo "<div style='font-size: 2em; letter-spacing: 5px;'>Grade: {$grade}</div>";
echo "<p style='margin-top: 20px; font-size: 1.1em;'>";
echo "Basé sur: Complétude des champs critiques (Région, Ville, Quartier)";
echo "</p>";
echo "</div>";

echo "</div></body></html>";

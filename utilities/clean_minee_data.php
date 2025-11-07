<?php
/**
 * Nettoyage et standardisation des données MINEE
 * Corrige: dates, doublons, champs vides
 */

set_time_limit(300);
ini_set('memory_limit', '512M');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Nettoyage Données MINEE</title>";
echo "<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
    .container { max-width: 1600px; margin: 0 auto; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
    h1 { color: #2c3e50; border-bottom: 4px solid #e74c3c; padding-bottom: 15px; margin-bottom: 30px; }
    h2 { color: #34495e; margin-top: 40px; padding: 15px; background: linear-gradient(90deg, #e74c3c, #f39c12); color: white; border-radius: 8px; }
    h3 { color: #2c3e50; margin-top: 25px; border-left: 4px solid #e74c3c; padding-left: 15px; }
    .step { background: #ecf0f1; padding: 25px; margin: 25px 0; border-left: 5px solid #e74c3c; border-radius: 0 10px 10px 0; }
    .success { background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 5px solid #28a745; }
    .warning { background: #fff3cd; color: #856404; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 5px solid #ffc107; }
    .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 5px solid #dc3545; }
    .info { background: #d1ecf1; color: #0c5460; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 5px solid #17a2b8; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 25px 0; }
    .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 10px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    .stat-value { font-size: 2.5em; font-weight: bold; margin: 10px 0; }
    .stat-label { font-size: 1em; opacity: 0.95; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; font-size: 0.9em; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; position: sticky; top: 0; font-weight: 600; }
    tr:nth-child(even) { background: #f8f9fa; }
    tr:hover { background: #e3f2fd; transition: 0.3s; }
    .badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 0.85em; font-weight: bold; margin: 2px; }
    .badge-success { background: #28a745; color: white; }
    .badge-warning { background: #ffc107; color: #000; }
    .badge-danger { background: #dc3545; color: white; }
    .badge-info { background: #17a2b8; color: white; }
    .btn { display: inline-block; padding: 15px 30px; background: #e74c3c; color: white; text-decoration: none; border-radius: 8px; margin: 10px 5px; cursor: pointer; border: none; font-size: 16px; font-weight: bold; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
    .btn:hover { background: #c0392b; transform: translateY(-2px); transition: 0.3s; }
    .btn-success { background: #28a745; }
    .btn-success:hover { background: #218838; }
    .progress-bar { width: 100%; height: 30px; background: #e9ecef; border-radius: 8px; overflow: hidden; margin: 10px 0; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, #e74c3c, #f39c12); transition: width 0.3s; text-align: center; line-height: 30px; color: white; font-weight: bold; }
    code { background: #f8f9fa; padding: 3px 8px; border-radius: 4px; font-family: 'Courier New', monospace; color: #e83e8c; }
    .compare { display: grid; grid-template-columns: 1fr auto 1fr; gap: 15px; align-items: center; margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; }
    .compare-before { text-align: right; color: #dc3545; }
    .compare-after { text-align: left; color: #28a745; }
    .compare-arrow { font-size: 1.5em; color: #6c757d; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🧹 Nettoyage des Données MINEE</h1>";
echo "<p style='color: #7f8c8d; font-size: 1.1em;'>Standardisation et correction automatique des données | " . date('d/m/Y H:i') . "</p>";

$input_file = 'F:/PROJETS DPPG/Stations_Service-1.csv';
$output_file = 'F:/PROJETS DPPG/Stations_Service-1_CLEANED.csv';

// ========================================
// ÉTAPE 1: CHARGEMENT
// ========================================
echo "<div class='step'>";
echo "<h2>📥 Étape 1: Chargement des données</h2>";

if (!file_exists($input_file)) {
    echo "<div class='error'><strong>❌ Erreur:</strong> Fichier introuvable: <code>$input_file</code></div>";
    echo "</div></div></body></html>";
    exit;
}

echo "<div class='info'>";
echo "<strong>✅ Fichier source:</strong> " . basename($input_file) . " (" . number_format(filesize($input_file) / 1024, 2) . " KB)";
echo "</div>";

// Charger les données
$handle = fopen($input_file, 'r');
$headers = fgetcsv($handle, 0, ';');

// Nettoyer et convertir les en-têtes
$headers = array_map(function($h) {
    $h = trim(str_replace("\xEF\xBB\xBF", '', $h));
    if (!mb_check_encoding($h, 'UTF-8')) {
        $h = mb_convert_encoding($h, 'UTF-8', 'ISO-8859-1');
    }
    return $h;
}, $headers);

$data = [];
while (($row = fgetcsv($handle, 0, ';')) !== false) {
    if (count($row) === count($headers)) {
        // Convertir les valeurs
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

echo "<div class='success'>";
echo "<strong>✅ Chargement terminé:</strong> $total_records enregistrements chargés";
echo "</div>";
echo "</div>";

// ========================================
// ÉTAPE 2: NETTOYAGE DES DATES
// ========================================
echo "<div class='step'>";
echo "<h2>📅 Étape 2: Conversion des dates</h2>";

$months_fr = [
    'janvier' => '01', 'jan' => '01',
    'février' => '02', 'fév' => '02', 'fevrier' => '02', 'fev' => '02',
    'mars' => '03', 'mar' => '03',
    'avril' => '04', 'avr' => '04',
    'mai' => '05',
    'juin' => '06',
    'juillet' => '07', 'juil' => '07',
    'août' => '08', 'aout' => '08',
    'septembre' => '09', 'sept' => '09', 'sep' => '09',
    'octobre' => '10', 'oct' => '10',
    'novembre' => '11', 'nov' => '11',
    'décembre' => '12', 'déc' => '12', 'dec' => '12'
];

$dates_converted = 0;
$dates_failed = 0;
$dates_examples = [];

foreach ($data as $index => &$row) {
    $date_field = trim($row['Date de Mise en service'] ?? '');

    if (empty($date_field)) {
        continue;
    }

    // Déjà au bon format ?
    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $date_field)) {
        continue;
    }

    // Format: "MS PV 05 juin 2023" ou "MS pv 28 fev 2020"
    if (preg_match('/MS\s+PV\s+(\d{1,2})\s+(\w+)\s+(\d{4})/i', $date_field, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $month_text = strtolower(trim($matches[2]));
        $year = $matches[3];

        if (isset($months_fr[$month_text])) {
            $month = $months_fr[$month_text];
            $new_date = "$day/$month/$year";

            if ($dates_converted < 5) {
                $dates_examples[] = [
                    'old' => $date_field,
                    'new' => $new_date
                ];
            }

            $row['Date de Mise en service'] = $new_date;
            $dates_converted++;
        } else {
            $dates_failed++;
        }
    } else {
        $dates_failed++;
    }
}
unset($row);

echo "<div class='stats'>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);'>";
echo "<div class='stat-value'>$dates_converted</div>";
echo "<div class='stat-label'>Dates converties</div>";
echo "</div>";

echo "<div class='stat-card' style='background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);'>";
echo "<div class='stat-value'>$dates_failed</div>";
echo "<div class='stat-label'>Dates non converties</div>";
echo "</div>";
echo "</div>";

if (!empty($dates_examples)) {
    echo "<h3>Exemples de conversion</h3>";
    foreach ($dates_examples as $example) {
        echo "<div class='compare'>";
        echo "<div class='compare-before'><code>" . htmlspecialchars($example['old']) . "</code></div>";
        echo "<div class='compare-arrow'>→</div>";
        echo "<div class='compare-after'><code>" . htmlspecialchars($example['new']) . "</code></div>";
        echo "</div>";
    }
}

echo "</div>";

// ========================================
// ÉTAPE 3: TRAITEMENT DES DOUBLONS
// ========================================
echo "<div class='step'>";
echo "<h2>🔄 Étape 3: Identification des doublons</h2>";

$seen = [];
$duplicates = [];
$duplicate_groups = [];

foreach ($data as $index => $row) {
    $key = strtolower(trim($row['Marketer'] ?? '')) . '|' .
           strtolower(trim($row['Ville/Localité'] ?? '')) . '|' .
           strtolower(trim($row['Quartier'] ?? ''));

    if (isset($seen[$key])) {
        if (!isset($duplicate_groups[$key])) {
            $duplicate_groups[$key] = [$seen[$key]];
        }
        $duplicate_groups[$key][] = $index;
        $duplicates[] = $index;
    } else {
        $seen[$key] = $index;
    }
}

$duplicate_count = count($duplicate_groups);

echo "<div class='stats'>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #fbc2eb 0%, #a6c1ee 100%);'>";
echo "<div class='stat-value'>$duplicate_count</div>";
echo "<div class='stat-label'>Groupes de doublons</div>";
echo "</div>";

echo "<div class='stat-card' style='background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);'>";
echo "<div class='stat-value'>" . count($duplicates) . "</div>";
echo "<div class='stat-label'>Enregistrements en doublon</div>";
echo "</div>";
echo "</div>";

if ($duplicate_count > 0) {
    echo "<div class='warning'>";
    echo "<strong>⚠️ Action requise:</strong> $duplicate_count groupes de doublons détectés. ";
    echo "Ils sont marqués dans le fichier de sortie avec <code>[DOUBLON]</code> dans les observations.";
    echo "</div>";

    // Marquer les doublons
    foreach ($duplicate_groups as $key => $indices) {
        foreach ($indices as $idx) {
            $current_obs = $data[$idx]['Observation'] ?? '';
            if (!empty($current_obs)) {
                $current_obs .= '; ';
            }
            $data[$idx]['Observation'] = $current_obs . '[DOUBLON - ' . count($indices) . ' occurrences similaires]';
        }
    }
}

echo "</div>";

// ========================================
// ÉTAPE 4: NETTOYAGE DES CHAMPS VIDES
// ========================================
echo "<div class='step'>";
echo "<h2>🔧 Étape 4: Standardisation des champs</h2>";

$marketer_empty = 0;
$region_empty = 0;
$ville_empty = 0;

foreach ($data as $index => &$row) {
    // Nettoyer les espaces
    foreach ($row as $key => $value) {
        $row[$key] = trim($value);
    }

    // Marquer les enregistrements avec opérateur vide
    if (empty($row['Marketer'])) {
        $marketer_empty++;
        $current_obs = $row['Observation'] ?? '';
        if (!empty($current_obs)) {
            $current_obs .= '; ';
        }
        $row['Observation'] = $current_obs . '[OPÉRATEUR MANQUANT]';
    }

    // Marquer les enregistrements sans région
    if (empty($row['Région'])) {
        $region_empty++;
        $current_obs = $row['Observation'] ?? '';
        if (!empty($current_obs)) {
            $current_obs .= '; ';
        }
        $row['Observation'] = $current_obs . '[RÉGION MANQUANTE]';
    }

    // Marquer les enregistrements sans ville
    if (empty($row['Ville/Localité'])) {
        $ville_empty++;
        $current_obs = $row['Observation'] ?? '';
        if (!empty($current_obs)) {
            $current_obs .= '; ';
        }
        $row['Observation'] = $current_obs . '[VILLE MANQUANTE]';
    }

    // Standardiser les zones d'implantation
    $zone = strtolower(trim($row['Zone d\'implantation'] ?? ''));
    if (in_array($zone, ['urbain', 'urbaine'])) {
        $row['Zone d\'implantation'] = 'Urbaine';
    } elseif (in_array($zone, ['rural', 'rurale'])) {
        $row['Zone d\'implantation'] = 'Rurale';
    }
}
unset($row);

echo "<div class='stats'>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);'>";
echo "<div class='stat-value'>$marketer_empty</div>";
echo "<div class='stat-label'>Opérateurs manquants</div>";
echo "</div>";

echo "<div class='stat-card' style='background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);'>";
echo "<div class='stat-value'>$region_empty</div>";
echo "<div class='stat-label'>Régions manquantes</div>";
echo "</div>";

echo "<div class='stat-card' style='background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);'>";
echo "<div class='stat-value'>$ville_empty</div>";
echo "<div class='stat-label'>Villes manquantes</div>";
echo "</div>";
echo "</div>";

echo "<div class='info'>";
echo "✅ Tous les champs problématiques sont marqués dans la colonne <strong>Observation</strong> pour révision manuelle.";
echo "</div>";

echo "</div>";

// ========================================
// ÉTAPE 5: GÉNÉRATION DU FICHIER NETTOYÉ
// ========================================
echo "<div class='step'>";
echo "<h2>💾 Étape 5: Génération du fichier nettoyé</h2>";

$fp = fopen($output_file, 'w');

// Écrire le BOM UTF-8 pour préserver les accents dans Excel
fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

// Écrire les données en UTF-8
fputcsv($fp, $headers, ';');

foreach ($data as $row) {
    fputcsv($fp, $row, ';');
}

fclose($fp);

$output_size = filesize($output_file);

echo "<div class='success'>";
echo "<h3>✅ Fichier nettoyé généré avec succès!</h3>";
echo "<p><strong>Fichier:</strong> <code>" . basename($output_file) . "</code></p>";
echo "<p><strong>Taille:</strong> " . number_format($output_size / 1024, 2) . " KB</p>";
echo "<p><strong>Enregistrements:</strong> $total_records</p>";
echo "</div>";

echo "</div>";

// ========================================
// RÉSUMÉ FINAL
// ========================================
echo "<div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 15px; margin-top: 40px;'>";
echo "<h2 style='color: white; border: none; margin: 0 0 30px 0; text-align: center;'>📊 Résumé du Nettoyage</h2>";

echo "<div style='display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px;'>";

echo "<div style='background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px;'>";
echo "<h3 style='margin: 0 0 15px 0; color: white; border: none;'>✅ Corrections Appliquées</h3>";
echo "<ul style='margin: 0; padding-left: 20px;'>";
echo "<li><strong>$dates_converted dates</strong> converties au format standard</li>";
echo "<li><strong>$duplicate_count groupes de doublons</strong> identifiés et marqués</li>";
echo "<li><strong>$marketer_empty opérateurs manquants</strong> signalés</li>";
echo "<li><strong>Zones d'implantation</strong> standardisées</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px;'>";
echo "<h3 style='margin: 0 0 15px 0; color: white; border: none;'>⚠️ Actions Requises</h3>";
echo "<ul style='margin: 0; padding-left: 20px;'>";
if ($marketer_empty > 0) echo "<li>Compléter <strong>$marketer_empty opérateurs</strong></li>";
if ($region_empty > 0) echo "<li>Compléter <strong>$region_empty régions</strong></li>";
if ($ville_empty > 0) echo "<li>Compléter <strong>$ville_empty villes</strong></li>";
if ($duplicate_count > 0) echo "<li>Vérifier <strong>$duplicate_count doublons</strong></li>";
if ($dates_failed > 0) echo "<li>Corriger <strong>$dates_failed dates</strong> manuellement</li>";
echo "</ul>";
echo "</div>";

echo "</div>";

echo "<div style='text-align: center; margin-top: 30px;'>";
echo "<p style='font-size: 1.2em; margin-bottom: 20px;'>Le fichier nettoyé est prêt pour l'étape suivante :</p>";
echo "<a href='match_minee_osm.php' class='btn btn-success' style='font-size: 1.2em; padding: 20px 40px;'>";
echo "🗺️ Étape Suivante: Matching avec OSM</a>";
echo "</div>";

echo "</div>";

echo "</div></body></html>";

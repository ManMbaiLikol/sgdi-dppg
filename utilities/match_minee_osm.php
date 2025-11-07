<?php
/**
 * Outil de matching MINEE ↔ OSM
 * Fusionne les données officielles MINEE avec les coordonnées GPS d'OSM
 */

// Sécurité : Accessible uniquement aux admins et chefs de service
require_once '../../includes/auth.php';
requireAnyRole(['admin', 'chef_service']);

set_time_limit(300);
ini_set('memory_limit', '512M');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Matching MINEE ↔ OSM</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
    .step { background: #ecf0f1; padding: 20px; margin: 20px 0; border-left: 4px solid #3498db; border-radius: 0 8px 8px 0; }
    .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; font-size: 0.85em; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #3498db; color: white; position: sticky; top: 0; }
    tr:nth-child(even) { background: #f9f9f9; }
    .match-high { background: #d4edda !important; }
    .match-medium { background: #fff3cd !important; }
    .match-low { background: #f8d7da !important; }
    .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 0.85em; font-weight: bold; color: white; }
    .badge-success { background: #28a745; }
    .badge-warning { background: #ffc107; color: #000; }
    .badge-danger { background: #dc3545; }
    .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; cursor: pointer; border: none; font-size: 14px; }
    .btn:hover { background: #2980b9; }
    .btn-success { background: #28a745; }
    .btn-success:hover { background: #218838; }
    .progress-bar { width: 100%; height: 30px; background: #e9ecef; border-radius: 5px; overflow: hidden; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, #3498db, #2ecc71); transition: width 0.3s; text-align: center; line-height: 30px; color: white; font-weight: bold; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔗 Matching MINEE ↔ OSM</h1>";
echo "<p class='text-muted'>Fusion intelligente des données officielles MINEE avec les coordonnées GPS d'OpenStreetMap</p>";

// Étape 1: Vérifier les fichiers
echo "<div class='step'>";
echo "<h2>📂 Étape 1: Vérification des fichiers</h2>";

$minee_file = 'F:/PROJETS DPPG/Stations_Service-1_CLEANED.csv';
$osm_file = __DIR__ . '/../../';

// Trouver le dernier fichier OSM filtré
$osm_files = array_merge(
    glob($osm_file . 'stations_osm_filtrees_*.csv'),
    glob($osm_file . 'stations_osm_cameroun_*.csv')
);

// Debug: afficher les fichiers trouvés
if (empty($osm_files)) {
    // Essayer dans le répertoire courant
    $osm_files = array_merge(
        glob('../../stations_osm_filtrees_*.csv'),
        glob('../../stations_osm_cameroun_*.csv')
    );
}

if (empty($osm_files)) {
    echo "<div class='error'>";
    echo "<h3>❌ Fichier OSM introuvable</h3>";
    echo "<p>Veuillez d'abord extraire les stations OSM.</p>";
    echo "<a href='extract_osm_stations.php' class='btn'>🗺️ Extraire OSM</a>";
    echo "</div></div></body></html>";
    exit;
}

usort($osm_files, function($a, $b) { return filemtime($b) - filemtime($a); });
$osm_file = $osm_files[0];

echo "<div class='success'>";
echo "<p>✅ <strong>Fichier MINEE:</strong> " . basename($minee_file) . " (" . number_format(filesize($minee_file)/1024, 2) . " KB)</p>";
echo "<p>✅ <strong>Fichier OSM:</strong> " . basename($osm_file) . " (" . number_format(filesize($osm_file)/1024, 2) . " KB)</p>";
echo "</div>";
echo "</div>";

// Étape 2: Chargement des données
echo "<div class='step'>";
echo "<h2>📥 Étape 2: Chargement des données</h2>";

// Charger MINEE
$minee_stations = [];
$handle = fopen($minee_file, 'r');
// Gérer le BOM UTF-8
fseek($handle, 0);
$bom = fread($handle, 3);
if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
    fseek($handle, 0);
}
$headers_minee = fgetcsv($handle, 0, ';');

// Nettoyer les en-têtes
$headers_minee = array_map(function($h) {
    return trim(str_replace(["\xEF\xBB\xBF", '"'], '', $h));
}, $headers_minee);

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    if (count($row) === count($headers_minee)) {
        $station = array_combine($headers_minee, $row);
        $minee_stations[] = $station;
    }
}
fclose($handle);

echo "<p>✅ <strong>" . count($minee_stations) . " stations MINEE</strong> chargées</p>";

// Charger OSM
$osm_stations = [];
$handle = fopen($osm_file, 'r');
fseek($handle, 3); // Skip BOM
$headers_osm = fgetcsv($handle, 0, ';');

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    if (count($row) === count($headers_osm)) {
        $station = array_combine($headers_osm, $row);
        $osm_stations[] = $station;
    }
}
fclose($handle);

echo "<p>✅ <strong>" . count($osm_stations) . " stations OSM</strong> chargées</p>";
echo "</div>";

// Étape 3: Matching
echo "<div class='step'>";
echo "<h2>🔍 Étape 3: Matching intelligent</h2>";

// Table de synonymes pour opérateurs
$operator_synonyms = [
    'total' => ['totalenergies', 'total energies', 'total energie'],
    'ola' => ['ola energy', 'oilybia', 'oilibya'],
    'bocom' => ['bocom petroleum', 'bocom petrol'],
    'tradex' => ['tradex oil', 'tradex petroleum'],
    'neptune' => ['neptune oil', 'neptune petroleum'],
    'corlay' => ['corlay oil', 'corlay petroleum'],
    'blessing' => ['blessing oil', 'blessing petroleum'],
    'camoco' => ['camoco oil', 'camoco petroleum'],
    'africa' => ['africa petro', 'africa petroleum', 'african petroleum'],
    'mobyl' => ['mobyl petroleum', 'mobil', 'mobil oil'],
    'mrs' => ['mrs oil', 'mrs petroleum'],
];

// Fonction de normalisation améliorée
function normalize_string($str) {
    $str = mb_strtolower(trim($str), 'UTF-8');
    // Supprimer accents
    $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
    // Remplacer underscores et tirets par espaces
    $str = str_replace(['_', '-'], ' ', $str);
    // Supprimer mots parasites
    $str = preg_replace('/\b(sarl|sa|inc|ltd|oil|petroleum|petro|station|service)\b/', '', $str);
    // Supprimer caractères spéciaux mais garder les espaces
    $str = preg_replace('/[^a-z0-9\s]/', '', $str);
    // Normaliser les espaces multiples
    $str = preg_replace('/\s+/', ' ', trim($str));
    return $str;
}

// Fonction de recherche de synonyme
function find_synonym($operator, $synonyms) {
    $normalized = normalize_string($operator);
    foreach ($synonyms as $main => $variants) {
        if (strpos($normalized, $main) !== false) {
            return $main;
        }
        foreach ($variants as $variant) {
            if (strpos($normalized, normalize_string($variant)) !== false) {
                return $main;
            }
        }
    }
    return $normalized;
}

// Fonction de similarité améliorée
function similarity_score($str1, $str2, $use_synonyms = false, $synonyms = []) {
    $str1_norm = normalize_string($str1);
    $str2_norm = normalize_string($str2);

    if ($str1_norm === $str2_norm) return 100;
    if (empty($str1_norm) || empty($str2_norm)) return 0;

    // Si utilisation des synonymes
    if ($use_synonyms && !empty($synonyms)) {
        $str1_syn = find_synonym($str1, $synonyms);
        $str2_syn = find_synonym($str2, $synonyms);
        if ($str1_syn === $str2_syn && !empty($str1_syn)) {
            return 95; // Score élevé pour synonymes
        }
    }

    // Vérifier si l'un contient l'autre
    if (strpos($str1_norm, $str2_norm) !== false || strpos($str2_norm, $str1_norm) !== false) {
        return 85;
    }

    // Calculer la similarité par mots
    $words1 = explode(' ', $str1_norm);
    $words2 = explode(' ', $str2_norm);
    $common_words = count(array_intersect($words1, $words2));
    $total_words = max(count($words1), count($words2));
    $word_similarity = $total_words > 0 ? ($common_words / $total_words) * 100 : 0;

    // Levenshtein distance
    $lev = levenshtein(substr($str1_norm, 0, 255), substr($str2_norm, 0, 255));
    $max_len = max(strlen($str1_norm), strlen($str2_norm));
    $lev_similarity = $max_len > 0 ? (1 - $lev / $max_len) * 100 : 0;

    // Retourner le meilleur score
    return round(max($word_similarity, $lev_similarity));
}

// Matching avec garantie d'unicité des coordonnées GPS
$matches = [];
$stats = ['high' => 0, 'medium' => 0, 'low' => 0, 'no_match' => 0];
$used_osm_ids = []; // Tracker les stations OSM déjà attribuées

echo "<div class='progress-bar'><div class='progress-fill' style='width: 0%' id='progress'>0%</div></div>";
echo "<p id='status'>Matching en cours avec garantie d'unicité GPS...</p>";

foreach ($minee_stations as $idx => $minee) {
    $best_match = null;
    $best_score = 0;
    $best_osm_key = null;

    $minee_marketer = $minee['Marketer'] ?? '';
    $minee_ville = $minee['Ville/Localité'] ?? '';
    $minee_region = $minee['Région'] ?? '';

    foreach ($osm_stations as $osm_key => $osm) {
        // Créer une clé unique pour cette station OSM basée sur ses coordonnées
        $osm_unique_key = ($osm['latitude'] ?? '') . ',' . ($osm['longitude'] ?? '');

        // Vérifier si cette station OSM a déjà été attribuée
        if (isset($used_osm_ids[$osm_unique_key])) {
            continue; // Passer à la station OSM suivante
        }

        $osm_operateur = $osm['operateur'] ?? $osm['nom'] ?? '';
        $osm_ville = $osm['ville'] ?? '';
        $osm_region = $osm['region'] ?? '';

        // Score de similarité avec synonymes pour l'opérateur
        $score_operateur = similarity_score($minee_marketer, $osm_operateur, true, $operator_synonyms);
        $score_ville = similarity_score($minee_ville, $osm_ville);
        $score_region = similarity_score($minee_region, $osm_region);

        // NOUVEAU: Priorité à la cohérence géographique !
        // La région ET la ville doivent correspondre avant de considérer l'opérateur
        // Pondération: 50% géographie (région+ville), 50% opérateur
        $score_geo = ($score_region * 0.6) + ($score_ville * 0.4);
        $total_score = ($score_geo * 0.7) + ($score_operateur * 0.3);

        // RÈGLE STRICTE: Éliminer si la région ne correspond pas du tout
        // Une station à Abong_Mbang (Est) ne peut PAS avoir des coordonnées de Bafoussam (Ouest)
        if ($score_region < 70) {
            continue; // Ignorer complètement ce match OSM
        }

        // Bonus important si région exacte (essentiel pour éviter les incohérences)
        if ($score_region >= 90) {
            $total_score += 15;  // Bonus augmenté de 5 à 15
        }

        // Bonus si ville exacte
        if ($score_ville >= 90) {
            $total_score += 10;  // Bonus augmenté de 5 à 10
        }

        // Bonus si ville proche (même si pas exact)
        if ($score_ville >= 70 && $score_region >= 90) {
            $total_score += 5;
        }

        // Seuil minimum relevé pour garantir la qualité
        if ($total_score > $best_score && $total_score > 60) {
            $best_score = $total_score;
            $best_match = $osm;
            $best_osm_key = $osm_unique_key;
        }
    }

    // Si un match a été trouvé, marquer cette station OSM comme utilisée
    if ($best_match && $best_osm_key) {
        $used_osm_ids[$best_osm_key] = true;
    }

    // Classer le match
    $match_quality = 'no_match';
    if ($best_score >= 80) {
        $match_quality = 'high';
        $stats['high']++;
    } elseif ($best_score >= 60) {
        $match_quality = 'medium';
        $stats['medium']++;
    } elseif ($best_score >= 50) {
        $match_quality = 'low';
        $stats['low']++;
    } else {
        $stats['no_match']++;
    }

    $matches[] = [
        'minee' => $minee,
        'osm' => $best_match,
        'score' => $best_score,
        'quality' => $match_quality
    ];
}

echo "<script>
document.getElementById('progress').style.width = '100%';
document.getElementById('progress').textContent = '100%';
document.getElementById('status').textContent = 'Matching terminé!';
</script>";

echo "<div class='success'>";
echo "<h3>✅ Matching terminé!</h3>";
echo "<div style='display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 15px;'>";
echo "<div style='text-align: center; padding: 15px; background: #d4edda; border-radius: 5px;'>";
echo "<strong style='font-size: 2em;'>{$stats['high']}</strong><br>Haute confiance";
echo "</div>";
echo "<div style='text-align: center; padding: 15px; background: #fff3cd; border-radius: 5px;'>";
echo "<strong style='font-size: 2em;'>{$stats['medium']}</strong><br>Moyenne confiance";
echo "</div>";
echo "<div style='text-align: center; padding: 15px; background: #f8d7da; border-radius: 5px;'>";
echo "<strong style='font-size: 2em;'>{$stats['low']}</strong><br>Faible confiance";
echo "</div>";
echo "<div style='text-align: center; padding: 15px; background: #e2e3e5; border-radius: 5px;'>";
echo "<strong style='font-size: 2em;'>{$stats['no_match']}</strong><br>Sans match";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";

// Étape 4: Aperçu
echo "<div class='step'>";
echo "<h2>👀 Étape 4: Aperçu des matchs (20 premiers)</h2>";

echo "<table>";
echo "<tr>";
echo "<th>Score</th>";
echo "<th>Opérateur MINEE</th>";
echo "<th>Ville MINEE</th>";
echo "<th>→</th>";
echo "<th>Opérateur OSM</th>";
echo "<th>Ville OSM</th>";
echo "<th>GPS</th>";
echo "</tr>";

foreach (array_slice($matches, 0, 20) as $match) {
    $class = '';
    $badge = '';

    if ($match['quality'] === 'high') {
        $class = 'match-high';
        $badge = '<span class="badge badge-success">✓ ' . round($match['score']) . '%</span>';
    } elseif ($match['quality'] === 'medium') {
        $class = 'match-medium';
        $badge = '<span class="badge badge-warning">~ ' . round($match['score']) . '%</span>';
    } elseif ($match['quality'] === 'low') {
        $class = 'match-low';
        $badge = '<span class="badge badge-danger">? ' . round($match['score']) . '%</span>';
    } else {
        $badge = '<span class="badge badge-danger">✗ Aucun</span>';
    }

    echo "<tr class='$class'>";
    echo "<td>$badge</td>";
    echo "<td>" . htmlspecialchars($match['minee']['Marketer'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($match['minee']['Ville/Localité'] ?? '') . "</td>";
    echo "<td><strong>→</strong></td>";

    if ($match['osm']) {
        echo "<td>" . htmlspecialchars($match['osm']['operateur'] ?? $match['osm']['nom'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($match['osm']['ville'] ?? '') . "</td>";
        echo "<td><code>" . ($match['osm']['latitude'] ?? '') . ", " . ($match['osm']['longitude'] ?? '') . "</code></td>";
    } else {
        echo "<td colspan='3' style='text-align: center; color: #999;'><em>Pas de match</em></td>";
    }

    echo "</tr>";
}

echo "</table>";
echo "<p><em>... et " . (count($matches) - 20) . " autres lignes</em></p>";
echo "</div>";

// Étape 5: Génération CSV
echo "<div class='step'>";
echo "<h2>📤 Étape 5: Génération du CSV fusionné</h2>";

// Créer le dossier exports s'il n'existe pas
$exports_dir = __DIR__ . '/../../exports/';
if (!is_dir($exports_dir)) {
    mkdir($exports_dir, 0755, true);
}

$output_filename = 'fusion_minee_osm_' . date('Y-m-d_His') . '.csv';
$output_path = $exports_dir . $output_filename;

$fp = fopen($output_path, 'w');
fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

// En-têtes format SGDI
fputcsv($fp, [
    'numero_dossier',
    'type_infrastructure',
    'nom_demandeur',
    'region',
    'ville',
    'quartier',
    'latitude',
    'longitude',
    'date_autorisation',
    'numero_decision',
    'observations',
    'score_matching',
    'source_gps'
], ';');

// Générer les lignes
$sequence_by_region_year = [];

foreach ($matches as $match) {
    $minee = $match['minee'];
    $osm = $match['osm'];

    // Déterminer région et année
    $region = $minee['Région'] ?? 'Inconnu';
    $year = '2020'; // Année par défaut

    // Code région
    $region_codes = [
        'Adamaoua' => 'AD', 'Centre' => 'CE', 'Est' => 'ES',
        'Extrême-Nord' => 'EN', 'Littoral' => 'LT', 'Nord' => 'NO',
        'Nord-Ouest' => 'NW', 'Ouest' => 'OU', 'Sud' => 'SU', 'Sud-Ouest' => 'SW'
    ];
    $region_code = $region_codes[$region] ?? 'XX';

    // Générer numéro séquentiel
    $key = $region_code . '-' . $year;
    if (!isset($sequence_by_region_year[$key])) {
        $sequence_by_region_year[$key] = 1;
    }
    $sequence = str_pad($sequence_by_region_year[$key]++, 3, '0', STR_PAD_LEFT);

    $numero_dossier = "HIST-SS-{$region_code}-{$year}-{$sequence}";

    // GPS depuis OSM ou vide
    $latitude = ($osm && isset($osm['latitude'])) ? $osm['latitude'] : '';
    $longitude = ($osm && isset($osm['longitude'])) ? $osm['longitude'] : '';
    $source_gps = ($osm && $latitude) ? 'OSM (matched)' : 'Non disponible';

    // Observations enrichies
    $observations = [];
    if (!empty($minee['Observation'])) {
        $observations[] = $minee['Observation'];
    }
    if (!empty($minee['Lieu-dit'])) {
        $observations[] = "Lieu-dit: " . $minee['Lieu-dit'];
    }
    if ($match['score'] > 0) {
        $observations[] = "Match OSM: " . round($match['score']) . "%";
    }

    $row = [
        $numero_dossier,
        'Implantation station-service',
        $minee['Marketer'] ?? '',
        $region,
        $minee['Ville/Localité'] ?? '',
        $minee['Quartier'] ?? '',
        $latitude,
        $longitude,
        '01/01/' . $year,
        'N°HIST-' . $region_code . '-' . $year . '-' . $sequence . '/MINEE/SG/DPPG/SDTD',
        implode('; ', $observations),
        round($match['score']),
        $source_gps
    ];

    fputcsv($fp, $row, ';');
}

fclose($fp);

echo "<div class='success'>";
echo "<h3>✅ Fichier CSV généré!</h3>";
echo "<p><strong>Nom:</strong> $output_filename</p>";
echo "<p><strong>Lignes:</strong> " . count($matches) . "</p>";
echo "<p><strong>Avec GPS:</strong> " . ($stats['high'] + $stats['medium'] + $stats['low']) . " (" . round((($stats['high'] + $stats['medium'] + $stats['low']) / count($matches)) * 100) . "%)</p>";
echo "</div>";

echo "<a href='../../exports/$output_filename' class='btn btn-success' download>";
echo "📥 Télécharger le fichier fusionné</a>";

echo "<a href='../../modules/import_historique/' class='btn'>";
echo "📤 Aller au module Import Historique</a>";

echo "<a href='../../rapport_matching_minee_osm.html' class='btn' target='_blank'>";
echo "📊 Voir le rapport détaillé</a>";

echo "</div>";

echo "</div></body></html>";

<?php
/**
 * Convertisseur CSV OSM → Format Import Historique
 * Transforme le CSV filtré OSM en format compatible avec le module import_historique
 */

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Conversion CSV OSM pour Import</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
    .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #17a2b8; }
    .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107; }
    .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; font-size: 0.9em; }
    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
    th { background: #3498db; color: white; position: sticky; top: 0; }
    tr:nth-child(even) { background: #f9f9f9; }
    .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
    .btn:hover { background: #2980b9; }
    .btn-success { background: #27ae60; }
    .btn-success:hover { background: #229954; }
    .step { background: #ecf0f1; padding: 15px; margin: 15px 0; border-left: 4px solid #3498db; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🔄 Conversion CSV OSM → Format Import</h1>";

// Étape 1: Vérifier les fichiers disponibles
echo "<div class='step'>";
echo "<h2>📂 Étape 1: Sélection du fichier CSV OSM</h2>";

$exports_dir = __DIR__ . '/exports/';

if (!is_dir($exports_dir)) {
    echo "<div class='error'>";
    echo "<h3>❌ Dossier exports introuvable</h3>";
    echo "<p>Veuillez d'abord extraire et filtrer les stations OSM.</p>";
    echo "</div>";
    echo "<a href='index.php' class='btn'>🗺️ Retour Module OSM</a>";
    echo "</div></body></html>";
    exit;
}

// Lister les fichiers CSV disponibles
$csv_files = array_merge(
    glob($exports_dir . 'stations_osm_filtrees_*.csv'),
    glob($exports_dir . 'stations_osm_cameroun_*.csv')
);

if (empty($csv_files)) {
    echo "<div class='error'>";
    echo "<h3>❌ Aucun fichier CSV trouvé</h3>";
    echo "<p>Veuillez d'abord extraire les stations OSM.</p>";
    echo "</div>";
    echo "<a href='extract_osm_stations.php' class='btn'>🗺️ Extraire stations OSM</a>";
    echo "<a href='index.php' class='btn'>🏠 Retour Module OSM</a>";
    echo "</div></body></html>";
    exit;
}

// Trier par date (plus récent en premier)
usort($csv_files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

echo "<p><strong>" . count($csv_files) . " fichier(s) CSV disponible(s)</strong></p>";

// Si aucun fichier sélectionné, afficher le formulaire de sélection
if (!isset($_POST['csv_file'])) {
    echo "<form method='POST'>";
    echo "<div class='warning'>";
    echo "<h3>Sélectionnez le fichier CSV à convertir:</h3>";

    foreach ($csv_files as $file) {
        $filename = basename($file);
        $filesize = number_format(filesize($file) / 1024, 2);
        $filedate = date('d/m/Y H:i', filemtime($file));

        // Déterminer le type de fichier
        $file_type = '';
        if (strpos($filename, 'filtrees') !== false) {
            if (strpos($filename, 'excellent') !== false) {
                $file_type = '✅ Filtré Excellent';
            } elseif (strpos($filename, 'excellent+bon') !== false) {
                $file_type = '✅ Filtré Excellent+Bon (Recommandé)';
            } else {
                $file_type = '✅ Filtré';
            }
        } else {
            $file_type = '📊 Extraction complète';
        }

        echo "<label style='display: block; padding: 10px; margin: 10px 0; border: 2px solid #ddd; border-radius: 5px; cursor: pointer;'>";
        echo "<input type='radio' name='csv_file' value='" . htmlspecialchars($filename) . "' required> ";
        echo "<strong>$file_type</strong><br>";
        echo "<small>📁 $filename</small><br>";
        echo "<small>📅 $filedate | 💾 {$filesize} KB</small>";
        echo "</label>";
    }

    echo "</div>";

    echo "<button type='submit' class='btn btn-success'>➡️ Convertir le fichier sélectionné</button>";
    echo "<a href='index.php' class='btn'>❌ Annuler</a>";
    echo "</form>";
    echo "</div>";

} else {
    // Fichier sélectionné, procéder à la conversion
    $selected_file = $exports_dir . basename($_POST['csv_file']);

    if (!file_exists($selected_file)) {
        echo "<div class='error'>❌ Fichier introuvable</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<p>✅ <strong>Fichier sélectionné:</strong> " . basename($selected_file) . "</p>";
    echo "</div>"; // Fin step 1

    // Étape 2: Lecture du CSV OSM
    echo "<div class='step'>";
    echo "<h2>📖 Étape 2: Lecture du CSV OSM</h2>";

    $handle = fopen($selected_file, 'r');

    // Ignorer le BOM UTF-8
    fseek($handle, 3);

    // Lire l'en-tête
    $headers_osm = fgetcsv($handle, 0, ';');

    echo "<p><strong>Colonnes trouvées:</strong> " . implode(', ', $headers_osm) . "</p>";

    // Lire toutes les lignes
    $stations_osm = [];
    while (($data = fgetcsv($handle, 0, ';')) !== false) {
        if (count($data) === count($headers_osm)) {
            $station = array_combine($headers_osm, $data);
            $stations_osm[] = $station;
        }
    }
    fclose($handle);

    echo "<p>✅ <strong>" . count($stations_osm) . " stations</strong> chargées</p>";
    echo "</div>"; // Fin step 2

    // Étape 3: Conversion au format import_historique
    echo "<div class='step'>";
    echo "<h2>🔄 Étape 3: Conversion au format Import Historique</h2>";

    echo "<div class='info'>";
    echo "<h4>📋 Format cible (import_historique):</h4>";
    echo "<p><code>numero_dossier;type_infrastructure;nom_demandeur;region;ville;latitude;longitude;date_autorisation;numero_decision;observations</code></p>";
    echo "</div>";

    // Préparer le nouveau CSV
    $converted_filename = 'import_historique_osm_' . date('Y-m-d_His') . '.csv';
    $converted_path = $exports_dir . $converted_filename;

    $fp = fopen($converted_path, 'w');

    // BOM UTF-8 pour Excel
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

    // En-têtes format import_historique
    $headers_import = [
        'numero_dossier',
        'type_infrastructure',
        'nom_demandeur',
        'region',
        'ville',
        'latitude',
        'longitude',
        'date_autorisation',
        'numero_decision',
        'observations'
    ];

    fputcsv($fp, $headers_import, ';');

    // Conversion ligne par ligne
    $conversion_stats = [
        'total' => count($stations_osm),
        'converted' => 0,
        'skipped' => 0,
        'warnings' => []
    ];

    foreach ($stations_osm as $station) {
        // Mapper les colonnes OSM → Import Historique
        $row = [
            '', // numero_dossier (sera généré par le module import)
            'Implantation station-service', // type_infrastructure
            $station['operateur'] ?: $station['nom'], // nom_demandeur (opérateur prioritaire)
            $station['region'] ?: '', // region
            $station['ville'] ?: '', // ville
            $station['latitude'] ?: '', // latitude
            $station['longitude'] ?: '', // longitude
            $station['date_autorisation'] ?: '', // date_autorisation (à enrichir manuellement)
            $station['numero_autorisation'] ?: '', // numero_decision (à enrichir manuellement)
            'Source: OpenStreetMap (OSM ID: ' . ($station['osm_id'] ?? 'N/A') . ')' // observations
        ];

        // Vérifications de qualité
        if (empty($row[2])) { // nom_demandeur
            $conversion_stats['warnings'][] = "Ligne sans opérateur ni nom: " . ($station['osm_id'] ?? 'inconnu');
        }

        if (empty($row[5]) || empty($row[6])) { // latitude/longitude
            $conversion_stats['warnings'][] = "Ligne sans coordonnées GPS: " . ($station['nom'] ?? 'inconnu');
            $conversion_stats['skipped']++;
            continue; // Sauter les lignes sans GPS
        }

        fputcsv($fp, $row, ';');
        $conversion_stats['converted']++;
    }

    fclose($fp);

    echo "<div class='success'>";
    echo "<h3>✅ Conversion terminée!</h3>";
    echo "<p><strong>Fichier créé:</strong> $converted_filename</p>";
    echo "<p><strong>Stations converties:</strong> {$conversion_stats['converted']} / {$conversion_stats['total']}</p>";
    if ($conversion_stats['skipped'] > 0) {
        echo "<p><strong>⚠️ Stations ignorées:</strong> {$conversion_stats['skipped']} (sans coordonnées GPS)</p>";
    }
    echo "</div>";

    // Afficher les avertissements
    if (!empty($conversion_stats['warnings'])) {
        echo "<div class='warning'>";
        echo "<h4>⚠️ Avertissements (" . count($conversion_stats['warnings']) . "):</h4>";
        echo "<ul>";
        foreach (array_slice($conversion_stats['warnings'], 0, 10) as $warning) {
            echo "<li>" . htmlspecialchars($warning) . "</li>";
        }
        if (count($conversion_stats['warnings']) > 10) {
            echo "<li><em>... et " . (count($conversion_stats['warnings']) - 10) . " autres</em></li>";
        }
        echo "</ul>";
        echo "</div>";
    }

    echo "</div>"; // Fin step 3

    // Étape 4: Aperçu et téléchargement
    echo "<div class='step'>";
    echo "<h2>👀 Étape 4: Aperçu (10 premières lignes)</h2>";

    // Relire le fichier converti pour l'aperçu
    $handle = fopen($converted_path, 'r');
    fseek($handle, 3); // Ignorer BOM
    $headers_preview = fgetcsv($handle, 0, ';');

    echo "<table>";
    echo "<tr>";
    foreach ($headers_preview as $h) {
        echo "<th>" . htmlspecialchars($h) . "</th>";
    }
    echo "</tr>";

    $preview_count = 0;
    while (($data = fgetcsv($handle, 0, ';')) !== false && $preview_count < 10) {
        echo "<tr>";
        foreach ($data as $cell) {
            echo "<td>" . htmlspecialchars($cell) . "</td>";
        }
        echo "</tr>";
        $preview_count++;
    }
    fclose($handle);

    echo "</table>";
    echo "<p><em>... et " . ($conversion_stats['converted'] - 10) . " autres lignes</em></p>";
    echo "</div>"; // Fin step 4

    // Étape 5: Instructions finales
    echo "<div class='info'>";
    echo "<h2>📋 Prochaines étapes</h2>";
    echo "<ol>";
    echo "<li><strong>Téléchargez le fichier converti</strong> (bouton ci-dessous)</li>";
    echo "<li><strong>Ouvrez-le dans Excel/LibreOffice</strong></li>";
    echo "<li><strong>Enrichissez les colonnes manquantes:</strong>";
    echo "<ul>";
    echo "<li><code>date_autorisation</code> - Format: JJ/MM/AAAA (ex: 15/03/2015)</li>";
    echo "<li><code>numero_decision</code> - Format: N°XXXX/MINEE/SG/DPPG/SDTD</li>";
    echo "<li>Vérifiez <code>nom_demandeur</code> (opérateur)</li>";
    echo "<li>Vérifiez <code>region</code> et <code>ville</code></li>";
    echo "</ul></li>";
    echo "<li><strong>Sauvegardez le fichier enrichi</strong></li>";
    echo "<li><strong>Importez via le module Import Historique</strong></li>";
    echo "</ol>";
    echo "</div>";

    // Boutons d'action
    echo "<div style='text-align: center; margin: 30px 0;'>";
    echo "<a href='exports/$converted_filename' class='btn btn-success' download>";
    echo "📥 Télécharger le fichier converti</a>";
    echo "<a href='../../modules/import_historique/' class='btn'>";
    echo "📤 Aller au module Import Historique</a>";
    echo "<a href='convert_for_import.php' class='btn'>";
    echo "🔄 Convertir un autre fichier</a>";
    echo "</div>";
}

echo "</div>"; // step (si on est au début)
echo "</div>"; // container
echo "</body></html>";

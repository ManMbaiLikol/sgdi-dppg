<?php
/**
 * Analyseur de fichier XLSM - Stations Service MINEE
 */

// Inclure Composer autoload si disponible
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Analyse Stations_Service-1.xlsm</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
    .section { background: #ecf0f1; padding: 20px; margin: 20px 0; border-radius: 5px; }
    .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; font-size: 0.9em; }
    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
    th { background: #3498db; color: white; position: sticky; top: 0; }
    tr:nth-child(even) { background: #f9f9f9; }
    code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>📊 Analyse du fichier Stations_Service-1.xlsm</h1>";

$file_path = 'F:/PROJETS DPPG/Stations_Service-1.xlsm';

// Vérifier si le fichier existe
if (!file_exists($file_path)) {
    echo "<div class='error'>";
    echo "<h3>❌ Fichier introuvable</h3>";
    echo "<p><strong>Chemin:</strong> $file_path</p>";
    echo "<p>Vérifiez que le chemin est correct et que vous avez les droits d'accès.</p>";
    echo "</div>";
    echo "</div></body></html>";
    exit;
}

echo "<div class='success'>";
echo "<h3>✅ Fichier trouvé</h3>";
echo "<p><strong>Chemin:</strong> $file_path</p>";
echo "<p><strong>Taille:</strong> " . number_format(filesize($file_path) / 1024, 2) . " KB</p>";
echo "<p><strong>Dernière modification:</strong> " . date('d/m/Y H:i:s', filemtime($file_path)) . "</p>";
echo "</div>";

// Essayer avec PhpSpreadsheet
if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
    echo "<div class='info'>";
    echo "<h3>📚 Utilisation de PhpSpreadsheet</h3>";
    echo "</div>";

    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);

        // Informations générales
        echo "<div class='section'>";
        echo "<h3>📋 Informations générales</h3>";
        echo "<p><strong>Nombre de feuilles:</strong> " . $spreadsheet->getSheetCount() . "</p>";
        echo "<p><strong>Noms des feuilles:</strong></p><ul>";
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            echo "<li>" . htmlspecialchars($sheet->getTitle()) . "</li>";
        }
        echo "</ul></div>";

        // Analyser la première feuille
        $sheet = $spreadsheet->getActiveSheet();
        echo "<div class='section'>";
        echo "<h3>✅ Feuille active: " . htmlspecialchars($sheet->getTitle()) . "</h3>";

        // Lire les en-têtes
        $headers = [];
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        echo "<h4>📑 Colonnes détectées ($highestColumnIndex colonnes):</h4>";
        echo "<table><tr><th>#</th><th>Nom de la colonne</th><th>Type probable</th></tr>";

        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $cellValue = $sheet->getCellByColumnAndRow($col, 1)->getValue();
            if ($cellValue) {
                $headers[$col] = $cellValue;

                // Déterminer le type probable
                $type = 'Texte';
                $lowerValue = strtolower($cellValue);
                if (strpos($lowerValue, 'date') !== false) $type = 'Date';
                elseif (strpos($lowerValue, 'numero') !== false || strpos($lowerValue, 'n°') !== false) $type = 'Numéro';
                elseif (strpos($lowerValue, 'latitude') !== false || strpos($lowerValue, 'longitude') !== false) $type = 'GPS';
                elseif (strpos($lowerValue, 'operateur') !== false || strpos($lowerValue, 'societe') !== false) $type = 'Opérateur';
                elseif (strpos($lowerValue, 'ville') !== false || strpos($lowerValue, 'localite') !== false) $type = 'Localisation';
                elseif (strpos($lowerValue, 'region') !== false) $type = 'Région';

                echo "<tr>";
                echo "<td><strong>$col</strong></td>";
                echo "<td>" . htmlspecialchars($cellValue) . "</td>";
                echo "<td><code>$type</code></td>";
                echo "</tr>";
            }
        }
        echo "</table>";

        // Compter les lignes
        $highestRow = $sheet->getHighestRow();
        echo "<p><strong>📈 Nombre total de lignes:</strong> $highestRow (1 en-tête + " . ($highestRow - 1) . " données)</p>";
        echo "</div>";

        // Aperçu des données
        echo "<div class='section'>";
        echo "<h3>👀 Aperçu des 5 premières lignes de données</h3>";

        for ($row = 2; $row <= min(6, $highestRow); $row++) {
            echo "<h5>Ligne " . ($row - 1) . ":</h5>";
            echo "<table>";
            echo "<tr><th>Colonne</th><th>Valeur</th></tr>";

            foreach ($headers as $col => $header) {
                $cellValue = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                if ($cellValue !== null && $cellValue !== '') {
                    // Formater les dates
                    if ($cellValue instanceof DateTime) {
                        $cellValue = $cellValue->format('d/m/Y');
                    }

                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($header) . "</strong></td>";
                    echo "<td>" . htmlspecialchars($cellValue) . "</td>";
                    echo "</tr>";
                }
            }
            echo "</table>";
        }
        echo "</div>";

        // Statistiques
        echo "<div class='section'>";
        echo "<h3>📊 Statistiques rapides</h3>";

        $stats = [
            'total_lignes' => $highestRow - 1,
            'colonnes' => count($headers),
            'vide' => 0
        ];

        // Vérifier colonnes GPS
        $has_gps = false;
        foreach ($headers as $header) {
            if (stripos($header, 'latitude') !== false || stripos($header, 'longitude') !== false) {
                $has_gps = true;
                break;
            }
        }

        echo "<ul>";
        echo "<li><strong>Total lignes de données:</strong> {$stats['total_lignes']}</li>";
        echo "<li><strong>Nombre de colonnes:</strong> {$stats['colonnes']}</li>";
        echo "<li><strong>Coordonnées GPS:</strong> " . ($has_gps ? "✅ Présentes" : "❌ Absentes") . "</li>";
        echo "</ul>";

        if (!$has_gps) {
            echo "<div class='error'>";
            echo "<h4>⚠️ Pas de coordonnées GPS détectées</h4>";
            echo "<p>Options disponibles:</p>";
            echo "<ol>";
            echo "<li>Matcher avec les données OSM par nom/opérateur/ville</li>";
            echo "<li>Importer sans GPS puis géolocaliser manuellement</li>";
            echo "<li>Utiliser un service de géocodage automatique (Google Maps API)</li>";
            echo "</ol>";
            echo "</div>";
        }

        echo "</div>";

        // Analyse de mapping
        echo "<div class='section'>";
        echo "<h3>🔄 Analyse de mapping vers format SGDI</h3>";
        echo "<p>Correspondances probables avec le format Import Historique:</p>";

        $mapping_suggestions = [];
        foreach ($headers as $col => $header) {
            $lowerHeader = strtolower($header);
            $suggested = null;

            if (stripos($lowerHeader, 'numero') !== false && stripos($lowerHeader, 'decision') === false) {
                $suggested = 'numero_dossier';
            } elseif (stripos($lowerHeader, 'operateur') !== false || stripos($lowerHeader, 'societe') !== false || stripos($lowerHeader, 'demandeur') !== false) {
                $suggested = 'nom_demandeur';
            } elseif (stripos($lowerHeader, 'region') !== false) {
                $suggested = 'region';
            } elseif (stripos($lowerHeader, 'ville') !== false || stripos($lowerHeader, 'localite') !== false) {
                $suggested = 'ville';
            } elseif (stripos($lowerHeader, 'date') !== false && stripos($lowerHeader, 'autorisation') !== false) {
                $suggested = 'date_autorisation';
            } elseif (stripos($lowerHeader, 'decision') !== false || stripos($lowerHeader, 'arrete') !== false) {
                $suggested = 'numero_decision';
            } elseif (stripos($lowerHeader, 'latitude') !== false) {
                $suggested = 'latitude';
            } elseif (stripos($lowerHeader, 'longitude') !== false) {
                $suggested = 'longitude';
            } elseif (stripos($lowerHeader, 'observation') !== false || stripos($lowerHeader, 'remarque') !== false) {
                $suggested = 'observations';
            }

            if ($suggested) {
                $mapping_suggestions[] = [
                    'colonne_source' => $header,
                    'colonne_cible' => $suggested
                ];
            }
        }

        if (!empty($mapping_suggestions)) {
            echo "<table>";
            echo "<tr><th>Colonne XLSM</th><th>→</th><th>Colonne SGDI</th></tr>";
            foreach ($mapping_suggestions as $mapping) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($mapping['colonne_source']) . "</td>";
                echo "<td><strong>→</strong></td>";
                echo "<td><code>" . $mapping['colonne_cible'] . "</code></td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='error'>";
            echo "❌ Aucune correspondance automatique détectée. Un mapping manuel sera nécessaire.";
            echo "</div>";
        }

        echo "</div>";

        // Recommandations
        echo "<div class='info'>";
        echo "<h3>💡 Recommandations</h3>";

        if (!$has_gps) {
            echo "<h4>Option 1: Matching avec OSM (Recommandé)</h4>";
            echo "<ol>";
            echo "<li>Créer un outil de matching automatique</li>";
            echo "<li>Matcher par: Nom opérateur + Ville</li>";
            echo "<li>Compléter les GPS depuis OSM</li>";
            echo "<li>Import dans SGDI avec données complètes</li>";
            echo "</ol>";

            echo "<h4>Option 2: Import sans GPS</h4>";
            echo "<ol>";
            echo "<li>Convertir XLSM → CSV Import Historique</li>";
            echo "<li>Import dans SGDI (sans GPS)</li>";
            echo "<li>Géolocalisation manuelle ultérieure</li>";
            echo "</ol>";
        } else {
            echo "<h4>Import direct</h4>";
            echo "<p>Le fichier contient des coordonnées GPS, vous pouvez procéder à l'import direct après conversion au format SGDI.</p>";
        }

        echo "</div>";

    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "<h3>❌ Erreur lors de l'analyse</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }

} else {
    // Analyse basique sans PhpSpreadsheet
    echo "<div class='error'>";
    echo "<h3>⚠️ PhpSpreadsheet non disponible</h3>";
    echo "<p>Analyse basique du fichier:</p>";
    echo "<ul>";
    echo "<li><strong>Extension:</strong> .xlsm (Excel Macro-Enabled)</li>";
    echo "<li><strong>Taille:</strong> " . number_format(filesize($file_path) / 1024, 2) . " KB</li>";
    echo "</ul>";
    echo "<p>Pour une analyse complète, installez PhpSpreadsheet via Composer:</p>";
    echo "<code>composer require phpoffice/phpspreadsheet</code>";
    echo "</div>";
}

echo "</div></body></html>";

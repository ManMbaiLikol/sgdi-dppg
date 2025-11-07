<?php
/**
 * Import MINEE - Version Corrigée
 * Mapping correct des colonnes du fichier MINEE
 */

require_once 'config/database.php';

set_time_limit(600);
ini_set('memory_limit', '512M');

$FICHIER_MINEE = 'F:/PROJETS DPPG/Stations_Service-1_ANALYSE.csv';
$USER_ADMIN_ID = 1;

// MAPPING CORRECT basé sur l'analyse du fichier
// Index 0 = N° Enregistrement
// Index 1 = Marketer (nom de l'opérateur)
// Index 2 = Région

$DRY_RUN = !isset($_GET['execute']);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Import MINEE - DPPG</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        h1 { color: #2c3e50; border-bottom: 4px solid #28a745; padding-bottom: 15px; }
        h2 { color: #34495e; margin-top: 30px; border-left: 4px solid #3498db; padding-left: 15px; }
        .success { background: #d4edda; border-left: 5px solid #28a745; padding: 20px; margin: 15px 0; border-radius: 4px; }
        .error { background: #f8d7da; border-left: 5px solid #dc3545; padding: 20px; margin: 15px 0; border-radius: 4px; }
        .warning { background: #fff3cd; border-left: 5px solid #ffc107; padding: 20px; margin: 15px 0; border-radius: 4px; }
        .info { background: #e8f4f8; border-left: 5px solid #3498db; padding: 20px; margin: 15px 0; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.9em; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #28a745; color: white; position: sticky; top: 0; }
        tr:nth-child(even) { background: #f8f9fa; }
        tr.skip-row { background: #fff3cd; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 25px 0; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 10px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .stat-card.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card.red { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
        .stat-value { font-size: 3em; font-weight: bold; margin: 10px 0; }
        .stat-label { font-size: 1em; opacity: 0.95; }
        .btn { display: inline-block; padding: 15px 30px; margin: 10px 5px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 1em; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.2); color: white; }
        .btn-primary { background: #3498db; }
        .btn-primary:hover { background: #2980b9; transform: translateY(-2px); }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; transform: translateY(-2px); }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; transform: translateY(-2px); }
        .mode-indicator { position: fixed; top: 20px; right: 20px; padding: 20px 30px; border-radius: 10px; font-weight: bold; font-size: 1.2em; z-index: 1000; box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
        .mode-test { background: #28a745; color: white; }
        .mode-real { background: #dc3545; color: white; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
        .progress { background: #e9ecef; height: 35px; border-radius: 8px; overflow: hidden; margin: 15px 0; }
        .progress-bar { background: linear-gradient(90deg, #28a745, #20c997); height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; transition: width 0.5s; }
    </style>
</head>
<body>

<div class="mode-indicator <?php echo $DRY_RUN ? 'mode-test' : 'mode-real'; ?>">
    <?php echo $DRY_RUN ? '🛡️ MODE TEST' : '⚠️ IMPORT EN COURS'; ?>
</div>

<div class="container">
    <h1>📥 Import Stations MINEE</h1>

    <?php
    // Vérification fichier
    if (!file_exists($FICHIER_MINEE)) {
        echo "<div class='error'>❌ Le fichier n'existe pas : <code>$FICHIER_MINEE</code></div>\n";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='success'>✅ Fichier source : <code>" . basename($FICHIER_MINEE) . "</code></div>\n";

    // Analyse préalable
    echo "<h2>📊 Analyse du Fichier</h2>\n";

    $handle = fopen($FICHIER_MINEE, 'r');
    $headers = fgetcsv($handle, 0, ';');

    $total_lines = 0;
    while (fgetcsv($handle, 0, ';') !== false) {
        $total_lines++;
    }
    fclose($handle);

    echo "<div class='info'>\n";
    echo "<strong>Mapping des colonnes MINEE :</strong><br>\n";
    echo "• Colonne 0 : <code>" . htmlspecialchars($headers[0]) . "</code> → <strong>numero</strong><br>\n";
    echo "• Colonne 1 : <code>" . htmlspecialchars($headers[1]) . "</code> → <strong>nom_demandeur</strong><br>\n";
    echo "• Colonne 2 : <code>" . htmlspecialchars($headers[2]) . "</code> → <strong>region</strong><br>\n";
    echo "• Colonne 3 : <code>" . htmlspecialchars($headers[3]) . "</code> → département (dans adresse)<br>\n";
    echo "• Colonne 4 : <code>" . htmlspecialchars($headers[4]) . "</code> → arrondissement (dans adresse)<br>\n";
    echo "• Colonne 5 : <code>" . htmlspecialchars($headers[5]) . "</code> → <strong>ville</strong><br>\n";
    echo "• Colonne 6 : <code>" . htmlspecialchars($headers[6]) . "</code> → quartier (dans adresse)<br>\n";
    echo "• Colonne 7 : <code>" . htmlspecialchars($headers[7]) . "</code> → lieu-dit (dans adresse)<br>\n";
    echo "• Colonne 8 : <code>" . htmlspecialchars($headers[8]) . "</code> → zone implantation (dans adresse)<br>\n";
    echo "• <strong>adresse_precise</strong> = Combinaison des colonnes 3,4,6,7,8<br>\n";
    echo "• Total de lignes : <strong>$total_lines</strong>\n";
    echo "</div>\n";

    if ($DRY_RUN) {
        // MODE TEST : Afficher 20 premières lignes
        echo "<h2>🧪 Aperçu des Données (20 Premières Lignes)</h2>\n";

        $handle = fopen($FICHIER_MINEE, 'r');
        fgetcsv($handle, 0, ';'); // Skip header

        echo "<table>\n";
        echo "<thead><tr><th>Ligne</th><th>N°</th><th>Nom Opérateur</th><th>Région</th><th>Ville</th><th>Lieu-dit</th><th>Statut</th></tr></thead>\n";
        echo "<tbody>\n";

        $line_num = 0;
        $test_ok = 0;
        $test_skip = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false && $line_num < 20) {
            $line_num++;

            $numero = isset($row[0]) && !empty(trim($row[0])) ? trim($row[0]) : 'HIST-' . str_pad($line_num, 5, '0', STR_PAD_LEFT);
            $nom = isset($row[1]) ? trim($row[1]) : '';
            $region = isset($row[2]) ? trim($row[2]) : '';
            $departement = isset($row[3]) ? trim($row[3]) : '';
            $arrondissement = isset($row[4]) ? trim($row[4]) : '';
            $ville = isset($row[5]) ? trim($row[5]) : '';
            $quartier = isset($row[6]) ? trim($row[6]) : '';
            $lieu_dit = isset($row[7]) ? trim($row[7]) : '';
            $zone_implantation = isset($row[8]) ? trim($row[8]) : '';

            $row_class = '';
            $status = '✅ OK';

            if (empty($nom)) {
                $row_class = 'skip-row';
                $status = '⚠️ Ignoré (nom vide)';
                $test_skip++;
            } else {
                $test_ok++;
            }

            echo "<tr class='$row_class'>\n";
            echo "<td>$line_num</td>\n";
            echo "<td>$numero</td>\n";
            echo "<td>" . htmlspecialchars(substr($nom, 0, 30)) . "</td>\n";
            echo "<td>$region</td>\n";
            echo "<td>$ville</td>\n";
            echo "<td>" . htmlspecialchars(substr($lieu_dit, 0, 20)) . "</td>\n";
            echo "<td>$status</td>\n";
            echo "</tr>\n";
        }

        echo "</tbody>\n";
        echo "</table>\n";

        fclose($handle);

        echo "<div class='stats'>\n";
        echo "<div class='stat-card green'>\n";
        echo "<div class='stat-value'>$test_ok</div>\n";
        echo "<div class='stat-label'>Lignes valides</div>\n";
        echo "</div>\n";
        echo "<div class='stat-card orange'>\n";
        echo "<div class='stat-value'>$test_skip</div>\n";
        echo "<div class='stat-label'>Lignes à ignorer</div>\n";
        echo "</div>\n";
        echo "<div class='stat-card'>\n";
        echo "<div class='stat-value'>$total_lines</div>\n";
        echo "<div class='stat-label'>Total dans le fichier</div>\n";
        echo "</div>\n";
        echo "</div>\n";

        if ($test_ok > 0) {
            echo "<div class='success'>\n";
            echo "✅ <strong>Le fichier est prêt pour l'import !</strong><br>\n";
            echo "Estimation : ~" . round(($test_ok / 20) * $total_lines) . " stations seront importées\n";
            echo "</div>\n";
        }

    } else {
        // MODE RÉEL : Import
        echo "<h2>🚀 Import en Cours...</h2>\n";

        try {
            $pdo->beginTransaction();

            $handle = fopen($FICHIER_MINEE, 'r');
            fgetcsv($handle, 0, ';'); // Skip header

            $imported = 0;
            $skipped = 0;
            $errors = 0;
            $line_num = 0;

            $stmt_insert = $pdo->prepare("
                INSERT INTO dossiers (
                    numero,
                    nom_demandeur,
                    type_infrastructure,
                    sous_type,
                    region,
                    ville,
                    adresse_precise,
                    telephone_demandeur,
                    statut,
                    est_historique,
                    coordonnees_gps,
                    user_id,
                    date_creation
                ) VALUES (?, ?, 'station_service', 'implantation', ?, ?, ?, NULL, 'historique_autorise', 1, NULL, ?, NOW())
            ");

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $line_num++;

                try {
                    $numero = isset($row[0]) && !empty(trim($row[0])) ? trim($row[0]) : 'HIST-' . str_pad($line_num, 5, '0', STR_PAD_LEFT);
                    $nom = isset($row[1]) ? trim($row[1]) : '';
                    $region = isset($row[2]) ? trim($row[2]) : '';
                    $departement = isset($row[3]) ? trim($row[3]) : '';
                    $arrondissement = isset($row[4]) ? trim($row[4]) : '';
                    $ville = isset($row[5]) ? trim($row[5]) : '';
                    $quartier = isset($row[6]) ? trim($row[6]) : '';
                    $lieu_dit = isset($row[7]) ? trim($row[7]) : '';
                    $zone_implantation = isset($row[8]) ? trim($row[8]) : '';

                    // Construire l'adresse complète structurée
                    $adresse_parts = [];
                    if (!empty($lieu_dit)) $adresse_parts[] = "Lieu-dit: $lieu_dit";
                    if (!empty($quartier)) $adresse_parts[] = "Quartier: $quartier";
                    if (!empty($arrondissement)) $adresse_parts[] = "Arrondissement: $arrondissement";
                    if (!empty($departement)) $adresse_parts[] = "Département: $departement";
                    if (!empty($zone_implantation)) $adresse_parts[] = "Zone: $zone_implantation";
                    $adresse = implode(', ', $adresse_parts);

                    // Ignorer les lignes sans nom
                    if (empty($nom)) {
                        $skipped++;
                        continue;
                    }

                    $stmt_insert->execute([
                        $numero,
                        $nom,
                        $region,
                        $ville,
                        $adresse,
                        $USER_ADMIN_ID
                    ]);

                    $imported++;

                    // Afficher progression tous les 100
                    if ($imported % 100 === 0) {
                        $progress = round(($line_num / $total_lines) * 100);
                        echo "<script>console.log('Import: $imported stations...');</script>\n";
                        flush();
                    }

                } catch (Exception $e) {
                    $errors++;
                }
            }

            fclose($handle);
            $pdo->commit();

            echo "<div class='success'>\n";
            echo "<h3>✅ Import Terminé avec Succès !</h3>\n";
            echo "</div>\n";

            echo "<div class='stats'>\n";
            echo "<div class='stat-card green'>\n";
            echo "<div class='stat-value'>$imported</div>\n";
            echo "<div class='stat-label'>Stations importées</div>\n";
            echo "</div>\n";
            echo "<div class='stat-card orange'>\n";
            echo "<div class='stat-value'>$skipped</div>\n";
            echo "<div class='stat-label'>Lignes ignorées</div>\n";
            echo "</div>\n";
            echo "<div class='stat-card red'>\n";
            echo "<div class='stat-value'>$errors</div>\n";
            echo "<div class='stat-label'>Erreurs</div>\n";
            echo "</div>\n";
            echo "</div>\n";

            // Vérification finale
            $count_historiques = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1")->fetchColumn();

            echo "<div class='info'>\n";
            echo "<strong>Vérification :</strong><br>\n";
            echo "Total stations historiques dans la base : <strong>$count_historiques</strong>\n";
            echo "</div>\n";

        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div class='error'>\n";
            echo "<h3>❌ Erreur lors de l'import</h3>\n";
            echo $e->getMessage() . "\n";
            echo "</div>\n";
        }
    }
    ?>

    <h2>🎮 Actions</h2>

    <div style="text-align: center; margin: 30px 0;">
        <?php if ($DRY_RUN): ?>
            <a href="import_minee_fixed.php?execute=1" class="btn btn-success"
               onclick="return confirm('⚠️ CONFIRMER L\'IMPORT ?\n\nEnviron <?php echo $total_lines; ?> lignes seront traitées.\n\nContinuer ?');">
                🚀 LANCER L'IMPORT RÉEL
            </a>
            <a href="debug_import_minee.php" class="btn btn-primary">🔍 Retour au Debug</a>
        <?php else: ?>
            <a href="diagnostic_data_quality.php" class="btn btn-success">📊 Vérifier les Données</a>
            <a href="verify_cleanup_need.php" class="btn btn-primary">🔍 Diagnostic Qualité</a>
        <?php endif; ?>
    </div>

</div>

</body>
</html>

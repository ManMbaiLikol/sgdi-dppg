<?php
/**
 * Import MINEE Complet et Robuste
 * Lit directement le CSV et importe ligne par ligne avec gestion d'erreurs
 */

require_once 'config/database.php';

set_time_limit(0); // Pas de timeout
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 0);

$step = isset($_GET['step']) ? intval($_GET['step']) : 0;

// Configuration
$CSV_LOCAL_PATH = 'F:/PROJETS DPPG/Stations_Service-1_ANALYSE.csv';
$USER_ADMIN_ID = 1;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Import MINEE Complet - DPPG</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        h1 { color: #2c3e50; border-bottom: 4px solid #28a745; padding-bottom: 15px; }
        .success { background: #d4edda; border-left: 5px solid #28a745; padding: 20px; margin: 15px 0; }
        .error { background: #f8d7da; border-left: 5px solid #dc3545; padding: 20px; margin: 15px 0; }
        .info { background: #e8f4f8; border-left: 5px solid #3498db; padding: 20px; margin: 15px 0; }
        .progress { width: 100%; height: 30px; background: #e9ecef; border-radius: 5px; overflow: hidden; margin: 20px 0; }
        .progress-bar { height: 100%; background: linear-gradient(90deg, #28a745 0%, #20c997 100%); transition: width 0.3s; text-align: center; line-height: 30px; color: white; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.9em; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #28a745; color: white; }
        .btn { display: inline-block; padding: 15px 30px; margin: 10px 5px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <h1>🚀 Import MINEE Complet sur Railway</h1>

    <?php
    if ($step == 0) {
        echo "<div class='info'>\n";
        echo "<h2>📋 Méthode d'Import</h2>\n";
        echo "<p>Ce script va importer les stations MINEE de manière COMPLÈTE et ROBUSTE.</p>\n";
        echo "<p><strong>Stratégie :</strong></p>\n";
        echo "<ul>\n";
        echo "<li>✅ Compléter l'import existant (ne pas tout supprimer)</li>\n";
        echo "<li>✅ Import des stations manquantes uniquement</li>\n";
        echo "<li>✅ Gestion d'erreurs détaillée</li>\n";
        echo "<li>✅ Progression en temps réel</li>\n";
        echo "</ul>\n";
        echo "</div>\n";

        try {
            $total_actuel = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1")->fetchColumn();
            $manquant = 1006 - $total_actuel;

            echo "<div class='info'>\n";
            echo "<h3>📊 État Actuel</h3>\n";
            echo "<table>\n";
            echo "<tr><th>Métrique</th><th>Valeur</th></tr>\n";
            echo "<tr><td>Stations historiques actuelles</td><td><strong>$total_actuel</strong></td></tr>\n";
            echo "<tr><td>Attendu</td><td>1006</td></tr>\n";
            echo "<tr><td>Manquant</td><td style='color: red;'><strong>$manquant</strong></td></tr>\n";
            echo "</table>\n";
            echo "</div>\n";

            echo "<div style='text-align: center; margin: 30px 0;'>\n";
            echo "<a href='?step=1' class='btn'>➡️ COMPLÉTER L'IMPORT ($manquant stations)</a>\n";
            echo "</div>\n";

        } catch (PDOException $e) {
            echo "<div class='error'>\n";
            echo "<p>❌ " . htmlspecialchars($e->getMessage()) . "</p>\n";
            echo "</div>\n";
        }
    }

    elseif ($step == 1) {
        echo "<h2>📥 Import en cours...</h2>\n";
        echo "<div id='progress-container'>\n";
        echo "<div class='progress'><div class='progress-bar' id='progress' style='width: 0%;'>0%</div></div>\n";
        echo "<div id='status'>Initialisation...</div>\n";
        echo "</div>\n";

        flush();
        ob_flush();

        try {
            $start_time = microtime(true);

            // Lire le fichier SQL
            echo "<script>document.getElementById('status').innerHTML = '📖 Lecture du fichier SQL...';</script>\n";
            flush();

            if (!file_exists('railway_import_minee.sql')) {
                throw new Exception("Fichier SQL introuvable");
            }

            $sql_content = file_get_contents('railway_import_minee.sql');
            preg_match_all('/INSERT INTO dossiers.*?VALUES\s*\((.*?)\);/s', $sql_content, $matches);

            $total_inserts = count($matches[0]);
            echo "<script>document.getElementById('status').innerHTML = '📊 $total_inserts INSERT trouvés dans le fichier';</script>\n";
            flush();

            // Obtenir les numéros déjà présents
            $existing_numbers = $pdo->query("
                SELECT numero FROM dossiers WHERE est_historique = 1
            ")->fetchAll(PDO::FETCH_COLUMN);

            $existing_set = array_flip($existing_numbers);

            echo "<script>document.getElementById('status').innerHTML = '🔍 " . count($existing_numbers) . " stations déjà présentes';</script>\n";
            flush();

            // Préparer le statement
            $stmt = $pdo->prepare("
                INSERT INTO dossiers (
                    numero, nom_demandeur, type_infrastructure, sous_type,
                    region, ville, adresse_precise, statut, est_historique,
                    coordonnees_gps, user_id, date_creation
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $imported = 0;
            $skipped = 0;
            $errors = 0;
            $error_details = [];

            // Traiter chaque INSERT
            foreach ($matches[0] as $idx => $insert) {
                // Extraire les valeurs
                if (preg_match('/VALUES\s*\((.*?)\);/s', $insert, $values_match)) {
                    $values_str = $values_match[1];

                    // Parser les valeurs (simplifié - assume des valeurs entre quotes)
                    preg_match_all("/'([^']*(?:''[^']*)*)'/", $values_str, $value_matches);
                    $values = $value_matches[1];

                    // Remplacer les doubles quotes
                    $values = array_map(function($v) {
                        return str_replace("''", "'", $v);
                    }, $values);

                    if (count($values) >= 11) {
                        $numero = $values[0];
                        $nom = $values[1];
                        $type_infra = $values[2];
                        $sous_type = $values[3];
                        $region = $values[4];
                        $ville = $values[5];
                        $adresse = $values[6];
                        $statut = $values[7];
                        $est_historique = ($values[8] === 'TRUE' || $values[8] === '1') ? 1 : 0;
                        $gps = ($values[9] === 'NULL' || empty($values[9])) ? null : $values[9];
                        $user_id = !empty($values[10]) ? intval($values[10]) : 1;

                        // Vérifier si déjà existant
                        if (isset($existing_set[$numero])) {
                            $skipped++;
                        } else {
                            try {
                                $stmt->execute([
                                    $numero, $nom, $type_infra, $sous_type,
                                    $region, $ville, $adresse, $statut,
                                    $est_historique, $gps, $user_id
                                ]);
                                $imported++;
                            } catch (PDOException $e) {
                                $errors++;
                                if ($errors <= 10) {
                                    $error_details[] = "Ligne $idx (N°$numero): " . $e->getMessage();
                                }
                            }
                        }

                        // Mise à jour de la progression tous les 50
                        if (($imported + $skipped + $errors) % 50 == 0) {
                            $progress = round((($imported + $skipped + $errors) / $total_inserts) * 100, 1);
                            echo "<script>";
                            echo "document.getElementById('progress').style.width = '$progress%';";
                            echo "document.getElementById('progress').innerHTML = '$progress%';";
                            echo "document.getElementById('status').innerHTML = '⏳ Importé: $imported | Ignoré: $skipped | Erreurs: $errors';";
                            echo "</script>\n";
                            flush();
                            ob_flush();
                        }
                    }
                }
            }

            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);

            // Résultats finaux
            $total_final = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1")->fetchColumn();

            echo "<script>";
            echo "document.getElementById('progress').style.width = '100%';";
            echo "document.getElementById('progress').innerHTML = '100%';";
            echo "</script>\n";

            echo "<div class='success'>\n";
            echo "<h3>🎉 Import terminé !</h3>\n";
            echo "<table>\n";
            echo "<tr><th>Métrique</th><th>Valeur</th></tr>\n";
            echo "<tr><td>Stations importées</td><td style='color: #28a745;'><strong>$imported</strong></td></tr>\n";
            echo "<tr><td>Déjà présentes (ignorées)</td><td>$skipped</td></tr>\n";
            echo "<tr><td>Erreurs</td><td style='color: " . ($errors > 0 ? 'red' : 'green') . ";'>$errors</td></tr>\n";
            echo "<tr><td>Total traité</td><td>$total_inserts</td></tr>\n";
            echo "<tr><td><strong>TOTAL FINAL dans la base</strong></td><td><strong>$total_final</strong></td></tr>\n";
            echo "<tr><td>Durée</td><td>{$duration}s</td></tr>\n";
            echo "</table>\n";
            echo "</div>\n";

            if ($errors > 0 && count($error_details) > 0) {
                echo "<div class='error'>\n";
                echo "<h3>⚠️ Détails des erreurs (10 premières) :</h3>\n";
                echo "<ul>\n";
                foreach ($error_details as $err) {
                    echo "<li><small>" . htmlspecialchars($err) . "</small></li>\n";
                }
                echo "</ul>\n";
                echo "</div>\n";
            }

            if ($total_final >= 1006) {
                echo "<div class='success'>\n";
                echo "<h3>✅ SUCCESS ! Toutes les stations MINEE sont importées !</h3>\n";
                echo "</div>\n";
            } else {
                echo "<div class='error'>\n";
                echo "<h3>⚠️ Il manque encore " . (1006 - $total_final) . " stations</h3>\n";
                echo "<p>Relancez le script pour compléter.</p>\n";
                echo "</div>\n";
            }

            echo "<div style='text-align: center; margin: 30px 0;'>\n";
            echo "<a href='modules/carte/index.php' class='btn'>🗺️ Voir la carte</a>\n";
            echo "<a href='railway_diagnostic_import.php' class='btn' style='background: #3498db;'>📊 Diagnostic</a>\n";
            if ($total_final < 1006) {
                echo "<a href='?step=1' class='btn' style='background: #ffc107; color: #333;'>🔁 Relancer l'import</a>\n";
            }
            echo "</div>\n";

        } catch (Exception $e) {
            echo "<div class='error'>\n";
            echo "<h3>❌ Erreur</h3>\n";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
            echo "</div>\n";
        }
    }
    ?>

</div>

</body>
</html>

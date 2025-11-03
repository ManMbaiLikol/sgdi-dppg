<?php
/**
 * Import MINEE par Batch SQL
 * Découpe le fichier SQL en petits lots et les exécute directement
 */

require_once 'config/database.php';

set_time_limit(0);
ini_set('memory_limit', '1024M');

$step = isset($_GET['step']) ? intval($_GET['step']) : 0;
$batch = isset($_GET['batch']) ? intval($_GET['batch']) : 0;

$BATCH_SIZE = 50; // 50 INSERT par batch

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Import MINEE par Batch - DPPG</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        h1 { color: #2c3e50; border-bottom: 4px solid #28a745; padding-bottom: 15px; }
        .success { background: #d4edda; border-left: 5px solid #28a745; padding: 20px; margin: 15px 0; }
        .error { background: #f8d7da; border-left: 5px solid #dc3545; padding: 20px; margin: 15px 0; }
        .info { background: #e8f4f8; border-left: 5px solid #3498db; padding: 20px; margin: 15px 0; }
        .progress { width: 100%; height: 40px; background: #e9ecef; border-radius: 5px; overflow: hidden; margin: 20px 0; }
        .progress-bar { height: 100%; background: linear-gradient(90deg, #28a745 0%, #20c997 100%); transition: width 0.5s; text-align: center; line-height: 40px; color: white; font-weight: bold; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #28a745; color: white; }
        .btn { display: inline-block; padding: 15px 30px; margin: 10px 5px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; cursor: pointer; border: none; font-size: 16px; }
        .btn:hover { background: #218838; }
        #log { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; margin: 20px 0; }
    </style>
    <script>
        function autoNextBatch(nextBatch, totalBatches) {
            setTimeout(function() {
                window.location.href = '?step=1&batch=' + nextBatch;
            }, 1000);
        }
    </script>
</head>
<body>

<div class="container">
    <h1>🚀 Import MINEE par Batch SQL</h1>

    <?php
    if ($step == 0) {
        echo "<div class='info'>\n";
        echo "<h2>📋 Import par Lots</h2>\n";
        echo "<p>Ce script va importer les stations MINEE par petits lots de $BATCH_SIZE stations.</p>\n";
        echo "<p><strong>Avantages :</strong></p>\n";
        echo "<ul>\n";
        echo "<li>✅ Import progressif sans timeout</li>\n";
        echo "<li>✅ Exécution SQL directe (pas de parsing)</li>\n";
        echo "<li>✅ Gestion d'erreurs par lot</li>\n";
        echo "<li>✅ Peut être interrompu et repris</li>\n";
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
            echo "<tr><td>Nombre de batches nécessaires</td><td>" . ceil($manquant / $BATCH_SIZE) . " batches de $BATCH_SIZE</td></tr>\n";
            echo "</table>\n";
            echo "</div>\n";

            echo "<div style='text-align: center; margin: 30px 0;'>\n";
            echo "<a href='?step=1&batch=0' class='btn'>➡️ DÉMARRER L'IMPORT PAR BATCH</a>\n";
            echo "</div>\n";

        } catch (PDOException $e) {
            echo "<div class='error'>\n";
            echo "<p>❌ " . htmlspecialchars($e->getMessage()) . "</p>\n";
            echo "</div>\n";
        }
    }

    elseif ($step == 1) {
        // Lire le fichier SQL
        if (!file_exists('railway_import_minee.sql')) {
            echo "<div class='error'><p>❌ Fichier SQL introuvable</p></div>\n";
            exit;
        }

        $sql_content = file_get_contents('railway_import_minee.sql');

        // Extraire TOUS les INSERT
        preg_match_all('/(INSERT INTO dossiers[^;]+;)/s', $sql_content, $matches);
        $all_inserts = $matches[1];

        $total_inserts = count($all_inserts);
        $total_batches = ceil($total_inserts / $BATCH_SIZE);

        $start_idx = $batch * $BATCH_SIZE;
        $end_idx = min($start_idx + $BATCH_SIZE, $total_inserts);

        $progress_pct = round(($batch / $total_batches) * 100, 1);

        echo "<h2>📥 Batch " . ($batch + 1) . " / $total_batches</h2>\n";
        echo "<div class='progress'>\n";
        echo "<div class='progress-bar' style='width: $progress_pct%;'>$progress_pct%</div>\n";
        echo "</div>\n";

        echo "<div id='log'>\n";
        echo "🔄 Traitement du batch " . ($batch + 1) . "...\n";
        echo "📊 Insertions $start_idx à $end_idx sur $total_inserts\n\n";

        $imported_this_batch = 0;
        $errors_this_batch = 0;

        try {
            $pdo->beginTransaction();

            for ($i = $start_idx; $i < $end_idx; $i++) {
                $insert = $all_inserts[$i];

                try {
                    // Exécuter l'INSERT directement
                    $pdo->exec($insert);
                    $imported_this_batch++;

                    if ($imported_this_batch % 10 == 0) {
                        echo "✅ Importé $imported_this_batch / " . ($end_idx - $start_idx) . "\n";
                        flush();
                        ob_flush();
                    }
                } catch (PDOException $e) {
                    $errors_this_batch++;

                    // Ignorer les erreurs de doublons
                    if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                        echo "❌ Erreur ligne $i: " . substr($e->getMessage(), 0, 100) . "\n";
                    }
                }
            }

            $pdo->commit();

            echo "\n✅ Batch terminé : $imported_this_batch importées, $errors_this_batch erreurs\n";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo "\n❌ ERREUR BATCH : " . $e->getMessage() . "\n";
        }

        echo "</div>\n";

        // Statistiques actuelles
        $total_now = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1")->fetchColumn();

        echo "<div class='info'>\n";
        echo "<h3>📊 État après ce batch</h3>\n";
        echo "<table>\n";
        echo "<tr><th>Métrique</th><th>Valeur</th></tr>\n";
        echo "<tr><td>Batch actuel</td><td>" . ($batch + 1) . " / $total_batches</td></tr>\n";
        echo "<tr><td>Importées ce batch</td><td>$imported_this_batch</td></tr>\n";
        echo "<tr><td>Erreurs ce batch</td><td>$errors_this_batch</td></tr>\n";
        echo "<tr><td><strong>Total stations historiques</strong></td><td><strong>$total_now</strong> / 1006</td></tr>\n";
        echo "<tr><td>Restant à importer</td><td>" . (1006 - $total_now) . "</td></tr>\n";
        echo "</table>\n";
        echo "</div>\n";

        if ($batch + 1 < $total_batches) {
            $next_batch = $batch + 1;
            echo "<div style='text-align: center; margin: 30px 0;'>\n";
            echo "<a href='?step=1&batch=$next_batch' class='btn'>➡️ BATCH SUIVANT (" . ($next_batch + 1) . "/$total_batches)</a>\n";
            echo "</div>\n";

            // Auto-redirection après 2 secondes
            echo "<script>autoNextBatch($next_batch, $total_batches);</script>\n";
            echo "<p style='text-align: center; color: #666;'>⏳ Redirection automatique dans 1 seconde...</p>\n";

        } else {
            // Terminé
            echo "<div class='success'>\n";
            echo "<h3>🎉 IMPORT TERMINÉ !</h3>\n";
            echo "<p><strong>Total stations historiques : $total_now</strong></p>\n";

            if ($total_now >= 1006) {
                echo "<p>✅ Toutes les 1006 stations MINEE sont importées !</p>\n";
            } else {
                echo "<p>⚠️ Il manque encore " . (1006 - $total_now) . " stations.</p>\n";
                echo "<p>Vous pouvez relancer l'import.</p>\n";
            }
            echo "</div>\n";

            echo "<div style='text-align: center; margin: 30px 0;'>\n";
            echo "<a href='modules/carte/index.php' class='btn'>🗺️ Voir la carte</a>\n";
            echo "<a href='railway_diagnostic_import.php' class='btn' style='background: #3498db;'>📊 Diagnostic</a>\n";
            if ($total_now < 1006) {
                echo "<a href='?step=0' class='btn' style='background: #ffc107; color: #333;'>🔁 Relancer</a>\n";
            }
            echo "</div>\n";
        }
    }
    ?>

</div>

</body>
</html>

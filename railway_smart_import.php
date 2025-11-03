<?php
/**
 * Import MINEE Intelligent sur Railway
 * Sauvegarde les vraies demandes, supprime les historiques, importe MINEE
 */

require_once 'config/database.php';

set_time_limit(600);
ini_set('memory_limit', '512M');

$FICHIER_SQL = 'railway_import_minee.sql';
$step = isset($_GET['step']) ? intval($_GET['step']) : 0;

// Liste des 10 dossiers à conserver
$DOSSIERS_A_CONSERVER = [
    'SS20251024025528',
    'PC20251010224931',
    'PC20251010222326',
    'PC20251010221611',
    'SS20251010220924',
    'PC20251010220305',
    'SS20251010215511',
    'SS20251010214546',
    'SS20251010195450',
    'SS20251010194847'
];

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Import MINEE Intelligent - DPPG</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        h1 { color: #2c3e50; border-bottom: 4px solid #3498db; padding-bottom: 15px; }
        h2 { color: #34495e; margin-top: 30px; border-left: 4px solid #3498db; padding-left: 15px; }
        .success { background: #d4edda; border-left: 5px solid #28a745; padding: 20px; margin: 15px 0; border-radius: 4px; }
        .error { background: #f8d7da; border-left: 5px solid #dc3545; padding: 20px; margin: 15px 0; border-radius: 4px; }
        .warning { background: #fff3cd; border-left: 5px solid #ffc107; padding: 20px; margin: 15px 0; border-radius: 4px; }
        .info { background: #e8f4f8; border-left: 5px solid #3498db; padding: 20px; margin: 15px 0; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.9em; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #3498db; color: white; }
        tr:nth-child(even) { background: #f8f9fa; }
        .keep { background: #d4edda !important; }
        .delete { background: #f8d7da !important; }
        .btn { display: inline-block; padding: 15px 30px; margin: 10px 5px; background: #3498db; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.2); border: none; cursor: pointer; font-size: 1em; }
        .btn:hover { background: #2980b9; transform: translateY(-2px); }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .step { display: inline-block; width: 40px; height: 40px; line-height: 40px; text-align: center; border-radius: 50%; background: #6c757d; color: white; font-weight: bold; margin-right: 10px; }
        .step.active { background: #3498db; }
        .step.done { background: #28a745; }
        .progress-bar { display: flex; justify-content: space-around; margin: 30px 0; }
    </style>
</head>
<body>

<div class="container">
    <h1>🚀 Import MINEE Intelligent sur Railway</h1>

    <div class="progress-bar">
        <div style="text-align: center;">
            <div class="step <?php echo $step >= 1 ? 'done' : ($step == 0 ? 'active' : ''); ?>">1</div>
            <div><strong>Analyse</strong></div>
        </div>
        <div style="text-align: center;">
            <div class="step <?php echo $step >= 2 ? 'done' : ($step == 1 ? 'active' : ''); ?>">2</div>
            <div><strong>Sauvegarde</strong></div>
        </div>
        <div style="text-align: center;">
            <div class="step <?php echo $step >= 3 ? 'done' : ($step == 2 ? 'active' : ''); ?>">3</div>
            <div><strong>Nettoyage</strong></div>
        </div>
        <div style="text-align: center;">
            <div class="step <?php echo $step >= 4 ? 'done' : ($step == 3 ? 'active' : ''); ?>">4</div>
            <div><strong>Import</strong></div>
        </div>
        <div style="text-align: center;">
            <div class="step <?php echo $step == 4 ? 'done' : ''; ?>">✓</div>
            <div><strong>Terminé</strong></div>
        </div>
    </div>

    <?php
    // ÉTAPE 0 : Analyse et vérification
    if ($step == 0) {
        echo "<h2>📊 Étape 1 : Analyse des Dossiers</h2>\n";

        try {
            $total = $pdo->query("SELECT COUNT(*) FROM dossiers")->fetchColumn();

            // Compter les dossiers à conserver
            $placeholders = str_repeat('?,', count($DOSSIERS_A_CONSERVER) - 1) . '?';
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM dossiers WHERE numero IN ($placeholders)");
            $stmt->execute($DOSSIERS_A_CONSERVER);
            $a_conserver = $stmt->fetchColumn();

            $a_supprimer = $total - $a_conserver;

            echo "<div class='info'>\n";
            echo "<h3>📈 État actuel de la base Railway :</h3>\n";
            echo "<table>\n";
            echo "<tr><th>Catégorie</th><th>Nombre</th></tr>\n";
            echo "<tr><td><strong>Total de dossiers</strong></td><td><strong>$total</strong></td></tr>\n";
            echo "<tr class='keep'><td>✅ Vraies demandes à CONSERVER</td><td><strong>$a_conserver</strong></td></tr>\n";
            echo "<tr class='delete'><td>❌ Anciennes stations à SUPPRIMER</td><td><strong>$a_supprimer</strong></td></tr>\n";
            echo "</table>\n";
            echo "</div>\n";

            // Afficher les 10 dossiers à conserver
            if ($a_conserver > 0) {
                echo "<h3>✅ Dossiers qui seront CONSERVÉS :</h3>\n";
                $stmt = $pdo->prepare("
                    SELECT id, numero, nom_demandeur, type_infrastructure, region, ville, statut, date_creation
                    FROM dossiers
                    WHERE numero IN ($placeholders)
                    ORDER BY date_creation DESC
                ");
                $stmt->execute($DOSSIERS_A_CONSERVER);
                $conserves = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "<table>\n";
                echo "<thead><tr><th>N°</th><th>Demandeur</th><th>Type</th><th>Région</th><th>Ville</th><th>Statut</th><th>Date</th></tr></thead>\n";
                echo "<tbody>\n";
                foreach ($conserves as $d) {
                    echo "<tr class='keep'>\n";
                    echo "<td><strong>{$d['numero']}</strong></td>\n";
                    echo "<td>" . htmlspecialchars(substr($d['nom_demandeur'], 0, 25)) . "</td>\n";
                    echo "<td>{$d['type_infrastructure']}</td>\n";
                    echo "<td>{$d['region']}</td>\n";
                    echo "<td>" . htmlspecialchars(substr($d['ville'], 0, 20)) . "</td>\n";
                    echo "<td>{$d['statut']}</td>\n";
                    echo "<td>" . date('Y-m-d', strtotime($d['date_creation'])) . "</td>\n";
                    echo "</tr>\n";
                }
                echo "</tbody></table>\n";
            }

            // Échantillon des dossiers à supprimer
            if ($a_supprimer > 0) {
                echo "<h3>❌ Aperçu des dossiers qui seront SUPPRIMÉS (10 premiers) :</h3>\n";
                $stmt = $pdo->prepare("
                    SELECT id, numero, nom_demandeur, region, ville, statut, date_creation
                    FROM dossiers
                    WHERE numero NOT IN ($placeholders)
                    ORDER BY date_creation DESC
                    LIMIT 10
                ");
                $stmt->execute($DOSSIERS_A_CONSERVER);
                $supprimes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "<table>\n";
                echo "<thead><tr><th>N°</th><th>Demandeur</th><th>Région</th><th>Ville</th><th>Statut</th><th>Date</th></tr></thead>\n";
                echo "<tbody>\n";
                foreach ($supprimes as $d) {
                    echo "<tr class='delete'>\n";
                    echo "<td>{$d['numero']}</td>\n";
                    echo "<td>" . htmlspecialchars(substr($d['nom_demandeur'], 0, 30)) . "</td>\n";
                    echo "<td>{$d['region']}</td>\n";
                    echo "<td>" . htmlspecialchars(substr($d['ville'], 0, 20)) . "</td>\n";
                    echo "<td>{$d['statut']}</td>\n";
                    echo "<td>" . date('Y-m-d', strtotime($d['date_creation'])) . "</td>\n";
                    echo "</tr>\n";
                }
                echo "</tbody></table>\n";
                echo "<p><small>... et " . ($a_supprimer - 10) . " autres dossiers</small></p>\n";
            }

            echo "<div class='warning'>\n";
            echo "<h3>🎯 Plan d'action :</h3>\n";
            echo "<ol>\n";
            echo "<li>✅ <strong>Sauvegarder</strong> les $a_conserver vraies demandes en mémoire</li>\n";
            echo "<li>❌ <strong>Supprimer</strong> les $a_supprimer anciennes stations</li>\n";
            echo "<li>➕ <strong>Importer</strong> les 1006 nouvelles stations MINEE (sans GPS)</li>\n";
            echo "<li>✅ <strong>Restaurer</strong> les $a_conserver vraies demandes</li>\n";
            echo "</ol>\n";
            echo "<p><strong>Résultat final :</strong> $a_conserver vraies demandes + 1006 stations MINEE = " . ($a_conserver + 1006) . " dossiers au total</p>\n";
            echo "</div>\n";

            if ($a_conserver != 10) {
                echo "<div class='error'>\n";
                echo "<h3>⚠️ ATTENTION</h3>\n";
                echo "<p>On devrait trouver exactement 10 dossiers à conserver, mais on en a trouvé <strong>$a_conserver</strong> !</p>\n";
                echo "<p>Vérifiez les numéros de dossiers avant de continuer.</p>\n";
                echo "</div>\n";
            } else {
                echo "<div style='text-align: center; margin: 30px 0;'>\n";
                echo "<a href='?step=1' class='btn btn-success' onclick='return confirm(\"✅ Confirmer : Lancer le processus complet d\\'import ?\");'>➡️ LANCER L\\'IMPORT COMPLET</a>\n";
                echo "<a href='modules/carte/index.php' class='btn' style='background: #6c757d;'>❌ Annuler</a>\n";
                echo "</div>\n";
            }

        } catch (PDOException $e) {
            echo "<div class='error'>\n";
            echo "<h3>❌ Erreur</h3>\n";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
            echo "</div>\n";
        }
    }

    // ÉTAPE 1 : Sauvegarde des vraies demandes
    elseif ($step == 1) {
        echo "<h2>💾 Étape 2 : Sauvegarde des Vraies Demandes</h2>\n";

        try {
            // Créer une table temporaire pour sauvegarder
            $pdo->exec("DROP TABLE IF EXISTS dossiers_temp_backup");
            $pdo->exec("CREATE TABLE dossiers_temp_backup LIKE dossiers");

            // Copier les 10 dossiers à conserver
            $placeholders = str_repeat('?,', count($DOSSIERS_A_CONSERVER) - 1) . '?';
            $stmt = $pdo->prepare("
                INSERT INTO dossiers_temp_backup
                SELECT * FROM dossiers
                WHERE numero IN ($placeholders)
            ");
            $stmt->execute($DOSSIERS_A_CONSERVER);
            $saved = $stmt->rowCount();

            echo "<div class='success'>\n";
            echo "<h3>✅ Sauvegarde réussie !</h3>\n";
            echo "<p><strong>$saved dossiers</strong> ont été sauvegardés dans une table temporaire.</p>\n";
            echo "</div>\n";

            echo "<div style='text-align: center; margin: 30px 0;'>\n";
            echo "<a href='?step=2' class='btn btn-success'>➡️ ÉTAPE 3 : Nettoyer la base</a>\n";
            echo "</div>\n";

        } catch (PDOException $e) {
            echo "<div class='error'>\n";
            echo "<h3>❌ Erreur lors de la sauvegarde</h3>\n";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
            echo "</div>\n";
        }
    }

    // ÉTAPE 2 : Suppression de TOUS les dossiers
    elseif ($step == 2) {
        echo "<h2>🗑️ Étape 3 : Nettoyage Complet de la Base</h2>\n";

        try {
            $avant = $pdo->query("SELECT COUNT(*) FROM dossiers")->fetchColumn();

            // Supprimer TOUS les dossiers
            $stmt = $pdo->prepare("DELETE FROM dossiers");
            $stmt->execute();
            $deleted = $stmt->rowCount();

            $apres = $pdo->query("SELECT COUNT(*) FROM dossiers")->fetchColumn();

            echo "<div class='success'>\n";
            echo "<h3>✅ Nettoyage réussi !</h3>\n";
            echo "<table>\n";
            echo "<tr><th>Avant</th><th>Supprimés</th><th>Après</th></tr>\n";
            echo "<tr><td>$avant</td><td style='color: #dc3545;'>-$deleted</td><td style='color: #28a745;'>$apres</td></tr>\n";
            echo "</table>\n";
            echo "</div>\n";

            if ($apres == 0) {
                echo "<div class='info'>\n";
                echo "<p>✅ La table est maintenant vide et prête pour l'import MINEE.</p>\n";
                echo "</div>\n";

                echo "<div style='text-align: center; margin: 30px 0;'>\n";
                echo "<a href='?step=3' class='btn btn-success'>➡️ ÉTAPE 4 : Importer MINEE</a>\n";
                echo "</div>\n";
            } else {
                echo "<div class='error'>\n";
                echo "<p>⚠️ Il reste encore $apres dossiers. Quelque chose s'est mal passé.</p>\n";
                echo "</div>\n";
            }

        } catch (PDOException $e) {
            echo "<div class='error'>\n";
            echo "<h3>❌ Erreur lors du nettoyage</h3>\n";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
            echo "</div>\n";
        }
    }

    // ÉTAPE 3 : Import MINEE + Restauration
    elseif ($step == 3) {
        echo "<h2>📥 Étape 4 : Import MINEE + Restauration</h2>\n";

        if (!file_exists($FICHIER_SQL)) {
            echo "<div class='error'><p>❌ Fichier SQL introuvable.</p></div>\n";
        } else {
            try {
                $start_time = microtime(true);

                echo "<h3>📥 A. Import des stations MINEE...</h3>\n";

                // Lire et exécuter les INSERT
                $sql_content = file_get_contents($FICHIER_SQL);
                preg_match_all('/INSERT INTO dossiers.*?;/s', $sql_content, $matches);
                $inserts = $matches[0];

                $pdo->beginTransaction();

                $imported = 0;
                foreach ($inserts as $insert) {
                    try {
                        $pdo->exec($insert);
                        $imported++;
                    } catch (PDOException $e) {
                        // Continue même en cas d'erreur
                    }
                }

                $pdo->commit();

                echo "<div class='success'>\n";
                echo "<p>✅ <strong>$imported stations MINEE</strong> importées avec succès !</p>\n";
                echo "</div>\n";

                // Restaurer les vraies demandes
                echo "<h3>♻️ B. Restauration des vraies demandes...</h3>\n";

                $pdo->exec("INSERT INTO dossiers SELECT * FROM dossiers_temp_backup");
                $restored = $pdo->query("SELECT COUNT(*) FROM dossiers_temp_backup")->fetchColumn();

                echo "<div class='success'>\n";
                echo "<p>✅ <strong>$restored vraies demandes</strong> restaurées avec succès !</p>\n";
                echo "</div>\n";

                // Nettoyer la table temporaire
                $pdo->exec("DROP TABLE IF EXISTS dossiers_temp_backup");

                $end_time = microtime(true);
                $duration = round($end_time - $start_time, 2);

                // Vérifications finales
                $total_final = $pdo->query("SELECT COUNT(*) FROM dossiers")->fetchColumn();
                $historiques = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1")->fetchColumn();
                $actifs = $total_final - $historiques;

                echo "<div class='success'>\n";
                echo "<h3>🎉 IMPORT TERMINÉ AVEC SUCCÈS !</h3>\n";
                echo "<table>\n";
                echo "<tr><th>Métrique</th><th>Valeur</th></tr>\n";
                echo "<tr><td>Stations MINEE importées</td><td><strong>$imported</strong></td></tr>\n";
                echo "<tr><td>Vraies demandes restaurées</td><td><strong>$restored</strong></td></tr>\n";
                echo "<tr><td><strong>TOTAL FINAL</strong></td><td><strong>$total_final</strong></td></tr>\n";
                echo "<tr><td>Durée</td><td>{$duration} secondes</td></tr>\n";
                echo "</table>\n";
                echo "</div>\n";

                // Statistiques
                echo "<h3>📊 Statistiques par Région</h3>\n";
                $regions = $pdo->query("
                    SELECT region, COUNT(*) as nb
                    FROM dossiers
                    WHERE est_historique = 1
                    GROUP BY region
                    ORDER BY nb DESC
                    LIMIT 10
                ")->fetchAll(PDO::FETCH_ASSOC);

                echo "<table>\n";
                echo "<thead><tr><th>Région</th><th>Nombre</th></tr></thead>\n";
                echo "<tbody>\n";
                foreach ($regions as $r) {
                    echo "<tr><td>{$r['region']}</td><td>{$r['nb']}</td></tr>\n";
                }
                echo "</tbody></table>\n";

                echo "<div style='text-align: center; margin: 30px 0;'>\n";
                echo "<a href='modules/carte/index.php' class='btn btn-success'>🗺️ Voir la carte</a>\n";
                echo "<a href='verify_import_result.php' class='btn'>📊 Rapport détaillé</a>\n";
                echo "</div>\n";

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo "<div class='error'>\n";
                echo "<h3>❌ Erreur lors de l'import</h3>\n";
                echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
                echo "</div>\n";
            }
        }
    }
    ?>

</div>

</body>
</html>

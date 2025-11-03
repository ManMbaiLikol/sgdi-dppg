<?php
/**
 * Import MINEE sur Railway - Interface d'exécution
 * Script sécurisé pour exécuter l'import en 3 étapes
 */

require_once 'config/database.php';

set_time_limit(600);
ini_set('memory_limit', '512M');

$FICHIER_SQL = 'railway_import_minee.sql';
$step = isset($_GET['step']) ? intval($_GET['step']) : 0;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Import MINEE Railway - DPPG</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        h1 { color: #2c3e50; border-bottom: 4px solid #3498db; padding-bottom: 15px; }
        h2 { color: #34495e; margin-top: 30px; border-left: 4px solid #3498db; padding-left: 15px; }
        .success { background: #d4edda; border-left: 5px solid #28a745; padding: 20px; margin: 15px 0; border-radius: 4px; }
        .error { background: #f8d7da; border-left: 5px solid #dc3545; padding: 20px; margin: 15px 0; border-radius: 4px; }
        .warning { background: #fff3cd; border-left: 5px solid #ffc107; padding: 20px; margin: 15px 0; border-radius: 4px; }
        .info { background: #e8f4f8; border-left: 5px solid #3498db; padding: 20px; margin: 15px 0; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.9em; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #3498db; color: white; position: sticky; top: 0; }
        tr:nth-child(even) { background: #f8f9fa; }
        .btn { display: inline-block; padding: 15px 30px; margin: 10px 5px; background: #3498db; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; transition: all 0.3s; box-shadow: 0 4px 10px rgba(0,0,0,0.2); border: none; cursor: pointer; font-size: 1em; }
        .btn:hover { background: #2980b9; transform: translateY(-2px); }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-warning:hover { background: #e0a800; }
        .step { display: inline-block; width: 40px; height: 40px; line-height: 40px; text-align: center; border-radius: 50%; background: #6c757d; color: white; font-weight: bold; margin-right: 10px; }
        .step.active { background: #3498db; }
        .step.done { background: #28a745; }
        .progress-bar { display: flex; justify-content: space-around; margin: 30px 0; }
    </style>
</head>
<body>

<div class="container">
    <h1>🚀 Import des Stations MINEE sur Railway</h1>

    <div class="progress-bar">
        <div style="text-align: center;">
            <div class="step <?php echo $step >= 1 ? 'done' : ($step == 0 ? 'active' : ''); ?>">1</div>
            <div><strong>Vérification</strong></div>
        </div>
        <div style="text-align: center;">
            <div class="step <?php echo $step >= 2 ? 'done' : ($step == 1 ? 'active' : ''); ?>">2</div>
            <div><strong>Suppression</strong></div>
        </div>
        <div style="text-align: center;">
            <div class="step <?php echo $step >= 3 ? 'done' : ($step == 2 ? 'active' : ''); ?>">3</div>
            <div><strong>Import</strong></div>
        </div>
        <div style="text-align: center;">
            <div class="step <?php echo $step == 3 ? 'done' : ''; ?>">✓</div>
            <div><strong>Terminé</strong></div>
        </div>
    </div>

    <?php
    // ÉTAPE 0 : Vérification initiale
    if ($step == 0) {
        echo "<h2>📊 Étape 1 : Vérification des Données Actuelles</h2>\n";

        try {
            // Compter les dossiers
            $total = $pdo->query("SELECT COUNT(*) FROM dossiers")->fetchColumn();
            $historiques = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1")->fetchColumn();
            $non_historiques = $total - $historiques;

            echo "<div class='info'>\n";
            echo "<h3>📈 État actuel de la base de données Railway :</h3>\n";
            echo "<table>\n";
            echo "<tr><th>Type de Dossier</th><th>Nombre</th></tr>\n";
            echo "<tr><td><strong>Total de dossiers</strong></td><td><strong>$total</strong></td></tr>\n";
            echo "<tr><td>Stations historiques (sera supprimé)</td><td style='color: #dc3545;'><strong>$historiques</strong></td></tr>\n";
            echo "<tr><td>Dossiers actifs (sera conservé)</td><td style='color: #28a745;'><strong>$non_historiques</strong></td></tr>\n";
            echo "</table>\n";
            echo "</div>\n";

            // Échantillon des stations historiques
            if ($historiques > 0) {
                echo "<h3>👁️ Aperçu des stations historiques qui seront supprimées :</h3>\n";
                $sample = $pdo->query("
                    SELECT id, numero, nom_demandeur, region, ville, coordonnees_gps
                    FROM dossiers
                    WHERE est_historique = 1
                    ORDER BY id ASC
                    LIMIT 10
                ")->fetchAll(PDO::FETCH_ASSOC);

                echo "<table>\n";
                echo "<thead><tr><th>ID</th><th>N°</th><th>Opérateur</th><th>Région</th><th>Ville</th><th>GPS</th></tr></thead>\n";
                echo "<tbody>\n";
                foreach ($sample as $row) {
                    $gps = empty($row['coordonnees_gps']) ? '<span style="color: #999;">NULL</span>' : htmlspecialchars($row['coordonnees_gps']);
                    echo "<tr>\n";
                    echo "<td>{$row['id']}</td>\n";
                    echo "<td>{$row['numero']}</td>\n";
                    echo "<td>" . htmlspecialchars($row['nom_demandeur']) . "</td>\n";
                    echo "<td>{$row['region']}</td>\n";
                    echo "<td>{$row['ville']}</td>\n";
                    echo "<td>$gps</td>\n";
                    echo "</tr>\n";
                }
                echo "</tbody>\n";
                echo "</table>\n";
            }

            echo "<div class='warning'>\n";
            echo "<h3>⚠️ ATTENTION - Opération irréversible !</h3>\n";
            echo "<p><strong>La prochaine étape va SUPPRIMER les $historiques stations historiques actuelles.</strong></p>\n";
            echo "<p>Les $non_historiques dossiers actifs (non historiques) seront conservés.</p>\n";
            echo "<p>Êtes-vous sûr de vouloir continuer ?</p>\n";
            echo "</div>\n";

            echo "<div style='text-align: center; margin: 30px 0;'>\n";
            echo "<a href='?step=1' class='btn btn-danger' onclick='return confirm(\"⚠️ CONFIRMER : Supprimer les $historiques stations historiques ?\");'>➡️ ÉTAPE 2 : Supprimer les stations historiques</a>\n";
            echo "<a href='modules/carte/index.php' class='btn btn-warning'>❌ Annuler et retourner à la carte</a>\n";
            echo "</div>\n";

        } catch (PDOException $e) {
            echo "<div class='error'>\n";
            echo "<h3>❌ Erreur de connexion à Railway</h3>\n";
            echo "<p>Message : " . htmlspecialchars($e->getMessage()) . "</p>\n";
            echo "<p>Vérifiez que les variables d'environnement Railway sont correctement configurées.</p>\n";
            echo "</div>\n";
        }
    }

    // ÉTAPE 1 : Suppression des stations historiques
    elseif ($step == 1) {
        echo "<h2>🗑️ Étape 2 : Suppression des Stations Historiques</h2>\n";

        try {
            // Compter avant suppression
            $historiques_avant = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1")->fetchColumn();

            // Supprimer
            $stmt = $pdo->prepare("DELETE FROM dossiers WHERE est_historique = 1");
            $stmt->execute();
            $deleted = $stmt->rowCount();

            // Vérifier après suppression
            $historiques_apres = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1")->fetchColumn();

            echo "<div class='success'>\n";
            echo "<h3>✅ Suppression réussie !</h3>\n";
            echo "<p><strong>$deleted stations historiques ont été supprimées.</strong></p>\n";
            echo "<table>\n";
            echo "<tr><th>Avant</th><th>Supprimés</th><th>Après</th></tr>\n";
            echo "<tr><td>$historiques_avant</td><td style='color: #dc3545;'>-$deleted</td><td style='color: #28a745;'>$historiques_apres</td></tr>\n";
            echo "</table>\n";
            echo "</div>\n";

            if ($historiques_apres == 0) {
                echo "<div class='info'>\n";
                echo "<h3>🎯 Prêt pour l'import</h3>\n";
                echo "<p>La base de données est maintenant vide de stations historiques.</p>\n";
                echo "<p>L'étape suivante va importer <strong>1006 nouvelles stations MINEE</strong> sans coordonnées GPS.</p>\n";
                echo "</div>\n";

                echo "<div style='text-align: center; margin: 30px 0;'>\n";
                echo "<a href='?step=2' class='btn btn-success'>➡️ ÉTAPE 3 : Importer les stations MINEE</a>\n";
                echo "</div>\n";
            } else {
                echo "<div class='error'>\n";
                echo "<h3>⚠️ Attention</h3>\n";
                echo "<p>Il reste encore $historiques_apres stations historiques. Recommencez la suppression.</p>\n";
                echo "</div>\n";
            }

        } catch (PDOException $e) {
            echo "<div class='error'>\n";
            echo "<h3>❌ Erreur lors de la suppression</h3>\n";
            echo "<p>Message : " . htmlspecialchars($e->getMessage()) . "</p>\n";
            echo "</div>\n";
        }
    }

    // ÉTAPE 2 : Import des nouvelles stations
    elseif ($step == 2) {
        echo "<h2>📥 Étape 3 : Import des Stations MINEE</h2>\n";

        if (!file_exists($FICHIER_SQL)) {
            echo "<div class='error'>\n";
            echo "<h3>❌ Fichier SQL introuvable</h3>\n";
            echo "<p>Le fichier <code>$FICHIER_SQL</code> n'existe pas.</p>\n";
            echo "</div>\n";
        } else {
            try {
                $start_time = microtime(true);

                // Lire le fichier SQL
                $sql_content = file_get_contents($FICHIER_SQL);

                // Extraire les INSERT statements
                preg_match_all('/INSERT INTO dossiers.*?;/s', $sql_content, $matches);
                $inserts = $matches[0];

                echo "<div class='info'>\n";
                echo "<h3>📊 Début de l'import...</h3>\n";
                echo "<p>Nombre d'insertions à effectuer : <strong>" . count($inserts) . "</strong></p>\n";
                echo "</div>\n";

                $imported = 0;
                $errors = 0;

                // Désactiver temporairement l'autocommit pour plus de performance
                $pdo->beginTransaction();

                foreach ($inserts as $insert) {
                    try {
                        $pdo->exec($insert);
                        $imported++;

                        // Afficher la progression tous les 100
                        if ($imported % 100 == 0) {
                            echo "<script>console.log('Importé : $imported / " . count($inserts) . "');</script>\n";
                            flush();
                        }
                    } catch (PDOException $e) {
                        $errors++;
                        if ($errors <= 5) {
                            echo "<div class='warning'><small>Erreur ligne $imported : " . htmlspecialchars($e->getMessage()) . "</small></div>\n";
                        }
                    }
                }

                // Valider la transaction
                $pdo->commit();

                $end_time = microtime(true);
                $duration = round($end_time - $start_time, 2);

                // Vérifications finales
                $total_historiques = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1")->fetchColumn();
                $avec_gps = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1 AND coordonnees_gps IS NOT NULL")->fetchColumn();
                $sans_gps = $total_historiques - $avec_gps;

                echo "<div class='success'>\n";
                echo "<h3>🎉 Import terminé avec succès !</h3>\n";
                echo "<table>\n";
                echo "<tr><th>Statistique</th><th>Valeur</th></tr>\n";
                echo "<tr><td>Stations importées</td><td><strong>$imported</strong></td></tr>\n";
                echo "<tr><td>Erreurs</td><td>" . ($errors > 0 ? "<span style='color: #dc3545;'>$errors</span>" : "<span style='color: #28a745;'>0</span>") . "</td></tr>\n";
                echo "<tr><td>Durée</td><td>{$duration} secondes</td></tr>\n";
                echo "<tr><td>Total stations historiques</td><td><strong>$total_historiques</strong></td></tr>\n";
                echo "<tr><td>Avec GPS</td><td>$avec_gps</td></tr>\n";
                echo "<tr><td>Sans GPS</td><td style='color: #28a745;'><strong>$sans_gps</strong></td></tr>\n";
                echo "</table>\n";
                echo "</div>\n";

                if ($sans_gps == $total_historiques) {
                    echo "<div class='success'>\n";
                    echo "<h3>✅ Validation réussie</h3>\n";
                    echo "<p><strong>Parfait !</strong> Toutes les stations historiques ont été importées SANS GPS (comme prévu).</p>\n";
                    echo "<p>La base de données est propre et prête pour l'ajout progressif des coordonnées GPS.</p>\n";
                    echo "</div>\n";
                }

                // Statistiques par région
                echo "<h3>📊 Répartition par Région</h3>\n";
                $regions = $pdo->query("
                    SELECT region, COUNT(*) as nb
                    FROM dossiers
                    WHERE est_historique = 1
                    GROUP BY region
                    ORDER BY nb DESC
                ")->fetchAll(PDO::FETCH_ASSOC);

                echo "<table>\n";
                echo "<thead><tr><th>Région</th><th>Nombre de Stations</th></tr></thead>\n";
                echo "<tbody>\n";
                foreach ($regions as $r) {
                    echo "<tr><td>{$r['region']}</td><td><strong>{$r['nb']}</strong></td></tr>\n";
                }
                echo "</tbody>\n";
                echo "</table>\n";

                echo "<div style='text-align: center; margin: 30px 0;'>\n";
                echo "<a href='modules/carte/index.php' class='btn btn-success'>🗺️ Voir la carte</a>\n";
                echo "<a href='verify_import_result.php' class='btn'>📊 Rapport détaillé</a>\n";
                echo "</div>\n";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>\n";
                echo "<h3>❌ Erreur lors de l'import</h3>\n";
                echo "<p>Message : " . htmlspecialchars($e->getMessage()) . "</p>\n";
                echo "</div>\n";
            }
        }
    }
    ?>

</div>

</body>
</html>

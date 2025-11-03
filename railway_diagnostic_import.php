<?php
/**
 * Diagnostic de l'import MINEE sur Railway
 */

require_once 'config/database.php';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Diagnostic Import - DPPG</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.9em; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #3498db; color: white; }
        tr:nth-child(even) { background: #f8f9fa; }
        .error { background: #f8d7da; border-left: 5px solid #dc3545; padding: 20px; margin: 15px 0; }
        .warning { background: #fff3cd; border-left: 5px solid #ffc107; padding: 20px; margin: 15px 0; }
        .info { background: #e8f4f8; border-left: 5px solid #3498db; padding: 20px; margin: 15px 0; }
        .success { background: #d4edda; border-left: 5px solid #28a745; padding: 20px; margin: 15px 0; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔍 Diagnostic de l'Import MINEE</h1>

    <?php
    try {
        // Statistiques générales
        echo "<h2>📊 Statistiques Générales</h2>\n";

        $total = $pdo->query("SELECT COUNT(*) FROM dossiers")->fetchColumn();
        $historiques = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1")->fetchColumn();
        $non_historiques = $total - $historiques;
        $avec_gps = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE coordonnees_gps IS NOT NULL AND coordonnees_gps != ''")->fetchColumn();
        $sans_gps = $total - $avec_gps;

        echo "<table>\n";
        echo "<tr><th>Catégorie</th><th>Nombre</th><th>Attendu</th><th>Écart</th></tr>\n";
        echo "<tr><td><strong>Total dossiers</strong></td><td>$total</td><td>1016</td><td style='color: " . ($total < 1016 ? 'red' : 'green') . ";'>" . ($total - 1016) . "</td></tr>\n";
        echo "<tr><td>Stations historiques</td><td>$historiques</td><td>1006</td><td style='color: " . ($historiques < 1006 ? 'red' : 'green') . ";'>" . ($historiques - 1006) . "</td></tr>\n";
        echo "<tr><td>Vraies demandes</td><td>$non_historiques</td><td>10</td><td style='color: " . ($non_historiques < 10 ? 'red' : 'green') . ";'>" . ($non_historiques - 10) . "</td></tr>\n";
        echo "<tr><td>Avec GPS</td><td>$avec_gps</td><td>10</td><td>" . ($avec_gps - 10) . "</td></tr>\n";
        echo "<tr><td>Sans GPS</td><td>$sans_gps</td><td>1006</td><td style='color: " . ($sans_gps < 1006 ? 'red' : 'green') . ";'>" . ($sans_gps - 1006) . "</td></tr>\n";
        echo "</table>\n";

        if ($total < 1016) {
            echo "<div class='error'>\n";
            echo "<h3>❌ Problème : Il manque " . (1016 - $total) . " dossiers !</h3>\n";
            echo "<p>L'import n'a pas importé toutes les stations MINEE.</p>\n";
            echo "</div>\n";
        }

        // Analyser les numéros des stations historiques
        echo "<h2>🔢 Analyse des Numéros de Stations Historiques</h2>\n";

        $numeros = $pdo->query("
            SELECT numero, COUNT(*) as nb
            FROM dossiers
            WHERE est_historique = 1
            GROUP BY numero
            HAVING nb > 1
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (count($numeros) > 0) {
            echo "<div class='warning'>\n";
            echo "<h3>⚠️ Doublons détectés !</h3>\n";
            echo "<table>\n";
            echo "<thead><tr><th>Numéro</th><th>Nombre de fois</th></tr></thead>\n";
            echo "<tbody>\n";
            foreach ($numeros as $n) {
                echo "<tr><td>{$n['numero']}</td><td>{$n['nb']}</td></tr>\n";
            }
            echo "</tbody></table>\n";
            echo "</div>\n";
        } else {
            echo "<div class='success'>\n";
            echo "<p>✅ Aucun doublon détecté dans les numéros.</p>\n";
            echo "</div>\n";
        }

        // Vérifier la séquence des numéros
        echo "<h2>📈 Séquence des Numéros Importés</h2>\n";

        $premier = $pdo->query("SELECT MIN(numero) as min FROM dossiers WHERE est_historique = 1 AND numero REGEXP '^[0-9]+$'")->fetchColumn();
        $dernier = $pdo->query("SELECT MAX(numero) as max FROM dossiers WHERE est_historique = 1 AND numero REGEXP '^[0-9]+$'")->fetchColumn();
        $count_numeriques = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1 AND numero REGEXP '^[0-9]+$'")->fetchColumn();

        echo "<table>\n";
        echo "<tr><th>Métrique</th><th>Valeur</th></tr>\n";
        echo "<tr><td>Premier numéro</td><td>$premier</td></tr>\n";
        echo "<tr><td>Dernier numéro</td><td>$dernier</td></tr>\n";
        echo "<tr><td>Stations avec numéro numérique</td><td>$count_numeriques</td></tr>\n";
        echo "<tr><td>Attendu (1 à 1006)</td><td>1006</td></tr>\n";
        echo "</table>\n";

        if ($count_numeriques < 1006) {
            echo "<div class='warning'>\n";
            echo "<h3>⚠️ Stations manquantes</h3>\n";
            echo "<p>Il manque " . (1006 - $count_numeriques) . " stations entre le numéro 1 et 1006.</p>\n";
            echo "<p><strong>Hypothèses possibles :</strong></p>\n";
            echo "<ul>\n";
            echo "<li>🔴 Erreurs SQL lors de l'insertion (contraintes, doublons)</li>\n";
            echo "<li>🔴 Timeout du script PHP</li>\n";
            echo "<li>🔴 Limite mémoire atteinte</li>\n";
            echo "<li>🔴 Le regex n'a pas capturé tous les INSERT</li>\n";
            echo "</ul>\n";
            echo "</div>\n";
        }

        // Top 10 des opérateurs importés
        echo "<h2>🏢 Top 10 Opérateurs Importés</h2>\n";

        $operateurs = $pdo->query("
            SELECT nom_demandeur, COUNT(*) as nb
            FROM dossiers
            WHERE est_historique = 1
            GROUP BY nom_demandeur
            ORDER BY nb DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>\n";
        echo "<thead><tr><th>Opérateur</th><th>Nombre</th></tr></thead>\n";
        echo "<tbody>\n";
        foreach ($operateurs as $op) {
            echo "<tr><td>" . htmlspecialchars($op['nom_demandeur']) . "</td><td>{$op['nb']}</td></tr>\n";
        }
        echo "</tbody></table>\n";

        // Répartition par région
        echo "<h2>🗺️ Répartition par Région</h2>\n";

        $regions = $pdo->query("
            SELECT region, COUNT(*) as nb
            FROM dossiers
            WHERE est_historique = 1
            GROUP BY region
            ORDER BY nb DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>\n";
        echo "<thead><tr><th>Région</th><th>Nombre</th></tr></thead>\n";
        echo "<tbody>\n";
        foreach ($regions as $r) {
            echo "<tr><td>{$r['region']}</td><td>{$r['nb']}</td></tr>\n";
        }
        echo "</tbody></table>\n";

        // Les 10 vraies demandes
        echo "<h2>✅ Les Vraies Demandes Conservées</h2>\n";

        $vraies = $pdo->query("
            SELECT numero, nom_demandeur, type_infrastructure, region, ville, statut, coordonnees_gps
            FROM dossiers
            WHERE est_historique = 0 OR est_historique IS NULL
            ORDER BY date_creation DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>\n";
        echo "<thead><tr><th>N°</th><th>Demandeur</th><th>Type</th><th>Région</th><th>Ville</th><th>Statut</th><th>GPS</th></tr></thead>\n";
        echo "<tbody>\n";
        foreach ($vraies as $v) {
            $gps = empty($v['coordonnees_gps']) ? '<span style="color: #999;">NULL</span>' : '✅';
            echo "<tr>\n";
            echo "<td><strong>{$v['numero']}</strong></td>\n";
            echo "<td>" . htmlspecialchars(substr($v['nom_demandeur'], 0, 25)) . "</td>\n";
            echo "<td>{$v['type_infrastructure']}</td>\n";
            echo "<td>{$v['region']}</td>\n";
            echo "<td>" . htmlspecialchars(substr($v['ville'], 0, 20)) . "</td>\n";
            echo "<td>{$v['statut']}</td>\n";
            echo "<td>$gps</td>\n";
            echo "</tr>\n";
        }
        echo "</tbody></table>\n";

        // Solution proposée
        echo "<h2>💡 Solution Proposée</h2>\n";

        if ($historiques < 1006) {
            echo "<div class='info'>\n";
            echo "<h3>🔧 Réimport nécessaire</h3>\n";
            echo "<p>Il faut réimporter les stations MINEE de manière plus robuste :</p>\n";
            echo "<ol>\n";
            echo "<li>✅ <strong>Option A :</strong> Import ligne par ligne avec gestion des erreurs détaillée</li>\n";
            echo "<li>✅ <strong>Option B :</strong> Découper le fichier SQL en plusieurs parties</li>\n";
            echo "<li>✅ <strong>Option C :</strong> Utiliser un script PHP optimisé avec PDO batch insert</li>\n";
            echo "</ol>\n";
            echo "<p><strong>Je peux créer l'un de ces scripts si vous voulez.</strong></p>\n";
            echo "</div>\n";
        }

    } catch (PDOException $e) {
        echo "<div class='error'>\n";
        echo "<h3>❌ Erreur</h3>\n";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
        echo "</div>\n";
    }
    ?>

</div>

</body>
</html>

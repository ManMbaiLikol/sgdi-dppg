<?php
/**
 * Identification des vraies demandes vs stations historiques
 */

require_once 'config/database.php';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Identification des Dossiers - DPPG</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.85em; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #3498db; color: white; position: sticky; top: 0; }
        tr:nth-child(even) { background: #f8f9fa; }
        .keep { background: #d4edda !important; }
        .delete { background: #f8d7da !important; }
        .info { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔍 Identification des Dossiers sur Railway</h1>

    <?php
    // Tous les dossiers triés par date
    $all = $pdo->query("
        SELECT
            id,
            numero,
            nom_demandeur,
            type_infrastructure,
            region,
            ville,
            statut,
            est_historique,
            coordonnees_gps,
            date_creation,
            user_id
        FROM dossiers
        ORDER BY date_creation DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'>\n";
    echo "<strong>Total de dossiers : " . count($all) . "</strong><br>\n";
    echo "Analysons-les par critères...\n";
    echo "</div>\n";

    // Statistiques par statut
    echo "<h2>📊 Répartition par Statut</h2>\n";
    $by_status = $pdo->query("
        SELECT statut, COUNT(*) as nb
        FROM dossiers
        GROUP BY statut
        ORDER BY nb DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>\n";
    echo "<thead><tr><th>Statut</th><th>Nombre</th></tr></thead>\n";
    echo "<tbody>\n";
    foreach ($by_status as $s) {
        echo "<tr><td><strong>{$s['statut']}</strong></td><td>{$s['nb']}</td></tr>\n";
    }
    echo "</tbody></table>\n";

    // Les 20 plus récents
    echo "<h2>📅 Les 20 Dossiers les Plus Récents (Probablement les VRAIES demandes)</h2>\n";
    echo "<table>\n";
    echo "<thead><tr><th>ID</th><th>Date</th><th>N°</th><th>Opérateur</th><th>Région</th><th>Ville</th><th>Statut</th><th>Historique</th><th>GPS</th><th>User</th></tr></thead>\n";
    echo "<tbody>\n";

    for ($i = 0; $i < min(20, count($all)); $i++) {
        $d = $all[$i];
        $class = $i < 10 ? 'keep' : '';
        $gps = empty($d['coordonnees_gps']) ? 'NULL' : substr($d['coordonnees_gps'], 0, 20);
        $hist = $d['est_historique'] ? 'OUI' : 'NON';

        echo "<tr class='$class'>\n";
        echo "<td>{$d['id']}</td>\n";
        echo "<td>" . date('Y-m-d H:i', strtotime($d['date_creation'])) . "</td>\n";
        echo "<td>{$d['numero']}</td>\n";
        echo "<td>" . htmlspecialchars(substr($d['nom_demandeur'], 0, 30)) . "</td>\n";
        echo "<td>{$d['region']}</td>\n";
        echo "<td>" . htmlspecialchars(substr($d['ville'], 0, 20)) . "</td>\n";
        echo "<td>{$d['statut']}</td>\n";
        echo "<td>$hist</td>\n";
        echo "<td><small>$gps</small></td>\n";
        echo "<td>{$d['user_id']}</td>\n";
        echo "</tr>\n";
    }
    echo "</tbody></table>\n";

    // Les 20 plus anciens
    echo "<h2>📅 Les 20 Dossiers les Plus Anciens (Probablement HISTORIQUES à supprimer)</h2>\n";
    $oldest = array_slice(array_reverse($all), 0, 20);

    echo "<table>\n";
    echo "<thead><tr><th>ID</th><th>Date</th><th>N°</th><th>Opérateur</th><th>Région</th><th>Ville</th><th>Statut</th><th>Historique</th><th>GPS</th></tr></thead>\n";
    echo "<tbody>\n";

    foreach ($oldest as $d) {
        $gps = empty($d['coordonnees_gps']) ? 'NULL' : substr($d['coordonnees_gps'], 0, 20);
        $hist = $d['est_historique'] ? 'OUI' : 'NON';

        echo "<tr class='delete'>\n";
        echo "<td>{$d['id']}</td>\n";
        echo "<td>" . date('Y-m-d H:i', strtotime($d['date_creation'])) . "</td>\n";
        echo "<td>{$d['numero']}</td>\n";
        echo "<td>" . htmlspecialchars(substr($d['nom_demandeur'], 0, 30)) . "</td>\n";
        echo "<td>{$d['region']}</td>\n";
        echo "<td>" . htmlspecialchars(substr($d['ville'], 0, 20)) . "</td>\n";
        echo "<td>{$d['statut']}</td>\n";
        echo "<td>$hist</td>\n";
        echo "<td><small>$gps</small></td>\n";
        echo "</tr>\n";
    }
    echo "</tbody></table>\n";

    // Critères pour identifier les vraies demandes
    echo "<h2>💡 Suggestion : Comment identifier les 10 vraies demandes ?</h2>\n";
    echo "<div class='info'>\n";
    echo "<p><strong>Critères possibles :</strong></p>\n";
    echo "<ul>\n";
    echo "<li>✅ Les 10 plus récentes par date_creation (en vert ci-dessus)</li>\n";
    echo "<li>✅ Statut différent de 'historique_autorise' ?</li>\n";
    echo "<li>✅ User_id différent de 1 (admin) ?</li>\n";
    echo "<li>✅ Coordonnées GPS vides ou remplies ?</li>\n";
    echo "</ul>\n";
    echo "<p><strong>Question :</strong> Pouvez-vous regarder les 10 lignes en VERT ci-dessus et confirmer que ce sont bien vos vraies demandes ?</p>\n";
    echo "</div>\n";

    // Compter par user_id
    echo "<h2>👥 Répartition par User ID</h2>\n";
    $by_user = $pdo->query("
        SELECT user_id, COUNT(*) as nb
        FROM dossiers
        GROUP BY user_id
        ORDER BY nb DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>\n";
    echo "<thead><tr><th>User ID</th><th>Nombre de dossiers</th></tr></thead>\n";
    echo "<tbody>\n";
    foreach ($by_user as $u) {
        echo "<tr><td>{$u['user_id']}</td><td>{$u['nb']}</td></tr>\n";
    }
    echo "</tbody></table>\n";

    ?>

    <h2>🎯 Prochaine Étape</h2>
    <div class='info'>
        <p><strong>Une fois que vous avez identifié les 10 vraies demandes :</strong></p>
        <ol>
            <li>Je vais créer un script qui SAUVEGARDE ces 10 dossiers</li>
            <li>SUPPRIME tous les autres (1101 historiques)</li>
            <li>IMPORTE les 1006 nouvelles stations MINEE</li>
            <li>RESTAURE vos 10 vraies demandes</li>
        </ol>
        <p><strong>Résultat final :</strong> 10 vraies demandes + 1006 stations MINEE = 1016 dossiers au total</p>
    </div>

</div>

</body>
</html>

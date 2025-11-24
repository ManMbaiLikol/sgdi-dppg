<?php
/**
 * Vérifier les données complètes de TRADEX sur Railway
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Données TRADEX</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #252526;
            padding: 30px;
            border-radius: 8px;
        }
        h1 { color: #4ec9b0; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #3e3e42;
        }
        th { background: #2d2d30; color: #dcdcaa; }
        .null { color: #f48771; font-style: italic; }
        .present { color: #4ec9b0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Données TRADEX sur Railway</h1>

<?php
try {
    $stmt = $pdo->query("
        SELECT
            id, numero, nom_demandeur,
            quartier, lieu_dit, arrondissement, departement,
            ville, region,
            latitude, longitude, coordonnees_gps,
            statut
        FROM dossiers
        WHERE nom_demandeur LIKE '%TRADEX%'
        AND statut IN ('autorise', 'historique_autorise')
        LIMIT 5
    ");

    $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($stations) > 0) {
        echo "<h2>✅ Trouvé " . count($stations) . " station(s) TRADEX</h2>";

        foreach ($stations as $i => $station) {
            echo "<h3>Station #" . ($i + 1) . " - {$station['nom_demandeur']} (ID: {$station['id']})</h3>";

            echo "<table>";
            echo "<tr><th>Champ</th><th>Valeur</th></tr>";

            foreach ($station as $key => $value) {
                $display = ($value === null || $value === '')
                    ? "<span class='null'>NULL/VIDE</span>"
                    : "<span class='present'>" . htmlspecialchars($value) . "</span>";

                echo "<tr>";
                echo "<td><strong>{$key}</strong></td>";
                echo "<td>{$display}</td>";
                echo "</tr>";
            }

            echo "</table>";

            // Simuler ce que le popup devrait afficher
            echo "<h4>📍 Ce que le popup devrait afficher :</h4>";
            echo "<div style='background: #2d2d30; padding: 15px; border-radius: 5px;'>";

            $nom_complet = ($station['lieu_dit'] && trim($station['lieu_dit']) !== '')
                ? $station['nom_demandeur'] . ' ' . $station['lieu_dit']
                : $station['nom_demandeur'];

            echo "<strong>" . htmlspecialchars($nom_complet) . "</strong><br>";

            if ($station['quartier'] && $station['ville']) {
                echo "📍 {$station['quartier']}, {$station['ville']}<br>";
            } else if ($station['ville']) {
                echo "📍 {$station['ville']}<br>";
            }

            if ($station['region']) {
                echo "🗺️ {$station['region']}";
            }

            echo "</div>";

            echo "<hr style='margin: 30px 0; border-color: #3e3e42;'>";
        }

    } else {
        echo "<h2>❌ Aucune station TRADEX autorisée trouvée</h2>";
    }

    // Statistiques
    echo "<h2>📊 Statistiques globales</h2>";
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN quartier IS NOT NULL AND TRIM(quartier) != '' THEN 1 ELSE 0 END) as avec_quartier,
            SUM(CASE WHEN lieu_dit IS NOT NULL AND TRIM(lieu_dit) != '' THEN 1 ELSE 0 END) as avec_lieu_dit,
            SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 ELSE 0 END) as avec_gps
        FROM dossiers
        WHERE statut IN ('autorise', 'historique_autorise')
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Métrique</th><th>Valeur</th><th>%</th></tr>";

    $pct_quartier = $stats['total'] > 0 ? round(($stats['avec_quartier'] / $stats['total']) * 100, 1) : 0;
    $pct_lieu_dit = $stats['total'] > 0 ? round(($stats['avec_lieu_dit'] / $stats['total']) * 100, 1) : 0;
    $pct_gps = $stats['total'] > 0 ? round(($stats['avec_gps'] / $stats['total']) * 100, 1) : 0;

    echo "<tr><td>Total infrastructures</td><td>{$stats['total']}</td><td>-</td></tr>";
    echo "<tr><td>Avec quartier renseigné</td><td>{$stats['avec_quartier']}</td><td>{$pct_quartier}%</td></tr>";
    echo "<tr><td>Avec lieu-dit renseigné</td><td>{$stats['avec_lieu_dit']}</td><td>{$pct_lieu_dit}%</td></tr>";
    echo "<tr><td>Avec coordonnées GPS</td><td>{$stats['avec_gps']}</td><td>{$pct_gps}%</td></tr>";
    echo "</table>";

    if ($stats['avec_quartier'] == 0 && $stats['avec_lieu_dit'] == 0) {
        echo "<div style='background: #4a2d2d; padding: 20px; border-radius: 5px; margin-top: 20px;'>";
        echo "<h3 style='color: #f48771;'>⚠️ PROBLÈME IDENTIFIÉ</h3>";
        echo "<p>Les colonnes <code>quartier</code> et <code>lieu_dit</code> existent mais sont <strong>VIDES</strong> pour tous les dossiers !</p>";
        echo "<p>Il faut importer/copier les données depuis votre base de données locale vers Railway.</p>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<h2 style='color: #f48771;'>❌ Erreur</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>

    </div>
</body>
</html>

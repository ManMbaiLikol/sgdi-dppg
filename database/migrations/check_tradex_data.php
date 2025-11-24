<?php
/**
 * Vérifier les données TRADEX sur Railway
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Vérification données TRADEX</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        .container {
            max-width: 900px;
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
        .null { color: #f48771; }
        .present { color: #4ec9b0; }
        pre {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Vérification des données TRADEX</h1>

<?php
try {
    // Rechercher TRADEX
    $stmt = $pdo->query("
        SELECT
            id, numero, nom_demandeur,
            quartier, lieu_dit, arrondissement,
            ville, region, statut,
            operateur_proprietaire,
            latitude, longitude
        FROM dossiers
        WHERE nom_demandeur LIKE '%TRADEX%'
        AND statut IN ('autorise', 'historique_autorise')
        ORDER BY id
        LIMIT 5
    ");

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($results) > 0) {
        echo "<h2>✅ Trouvé " . count($results) . " station(s) TRADEX</h2>";

        foreach ($results as $i => $row) {
            echo "<h3>Station #" . ($i + 1) . " - " . htmlspecialchars($row['nom_demandeur']) . " (ID: {$row['id']})</h3>";
            echo "<table>";
            echo "<tr><th>Champ</th><th>Valeur</th><th>Status</th></tr>";

            foreach ($row as $key => $value) {
                $status = ($value === null || $value === '') ?
                    '<span class="null">❌ NULL/VIDE</span>' :
                    '<span class="present">✓ Présent</span>';

                $displayValue = ($value === null || $value === '') ?
                    '<em>NULL</em>' :
                    htmlspecialchars($value);

                echo "<tr>";
                echo "<td><strong>{$key}</strong></td>";
                echo "<td>{$displayValue}</td>";
                echo "<td>{$status}</td>";
                echo "</tr>";
            }

            echo "</table>";

            // Afficher ce qui devrait s'afficher dans le popup
            echo "<h4>📍 Popup attendu pour cette station :</h4>";
            echo "<pre>";

            $nom_complet = ($row['lieu_dit'] && trim($row['lieu_dit']) !== '')
                ? $row['nom_demandeur'] . ' ' . $row['lieu_dit']
                : $row['nom_demandeur'];

            echo "Nom complet: {$nom_complet}\n";

            if ($row['quartier'] && $row['ville']) {
                echo "Localisation: {$row['quartier']}, {$row['ville']}\n";
            } else if ($row['ville']) {
                echo "Localisation: {$row['ville']}\n";
            }

            if ($row['region']) {
                echo "Région: {$row['region']}\n";
            }

            echo "</pre>";

            echo "<hr style='margin: 30px 0; border-color: #3e3e42;'>";
        }

    } else {
        echo "<h2>⚠️ Aucune station TRADEX trouvée</h2>";
        echo "<p>Vérification des stations autorisées...</p>";

        $stmt = $pdo->query("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN statut IN ('autorise', 'historique_autorise') THEN 1 ELSE 0 END) as autorises
            FROM dossiers
            WHERE nom_demandeur LIKE '%TRADEX%'
        ");
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "<pre>";
        echo "Total TRADEX: {$counts['total']}\n";
        echo "TRADEX autorisés: {$counts['autorises']}\n";
        echo "</pre>";
    }

    // Statistiques globales
    echo "<h2>📊 Statistiques globales</h2>";
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN quartier IS NOT NULL AND quartier != '' THEN 1 ELSE 0 END) as avec_quartier,
            SUM(CASE WHEN lieu_dit IS NOT NULL AND lieu_dit != '' THEN 1 ELSE 0 END) as avec_lieu_dit,
            SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 ELSE 0 END) as avec_gps
        FROM dossiers
        WHERE statut IN ('autorise', 'historique_autorise')
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Métrique</th><th>Valeur</th></tr>";
    echo "<tr><td>Total infrastructures autorisées</td><td>{$stats['total']}</td></tr>";
    echo "<tr><td>Avec quartier renseigné</td><td>{$stats['avec_quartier']}</td></tr>";
    echo "<tr><td>Avec lieu-dit renseigné</td><td>{$stats['avec_lieu_dit']}</td></tr>";
    echo "<tr><td>Avec coordonnées GPS</td><td>{$stats['avec_gps']}</td></tr>";
    echo "</table>";

} catch (Exception $e) {
    echo "<h2 style='color: #f48771;'>❌ Erreur</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>

        <p style="margin-top: 30px; padding: 15px; background: #2d2d30; border-radius: 5px;">
            <strong>🔒 Sécurité :</strong> Supprimez ce fichier après vérification.
        </p>
    </div>
</body>
</html>

<?php
/**
 * Vérifier la structure complète de la table dossiers sur Railway
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Structure table dossiers</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
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
            font-size: 12px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #3e3e42;
        }
        th { background: #2d2d30; color: #dcdcaa; }
        .nullable { color: #4ec9b0; }
        .not-null { color: #f48771; }
        pre {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .section {
            margin: 30px 0;
            padding: 20px;
            background: #2d2d30;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Structure de la table 'dossiers'</h1>

<?php
try {
    // Obtenir la structure complète de la table dossiers
    $stmt = $pdo->query("DESCRIBE dossiers");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='section'>";
    echo "<h2>📊 Colonnes de la table 'dossiers' (" . count($columns) . " colonnes)</h2>";
    echo "<table>";
    echo "<tr>";
    echo "<th>#</th>";
    echo "<th>Nom de la colonne</th>";
    echo "<th>Type</th>";
    echo "<th>NULL?</th>";
    echo "<th>Clé</th>";
    echo "<th>Défaut</th>";
    echo "<th>Extra</th>";
    echo "</tr>";

    $colonnes_importantes = [
        'latitude', 'longitude', 'coordonnees_gps',
        'quartier', 'lieu_dit', 'arrondissement', 'departement',
        'zone_type', 'adresse_precise', 'annee_mise_en_service'
    ];

    foreach ($columns as $i => $col) {
        $is_important = in_array($col['Field'], $colonnes_importantes);
        $style = $is_important ? "background: #2d4a2d;" : "";

        echo "<tr style='$style'>";
        echo "<td>" . ($i + 1) . "</td>";
        echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td class='" . ($col['Null'] === 'YES' ? 'nullable' : 'not-null') . "'>";
        echo $col['Null'];
        echo "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "</div>";

    // Vérifier les colonnes manquantes importantes
    echo "<div class='section'>";
    echo "<h2>⚠️ Vérification des colonnes importantes</h2>";
    echo "<table>";
    echo "<tr><th>Colonne</th><th>Status</th></tr>";

    $existing_columns = array_column($columns, 'Field');

    foreach ($colonnes_importantes as $col) {
        $exists = in_array($col, $existing_columns);
        $status = $exists ?
            "<span style='color: #4ec9b0;'>✓ Présente</span>" :
            "<span style='color: #f48771;'>✗ MANQUANTE</span>";

        echo "<tr>";
        echo "<td><strong>{$col}</strong></td>";
        echo "<td>{$status}</td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "</div>";

    // Vérifier si latitude/longitude existent
    $has_lat_lng = in_array('latitude', $existing_columns) && in_array('longitude', $existing_columns);

    if (!$has_lat_lng) {
        echo "<div class='section' style='background: #4a2d2d;'>";
        echo "<h2>❌ PROBLÈME CRITIQUE : Colonnes GPS manquantes</h2>";
        echo "<p>Les colonnes <code>latitude</code> et <code>longitude</code> n'existent pas !</p>";
        echo "<p>La carte ne peut pas fonctionner sans ces colonnes.</p>";

        // Vérifier si coordonnees_gps existe
        if (in_array('coordonnees_gps', $existing_columns)) {
            echo "<p style='color: #4ec9b0;'>✓ La colonne <code>coordonnees_gps</code> existe (format texte)</p>";
            echo "<p><strong>Solution :</strong> Il faut créer les colonnes latitude/longitude et extraire les données de coordonnees_gps</p>";
        } else {
            echo "<p style='color: #f48771;'>✗ Aucune colonne GPS n'existe !</p>";
        }

        echo "</div>";
    }

    // Compter les dossiers
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dossiers");
    $count = $stmt->fetchColumn();

    echo "<div class='section'>";
    echo "<h2>📈 Statistiques</h2>";
    echo "<p><strong>Nombre total de dossiers :</strong> {$count}</p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='section' style='background: #4a2d2d;'>";
    echo "<h2 style='color: #f48771;'>❌ Erreur</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
?>

        <p style="margin-top: 30px; padding: 15px; background: #2d2d30; border-radius: 5px;">
            <strong>ℹ️ Info :</strong> Les colonnes surlignées en vert sont les colonnes importantes pour la carte et les popups.
        </p>
    </div>
</body>
</html>

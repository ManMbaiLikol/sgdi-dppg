<?php
/**
 * Migration : Ajouter colonnes latitude et longitude
 * + Extraire les données depuis coordonnees_gps
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Migration GPS - Latitude/Longitude</title>
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
        h2 { color: #569cd6; margin-top: 30px; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        .info { color: #9cdcfe; }
        pre {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border-left: 3px solid #4ec9b0;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #2d2d30;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #3e3e42;
        }
        th { background: #2d2d30; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗺️ Migration : Colonnes Latitude/Longitude</h1>
        <p class="info">Ajout des colonnes GPS et extraction des données depuis coordonnees_gps</p>

<?php
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "<h2>📋 Étape 1 : Vérification des colonnes existantes</h2>";
echo "<div class='step'>";

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM dossiers LIKE 'latitude'");
    $lat_exists = $stmt->rowCount() > 0;

    $stmt = $pdo->query("SHOW COLUMNS FROM dossiers LIKE 'longitude'");
    $lng_exists = $stmt->rowCount() > 0;

    echo "<pre>";
    echo "Colonne 'latitude' : " . ($lat_exists ? "<span class='warning'>○ Déjà présente</span>" : "<span class='error'>✗ Manquante</span>") . "\n";
    echo "Colonne 'longitude' : " . ($lng_exists ? "<span class='warning'>○ Déjà présente</span>" : "<span class='error'>✗ Manquante</span>") . "\n";
    echo "</pre>";

    if (!$lat_exists || !$lng_exists) {
        echo "<h2>⚙️ Étape 2 : Ajout des colonnes</h2>";
        echo "<div class='step'>";

        if (!$lat_exists) {
            echo "<p>Ajout de la colonne <code>latitude</code>...</p>";
            $pdo->exec("ALTER TABLE dossiers ADD COLUMN latitude DECIMAL(10, 8) NULL AFTER coordonnees_gps");
            echo "<p class='success'>✓ Colonne latitude ajoutée</p>";
        }

        if (!$lng_exists) {
            echo "<p>Ajout de la colonne <code>longitude</code>...</p>";
            $pdo->exec("ALTER TABLE dossiers ADD COLUMN longitude DECIMAL(11, 8) NULL AFTER latitude");
            echo "<p class='success'>✓ Colonne longitude ajoutée</p>";
        }

        echo "</div>";
    }

} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    exit;
}

echo "</div>";

// Étape 3 : Extraction des coordonnées depuis coordonnees_gps
echo "<h2>🔄 Étape 3 : Extraction des coordonnées GPS</h2>";
echo "<div class='step'>";

try {
    // Compter les dossiers avec coordonnees_gps renseignées
    $stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE coordonnees_gps IS NOT NULL AND coordonnees_gps != ''");
    $total_gps = $stmt->fetchColumn();

    echo "<p class='info'>Dossiers avec coordonnées GPS : {$total_gps}</p>";

    if ($total_gps > 0) {
        // Récupérer tous les dossiers avec coordonnees_gps
        $stmt = $pdo->query("
            SELECT id, numero, coordonnees_gps, latitude, longitude
            FROM dossiers
            WHERE coordonnees_gps IS NOT NULL AND coordonnees_gps != ''
            AND (latitude IS NULL OR longitude IS NULL)
        ");
        $dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updated = 0;
        $failed = 0;
        $examples = [];

        echo "<p>Traitement de " . count($dossiers) . " dossier(s)...</p>";
        echo "<pre>";

        foreach ($dossiers as $dossier) {
            // Formats possibles :
            // "4.577838, 13.689591"
            // "4°34'40.22\"N 13°41'22.53\"E"
            // "4.577838° N, 13.689591° E"

            $coords = $dossier['coordonnees_gps'];
            $lat = null;
            $lng = null;

            // Essayer format décimal simple : "4.577838, 13.689591"
            if (preg_match('/^([0-9.-]+)[,\s]+([0-9.-]+)$/', trim($coords), $matches)) {
                $lat = floatval($matches[1]);
                $lng = floatval($matches[2]);
            }
            // Essayer format avec degrés : "4.577838° N, 13.689591° E"
            elseif (preg_match('/([0-9.]+)°?\s*[NS].*?([0-9.]+)°?\s*[EW]/i', $coords, $matches)) {
                $lat = floatval($matches[1]);
                $lng = floatval($matches[2]);
            }
            // Essayer format DMS : "4°34'40.22"N 13°41'22.53"E"
            elseif (preg_match('/(\d+)°(\d+)\'([\d.]+)"([NS]).*?(\d+)°(\d+)\'([\d.]+)"([EW])/i', $coords, $matches)) {
                // Convertir DMS en décimal
                $lat = floatval($matches[1]) + floatval($matches[2])/60 + floatval($matches[3])/3600;
                if ($matches[4] === 'S') $lat = -$lat;

                $lng = floatval($matches[5]) + floatval($matches[6])/60 + floatval($matches[7])/3600;
                if ($matches[8] === 'W') $lng = -$lng;
            }

            if ($lat !== null && $lng !== null) {
                // Vérifier que les coordonnées sont dans les limites du Cameroun
                // Latitude: 2° - 13° N, Longitude: 8° - 16° E
                if ($lat >= 2 && $lat <= 13 && $lng >= 8 && $lng <= 16) {
                    $update = $pdo->prepare("UPDATE dossiers SET latitude = ?, longitude = ? WHERE id = ?");
                    $update->execute([$lat, $lng, $dossier['id']]);
                    $updated++;

                    if (count($examples) < 5) {
                        $examples[] = [
                            'numero' => $dossier['numero'],
                            'original' => $coords,
                            'lat' => $lat,
                            'lng' => $lng
                        ];
                    }
                } else {
                    echo "⚠ #{$dossier['numero']}: Coordonnées hors limites ($lat, $lng)\n";
                    $failed++;
                }
            } else {
                echo "⚠ #{$dossier['numero']}: Format non reconnu '{$coords}'\n";
                $failed++;
            }

            // Progress
            if (($updated + $failed) % 50 === 0) {
                echo ".";
            }
        }

        echo "\n</pre>";

        echo "<p class='success'>✓ Coordonnées extraites : {$updated}</p>";
        if ($failed > 0) {
            echo "<p class='warning'>⚠ Échecs : {$failed}</p>";
        }

        // Afficher quelques exemples
        if (count($examples) > 0) {
            echo "<h3>📊 Exemples de conversions réussies</h3>";
            echo "<table>";
            echo "<tr><th>Numéro</th><th>Format original</th><th>Latitude</th><th>Longitude</th></tr>";
            foreach ($examples as $ex) {
                echo "<tr>";
                echo "<td>{$ex['numero']}</td>";
                echo "<td>" . htmlspecialchars($ex['original']) . "</td>";
                echo "<td>{$ex['lat']}</td>";
                echo "<td>{$ex['lng']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p class='warning'>⚠ Aucun dossier avec coordonnées GPS à traiter</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";

// Étape 4 : Statistiques finales
echo "<h2>📊 Étape 4 : Statistiques finales</h2>";
echo "<div class='step'>";

try {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 ELSE 0 END) as avec_coords,
            SUM(CASE WHEN coordonnees_gps IS NOT NULL AND coordonnees_gps != '' THEN 1 ELSE 0 END) as avec_gps_text
        FROM dossiers
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Métrique</th><th>Valeur</th></tr>";
    echo "<tr><td>Total dossiers</td><td>{$stats['total']}</td></tr>";
    echo "<tr><td>Avec latitude/longitude</td><td><strong>{$stats['avec_coords']}</strong></td></tr>";
    echo "<tr><td>Avec coordonnees_gps (texte)</td><td>{$stats['avec_gps_text']}</td></tr>";
    echo "</table>";

    $pourcentage = $stats['total'] > 0 ? round(($stats['avec_coords'] / $stats['total']) * 100, 2) : 0;
    echo "<p class='info'>📍 Taux de couverture GPS : <strong>{$pourcentage}%</strong></p>";

} catch (Exception $e) {
    echo "<p class='error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";

// Message final
echo "<hr style='margin: 30px 0; border-color: #3e3e42;'>";
echo "<h2 class='success'>✅ Migration terminée !</h2>";
echo "<p>Les colonnes <code>latitude</code> et <code>longitude</code> sont maintenant disponibles.</p>";
echo "<p>La carte devrait maintenant fonctionner correctement avec les coordonnées GPS.</p>";

echo "<p style='margin-top: 30px; padding: 15px; background: #2d2d30; border-radius: 5px;'>";
echo "<strong>🔒 Sécurité :</strong> Supprimez ce fichier après exécution.";
echo "</p>";
?>

    </div>
</body>
</html>

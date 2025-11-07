<?php
/**
 * Import automatique du fichier fusionné MINEE-OSM
 * Script autonome pour importer les 1101 stations dans SGDI
 */

require_once __DIR__ . '/config/database.php';

set_time_limit(600);
ini_set('memory_limit', '1024M');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Import Fusion MINEE-OSM → SGDI</title>";
echo "<style>
    body { font-family: 'Segoe UI', sans-serif; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .container { max-width: 1400px; margin: 0 auto; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
    h1 { color: #2c3e50; border-bottom: 4px solid #667eea; padding-bottom: 15px; }
    h2 { color: #34495e; margin-top: 40px; padding: 15px; background: linear-gradient(90deg, #667eea, #764ba2); color: white; border-radius: 8px; }
    .step { background: #f8f9fa; padding: 25px; margin: 25px 0; border-left: 5px solid #667eea; border-radius: 0 10px 10px 0; }
    .success { background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 5px solid #28a745; }
    .warning { background: #fff3cd; color: #856404; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 5px solid #ffc107; }
    .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 5px solid #dc3545; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 25px 0; }
    .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 10px; text-align: center; }
    .stat-value { font-size: 2.5em; font-weight: bold; }
    .progress-bar { width: 100%; height: 40px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 15px 0; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); text-align: center; line-height: 40px; color: white; font-weight: bold; transition: width 0.3s; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; font-size: 0.9em; }
    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
    th { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    tr:nth-child(even) { background: #f9f9f9; }
    code { background: #f8f9fa; padding: 3px 8px; border-radius: 4px; font-family: monospace; color: #e83e8c; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>📥 Import Automatique : Fusion MINEE-OSM → SGDI</h1>";
echo "<p style='color: #7f8c8d; font-size: 1.1em;'>Import de 1,101 stations avec 79% de couverture GPS | " . date('d/m/Y H:i') . "</p>";

// ========================================
// ÉTAPE 1: VÉRIFICATIONS PRÉLIMINAIRES
// ========================================
echo "<div class='step'>";
echo "<h2>✅ Étape 1: Vérifications préliminaires</h2>";

// Trouver le dernier fichier de fusion
$fusion_files = glob(__DIR__ . '/exports/fusion_minee_osm_*.csv');
usort($fusion_files, function($a, $b) { return filemtime($b) - filemtime($a); });
$fusion_file = $fusion_files[0] ?? __DIR__ . '/exports/fusion_minee_osm_2025-10-31_105924.csv';

if (!file_exists($fusion_file)) {
    echo "<div class='error'><strong>❌ Erreur:</strong> Fichier fusion introuvable: <code>$fusion_file</code></div>";
    echo "</div></div></body></html>";
    exit;
}

echo "<div class='success'>";
echo "<p>✅ <strong>Fichier fusion:</strong> " . basename($fusion_file) . " (" . number_format(filesize($fusion_file) / 1024, 2) . " KB)</p>";
echo "</div>";

// Vérifier la connexion base de données
try {
    $pdo->query("SELECT 1");
    echo "<div class='success'>";
    echo "<p>✅ <strong>Connexion base de données:</strong> OK</p>";
    echo "</div>";
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<p>❌ <strong>Erreur connexion base:</strong> " . $e->getMessage() . "</p>";
    echo "</div></div></body></html>";
    exit;
}

// Vérifier uniquement la table dossiers et users
$required_tables = ['dossiers', 'users'];
$missing_tables = [];

foreach ($required_tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if (!$stmt->fetch()) {
        $missing_tables[] = $table;
    }
}

if (!empty($missing_tables)) {
    echo "<div class='error'>";
    echo "<p>❌ <strong>Tables manquantes:</strong> " . implode(', ', $missing_tables) . "</p>";
    echo "<p>Veuillez créer la structure de base de données d'abord.</p>";
    echo "</div></div></body></html>";
    exit;
}

echo "<div class='success'>";
echo "<p>✅ <strong>Tables nécessaires :</strong> " . implode(', ', $required_tables) . "</p>";
echo "</div>";
echo "</div>";

// ========================================
// ÉTAPE 2: CHARGEMENT DES DONNÉES
// ========================================
echo "<div class='step'>";
echo "<h2>📥 Étape 2: Chargement du fichier fusion</h2>";

$handle = fopen($fusion_file, 'r');
// Skip BOM UTF-8
fseek($handle, 3);
$headers = fgetcsv($handle, 0, ';');

$data = [];
while (($row = fgetcsv($handle, 0, ';')) !== false) {
    if (count($row) === count($headers)) {
        $data[] = array_combine($headers, $row);
    }
}
fclose($handle);

$total_records = count($data);
$with_gps = count(array_filter($data, function($row) {
    return !empty($row['latitude']) && !empty($row['longitude']);
}));

echo "<div class='stats'>";
echo "<div class='stat-card'>";
echo "<div class='stat-value'>$total_records</div>";
echo "<div>Total enregistrements</div>";
echo "</div>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);'>";
echo "<div class='stat-value'>$with_gps</div>";
echo "<div>Avec GPS (" . round(($with_gps / $total_records) * 100) . "%)</div>";
echo "</div>";
echo "</div>";

echo "</div>";

// ========================================
// ÉTAPE 3: RÉCUPÉRATION DES IDs
// ========================================
echo "<div class='step'>";
echo "<h2>🔍 Étape 3: Récupération des IDs de référence</h2>";

// Pour les dossiers historiques, on utilise directement les valeurs ENUM
// Type: 'station_service', Sous-type: 'implantation', Statut: 'historique_autorise'
$type_infrastructure = 'station_service';
$sous_type = 'implantation';
$statut = 'historique_autorise';

// Récupérer ou créer un utilisateur système pour l'import
$stmt = $pdo->query("SELECT id FROM users WHERE username = 'import_system' LIMIT 1");
$user_id = $stmt->fetchColumn();

if (!$user_id) {
    // Créer un utilisateur système si inexistant
    $pdo->exec("INSERT INTO users (username, email, password, role, nom, prenom, actif)
                VALUES ('import_system', 'import@system.local', '', 'admin', 'Import', 'Système', 0)");
    $user_id = $pdo->lastInsertId();
}

echo "<div class='success'>";
echo "<p>✅ <strong>Type infrastructure:</strong> $type_infrastructure</p>";
echo "<p>✅ <strong>Sous-type:</strong> $sous_type</p>";
echo "<p>✅ <strong>Statut:</strong> $statut</p>";
echo "<p>✅ <strong>User ID système:</strong> $user_id</p>";
echo "</div>";
echo "</div>";

// ========================================
// ÉTAPE 4: IMPORT DES DONNÉES
// ========================================
echo "<div class='step'>";
echo "<h2>💾 Étape 4: Import des dossiers dans SGDI</h2>";

$success_count = 0;
$error_count = 0;
$skip_count = 0;
$errors = [];

echo "<div class='progress-bar'>";
echo "<div class='progress-fill' id='progress' style='width: 0%'>0%</div>";
echo "</div>";

echo "<div id='status'>Importation en cours...</div>";
echo "<br>";

try {
    $pdo->beginTransaction();

    foreach ($data as $index => $row) {
        $numero_dossier = $row['numero_dossier'] ?? '';

        // Vérifier si le dossier existe déjà (utiliser 'numero' et 'id')
        $stmt = $pdo->prepare("SELECT id FROM dossiers WHERE numero = ?");
        $stmt->execute([$numero_dossier]);

        if ($stmt->fetch()) {
            $skip_count++;
            continue;
        }

        // Insérer le dossier (utiliser 'numero' au lieu de 'numero_dossier', et 'statut' au lieu de 'id_statut')
        $sql = "INSERT INTO dossiers (
            numero,
            type_infrastructure,
            sous_type,
            statut,
            nom_demandeur,
            region,
            ville,
            quartier,
            coordonnees_gps,
            date_soumission,
            date_decision,
            numero_decision,
            observations,
            est_historique,
            score_matching_osm,
            source_gps,
            user_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)";

        $gps = null;
        if (!empty($row['latitude']) && !empty($row['longitude'])) {
            $gps = $row['latitude'] . ',' . $row['longitude'];
        }

        // Convertir date format JJ/MM/AAAA vers AAAA-MM-JJ
        $date_autorisation = $row['date_autorisation'] ?? '01/01/2020';
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $date_autorisation, $matches)) {
            $date_sql = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        } else {
            $date_sql = date('Y-m-d');
        }

        $params = [
            $numero_dossier,
            $type_infrastructure,
            $sous_type,
            $statut,
            $row['nom_demandeur'] ?? '',
            $row['region'] ?? '',
            $row['ville'] ?? '',
            $row['quartier'] ?? '',
            $gps,
            $date_sql,
            $date_sql,
            $row['numero_decision'] ?? '',
            $row['observations'] ?? '',
            $row['score_matching'] ?? 0,
            $row['source_gps'] ?? 'Non disponible',
            $user_id
        ];

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $success_count++;
        } catch (PDOException $e) {
            $error_count++;
            $errors[] = [
                'dossier' => $numero_dossier,
                'error' => $e->getMessage()
            ];
        }

        // Mettre à jour la progression
        if (($index + 1) % 50 == 0) {
            $progress = round((($index + 1) / $total_records) * 100);
            echo "<script>
                document.getElementById('progress').style.width = '{$progress}%';
                document.getElementById('progress').textContent = '{$progress}%';
                document.getElementById('status').textContent = 'Importé: " . ($index + 1) . " / $total_records';
            </script>";
            flush();
            ob_flush();
        }
    }

    $pdo->commit();

    echo "<script>
        document.getElementById('progress').style.width = '100%';
        document.getElementById('progress').textContent = '100%';
        document.getElementById('status').textContent = 'Import terminé!';
    </script>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<div class='error'>";
    echo "<h3>❌ Erreur fatale lors de l'import</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div></div></body></html>";
    exit;
}

echo "<div class='stats'>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);'>";
echo "<div class='stat-value'>$success_count</div>";
echo "<div>Importés avec succès</div>";
echo "</div>";

echo "<div class='stat-card' style='background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);'>";
echo "<div class='stat-value'>$skip_count</div>";
echo "<div>Déjà existants (ignorés)</div>";
echo "</div>";

echo "<div class='stat-card' style='background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);'>";
echo "<div class='stat-value'>$error_count</div>";
echo "<div>Erreurs</div>";
echo "</div>";
echo "</div>";

if ($error_count > 0) {
    echo "<div class='warning'>";
    echo "<h3>⚠️ Erreurs rencontrées ($error_count)</h3>";
    echo "<table>";
    echo "<tr><th>Numéro dossier</th><th>Erreur</th></tr>";
    foreach (array_slice($errors, 0, 10) as $error) {
        echo "<tr>";
        echo "<td><code>" . htmlspecialchars($error['dossier']) . "</code></td>";
        echo "<td>" . htmlspecialchars($error['error']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    if (count($errors) > 10) {
        echo "<p><em>... et " . (count($errors) - 10) . " autres erreurs</em></p>";
    }
    echo "</div>";
}

echo "</div>";

// ========================================
// ÉTAPE 5: VÉRIFICATION
// ========================================
echo "<div class='step'>";
echo "<h2>🔍 Étape 5: Vérification de l'import</h2>";

// Compter les dossiers historiques
$stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1");
$total_historiques = $stmt->fetchColumn();

// Compter ceux avec GPS
$stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1 AND coordonnees_gps IS NOT NULL");
$historiques_avec_gps = $stmt->fetchColumn();

echo "<div class='stats'>";
echo "<div class='stat-card'>";
echo "<div class='stat-value'>$total_historiques</div>";
echo "<div>Dossiers historiques en base</div>";
echo "</div>";

echo "<div class='stat-card' style='background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);'>";
echo "<div class='stat-value'>$historiques_avec_gps</div>";
echo "<div>Avec coordonnées GPS</div>";
echo "</div>";

$pct_gps = $total_historiques > 0 ? round(($historiques_avec_gps / $total_historiques) * 100) : 0;
echo "<div class='stat-card' style='background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);'>";
echo "<div class='stat-value'>{$pct_gps}%</div>";
echo "<div>Couverture GPS</div>";
echo "</div>";
echo "</div>";

echo "</div>";

// ========================================
// RÉSUMÉ FINAL
// ========================================
echo "<div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 15px; margin-top: 40px; text-align: center;'>";
echo "<h2 style='color: white; border: none; margin: 0 0 20px 0;'>✅ Import Terminé avec Succès!</h2>";
echo "<p style='font-size: 1.3em; margin: 20px 0;'>";
echo "<strong>$success_count dossiers historiques</strong> ont été importés dans le système SGDI";
echo "</p>";

if ($skip_count > 0) {
    echo "<p style='font-size: 1.1em;'>";
    echo "($skip_count dossiers déjà existants ont été ignorés)";
    echo "</p>";
}

echo "<div style='margin-top: 30px;'>";
echo "<a href='modules/import_historique/dashboard.php' style='display: inline-block; padding: 15px 40px; background: white; color: #667eea; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 1.1em;'>";
echo "📊 Voir le Tableau de Bord";
echo "</a>";
echo "</div>";

echo "</div>";

echo "</div></body></html>";

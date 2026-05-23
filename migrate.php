<?php
/**
 * Script de migration SQL accessible via web
 * URL: https://sgdi-dppg-production.up.railway.app/migrate.php
 *
 * ⚠️ SÉCURISÉ: Nécessite un token secret
 */

// Token de sécurité (à passer en paramètre ?token=...)
define('MIGRATION_TOKEN', 'sgdi-migration-2025-secure-token-' . md5('dppg-minee-cameroun'));

// Vérifier le token
if (!isset($_GET['token']) || $_GET['token'] !== MIGRATION_TOKEN) {
    http_response_code(403);
    die("❌ Accès refusé. Token invalide.\n");
}

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Migration SQL</title>";
echo "<style>body{font-family:monospace;background:#1e1e1e;color:#d4d4d4;padding:20px;}";
echo ".success{color:#4ec9b0;}.error{color:#f48771;}.info{color:#569cd6;}</style></head><body>";

echo "<h1>🔧 EXÉCUTION MIGRATION SQL</h1>";
echo "<pre>";

// Mode ajout colonnes GPS (score_matching_osm, source_gps) requises par modules/admin_gps
if (isset($_GET['action']) && $_GET['action'] === 'add_gps_columns') {
    echo "<h2>Ajout des colonnes GPS manquantes (admin_gps)</h2>\n";

    $columns_to_add = [
        'score_matching_osm' => "ALTER TABLE dossiers ADD COLUMN score_matching_osm INT DEFAULT NULL COMMENT 'Score de matching avec OSM (0-100)'",
        'source_gps'         => "ALTER TABLE dossiers ADD COLUMN source_gps VARCHAR(100) DEFAULT NULL COMMENT 'Source des coordonnees GPS'",
    ];

    try {
        foreach ($columns_to_add as $col => $ddl) {
            $stmt = $pdo->query("SHOW COLUMNS FROM dossiers LIKE " . $pdo->quote($col));
            if ($stmt->rowCount() === 0) {
                $pdo->exec($ddl);
                echo "<span class='success'>OK Colonne '$col' ajoutee</span>\n";
            } else {
                echo "<span class='info'>-- Colonne '$col' deja presente</span>\n";
            }
        }
        echo "\n<span class='success'>Termine. La page modules/admin_gps/index.php devrait fonctionner.</span>\n";
    } catch (PDOException $e) {
        echo "<span class='error'>Erreur: " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo "</pre></body></html>";
    exit(0);
}

// Mode diagnostic: lister les tables existantes
if (isset($_GET['check'])) {
    echo "<h2>📊 TABLES EXISTANTES DANS LA BASE DE DONNÉES</h2>\n";
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<span class='info'>Nombre total de tables: " . count($tables) . "</span>\n\n";

        if (count($tables) === 0) {
            echo "<span class='error'>⚠️ AUCUNE TABLE TROUVÉE!</span>\n";
            echo "La base de données est complètement vide.\n";
            echo "Il faut exécuter toutes les migrations depuis le début.\n";
        } else {
            foreach ($tables as $table) {
                $count_stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
                $row_count = $count_stmt->fetchColumn();
                echo "  <span class='success'>✅ $table</span> ($row_count lignes)\n";
            }
        }
    } catch (PDOException $e) {
        echo "<span class='error'>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo "</pre></body></html>";
    exit(0);
}

// Mode affichage du SQL: voir le contenu exact du fichier
if (isset($_GET['showsql'])) {
    echo "<h2>📄 CONTENU DU FICHIER SQL</h2>\n";
    $sql_file = __DIR__ . '/database/migrations/007_create_decisions_and_registre_simple.sql';
    if (file_exists($sql_file)) {
        $content = file_get_contents($sql_file);
        echo "<span class='info'>Fichier: $sql_file</span>\n";
        echo "<span class='info'>Taille: " . strlen($content) . " octets</span>\n\n";
        echo htmlspecialchars($content);
    } else {
        echo "<span class='error'>❌ Fichier introuvable: $sql_file</span>\n";
    }
    echo "</pre></body></html>";
    exit(0);
}

// Lire le fichier SQL (version simplifiée sans clés étrangères)
$sql_file = __DIR__ . '/database/migrations/007_create_decisions_and_registre_simple.sql';

if (!file_exists($sql_file)) {
    echo "<span class='error'>❌ Erreur: Fichier migration introuvable: $sql_file</span>\n";
    exit(1);
}

$sql = file_get_contents($sql_file);

if ($sql === false) {
    echo "<span class='error'>❌ Erreur: Impossible de lire le fichier migration</span>\n";
    exit(1);
}

echo "<span class='info'>📄 Fichier de migration chargé: 007_create_decisions_and_registre_simple.sql</span>\n";
echo "<span class='info'>📊 Taille: " . strlen($sql) . " octets</span>\n\n";

// Séparer les commandes SQL (en ignorant les commentaires)
$commands = [];
$current_command = '';
$lines = explode("\n", $sql);

foreach ($lines as $line) {
    $line = trim($line);

    // Ignorer les commentaires et lignes vides
    if (empty($line) || strpos($line, '--') === 0) {
        continue;
    }

    $current_command .= $line . ' ';

    // Si la ligne se termine par ;, c'est la fin de la commande
    if (substr($line, -1) === ';') {
        $commands[] = trim($current_command);
        $current_command = '';
    }
}

echo "<span class='info'>🔧 Nombre de commandes SQL à exécuter: " . count($commands) . "</span>\n\n";

// Exécuter chaque commande
$success_count = 0;
$error_count = 0;

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    foreach ($commands as $index => $command) {
        if (empty(trim($command))) {
            continue;
        }

        try {
            // Afficher un résumé de la commande
            $command_preview = substr($command, 0, 80);
            if (strlen($command) > 80) {
                $command_preview .= '...';
            }

            echo "▶ Commande " . ($index + 1) . ": " . htmlspecialchars($command_preview) . "\n";

            $pdo->exec($command);
            echo "<span class='success'>  ✅ Succès</span>\n";
            $success_count++;

        } catch (PDOException $e) {
            // Certaines erreurs sont acceptables (table déjà existante, etc.)
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "<span class='info'>  ⚠️  Déjà existant (ignoré)</span>\n";
                $success_count++;
            } else {
                echo "<span class='error'>  ❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</span>\n";
                $error_count++;
            }
        }
    }

    echo "\n";
    echo "=== RÉSULTAT ===\n";
    echo "<span class='success'>✅ Commandes réussies: $success_count</span>\n";
    echo "<span class='error'>❌ Commandes échouées: $error_count</span>\n";

    if ($error_count === 0) {
        echo "\n<span class='success'>🎉 Migration exécutée avec succès!</span>\n";
    } else {
        echo "\n<span class='error'>⚠️  Migration partiellement réussie avec $error_count erreur(s)</span>\n";
    }

    // Vérifier que les tables ont bien été créées
    echo "\n=== VÉRIFICATION ===\n";

    $tables_to_check = ['decisions_ministerielle', 'registre_public'];

    foreach ($tables_to_check as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetch();

        if ($exists) {
            echo "<span class='success'>✅ Table '$table' créée</span>\n";

            // Compter les colonnes
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll();
            echo "   └─ " . count($columns) . " colonnes\n";
        } else {
            echo "<span class='error'>❌ Table '$table' introuvable</span>\n";
        }
    }

} catch (PDOException $e) {
    echo "\n<span class='error'>❌ Erreur fatale: " . htmlspecialchars($e->getMessage()) . "</span>\n";
    exit(1);
}

echo "\n<span class='success'>✅ Terminé!</span>\n";
echo "</pre></body></html>";

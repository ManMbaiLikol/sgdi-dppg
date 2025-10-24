<?php
/**
 * Import d'un dump SQL via PDO (sans mysql.exe)
 * Compatible Windows
 *
 * Usage: php sync/import_via_pdo.php fichier.sql
 */

$backup_file = $argv[1] ?? null;

if (!$backup_file || !file_exists($backup_file)) {
    die("❌ ERREUR: Fichier introuvable: $backup_file\n" .
        "Usage: php sync/import_via_pdo.php fichier.sql\n\n");
}

require_once __DIR__ . '/../config/database.php';

echo "Import du fichier: $backup_file\n";
echo "Taille: " . formatBytes(filesize($backup_file)) . "\n\n";

try {
    // Connexion à la base
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => 0
        ]
    );

    // Lire le fichier SQL
    echo "Lecture du fichier SQL...\n";
    $sql = file_get_contents($backup_file);

    if (empty($sql)) {
        die("❌ Fichier SQL vide!\n");
    }

    // Désactiver temporairement les foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    echo "Parsing et exécution des requêtes SQL...\n";

    // Parser le fichier SQL ligne par ligne
    $lines = explode("\n", $sql);
    $query = '';
    $in_multiline = false;
    $executed = 0;
    $skipped = 0;

    foreach ($lines as $line_num => $line) {
        $line = trim($line);

        // Ignorer les commentaires et lignes vides
        if (empty($line) ||
            strpos($line, '--') === 0 ||
            strpos($line, '/*') === 0 ||
            strpos($line, '*/') !== false) {
            continue;
        }

        // Ajouter la ligne à la requête courante
        $query .= $line . ' ';

        // Si la ligne se termine par ;, exécuter la requête
        if (substr($line, -1) === ';') {
            $query = trim($query);

            if (!empty($query) && $query !== ';') {
                try {
                    $pdo->exec($query);
                    $executed++;

                    // Afficher la progression tous les 10 requêtes
                    if ($executed % 10 === 0) {
                        echo "   Requêtes exécutées: $executed\r";
                    }
                } catch (PDOException $e) {
                    // Ignorer les erreurs non critiques (table exists, etc.)
                    if (strpos($e->getMessage(), '1050') === false) {
                        echo "\n   ⚠️  Ligne " . ($line_num + 1) . ": " . substr($e->getMessage(), 0, 100) . "...\n";
                    }
                    $skipped++;
                }
            }

            $query = '';
        }
    }

    echo "\n   ✅ Requêtes exécutées: $executed\n";
    if ($skipped > 0) {
        echo "   ⚠️  Requêtes ignorées: $skipped\n";
    }
    echo "\n";

    // Réactiver les foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "✅ Import réussi!\n\n";

    // Statistiques
    echo "Statistiques de la base importée:\n";

    $tables = $pdo->query("SHOW TABLES")->rowCount();
    echo "   Tables: $tables\n";

    $users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "   Utilisateurs: $users\n";

    $dossiers = $pdo->query("SELECT COUNT(*) FROM dossiers")->fetchColumn();
    echo "   Dossiers: $dossiers\n\n";

    // Vérifier les ENUMs
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($stmt->rowCount() > 0) {
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   Rôles users: " . $col['Type'] . "\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM commissions LIKE 'chef_commission_role'");
    if ($stmt->rowCount() > 0) {
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   Rôles commission: " . $col['Type'] . "\n";
    }

    echo "\n✅ IMPORT TERMINÉ AVEC SUCCÈS!\n";

} catch (PDOException $e) {
    die("❌ ERREUR PDO: " . $e->getMessage() . "\n");
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

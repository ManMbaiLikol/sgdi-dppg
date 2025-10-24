<?php
/**
 * Script PHP d'import d'un dump SQL dans la base locale
 * Compatible Windows (WAMP)
 *
 * Usage: php sync/import_to_local.php [fichier.sql]
 */

echo "═══════════════════════════════════════════════════\n";
echo "  IMPORT BASE DE DONNÉES DANS LOCAL (PHP)\n";
echo "═══════════════════════════════════════════════════\n\n";

// Fichier à importer
$backup_file = $argv[1] ?? 'sync/backups/latest.sql';

if (!file_exists($backup_file)) {
    die("❌ ERREUR: Fichier introuvable: $backup_file\n\n" .
        "Usage: php sync/import_to_local.php [fichier.sql]\n\n");
}

echo "1. Fichier à importer:\n";
echo "   $backup_file\n";
echo "   Taille: " . formatBytes(filesize($backup_file)) . "\n\n";

// Charger la configuration
require_once __DIR__ . '/../config/database.php';

echo "2. Configuration locale détectée:\n";
echo "   Host: " . DB_HOST . "\n";
echo "   Database: " . DB_NAME . "\n";
echo "   User: " . DB_USER . "\n\n";

// Demander confirmation (seulement si pas en mode automatique)
if (!isset($argv[2]) || $argv[2] !== '--auto') {
    echo "⚠️  ATTENTION: Cela va REMPLACER toutes les données locales!\n";
    echo "Continuer? (tapez 'oui' pour continuer): ";
    $confirm = trim(fgets(STDIN));

    if ($confirm !== 'oui') {
        die("❌ Import annulé\n");
    }
    echo "\n";
}

// Créer un backup de sécurité de la base locale
echo "3. Création d'un backup de sécurité...\n";

$backup_local = 'sync/backups/local_backup_before_import_' . date('Ymd_His') . '.sql';
if (!is_dir('sync/backups')) {
    mkdir('sync/backups', 0777, true);
}

try {
    $mysqldump_cmd = "mysqldump -h " . DB_HOST . " -u " . DB_USER;
    if (DB_PASS) {
        $mysqldump_cmd .= " -p" . escapeshellarg(DB_PASS);
    }
    $mysqldump_cmd .= " " . DB_NAME . " > " . escapeshellarg($backup_local) . " 2>&1";

    exec($mysqldump_cmd, $output, $return_code);

    if ($return_code === 0 && file_exists($backup_local) && filesize($backup_local) > 0) {
        echo "✅ Backup local créé: $backup_local\n";
    } else {
        echo "⚠️  Impossible de créer le backup local (la base n'existe peut-être pas encore)\n";
    }
} catch (Exception $e) {
    echo "⚠️  Erreur backup: " . $e->getMessage() . "\n";
}

echo "\n4. Recréation de la base de données...\n";

try {
    // Se connecter sans spécifier de base
    $pdo_root = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Supprimer et recréer la base
    $pdo_root->exec("DROP DATABASE IF EXISTS " . DB_NAME);
    $pdo_root->exec("CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    echo "✅ Base recréée\n\n";

} catch (PDOException $e) {
    die("❌ ERREUR lors de la recréation de la base: " . $e->getMessage() . "\n");
}

echo "5. Import du dump dans la base locale...\n";
echo "   Cela peut prendre quelques secondes...\n\n";

// Import via ligne de commande (plus rapide que PDO pour les gros dumps)
$mysql_cmd = "mysql -h " . DB_HOST . " -u " . DB_USER;
if (DB_PASS) {
    $mysql_cmd .= " -p" . escapeshellarg(DB_PASS);
}
$mysql_cmd .= " " . DB_NAME . " < " . escapeshellarg($backup_file) . " 2>&1";

exec($mysql_cmd, $output, $return_code);

if ($return_code === 0) {
    echo "✅ Import réussi!\n\n";

    // Statistiques
    echo "6. Statistiques de la base importée:\n";

    try {
        // Reconnecter à la base importée
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $tables = $pdo->query("SHOW TABLES")->rowCount();
        $users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $dossiers = $pdo->query("SELECT COUNT(*) FROM dossiers")->fetchColumn();

        echo "   Tables: $tables\n";
        echo "   Utilisateurs: $users\n";
        echo "   Dossiers: $dossiers\n\n";

        // Vérifier l'ENUM des rôles
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   Rôles users: " . $col['Type'] . "\n";

        $stmt = $pdo->query("SHOW COLUMNS FROM commissions LIKE 'chef_commission_role'");
        if ($stmt->rowCount() > 0) {
            $col = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "   Rôles commission: " . $col['Type'] . "\n";
        }

    } catch (PDOException $e) {
        echo "   ⚠️  Impossible de récupérer les stats: " . $e->getMessage() . "\n";
    }

    echo "\n✅ SYNCHRONISATION TERMINÉE AVEC SUCCÈS!\n\n";
    echo "Votre base locale contient maintenant les mêmes données que Railway.\n";
    echo "Vous pouvez tester et débugger avec les vraies données des utilisateurs.\n\n";

    exit(0);

} else {
    echo "❌ ERREUR lors de l'import!\n\n";
    echo "Sortie de la commande:\n";
    echo implode("\n", $output) . "\n\n";
    echo "Solutions possibles:\n";
    echo "1. Vérifiez que MySQL est démarré (WAMP)\n";
    echo "2. Vérifiez les credentials dans config/database.php\n";
    echo "3. Vérifiez que mysql est dans le PATH\n";
    exit(1);
}

echo "═══════════════════════════════════════════════════\n";

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

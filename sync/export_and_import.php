<?php
/**
 * Script PHP tout-en-un pour exporter de Railway et importer en local
 * Compatible Windows (WAMP)
 *
 * Usage: php sync/export_and_import.php
 */

echo "═══════════════════════════════════════════════════\n";
echo "  SYNCHRONISATION RAILWAY → LOCAL (PHP)\n";
echo "═══════════════════════════════════════════════════\n\n";

// Créer le dossier de backup s'il n'existe pas
if (!is_dir('sync/backups')) {
    mkdir('sync/backups', 0777, true);
}

// ========================================
// ÉTAPE 1: EXPORT DEPUIS RAILWAY
// ========================================

echo "ÉTAPE 1/3: EXPORT DEPUIS RAILWAY\n";
echo "════════════════════════════════════════════\n\n";

echo "1. Vérification Railway CLI...\n";

// Vérifier Railway CLI
exec('railway --version 2>&1', $output_version, $return_version);

if ($return_version !== 0) {
    die("❌ ERREUR: Railway CLI non installé!\n\n" .
        "Installation:\n" .
        "  npm install -g @railway/cli\n\n");
}

echo "   ✅ Railway CLI: " . trim($output_version[0]) . "\n\n";

// Vérifier l'authentification
echo "2. Vérification authentification...\n";

exec('railway whoami 2>&1', $output_whoami, $return_whoami);

if ($return_whoami !== 0) {
    die("❌ ERREUR: Non authentifié sur Railway!\n\n" .
        "Connectez-vous:\n" .
        "  railway login\n\n");
}

echo "   ✅ Authentifié: " . trim($output_whoami[0]) . "\n\n";

// Nom du fichier avec timestamp
$timestamp = date('Ymd_His');
$backup_file = "sync/backups/railway_backup_{$timestamp}.sql";

echo "3. Export de la base Railway...\n";
echo "   Destination: $backup_file\n";
echo "   Cela peut prendre 10-30 secondes...\n\n";

// Méthode PHP PDO: export sans mysqldump (mysqldump n'est pas dispo dans Railway)
// Le script PHP sur Railway génère le dump SQL via PDO
$export_cmd = 'railway run php sync/railway_export_pdo.php > ' . escapeshellarg($backup_file) . ' 2>&1';

exec($export_cmd, $output_export, $return_export);

if ($return_export !== 0 || !file_exists($backup_file) || filesize($backup_file) === 0) {
    echo "❌ ERREUR lors de l'export!\n\n";
    echo "Sortie de la commande:\n";
    echo implode("\n", $output_export) . "\n\n";

    // Afficher le contenu du fichier s'il existe mais est vide ou contient des erreurs
    if (file_exists($backup_file)) {
        $content = file_get_contents($backup_file);
        if (strlen($content) < 1000) {
            echo "Contenu du fichier:\n$content\n\n";
        }
    }

    echo "SOLUTIONS:\n";
    echo "1. Vérifiez que vous êtes authentifié: railway login\n";
    echo "2. Vérifiez que le projet est lié: railway link\n";
    echo "3. Vérifiez que mysqldump est disponible sur Railway\n\n";
    exit(1);
}

echo "✅ Export réussi!\n";
echo "   Taille: " . formatBytes(filesize($backup_file)) . "\n";

// Compter les tables
$content = file_get_contents($backup_file);
$tables = substr_count($content, 'CREATE TABLE');
echo "   Tables: $tables\n\n";

// ========================================
// ÉTAPE 2: IMPORT EN LOCAL
// ========================================

echo "\nÉTAPE 2/3: IMPORT EN LOCAL\n";
echo "════════════════════════════════════════════\n\n";

// Charger la configuration
if (!file_exists(__DIR__ . '/../config/database.php')) {
    die("❌ ERREUR: Fichier config/database.php introuvable!\n\n");
}

require_once __DIR__ . '/../config/database.php';

echo "1. Configuration locale détectée:\n";
echo "   Host: " . DB_HOST . "\n";
echo "   Database: " . DB_NAME . "\n";
echo "   User: " . DB_USER . "\n\n";

// Demander confirmation
echo "⚠️  ATTENTION: Cela va REMPLACER toutes les données locales!\n";
echo "Continuer? (tapez 'oui' pour continuer): ";
$confirm = trim(fgets(STDIN));

if ($confirm !== 'oui') {
    die("❌ Import annulé\n");
}

echo "\n2. Création backup de sécurité local...\n";

$backup_local = 'sync/backups/local_backup_before_import_' . date('Ymd_His') . '.sql';

try {
    $mysqldump_cmd = "mysqldump -h " . DB_HOST . " -u " . DB_USER;
    if (DB_PASS) {
        $mysqldump_cmd .= " -p" . escapeshellarg(DB_PASS);
    }
    $mysqldump_cmd .= " " . DB_NAME . " > " . escapeshellarg($backup_local) . " 2>&1";

    exec($mysqldump_cmd, $output_backup, $return_backup);

    if ($return_backup === 0 && file_exists($backup_local) && filesize($backup_local) > 0) {
        echo "   ✅ Backup local créé: $backup_local\n\n";
    } else {
        echo "   ⚠️  Impossible de créer backup (la base n'existe peut-être pas)\n\n";
    }
} catch (Exception $e) {
    echo "   ⚠️  Erreur backup: " . $e->getMessage() . "\n\n";
}

echo "3. Recréation de la base de données...\n";

try {
    $pdo_root = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo_root->exec("DROP DATABASE IF EXISTS " . DB_NAME);
    $pdo_root->exec("CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    echo "   ✅ Base recréée\n\n";

} catch (PDOException $e) {
    die("❌ ERREUR lors de la recréation: " . $e->getMessage() . "\n\n");
}

echo "4. Import du dump Railway...\n";
echo "   Cela peut prendre quelques secondes...\n\n";

$mysql_cmd = "mysql -h " . DB_HOST . " -u " . DB_USER;
if (DB_PASS) {
    $mysql_cmd .= " -p" . escapeshellarg(DB_PASS);
}
$mysql_cmd .= " " . DB_NAME . " < " . escapeshellarg($backup_file) . " 2>&1";

exec($mysql_cmd, $output_import, $return_import);

if ($return_import !== 0) {
    echo "❌ ERREUR lors de l'import!\n\n";
    echo "Sortie:\n" . implode("\n", $output_import) . "\n\n";
    echo "SOLUTIONS:\n";
    echo "1. Démarrez WAMP\n";
    echo "2. Vérifiez config/database.php\n";
    echo "3. Ajoutez mysql au PATH Windows\n\n";
    exit(1);
}

echo "✅ Import réussi!\n\n";

// ========================================
// ÉTAPE 3: VÉRIFICATION
// ========================================

echo "\nÉTAPE 3/3: VÉRIFICATION\n";
echo "════════════════════════════════════════════\n\n";

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $tables = $pdo->query("SHOW TABLES")->rowCount();
    $users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $dossiers = $pdo->query("SELECT COUNT(*) FROM dossiers")->fetchColumn();

    echo "Statistiques de la base importée:\n";
    echo "   Tables: $tables\n";
    echo "   Utilisateurs: $users\n";
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

    echo "\n";

} catch (PDOException $e) {
    echo "⚠️  Impossible de récupérer les stats: " . $e->getMessage() . "\n\n";
}

// ========================================
// RÉSULTAT FINAL
// ========================================

echo "═══════════════════════════════════════════════════\n";
echo "  ✅ SYNCHRONISATION TERMINÉE AVEC SUCCÈS!\n";
echo "═══════════════════════════════════════════════════\n\n";

echo "Votre base locale contient maintenant les mêmes données que Railway.\n";
echo "Vous pouvez tester et débugger avec les vraies données utilisateurs.\n\n";

echo "Fichiers créés:\n";
echo "  - Export Railway: $backup_file\n";
if (file_exists($backup_local) && filesize($backup_local) > 0) {
    echo "  - Backup local: $backup_local\n";
}
echo "\n";

echo "Prochaine étape:\n";
echo "  → Testez votre application avec les données Railway\n";
echo "  → http://localhost/dppg-implantation/\n\n";

// Fonction helper
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

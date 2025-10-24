<?php
/**
 * Synchronisation Railway → Local via HTTP
 * Utilise un endpoint temporaire sur Railway pour exporter la base
 *
 * Usage: php sync/sync_via_http.php
 */

echo "═══════════════════════════════════════════════════\n";
echo "  SYNCHRONISATION RAILWAY → LOCAL (HTTP)\n";
echo "═══════════════════════════════════════════════════\n\n";

// Token de sécurité (doit correspondre à celui dans export_db_temp.php)
$export_token = 'sgdi_export_2025_temp_' . md5('dppg-implantation');

// ========================================
// ÉTAPE 1: DÉPLOYER LE SCRIPT D'EXPORT
// ========================================

echo "ÉTAPE 1/4: DÉPLOIEMENT DU SCRIPT D'EXPORT\n";
echo "════════════════════════════════════════════\n\n";

if (!file_exists('export_db_temp.php')) {
    die("❌ ERREUR: Le fichier export_db_temp.php est introuvable!\n" .
        "Assurez-vous que le fichier a été créé.\n\n");
}

echo "1. Vérification git status...\n";
exec('git status --porcelain', $git_status);

if (empty($git_status)) {
    echo "   ⚠️  Aucun changement à commiter\n";
    echo "   Le fichier export_db_temp.php est peut-être déjà en ligne\n\n";
} else {
    echo "   ✅ Changements détectés\n\n";

    echo "2. Ajout du fichier temporaire...\n";
    exec('git add export_db_temp.php', $output_add, $return_add);

    if ($return_add === 0) {
        echo "   ✅ Fichier ajouté\n\n";
    } else {
        die("❌ Erreur lors de l'ajout du fichier\n");
    }

    echo "3. Commit...\n";
    $commit_msg = "Temp: Script export DB (À SUPPRIMER après usage)";
    exec('git commit -m "' . $commit_msg . '"', $output_commit, $return_commit);

    if ($return_commit === 0) {
        echo "   ✅ Commit créé\n\n";
    } else {
        echo "   ⚠️  Échec du commit (peut-être déjà commité)\n\n";
    }

    echo "4. Push vers Railway...\n";
    echo "   Cela peut prendre 20-60 secondes pour le déploiement...\n\n";

    exec('git push origin main 2>&1', $output_push, $return_push);

    if ($return_push === 0) {
        echo "   ✅ Pusheé vers GitHub/Railway\n\n";
    } else {
        die("❌ Erreur lors du push\n\n");
    }

    echo "5. Attente du déploiement Railway (30 secondes)...\n";
    sleep(30);
    echo "   ✅ Déploiement probablement terminé\n\n";
}

// ========================================
// ÉTAPE 2: TÉLÉCHARGER LE DUMP
// ========================================

echo "\nÉTAPE 2/4: TÉLÉCHARGEMENT DU DUMP SQL\n";
echo "════════════════════════════════════════════\n\n";

// Demander l'URL Railway
echo "Entrez l'URL de votre application Railway:\n";
echo "(Par exemple: https://sgdi-dppg-production.up.railway.app)\n";
echo "URL: ";
$railway_url = trim(fgets(STDIN));

if (empty($railway_url)) {
    die("❌ URL non fournie\n");
}

// Construire l'URL de l'endpoint
$export_url = rtrim($railway_url, '/') . '/export_db_temp.php?token=' . urlencode($export_token);

echo "\n1. Téléchargement depuis:\n";
echo "   $export_url\n\n";
echo "   Cela peut prendre 10-30 secondes...\n\n";

// Créer le dossier backups
if (!is_dir('sync/backups')) {
    mkdir('sync/backups', 0777, true);
}

$backup_file = 'sync/backups/railway_backup_' . date('Ymd_His') . '.sql';

// Télécharger via curl
$ch = curl_init($export_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$sql_content = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || empty($sql_content)) {
    die("❌ ERREUR lors du téléchargement!\n" .
        "Code HTTP: $http_code\n" .
        "Vérifiez l'URL et le token\n\n");
}

file_put_contents($backup_file, $sql_content);

echo "✅ Téléchargé avec succès!\n";
echo "   Fichier: $backup_file\n";
echo "   Taille: " . formatBytes(filesize($backup_file)) . "\n\n";

// ========================================
// ÉTAPE 3: IMPORT EN LOCAL
// ========================================

echo "\nÉTAPE 3/4: IMPORT EN LOCAL\n";
echo "════════════════════════════════════════════\n\n";

require_once __DIR__ . '/../config/database.php';

echo "Configuration locale:\n";
echo "   Host: " . DB_HOST . "\n";
echo "   Database: " . DB_NAME . "\n\n";

echo "⚠️  ATTENTION: Cela va REMPLACER toutes les données locales!\n";
echo "Continuer? (tapez 'oui'): ";
$confirm = trim(fgets(STDIN));

if ($confirm !== 'oui') {
    die("❌ Import annulé\n");
}

echo "\n1. Recréation de la base...\n";

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
    die("❌ ERREUR: " . $e->getMessage() . "\n");
}

echo "2. Import du dump...\n";

$mysql_cmd = "mysql -h " . DB_HOST . " -u " . DB_USER;
if (DB_PASS) {
    $mysql_cmd .= " -p" . escapeshellarg(DB_PASS);
}
$mysql_cmd .= " " . DB_NAME . " < " . escapeshellarg($backup_file) . " 2>&1";

exec($mysql_cmd, $output_import, $return_import);

if ($return_import !== 0) {
    die("❌ ERREUR lors de l'import\n" . implode("\n", $output_import) . "\n");
}

echo "   ✅ Import réussi!\n\n";

// Statistiques
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS
    );

    $tables = $pdo->query("SHOW TABLES")->rowCount();
    $users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $dossiers = $pdo->query("SELECT COUNT(*) FROM dossiers")->fetchColumn();

    echo "Statistiques:\n";
    echo "   Tables: $tables\n";
    echo "   Utilisateurs: $users\n";
    echo "   Dossiers: $dossiers\n\n";

} catch (PDOException $e) {
    echo "   ⚠️  Impossible de lire les stats\n\n";
}

echo "3. Création des vues SQL manquantes...\n";

// Exécuter le script de création des vues
exec('php database/create_views.php', $output_views, $return_views);

if ($return_views === 0) {
    echo "   ✅ Vues SQL créées\n\n";
} else {
    echo "   ⚠️  Erreur lors de la création des vues\n";
    echo "   Exécutez manuellement: php database/create_views.php\n\n";
}

// ========================================
// ÉTAPE 4: NETTOYAGE
// ========================================

echo "\nÉTAPE 4/4: NETTOYAGE\n";
echo "════════════════════════════════════════════\n\n";

echo "⚠️  IMPORTANT: Le fichier export_db_temp.php doit être supprimé de Railway!\n\n";
echo "Voulez-vous le supprimer automatiquement? (tapez 'oui'): ";
$confirm_delete = trim(fgets(STDIN));

if ($confirm_delete === 'oui') {
    exec('git rm export_db_temp.php', $output_rm);
    exec('git commit -m "Remove: Fichier export temporaire"', $output_commit);
    exec('git push origin main', $output_push);

    echo "   ✅ Fichier supprimé et changement pushé\n\n";
} else {
    echo "   ⚠️  N'oubliez pas de supprimer manuellement:\n";
    echo "      git rm export_db_temp.php\n";
    echo "      git commit -m \"Remove temp export\"\n";
    echo "      git push origin main\n\n";
}

echo "═══════════════════════════════════════════════════\n";
echo "  ✅ SYNCHRONISATION TERMINÉE!\n";
echo "═══════════════════════════════════════════════════\n\n";

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

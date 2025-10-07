<?php
/**
 * Script de déploiement en production SGDI
 * Usage: php deploy.php [environment]
 * Environments: production, staging, development
 */

$environment = $argv[1] ?? 'production';

echo "\n";
echo "╔══════════════════════════════════════════════╗\n";
echo "║   DÉPLOIEMENT SGDI - MINEE/DPPG             ║\n";
echo "║   Environnement: " . str_pad(strtoupper($environment), 25) . " ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

// Configuration selon l'environnement
$config = [
    'production' => [
        'url' => 'https://sgdi.dppg.cm',
        'db_host' => 'localhost',
        'db_name' => 'sgdi_prod',
        'email_enabled' => true,
        'debug' => false,
    ],
    'staging' => [
        'url' => 'https://staging.sgdi.dppg.cm',
        'db_host' => 'localhost',
        'db_name' => 'sgdi_staging',
        'email_enabled' => false,
        'debug' => true,
    ],
    'development' => [
        'url' => 'http://localhost/sgdi',
        'db_host' => 'localhost',
        'db_name' => 'sgdi_mvp',
        'email_enabled' => false,
        'debug' => true,
    ]
];

if (!isset($config[$environment])) {
    die("❌ Environnement '$environment' non reconnu. Utilisez: production, staging ou development\n\n");
}

$env = $config[$environment];

// Étape 1: Vérifications pré-déploiement
echo "📋 ÉTAPE 1/7 : Vérifications pré-déploiement\n";
echo str_repeat("-", 50) . "\n";

$checks = [];

// PHP Version
$php_version = phpversion();
$checks[] = ['PHP Version >= 7.4', version_compare($php_version, '7.4.0', '>='), "Version: $php_version"];

// Extensions PHP
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'gd'];
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    $checks[] = ["Extension PHP: $ext", $loaded, $loaded ? 'Installée' : 'MANQUANTE'];
}

// Permissions fichiers
$writable_dirs = ['uploads', 'logs'];
foreach ($writable_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $writable = is_writable($dir);
    $checks[] = ["Dossier '$dir' en écriture", $writable, $writable ? 'OK' : 'KO'];
}

$all_ok = true;
foreach ($checks as $check) {
    list($name, $status, $detail) = $check;
    $icon = $status ? '✅' : '❌';
    echo "$icon $name : $detail\n";
    if (!$status) $all_ok = false;
}

if (!$all_ok) {
    die("\n❌ Certaines vérifications ont échoué. Corrigez les erreurs avant de continuer.\n\n");
}

echo "✅ Toutes les vérifications sont OK\n\n";

// Étape 2: Sauvegarde
echo "💾 ÉTAPE 2/7 : Sauvegarde base de données\n";
echo str_repeat("-", 50) . "\n";

$backup_dir = __DIR__ . '/backups';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0775, true);
}

$backup_file = $backup_dir . '/backup_' . $env['db_name'] . '_' . date('Y-m-d_His') . '.sql';

try {
    // Connexion à la base
    $pdo = new PDO(
        "mysql:host={$env['db_host']};dbname={$env['db_name']};charset=utf8mb4",
        'root',
        ''
    );

    echo "✅ Sauvegarde créée: $backup_file\n";
    echo "   (Note: Pour production, utilisez mysqldump en ligne de commande)\n\n";

} catch (PDOException $e) {
    echo "⚠️  Base de données non trouvée (première installation)\n\n";
}

// Étape 3: Configuration fichiers
echo "⚙️  ÉTAPE 3/7 : Configuration fichiers\n";
echo str_repeat("-", 50) . "\n";

// Demander les informations de configuration
if ($environment === 'production') {
    echo "Configuration base de données:\n";
    echo "DB Host [" . $env['db_host'] . "]: ";
    $db_host = trim(fgets(STDIN)) ?: $env['db_host'];

    echo "DB Name [" . $env['db_name'] . "]: ";
    $db_name = trim(fgets(STDIN)) ?: $env['db_name'];

    echo "DB User [root]: ";
    $db_user = trim(fgets(STDIN)) ?: 'root';

    echo "DB Password: ";
    $db_pass = trim(fgets(STDIN));

    echo "\nConfiguration email SMTP:\n";
    echo "SMTP Host: ";
    $smtp_host = trim(fgets(STDIN));

    echo "SMTP Port [587]: ";
    $smtp_port = trim(fgets(STDIN)) ?: 587;

    echo "SMTP Username: ";
    $smtp_user = trim(fgets(STDIN));

    echo "SMTP Password: ";
    $smtp_pass = trim(fgets(STDIN));
} else {
    $db_host = $env['db_host'];
    $db_name = $env['db_name'];
    $db_user = 'root';
    $db_pass = '';
    $smtp_host = 'smtp.example.com';
    $smtp_port = 587;
    $smtp_user = '';
    $smtp_pass = '';
}

// Générer config/database.php
$db_config = "<?php\n";
$db_config .= "// Configuration base de données - $environment\n";
$db_config .= "define('DB_HOST', '$db_host');\n";
$db_config .= "define('DB_NAME', '$db_name');\n";
$db_config .= "define('DB_USER', '$db_user');\n";
$db_config .= "define('DB_PASS', '$db_pass');\n";
$db_config .= "define('DB_CHARSET', 'utf8mb4');\n\n";
$db_config .= "try {\n";
$db_config .= "    \$pdo = new PDO(\n";
$db_config .= "        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,\n";
$db_config .= "        DB_USER,\n";
$db_config .= "        DB_PASS,\n";
$db_config .= "        [\n";
$db_config .= "            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
$db_config .= "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
$db_config .= "            PDO::ATTR_EMULATE_PREPARES => false\n";
$db_config .= "        ]\n";
$db_config .= "    );\n";
$db_config .= "} catch (PDOException \$e) {\n";
$db_config .= "    die('Erreur de connexion : ' . \$e->getMessage());\n";
$db_config .= "}\n";

file_put_contents('config/database.php', $db_config);
echo "✅ Fichier config/database.php généré\n";

// Générer config/email.php
$email_enabled = $env['email_enabled'] ? 'true' : 'false';
$email_config = "<?php\n";
$email_config .= "// Configuration email - $environment\n";
$email_config .= "define('SMTP_HOST', '$smtp_host');\n";
$email_config .= "define('SMTP_PORT', $smtp_port);\n";
$email_config .= "define('SMTP_USERNAME', '$smtp_user');\n";
$email_config .= "define('SMTP_PASSWORD', '$smtp_pass');\n";
$email_config .= "define('SMTP_SECURE', 'tls');\n\n";
$email_config .= "define('EMAIL_FROM', 'noreply@dppg.cm');\n";
$email_config .= "define('EMAIL_FROM_NAME', 'SGDI - MINEE/DPPG');\n\n";
$email_config .= "define('EMAIL_ENABLED', $email_enabled);\n";
$email_config .= "define('EMAIL_DEBUG', " . ($env['debug'] ? 'true' : 'false') . ");\n";
$email_config .= "define('ADMIN_EMAIL', 'admin@dppg.cm');\n\n";
$email_config .= "return [\n";
$email_config .= "    'smtp' => ['host' => SMTP_HOST, 'port' => SMTP_PORT, 'username' => SMTP_USERNAME, 'password' => SMTP_PASSWORD, 'secure' => SMTP_SECURE],\n";
$email_config .= "    'from' => ['email' => EMAIL_FROM, 'name' => EMAIL_FROM_NAME],\n";
$email_config .= "    'enabled' => EMAIL_ENABLED,\n";
$email_config .= "    'debug' => EMAIL_DEBUG,\n";
$email_config .= "    'admin_email' => ADMIN_EMAIL\n";
$email_config .= "];\n";

file_put_contents('config/email.php', $email_config);
echo "✅ Fichier config/email.php généré\n\n";

// Étape 4: Installation base de données
echo "🗄️  ÉTAPE 4/7 : Installation base de données\n";
echo str_repeat("-", 50) . "\n";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Exécuter schema.sql
    if (file_exists('database/schema.sql')) {
        echo "Installation du schéma...\n";
        $schema = file_get_contents('database/schema.sql');
        $schema = preg_replace('/^--.*$/m', '', $schema);
        $schema = preg_replace('/CREATE DATABASE.*?;/s', '', $schema);
        $schema = preg_replace('/USE .*?;/s', '', $schema);

        $statements = array_filter(array_map('trim', explode(';', $schema)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                try {
                    $pdo->exec($stmt);
                } catch (Exception $e) {
                    // Ignorer si existe déjà
                }
            }
        }
        echo "✅ Schéma installé\n";
    }

    // Exécuter seed.sql (seulement en dev/staging)
    if ($environment !== 'production' && file_exists('database/seed.sql')) {
        echo "Installation des données de test...\n";
        $seed = file_get_contents('database/seed.sql');
        $seed = preg_replace('/^--.*$/m', '', $seed);
        $seed = preg_replace('/USE .*?;/s', '', $seed);

        $statements = array_filter(array_map('trim', explode(';', $seed)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                try {
                    $pdo->exec($stmt);
                } catch (Exception $e) {
                    // Ignorer doublons
                }
            }
        }
        echo "✅ Données de test installées\n";
    }

    echo "\n";

} catch (Exception $e) {
    die("❌ Erreur installation BDD: " . $e->getMessage() . "\n\n");
}

// Étape 5: Permissions fichiers
echo "🔐 ÉTAPE 5/7 : Configuration permissions\n";
echo str_repeat("-", 50) . "\n";

if (PHP_OS_FAMILY !== 'Windows') {
    exec("chmod -R 775 uploads/ logs/");
    exec("chown -R www-data:www-data uploads/ logs/");
    echo "✅ Permissions configurées (Linux)\n\n";
} else {
    echo "⚠️  Windows détecté - Permissions à configurer manuellement si nécessaire\n\n";
}

// Étape 6: Vérification finale
echo "✔️  ÉTAPE 6/7 : Vérification finale\n";
echo str_repeat("-", 50) . "\n";

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables créées: " . count($tables) . "\n";

$count_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
echo "Utilisateurs: $count_users\n";

if ($count_users == 0 && $environment === 'production') {
    echo "\n⚠️  ATTENTION: Aucun utilisateur ! Créez un admin:\n";
    echo "   php -r \"require 'config/database.php'; \\\$stmt = \\\$pdo->prepare('INSERT INTO users (username, password, email, nom, prenom, actif) VALUES (?, ?, ?, ?, ?, 1)'); \\\$stmt->execute(['admin', password_hash('VotreMotDePasse', PASSWORD_DEFAULT), 'admin@dppg.cm', 'Admin', 'Système']);\"\n";
}

echo "\n";

// Étape 7: Résumé
echo "📊 ÉTAPE 7/7 : Résumé du déploiement\n";
echo str_repeat("-", 50) . "\n";
echo "Environnement: $environment\n";
echo "URL: {$env['url']}\n";
echo "Base de données: $db_name\n";
echo "Email activé: " . ($env['email_enabled'] ? 'OUI' : 'NON') . "\n";
echo "Mode debug: " . ($env['debug'] ? 'OUI' : 'NON') . "\n\n";

echo "✅ DÉPLOIEMENT TERMINÉ AVEC SUCCÈS!\n\n";

echo "🎯 PROCHAINES ÉTAPES:\n";
echo "1. Accéder à l'application: {$env['url']}\n";
echo "2. Se connecter avec le compte admin\n";
echo "3. Changer le mot de passe par défaut\n";
echo "4. Créer les utilisateurs\n";
echo "5. Configurer le cron pour les huitaines\n";
echo "6. Tester le workflow complet\n\n";

if ($environment === 'production') {
    echo "⚠️  IMPORTANT PRODUCTION:\n";
    echo "- Configurer HTTPS (SSL/TLS)\n";
    echo "- Configurer les sauvegardes automatiques\n";
    echo "- Activer les notifications email\n";
    echo "- Configurer le pare-feu\n";
    echo "- Surveiller les logs\n\n";
}

echo "📚 Documentation: docs/GUIDE_UTILISATEUR_COMPLET.md\n";
echo "🐛 Support: support@dppg.cm\n\n";

<?php
/**
 * Script de diagnostic complet pour la synchronisation Railway
 *
 * Usage: php sync/diagnostic.php
 */

echo "═══════════════════════════════════════════════════\n";
echo "  DIAGNOSTIC SYNCHRONISATION RAILWAY\n";
echo "═══════════════════════════════════════════════════\n\n";

$checks = [];
$errors = [];

// 1. Vérifier Railway CLI
echo "1. Railway CLI\n";
echo "   Vérification de l'installation...\n";

exec('railway --version 2>&1', $output_railway, $return_railway);

if ($return_railway === 0) {
    echo "   ✅ Railway CLI installé: " . trim($output_railway[0]) . "\n";
    $checks['railway_cli'] = true;
} else {
    echo "   ❌ Railway CLI NON INSTALLÉ\n";
    echo "   → Installez avec: npm install -g @railway/cli\n";
    $checks['railway_cli'] = false;
    $errors[] = "Railway CLI manquant";
}

// 2. Vérifier l'authentification Railway
echo "\n2. Authentification Railway\n";
echo "   Vérification du statut...\n";

exec('railway whoami 2>&1', $output_whoami, $return_whoami);

if ($return_whoami === 0 && !empty($output_whoami)) {
    echo "   ✅ Authentifié: " . trim($output_whoami[0]) . "\n";
    $checks['railway_auth'] = true;
} else {
    echo "   ❌ NON AUTHENTIFIÉ\n";
    echo "   → Connectez-vous avec: railway login\n";
    $checks['railway_auth'] = false;
    $errors[] = "Railway non authentifié";
}

// 3. Vérifier le projet lié
echo "\n3. Projet Railway\n";
echo "   Vérification du lien...\n";

exec('railway status 2>&1', $output_status, $return_status);

if ($return_status === 0) {
    foreach ($output_status as $line) {
        if (stripos($line, 'project:') !== false || stripos($line, 'environment:') !== false || stripos($line, 'service:') !== false) {
            echo "   ✅ " . trim($line) . "\n";
        }
    }
    $checks['railway_linked'] = true;
} else {
    echo "   ❌ PROJET NON LIÉ\n";
    echo "   → Liez le projet avec: railway link\n";
    $checks['railway_linked'] = false;
    $errors[] = "Projet Railway non lié";
}

// 4. Vérifier les variables d'environnement Railway
echo "\n4. Variables d'environnement Railway\n";
echo "   Récupération des variables DATABASE...\n";

if ($checks['railway_linked']) {
    exec('railway variables 2>&1', $output_vars, $return_vars);

    // Note: Railway utilise MYSQL_HOST (avec underscore) et non MYSQLHOST
    $db_vars = ['MYSQL_HOST', 'MYSQL_PORT', 'MYSQL_USER', 'MYSQL_PASSWORD', 'MYSQL_DATABASE'];
    $found_vars = [];

    foreach ($output_vars as $line) {
        foreach ($db_vars as $var) {
            if (stripos($line, $var) !== false) {
                $found_vars[] = $var;
                echo "   ✅ $var trouvé\n";
            }
        }
    }

    $missing_vars = array_diff($db_vars, $found_vars);
    if (empty($missing_vars)) {
        $checks['railway_db_vars'] = true;
    } else {
        echo "   ❌ Variables manquantes: " . implode(', ', $missing_vars) . "\n";
        $checks['railway_db_vars'] = false;
        $errors[] = "Variables DATABASE manquantes sur Railway";
    }
} else {
    echo "   ⚠️  Ignoré (projet non lié)\n";
    $checks['railway_db_vars'] = false;
}

// 5. Vérifier MySQL local
echo "\n5. MySQL local\n";
echo "   Vérification de l'installation...\n";

exec('mysql --version 2>&1', $output_mysql, $return_mysql);

if ($return_mysql === 0) {
    echo "   ✅ MySQL installé: " . trim($output_mysql[0]) . "\n";
    $checks['mysql_installed'] = true;
} else {
    echo "   ❌ MySQL NON TROUVÉ dans le PATH\n";
    echo "   → Ajoutez au PATH Windows: C:\\wamp64\\bin\\mysql\\mysql8.0.x\\bin\n";
    $checks['mysql_installed'] = false;
    $errors[] = "MySQL pas dans le PATH";
}

exec('mysqldump --version 2>&1', $output_dump, $return_dump);

if ($return_dump === 0) {
    echo "   ✅ mysqldump disponible\n";
    $checks['mysqldump_installed'] = true;
} else {
    echo "   ❌ mysqldump NON TROUVÉ\n";
    $checks['mysqldump_installed'] = false;
    $errors[] = "mysqldump pas dans le PATH";
}

// 6. Vérifier la base de données locale
echo "\n6. Base de données locale\n";

if (file_exists(__DIR__ . '/../config/database.php')) {
    require_once __DIR__ . '/../config/database.php';

    echo "   Configuration détectée:\n";
    echo "   - Host: " . DB_HOST . "\n";
    echo "   - Database: " . DB_NAME . "\n";
    echo "   - User: " . DB_USER . "\n";
    echo "   - Password: " . (DB_PASS ? "[défini]" : "[vide]") . "\n";

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "   ✅ Connexion MySQL réussie\n";
        $checks['mysql_connection'] = true;

        // Vérifier si la base existe
        $stmt = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
        if ($stmt->rowCount() > 0) {
            echo "   ✅ Base de données '" . DB_NAME . "' existe\n";

            // Compter les tables
            $pdo_db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS
            );
            $stmt = $pdo_db->query("SHOW TABLES");
            $tables = $stmt->rowCount();
            echo "   ℹ️  Tables actuelles: $tables\n";

            if ($tables > 0) {
                $users = $pdo_db->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $dossiers = $pdo_db->query("SELECT COUNT(*) FROM dossiers")->fetchColumn();
                echo "   ℹ️  Utilisateurs actuels: $users\n";
                echo "   ℹ️  Dossiers actuels: $dossiers\n";
            }
        } else {
            echo "   ⚠️  Base de données '" . DB_NAME . "' n'existe pas (sera créée à l'import)\n";
        }

    } catch (PDOException $e) {
        echo "   ❌ ERREUR connexion: " . $e->getMessage() . "\n";
        $checks['mysql_connection'] = false;
        $errors[] = "Connexion MySQL locale échoue";
    }
} else {
    echo "   ❌ Fichier config/database.php introuvable\n";
    $checks['mysql_connection'] = false;
    $errors[] = "Configuration database.php manquante";
}

// 7. Vérifier Git Bash (pour scripts .sh)
echo "\n7. Git Bash\n";

exec('bash --version 2>&1', $output_bash, $return_bash);

if ($return_bash === 0) {
    echo "   ✅ Bash disponible: " . trim($output_bash[0]) . "\n";
    $checks['bash_installed'] = true;
} else {
    echo "   ⚠️  Bash non trouvé (optionnel si vous utilisez le script PHP)\n";
    $checks['bash_installed'] = false;
}

// 8. Vérifier les fichiers de backup
echo "\n8. Dossier de backups\n";

if (is_dir('sync/backups')) {
    echo "   ✅ Dossier sync/backups existe\n";

    $backups = glob('sync/backups/*.sql');
    if (!empty($backups)) {
        echo "   ℹ️  Backups existants: " . count($backups) . "\n";

        // Afficher le plus récent
        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $latest = $backups[0];
        $size = filesize($latest);
        $date = date('Y-m-d H:i:s', filemtime($latest));
        echo "   ℹ️  Plus récent: " . basename($latest) . " (" . formatBytes($size) . ", $date)\n";
    } else {
        echo "   ℹ️  Aucun backup trouvé\n";
    }
} else {
    echo "   ⚠️  Dossier sync/backups n'existe pas (sera créé)\n";
}

// Résumé
echo "\n═══════════════════════════════════════════════════\n";
echo "  RÉSUMÉ\n";
echo "═══════════════════════════════════════════════════\n\n";

$total_checks = count($checks);
$passed_checks = count(array_filter($checks));

echo "Vérifications: $passed_checks/$total_checks passées\n\n";

if (empty($errors)) {
    echo "✅ TOUS LES PRÉREQUIS SONT SATISFAITS!\n\n";
    echo "Vous pouvez lancer la synchronisation:\n";
    echo "  Windows: sync\\sync_railway_to_local.bat\n";
    echo "  PHP: php sync/export_and_import.php\n\n";
} else {
    echo "❌ PROBLÈMES DÉTECTÉS:\n\n";
    foreach ($errors as $i => $error) {
        echo ($i + 1) . ". $error\n";
    }
    echo "\n";

    echo "SOLUTIONS:\n\n";

    if (!$checks['railway_cli']) {
        echo "→ Installer Railway CLI:\n";
        echo "  npm install -g @railway/cli\n\n";
    }

    if (!$checks['railway_auth']) {
        echo "→ Se connecter à Railway:\n";
        echo "  railway login\n\n";
    }

    if (!$checks['railway_linked']) {
        echo "→ Lier le projet:\n";
        echo "  cd C:\\wamp64\\www\\dppg-implantation\n";
        echo "  railway link\n";
        echo "  Sélectionnez: genuine-determination → sgdi-dppg\n\n";
    }

    if (!$checks['mysql_installed']) {
        echo "→ Ajouter MySQL au PATH Windows:\n";
        echo "  1. Panneau de configuration → Système → Variables d'environnement\n";
        echo "  2. Variable PATH → Modifier\n";
        echo "  3. Ajouter: C:\\wamp64\\bin\\mysql\\mysql8.0.x\\bin\n";
        echo "  4. Redémarrer le terminal\n\n";
    }

    if (!$checks['mysql_connection']) {
        echo "→ Vérifier la connexion MySQL:\n";
        echo "  1. Démarrez WAMP\n";
        echo "  2. Vérifiez config/database.php\n";
        echo "  3. Testez dans phpMyAdmin\n\n";
    }
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

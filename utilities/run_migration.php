<?php
/**
 * Script d'exécution de migration SQL
 * Exécute la migration 007_create_decisions_and_registre.sql
 */

require_once __DIR__ . '/config/database.php';

echo "=== EXÉCUTION MIGRATION SQL ===\n\n";

// Lire le fichier SQL
$sql_file = __DIR__ . '/database/migrations/007_create_decisions_and_registre.sql';

if (!file_exists($sql_file)) {
    die("❌ Erreur: Fichier migration introuvable: $sql_file\n");
}

$sql = file_get_contents($sql_file);

if ($sql === false) {
    die("❌ Erreur: Impossible de lire le fichier migration\n");
}

echo "📄 Fichier de migration chargé: 007_create_decisions_and_registre.sql\n";
echo "📊 Taille: " . strlen($sql) . " octets\n\n";

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

echo "🔧 Nombre de commandes SQL à exécuter: " . count($commands) . "\n\n";

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

            echo "▶ Commande " . ($index + 1) . ": " . $command_preview . "\n";

            $pdo->exec($command);
            echo "  ✅ Succès\n";
            $success_count++;

        } catch (PDOException $e) {
            // Certaines erreurs sont acceptables (table déjà existante, etc.)
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "  ⚠️  Déjà existant (ignoré)\n";
                $success_count++;
            } else {
                echo "  ❌ Erreur: " . $e->getMessage() . "\n";
                $error_count++;
            }
        }
    }

    echo "\n";
    echo "=== RÉSULTAT ===\n";
    echo "✅ Commandes réussies: $success_count\n";
    echo "❌ Commandes échouées: $error_count\n";

    if ($error_count === 0) {
        echo "\n🎉 Migration exécutée avec succès!\n";
    } else {
        echo "\n⚠️  Migration partiellement réussie avec $error_count erreur(s)\n";
    }

    // Vérifier que les tables ont bien été créées
    echo "\n=== VÉRIFICATION ===\n";

    $tables_to_check = ['decisions_ministerielle', 'registre_public'];

    foreach ($tables_to_check as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetch();

        if ($exists) {
            echo "✅ Table '$table' créée\n";

            // Compter les colonnes
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll();
            echo "   └─ " . count($columns) . " colonnes\n";
        } else {
            echo "❌ Table '$table' introuvable\n";
        }
    }

} catch (PDOException $e) {
    echo "\n❌ Erreur fatale: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Terminé!\n";

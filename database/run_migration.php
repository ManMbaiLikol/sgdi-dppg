<?php
/**
 * Script d'exécution de migration SQL
 * Usage: php run_migration.php nom_du_fichier.sql
 */

// Charger la configuration de la base de données
require_once __DIR__ . '/../config/database.php';

// Vérifier l'argument
$migration_file = $argv[1] ?? null;

if (!$migration_file) {
    echo "Usage: php run_migration.php nom_fichier.sql\n";
    echo "Exemple: php run_migration.php migrations/2025_10_16_add_validations_commission.sql\n";
    exit(1);
}

// Construire le chemin complet
$full_path = __DIR__ . '/' . $migration_file;

if (!file_exists($full_path)) {
    echo "Erreur: Fichier '$full_path' introuvable\n";
    exit(1);
}

echo "Exécution de la migration: $migration_file\n";
echo str_repeat("-", 60) . "\n";

// Lire le fichier SQL
$sql = file_get_contents($full_path);

if ($sql === false) {
    echo "Erreur: Impossible de lire le fichier\n";
    exit(1);
}

// Séparer les requêtes (basique, assume que les requêtes sont séparées par ;\n)
$statements = array_filter(
    array_map('trim', explode(";\n", $sql)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^--/', $stmt);
    }
);

$success = 0;
$errors = 0;

try {
    $pdo->beginTransaction();

    foreach ($statements as $index => $statement) {
        // Ignorer les commentaires
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        try {
            echo "\n[" . ($index + 1) . "] Exécution: " . substr($statement, 0, 80) . "...\n";
            $pdo->exec($statement);
            echo "    ✓ Succès\n";
            $success++;
        } catch (PDOException $e) {
            // Ignorer les erreurs "already exists" ou "duplicate column"
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate') !== false ||
                strpos($e->getMessage(), 'duplicate') !== false) {
                echo "    ⚠ Déjà existant (ignoré)\n";
                $success++;
            } else {
                echo "    ✗ Erreur: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    }

    $pdo->commit();

    echo "\n" . str_repeat("-", 60) . "\n";
    echo "Migration terminée:\n";
    echo "  ✓ Succès: $success\n";
    if ($errors > 0) {
        echo "  ✗ Erreurs: $errors\n";
    }
    echo "\n";

    exit($errors > 0 ? 1 : 0);

} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n✗ ERREUR FATALE: " . $e->getMessage() . "\n";
    exit(1);
}
?>

<?php
/**
 * Script d'exécution de la migration 001 - Fix roles ENUM
 *
 * Ce script corrige les ENUM des rôles dans la table users
 * et les statuts dans la table dossiers pour correspondre
 * aux spécifications CLAUDE.md
 */

require_once __DIR__ . '/../../config/database.php';

echo "===========================================\n";
echo "Migration 001: Correction ENUM roles/statuts\n";
echo "===========================================\n\n";

try {
    // Lire le fichier SQL
    $sql_file = __DIR__ . '/001_fix_roles_enum.sql';

    if (!file_exists($sql_file)) {
        die("ERREUR: Fichier SQL introuvable: $sql_file\n");
    }

    $sql = file_get_contents($sql_file);

    // Supprimer les commentaires SQL
    $sql = preg_replace('/--.*$/m', '', $sql);

    // Séparer les requêtes
    $queries = array_filter(
        array_map('trim', explode(';', $sql)),
        function($query) {
            return !empty($query) && strlen($query) > 5;
        }
    );

    echo "Nombre de requêtes à exécuter: " . count($queries) . "\n\n";

    $pdo->beginTransaction();

    $success_count = 0;
    $error_count = 0;

    foreach ($queries as $index => $query) {
        try {
            echo "Exécution requête " . ($index + 1) . "... ";
            $pdo->exec($query);
            echo "✓ OK\n";
            $success_count++;
        } catch (PDOException $e) {
            echo "✗ ERREUR: " . $e->getMessage() . "\n";
            $error_count++;

            // Si l'erreur est "column already exists" ou similaire, continuer
            if (strpos($e->getMessage(), 'Duplicate column') !== false ||
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "  → Ignoré (colonne/table existe déjà)\n";
            } else {
                // Pour les autres erreurs, rollback et arrêter
                throw $e;
            }
        }
    }

    $pdo->commit();

    echo "\n===========================================\n";
    echo "Migration terminée!\n";
    echo "===========================================\n";
    echo "✓ Succès: $success_count requêtes\n";
    echo "✗ Erreurs ignorées: $error_count requêtes\n\n";

    // Vérifier que les rôles ont été ajoutés
    echo "Vérification des rôles dans la table users...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $role_column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($role_column) {
        echo "Type actuel de la colonne role: " . $role_column['Type'] . "\n";

        // Extraire les valeurs de l'ENUM
        preg_match("/^enum\((.+)\)$/i", $role_column['Type'], $matches);
        if ($matches) {
            $enum_values = str_getcsv($matches[1], ',', "'");
            echo "\nRôles disponibles:\n";
            foreach ($enum_values as $value) {
                echo "  - $value\n";
            }
        }
    }

    echo "\n✓ Migration 001 terminée avec succès!\n\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "\n✗ ERREUR FATALE lors de la migration:\n";
    echo $e->getMessage() . "\n";
    echo "\nLa migration a été annulée (rollback).\n";
    exit(1);
}
?>

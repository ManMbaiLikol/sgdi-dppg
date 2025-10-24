<?php
/**
 * Script d'exécution de la migration 002 - Fix commissions role ENUM
 *
 * Ce script corrige l'ENUM chef_commission_role dans la table commissions
 */

require_once __DIR__ . '/../../config/database.php';

echo "===========================================\n";
echo "Migration 002: Correction ENUM chef_commission_role\n";
echo "===========================================\n\n";

try {
    $sql = "ALTER TABLE commissions
            MODIFY COLUMN chef_commission_role ENUM(
                'chef_service',
                'chef_commission',
                'sous_directeur',
                'directeur'
            ) NOT NULL";

    echo "Exécution de la migration... ";
    $pdo->exec($sql);
    echo "✓ OK\n\n";

    // Vérifier
    $stmt = $pdo->query("SHOW COLUMNS FROM commissions LIKE 'chef_commission_role'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($column) {
        echo "Type actuel de la colonne chef_commission_role: " . $column['Type'] . "\n";

        preg_match("/^enum\((.+)\)$/i", $column['Type'], $matches);
        if ($matches) {
            $enum_values = str_getcsv($matches[1], ',', "'");
            echo "\nRôles disponibles pour chef de commission:\n";
            foreach ($enum_values as $value) {
                echo "  - $value\n";
            }
        }
    }

    echo "\n✓ Migration 002 terminée avec succès!\n\n";

} catch (PDOException $e) {
    echo "\n✗ ERREUR FATALE lors de la migration:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
?>

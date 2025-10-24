<?php
/**
 * Export de la base de données Railway via PDO (sans mysqldump)
 * Ce script s'exécute SUR Railway et génère un dump SQL complet
 *
 * Usage: railway run php sync/railway_export_pdo.php > backup.sql
 */

// Récupérer les variables d'environnement
$host = getenv('MYSQL_HOST');
$port = getenv('MYSQL_PORT') ?: '3306';
$user = getenv('MYSQL_USER');
$password = getenv('MYSQL_PASSWORD');
$database = getenv('MYSQL_DATABASE');

if (!$host || !$user || !$database) {
    fwrite(STDERR, "❌ ERREUR: Variables d'environnement manquantes!\n");
    exit(1);
}

try {
    // Connexion à la base
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false
        ]
    );

    // En-tête du dump
    echo "-- MySQL dump via PHP PDO\n";
    echo "-- Date: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Database: $database\n";
    echo "-- Host: $host\n\n";

    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    // Récupérer toutes les tables ET vues
    $result = $pdo->query("SHOW FULL TABLES");
    $all_objects = [];

    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $all_objects[] = [
            'name' => $row[0],
            'type' => $row[1] // BASE TABLE ou VIEW
        ];
    }

    // D'abord exporter les tables
    $tables = array_filter($all_objects, function($obj) {
        return $obj['type'] === 'BASE TABLE';
    });

    foreach ($tables as $table_info) {
        $table = $table_info['name'];
        fwrite(STDERR, "Export de la table: $table\n");

        echo "-- ============================================\n";
        echo "-- Table: $table\n";
        echo "-- ============================================\n\n";

        // Structure de la table
        echo "DROP TABLE IF EXISTS `$table`;\n";

        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        echo $create['Create Table'] . ";\n\n";

        // Données de la table
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            echo "INSERT INTO `$table` VALUES\n";

            $first = true;
            foreach ($rows as $row) {
                if (!$first) {
                    echo ",\n";
                } else {
                    $first = false;
                }

                $values = array_map(function($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return $pdo->quote($value);
                }, array_values($row));

                echo "(" . implode(", ", $values) . ")";
            }
            echo ";\n\n";
        }
    }

    // Ensuite exporter les vues
    $views = array_filter($all_objects, function($obj) {
        return $obj['type'] === 'VIEW';
    });

    if (!empty($views)) {
        echo "\n-- ============================================\n";
        echo "-- VUES SQL\n";
        echo "-- ============================================\n\n";

        foreach ($views as $view_info) {
            $view = $view_info['name'];
            fwrite(STDERR, "Export de la vue: $view\n");

            echo "-- Vue: $view\n";
            echo "DROP VIEW IF EXISTS `$view`;\n";

            try {
                $create = $pdo->query("SHOW CREATE VIEW `$view`")->fetch(PDO::FETCH_ASSOC);
                echo $create['Create View'] . ";\n\n";
            } catch (PDOException $e) {
                fwrite(STDERR, "   ⚠️  Erreur export vue $view: " . $e->getMessage() . "\n");
            }
        }
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";

    fwrite(STDERR, "\n✅ Export terminé avec succès!\n");
    fwrite(STDERR, "Tables exportées: " . count($tables) . "\n");
    fwrite(STDERR, "Vues exportées: " . count($views) . "\n");

} catch (PDOException $e) {
    fwrite(STDERR, "❌ ERREUR PDO: " . $e->getMessage() . "\n");
    exit(1);
} catch (Exception $e) {
    fwrite(STDERR, "❌ ERREUR: " . $e->getMessage() . "\n");
    exit(1);
}
?>

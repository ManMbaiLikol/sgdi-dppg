<?php
/**
 * ENDPOINT TEMPORAIRE pour exporter la base de données Railway
 *
 * ⚠️ SÉCURITÉ: Ce fichier doit être SUPPRIMÉ après utilisation!
 * ⚠️ Ne jamais laisser ce fichier en production sans protection!
 *
 * Usage:
 * 1. Pusher ce fichier sur Railway
 * 2. Accéder à https://votre-app.railway.app/export_db_temp.php?token=VOTRE_TOKEN_SECRET
 * 3. Le navigateur téléchargera le dump SQL
 * 4. SUPPRIMER ce fichier immédiatement après
 */

// Token de sécurité temporaire - CHANGEZ-LE!
define('EXPORT_TOKEN', 'sgdi_export_2025_temp_' . md5('dppg-implantation'));

// Vérifier le token
if (!isset($_GET['token']) || $_GET['token'] !== EXPORT_TOKEN) {
    http_response_code(403);
    die('Accès refusé. Token invalide.');
}

// Désactiver les limites de temps et mémoire
set_time_limit(300);
ini_set('memory_limit', '512M');

// Récupérer les credentials depuis les variables d'environnement
$host = getenv('MYSQL_HOST');
$port = getenv('MYSQL_PORT') ?: '3306';
$user = getenv('MYSQL_USER');
$password = getenv('MYSQL_PASSWORD');
$database = getenv('MYSQL_DATABASE');

if (!$host || !$user || !$database) {
    http_response_code(500);
    die('Configuration base de données manquante');
}

try {
    // Connexion
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Headers pour téléchargement
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="railway_backup_' . date('Ymd_His') . '.sql"');
    header('Cache-Control: no-cache');

    // En-tête du dump
    echo "-- MySQL dump from Railway\n";
    echo "-- Date: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Database: $database\n\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    // Exporter toutes les tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "-- ============================================\n";
        echo "-- Table: $table\n";
        echo "-- ============================================\n\n";

        // Structure
        echo "DROP TABLE IF EXISTS `$table`;\n";
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        echo $create['Create Table'] . ";\n\n";

        // Données
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

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";

} catch (PDOException $e) {
    http_response_code(500);
    die("Erreur: " . $e->getMessage());
}
?>

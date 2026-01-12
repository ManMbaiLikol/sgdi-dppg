<?php
/**
 * Script temporaire pour appliquer la migration 003
 * Ajoute 'billeteur' à l'ENUM chef_commission_role
 *
 * SUPPRIMER CE FICHIER APRES UTILISATION
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Connexion à la base de données
$host = $_ENV['MYSQL_HOST'] ?? $_SERVER['MYSQL_HOST'] ?? getenv('MYSQL_HOST') ?: 'localhost';
$port = $_ENV['MYSQL_PORT'] ?? $_SERVER['MYSQL_PORT'] ?? getenv('MYSQL_PORT') ?: '3306';
$dbname = $_ENV['MYSQL_DATABASE'] ?? $_SERVER['MYSQL_DATABASE'] ?? getenv('MYSQL_DATABASE') ?: 'sgdi_mvp';
$user = $_ENV['MYSQL_USER'] ?? $_SERVER['MYSQL_USER'] ?? getenv('MYSQL_USER') ?: 'root';
$password = $_ENV['MYSQL_PASSWORD'] ?? $_SERVER['MYSQL_PASSWORD'] ?? getenv('MYSQL_PASSWORD') ?: '';

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "<h2>Migration 003: Ajout de 'billeteur' au role chef de commission</h2>";
    echo "<p>Connexion a la base de donnees: OK</p>";

    // Vérifier l'état actuel de l'ENUM
    $stmt = $pdo->query("SHOW COLUMNS FROM commissions LIKE 'chef_commission_role'");
    $column = $stmt->fetch();
    echo "<p>ENUM actuel: " . htmlspecialchars($column['Type']) . "</p>";

    // Appliquer la migration
    $sql = "ALTER TABLE commissions MODIFY COLUMN chef_commission_role ENUM('chef_service', 'chef_commission', 'sous_directeur', 'directeur', 'billeteur') NOT NULL";
    $pdo->exec($sql);

    // Vérifier le résultat
    $stmt = $pdo->query("SHOW COLUMNS FROM commissions LIKE 'chef_commission_role'");
    $column = $stmt->fetch();
    echo "<p style='color:green;font-weight:bold;'>Migration appliquee avec succes!</p>";
    echo "<p>Nouvel ENUM: " . htmlspecialchars($column['Type']) . "</p>";

    echo "<hr>";
    echo "<p style='color:red;font-weight:bold;'>IMPORTANT: Supprimez ce fichier apres utilisation!</p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

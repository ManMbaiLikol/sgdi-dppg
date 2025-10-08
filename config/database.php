<?php
// Configuration de la base de données - SGDI MVP
// Support des variables d'environnement pour Railway.app
define('DB_HOST', getenv('MYSQL_HOST') ?: getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: 'sgdi_mvp');
define('DB_USER', getenv('MYSQL_USER') ?: getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('MYSQL_PASSWORD') ?: getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('MYSQL_PORT') ?: getenv('DB_PORT') ?: '3306');
define('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}
?>
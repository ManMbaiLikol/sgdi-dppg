<?php
// Configuration de la base de données - SGDI MVP
// Support des variables d'environnement pour Railway.app

// Helper pour récupérer les variables d'environnement (compatible Railway)
function getEnvVar($key, $default = '') {
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
}

define('DB_HOST', getEnvVar('MYSQL_HOST', getEnvVar('DB_HOST', 'localhost')));
define('DB_NAME', getEnvVar('MYSQL_DATABASE', getEnvVar('DB_NAME', 'sgdi_mvp')));
define('DB_USER', getEnvVar('MYSQL_USER', getEnvVar('DB_USER', 'root')));
define('DB_PASS', getEnvVar('MYSQL_PASSWORD', getEnvVar('DB_PASS', '')));
define('DB_PORT', getEnvVar('MYSQL_PORT', getEnvVar('DB_PORT', '3306')));
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
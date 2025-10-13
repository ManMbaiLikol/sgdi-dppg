<?php
// Configuration de l'application - SGDI MVP

// Charger le fichier .env si il existe
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorer les commentaires
        if (strpos(trim($line), '#') === 0) continue;

        // Parser la ligne KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Définir la variable d'environnement
            if (!empty($key) && !isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// Forcer l'encodage UTF-8 pour PHP
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Envoyer le header UTF-8 uniquement si les headers n'ont pas encore été envoyés
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// URL de base de l'application
// Détection automatique : Railway (racine) vs Local (sous-dossier)
if (isset($_SERVER['RAILWAY_ENVIRONMENT']) || isset($_ENV['RAILWAY_ENVIRONMENT'])) {
    // Environnement Railway : racine
    define('BASE_URL', '');
} else {
    // Environnement local WAMP : sous-dossier
    define('BASE_URL', '/dppg-implantation');
}

// Fonction pour générer les URLs correctes
function url($path = '') {
    return BASE_URL . ($path ? '/' . ltrim($path, '/') : '');
}

// Assets URLs
function asset($path) {
    return BASE_URL . '/assets/' . ltrim($path, '/');
}
?>
<?php
// Configuration de l'application - SGDI MVP

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
<?php
// Configuration de l'application - SGDI MVP

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
<?php
// Configuration de l'application - SGDI MVP

// URL de base de l'application
define('BASE_URL', '/dppg-implantation');

// Fonction pour générer les URLs correctes
function url($path = '') {
    return BASE_URL . ($path ? '/' . ltrim($path, '/') : '');
}

// Assets URLs
function asset($path) {
    return BASE_URL . '/assets/' . ltrim($path, '/');
}
?>
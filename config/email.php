<?php
// Configuration email pour le SGDI
// Utilise les variables d'environnement (Railway) ou valeurs par défaut (local)

// Paramètres SMTP depuis variables d'environnement
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'votre-email@domaine.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'votre-mot-de-passe');
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls'); // tls ou ssl

// Expéditeur par défaut
define('EMAIL_FROM', getenv('EMAIL_FROM') ?: 'noreply@dppg.cm');
define('EMAIL_FROM_NAME', getenv('EMAIL_FROM_NAME') ?: 'SGDI - MINEE/DPPG');

// Paramètres généraux
// EMAIL_ENABLED = true si variable d'environnement définie, sinon false
define('EMAIL_ENABLED', getenv('EMAIL_ENABLED') === 'true' || getenv('EMAIL_ENABLED') === '1');
define('EMAIL_DEBUG', getenv('EMAIL_DEBUG') !== 'false'); // Debug activé par défaut

// Email de l'administrateur système
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@dppg.cm');

return [
    'smtp' => [
        'host' => SMTP_HOST,
        'port' => SMTP_PORT,
        'username' => SMTP_USERNAME,
        'password' => SMTP_PASSWORD,
        'secure' => SMTP_SECURE
    ],
    'from' => [
        'email' => EMAIL_FROM,
        'name' => EMAIL_FROM_NAME
    ],
    'enabled' => EMAIL_ENABLED,
    'debug' => EMAIL_DEBUG,
    'admin_email' => ADMIN_EMAIL
];

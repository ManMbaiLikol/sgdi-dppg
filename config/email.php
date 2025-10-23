<?php
// Configuration email pour le SGDI
// Utilise les variables d'environnement (Railway) ou valeurs par défaut (local)

// Paramètres SMTP depuis variables d'environnement
if (!defined('SMTP_HOST')) define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'votre-email@domaine.com');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'votre-mot-de-passe');
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls'); // tls ou ssl

// Expéditeur par défaut
if (!defined('EMAIL_FROM')) define('EMAIL_FROM', getenv('EMAIL_FROM') ?: 'noreply@dppg.cm');
if (!defined('EMAIL_FROM_NAME')) define('EMAIL_FROM_NAME', getenv('EMAIL_FROM_NAME') ?: 'SGDI - MINEE/DPPG');

// Paramètres généraux
// EMAIL_ENABLED = true si variable d'environnement définie, sinon false
if (!defined('EMAIL_ENABLED')) define('EMAIL_ENABLED', getenv('EMAIL_ENABLED') === 'true' || getenv('EMAIL_ENABLED') === '1');
if (!defined('EMAIL_DEBUG')) define('EMAIL_DEBUG', getenv('EMAIL_DEBUG') === 'true' || getenv('EMAIL_DEBUG') === '1'); // Debug désactivé par défaut

// Email de l'administrateur système
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@dppg.cm');

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

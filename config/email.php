<?php
// Configuration email pour le SGDI

// Paramètres SMTP
define('SMTP_HOST', 'smtp.gmail.com'); // Modifier selon votre serveur SMTP
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'votre-email@domaine.com'); // À configurer
define('SMTP_PASSWORD', 'votre-mot-de-passe'); // À configurer
define('SMTP_SECURE', 'tls'); // tls ou ssl

// Expéditeur par défaut
define('EMAIL_FROM', 'noreply@dppg.cm');
define('EMAIL_FROM_NAME', 'SGDI - MINEE/DPPG');

// Paramètres généraux
define('EMAIL_ENABLED', false); // Mettre à true pour activer l'envoi réel d'emails
define('EMAIL_DEBUG', true); // Activer le mode debug pour les tests

// Email de l'administrateur système
define('ADMIN_EMAIL', 'admin@dppg.cm');

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

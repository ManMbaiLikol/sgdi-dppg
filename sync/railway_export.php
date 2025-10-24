<?php
/**
 * Script qui s'exécute SUR Railway pour exporter la base de données
 * Ce script est appelé via: railway run php sync/railway_export.php > backup.sql
 */

// Récupérer les variables d'environnement Railway
$host = getenv('MYSQL_HOST');
$port = getenv('MYSQL_PORT') ?: '3306';
$user = getenv('MYSQL_USER');
$password = getenv('MYSQL_PASSWORD');
$database = getenv('MYSQL_DATABASE');

if (!$host || !$user || !$database) {
    fwrite(STDERR, "ERREUR: Variables d'environnement manquantes!\n");
    fwrite(STDERR, "MYSQL_HOST: " . ($host ?: 'NON DÉFINI') . "\n");
    fwrite(STDERR, "MYSQL_PORT: " . ($port ?: 'NON DÉFINI') . "\n");
    fwrite(STDERR, "MYSQL_USER: " . ($user ?: 'NON DÉFINI') . "\n");
    fwrite(STDERR, "MYSQL_PASSWORD: " . ($password ? '[DÉFINI]' : 'NON DÉFINI') . "\n");
    fwrite(STDERR, "MYSQL_DATABASE: " . ($database ?: 'NON DÉFINI') . "\n");
    exit(1);
}

// Construire la commande mysqldump
$cmd = sprintf(
    'mysqldump -h %s -P %s -u %s -p%s %s --single-transaction --routines --triggers --events 2>&1',
    escapeshellarg($host),
    escapeshellarg($port),
    escapeshellarg($user),
    escapeshellarg($password),
    escapeshellarg($database)
);

// Exécuter mysqldump et rediriger vers stdout
passthru($cmd, $return_code);

if ($return_code !== 0) {
    fwrite(STDERR, "\nERREUR: mysqldump a échoué avec le code $return_code\n");
    exit(1);
}

// Succès - le dump SQL est sur stdout
exit(0);
?>

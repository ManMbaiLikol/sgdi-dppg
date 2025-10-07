<?php
/**
 * Script CRON pour la sauvegarde automatique de la base de données
 * À exécuter tous les jours à 2h du matin
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/database.php';

$log_file = ROOT_PATH . '/logs/backup_' . date('Y-m') . '.log';
$log_dir = dirname($log_file);

if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== Début de la sauvegarde de la base de données ===");

try {
    // Configuration
    $backup_dir = ROOT_PATH . '/backups/database';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    // Nom du fichier de backup
    $backup_file = $backup_dir . '/sgdi_backup_' . date('Y-m-d_His') . '.sql';

    // Récupérer les paramètres de connexion
    $db_host = DB_HOST;
    $db_name = DB_NAME;
    $db_user = DB_USER;
    $db_pass = DB_PASSWORD;

    logMessage("Base de données: $db_name");
    logMessage("Fichier de sauvegarde: " . basename($backup_file));

    // Commande mysqldump
    $command = sprintf(
        'mysqldump --host=%s --user=%s --password=%s %s > %s 2>&1',
        escapeshellarg($db_host),
        escapeshellarg($db_user),
        escapeshellarg($db_pass),
        escapeshellarg($db_name),
        escapeshellarg($backup_file)
    );

    // Exécuter la sauvegarde
    $output = [];
    $return_var = 0;
    exec($command, $output, $return_var);

    if ($return_var === 0 && file_exists($backup_file)) {
        $file_size = filesize($backup_file);
        logMessage("Sauvegarde créée avec succès: " . round($file_size/1024/1024, 2) . " MB");

        // Compresser le fichier
        $compressed = $backup_file . '.gz';
        $gzCommand = sprintf('gzip %s', escapeshellarg($backup_file));
        exec($gzCommand, $output, $return_var);

        if (file_exists($compressed)) {
            $compressed_size = filesize($compressed);
            logMessage("Fichier compressé: " . round($compressed_size/1024/1024, 2) . " MB");
            $backup_file = $compressed;
        }

        // Supprimer les backups de plus de 30 jours
        $retention_days = 30;
        $cutoff_time = time() - ($retention_days * 24 * 60 * 60);
        $old_backups = glob($backup_dir . '/sgdi_backup_*.sql*');

        $deleted_count = 0;
        foreach ($old_backups as $old_backup) {
            if (filemtime($old_backup) < $cutoff_time) {
                if (unlink($old_backup)) {
                    $deleted_count++;
                    logMessage("Ancien backup supprimé: " . basename($old_backup));
                }
            }
        }

        if ($deleted_count > 0) {
            logMessage("Anciens backups supprimés: $deleted_count");
        }

        logMessage("=== Sauvegarde terminée avec succès ===\n");
        exit(0);

    } else {
        logMessage("ERREUR: Échec de la création du backup");
        logMessage("Code retour: $return_var");
        if (!empty($output)) {
            logMessage("Output: " . implode("\n", $output));
        }
        logMessage("=== Sauvegarde terminée avec erreur ===\n");
        exit(1);
    }

} catch (Exception $e) {
    logMessage("ERREUR EXCEPTION: " . $e->getMessage());
    logMessage("=== Sauvegarde terminée avec erreur ===\n");
    exit(1);
}
?>

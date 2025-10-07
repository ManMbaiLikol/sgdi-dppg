<?php
/**
 * Script CRON pour le nettoyage des logs anciens
 * À exécuter tous les jours à 3h du matin
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/database.php';

$log_file = ROOT_PATH . '/logs/nettoyage_' . date('Y-m') . '.log';
$log_dir = dirname($log_file);

if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== Début du nettoyage des logs ===");

try {
    // 1. Supprimer les fichiers de logs de plus de 90 jours
    $logs_dir = ROOT_PATH . '/logs';
    $retention_days = 90;
    $cutoff_time = time() - ($retention_days * 24 * 60 * 60);

    $deleted_files = 0;
    $freed_space = 0;

    if (is_dir($logs_dir)) {
        $files = glob($logs_dir . '/*.log');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                $size = filesize($file);
                if (unlink($file)) {
                    $deleted_files++;
                    $freed_space += $size;
                    logMessage("Supprimé: " . basename($file) . " (" . round($size/1024, 2) . " KB)");
                }
            }
        }
    }

    logMessage("Fichiers de logs supprimés: $deleted_files");
    logMessage("Espace libéré: " . round($freed_space/1024/1024, 2) . " MB");

    // 2. Nettoyer la table logs_activite (garder 6 mois)
    $sql = "DELETE FROM logs_activite WHERE date_action < DATE_SUB(NOW(), INTERVAL 6 MONTH)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $deleted_logs = $stmt->rowCount();
    logMessage("Logs activité supprimés: $deleted_logs entrées");

    // 3. Archiver les anciennes notifications (plus de 3 mois)
    $sql_archive = "UPDATE notifications SET archived = 1
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)
                    AND archived = 0";
    $stmt = $pdo->prepare($sql_archive);
    $stmt->execute();
    $archived_notifs = $stmt->rowCount();
    logMessage("Notifications archivées: $archived_notifs");

    // 4. Supprimer les notifications archivées de plus de 1 an
    $sql_delete = "DELETE FROM notifications
                   WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
                   AND archived = 1";
    $stmt = $pdo->prepare($sql_delete);
    $stmt->execute();
    $deleted_notifs = $stmt->rowCount();
    logMessage("Notifications supprimées: $deleted_notifs");

    // 5. Optimiser les tables
    logMessage("Optimisation des tables...");
    $tables = ['logs_activite', 'notifications', 'historique', 'visas'];
    foreach ($tables as $table) {
        $pdo->exec("OPTIMIZE TABLE $table");
        logMessage("Table optimisée: $table");
    }

    logMessage("=== Nettoyage terminé avec succès ===\n");
    exit(0);

} catch (Exception $e) {
    logMessage("ERREUR: " . $e->getMessage());
    logMessage("=== Nettoyage terminé avec erreur ===\n");
    exit(1);
}
?>

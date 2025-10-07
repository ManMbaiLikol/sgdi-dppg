<?php
/**
 * Fonctions de sauvegarde et restauration de la base de données
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Créer un backup de la base de données
 *
 * @param string $type Type de backup (manuel, automatique, avant_maj)
 * @param int|null $user_id ID de l'utilisateur créateur
 * @return array Résultat du backup
 */
function createDatabaseBackup($type = 'manuel', $user_id = null) {
    global $conn;

    $start_time = microtime(true);
    $backup_dir = __DIR__ . '/../backups';

    // Créer le dossier de backups s'il n'existe pas
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    // Nom du fichier avec timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "sgdi_backup_{$timestamp}.sql";
    $filepath = $backup_dir . '/' . $filename;

    try {
        // Enregistrer le backup dans la table
        $stmt = $conn->prepare("
            INSERT INTO backups (type, nom_fichier, chemin_fichier, statut, created_by)
            VALUES (?, ?, ?, 'en_cours', ?)
        ");
        $stmt->execute([$type, $filename, $filepath, $user_id]);
        $backup_id = $conn->lastInsertId();

        // Ouvrir le fichier
        $handle = fopen($filepath, 'w');
        if (!$handle) {
            throw new Exception("Impossible de créer le fichier de backup");
        }

        // Header du fichier SQL
        fwrite($handle, "-- ============================================================================\n");
        fwrite($handle, "-- SGDI Database Backup\n");
        fwrite($handle, "-- Date: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Type: {$type}\n");
        fwrite($handle, "-- ============================================================================\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        // Récupérer toutes les tables
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $total_rows = 0;

        // Sauvegarder chaque table
        foreach ($tables as $table) {
            // Structure de la table
            fwrite($handle, "\n-- ============================================================================\n");
            fwrite($handle, "-- Table: {$table}\n");
            fwrite($handle, "-- ============================================================================\n\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");

            $create_table = $conn->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            fwrite($handle, $create_table['Create Table'] . ";\n\n");

            // Données de la table
            $rows = $conn->query("SELECT * FROM `{$table}`");
            $num_rows = $rows->rowCount();

            if ($num_rows > 0) {
                fwrite($handle, "-- Données de la table {$table} ({$num_rows} lignes)\n");

                $columns = $conn->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
                $column_list = '`' . implode('`, `', $columns) . '`';

                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $conn->quote($value) . "'";
                        }
                    }

                    fwrite($handle, "INSERT INTO `{$table}` ({$column_list}) VALUES (" . implode(', ', $values) . ");\n");
                    $total_rows++;
                }

                fwrite($handle, "\n");
            }
        }

        // Footer
        fwrite($handle, "\n-- ============================================================================\n");
        fwrite($handle, "-- Fin du backup\n");
        fwrite($handle, "-- Total tables: " . count($tables) . "\n");
        fwrite($handle, "-- Total lignes: {$total_rows}\n");
        fwrite($handle, "-- ============================================================================\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");

        fclose($handle);

        // Calculer la taille du fichier
        $filesize_mo = filesize($filepath) / 1024 / 1024;

        // Calculer la durée
        $duration = round(microtime(true) - $start_time);

        // Mettre à jour le backup
        $stmt = $conn->prepare("
            UPDATE backups
            SET statut = 'termine',
                taille_mo = ?,
                nb_tables = ?,
                nb_lignes = ?,
                duree_secondes = ?
            WHERE id = ?
        ");
        $stmt->execute([$filesize_mo, count($tables), $total_rows, $duration, $backup_id]);

        // Nettoyer les anciens backups
        cleanOldBackups();

        return [
            'success' => true,
            'backup_id' => $backup_id,
            'filename' => $filename,
            'filepath' => $filepath,
            'size_mo' => $filesize_mo,
            'tables' => count($tables),
            'rows' => $total_rows,
            'duration' => $duration
        ];

    } catch (Exception $e) {
        // Enregistrer l'erreur
        if (isset($backup_id)) {
            $stmt = $conn->prepare("
                UPDATE backups
                SET statut = 'erreur',
                    message_erreur = ?
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $backup_id]);
        }

        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Restaurer un backup
 *
 * @param int $backup_id ID du backup à restaurer
 * @return array Résultat de la restauration
 */
function restoreDatabaseBackup($backup_id) {
    global $conn;

    try {
        // Récupérer les informations du backup
        $stmt = $conn->prepare("SELECT * FROM backups WHERE id = ?");
        $stmt->execute([$backup_id]);
        $backup = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$backup) {
            throw new Exception("Backup introuvable");
        }

        if (!file_exists($backup['chemin_fichier'])) {
            throw new Exception("Fichier de backup introuvable");
        }

        // Créer un backup de sécurité avant restauration
        $security_backup = createDatabaseBackup('avant_maj', $_SESSION['user_id'] ?? null);

        if (!$security_backup['success']) {
            throw new Exception("Impossible de créer le backup de sécurité");
        }

        // Lire et exécuter le fichier SQL
        $sql = file_get_contents($backup['chemin_fichier']);

        // Exécuter les requêtes
        $conn->exec($sql);

        return [
            'success' => true,
            'message' => 'Restauration effectuée avec succès',
            'security_backup' => $security_backup['filename']
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Nettoyer les anciens backups selon la rétention configurée
 */
function cleanOldBackups() {
    global $conn;

    // Récupérer la durée de rétention
    $retention = getParametre('backup_retention_jours', 30);

    // Trouver les backups à supprimer
    $stmt = $conn->prepare("
        SELECT id, chemin_fichier
        FROM backups
        WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND type = 'automatique'
    ");
    $stmt->execute([$retention]);
    $old_backups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($old_backups as $backup) {
        // Supprimer le fichier
        if (file_exists($backup['chemin_fichier'])) {
            unlink($backup['chemin_fichier']);
        }

        // Supprimer l'entrée en base
        $stmt = $conn->prepare("DELETE FROM backups WHERE id = ?");
        $stmt->execute([$backup['id']]);
    }

    return count($old_backups);
}

/**
 * Lister les backups disponibles
 *
 * @param int $limit Nombre de backups à retourner
 * @return array Liste des backups
 */
function listBackups($limit = 50) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT
            b.*,
            CONCAT(u.prenom, ' ', u.nom) as createur
        FROM backups b
        LEFT JOIN users u ON b.created_by = u.id
        ORDER BY b.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Télécharger un backup
 *
 * @param int $backup_id ID du backup
 */
function downloadBackup($backup_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM backups WHERE id = ?");
    $stmt->execute([$backup_id]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$backup || !file_exists($backup['chemin_fichier'])) {
        die('Backup introuvable');
    }

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $backup['nom_fichier'] . '"');
    header('Content-Length: ' . filesize($backup['chemin_fichier']));

    readfile($backup['chemin_fichier']);
    exit;
}

/**
 * Récupérer un paramètre système
 *
 * @param string $cle Clé du paramètre
 * @param mixed $default Valeur par défaut
 * @return mixed Valeur du paramètre
 */
function getParametre($cle, $default = null) {
    global $conn;

    $stmt = $conn->prepare("SELECT valeur, type FROM parametres_systeme WHERE cle = ?");
    $stmt->execute([$cle]);
    $param = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$param) {
        return $default;
    }

    // Convertir selon le type
    switch ($param['type']) {
        case 'boolean':
            return $param['valeur'] === 'true';
        case 'number':
            return is_numeric($param['valeur']) ? floatval($param['valeur']) : $default;
        case 'json':
            return json_decode($param['valeur'], true);
        default:
            return $param['valeur'];
    }
}

/**
 * Définir un paramètre système
 *
 * @param string $cle Clé du paramètre
 * @param mixed $valeur Valeur du paramètre
 * @param int|null $user_id ID de l'utilisateur
 * @return bool Succès
 */
function setParametre($cle, $valeur, $user_id = null) {
    global $conn;

    // Convertir la valeur en string selon le type
    if (is_bool($valeur)) {
        $valeur = $valeur ? 'true' : 'false';
    } elseif (is_array($valeur)) {
        $valeur = json_encode($valeur);
    }

    $stmt = $conn->prepare("
        UPDATE parametres_systeme
        SET valeur = ?, updated_by = ?
        WHERE cle = ?
    ");

    return $stmt->execute([$valeur, $user_id, $cle]);
}

/**
 * Tâche CRON pour backup automatique
 * À exécuter quotidiennement via cron
 */
function cronAutoBackup() {
    $enabled = getParametre('backup_auto_enabled', false);

    if (!$enabled) {
        echo "Backup automatique désactivé\n";
        return;
    }

    $heure = getParametre('backup_auto_heure', '02:00');
    $current_time = date('H:i');

    // Vérifier si c'est l'heure du backup
    if (abs(strtotime($current_time) - strtotime($heure)) > 300) { // 5 minutes de tolérance
        echo "Pas l'heure du backup (configuré: {$heure}, actuel: {$current_time})\n";
        return;
    }

    echo "Démarrage du backup automatique...\n";
    $result = createDatabaseBackup('automatique', null);

    if ($result['success']) {
        echo "✅ Backup créé avec succès\n";
        echo "Fichier: {$result['filename']}\n";
        echo "Taille: {$result['size_mo']} Mo\n";
        echo "Tables: {$result['tables']}\n";
        echo "Lignes: {$result['rows']}\n";
        echo "Durée: {$result['duration']}s\n";
    } else {
        echo "❌ Erreur: {$result['error']}\n";
    }
}

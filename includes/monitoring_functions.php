<?php
/**
 * Fonctions de monitoring et surveillance système
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/backup_functions.php';

/**
 * Collecter les métriques système
 *
 * @return array Métriques collectées
 */
function collectSystemMetrics() {
    global $conn;

    $metrics = [];

    // 1. Métriques base de données
    $db_metrics = collectDatabaseMetrics();
    $metrics = array_merge($metrics, $db_metrics);

    // 2. Métriques fichiers
    $file_metrics = collectFileMetrics();
    $metrics = array_merge($metrics, $file_metrics);

    // 3. Métriques sessions
    $session_metrics = collectSessionMetrics();
    $metrics = array_merge($metrics, $session_metrics);

    // 4. Métriques applicatives
    $app_metrics = collectApplicationMetrics();
    $metrics = array_merge($metrics, $app_metrics);

    // Enregistrer toutes les métriques
    foreach ($metrics as $metric) {
        saveMetric(
            $metric['type'],
            $metric['nom'],
            $metric['valeur'],
            $metric['unite'] ?? null,
            $metric['seuil'] ?? null,
            $metric['niveau'] ?? 'info',
            $metric['message'] ?? null
        );
    }

    return $metrics;
}

/**
 * Collecter métriques base de données
 */
function collectDatabaseMetrics() {
    global $conn;

    $metrics = [];

    try {
        // Taille de la base
        $stmt = $conn->query("
            SELECT
                SUM(data_length + index_length) / 1024 / 1024 as size_mb
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
        ");
        $size = $stmt->fetch(PDO::FETCH_ASSOC);

        $metrics[] = [
            'type' => 'disque',
            'nom' => 'Taille base de données',
            'valeur' => round($size['size_mb'], 2),
            'unite' => 'Mo',
            'seuil' => 5000,
            'niveau' => $size['size_mb'] > 5000 ? 'warning' : 'info',
            'message' => 'Taille de la base: ' . round($size['size_mb'], 2) . ' Mo'
        ];

        // Nombre de connexions actives
        $stmt = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
        $threads = $stmt->fetch(PDO::FETCH_ASSOC);

        $metrics[] = [
            'type' => 'requetes',
            'nom' => 'Connexions actives',
            'valeur' => $threads['Value'],
            'unite' => 'connexions',
            'seuil' => 50,
            'niveau' => $threads['Value'] > 50 ? 'warning' : 'info'
        ];

        // Requêtes lentes
        $stmt = $conn->query("SHOW STATUS LIKE 'Slow_queries'");
        $slow = $stmt->fetch(PDO::FETCH_ASSOC);

        $metrics[] = [
            'type' => 'requetes',
            'nom' => 'Requêtes lentes',
            'valeur' => $slow['Value'],
            'unite' => 'requêtes',
            'seuil' => 100,
            'niveau' => $slow['Value'] > 100 ? 'warning' : 'info'
        ];

        // Nombre de dossiers par statut
        $stmt = $conn->query("
            SELECT
                s.libelle,
                COUNT(*) as nb
            FROM dossiers d
            INNER JOIN statuts_dossier s ON d.statut_id = s.id
            WHERE d.archive = 0
            GROUP BY s.id, s.libelle
        ");
        $statuts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($statuts as $statut) {
            $metrics[] = [
                'type' => 'autre',
                'nom' => 'Dossiers ' . $statut['libelle'],
                'valeur' => $statut['nb'],
                'unite' => 'dossiers',
                'niveau' => 'info'
            ];
        }

    } catch (Exception $e) {
        $metrics[] = [
            'type' => 'erreurs',
            'nom' => 'Erreur collecte DB',
            'valeur' => 1,
            'niveau' => 'critical',
            'message' => $e->getMessage()
        ];
    }

    return $metrics;
}

/**
 * Collecter métriques fichiers
 */
function collectFileMetrics() {
    $metrics = [];

    try {
        // Espace disque utilisé par les uploads
        $upload_dir = __DIR__ . '/../uploads';
        $total_size = 0;
        $file_count = 0;

        if (is_dir($upload_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($upload_dir)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $total_size += $file->getSize();
                    $file_count++;
                }
            }
        }

        $size_mb = $total_size / 1024 / 1024;

        $metrics[] = [
            'type' => 'disque',
            'nom' => 'Fichiers uploadés',
            'valeur' => round($size_mb, 2),
            'unite' => 'Mo',
            'seuil' => 10000,
            'niveau' => $size_mb > 10000 ? 'warning' : 'info',
            'message' => "{$file_count} fichiers, " . round($size_mb, 2) . " Mo"
        ];

        // Espace disque backups
        $backup_dir = __DIR__ . '/../backups';
        $backup_size = 0;
        $backup_count = 0;

        if (is_dir($backup_dir)) {
            $files = glob($backup_dir . '/*.sql');
            foreach ($files as $file) {
                $backup_size += filesize($file);
                $backup_count++;
            }
        }

        $backup_mb = $backup_size / 1024 / 1024;

        $metrics[] = [
            'type' => 'disque',
            'nom' => 'Backups',
            'valeur' => round($backup_mb, 2),
            'unite' => 'Mo',
            'seuil' => 5000,
            'niveau' => $backup_mb > 5000 ? 'warning' : 'info',
            'message' => "{$backup_count} backups, " . round($backup_mb, 2) . " Mo"
        ];

        // Espace disque total disponible
        $disk_free = disk_free_space(__DIR__);
        $disk_total = disk_total_space(__DIR__);
        $disk_used_percent = (($disk_total - $disk_free) / $disk_total) * 100;

        $metrics[] = [
            'type' => 'disque',
            'nom' => 'Utilisation disque',
            'valeur' => round($disk_used_percent, 2),
            'unite' => '%',
            'seuil' => 85,
            'niveau' => $disk_used_percent > 85 ? 'critical' : ($disk_used_percent > 70 ? 'warning' : 'info'),
            'message' => round($disk_used_percent, 2) . '% utilisé'
        ];

    } catch (Exception $e) {
        $metrics[] = [
            'type' => 'erreurs',
            'nom' => 'Erreur collecte fichiers',
            'valeur' => 1,
            'niveau' => 'critical',
            'message' => $e->getMessage()
        ];
    }

    return $metrics;
}

/**
 * Collecter métriques sessions
 */
function collectSessionMetrics() {
    global $conn;

    $metrics = [];

    try {
        // Sessions actives (dernière activité < 30 min)
        $stmt = $conn->query("
            SELECT COUNT(DISTINCT user_id) as nb
            FROM sessions
            WHERE derniere_activite > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $metrics[] = [
            'type' => 'sessions',
            'nom' => 'Utilisateurs connectés',
            'valeur' => $result['nb'] ?? 0,
            'unite' => 'utilisateurs',
            'niveau' => 'info'
        ];

        // Sessions par rôle
        $stmt = $conn->query("
            SELECT
                r.nom as role,
                COUNT(DISTINCT s.user_id) as nb
            FROM sessions s
            INNER JOIN users u ON s.user_id = u.id
            INNER JOIN user_roles ur ON u.id = ur.user_id
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE s.derniere_activite > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            GROUP BY r.id, r.nom
        ");
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($roles as $role) {
            $metrics[] = [
                'type' => 'sessions',
                'nom' => 'Sessions ' . $role['role'],
                'valeur' => $role['nb'],
                'unite' => 'sessions',
                'niveau' => 'info'
            ];
        }

    } catch (Exception $e) {
        $metrics[] = [
            'type' => 'erreurs',
            'nom' => 'Erreur collecte sessions',
            'valeur' => 1,
            'niveau' => 'critical',
            'message' => $e->getMessage()
        ];
    }

    return $metrics;
}

/**
 * Collecter métriques applicatives
 */
function collectApplicationMetrics() {
    global $conn;

    $metrics = [];

    try {
        // Huitaines urgentes/expirées
        $stmt = $conn->query("
            SELECT
                SUM(CASE WHEN jours_restants < 0 THEN 1 ELSE 0 END) as expires,
                SUM(CASE WHEN jours_restants BETWEEN 0 AND 2 THEN 1 ELSE 0 END) as urgents
            FROM v_huitaines_actives
        ");
        $huitaines = $stmt->fetch(PDO::FETCH_ASSOC);

        $metrics[] = [
            'type' => 'autre',
            'nom' => 'Huitaines expirées',
            'valeur' => $huitaines['expires'] ?? 0,
            'unite' => 'huitaines',
            'seuil' => 5,
            'niveau' => ($huitaines['expires'] ?? 0) > 0 ? 'critical' : 'info'
        ];

        $metrics[] = [
            'type' => 'autre',
            'nom' => 'Huitaines urgentes',
            'valeur' => $huitaines['urgents'] ?? 0,
            'unite' => 'huitaines',
            'seuil' => 10,
            'niveau' => ($huitaines['urgents'] ?? 0) > 5 ? 'warning' : 'info'
        ];

        // Erreurs récentes (dernières 24h)
        $stmt = $conn->query("
            SELECT COUNT(*) as nb
            FROM logs_activite
            WHERE action = 'erreur'
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $erreurs = $stmt->fetch(PDO::FETCH_ASSOC);

        $metrics[] = [
            'type' => 'erreurs',
            'nom' => 'Erreurs 24h',
            'valeur' => $erreurs['nb'] ?? 0,
            'unite' => 'erreurs',
            'seuil' => 50,
            'niveau' => ($erreurs['nb'] ?? 0) > 50 ? 'warning' : 'info'
        ];

        // Notifications non lues
        $stmt = $conn->query("
            SELECT COUNT(*) as nb
            FROM notifications
            WHERE lue = 0
        ");
        $notifs = $stmt->fetch(PDO::FETCH_ASSOC);

        $metrics[] = [
            'type' => 'autre',
            'nom' => 'Notifications non lues',
            'valeur' => $notifs['nb'] ?? 0,
            'unite' => 'notifications',
            'niveau' => 'info'
        ];

        // Mémoire PHP
        $memory_usage = memory_get_usage(true) / 1024 / 1024;
        $memory_limit = ini_get('memory_limit');

        $metrics[] = [
            'type' => 'memoire',
            'nom' => 'Mémoire PHP utilisée',
            'valeur' => round($memory_usage, 2),
            'unite' => 'Mo',
            'message' => 'Limite: ' . $memory_limit,
            'niveau' => 'info'
        ];

    } catch (Exception $e) {
        $metrics[] = [
            'type' => 'erreurs',
            'nom' => 'Erreur collecte app',
            'valeur' => 1,
            'niveau' => 'critical',
            'message' => $e->getMessage()
        ];
    }

    return $metrics;
}

/**
 * Sauvegarder une métrique
 */
function saveMetric($type, $nom, $valeur, $unite = null, $seuil = null, $niveau = 'info', $message = null) {
    global $conn;

    $donnees = [
        'timestamp_collecte' => date('Y-m-d H:i:s'),
        'serveur' => $_SERVER['SERVER_NAME'] ?? 'localhost'
    ];

    $stmt = $conn->prepare("
        INSERT INTO monitoring_systeme
        (type_metrique, nom_metrique, valeur, unite, seuil_alerte, niveau, message, donnees_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    return $stmt->execute([
        $type,
        $nom,
        $valeur,
        $unite,
        $seuil,
        $niveau,
        $message,
        json_encode($donnees, JSON_UNESCAPED_UNICODE)
    ]);
}

/**
 * Récupérer les alertes actives
 *
 * @param array $niveaux Niveaux à inclure
 * @return array Alertes
 */
function getAlertes($niveaux = ['warning', 'critical']) {
    global $conn;

    $placeholders = implode(',', array_fill(0, count($niveaux), '?'));

    $stmt = $conn->prepare("
        SELECT *
        FROM monitoring_systeme
        WHERE niveau IN ($placeholders)
            AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY
            CASE niveau
                WHEN 'critical' THEN 1
                WHEN 'warning' THEN 2
                ELSE 3
            END,
            timestamp DESC
    ");

    $stmt->execute($niveaux);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupérer l'historique d'une métrique
 *
 * @param string $nom Nom de la métrique
 * @param int $heures Nombre d'heures d'historique
 * @return array Données
 */
function getMetricHistory($nom, $heures = 24) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT
            timestamp,
            valeur,
            unite,
            niveau,
            message
        FROM monitoring_systeme
        WHERE nom_metrique = ?
            AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ORDER BY timestamp ASC
    ");

    $stmt->execute([$nom, $heures]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Dashboard monitoring
 *
 * @return array Données du dashboard
 */
function getMonitoringDashboard() {
    global $conn;

    $dashboard = [];

    // Dernières métriques par type
    $stmt = $conn->query("
        SELECT
            type_metrique,
            COUNT(*) as nb_metriques,
            SUM(CASE WHEN niveau = 'critical' THEN 1 ELSE 0 END) as nb_critical,
            SUM(CASE WHEN niveau = 'warning' THEN 1 ELSE 0 END) as nb_warning
        FROM monitoring_systeme
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY type_metrique
    ");
    $dashboard['par_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Alertes actives
    $dashboard['alertes'] = getAlertes(['warning', 'critical']);

    // Santé globale
    $nb_critical = count(array_filter($dashboard['alertes'], fn($a) => $a['niveau'] === 'critical'));
    $nb_warning = count(array_filter($dashboard['alertes'], fn($a) => $a['niveau'] === 'warning'));

    if ($nb_critical > 0) {
        $dashboard['sante_globale'] = 'critical';
    } elseif ($nb_warning > 5) {
        $dashboard['sante_globale'] = 'warning';
    } else {
        $dashboard['sante_globale'] = 'ok';
    }

    // Statistiques système
    $stmt = $conn->query("
        SELECT
            (SELECT COUNT(*) FROM dossiers WHERE archive = 0) as nb_dossiers_actifs,
            (SELECT COUNT(*) FROM users WHERE actif = 1) as nb_users_actifs,
            (SELECT COUNT(*) FROM notifications WHERE lue = 0) as nb_notifications_non_lues,
            (SELECT COUNT(*) FROM v_huitaines_actives WHERE jours_restants < 0) as nb_huitaines_expirees
    ");
    $dashboard['stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

    return $dashboard;
}

/**
 * Nettoyer les anciennes métriques
 * Garder seulement les N derniers jours
 */
function cleanOldMetrics($retention_jours = 30) {
    global $conn;

    $stmt = $conn->prepare("
        DELETE FROM monitoring_systeme
        WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");

    $stmt->execute([$retention_jours]);

    return $stmt->rowCount();
}

/**
 * Tâche CRON de monitoring
 */
function cronMonitoring() {
    $enabled = getParametre('monitoring_enabled', false);

    if (!$enabled) {
        echo "Monitoring désactivé\n";
        return;
    }

    echo "Collecte des métriques système...\n";

    $metrics = collectSystemMetrics();

    echo "✅ " . count($metrics) . " métriques collectées\n";

    // Vérifier les alertes
    $alertes = getAlertes(['critical', 'warning']);

    if (count($alertes) > 0) {
        echo "⚠️  " . count($alertes) . " alertes détectées:\n";
        foreach ($alertes as $alerte) {
            $icon = $alerte['niveau'] === 'critical' ? '🔴' : '🟡';
            echo "{$icon} {$alerte['nom_metrique']}: {$alerte['valeur']} {$alerte['unite']}\n";
        }
    } else {
        echo "✅ Aucune alerte\n";
    }

    // Nettoyer les anciennes métriques
    $deleted = cleanOldMetrics(30);
    echo "🗑️  {$deleted} anciennes métriques supprimées\n";

    return [
        'metrics_collected' => count($metrics),
        'alertes' => count($alertes),
        'old_deleted' => $deleted
    ];
}

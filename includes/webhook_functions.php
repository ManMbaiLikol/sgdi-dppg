<?php
/**
 * Système de webhooks
 * Permet d'envoyer des notifications vers des URLs externes
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Déclencher un webhook
 *
 * @param string $evenement Nom de l'événement
 * @param array $payload Données à envoyer
 * @return bool Succès
 */
function triggerWebhook($evenement, $payload = []) {
    global $conn;

    // Récupérer les webhooks actifs pour cet événement
    $stmt = $conn->prepare("
        SELECT * FROM webhooks
        WHERE evenement = ?
            AND actif = 1
    ");
    $stmt->execute([$evenement]);
    $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $success_count = 0;

    foreach ($webhooks as $webhook) {
        $result = executeWebhook($webhook, $payload);

        if ($result['success']) {
            $success_count++;
        }
    }

    return $success_count > 0;
}

/**
 * Exécuter un webhook
 *
 * @param array $webhook Configuration du webhook
 * @param array $payload Données
 * @return array Résultat
 */
function executeWebhook($webhook, $payload) {
    global $conn;

    $start_time = microtime(true);

    try {
        // Préparer les headers
        $headers = json_decode($webhook['headers'], true) ?? [];
        $headers['Content-Type'] = 'application/json';
        $headers['User-Agent'] = 'SGDI-Webhook/1.0';

        // Ajouter la signature HMAC si un secret est défini
        if ($webhook['secret']) {
            $signature = hash_hmac('sha256', json_encode($payload), $webhook['secret']);
            $headers['X-Webhook-Signature'] = $signature;
        }

        // Convertir headers en format cURL
        $header_lines = [];
        foreach ($headers as $key => $value) {
            $header_lines[] = "{$key}: {$value}";
        }

        // Préparer la requête cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhook['url']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $webhook['methode']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header_lines);
        curl_setopt($ch, CURLOPT_TIMEOUT, $webhook['timeout_secondes']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        // Exécuter la requête
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $duration_ms = round((microtime(true) - $start_time) * 1000);

        // Déterminer le statut
        $success = ($http_code >= 200 && $http_code < 300);
        $statut = $success ? 'succes' : 'erreur';

        // Logger l'exécution
        $stmt = $conn->prepare("
            INSERT INTO webhooks_logs
            (webhook_id, evenement, payload, statut_http, reponse, duree_ms, erreur)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $webhook['id'],
            $webhook['evenement'],
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $http_code,
            $response,
            $duration_ms,
            $error ?: null
        ]);

        // Mettre à jour les statistiques du webhook
        $stmt = $conn->prepare("
            UPDATE webhooks
            SET derniere_execution = NOW(),
                dernier_statut = ?,
                nb_executions = nb_executions + 1,
                nb_succes = nb_succes + ?,
                nb_erreurs = nb_erreurs + ?
            WHERE id = ?
        ");
        $stmt->execute([
            $statut,
            $success ? 1 : 0,
            $success ? 0 : 1,
            $webhook['id']
        ]);

        // Si échec et retry activé
        if (!$success && $webhook['retry_max'] > 0) {
            scheduleWebhookRetry($webhook, $payload, 1);
        }

        return [
            'success' => $success,
            'http_code' => $http_code,
            'response' => $response,
            'duration_ms' => $duration_ms,
            'error' => $error
        ];

    } catch (Exception $e) {
        $duration_ms = round((microtime(true) - $start_time) * 1000);

        // Logger l'erreur
        $stmt = $conn->prepare("
            INSERT INTO webhooks_logs
            (webhook_id, evenement, payload, statut_http, duree_ms, erreur)
            VALUES (?, ?, ?, 0, ?, ?)
        ");
        $stmt->execute([
            $webhook['id'],
            $webhook['evenement'],
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $duration_ms,
            $e->getMessage()
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'duration_ms' => $duration_ms
        ];
    }
}

/**
 * Planifier un retry de webhook
 *
 * @param array $webhook
 * @param array $payload
 * @param int $attempt Numéro de tentative
 */
function scheduleWebhookRetry($webhook, $payload, $attempt) {
    // Implémentation simplifiée : on pourrait utiliser une queue system
    // Pour l'instant, on réessaie immédiatement avec délai exponentiel

    if ($attempt > $webhook['retry_max']) {
        return;
    }

    // Délai exponentiel : 2^attempt secondes
    $delay = pow(2, $attempt);
    sleep($delay);

    $result = executeWebhook($webhook, $payload);

    // Si toujours échec, retry
    if (!$result['success'] && $attempt < $webhook['retry_max']) {
        scheduleWebhookRetry($webhook, $payload, $attempt + 1);
    }
}

/**
 * Créer un webhook
 *
 * @param array $data Données du webhook
 * @param int|null $user_id ID créateur
 * @return array Résultat
 */
function createWebhook($data, $user_id = null) {
    global $conn;

    validateWebhookData($data);

    $stmt = $conn->prepare("
        INSERT INTO webhooks
        (nom, url, evenement, methode, headers, secret, actif,
         retry_max, timeout_secondes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['nom'],
        $data['url'],
        $data['evenement'],
        $data['methode'] ?? 'POST',
        isset($data['headers']) ? json_encode($data['headers']) : null,
        $data['secret'] ?? null,
        $data['actif'] ?? true,
        $data['retry_max'] ?? 3,
        $data['timeout_secondes'] ?? 30,
        $user_id
    ]);

    return [
        'success' => true,
        'id' => $conn->lastInsertId(),
        'message' => 'Webhook créé'
    ];
}

/**
 * Tester un webhook
 *
 * @param int $webhook_id
 * @return array Résultat
 */
function testWebhook($webhook_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM webhooks WHERE id = ?");
    $stmt->execute([$webhook_id]);
    $webhook = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$webhook) {
        return [
            'success' => false,
            'error' => 'Webhook introuvable'
        ];
    }

    // Payload de test
    $test_payload = [
        'event' => 'test',
        'message' => 'Test webhook SGDI',
        'timestamp' => date('c'),
        'webhook_id' => $webhook_id
    ];

    return executeWebhook($webhook, $test_payload);
}

/**
 * Valider les données de webhook
 *
 * @param array $data
 * @throws Exception
 */
function validateWebhookData($data) {
    $required = ['nom', 'url', 'evenement'];

    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Champ requis: {$field}");
        }
    }

    // Valider l'URL
    if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
        throw new Exception("URL invalide");
    }

    // Valider la méthode HTTP
    $allowed_methods = ['GET', 'POST', 'PUT', 'DELETE'];
    if (isset($data['methode']) && !in_array($data['methode'], $allowed_methods)) {
        throw new Exception("Méthode HTTP invalide");
    }
}

/**
 * Liste des événements disponibles
 *
 * @return array
 */
function getWebhookEvents() {
    return [
        'dossier.created' => 'Nouveau dossier créé',
        'dossier.updated' => 'Dossier mis à jour',
        'dossier.statut_changed' => 'Changement de statut',
        'dossier.deleted' => 'Dossier supprimé',
        'paiement.recorded' => 'Paiement enregistré',
        'inspection.completed' => 'Inspection terminée',
        'huitaine.expired' => 'Huitaine expirée',
        'huitaine.warning' => 'Alerte huitaine (J-2 ou J-1)',
        'commission.created' => 'Commission constituée',
        'visa.granted' => 'Visa accordé',
        'decision.published' => 'Décision publiée',
        'user.created' => 'Nouvel utilisateur',
        'user.deleted' => 'Utilisateur supprimé',
        'notification.sent' => 'Notification envoyée',
        'backup.completed' => 'Backup terminé',
        'system.error' => 'Erreur système'
    ];
}

/**
 * Statistiques des webhooks
 *
 * @return array
 */
function getWebhookStatistics() {
    global $conn;

    $stmt = $conn->query("
        SELECT
            w.nom,
            w.evenement,
            w.actif,
            w.nb_executions,
            w.nb_succes,
            w.nb_erreurs,
            w.derniere_execution,
            w.dernier_statut,
            ROUND(w.nb_succes / NULLIF(w.nb_executions, 0) * 100, 2) as taux_succes
        FROM webhooks w
        ORDER BY w.nb_executions DESC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Logs récents d'un webhook
 *
 * @param int $webhook_id
 * @param int $limit
 * @return array
 */
function getWebhookLogs($webhook_id, $limit = 50) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT *
        FROM webhooks_logs
        WHERE webhook_id = ?
        ORDER BY timestamp DESC
        LIMIT ?
    ");

    $stmt->execute([$webhook_id, $limit]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Nettoyer les anciens logs de webhooks
 *
 * @param int $retention_jours
 * @return int Nombre de logs supprimés
 */
function cleanWebhookLogs($retention_jours = 90) {
    global $conn;

    $stmt = $conn->prepare("
        DELETE FROM webhooks_logs
        WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");

    $stmt->execute([$retention_jours]);

    return $stmt->rowCount();
}

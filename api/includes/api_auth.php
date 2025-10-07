<?php
/**
 * Authentification API
 */

class ApiException extends Exception {
    private $httpCode;

    public function __construct($message, $code = 0, $httpCode = 400) {
        parent::__construct($message, $code);
        $this->httpCode = $httpCode;
    }

    public function getHttpCode() {
        return $this->httpCode;
    }
}

/**
 * Authentifier une requête API
 *
 * @return array Informations de l'API key
 * @throws ApiException
 */
function authenticateRequest() {
    global $conn;

    // Récupérer la clé API
    $api_key = null;

    // Méthode 1: Header Authorization Bearer
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.+)/', $headers['Authorization'], $matches)) {
            $api_key = $matches[1];
        }
    }

    // Méthode 2: Header X-API-Key
    if (!$api_key && isset($headers['X-API-Key'])) {
        $api_key = $headers['X-API-Key'];
    }

    // Méthode 3: Query parameter (déconseillé)
    if (!$api_key && isset($_GET['api_key'])) {
        $api_key = $_GET['api_key'];
    }

    if (!$api_key) {
        throw new ApiException('Clé API manquante', 1, 401);
    }

    // Valider la clé API
    $stmt = $conn->prepare("
        SELECT
            ak.*,
            u.id as user_id,
            u.email,
            u.actif as user_actif
        FROM api_keys ak
        LEFT JOIN users u ON ak.user_id = u.id
        WHERE ak.cle = ?
            AND ak.actif = 1
    ");
    $stmt->execute([$api_key]);
    $key_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$key_data) {
        throw new ApiException('Clé API invalide', 2, 401);
    }

    // Vérifier l'expiration
    if ($key_data['expire_at'] && strtotime($key_data['expire_at']) < time()) {
        throw new ApiException('Clé API expirée', 3, 401);
    }

    // Vérifier les IP autorisées
    if ($key_data['ip_autorisees']) {
        $client_ip = $_SERVER['REMOTE_ADDR'];
        $allowed_ips = explode(',', $key_data['ip_autorisees']);
        $allowed_ips = array_map('trim', $allowed_ips);

        if (!in_array($client_ip, $allowed_ips)) {
            throw new ApiException('IP non autorisée', 4, 403);
        }
    }

    // Mettre à jour la dernière utilisation
    $stmt = $conn->prepare("
        UPDATE api_keys
        SET derniere_utilisation = NOW(),
            nb_requetes_total = nb_requetes_total + 1
        WHERE id = ?
    ");
    $stmt->execute([$key_data['id']]);

    // Décoder les permissions
    $key_data['permissions'] = json_decode($key_data['permissions'], true) ?? [];

    return $key_data;
}

/**
 * Vérifier le rate limiting
 *
 * @param array $api_key Données de la clé API
 * @throws ApiException
 */
function checkRateLimit($api_key) {
    global $conn;

    $limit_per_hour = $api_key['rate_limit_par_heure'] ?? 1000;

    // Compter les requêtes de la dernière heure
    $stmt = $conn->prepare("
        SELECT COUNT(*) as nb
        FROM api_logs
        WHERE api_key_id = ?
            AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$api_key['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $requests_count = $result['nb'];

    // Headers de rate limit
    header("X-RateLimit-Limit: {$limit_per_hour}");
    header("X-RateLimit-Remaining: " . max(0, $limit_per_hour - $requests_count));
    header("X-RateLimit-Reset: " . date('c', strtotime('+1 hour', strtotime(date('Y-m-d H:00:00')))));

    if ($requests_count >= $limit_per_hour) {
        throw new ApiException('Rate limit dépassé', 5, 429);
    }
}

/**
 * Vérifier une permission
 *
 * @param array $api_key Données de la clé
 * @param string $permission Permission requise
 * @return bool
 */
function hasPermission($api_key, $permission) {
    // Si pas de permissions définies, accès complet
    if (empty($api_key['permissions'])) {
        return true;
    }

    // Vérifier la permission spécifique
    if (in_array($permission, $api_key['permissions'])) {
        return true;
    }

    // Vérifier wildcard
    $parts = explode('.', $permission);
    if (count($parts) > 1) {
        $wildcard = $parts[0] . '.*';
        if (in_array($wildcard, $api_key['permissions'])) {
            return true;
        }
    }

    return false;
}

/**
 * Requiert une permission
 *
 * @param array $api_key
 * @param string $permission
 * @throws ApiException
 */
function requirePermission($api_key, $permission) {
    if (!hasPermission($api_key, $permission)) {
        throw new ApiException("Permission insuffisante: {$permission}", 6, 403);
    }
}

/**
 * Logger une requête API
 */
function logApiRequest() {
    global $conn;

    // Récupérer l'API key ID si possible
    $api_key_id = null;
    $headers = getallheaders();
    $api_key_string = null;

    if (isset($headers['Authorization']) && preg_match('/Bearer\s+(.+)/', $headers['Authorization'], $matches)) {
        $api_key_string = $matches[1];
    } elseif (isset($headers['X-API-Key'])) {
        $api_key_string = $headers['X-API-Key'];
    }

    if ($api_key_string) {
        $stmt = $conn->prepare("SELECT id FROM api_keys WHERE cle = ?");
        $stmt->execute([$api_key_string]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $api_key_id = $result['id'] ?? null;
    }

    // Préparer les données
    $endpoint = $_SERVER['REQUEST_URI'] ?? '';
    $methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $ip_client = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    // Paramètres de la requête
    $params = [];
    if ($methode === 'GET') {
        $params = $_GET;
    } else {
        $params = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    // Masquer les données sensibles
    if (isset($params['password'])) $params['password'] = '***';
    if (isset($params['secret'])) $params['secret'] = '***';

    // Note: On n'enregistre pas encore le statut et la réponse
    // Ils seront mis à jour par logApiResponse()

    $GLOBALS['api_log_start_time'] = microtime(true);
    $GLOBALS['api_log_data'] = [
        'api_key_id' => $api_key_id,
        'endpoint' => $endpoint,
        'methode' => $methode,
        'ip_client' => $ip_client,
        'user_agent' => $user_agent,
        'params' => json_encode($params, JSON_UNESCAPED_UNICODE)
    ];
}

/**
 * Logger la réponse API
 *
 * @param int $statut_http
 * @param mixed $reponse
 * @param string|null $erreur
 */
function logApiResponse($statut_http, $reponse = null, $erreur = null) {
    global $conn;

    if (!isset($GLOBALS['api_log_data'])) {
        return;
    }

    $duration_ms = round((microtime(true) - $GLOBALS['api_log_start_time']) * 1000);

    $data = $GLOBALS['api_log_data'];

    $stmt = $conn->prepare("
        INSERT INTO api_logs
        (api_key_id, endpoint, methode, ip_client, user_agent, params,
         statut_http, reponse_json, duree_ms, erreur)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['api_key_id'],
        $data['endpoint'],
        $data['methode'],
        $data['ip_client'],
        $data['user_agent'],
        $data['params'],
        $statut_http,
        json_encode($reponse, JSON_UNESCAPED_UNICODE),
        $duration_ms,
        $erreur
    ]);
}

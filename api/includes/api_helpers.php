<?php
/**
 * Helpers API
 */

/**
 * Envoyer une réponse JSON
 *
 * @param mixed $data Données à envoyer
 * @param int $code Code HTTP
 */
function jsonResponse($data, $code = 200) {
    http_response_code($code);

    $response = [
        'success' => $code >= 200 && $code < 300,
        'data' => $data,
        'timestamp' => date('c'),
        'version' => '1.0'
    ];

    // Logger la réponse
    logApiResponse($code, $data, isset($data['error']) ? $data['error'] : null);

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Récupérer le body JSON de la requête
 *
 * @return array
 */
function getJsonBody() {
    $body = file_get_contents('php://input');
    return json_decode($body, true) ?? [];
}

/**
 * Valider les champs requis
 *
 * @param array $data Données
 * @param array $required Champs requis
 * @throws ApiException
 */
function validateRequired($data, $required) {
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            throw new ApiException("Champ requis manquant: {$field}", 10, 400);
        }
    }
}

/**
 * Paginer des résultats
 *
 * @param array $items Items
 * @param int $page Numéro de page
 * @param int $per_page Items par page
 * @return array
 */
function paginate($items, $page = 1, $per_page = 20) {
    $total = count($items);
    $total_pages = ceil($total / $per_page);
    $page = max(1, min($page, $total_pages));

    $offset = ($page - 1) * $per_page;
    $items_page = array_slice($items, $offset, $per_page);

    return [
        'items' => $items_page,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_items' => $total,
            'total_pages' => $total_pages,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1
        ]
    ];
}

/**
 * Sanitize output data
 *
 * @param mixed $data
 * @return mixed
 */
function sanitizeOutput($data) {
    if (is_array($data)) {
        return array_map('sanitizeOutput', $data);
    }

    if (is_string($data)) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    return $data;
}

/**
 * Filtrer les champs à retourner
 *
 * @param array $item Item
 * @param array $fields Champs à garder
 * @return array
 */
function filterFields($item, $fields) {
    if (empty($fields)) {
        return $item;
    }

    $filtered = [];
    foreach ($fields as $field) {
        if (isset($item[$field])) {
            $filtered[$field] = $item[$field];
        }
    }

    return $filtered;
}

/**
 * Récupérer un paramètre système (version API)
 */
function getParametre($cle, $default = null) {
    global $conn;

    $stmt = $conn->prepare("SELECT valeur, type FROM parametres_systeme WHERE cle = ?");
    $stmt->execute([$cle]);
    $param = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$param) {
        return $default;
    }

    switch ($param['type']) {
        case 'boolean':
            return $param['valeur'] === 'true';
        case 'number':
            return floatval($param['valeur']);
        case 'json':
            return json_decode($param['valeur'], true);
        default:
            return $param['valeur'];
    }
}

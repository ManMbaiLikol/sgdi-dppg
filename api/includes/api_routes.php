<?php
/**
 * Routeur API
 */

require_once __DIR__ . '/../controllers/DossiersController.php';
require_once __DIR__ . '/../controllers/UsersController.php';
require_once __DIR__ . '/../controllers/StatisticsController.php';

/**
 * Router une requête vers le bon contrôleur
 *
 * @param array $api_key Données de l'API key
 * @return mixed Résultat
 */
function routeRequest($api_key) {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = str_replace('/api/', '', $path);
    $path = trim($path, '/');

    $segments = explode('/', $path);
    $resource = $segments[0] ?? '';
    $id = $segments[1] ?? null;
    $action = $segments[2] ?? null;

    // Routes disponibles
    switch ($resource) {
        case '':
        case 'index.php':
            return handleRoot();

        case 'dossiers':
            return handleDossiers($method, $id, $action, $api_key);

        case 'users':
            return handleUsers($method, $id, $action, $api_key);

        case 'statistics':
        case 'stats':
            return handleStatistics($method, $api_key);

        case 'notifications':
            return handleNotifications($method, $id, $api_key);

        case 'documents':
            return handleDocuments($method, $id, $action, $api_key);

        case 'webhooks':
            return handleWebhooks($method, $id, $api_key);

        default:
            throw new ApiException("Endpoint non trouvé: /{$resource}", 404, 404);
    }
}

/**
 * Root endpoint - Documentation
 */
function handleRoot() {
    return [
        'message' => 'Bienvenue sur l\'API SGDI',
        'version' => '1.0',
        'documentation' => '/api/docs',
        'endpoints' => [
            'GET /dossiers' => 'Liste des dossiers',
            'GET /dossiers/{id}' => 'Détails d\'un dossier',
            'POST /dossiers' => 'Créer un dossier',
            'PUT /dossiers/{id}' => 'Mettre à jour un dossier',
            'DELETE /dossiers/{id}' => 'Supprimer un dossier',
            'GET /dossiers/{id}/documents' => 'Documents d\'un dossier',
            'GET /dossiers/{id}/historique' => 'Historique d\'un dossier',
            'GET /users' => 'Liste des utilisateurs',
            'GET /users/{id}' => 'Détails utilisateur',
            'GET /statistics' => 'Statistiques globales',
            'GET /statistics/dashboard' => 'Données dashboard',
            'GET /notifications' => 'Liste des notifications',
            'POST /webhooks/test' => 'Tester un webhook'
        ]
    ];
}

/**
 * Handler dossiers
 */
function handleDossiers($method, $id, $action, $api_key) {
    $controller = new DossiersController($api_key);

    switch ($method) {
        case 'GET':
            if ($id) {
                if ($action === 'documents') {
                    return $controller->getDocuments($id);
                } elseif ($action === 'historique') {
                    return $controller->getHistorique($id);
                } else {
                    return $controller->getOne($id);
                }
            } else {
                return $controller->getAll();
            }

        case 'POST':
            return $controller->create();

        case 'PUT':
            if (!$id) {
                throw new ApiException('ID requis pour la mise à jour', 400, 400);
            }
            return $controller->update($id);

        case 'DELETE':
            if (!$id) {
                throw new ApiException('ID requis pour la suppression', 400, 400);
            }
            return $controller->delete($id);

        default:
            throw new ApiException('Méthode non supportée', 405, 405);
    }
}

/**
 * Handler users
 */
function handleUsers($method, $id, $action, $api_key) {
    $controller = new UsersController($api_key);

    switch ($method) {
        case 'GET':
            if ($id) {
                return $controller->getOne($id);
            } else {
                return $controller->getAll();
            }

        case 'POST':
            return $controller->create();

        case 'PUT':
            if (!$id) {
                throw new ApiException('ID requis', 400, 400);
            }
            return $controller->update($id);

        case 'DELETE':
            if (!$id) {
                throw new ApiException('ID requis', 400, 400);
            }
            return $controller->delete($id);

        default:
            throw new ApiException('Méthode non supportée', 405, 405);
    }
}

/**
 * Handler statistics
 */
function handleStatistics($method, $api_key) {
    requirePermission($api_key, 'statistics.read');

    $controller = new StatisticsController($api_key);

    if ($method !== 'GET') {
        throw new ApiException('Seul GET est supporté', 405, 405);
    }

    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if (strpos($path, '/dashboard') !== false) {
        return $controller->getDashboard();
    } else {
        return $controller->getGlobal();
    }
}

/**
 * Handler notifications
 */
function handleNotifications($method, $id, $api_key) {
    requirePermission($api_key, 'notifications.read');

    global $conn;

    if ($method === 'GET') {
        if ($id) {
            // Une notification spécifique
            $stmt = $conn->prepare("
                SELECT * FROM notifications
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $notif = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$notif) {
                throw new ApiException('Notification introuvable', 404, 404);
            }

            return $notif;
        } else {
            // Liste des notifications
            $user_id = $_GET['user_id'] ?? null;
            $lu = $_GET['lu'] ?? null;
            $limit = min(100, $_GET['limit'] ?? 50);

            $where = ['1=1'];
            $params = [];

            if ($user_id) {
                $where[] = 'user_id = ?';
                $params[] = $user_id;
            }

            if ($lu !== null) {
                $where[] = 'lu = ?';
                $params[] = $lu === 'true' ? 1 : 0;
            }

            $sql = "
                SELECT * FROM notifications
                WHERE " . implode(' AND ', $where) . "
                ORDER BY created_at DESC
                LIMIT ?
            ";
            $params[] = $limit;

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    throw new ApiException('Méthode non supportée', 405, 405);
}

/**
 * Handler documents
 */
function handleDocuments($method, $id, $action, $api_key) {
    requirePermission($api_key, 'documents.read');

    global $conn;

    if ($method === 'GET' && $id) {
        $stmt = $conn->prepare("
            SELECT
                d.*,
                td.nom as type_nom,
                dos.numero_dossier
            FROM documents d
            LEFT JOIN types_document td ON d.type_document_id = td.id
            LEFT JOIN dossiers dos ON d.dossier_id = dos.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            throw new ApiException('Document introuvable', 404, 404);
        }

        // Si action = download, retourner le fichier
        if ($action === 'download') {
            if (!file_exists($doc['chemin_fichier'])) {
                throw new ApiException('Fichier physique introuvable', 404, 404);
            }

            header('Content-Type: ' . $doc['type_mime']);
            header('Content-Disposition: attachment; filename="' . $doc['nom_fichier'] . '"');
            header('Content-Length: ' . filesize($doc['chemin_fichier']));

            readfile($doc['chemin_fichier']);
            exit;
        }

        return $doc;
    }

    throw new ApiException('Méthode non supportée', 405, 405);
}

/**
 * Handler webhooks
 */
function handleWebhooks($method, $id, $api_key) {
    requirePermission($api_key, 'webhooks.manage');

    if ($method === 'POST' && $id === 'test') {
        $body = getJsonBody();
        validateRequired($body, ['webhook_id']);

        require_once __DIR__ . '/../../includes/webhook_functions.php';

        $result = testWebhook($body['webhook_id']);

        return $result;
    }

    throw new ApiException('Endpoint non supporté', 404, 404);
}

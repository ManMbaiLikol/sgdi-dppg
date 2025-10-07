<?php
/**
 * API REST SGDI
 * Point d'entrée principal de l'API
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Gérer les requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/api_auth.php';
require_once __DIR__ . '/includes/api_helpers.php';
require_once __DIR__ . '/includes/api_routes.php';

// Vérifier que l'API est activée
if (!getParametre('api_enabled', true)) {
    jsonResponse(['error' => 'API désactivée'], 503);
}

// Logger la requête
logApiRequest();

try {
    // Authentifier la requête
    $api_key = authenticateRequest();

    // Vérifier le rate limiting
    checkRateLimit($api_key);

    // Router la requête
    $result = routeRequest($api_key);

    // Réponse
    jsonResponse($result);

} catch (ApiException $e) {
    jsonResponse([
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ], $e->getHttpCode());

} catch (Exception $e) {
    // Ne pas exposer les détails des erreurs internes
    jsonResponse([
        'error' => 'Erreur interne du serveur'
    ], 500);

    // Logger l'erreur
    error_log("API Error: " . $e->getMessage());
}

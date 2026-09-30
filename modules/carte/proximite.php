<?php
/**
 * Contrôle de proximité (JSON) : sites protégés par la réglementation autour d'un point
 * GET lat, lon, zone (urbaine|rurale)
 * Les stations-service proches sont calculées dans la page, à partir des données déjà chargées.
 */
require_once '../../includes/auth.php';
require_once '../../includes/contraintes_distance_functions.php';
require_once '../../includes/proximite_functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['erreur' => 'Accès refusé']);
    exit;
}
session_write_close();

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lon = filter_input(INPUT_GET, 'lon', FILTER_VALIDATE_FLOAT);
if ($lat === false || $lon === false || $lat === null || $lon === null || $lat < 1.5 || $lat > 13.5 || $lon < 8 || $lon > 16.5) {
    http_response_code(400);
    echo json_encode(['erreur' => 'Coordonnées invalides (Cameroun : latitude 1,5 à 13,5 ; longitude 8 à 16,5)']);
    exit;
}

set_time_limit(40);
$resultat = proxSitesSensibles($lat, $lon, ($_GET['zone'] ?? '') === 'rurale' ? 'rurale' : 'urbaine');
header('Cache-Control: private, max-age=300');
echo json_encode($resultat, JSON_UNESCAPED_UNICODE);

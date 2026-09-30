<?php
/**
 * Données de la carte des infrastructures (JSON)
 *
 * GET  : infrastructures du SGDI, points d'intérêt et référence OpenStreetMap
 * POST action=synchroniser_osm (admin, chef de service) : resynchronise la référence OSM
 *
 * Format compact pour limiter le volume :
 *   sgdi : [id, lat, lon, type, nature, demandeur, opérateur, ville, région, statut, numéro]
 *   poi  : [lat, lon, nom, catégorie, distance min (m), distance min rurale (m), couleur]
 *   osm  : voir includes/osm_sync.php
 */
require_once '../../includes/auth.php';
require_once '../../includes/map_functions.php';
require_once '../../includes/contraintes_distance_functions.php';
require_once '../../includes/osm_sync.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['erreur' => 'Accès refusé']);
    exit;
}

// Resynchronisation manuelle de la référence OSM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '') || ($_POST['action'] ?? '') !== 'synchroniser_osm') {
        http_response_code(400);
        echo json_encode(['erreur' => 'Requête invalide']);
        exit;
    }
    if (!hasAnyRole(['admin', 'chef_service'])) {
        http_response_code(403);
        echo json_encode(['erreur' => 'Réservé aux administrateurs et au Chef de Service']);
        exit;
    }
    session_write_close(); // ne pas bloquer les autres pages pendant le téléchargement
    set_time_limit(200);
    try {
        $stats = osmSynchroniser();
        echo json_encode(['ok' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        error_log('Synchronisation OSM : ' . $e->getMessage());
        http_response_code(502);
        echo json_encode(['erreur' => 'OpenStreetMap est momentanément indisponible. Réessayez dans quelques minutes.']);
    }
    exit;
}

session_write_close();

// Infrastructures du SGDI (positions des dossiers), région recalculée d'après les coordonnées
$sgdi = [];
foreach (getAllInfrastructuresForMap([]) as $i) {
    $lat = (float) $i['latitude'];
    $lon = (float) $i['longitude'];
    $sgdi[] = [
        (int) $i['id'], round($lat, 6), round($lon, 6),
        $i['type_infrastructure'], (string) $i['sous_type'],
        (string) $i['nom_demandeur'],
        (string) ($i['operateur_proprietaire'] ?: $i['entreprise_beneficiaire']),
        (string) ($i['ville'] ?: $i['arrondissement']),
        osmRegionDuPoint($lat, $lon) ?: (string) $i['region'],
        $i['statut'], $i['numero'],
    ];
}

// Points d'intérêt soumis à distance minimale
$poi = [];
try {
    foreach (getAllPOIsForMap() as $p) {
        $poi[] = [(float) $p['latitude'], (float) $p['longitude'], (string) $p['nom'], (string) $p['categorie_nom'],
                  (int) $p['distance_min_metres'], (int) $p['distance_min_rural_metres'], (string) ($p['couleur_marqueur'] ?: '#e74c3c')];
    }
} catch (Exception $e) {
    error_log('POI carte : ' . $e->getMessage());
}

// Couverture GPS : part des stations-service du SGDI qui ont une position exploitable
$stations_total = 0;
try {
    $stations_total = (int) $pdo->query("SELECT COUNT(*) FROM dossiers WHERE type_infrastructure = 'station_service'")->fetchColumn();
} catch (Exception $e) {
}
$stations_geolocalisees = count(array_filter($sgdi, function ($p) { return $p[3] === 'station_service'; }));

header('Cache-Control: private, max-age=60');
echo json_encode([
    'genere' => date('c'),
    'couverture' => ['stations' => $stations_total, 'geolocalisees' => $stations_geolocalisees],
    'sgdi' => $sgdi,
    'poi' => $poi,
    'osm' => osmDonnees(),
], JSON_UNESCAPED_UNICODE);

// Référence OSM de plus de 24 h : resynchroniser après avoir envoyé la réponse (PHP-FPM uniquement),
// un seul processus à la fois grâce au verrou.
if (osmAActualiser() && function_exists('fastcgi_finish_request')) {
    if (!is_dir(dirname(OSM_FICHIER_CACHE))) @mkdir(dirname(OSM_FICHIER_CACHE), 0775, true);
    $verrou = @fopen(dirname(OSM_FICHIER_CACHE) . '/osm_sync.lock', 'c');
    if ($verrou && flock($verrou, LOCK_EX | LOCK_NB)) {
        fastcgi_finish_request();
        set_time_limit(200);
        try {
            osmSynchroniser();
        } catch (Exception $e) {
            osmNoterEchec();
            error_log('Synchronisation OSM automatique : ' . $e->getMessage());
        }
        flock($verrou, LOCK_UN);
    }
}

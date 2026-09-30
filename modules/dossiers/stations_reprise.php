<?php
/**
 * Recherche des stations existantes pouvant être reprises (JSON)
 * GET q : texte (numéro, nom, opérateur, ville, quartier) ; type : type d'infrastructure
 */
require_once '../../includes/auth.php';
require_once '../../includes/reprise_functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !hasAnyRole(['chef_service', 'admin'])) {
    http_response_code(403);
    echo json_encode(['erreur' => 'Accès refusé']);
    exit;
}
session_write_close();

if (!repriseDisponible()) {
    echo json_encode(['stations' => [], 'indisponible' => true]);
    exit;
}

$types = ['station_service', 'point_consommateur', 'depot_gpl', 'centre_emplisseur'];
$type = in_array($_GET['type'] ?? '', $types, true) ? $_GET['type'] : 'station_service';

$stations = array_map(function ($s) {
    return [
        'id' => (int) $s['id'],
        'numero' => $s['numero'],
        'nom' => $s['nom_demandeur'],
        'operateur' => $s['operateur_proprietaire'] ?: $s['entreprise_beneficiaire'],
        'historique' => $s['statut'] === 'historique_autorise',
        'region' => $s['region'], 'departement' => $s['departement'], 'arrondissement' => $s['arrondissement'],
        'ville' => $s['ville'], 'quartier' => $s['quartier'], 'lieu_dit' => $s['lieu_dit'],
        'gps' => $s['coordonnees_gps'],
        'approximatif' => $s['source_gps'] === 'Centre de la localité (approximatif)',
    ];
}, repriseRechercherStations($_GET['q'] ?? '', $type));

echo json_encode(['stations' => $stations], JSON_UNESCAPED_UNICODE);

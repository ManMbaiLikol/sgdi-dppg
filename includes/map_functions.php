<?php
// Fonctions pour la gestion de la cartographie - SGDI

/**
 * Valider des coordonnées GPS
 */
function validateGPSCoordinates($latitude, $longitude) {
    $errors = [];

    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        $errors[] = 'Les coordonnées doivent être numériques';
        return $errors;
    }

    $lat = floatval($latitude);
    $lng = floatval($longitude);

    // Vérifier les limites générales
    if ($lat < -90 || $lat > 90) {
        $errors[] = 'La latitude doit être entre -90 et 90';
    }

    if ($lng < -180 || $lng > 180) {
        $errors[] = 'La longitude doit être entre -180 et 180';
    }

    // Vérifier que c'est bien au Cameroun (approximatif)
    // Cameroun: latitude 2° à 13° N, longitude 8° à 16° E
    if ($lat < 1.5 || $lat > 13.5) {
        $errors[] = 'Ces coordonnées ne semblent pas être au Cameroun (latitude attendue: 2° à 13° N)';
    }

    if ($lng < 7.5 || $lng > 16.5) {
        $errors[] = 'Ces coordonnées ne semblent pas être au Cameroun (longitude attendue: 8° à 16° E)';
    }

    return $errors;
}

/**
 * Parser des coordonnées GPS depuis différents formats
 */
function parseGPSCoordinates($input) {
    $input = trim($input);

    // Format: "3.8667, 11.5167" ou "3.8667,11.5167"
    if (preg_match('/^(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)$/', $input, $matches)) {
        return [
            'latitude' => floatval($matches[1]),
            'longitude' => floatval($matches[2])
        ];
    }

    // Format: "3°52'0"N 11°31'0"E" (DMS - Degrees Minutes Seconds)
    if (preg_match('/(\d+)°(\d+)\'([\d.]+)"([NS])\s+(\d+)°(\d+)\'([\d.]+)"([EW])/', $input, $matches)) {
        $lat = $matches[1] + ($matches[2] / 60) + ($matches[3] / 3600);
        if ($matches[4] === 'S') $lat = -$lat;

        $lng = $matches[5] + ($matches[6] / 60) + ($matches[7] / 3600);
        if ($matches[8] === 'W') $lng = -$lng;

        return [
            'latitude' => $lat,
            'longitude' => $lng
        ];
    }

    // Format: "N 3.8667 E 11.5167"
    if (preg_match('/[NS]\s*(-?\d+\.?\d*)\s*[EW]\s*(-?\d+\.?\d*)/', $input, $matches)) {
        return [
            'latitude' => floatval($matches[1]),
            'longitude' => floatval($matches[2])
        ];
    }

    return null;
}

/**
 * Calculer la distance entre deux points GPS (en kilomètres)
 * Utilise la formule de Haversine
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Rayon de la Terre en km

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));

    $distance = $earthRadius * $c;

    return round($distance, 2);
}

/**
 * Trouver les infrastructures à proximité
 */
function findNearbyInfrastructures($latitude, $longitude, $radius_km = 5, $exclude_dossier_id = null) {
    global $pdo;

    // Récupérer toutes les infrastructures autorisées avec coordonnées
    $sql = "SELECT id, numero, type_infrastructure, sous_type, nom_demandeur,
                   ville, coordonnees_gps, statut
            FROM dossiers
            WHERE coordonnees_gps IS NOT NULL
            AND coordonnees_gps != ''
            AND statut IN ('autorise', 'decide')";

    if ($exclude_dossier_id) {
        $sql .= " AND id != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$exclude_dossier_id]);
    } else {
        $stmt = $pdo->query($sql);
    }

    $infrastructures = $stmt->fetchAll();
    $nearby = [];

    foreach ($infrastructures as $infra) {
        $coords = parseGPSCoordinates($infra['coordonnees_gps']);
        if ($coords) {
            $distance = calculateDistance(
                $latitude,
                $longitude,
                $coords['latitude'],
                $coords['longitude']
            );

            if ($distance <= $radius_km) {
                $infra['distance'] = $distance;
                $infra['parsed_coords'] = $coords;
                $nearby[] = $infra;
            }
        }
    }

    // Trier par distance
    usort($nearby, function($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });

    return $nearby;
}

/**
 * Obtenir le centre géographique du Cameroun pour la carte par défaut
 */
function getCameroonCenter() {
    return [
        'latitude' => 7.3697,
        'longitude' => 12.3547,
        'zoom' => 6
    ];
}

/**
 * Obtenir les limites géographiques du Cameroun
 */
function getCameroonBounds() {
    return [
        'south' => 1.5,
        'north' => 13.5,
        'west' => 7.5,
        'east' => 16.5
    ];
}

/**
 * Formater des coordonnées GPS pour l'affichage
 */
function formatGPSCoordinates($latitude, $longitude, $format = 'decimal') {
    if ($format === 'decimal') {
        return sprintf("%.6f, %.6f", $latitude, $longitude);
    }

    if ($format === 'dms') {
        // Conversion en DMS (Degrees Minutes Seconds)
        $latDeg = floor(abs($latitude));
        $latMin = floor((abs($latitude) - $latDeg) * 60);
        $latSec = round(((abs($latitude) - $latDeg) * 60 - $latMin) * 60, 2);
        $latDir = $latitude >= 0 ? 'N' : 'S';

        $lngDeg = floor(abs($longitude));
        $lngMin = floor((abs($longitude) - $lngDeg) * 60);
        $lngSec = round(((abs($longitude) - $lngDeg) * 60 - $lngMin) * 60, 2);
        $lngDir = $longitude >= 0 ? 'E' : 'W';

        return sprintf("%d°%d'%.2f\"%s %d°%d'%.2f\"%s",
            $latDeg, $latMin, $latSec, $latDir,
            $lngDeg, $lngMin, $lngSec, $lngDir
        );
    }

    return null;
}

/**
 * Obtenir l'icône de marqueur selon le type d'infrastructure
 */
function getMarkerIcon($type_infrastructure) {
    $icons = [
        'station_service' => 'gas-pump',
        'point_consommateur' => 'industry',
        'depot_gpl' => 'warehouse',
        'centre_emplisseur' => 'fire'
    ];

    return $icons[$type_infrastructure] ?? 'map-marker';
}

/**
 * Obtenir la couleur du marqueur selon le statut
 */
function getMarkerColor($statut) {
    $colors = [
        'autorise' => 'green',
        'decide' => 'blue',
        'valide' => 'orange',
        'inspecte' => 'yellow',
        'en_cours' => 'gray',
        'rejete' => 'red'
    ];

    return $colors[$statut] ?? 'gray';
}

/**
 * Générer le lien Google Maps
 */
function getGoogleMapsLink($latitude, $longitude) {
    return sprintf("https://www.google.com/maps?q=%.6f,%.6f", $latitude, $longitude);
}

/**
 * Vérifier si une zone est restreinte (zones militaires, parcs nationaux, zones protégées)
 */
function isRestrictedZone($latitude, $longitude) {
    // Définir les zones restreintes au Cameroun
    // Format: ['nom' => string, 'type' => string, 'lat_min' => float, 'lat_max' => float, 'lng_min' => float, 'lng_max' => float]
    $restricted_zones = [
        // Zones militaires
        [
            'nom' => 'Base militaire de Yaoundé',
            'type' => 'militaire',
            'lat_min' => 3.85, 'lat_max' => 3.90,
            'lng_min' => 11.49, 'lng_max' => 11.54
        ],
        [
            'nom' => 'Base militaire de Douala',
            'type' => 'militaire',
            'lat_min' => 4.03, 'lat_max' => 4.08,
            'lng_min' => 9.69, 'lng_max' => 9.74
        ],

        // Parcs nationaux (distance de sécurité)
        [
            'nom' => 'Parc national de Waza',
            'type' => 'parc_national',
            'lat_min' => 11.1, 'lat_max' => 11.8,
            'lng_min' => 14.5, 'lng_max' => 15.1
        ],
        [
            'nom' => 'Parc national de la Bénoué',
            'type' => 'parc_national',
            'lat_min' => 8.2, 'lat_max' => 9.0,
            'lng_min' => 13.3, 'lng_max' => 14.5
        ],
        [
            'nom' => 'Parc national de Korup',
            'type' => 'parc_national',
            'lat_min' => 5.0, 'lat_max' => 5.5,
            'lng_min' => 8.7, 'lng_max' => 9.2
        ],

        // Réserves écologiques
        [
            'nom' => 'Réserve de faune du Dja',
            'type' => 'reserve',
            'lat_min' => 2.8, 'lat_max' => 3.6,
            'lng_min' => 12.4, 'lng_max' => 13.8
        ],

        // Zones aéroportuaires (rayon de sécurité)
        [
            'nom' => 'Aéroport international de Yaoundé-Nsimalen',
            'type' => 'aeroportuaire',
            'lat_min' => 3.71, 'lat_max' => 3.75,
            'lng_min' => 11.53, 'lng_max' => 11.57
        ],
        [
            'nom' => 'Aéroport international de Douala',
            'type' => 'aeroportuaire',
            'lat_min' => 4.00, 'lat_max' => 4.03,
            'lng_min' => 9.71, 'lng_max' => 9.73
        ]
    ];

    // Vérifier si les coordonnées tombent dans une zone restreinte
    foreach ($restricted_zones as $zone) {
        if ($latitude >= $zone['lat_min'] && $latitude <= $zone['lat_max'] &&
            $longitude >= $zone['lng_min'] && $longitude <= $zone['lng_max']) {
            return [
                'restricted' => true,
                'zone_name' => $zone['nom'],
                'zone_type' => $zone['type'],
                'message' => "Cette localisation se trouve dans une zone restreinte : {$zone['nom']} ({$zone['type']})"
            ];
        }
    }

    return [
        'restricted' => false,
        'zone_name' => null,
        'zone_type' => null,
        'message' => null
    ];
}

/**
 * Obtenir toutes les infrastructures pour la carte
 */
function getAllInfrastructuresForMap($filters = []) {
    global $pdo;

    $sql = "SELECT id, numero, type_infrastructure, sous_type, nom_demandeur,
                   ville, region, quartier, arrondissement, departement, lieu_dit,
                   coordonnees_gps, statut, date_creation,
                   operateur_proprietaire, entreprise_beneficiaire
            FROM dossiers
            WHERE coordonnees_gps IS NOT NULL
            AND coordonnees_gps != ''";

    $params = [];

    if (!empty($filters['type_infrastructure'])) {
        $sql .= " AND type_infrastructure = ?";
        $params[] = $filters['type_infrastructure'];
    }

    // Support pour un seul statut ou plusieurs statuts
    if (!empty($filters['statut'])) {
        $sql .= " AND statut = ?";
        $params[] = $filters['statut'];
    } elseif (!empty($filters['statuts']) && is_array($filters['statuts'])) {
        $placeholders = implode(',', array_fill(0, count($filters['statuts']), '?'));
        $sql .= " AND statut IN ($placeholders)";
        $params = array_merge($params, $filters['statuts']);
    }

    if (!empty($filters['region'])) {
        $sql .= " AND region = ?";
        $params[] = $filters['region'];
    }

    $sql .= " ORDER BY date_creation DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $infrastructures = $stmt->fetchAll();

    // Parser les coordonnées
    $result = [];
    foreach ($infrastructures as $infra) {
        $coords = parseGPSCoordinates($infra['coordonnees_gps']);
        if ($coords) {
            $infra['latitude'] = $coords['latitude'];
            $infra['longitude'] = $coords['longitude'];
            $result[] = $infra;
        }
    }

    return $result;
}
?>

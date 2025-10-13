<?php
require_once '../../includes/auth.php';

requireLogin();

// Vérifier que l'utilisateur est admin ou chef de service
if (!in_array($_SESSION['user_role'], ['admin', 'chef_service'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Action non spécifiée']);
    exit;
}

$action = $input['action'];

// Mapping des types Google Places vers les catégories SGDI
function getGooglePlaceTypeMapping() {
    return [
        // Établissements d'enseignement (100m)
        'school' => 'etablissement_enseignement',
        'university' => 'etablissement_enseignement',
        'primary_school' => 'etablissement_enseignement',
        'secondary_school' => 'etablissement_enseignement',

        // Infrastructures sanitaires (100m)
        'hospital' => 'infrastructure_sanitaire',
        'doctor' => 'infrastructure_sanitaire',
        'dentist' => 'infrastructure_sanitaire',
        'pharmacy' => 'infrastructure_sanitaire',
        'health' => 'infrastructure_sanitaire',

        // Lieux de culte (100m)
        'church' => 'lieu_culte',
        'mosque' => 'lieu_culte',
        'synagogue' => 'lieu_culte',
        'hindu_temple' => 'lieu_culte',
        'place_of_worship' => 'lieu_culte',

        // Terrains de sport (100m)
        'stadium' => 'terrain_sport',

        // Places de marché (100m)
        'supermarket' => 'place_marche',

        // Bâtiments administratifs (100m)
        'local_government_office' => 'batiment_administratif',
        'courthouse' => 'batiment_administratif',
        'embassy' => 'batiment_administratif',

        // Mairies (500m)
        'city_hall' => 'mairie',
        'town_hall' => 'mairie',
    ];
}

// Récupérer les catégories POI depuis la base
function getCategoriesPOI($pdo) {
    $stmt = $pdo->query("SELECT * FROM categories_poi WHERE actif = 1");
    $categories = [];
    foreach ($stmt->fetchAll() as $cat) {
        $categories[$cat['code']] = $cat;
    }
    return $categories;
}

// Fonction pour rechercher les POI sur Google Places
function searchGooglePlaces($ville, $region, $radius, $categories, $pdo) {
    global $input;

    $api_key = getEnvVar('GOOGLE_PLACES_API_KEY', '');
    if (empty($api_key)) {
        throw new Exception('Clé API Google Places non configurée');
    }

    // Récupérer les catégories POI
    $categoriesPOI = getCategoriesPOI($pdo);

    // Mapping des types Google
    $typeMapping = getGooglePlaceTypeMapping();

    // Liste des types Google à rechercher selon les catégories sélectionnées
    $googleTypes = [];
    foreach ($categories as $catId) {
        $cat = array_filter($categoriesPOI, function($c) use ($catId) {
            return $c['id'] == $catId;
        });
        $cat = reset($cat);

        if ($cat) {
            // Trouver les types Google correspondants
            foreach ($typeMapping as $googleType => $sgdiCode) {
                if ($sgdiCode === $cat['code']) {
                    $googleTypes[] = $googleType;
                }
            }
        }
    }

    if (empty($googleTypes)) {
        return ['results' => [], 'search_count' => 0];
    }

    // Géocoder la ville pour obtenir les coordonnées centrales
    $geocodeUrl = "https://maps.googleapis.com/maps/api/geocode/json?" . http_build_query([
        'address' => "$ville, $region, Cameroun",
        'key' => $api_key
    ]);

    $geocodeResponse = @file_get_contents($geocodeUrl);
    if ($geocodeResponse === false) {
        throw new Exception('Erreur lors du géocodage de la ville');
    }

    $geocodeData = json_decode($geocodeResponse, true);
    if ($geocodeData['status'] !== 'OK' || empty($geocodeData['results'])) {
        throw new Exception('Impossible de localiser la ville : ' . $ville);
    }

    $location = $geocodeData['results'][0]['geometry']['location'];
    $lat = $location['lat'];
    $lng = $location['lng'];

    // Déterminer si c'est une zone urbaine ou rurale (simplification)
    $grandes_villes = ['Yaoundé', 'Douala', 'Garoua', 'Bamenda', 'Bafoussam', 'Maroua', 'Ngaoundéré'];
    $zone_type = in_array($ville, $grandes_villes) ? 'urbaine' : 'rurale';

    // Rechercher chaque type de POI
    $allResults = [];
    $searchCount = 0;
    $uniquePOIs = []; // Pour éviter les doublons

    foreach (array_unique($googleTypes) as $type) {
        $searchCount++;

        $placesUrl = "https://maps.googleapis.com/maps/api/place/nearbysearch/json?" . http_build_query([
            'location' => "$lat,$lng",
            'radius' => $radius,
            'type' => $type,
            'key' => $api_key
        ]);

        $placesResponse = @file_get_contents($placesUrl);
        if ($placesResponse === false) {
            continue;
        }

        $placesData = json_decode($placesResponse, true);
        if ($placesData['status'] === 'OK' && !empty($placesData['results'])) {
            foreach ($placesData['results'] as $place) {
                // Éviter les doublons basés sur place_id
                if (isset($uniquePOIs[$place['place_id']])) {
                    continue;
                }

                // Mapper le type Google vers la catégorie SGDI
                $sgdiCode = $typeMapping[$type] ?? null;
                if (!$sgdiCode) {
                    continue;
                }

                $categorie = $categoriesPOI[$sgdiCode] ?? null;
                if (!$categorie) {
                    continue;
                }

                // Extraire les informations du POI
                $poi = [
                    'place_id' => $place['place_id'],
                    'nom' => $place['name'],
                    'latitude' => $place['geometry']['location']['lat'],
                    'longitude' => $place['geometry']['location']['lng'],
                    'adresse' => $place['vicinity'] ?? '',
                    'ville' => $ville,
                    'region' => $region,
                    'zone_type' => $zone_type,
                    'rating' => $place['rating'] ?? null,
                    'user_ratings_total' => $place['user_ratings_total'] ?? 0,
                    'google_type' => $type,
                    'categorie_id' => $categorie['id'],
                    'categorie_code' => $categorie['code'],
                    'categorie_nom' => $categorie['nom'],
                    'categorie_icone' => $categorie['icone'],
                    'categorie_couleur' => $categorie['couleur_marqueur'],
                ];

                $uniquePOIs[$place['place_id']] = $poi;
                $allResults[] = $poi;
            }
        }

        // Respecter les limites de taux de l'API
        usleep(100000); // 100ms de pause entre les requêtes
    }

    return [
        'results' => $allResults,
        'search_count' => $searchCount
    ];
}

// Fonction pour importer les POI dans la base
function importPOIs($pois, $pdo) {
    $imported = 0;
    $skipped = 0;
    $errors = [];

    foreach ($pois as $poi) {
        try {
            // Vérifier si le POI existe déjà (même nom et coordonnées proches)
            $stmt = $pdo->prepare("
                SELECT id FROM points_interet
                WHERE nom = :nom
                AND ABS(latitude - :lat) < 0.001
                AND ABS(longitude - :lng) < 0.001
                LIMIT 1
            ");
            $stmt->execute([
                ':nom' => $poi['nom'],
                ':lat' => $poi['latitude'],
                ':lng' => $poi['longitude']
            ]);

            if ($stmt->fetch()) {
                $skipped++;
                continue;
            }

            // Insérer le POI
            $stmt = $pdo->prepare("
                INSERT INTO points_interet (
                    categorie_id,
                    nom,
                    description,
                    latitude,
                    longitude,
                    adresse,
                    ville,
                    region,
                    zone_type,
                    user_id,
                    actif
                ) VALUES (
                    :categorie_id,
                    :nom,
                    :description,
                    :latitude,
                    :longitude,
                    :adresse,
                    :ville,
                    :region,
                    :zone_type,
                    :user_id,
                    1
                )
            ");

            $description = "Importé depuis Google Places";
            if (!empty($poi['rating'])) {
                $description .= " - Note: {$poi['rating']}/5";
            }
            if (!empty($poi['user_ratings_total'])) {
                $description .= " ({$poi['user_ratings_total']} avis)";
            }

            $stmt->execute([
                ':categorie_id' => $poi['categorie_id'],
                ':nom' => $poi['nom'],
                ':description' => $description,
                ':latitude' => $poi['latitude'],
                ':longitude' => $poi['longitude'],
                ':adresse' => $poi['adresse'] ?? '',
                ':ville' => $poi['ville'],
                ':region' => $poi['region'],
                ':zone_type' => $poi['zone_type'],
                ':user_id' => $_SESSION['user_id']
            ]);

            $poiId = $pdo->lastInsertId();

            // Enregistrer dans l'audit
            $stmt = $pdo->prepare("
                INSERT INTO audit_poi (
                    poi_id,
                    action,
                    user_id,
                    nouvelles_valeurs
                ) VALUES (
                    :poi_id,
                    'creation',
                    :user_id,
                    :valeurs
                )
            ");

            $stmt->execute([
                ':poi_id' => $poiId,
                ':user_id' => $_SESSION['user_id'],
                ':valeurs' => json_encode([
                    'source' => 'google_places',
                    'place_id' => $poi['place_id'] ?? null,
                    'imported_at' => date('Y-m-d H:i:s')
                ])
            ]);

            $imported++;
        } catch (Exception $e) {
            $errors[] = "Erreur pour '{$poi['nom']}': " . $e->getMessage();
        }
    }

    return [
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors
    ];
}

// Traiter l'action
try {
    header('Content-Type: application/json');

    switch ($action) {
        case 'search':
            // Valider les paramètres
            if (empty($input['ville']) || empty($input['region'])) {
                throw new Exception('Ville et région requis');
            }

            if (empty($input['categories']) || !is_array($input['categories'])) {
                throw new Exception('Catégories requises');
            }

            $radius = (int)($input['radius'] ?? 10000);

            // Effectuer la recherche
            $results = searchGooglePlaces(
                $input['ville'],
                $input['region'],
                $radius,
                $input['categories'],
                $pdo
            );

            echo json_encode([
                'success' => true,
                'results' => $results['results'],
                'search_count' => $results['search_count'],
                'ville' => $input['ville'],
                'region' => $input['region']
            ]);
            break;

        case 'import':
            // Valider les paramètres
            if (empty($input['pois']) || !is_array($input['pois'])) {
                throw new Exception('Aucun POI à importer');
            }

            // Importer les POI
            $results = importPOIs($input['pois'], $pdo);

            echo json_encode([
                'success' => true,
                'imported' => $results['imported'],
                'skipped' => $results['skipped'],
                'errors' => $results['errors']
            ]);
            break;

        default:
            throw new Exception('Action non reconnue');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

<?php
// Fonctions pour la gestion des contraintes de distance - SGDI
// Implémentation des normes de distance de sécurité pour les stations-service

/**
 * Valider la conformité géospatiale d'une infrastructure
 * @param float $latitude
 * @param float $longitude
 * @param int $dossier_id ID du dossier (pour exclure de la recherche)
 * @param string $zone_type 'urbaine' ou 'rurale'
 * @return array Résultat de la validation avec détails
 */
function validerConformiteGeospatiale($latitude, $longitude, $dossier_id = null, $zone_type = 'urbaine') {
    global $pdo;

    $result = [
        'conforme' => true,
        'violations' => [],
        'stations_proches' => [],
        'pois_proches' => [],
        'nombre_violations' => 0
    ];

    // 1. Vérifier la distance avec les autres stations-service
    $violations_stations = verifierDistanceStations($latitude, $longitude, $dossier_id, $zone_type);
    if (!empty($violations_stations)) {
        $result['conforme'] = false;
        $result['violations'] = array_merge($result['violations'], $violations_stations);
        $result['stations_proches'] = $violations_stations;
    }

    // 2. Vérifier la distance avec les points d'intérêt stratégiques
    $violations_pois = verifierDistancePOIs($latitude, $longitude, $zone_type);
    if (!empty($violations_pois)) {
        $result['conforme'] = false;
        $result['violations'] = array_merge($result['violations'], $violations_pois);
        $result['pois_proches'] = $violations_pois;
    }

    $result['nombre_violations'] = count($result['violations']);

    return $result;
}

/**
 * Vérifier la distance minimale avec les autres stations-service (500m)
 */
function verifierDistanceStations($latitude, $longitude, $dossier_id_exclus = null, $zone_type = 'urbaine') {
    global $pdo;

    // Distance minimale : 500m (ou 400m en zone rurale = 500 * 0.8)
    $distance_min = ($zone_type === 'rurale') ? 400 : 500;

    $violations = [];

    // Récupérer toutes les stations-service autorisées ou décidées avec coordonnées
    $sql = "SELECT id, numero, nom_demandeur, ville, coordonnees_gps, statut, type_infrastructure, sous_type
            FROM dossiers
            WHERE type_infrastructure IN ('station_service', 'reprise_station_service')
            AND coordonnees_gps IS NOT NULL
            AND coordonnees_gps != ''
            AND statut IN ('autorise', 'decide', 'valide', 'inspecte', 'paye')";

    if ($dossier_id_exclus) {
        $sql .= " AND id != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dossier_id_exclus]);
    } else {
        $stmt = $pdo->query($sql);
    }

    $stations = $stmt->fetchAll();

    foreach ($stations as $station) {
        $coords = parseGPSCoordinates($station['coordonnees_gps']);
        if ($coords) {
            // Calculer la distance en mètres
            $distance = calculateDistance(
                $latitude,
                $longitude,
                $coords['latitude'],
                $coords['longitude']
            ) * 1000; // Convertir en mètres

            if ($distance < $distance_min) {
                $ecart = $distance_min - $distance;
                $violations[] = [
                    'type' => 'distance_station',
                    'station_id' => $station['id'],
                    'numero_station' => $station['numero'],
                    'nom_etablissement' => $station['nom_demandeur'],
                    'ville' => $station['ville'],
                    'distance_mesuree' => round($distance, 2),
                    'distance_requise' => $distance_min,
                    'ecart' => round($ecart, 2),
                    'severite' => determinerSeverite($ecart, $distance_min),
                    'coordonnees' => $station['coordonnees_gps'],
                    'message' => sprintf(
                        'Station-service "%s" à %.0f m (minimum requis: %d m, écart: %.0f m)',
                        $station['nom_demandeur'],
                        $distance,
                        $distance_min,
                        $ecart
                    )
                ];
            }
        }
    }

    return $violations;
}

/**
 * Vérifier la distance avec les points d'intérêt stratégiques
 */
function verifierDistancePOIs($latitude, $longitude, $zone_type = 'urbaine') {
    global $pdo;

    $violations = [];

    // Récupérer tous les POI actifs
    $sql = "SELECT p.*, c.nom as categorie_nom, c.code as categorie_code,
                   c.distance_min_metres, c.distance_min_rural_metres,
                   c.couleur_marqueur, c.icone
            FROM points_interet p
            JOIN categories_poi c ON p.categorie_id = c.id
            WHERE p.actif = 1 AND c.actif = 1";

    $stmt = $pdo->query($sql);
    $pois = $stmt->fetchAll();

    foreach ($pois as $poi) {
        // Déterminer la distance minimale selon le type de zone
        $distance_min = ($zone_type === 'rurale')
            ? $poi['distance_min_rural_metres']
            : $poi['distance_min_metres'];

        // Calculer la distance en mètres
        $distance = calculateDistance(
            $latitude,
            $longitude,
            $poi['latitude'],
            $poi['longitude']
        ) * 1000; // Convertir en mètres

        if ($distance < $distance_min) {
            $ecart = $distance_min - $distance;
            $violations[] = [
                'type' => 'distance_poi',
                'poi_id' => $poi['id'],
                'categorie' => $poi['categorie_nom'],
                'categorie_code' => $poi['categorie_code'],
                'nom_etablissement' => $poi['nom'],
                'ville' => $poi['ville'],
                'distance_mesuree' => round($distance, 2),
                'distance_requise' => $distance_min,
                'ecart' => round($ecart, 2),
                'severite' => determinerSeverite($ecart, $distance_min),
                'coordonnees' => sprintf("%.6f, %.6f", $poi['latitude'], $poi['longitude']),
                'couleur' => $poi['couleur_marqueur'],
                'icone' => $poi['icone'],
                'message' => sprintf(
                    '%s "%s" à %.0f m (minimum requis: %d m, écart: %.0f m)',
                    $poi['categorie_nom'],
                    $poi['nom'],
                    $distance,
                    $distance_min,
                    $ecart
                )
            ];
        }
    }

    return $violations;
}

/**
 * Déterminer la sévérité d'une violation selon l'écart
 */
function determinerSeverite($ecart, $distance_requise) {
    $pourcentage_ecart = ($ecart / $distance_requise) * 100;

    if ($pourcentage_ecart > 50) {
        return 'critique'; // Écart > 50% de la distance requise
    } elseif ($pourcentage_ecart > 25) {
        return 'majeure'; // Écart entre 25% et 50%
    } else {
        return 'mineure'; // Écart < 25%
    }
}

/**
 * Enregistrer une validation géospatiale dans la base de données
 */
function enregistrerValidationGeospatiale($dossier_id, $latitude, $longitude, $zone_type, $conforme, $violations, $user_id) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // 1. Enregistrer la validation
        $sql = "INSERT INTO validations_geospatiales
                (dossier_id, latitude, longitude, zone_type, conforme, nombre_violations, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $dossier_id,
            $latitude,
            $longitude,
            $zone_type,
            $conforme ? 1 : 0,
            count($violations),
            $user_id
        ]);

        $validation_id = $pdo->lastInsertId();

        // 2. Enregistrer les violations détectées
        if (!empty($violations)) {
            $sql = "INSERT INTO violations_contraintes
                    (validation_id, dossier_id, type_violation, poi_id, station_id,
                     distance_mesuree, distance_requise, ecart, nom_etablissement,
                     categorie_etablissement, severite, coordonnees_etablissement)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            foreach ($violations as $violation) {
                $stmt->execute([
                    $validation_id,
                    $dossier_id,
                    $violation['type'],
                    $violation['poi_id'] ?? null,
                    $violation['station_id'] ?? null,
                    $violation['distance_mesuree'],
                    $violation['distance_requise'],
                    $violation['ecart'],
                    $violation['nom_etablissement'],
                    $violation['categorie'] ?? ($violation['type'] === 'distance_station' ? 'Station-service' : null),
                    $violation['severite'],
                    $violation['coordonnees']
                ]);
            }
        }

        // 3. Mettre à jour le dossier
        $statut_conformite = $conforme ? 'conforme' : 'non_conforme';
        $sql = "UPDATE dossiers
                SET zone_type = ?,
                    validation_geospatiale_faite = 1,
                    conformite_geospatiale = ?
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$zone_type, $statut_conformite, $dossier_id]);

        $pdo->commit();
        return $validation_id;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur lors de l'enregistrement de la validation: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir la dernière validation géospatiale d'un dossier
 */
function getDerniereValidationGeospatiale($dossier_id) {
    global $pdo;

    $sql = "SELECT * FROM validations_geospatiales
            WHERE dossier_id = ?
            ORDER BY date_validation DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dossier_id]);

    return $stmt->fetch();
}

/**
 * Obtenir les violations d'un dossier
 */
function getViolationsDossier($dossier_id, $validation_id = null) {
    global $pdo;

    if ($validation_id) {
        $sql = "SELECT * FROM violations_contraintes
                WHERE validation_id = ?
                ORDER BY severite DESC, ecart DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$validation_id]);
    } else {
        // Récupérer les violations de la dernière validation
        $sql = "SELECT v.* FROM violations_contraintes v
                JOIN validations_geospatiales vg ON v.validation_id = vg.id
                WHERE v.dossier_id = ?
                ORDER BY vg.date_validation DESC, v.severite DESC, v.ecart DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dossier_id]);
    }

    return $stmt->fetchAll();
}

/**
 * Obtenir toutes les catégories de POI
 */
function getCategoriesPOI() {
    global $pdo;

    $sql = "SELECT * FROM categories_poi WHERE actif = 1 ORDER BY distance_min_metres DESC, nom";
    $stmt = $pdo->query($sql);

    return $stmt->fetchAll();
}

/**
 * Obtenir tous les POI d'une catégorie
 */
function getPOIsParCategorie($categorie_id, $actif_seulement = true) {
    global $pdo;

    $sql = "SELECT * FROM points_interet WHERE categorie_id = ?";
    if ($actif_seulement) {
        $sql .= " AND actif = 1";
    }
    $sql .= " ORDER BY nom";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$categorie_id]);

    return $stmt->fetchAll();
}

/**
 * Obtenir tous les POI pour la carte
 */
function getAllPOIsForMap($filters = []) {
    global $pdo;

    $sql = "SELECT p.*, c.nom as categorie_nom, c.code as categorie_code,
                   c.distance_min_metres, c.distance_min_rural_metres,
                   c.couleur_marqueur, c.icone
            FROM points_interet p
            JOIN categories_poi c ON p.categorie_id = c.id
            WHERE p.actif = 1 AND c.actif = 1";

    $params = [];

    if (!empty($filters['categorie_id'])) {
        $sql .= " AND p.categorie_id = ?";
        $params[] = $filters['categorie_id'];
    }

    if (!empty($filters['ville'])) {
        $sql .= " AND p.ville = ?";
        $params[] = $filters['ville'];
    }

    if (!empty($filters['region'])) {
        $sql .= " AND p.region = ?";
        $params[] = $filters['region'];
    }

    $sql .= " ORDER BY c.distance_min_metres DESC, p.nom";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Créer un nouveau POI
 */
function creerPOI($data, $user_id) {
    global $pdo;

    try {
        $sql = "INSERT INTO points_interet
                (categorie_id, nom, description, latitude, longitude, adresse,
                 ville, region, zone_type, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['categorie_id'],
            $data['nom'],
            $data['description'] ?? null,
            $data['latitude'],
            $data['longitude'],
            $data['adresse'] ?? null,
            $data['ville'] ?? null,
            $data['region'] ?? null,
            $data['zone_type'] ?? 'urbaine',
            $user_id
        ]);

        $poi_id = $pdo->lastInsertId();

        // Audit
        auditerActionPOI($poi_id, 'creation', $user_id, null, $data);

        return $poi_id;

    } catch (Exception $e) {
        error_log("Erreur lors de la création du POI: " . $e->getMessage());
        return false;
    }
}

/**
 * Mettre à jour un POI
 */
function mettreAJourPOI($poi_id, $data, $user_id) {
    global $pdo;

    try {
        // Récupérer les anciennes valeurs pour l'audit
        $sql = "SELECT * FROM points_interet WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$poi_id]);
        $ancien = $stmt->fetch();

        $sql = "UPDATE points_interet
                SET categorie_id = ?, nom = ?, description = ?,
                    latitude = ?, longitude = ?, adresse = ?,
                    ville = ?, region = ?, zone_type = ?
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['categorie_id'],
            $data['nom'],
            $data['description'] ?? null,
            $data['latitude'],
            $data['longitude'],
            $data['adresse'] ?? null,
            $data['ville'] ?? null,
            $data['region'] ?? null,
            $data['zone_type'] ?? 'urbaine',
            $poi_id
        ]);

        // Audit
        auditerActionPOI($poi_id, 'modification', $user_id, $ancien, $data);

        return true;

    } catch (Exception $e) {
        error_log("Erreur lors de la mise à jour du POI: " . $e->getMessage());
        return false;
    }
}

/**
 * Désactiver un POI
 */
function desactiverPOI($poi_id, $user_id) {
    global $pdo;

    try {
        $sql = "UPDATE points_interet SET actif = 0 WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$poi_id]);

        auditerActionPOI($poi_id, 'desactivation', $user_id);

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de la désactivation du POI: " . $e->getMessage());
        return false;
    }
}

/**
 * Auditer une action sur un POI
 */
function auditerActionPOI($poi_id, $action, $user_id, $anciennes_valeurs = null, $nouvelles_valeurs = null) {
    global $pdo;

    try {
        $sql = "INSERT INTO audit_poi (poi_id, action, user_id, anciennes_valeurs, nouvelles_valeurs)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $poi_id,
            $action,
            $user_id,
            $anciennes_valeurs ? json_encode($anciennes_valeurs) : null,
            $nouvelles_valeurs ? json_encode($nouvelles_valeurs) : null
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de l'audit du POI: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir les statistiques de conformité
 */
function getStatistiquesConformite($filters = []) {
    global $pdo;

    $sql = "SELECT * FROM vue_statistiques_conformite WHERE 1=1";
    $params = [];

    if (!empty($filters['region'])) {
        $sql .= " AND region = ?";
        $params[] = $filters['region'];
    }

    if (!empty($filters['ville'])) {
        $sql .= " AND ville = ?";
        $params[] = $filters['ville'];
    }

    if (!empty($filters['type_infrastructure'])) {
        $sql .= " AND type_infrastructure = ?";
        $params[] = $filters['type_infrastructure'];
    }

    $sql .= " ORDER BY region, ville";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Obtenir les violations critiques
 */
function getViolationsCritiques($limit = null) {
    global $pdo;

    $sql = "SELECT * FROM vue_violations_critiques";

    if ($limit) {
        $sql .= " LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
    } else {
        $stmt = $pdo->query($sql);
    }

    return $stmt->fetchAll();
}

/**
 * Vérifier si un dossier a besoin d'une validation géospatiale
 */
function dossierneedValidationGeospatiale($dossier) {
    // Vérifier si le dossier a des coordonnées GPS
    if (empty($dossier['coordonnees_gps'])) {
        return false;
    }

    // Vérifier si c'est une station-service (principale cible de la réglementation)
    if (!in_array($dossier['type_infrastructure'], ['station_service', 'reprise_station_service'])) {
        return false;
    }

    // Vérifier si une validation a déjà été faite
    if ($dossier['validation_geospatiale_faite'] == 1) {
        return false;
    }

    return true;
}
?>

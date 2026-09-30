<?php
/**
 * Contrôle de proximité d'un emplacement : sites protégés par la réglementation
 *
 * Distances minimales entre une station-service et :
 *   - 1 000 m : Présidence de la République, Services du Premier Ministre, Assemblée nationale, Sénat,
 *               services du Gouverneur, préfectures, sous-préfectures ;
 *   -   100 m : établissements d'enseignement, centres hospitaliers et de santé, lieux de culte,
 *               terrains de sport, places de marché, bâtiments administratifs.
 * Les distances sont lues dans categories_poi (modifiables), à défaut dans PROX_REGLES.
 *
 * Sources : points d'intérêt saisis dans le SGDI et OpenStreetMap (requête Overpass autour du point,
 * mise en cache). Pour un site étendu (école, stade…), la distance est mesurée jusqu'à sa limite.
 */
require_once __DIR__ . '/osm_sync.php';

const PROX_RAYON_GRANDS_SITES = 1000;  // recherche des sites à 1 000 m
const PROX_RAYON_SITES = 300;          // recherche des sites à 100 m (marge pour le contexte)
const PROX_CACHE_DUREE = 7 * 24 * 3600;

// code => [libellé, distance réglementaire (m)]
const PROX_REGLES = [
    'presidence' => ['Présidence de la République', 1000],
    'services_pm' => ['Services du Premier Ministre', 1000],
    'assemblee_nationale' => ['Assemblée nationale', 1000],
    'senat' => ['Sénat', 1000],
    'services_gouverneur' => ['Services du Gouverneur', 1000],
    'prefecture' => ['Préfecture', 1000],
    'sous_prefecture' => ['Sous-préfecture', 1000],
    'etablissement_enseignement' => ['Établissement d\'enseignement', 100],
    'infrastructure_sanitaire' => ['Centre hospitalier ou de santé', 100],
    'lieu_culte' => ['Lieu de culte', 100],
    'terrain_sport' => ['Terrain de sport', 100],
    'place_marche' => ['Place de marché', 100],
    'batiment_administratif' => ['Bâtiment administratif', 100],
    'mairie' => ['Mairie', 100],
];

/**
 * Règles en vigueur : catégories du SGDI (distances modifiables par l'administration), à défaut PROX_REGLES
 * @return array code => ['nom' => ..., 'urbaine' => m, 'rurale' => m]
 */
function proxRegles() {
    global $pdo;
    $regles = [];
    foreach (PROX_REGLES as $code => $r) $regles[$code] = ['nom' => $r[0], 'urbaine' => $r[1], 'rurale' => $r[1]];
    try {
        foreach ($pdo->query("SELECT code, nom, distance_min_metres, distance_min_rural_metres FROM categories_poi WHERE actif = 1") as $c) {
            $regles[$c['code']] = ['nom' => $c['nom'], 'urbaine' => (int) $c['distance_min_metres'],
                                   'rurale' => (int) ($c['distance_min_rural_metres'] ?: $c['distance_min_metres'])];
        }
    } catch (Exception $e) {
        // table absente : règles par défaut
    }
    return $regles;
}

/**
 * Catégorie réglementaire d'un élément OpenStreetMap, ou null
 */
function proxCategorieOsm(array $t) {
    $nom = mb_strtolower(($t['name'] ?? '') . ' ' . ($t['name:fr'] ?? '') . ' ' . ($t['official_name'] ?? ''));
    $motifs = [
        'presidence' => '/pr[ée]sidence de la r[ée]publique|palais de l.unit[ée]|palais pr[ée]sidentiel/u',
        'services_pm' => '/primature|premier minist/u',
        'assemblee_nationale' => '/assembl[ée]e nationale/u',
        'senat' => '/\bs[ée]nat\b/u',
        'services_gouverneur' => '/gouverneur|gouvernorat|governor/u',
        'sous_prefecture' => '/sous[- ]?pr[ée]fecture|sub[- ]?divisional office/u',
        'prefecture' => '/pr[ée]fecture|divisional office/u',
    ];
    foreach ($motifs as $code => $motif) {
        if (preg_match($motif, $nom)) return $code;
    }
    $amenity = $t['amenity'] ?? '';
    if (in_array($amenity, ['school', 'college', 'university', 'kindergarten'], true)) return 'etablissement_enseignement';
    // Centres hospitaliers et de santé (pas les pharmacies ni les opticiens)
    if (in_array($amenity, ['hospital', 'clinic', 'doctors', 'health_post'], true)
        || in_array($t['healthcare'] ?? '', ['hospital', 'clinic', 'centre', 'doctor', 'health_post', 'birthing_centre'], true)) return 'infrastructure_sanitaire';
    if ($amenity === 'place_of_worship') return 'lieu_culte';
    if (in_array($t['leisure'] ?? '', ['pitch', 'stadium', 'sports_centre', 'track'], true)) return 'terrain_sport';
    if ($amenity === 'marketplace') return 'place_marche';
    if ($amenity === 'townhall') return 'mairie';
    if ($amenity === 'courthouse' || ($t['office'] ?? '') === 'government') return 'batiment_administratif';
    return null;
}

/**
 * Distance (m) entre un point et la géométrie d'un élément OSM : 0 à l'intérieur d'une surface,
 * sinon jusqu'au bord le plus proche. Projection locale plane, suffisante à cette échelle.
 */
function proxDistanceElement($lat, $lon, array $e) {
    $kx = cos(deg2rad($lat)) * 111320;
    $ky = 110540;
    $xy = function ($p) use ($lat, $lon, $kx, $ky) { return [($p['lon'] - $lon) * $kx, ($p['lat'] - $lat) * $ky]; };

    if (isset($e['lat'], $e['lon'])) {
        return geolocDistanceSimple($lat, $lon, $e['lat'], $e['lon']);
    }
    $anneaux = [];
    if (!empty($e['geometry'])) $anneaux[] = ['pts' => $e['geometry'], 'surface' => true];
    foreach ($e['members'] ?? [] as $m) {
        if (!empty($m['geometry'])) $anneaux[] = ['pts' => $m['geometry'], 'surface' => ($m['role'] ?? '') === 'outer'];
    }
    $min = INF;
    foreach ($anneaux as $a) {
        $pts = array_map($xy, array_values(array_filter($a['pts'], function ($p) { return isset($p['lat'], $p['lon']); })));
        $n = count($pts);
        if (!$n) continue;
        if ($n === 1) { $min = min($min, hypot($pts[0][0], $pts[0][1])); continue; }
        $ferme = $a['surface'] && $n > 3 && abs($pts[0][0] - $pts[$n - 1][0]) < .01 && abs($pts[0][1] - $pts[$n - 1][1]) < .01;
        if ($ferme) {
            // Point dans le polygone (lancer de rayon)
            $dedans = false;
            for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                if ((($pts[$i][1] > 0) !== ($pts[$j][1] > 0)) &&
                    (0 < ($pts[$j][0] - $pts[$i][0]) * (0 - $pts[$i][1]) / ($pts[$j][1] - $pts[$i][1]) + $pts[$i][0])) {
                    $dedans = !$dedans;
                }
            }
            if ($dedans) return 0.0;
        }
        for ($i = 1; $i < $n; $i++) {
            $min = min($min, proxDistanceSegment($pts[$i - 1], $pts[$i]));
        }
    }
    if ($min === INF && isset($e['center'])) return geolocDistanceSimple($lat, $lon, $e['center']['lat'], $e['center']['lon']);
    return $min;
}

// Distance de l'origine au segment [a, b] (coordonnées planes en mètres)
function proxDistanceSegment(array $a, array $b) {
    $dx = $b[0] - $a[0];
    $dy = $b[1] - $a[1];
    $l2 = $dx * $dx + $dy * $dy;
    $t = $l2 > 0 ? max(0, min(1, -($a[0] * $dx + $a[1] * $dy) / $l2)) : 0;
    return hypot($a[0] + $t * $dx, $a[1] + $t * $dy);
}

function geolocDistanceSimple($lat1, $lon1, $lat2, $lon2) {
    $r = 6371000;
    $dlat = deg2rad($lat2 - $lat1);
    $dlon = deg2rad($lon2 - $lon1);
    $a = sin($dlat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dlon / 2) ** 2;
    return 2 * $r * asin(min(1, sqrt($a)));
}

// Point le plus représentatif d'un élément pour l'afficher sur la carte
function proxPositionElement(array $e) {
    if (isset($e['lat'], $e['lon'])) return [$e['lat'], $e['lon']];
    if (isset($e['center'])) return [$e['center']['lat'], $e['center']['lon']];
    if (isset($e['bounds'])) return [($e['bounds']['minlat'] + $e['bounds']['maxlat']) / 2, ($e['bounds']['minlon'] + $e['bounds']['maxlon']) / 2];
    $g = $e['geometry'] ?? ($e['members'][0]['geometry'] ?? []);
    if (!$g) return null;
    $lat = array_sum(array_column($g, 'lat')) / count($g);
    $lon = array_sum(array_column($g, 'lon')) / count($g);
    return [$lat, $lon];
}

/**
 * Éléments OpenStreetMap autour du point (Overpass), mis en cache par position arrondie (~10 m)
 */
function proxElementsOsm($lat, $lon) {
    $dossier = __DIR__ . '/../cache/proximite';
    $fichier = $dossier . '/' . sprintf('%.4f_%.4f', $lat, $lon) . '.json';
    if (is_file($fichier) && filemtime($fichier) > time() - PROX_CACHE_DUREE) {
        $cache = json_decode(file_get_contents($fichier), true);
        if (is_array($cache)) return $cache;
    }

    $a1 = PROX_RAYON_GRANDS_SITES;
    $a2 = PROX_RAYON_SITES;
    $p = sprintf('%.6f,%.6f', $lat, $lon);
    $requete = "[out:json][timeout:25];(
        nwr(around:$a1,$p)[\"name\"~\"pr.fecture|gouverneur|gouvernorat|governor|pr.sidence de la r.publique|palais de l.unit|assembl.e nationale|s.nat|primature|premier minist|divisional office\",i];
        nwr(around:$a2,$p)[\"amenity\"~\"^(school|college|university|kindergarten|hospital|clinic|doctors|health_post|place_of_worship|marketplace|townhall|courthouse)$\"];
        nwr(around:$a2,$p)[\"healthcare\"~\"^(hospital|clinic|centre|doctor|health_post|birthing_centre)$\"];
        nwr(around:$a2,$p)[\"leisure\"~\"^(pitch|stadium|sports_centre|track)$\"];
        nwr(around:$a2,$p)[\"office\"=\"government\"];
    );out geom qt;";
    // Serveur principal, puis un miroir si le premier est saturé (réponse attendue pendant la consultation)
    try {
        $elements = osmRequete($requete, 'https://overpass-api.de/api/interpreter', 20)['elements'];
    } catch (RuntimeException $e) {
        $elements = osmRequete($requete, 'https://overpass.private.coffee/api/interpreter', 20)['elements'];
    }

    if (!is_dir($dossier)) @mkdir($dossier, 0775, true);
    @file_put_contents($fichier, json_encode($elements));
    return $elements;
}

/**
 * Sites protégés autour d'un point, du plus proche au plus éloigné
 * @return array ['sites' => [...], 'osm_disponible' => bool]
 */
function proxSitesSensibles($lat, $lon, $zone = 'urbaine') {
    $regles = proxRegles();
    $zone = $zone === 'rurale' ? 'rurale' : 'urbaine';
    $sites = [];

    // Points d'intérêt saisis dans le SGDI
    try {
        foreach (getAllPOIsForMap() as $p) {
            $d = geolocDistanceSimple($lat, $lon, (float) $p['latitude'], (float) $p['longitude']);
            if ($d > PROX_RAYON_GRANDS_SITES) continue;
            $min = (int) ($zone === 'rurale' ? ($p['distance_min_rural_metres'] ?: $p['distance_min_metres']) : $p['distance_min_metres']);
            $sites[] = ['nom' => $p['nom'], 'categorie' => $p['categorie_nom'], 'code' => $p['categorie_code'], 'distance' => round($d),
                        'minimum' => $min, 'conforme' => $d >= $min, 'source' => 'SGDI',
                        'lat' => (float) $p['latitude'], 'lon' => (float) $p['longitude'], 'url' => null];
        }
    } catch (Exception $e) {
        error_log('Contrôle de proximité, points d\'intérêt : ' . $e->getMessage());
    }

    // OpenStreetMap
    $osm_disponible = true;
    try {
        $vus = [];
        foreach (proxElementsOsm($lat, $lon) as $e) {
            $cle = $e['type'] . $e['id'];
            if (isset($vus[$cle])) continue;
            $vus[$cle] = true;
            $code = proxCategorieOsm($e['tags'] ?? []);
            if (!$code || !isset($regles[$code])) continue;
            $d = proxDistanceElement($lat, $lon, $e);
            $min = $regles[$code][$zone];
            // Sites à 100 m : seulement ceux du voisinage immédiat
            if ($d > ($min >= 1000 ? PROX_RAYON_GRANDS_SITES : PROX_RAYON_SITES)) continue;
            $pos = proxPositionElement($e);
            $t = $e['tags'] ?? [];
            $sites[] = ['nom' => $t['name'] ?? ($t['name:fr'] ?? ''), 'categorie' => $regles[$code]['nom'], 'code' => $code,
                        'distance' => round($d), 'minimum' => $min, 'conforme' => $d >= $min, 'source' => 'OpenStreetMap',
                        'lat' => $pos[0] ?? null, 'lon' => $pos[1] ?? null,
                        'url' => 'https://www.openstreetmap.org/' . $e['type'] . '/' . $e['id']];
        }
    } catch (Exception $e) {
        $osm_disponible = false;
        error_log('Contrôle de proximité, Overpass : ' . $e->getMessage());
    }

    usort($sites, function ($a, $b) { return [$a['conforme'], $a['distance']] <=> [$b['conforme'], $b['distance']]; });
    return ['sites' => $sites, 'osm_disponible' => $osm_disponible];
}

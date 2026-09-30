<?php
/**
 * SGDI - Référence OpenStreetMap des points de distribution au Cameroun
 *
 * Télécharge via Overpass les stations-service, points de vente GPL et dépôts pétroliers,
 * les rattache à leur région et les enregistre dans cache/osm_points.json.
 * Sans cache (juste après un déploiement), le jeu de départ versionné
 * assets/data/osm_points_seed.json est utilisé.
 *
 * Format d'un point : [lat, lon, catégorie (station|gpl|depot), nom, marque, ville, région, gpl (0|1),
 *                      texte brut marque/opérateur/nom (rapprochement des dossiers historiques)]
 * Lieux habités : cache/osm_lieux.json (jeu de départ assets/data/osm_lieux_seed.json), [nom, lat, lon, type, région]
 */

define('OSM_FICHIER_CACHE', __DIR__ . '/../cache/osm_points.json');
define('OSM_FICHIER_SEED', __DIR__ . '/../assets/data/osm_points_seed.json');
define('OSM_FICHIER_REGIONS', __DIR__ . '/../assets/data/cameroun_regions.json');
define('OSM_FICHIER_LIEUX', __DIR__ . '/../cache/osm_lieux.json');
define('OSM_FICHIER_LIEUX_SEED', __DIR__ . '/../assets/data/osm_lieux_seed.json');
define('OSM_DUREE_VALIDITE', 24 * 3600); // resynchroniser au plus une fois par jour

/**
 * Contours simplifiés des 10 régions : [['nom' => ..., 'ring' => [[lat, lon], ...]], ...]
 */
function osmRegions() {
    static $regions = null;
    if ($regions === null) {
        $regions = json_decode((string) @file_get_contents(OSM_FICHIER_REGIONS), true) ?: [];
    }
    return $regions;
}

/**
 * Région contenant le point (test point dans polygone), '' si hors du territoire
 */
function osmRegionDuPoint($lat, $lon) {
    foreach (osmRegions() as $r) {
        $ring = $r['ring'];
        $dedans = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            list($yi, $xi) = $ring[$i];
            list($yj, $xj) = $ring[$j];
            if ((($yi > $lat) != ($yj > $lat)) && ($lon < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $dedans = !$dedans;
            }
        }
        if ($dedans) return $r['nom'];
    }
    return '';
}

/**
 * Marque ou opérateur reconnu à partir des étiquettes OSM
 */
function osmMarque(array $t) {
    $texte = strtolower(($t['brand'] ?? '') . ' ' . ($t['operator'] ?? '') . ' ' . ($t['name'] ?? ''));
    $marques = [
        'total' => 'TotalEnergies', 'totalenergies' => 'TotalEnergies', 'tradex' => 'Tradex', 'mrs' => 'MRS', 'neptune' => 'Neptune', 'oilibya' => 'OLA Energy',
        'ola' => 'OLA Energy', 'bocom' => 'Bocom', 'corlay' => 'Corlay', 'green oil' => 'Green Oil', 'camoco' => 'Camoco',
        'petrolex' => 'Petrolex', 'glocal' => 'Glocal', 'blessing' => 'Blessing', 'gulfin' => 'Gulfin', 'camgaz' => 'Camgaz',
        'scdp' => 'SCDP', 'afrigaz' => 'Afrigaz', 'sctm' => 'SCTM', 'mobil' => 'Mobil', 'shell' => 'Shell', 'vivo' => 'Vivo Energy',
    ];
    foreach ($marques as $cle => $nom) {
        if (preg_match('/\b' . preg_quote($cle, '/') . '\b/', $texte)) return $nom;
    }
    return 'Autre / indépendant';
}

/**
 * Transforme les éléments Overpass en points classés et rattachés à une région
 */
function osmClasserElements(array $elements) {
    $points = [];
    $vus = [];
    foreach ($elements as $e) {
        $t = $e['tags'] ?? [];
        $lat = $e['lat'] ?? ($e['center']['lat'] ?? null);
        $lon = $e['lon'] ?? ($e['center']['lon'] ?? null);
        if ($lat === null || $lon === null) continue;

        $contenu = strtolower(($t['content'] ?? '') . ' ' . ($t['substance'] ?? ''));
        if (($t['amenity'] ?? '') === 'fuel') {
            $cat = 'station';
        } elseif (($t['shop'] ?? '') === 'gas') {
            $cat = 'gpl';
        } elseif (in_array($t['industrial'] ?? '', ['oil', 'depot', 'fuel_depot', 'gas'], true)
            || (($t['man_made'] ?? '') === 'storage_tank' && preg_match('/fuel|oil|lpg|gas|diesel|petrol|gasoline/', $contenu))
            || ($t['landuse'] ?? '') === 'industrial') {
            $cat = 'depot';
        } else {
            continue;
        }

        // Plusieurs cuves d'un même dépôt : un seul point
        $cle = $cat . round($lat, 3) . '|' . round($lon, 3);
        if ($cat === 'depot' && isset($vus[$cle])) continue;
        $vus[$cle] = true;

        $region = osmRegionDuPoint($lat, $lon);
        if ($region === '') continue;

        $gpl = $cat === 'gpl' || ($t['fuel:lpg'] ?? '') === 'yes' || preg_match('/lpg|gpl/', $contenu . ' ' . strtolower($t['name'] ?? ''));
        $points[] = [round($lat, 5), round($lon, 5), $cat, trim($t['name'] ?? ''), osmMarque($t), trim($t['addr:city'] ?? ''), $region, $gpl ? 1 : 0,
                     trim(($t['brand'] ?? '') . ' ' . ($t['operator'] ?? '') . ' ' . ($t['name'] ?? ''))];
    }
    return $points;
}

/**
 * Interroge Overpass pour les points de distribution. Renvoie la réponse décodée ou lève une exception.
 */
function osmTelecharger() {
    return osmRequete('[out:json][timeout:120];area["ISO3166-1"="CM"][admin_level=2]->.cm;('
             . 'nwr["amenity"="fuel"](area.cm);nwr["shop"="gas"](area.cm);'
             . 'nwr["industrial"~"oil|fuel|gas|depot"](area.cm);nwr["man_made"="storage_tank"](area.cm);'
             . 'nwr["landuse"="industrial"]["name"~"SCDP|[Dd][ée]p[oô]t|GPL|[Pp][ée]trol|Tradex|[Gg]az",i](area.cm););out center tags;');
}

/**
 * Exécute une requête Overpass QL
 */
function osmRequete($requete) {
    $ch = curl_init('https://overpass-api.de/api/interpreter');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['data' => $requete]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 150,
        CURLOPT_USERAGENT => 'SGDI-MINEE-DPPG/2.0 (carte des infrastructures)', // exigé par Overpass
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $reponse = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erreur = curl_error($ch);
    curl_close($ch);

    $donnees = $reponse ? json_decode($reponse, true) : null;
    if ($code !== 200 || !isset($donnees['elements'])) {
        throw new RuntimeException("Overpass indisponible (HTTP $code) $erreur");
    }
    return $donnees;
}

/**
 * Synchronise la référence OSM et l'écrit dans le cache. Renvoie le nombre de points par catégorie.
 */
function osmSynchroniser($fichier = OSM_FICHIER_CACHE) {
    $brut = osmTelecharger();
    $points = osmClasserElements($brut['elements']);
    if (count($points) < 100) {
        // Réponse anormalement pauvre : ne pas écraser une référence valide
        throw new RuntimeException('Réponse Overpass incomplète (' . count($points) . ' points)');
    }

    $dossier = dirname($fichier);
    if (!is_dir($dossier)) @mkdir($dossier, 0775, true);
    $json = json_encode([
        'maj' => $brut['osm3s']['timestamp_osm_base'] ?? gmdate('Y-m-d\TH:i:s\Z'),
        'points' => $points,
    ], JSON_UNESCAPED_UNICODE);
    $tmp = $fichier . '.' . getmypid() . '.tmp';
    if (file_put_contents($tmp, $json) === false || !rename($tmp, $fichier)) {
        @unlink($tmp);
        throw new RuntimeException("Écriture impossible dans $fichier");
    }

    $stats = [];
    foreach ($points as $p) $stats[$p[2]] = ($stats[$p[2]] ?? 0) + 1;

    // Lieux habités (utiles au rapprochement des dossiers historiques) : un échec ne bloque pas les stations
    try {
        $stats['lieux'] = osmSynchroniserLieux();
    } catch (Exception $e) {
        error_log('Synchronisation des lieux OSM : ' . $e->getMessage());
    }
    return $stats;
}

/**
 * Télécharge les lieux habités (ville, bourg, village, quartier…) et les enregistre dans le cache.
 * Renvoie le nombre de lieux.
 */
function osmSynchroniserLieux($fichier = OSM_FICHIER_LIEUX) {
    $brut = osmRequete('[out:json][timeout:120];area["ISO3166-1"="CM"][admin_level=2]->.cm;'
        . 'node["place"~"^(city|town|village|suburb|neighbourhood|quarter|hamlet)$"]["name"](area.cm);out;');
    $lieux = [];
    foreach ($brut['elements'] as $e) {
        $region = osmRegionDuPoint($e['lat'], $e['lon']);
        if ($region === '') continue;
        $lieux[] = [trim($e['tags']['name']), round($e['lat'], 5), round($e['lon'], 5), $e['tags']['place'], $region];
    }
    if (count($lieux) < 1000) {
        throw new RuntimeException('Réponse Overpass incomplète (' . count($lieux) . ' lieux)');
    }
    $dossier = dirname($fichier);
    if (!is_dir($dossier)) @mkdir($dossier, 0775, true);
    $tmp = $fichier . '.' . getmypid() . '.tmp';
    if (file_put_contents($tmp, json_encode(['lieux' => $lieux], JSON_UNESCAPED_UNICODE)) === false || !rename($tmp, $fichier)) {
        @unlink($tmp);
        throw new RuntimeException("Écriture impossible dans $fichier");
    }
    return count($lieux);
}

/**
 * Lieux habités : le cache s'il existe, sinon le jeu de départ
 */
function osmLieux() {
    foreach ([OSM_FICHIER_LIEUX, OSM_FICHIER_LIEUX_SEED] as $f) {
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        if (!empty($d['lieux'])) return $d['lieux'];
    }
    return [];
}

/**
 * Référence OSM à afficher : le cache s'il existe, sinon le jeu de départ
 */
function osmDonnees() {
    foreach ([OSM_FICHIER_CACHE, OSM_FICHIER_SEED] as $f) {
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        if (!empty($d['points'])) {
            $d['source'] = $f === OSM_FICHIER_CACHE ? 'cache' : 'seed';
            return $d;
        }
    }
    return ['maj' => null, 'points' => [], 'source' => 'aucune'];
}

/**
 * La référence doit-elle être resynchronisée ?
 */
function osmAActualiser() {
    $echec = dirname(OSM_FICHIER_CACHE) . '/osm_sync.echec';
    if (is_file($echec) && (time() - filemtime($echec)) < 3600) {
        return false; // dernier essai en échec il y a moins d'une heure
    }
    return !is_file(OSM_FICHIER_CACHE) || (time() - filemtime(OSM_FICHIER_CACHE)) > OSM_DUREE_VALIDITE;
}

/**
 * Mémorise un échec de synchronisation (pour espacer les nouvelles tentatives)
 */
function osmNoterEchec() {
    $dossier = dirname(OSM_FICHIER_CACHE);
    if (!is_dir($dossier)) @mkdir($dossier, 0775, true);
    @touch($dossier . '/osm_sync.echec');
}

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
function osmRequete($requete, $url = 'https://overpass-api.de/api/interpreter', $delai = 150) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['data' => $requete]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $delai,
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
        . 'nwr["place"~"^(city|town|village|suburb|neighbourhood|quarter|hamlet|locality)$"]["name"](area.cm);out center tags;');
    $lieux = [];
    foreach ($brut['elements'] as $e) {
        // Point, ou centre du contour (quartiers décrits par une surface)
        $lat = $e['lat'] ?? ($e['center']['lat'] ?? null);
        $lon = $e['lon'] ?? ($e['center']['lon'] ?? null);
        if ($lat === null) continue;
        $region = osmRegionDuPoint($lat, $lon);
        if ($region === '') continue;
        $lieux[] = [trim($e['tags']['name']), round($lat, 5), round($lon, 5), $e['tags']['place'], $region];
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
    static $memo = null;
    if ($memo !== null) return $memo;
    foreach ([OSM_FICHIER_CACHE, OSM_FICHIER_SEED] as $f) {
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        if (!empty($d['points'])) {
            $d['source'] = $f === OSM_FICHIER_CACHE ? 'cache' : 'seed';
            $d['points'] = osmDedoublonner($d['points']);
            return $memo = $d;
        }
    }
    return ['maj' => null, 'points' => [], 'source' => 'aucune'];
}

define('OSM_DISTANCE_DOUBLON', 40); // une même station saisie deux fois dans OSM (point et bâtiment, ou deux contributeurs)

/**
 * Stations et points GPL saisis plusieurs fois dans OpenStreetMap : un seul point par station.
 * Deux points de même catégorie à moins de 40 m sont la même station s'ils ont la même marque,
 * des noms semblables, ou si l'un n'a ni nom ni marque. On garde le mieux renseigné.
 */
function osmDedoublonner(array $points) {
    $cle = function ($p) { return preg_replace('/[^a-z0-9]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $p))); };
    $info = function ($p) { return strlen(trim(($p[3] ?? '') . ($p[8] ?? ''))); };
    $memeStation = function ($a, $b) use ($cle, $info) {
        if ($info($a) === 0 || $info($b) === 0) return true;
        // Même réseau sous deux noms (ancienne étiquette « Texaco » et nouvelle « MRS »)
        $ra = osmReseaux(($a[8] ?? '') . ' ' . $a[3] . ' ' . $a[4]);
        if ($ra && array_intersect($ra, osmReseaux(($b[8] ?? '') . ' ' . $b[3] . ' ' . $b[4]))) return true;
        if ($a[4] !== 'Autre / indépendant' || $b[4] !== 'Autre / indépendant') return $a[4] === $b[4];
        $na = $cle($a[3]); $nb = $cle($b[3]);
        return $na !== '' && $nb !== '' && (strpos($na, $nb) !== false || strpos($nb, $na) !== false);
    };

    // Grille d'environ 110 m pour ne comparer que les voisins
    $grille = [];
    foreach ($points as $i => $p) $grille[round($p[0], 3) . '|' . round($p[1], 3)][] = $i;
    $retire = [];
    foreach ($points as $i => $p) {
        if (isset($retire[$i]) || !in_array($p[2], ['station', 'gpl'], true)) continue;
        for ($dy = -1; $dy <= 1; $dy++) for ($dx = -1; $dx <= 1; $dx++) {
            foreach ($grille[round(round($p[0], 3) + $dy / 1000, 3) . '|' . round(round($p[1], 3) + $dx / 1000, 3)] ?? [] as $j) {
                if ($j <= $i || isset($retire[$j]) || $points[$j][2] !== $p[2]) continue;
                $q = $points[$j];
                if (osmDistance($p[0], $p[1], $q[0], $q[1]) > OSM_DISTANCE_DOUBLON || !$memeStation($p, $q)) continue;
                // Garder le point le mieux renseigné ; GPL disponible si l'un des deux l'indique
                if ($info($q) > $info($p)) { $q[7] = $q[7] || $p[7]; $points[$i] = $p = $q; } else { $points[$i][7] = $p[7] = $p[7] || $q[7]; }
                $retire[$j] = true;
            }
        }
    }
    return array_values(array_diff_key($points, $retire));
}

/**
 * Marques d'un même réseau, sous des noms différents : CORLAY exploite le réseau MRS, qui a repris
 * les anciennes stations Texaco (OSM : « MRS CORLAY », « Texaco »…).
 * @return array groupe => mots qui le désignent
 */
function osmReseauxEquivalents() {
    return [
        'corlay-mrs' => ['corlay', 'coray', 'mrs', 'texaco'],
    ];
}

// Réseaux reconnus dans un texte (nom de dossier, ou nom, marque et opérateur d'une station)
function osmReseaux($texte) {
    $mots = preg_split('/[^a-z0-9]+/', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $texte)), -1, PREG_SPLIT_NO_EMPTY);
    $reseaux = [];
    foreach (osmReseauxEquivalents() as $groupe => $cles) {
        if (array_intersect($cles, $mots)) $reseaux[] = $groupe;
    }
    return $reseaux;
}

function osmDistance($lat1, $lon1, $lat2, $lon2) {
    $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;
    return 12742000 * asin(min(1, sqrt($a)));
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

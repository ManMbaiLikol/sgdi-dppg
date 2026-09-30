<?php
/**
 * SGDI - Géolocalisation des dossiers historiques par rapprochement avec OpenStreetMap
 *
 * Les dossiers historiques (import MINEE) n'ont que la marque (nom_demandeur), la localité (ville)
 * et la région. Pour chacun :
 *   1. la localité est retrouvée parmi les lieux habités OSM de la même région (tolérance aux coquilles) ;
 *   2. les stations OSM de la même marque sont cherchées dans un rayon adapté à la taille du lieu ;
 *   3. le résultat est « unique » (une station pour un dossier), « ambigu » (choix humain) ou sans candidate.
 * Aucune coordonnée n'est écrite sans validation humaine.
 */

require_once __DIR__ . '/osm_sync.php';
require_once __DIR__ . '/map_functions.php';

// Score enregistré dans dossiers.score_matching_osm
define('GEOLOC_SCORE_UNIQUE', 95);   // correspondance unique validée
define('GEOLOC_SCORE_CHOIX', 75);    // candidate choisie par un humain parmi plusieurs
define('GEOLOC_SCORE_IGNORE', 0);    // « aucune ne correspond » : ne plus proposer
define('GEOLOC_DISTANCE_DEJA_UTILISEE', 30); // une station OSM à moins de 30 m d'un dossier géolocalisé est déjà attribuée

// Rayon de recherche autour du centre du lieu, selon son type OSM
function geolocRayon($type) {
    $rayons = ['city' => 12000, 'town' => 6000, 'village' => 4000, 'hamlet' => 3000, 'suburb' => 3000, 'quarter' => 2500, 'neighbourhood' => 2000];
    return $rayons[$type] ?? 3000;
}

function geolocNormaliser($s) {
    $s = mb_strtolower(trim((string) $s));
    $s = strtr($s, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
                    'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', '_' => ' ', '-' => ' ', "'" => ' ']);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
}

// Similarité de 0 à 1, tolérante aux fautes de frappe (« Nkonsamba » / « Nkongsamba »)
function geolocSimilarite($a, $b) {
    if ($a === $b) return 1.0;
    if ($a === '' || $b === '') return 0.0;
    return 1 - levenshtein($a, $b) / max(strlen($a), strlen($b));
}

// Mots distinctifs d'un nom de distributeur (sans « petroleum », « sarl »…)
function geolocMotsMarque($nom) {
    static $vides = ['petroleum', 'petroleums', 'petrol', 'oil', 'oils', 'energy', 'energies', 'sarl', 'sa', 'sas', 'cameroun', 'cameroon',
        'station', 'stations', 'services', 'service', 'ets', 'group', 'groupe', 'gaz', 'gas', 'distribution', 'company', 'the', 'de', 'du',
        'des', 'la', 'le', 'et', 'and', 'fuel', 'plc', 'ltd'];
    return array_values(array_filter(explode(' ', geolocNormaliser($nom)), function ($m) use ($vides) {
        return strlen($m) >= 3 && !in_array($m, $vides, true);
    }));
}

// Même distributeur ? Marque reconnue : comparaison des marques ; sinon tous les mots distinctifs doivent apparaître
function geolocMemeMarque($nom_sgdi, $marque_sgdi, array $point_osm) {
    if ($marque_sgdi !== 'Autre / indépendant') {
        return $marque_sgdi === $point_osm[4];
    }
    $mots = geolocMotsMarque($nom_sgdi);
    if (!$mots) return false;
    $cible = ' ' . geolocNormaliser($point_osm[8] ?? $point_osm[3]) . ' ';
    foreach ($mots as $m) {
        if (strpos($cible, " $m ") === false) return false;
    }
    return true;
}

function geolocDistance($lat1, $lon1, $lat2, $lon2) {
    $x = deg2rad($lon2 - $lon1) * cos(deg2rad(($lat1 + $lat2) / 2));
    $y = deg2rad($lat2 - $lat1);
    return 6371000 * sqrt($x * $x + $y * $y);
}

/**
 * Propositions de géolocalisation pour les dossiers historiques sans coordonnées.
 *
 * @return array ['dossiers' => [id => [dossier, localite, candidates[], type]], 'stats' => [...]]
 *   candidate : ['lat', 'lon', 'nom', 'marque', 'distance' (m du centre de la localité)]
 *   type : 'unique' | 'ambigu' | 'aucune' | 'localite_introuvable'
 */
function geolocPropositions() {
    global $pdo;

    // Régions officielles, reconnues malgré les variantes (« Sud_Ouest », « SUD-OUEST »)
    $regions = [];
    foreach (osmRegions() as $r) $regions[geolocNormaliser($r['nom'])] = $r['nom'];

    // Lieux habités indexés par région
    $lieux = [];
    foreach (osmLieux() as $l) {
        $lieux[$l[4]][] = ['n' => geolocNormaliser($l[0]), 'nom' => $l[0], 'lat' => $l[1], 'lon' => $l[2], 'type' => $l[3]];
    }

    // Stations OSM, hors celles déjà attribuées à un dossier géolocalisé
    $positions = [];
    foreach ($pdo->query("SELECT coordonnees_gps FROM dossiers WHERE coordonnees_gps IS NOT NULL AND coordonnees_gps <> ''") as $r) {
        $c = parseGPSCoordinates($r['coordonnees_gps']);
        if ($c) $positions[] = [$c['latitude'], $c['longitude']];
    }
    $stations = [];
    foreach (osmDonnees()['points'] as $p) {
        if ($p[2] !== 'station') continue;
        foreach ($positions as $pos) {
            if (abs($pos[0] - $p[0]) < .001 && abs($pos[1] - $p[1]) < .001 && geolocDistance($pos[0], $pos[1], $p[0], $p[1]) < GEOLOC_DISTANCE_DEJA_UTILISEE) continue 2;
        }
        $stations[] = $p;
    }

    $dossiers = $pdo->query("SELECT id, numero, nom_demandeur, ville, region FROM dossiers
                             WHERE est_historique = 1 AND (coordonnees_gps IS NULL OR coordonnees_gps = '')
                             AND (score_matching_osm IS NULL OR score_matching_osm <> " . GEOLOC_SCORE_IGNORE . ")
                             ORDER BY region, ville, nom_demandeur")->fetchAll();

    $cache_localites = [];
    $resultats = [];
    $groupes = [];
    foreach ($dossiers as $d) {
        $region = $regions[geolocNormaliser($d['region'])] ?? '';
        $cle_loc = $region . '|' . geolocNormaliser($d['ville']);
        if (!array_key_exists($cle_loc, $cache_localites)) {
            // Lieu de même nom le plus ressemblant ; à égalité, le plus grand (ville avant village)
            $meilleur = null;
            $score = 0;
            $v = geolocNormaliser($d['ville']);
            foreach ($lieux[$region] ?? [] as $l) {
                $s = geolocSimilarite($v, $l['n']);
                if ($s >= 0.85 && ($s > $score || ($s == $score && geolocRayon($l['type']) > geolocRayon($meilleur['type'])))) {
                    $meilleur = $l;
                    $score = $s;
                }
            }
            $cache_localites[$cle_loc] = $meilleur;
        }
        $localite = $cache_localites[$cle_loc];
        $resultats[$d['id']] = ['dossier' => $d, 'localite' => $localite, 'candidates' => [], 'type' => 'localite_introuvable'];
        if (!$localite) continue;

        $marque = osmMarque(['name' => $d['nom_demandeur']]);
        foreach ($stations as $p) {
            if (!geolocMemeMarque($d['nom_demandeur'], $marque, $p)) continue;
            $dist = geolocDistance($localite['lat'], $localite['lon'], $p[0], $p[1]);
            if ($dist <= geolocRayon($localite['type'])) {
                $resultats[$d['id']]['candidates'][] = ['lat' => $p[0], 'lon' => $p[1], 'nom' => $p[3] ?: 'Station sans nom', 'marque' => $p[4], 'distance' => (int) round($dist)];
            }
        }
        usort($resultats[$d['id']]['candidates'], function ($a, $b) { return $a['distance'] - $b['distance']; });
        $groupes[geolocNormaliser($d['nom_demandeur']) . '|' . $cle_loc][] = $d['id'];
    }

    // Unique : un seul dossier de cette marque dans la localité, et une seule station candidate
    foreach ($groupes as $ids) {
        foreach ($ids as $id) {
            $n = count($resultats[$id]['candidates']);
            $resultats[$id]['type'] = $n === 0 ? 'aucune' : (count($ids) === 1 && $n === 1 ? 'unique' : 'ambigu');
        }
    }

    $stats = ['unique' => 0, 'ambigu' => 0, 'aucune' => 0, 'localite_introuvable' => 0];
    foreach ($resultats as $r) $stats[$r['type']]++;
    return ['dossiers' => $resultats, 'stats' => $stats];
}

/**
 * Enregistre la position d'un dossier historique après validation humaine.
 * La position doit être l'une des candidates calculées pour ce dossier (pas de coordonnées arbitraires).
 */
function geolocEnregistrer($dossier_id, array $candidate, $score, $user_id) {
    global $pdo;
    $gps = $candidate['lat'] . ',' . $candidate['lon'];
    $stmt = $pdo->prepare("UPDATE dossiers SET coordonnees_gps = ?, latitude = ?, longitude = ?, source_gps = ?, score_matching_osm = ?
                           WHERE id = ? AND est_historique = 1 AND (coordonnees_gps IS NULL OR coordonnees_gps = '')");
    $stmt->execute([$gps, $candidate['lat'], $candidate['lon'],
        $score === GEOLOC_SCORE_UNIQUE ? 'OSM (rapprochement automatique validé)' : 'OSM (choix parmi les candidates)', $score, $dossier_id]);
    if ($stmt->rowCount() !== 1) return false;
    addHistoriqueDossier($dossier_id, $user_id, 'modification_gps',
        'Position GPS issue d\'OpenStreetMap (' . $candidate['nom'] . ', ' . $candidate['marque'] . ') : ' . $gps);
    return true;
}

/**
 * « Aucune candidate ne correspond » : le dossier n'est plus proposé (reste à géolocaliser sur le terrain)
 */
function geolocIgnorer($dossier_id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE dossiers SET score_matching_osm = ? WHERE id = ? AND est_historique = 1 AND (coordonnees_gps IS NULL OR coordonnees_gps = '')");
    $stmt->execute([GEOLOC_SCORE_IGNORE, $dossier_id]);
    if ($stmt->rowCount() === 1) {
        addHistoriqueDossier($dossier_id, $user_id, 'rapprochement_gps_ignore', 'Aucune station OpenStreetMap ne correspond : position à relever sur le terrain');
        return true;
    }
    return false;
}

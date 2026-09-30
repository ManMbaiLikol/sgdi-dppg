<?php
/**
 * SGDI - Géolocalisation des dossiers historiques par rapprochement avec OpenStreetMap
 *
 * Les dossiers historiques (import MINEE) n'ont que la marque (nom_demandeur), la localité (ville)
 * et la région. Pour chacun :
 *   1. la localité est retrouvée parmi les lieux habités OSM (tolérance aux coquilles, variantes de nom,
 *      autre région si le nom est unique au Cameroun) ;
 *   2. les stations candidates sont cherchées par niveau de confiance :
 *        a. même marque dans le rayon du lieu,
 *        b. même marque un peu plus loin (rayon élargi),
 *        c. station d'une autre marque ou sans marque dans le rayon (station reprise, rebaptisée ou mal renseignée) ;
 *   3. le dossier est « unique » (niveau a, une station pour un dossier), « ambigu » (choix humain),
 *      « aucune » (localité trouvée, pas de station : position approximative possible) ou « localité introuvable ».
 * Aucune coordonnée n'est écrite sans validation humaine.
 */

require_once __DIR__ . '/osm_sync.php';
require_once __DIR__ . '/map_functions.php';

// Score enregistré dans dossiers.score_matching_osm
define('GEOLOC_SCORE_UNIQUE', 95);   // correspondance unique validée
define('GEOLOC_SCORE_CHOIX', 75);    // candidate choisie par un humain parmi plusieurs
define('GEOLOC_SCORE_AUTO', 60);     // meilleure candidate attribuée automatiquement, à vérifier
define('GEOLOC_SOURCE_AUTO', 'OSM (attribution automatique – à vérifier)');
define('GEOLOC_SCORE_APPROX', 30);   // centre de la localité : position approximative
define('GEOLOC_SCORE_IGNORE', 0);    // « aucune station ne correspond » : plus de candidates proposées
define('GEOLOC_SOURCE_APPROX', 'Centre de la localité (approximatif)');
define('GEOLOC_DISTANCE_DEJA_UTILISEE', 30); // une station OSM à moins de 30 m d'un dossier géolocalisé est déjà attribuée
define('GEOLOC_FACTEUR_RAYON_ELARGI', 2.5);
define('GEOLOC_MAX_CANDIDATES_AUTRES', 8);

// Libellés des niveaux de candidates
function geolocNiveaux() {
    return [
        'meme_marque' => ['Même marque', 'succes'],
        'meme_marque_eloignee' => ['Même marque, plus loin', 'instruction'],
        'autre_marque' => ['Autre marque dans OSM', 'attention'],
        'sans_marque' => ['Sans marque dans OSM', 'preparation'],
    ];
}

// Une position enregistrée est-elle approximative (centre de localité) ?
function geolocEstApproximatif($source_gps) {
    return $source_gps === GEOLOC_SOURCE_APPROX;
}

// Rayon de recherche autour du centre du lieu, selon son type OSM
function geolocRayon($type) {
    $rayons = ['city' => 12000, 'town' => 6000, 'village' => 4000, 'hamlet' => 3000, 'suburb' => 3000,
               'quarter' => 2500, 'neighbourhood' => 2000, 'locality' => 2500];
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
    // Espaces ignorés : « Biyem Assi » = « Biyemassi »
    $a2 = str_replace(' ', '', $a);
    $b2 = str_replace(' ', '', $b);
    if ($a2 === $b2) return 0.99;
    return 1 - levenshtein($a2, $b2) / max(strlen($a2), strlen($b2));
}

/**
 * Variantes d'un nom de localité, de la plus fidèle à la plus large :
 * « Tsinga village » → « tsinga » ; « Nkozoa par Yaoundé » → « nkozoa » ; « Bangangté I » → « bangangte » ;
 * « Nouvelle Gare Routière Bamougoum » → … → « bamougoum »
 */
function geolocMotsAccessoires() {
    return ['village', 'centre', 'center', 'ville', 'carrefour', 'marche', 'gare', 'routiere', 'nouvelle', 'nouveau',
            'sud', 'nord', 'est', 'ouest', 'i', 'ii', 'iii', 'iv', 'v', '1', '2', '3', 'bis', 'quartier', 'entree', 'sortie'];
}

// Nom sans ses mots accessoires (« Mimboman I » → « mimboman »)
function geolocNomSimplifie($nom) {
    $mots = array_filter(explode(' ', geolocNormaliser($nom)), function ($m) { return !in_array($m, geolocMotsAccessoires(), true); });
    return implode(' ', $mots);
}

function geolocVariantesLocalite($ville) {
    $accessoires = geolocMotsAccessoires();
    $n = geolocNormaliser($ville);
    if ($n === '') return [];
    $variantes = [$n];
    $n = trim(preg_replace('/\s+par\s+.*$/', '', $n));
    $variantes[] = $n;
    $mots = array_values(array_filter(explode(' ', $n), function ($m) use ($accessoires) { return !in_array($m, $accessoires, true); }));
    if ($mots) $variantes[] = implode(' ', $mots);
    // En dernier recours, chaque mot distinctif (le dernier d'abord : « … Bamougoum »)
    foreach (array_reverse($mots) as $m) {
        if (strlen($m) >= 5) $variantes[] = $m;
    }
    return array_values(array_unique(array_filter($variantes)));
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

/**
 * Marques d'un même réseau, sous des noms différents : CORLAY exploite le réseau MRS, qui a repris
 * les anciennes stations Texaco (OSM : « MRS CORLAY », « Texaco »…).
 * groupe => mots (normalisés) qui le désignent
 */
function geolocReseauxEquivalents() {
    return [
        'corlay-mrs' => ['corlay', 'coray', 'mrs', 'texaco'],
    ];
}

// Réseaux reconnus dans un texte (nom de dossier, ou nom, marque et opérateur d'une station OSM)
function geolocReseaux($texte) {
    $mots = explode(' ', geolocNormaliser($texte));
    $reseaux = [];
    foreach (geolocReseauxEquivalents() as $groupe => $cles) {
        if (array_intersect($cles, $mots)) $reseaux[] = $groupe;
    }
    return $reseaux;
}

// Même distributeur ? Même réseau, sinon marque reconnue : comparaison des marques ; sinon tous les mots distinctifs doivent apparaître
function geolocMemeMarque($nom_sgdi, $marque_sgdi, array $point_osm) {
    $reseaux = geolocReseaux($nom_sgdi);
    if ($reseaux && array_intersect($reseaux, geolocReseaux(($point_osm[8] ?? '') . ' ' . $point_osm[3] . ' ' . $point_osm[4]))) {
        return true;
    }
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

/**
 * Noms compatibles, pour le contrôle a posteriori : même marque, ou un mot distinctif commun
 * (« AFRICA PETRO. » et « Africa Petroleum », « SOPROPEC » et « Station Sopropec »)
 */
function geolocNomsCompatibles($nom_sgdi, array $point_osm) {
    if (geolocMemeMarque($nom_sgdi, osmMarque(['name' => $nom_sgdi]), $point_osm)) return true;
    $cible = geolocMotsMarque(($point_osm[8] ?? '') . ' ' . $point_osm[3]);
    foreach (geolocMotsMarque($nom_sgdi) as $m) {
        if (strlen($m) < 4) continue;
        foreach ($cible as $c) {
            if (strlen($c) >= 4 && (strpos($c, $m) === 0 || strpos($m, $c) === 0)) return true;
        }
    }
    return false;
}

function geolocDistance($lat1, $lon1, $lat2, $lon2) {
    $x = deg2rad($lon2 - $lon1) * cos(deg2rad(($lat1 + $lat2) / 2));
    $y = deg2rad($lat2 - $lat1);
    return 6371000 * sqrt($x * $x + $y * $y);
}

/**
 * Retrouve la localité d'un dossier. Renvoie le lieu OSM (+ 'autre_region' => bool) ou null.
 */
function geolocTrouverLocalite($ville, $region, array $lieux_par_region, array $lieux_par_nom) {
    foreach (geolocVariantesLocalite($ville) as $v) {
        // 1. Dans la région du dossier : lieu le plus ressemblant, à égalité le plus grand
        $meilleur = null;
        $score = 0;
        foreach ($lieux_par_region[$region] ?? [] as $l) {
            $s = max(geolocSimilarite($v, $l['n']), geolocSimilarite($v, $l['n2']));
            if ($s >= 0.85 && ($s > $score || ($s == $score && geolocRayon($l['type']) > geolocRayon($meilleur['type'])))) {
                $meilleur = $l;
                $score = $s;
            }
        }
        if ($meilleur) return $meilleur + ['autre_region' => false];
        // 2. Ailleurs au Cameroun, seulement si le nom exact est unique (région mal saisie)
        if (isset($lieux_par_nom[$v]) && count($lieux_par_nom[$v]) === 1) {
            return $lieux_par_nom[$v][0] + ['autre_region' => true];
        }
    }
    return null;
}

/**
 * Propositions de géolocalisation pour les dossiers historiques sans position précise.
 *
 * @return array ['dossiers' => [id => [dossier, localite, candidates[], type]], 'stats' => [...]]
 *   candidate : ['lat', 'lon', 'nom', 'marque', 'distance' (m du centre de la localité), 'niveau']
 *   type : 'unique' | 'ambigu' | 'aucune' | 'localite_introuvable'
 */
function geolocPropositions(array $exclure = []) {
    global $pdo;

    // Régions officielles, reconnues malgré les variantes (« Sud_Ouest », « SUD-OUEST »)
    $regions = [];
    foreach (osmRegions() as $r) $regions[geolocNormaliser($r['nom'])] = $r['nom'];

    // Lieux habités, par région et par nom
    $lieux_par_region = [];
    $lieux_par_nom = [];
    foreach (osmLieux() as $l) {
        $lieu = ['n' => geolocNormaliser($l[0]), 'n2' => geolocNomSimplifie($l[0]), 'nom' => $l[0], 'lat' => $l[1], 'lon' => $l[2], 'type' => $l[3], 'region' => $l[4]];
        $lieux_par_region[$l[4]][] = $lieu;
        $lieux_par_nom[$lieu['n']][] = $lieu;
        if ($lieu['n2'] !== $lieu['n'] && $lieu['n2'] !== '') $lieux_par_nom[$lieu['n2']][] = $lieu;
    }

    // Stations OSM, hors celles déjà attribuées à un dossier précisément géolocalisé
    $positions = [];
    $stmt = $pdo->prepare("SELECT coordonnees_gps FROM dossiers WHERE coordonnees_gps IS NOT NULL AND coordonnees_gps <> ''
                           AND (source_gps IS NULL OR source_gps <> ?)");
    $stmt->execute([GEOLOC_SOURCE_APPROX]);
    foreach ($stmt as $r) {
        $c = parseGPSCoordinates($r['coordonnees_gps']);
        if ($c) $positions[] = [$c['latitude'], $c['longitude']];
    }
    foreach ($exclure as $pos) $positions[] = $pos; // stations déjà attribuées (attribution automatique en cours)
    $stations = [];
    foreach (osmDonnees()['points'] as $p) {
        if ($p[2] !== 'station') continue;
        foreach ($positions as $pos) {
            if (abs($pos[0] - $p[0]) < .001 && abs($pos[1] - $p[1]) < .001 && geolocDistance($pos[0], $pos[1], $p[0], $p[1]) < GEOLOC_DISTANCE_DEJA_UTILISEE) continue 2;
        }
        $stations[] = $p;
    }

    // Dossiers sans position, ou avec une position seulement approximative
    $stmt = $pdo->prepare("SELECT id, numero, nom_demandeur, ville, region, score_matching_osm, source_gps FROM dossiers
                           WHERE est_historique = 1
                           AND (coordonnees_gps IS NULL OR coordonnees_gps = '' OR source_gps = ?)
                           ORDER BY region, ville, nom_demandeur");
    $stmt->execute([GEOLOC_SOURCE_APPROX]);
    $dossiers = $stmt->fetchAll();

    $cache_localites = [];
    $resultats = [];
    $groupes = [];
    foreach ($dossiers as $d) {
        $region = $regions[geolocNormaliser($d['region'])] ?? '';
        $cle_loc = $region . '|' . geolocNormaliser($d['ville']);
        if (!array_key_exists($cle_loc, $cache_localites)) {
            $cache_localites[$cle_loc] = geolocTrouverLocalite($d['ville'], $region, $lieux_par_region, $lieux_par_nom);
        }
        $localite = $cache_localites[$cle_loc];
        $resultats[$d['id']] = ['dossier' => $d, 'localite' => $localite, 'candidates' => [], 'type' => 'localite_introuvable',
                                'approximatif' => geolocEstApproximatif($d['source_gps'])];
        if (!$localite) continue;

        // « Aucune ne correspond » déjà indiqué : plus de candidates, position approximative seulement
        if ((string) $d['score_matching_osm'] === (string) GEOLOC_SCORE_IGNORE) {
            $resultats[$d['id']]['type'] = 'aucune';
            continue;
        }

        $marque = osmMarque(['name' => $d['nom_demandeur']]);
        $rayon = geolocRayon($localite['type']);
        $par_niveau = ['meme_marque' => [], 'meme_marque_eloignee' => [], 'autre_marque' => [], 'sans_marque' => []];
        foreach ($stations as $p) {
            $dist = geolocDistance($localite['lat'], $localite['lon'], $p[0], $p[1]);
            if ($dist > $rayon * GEOLOC_FACTEUR_RAYON_ELARGI) continue;
            $meme = geolocMemeMarque($d['nom_demandeur'], $marque, $p);
            if ($meme) {
                $niveau = $dist <= $rayon ? 'meme_marque' : 'meme_marque_eloignee';
            } elseif ($dist <= $rayon) {
                $niveau = ($p[4] === 'Autre / indépendant' && trim($p[8] ?? '') === '') ? 'sans_marque' : 'autre_marque';
            } else {
                continue;
            }
            $par_niveau[$niveau][] = ['lat' => $p[0], 'lon' => $p[1], 'nom' => $p[3] ?: 'Station sans nom',
                                      'marque' => $p[4], 'distance' => (int) round($dist), 'niveau' => $niveau];
        }
        foreach ($par_niveau as &$liste) {
            usort($liste, function ($a, $b) { return $a['distance'] - $b['distance']; });
        }
        unset($liste);

        // Le niveau le plus sûr disponible ; stations d'autres marques ou sans marque seulement à défaut
        if ($par_niveau['meme_marque']) {
            $candidates = $par_niveau['meme_marque'];
        } elseif ($par_niveau['meme_marque_eloignee']) {
            $candidates = $par_niveau['meme_marque_eloignee'];
        } else {
            $candidates = array_slice(array_merge($par_niveau['autre_marque'], $par_niveau['sans_marque']), 0, GEOLOC_MAX_CANDIDATES_AUTRES);
            usort($candidates, function ($a, $b) { return $a['distance'] - $b['distance']; });
        }
        $resultats[$d['id']]['candidates'] = $candidates;
        $groupes[geolocNormaliser($d['nom_demandeur']) . '|' . $cle_loc][] = $d['id'];
    }

    // Unique : même marque dans le rayon, un seul dossier de cette marque dans la localité, une seule station
    foreach ($groupes as $ids) {
        foreach ($ids as $id) {
            $c = $resultats[$id]['candidates'];
            if (!$c) {
                $resultats[$id]['type'] = 'aucune';
            } elseif (count($ids) === 1 && count($c) === 1 && $c[0]['niveau'] === 'meme_marque' && !$resultats[$id]['localite']['autre_region']) {
                $resultats[$id]['type'] = 'unique';
            } else {
                $resultats[$id]['type'] = 'ambigu';
            }
        }
    }

    $stats = ['unique' => 0, 'ambigu' => 0, 'aucune' => 0, 'localite_introuvable' => 0, 'approximatifs' => 0];
    foreach ($resultats as $r) {
        $stats[$r['type']]++;
        if ($r['approximatif']) $stats['approximatifs']++;
    }
    return ['dossiers' => $resultats, 'stats' => $stats];
}

// Écrit une position si le dossier n'en a pas, ou seulement une approximative
function geolocEcrirePosition($dossier_id, $lat, $lon, $source, $score) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE dossiers SET coordonnees_gps = ?, latitude = ?, longitude = ?, source_gps = ?, score_matching_osm = ?
                           WHERE id = ? AND est_historique = 1 AND (coordonnees_gps IS NULL OR coordonnees_gps = '' OR source_gps = ?)");
    $stmt->execute([$lat . ',' . $lon, $lat, $lon, $source, $score, $dossier_id, GEOLOC_SOURCE_APPROX]);
    return $stmt->rowCount() === 1;
}

/**
 * Enregistre la position d'une station OSM après validation humaine.
 * La position doit être l'une des candidates calculées pour ce dossier (pas de coordonnées arbitraires).
 */
function geolocEnregistrer($dossier_id, array $candidate, $score, $user_id) {
    $precisions = ['meme_marque_eloignee' => ' – même marque, plus loin', 'autre_marque' => ' – autre marque dans OSM', 'sans_marque' => ' – sans marque dans OSM'];
    $sources = [GEOLOC_SCORE_UNIQUE => 'OSM (rapprochement automatique validé)', GEOLOC_SCORE_AUTO => GEOLOC_SOURCE_AUTO];
    $source = ($sources[$score] ?? 'OSM (choix parmi les candidates)')
            . ($precisions[$candidate['niveau'] ?? ''] ?? '');
    if (!geolocEcrirePosition($dossier_id, $candidate['lat'], $candidate['lon'], $source, $score)) return false;
    addHistoriqueDossier($dossier_id, $user_id, 'modification_gps',
        'Position GPS issue d\'OpenStreetMap (' . $candidate['nom'] . ', ' . $candidate['marque'] . ') : ' . $candidate['lat'] . ',' . $candidate['lon']);
    return true;
}

/**
 * Position approximative : centre de la localité retrouvée (station absente d'OpenStreetMap)
 */
function geolocPlacerApproximatif($dossier_id, array $localite, $user_id) {
    if (!geolocEcrirePosition($dossier_id, $localite['lat'], $localite['lon'], GEOLOC_SOURCE_APPROX, GEOLOC_SCORE_APPROX)) return false;
    addHistoriqueDossier($dossier_id, $user_id, 'modification_gps',
        'Position approximative : centre de ' . $localite['nom'] . ' (' . $localite['lat'] . ',' . $localite['lon'] . '), à préciser sur le terrain');
    return true;
}

/**
 * Attribution automatique de toutes les correspondances, sans validation une à une.
 *   - correspondance sûre : la station trouvée ;
 *   - plusieurs candidates : la meilleure candidate encore libre (même marque d'abord, puis la plus proche du centre,
 *     à défaut une station sans nom ni marque dans OSM, jamais une station d'une autre marque) ;
 *     une station n'est jamais attribuée à deux dossiers ;
 *   - aucune station libre, ou aucune station OSM : centre de la localité (position approximative).
 * Les positions gardent une source explicite pour être revues et corrigées au cas par cas.
 *
 * @param bool $simulation true : calcule sans rien écrire
 * @return array Nombre de dossiers par résultat
 */
function geolocAttribuerTout($user_id, $simulation = false) {
    global $pdo;
    $rang = ['meme_marque' => 0, 'meme_marque_eloignee' => 1, 'autre_marque' => 2, 'sans_marque' => 3];
    $stats = ['sure' => 0, 'choix_automatique' => 0, 'approximatif' => 0, 'deja_approximatif' => 0, 'localite_introuvable' => 0];
    $utilisees = [];   // stations attribuées pendant l'exécution : "lat,lon" => [lat, lon]
    $attribues = [];   // dossiers ayant reçu une station
    $cle = function ($c) { return round($c['lat'], 5) . ',' . round($c['lon'], 5); };

    if (!$simulation) $pdo->beginTransaction();
    try {
        // Passages successifs : quand les stations de la même marque sont toutes prises, le passage suivant
        // propose le niveau d'après (même marque plus loin, autre marque…), jusqu'à ce que plus rien ne change.
        for ($passage = 1; ; $passage++) {
            $propositions = geolocPropositions(array_values($utilisees))['dossiers'];

            // Jamais une station d'une autre marque : le nom du dossier ne correspondrait pas à la station réelle
            $ordre = [];
            foreach ($propositions as $p) {
                if (isset($attribues[$p['dossier']['id']]) || !in_array($p['type'], ['unique', 'ambigu'], true)) continue;
                $p['candidates'] = array_values(array_filter($p['candidates'], function ($c) { return $c['niveau'] !== 'autre_marque'; }));
                if ($p['candidates']) $ordre[] = $p;
            }
            // Correspondances sûres d'abord, puis meilleur niveau et station la plus proche du centre
            usort($ordre, function ($a, $b) use ($rang) {
                $poids = function ($p) use ($rang) { return $p['type'] === 'unique' ? 0 : 1 + $rang[$p['candidates'][0]['niveau']]; };
                return [$poids($a), $a['candidates'][0]['distance'], $a['dossier']['id']]
                   <=> [$poids($b), $b['candidates'][0]['distance'], $b['dossier']['id']];
            });

            $nouveaux = 0;
            foreach ($ordre as $p) {
                $choisie = null;
                foreach ($p['candidates'] as $c) {
                    if (!isset($utilisees[$cle($c)])) { $choisie = $c; break; }
                }
                if (!$choisie) continue;
                $id = (int) $p['dossier']['id'];
                $utilisees[$cle($choisie)] = [$choisie['lat'], $choisie['lon']];
                $attribues[$id] = true;
                $nouveaux++;
                $sure = $passage === 1 && $p['type'] === 'unique';
                $stats[$sure ? 'sure' : 'choix_automatique']++;
                if (!$simulation) geolocEnregistrer($id, $choisie, $sure ? GEOLOC_SCORE_UNIQUE : GEOLOC_SCORE_AUTO, $user_id);
            }
            if (!$nouveaux) break;
        }

        // Aucune station libre : centre de la localité
        foreach ($propositions as $id => $p) {
            if (isset($attribues[$id])) continue;
            if ($p['type'] === 'localite_introuvable') {
                $stats['localite_introuvable']++;
            } elseif ($p['approximatif']) {
                $stats['deja_approximatif']++;
            } else {
                $stats['approximatif']++;
                if (!$simulation) geolocPlacerApproximatif($id, $p['localite'], $user_id);
            }
        }
        if (!$simulation) $pdo->commit();
    } catch (Exception $e) {
        if (!$simulation) $pdo->rollBack();
        throw $e;
    }
    return $stats;
}

/**
 * Contrôle des correspondances : pour chaque dossier historique placé sur une station OpenStreetMap,
 * compare son nom avec le nom et la marque de la station OSM à la même position.
 * @return array liste de ['dossier', 'point' (station OSM ou null), 'verdict' : concordant | discordant | indetermine | introuvable]
 */
function geolocControlerCorrespondances() {
    global $pdo;
    $stations = array_values(array_filter(osmDonnees()['points'], function ($p) { return $p[2] === 'station'; }));
    $dossiers = $pdo->query("SELECT id, numero, nom_demandeur, ville, region, coordonnees_gps, source_gps, score_matching_osm
                             FROM dossiers WHERE est_historique = 1 AND source_gps LIKE 'OSM%' ORDER BY region, ville, nom_demandeur")->fetchAll();
    $resultats = [];
    foreach ($dossiers as $d) {
        $c = parseGPSCoordinates($d['coordonnees_gps']);
        $point = null;
        $meilleure = GEOLOC_DISTANCE_DEJA_UTILISEE;
        if ($c) {
            foreach ($stations as $p) {
                if (abs($p[0] - $c['latitude']) > .001 || abs($p[1] - $c['longitude']) > .001) continue;
                $dist = geolocDistance($c['latitude'], $c['longitude'], $p[0], $p[1]);
                if ($dist < $meilleure) { $meilleure = $dist; $point = $p; }
            }
        }
        if (!$point) {
            $verdict = 'introuvable'; // station retirée d'OSM depuis, ou position modifiée à la main
        } elseif (geolocNomsCompatibles($d['nom_demandeur'], $point)) {
            $verdict = 'concordant';
        } elseif ($point[4] === 'Autre / indépendant' && trim(($point[8] ?? '') . $point[3]) === '') {
            $verdict = 'indetermine'; // station sans nom ni marque dans OSM : rien ne contredit le dossier
        } else {
            $verdict = 'discordant';
        }
        $resultats[] = ['dossier' => $d, 'point' => $point, 'verdict' => $verdict];
    }
    return $resultats;
}

/**
 * Corrige les correspondances discordantes attribuées automatiquement : la position est retirée,
 * puis l'attribution automatique cherche une station libre de la même marque, à défaut le centre de la localité.
 * Les choix faits à la main dans l'écran de rapprochement ne sont pas modifiés.
 * @return array ['controle' => liste, 'corriges' => n, 'attribution' => statistiques]
 */
function geolocCorrigerDiscordances($user_id, $simulation = false) {
    global $pdo;
    $controle = geolocControlerCorrespondances();
    $a_corriger = array_filter($controle, function ($r) {
        return $r['verdict'] === 'discordant' && strpos($r['dossier']['source_gps'], 'OSM (choix parmi') !== 0;
    });
    if ($simulation) return ['controle' => $controle, 'corriges' => count($a_corriger), 'attribution' => null];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE dossiers SET coordonnees_gps = NULL, latitude = NULL, longitude = NULL, source_gps = NULL, score_matching_osm = NULL
                               WHERE id = ? AND est_historique = 1");
        foreach ($a_corriger as $r) {
            $stmt->execute([$r['dossier']['id']]);
            addHistoriqueDossier($r['dossier']['id'], $user_id, 'modification_gps',
                'Position retirée : la station OpenStreetMap attribuée automatiquement (' . ($r['point'][3] ?: 'sans nom') . ', ' . $r['point'][4]
                . ') ne correspond pas au nom du dossier');
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    // Nouvelle attribution (même marque uniquement), sinon centre de la localité
    return ['controle' => $controle, 'corriges' => count($a_corriger), 'attribution' => geolocAttribuerTout($user_id)];
}

/**
 * « Aucune station ne correspond » : plus de candidates proposées (position approximative toujours possible)
 */
function geolocIgnorer($dossier_id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE dossiers SET score_matching_osm = ? WHERE id = ? AND est_historique = 1
                           AND (coordonnees_gps IS NULL OR coordonnees_gps = '' OR source_gps = ?)");
    $stmt->execute([GEOLOC_SCORE_IGNORE, $dossier_id, GEOLOC_SOURCE_APPROX]);
    if ($stmt->rowCount() === 1) {
        addHistoriqueDossier($dossier_id, $user_id, 'rapprochement_gps_ignore', 'Aucune station OpenStreetMap ne correspond : position à relever sur le terrain');
        return true;
    }
    return false;
}

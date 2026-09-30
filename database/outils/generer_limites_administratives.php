<?php
/**
 * Génère les limites des départements et arrondissements du Cameroun depuis OpenStreetMap
 * (assets/data/cameroun_departements.json, assets/data/cameroun_arrondissements.json).
 *
 * Sert à déduire département et arrondissement des coordonnées GPS, ces champs n'étant pas
 * renseignés pour les dossiers historiques. À relancer seulement si le découpage administratif change.
 * Usage : php database/outils/generer_limites_administratives.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Script à exécuter en ligne de commande uniquement.\n");
}

require_once __DIR__ . '/../../includes/osm_sync.php';

const TOLERANCE = 0.0008; // simplification des contours (~90 m), largement suffisante pour classer un point

// Assemble les chemins d'une relation en anneaux fermés
function assemblerAnneaux(array $chemins) {
    $cle = function ($p) { return sprintf('%.7f,%.7f', $p[0], $p[1]); };
    $anneaux = [];
    while ($chemins) {
        $anneau = array_shift($chemins);
        $progres = true;
        while ($cle(reset($anneau)) !== $cle(end($anneau)) && $progres) {
            $progres = false;
            $fin = $cle(end($anneau));
            foreach ($chemins as $k => $c) {
                if ($cle($c[0]) === $fin) { $anneau = array_merge($anneau, array_slice($c, 1)); }
                elseif ($cle(end($c)) === $fin) { $anneau = array_merge($anneau, array_slice(array_reverse($c), 1)); }
                else continue;
                unset($chemins[$k]);
                $progres = true;
                break;
            }
        }
        if (count($anneau) >= 4) $anneaux[] = $anneau;
    }
    return $anneaux;
}

// Douglas-Peucker
function simplifier(array $pts, $tol) {
    if (count($pts) < 3) return $pts;
    $a = $pts[0]; $b = end($pts); $max = 0; $index = 0;
    $dx = $b[1] - $a[1]; $dy = $b[0] - $a[0]; $l = hypot($dx, $dy) ?: 1e-12;
    for ($i = 1; $i < count($pts) - 1; $i++) {
        $d = abs($dy * $pts[$i][1] - $dx * $pts[$i][0] + $b[1] * $a[0] - $b[0] * $a[1]) / $l;
        if ($d > $max) { $max = $d; $index = $i; }
    }
    if ($max <= $tol) return [$a, $b];
    return array_merge(array_slice(simplifier(array_slice($pts, 0, $index + 1), $tol), 0, -1), simplifier(array_slice($pts, $index), $tol));
}

foreach ([6 => 'departements', 8 => 'arrondissements'] as $niveau => $nom) {
    echo "Téléchargement des $nom (niveau $niveau)… ";
    $requete = "[out:json][timeout:300];area[\"ISO3166-1\"=\"CM\"][admin_level=2]->.cm;"
        . "rel(area.cm)[\"boundary\"=\"administrative\"][\"admin_level\"=\"$niveau\"];out geom;";
    // Réponse brute gardée dans cache/ : une relance du script ne retélécharge pas
    $brut = __DIR__ . "/../../cache/limites_$niveau.json";
    $r = is_file($brut) ? json_decode(file_get_contents($brut), true) : null;
    foreach ($r ? [] : ['https://overpass-api.de/api/interpreter', 'https://overpass.private.coffee/api/interpreter', 'https://overpass.kumi.systems/api/interpreter'] as $serveur) {
        try { $r = osmRequete($requete, $serveur, 320); break; } catch (RuntimeException $x) { echo '(' . parse_url($serveur, PHP_URL_HOST) . ' : ' . $x->getMessage() . ') '; }
    }
    if (!$r) exit("\nAucun serveur Overpass n'a répondu, réessayez plus tard.\n");
    if (!is_file($brut)) { @mkdir(dirname($brut), 0775, true); file_put_contents($brut, json_encode($r)); }
    $limites = [];
    foreach ($r['elements'] as $e) {
        $chemins = [];
        foreach ($e['members'] ?? [] as $m) {
            if ($m['type'] !== 'way' || !in_array($m['role'] ?? '', ['outer', ''], true) || empty($m['geometry'])) continue;
            $chemins[] = array_map(function ($p) { return [$p['lat'], $p['lon']]; }, $m['geometry']);
        }
        $anneaux = [];
        foreach (assemblerAnneaux($chemins) as $a) {
            // Anneau fermé (premier point = dernier) : simplifié en deux moitiés, coupées au point le plus éloigné du départ
            $loin = 0; $dmax = -1;
            foreach ($a as $i => $p) { $d = hypot($p[0] - $a[0][0], $p[1] - $a[0][1]); if ($d > $dmax) { $dmax = $d; $loin = $i; } }
            $s = array_merge(array_slice(simplifier(array_slice($a, 0, $loin + 1), TOLERANCE), 0, -1), simplifier(array_slice($a, $loin), TOLERANCE));
            $s = array_map(function ($p) { return [round($p[0], 4), round($p[1], 4)]; }, $s);
            if (count($s) >= 4) $anneaux[] = $s;
        }
        if (!$anneaux) { echo "\n  (sans contour exploitable : " . ($e['tags']['name'] ?? $e['id']) . ")"; continue; }
        $tous = array_merge(...$anneaux);
        $lats = array_column($tous, 0); $lons = array_column($tous, 1);
        $limites[] = ['nom' => $e['tags']['name:fr'] ?? $e['tags']['name'] ?? '', 'bbox' => [min($lats), min($lons), max($lats), max($lons)], 'anneaux' => $anneaux];
    }
    usort($limites, function ($a, $b) { return strcmp($a['nom'], $b['nom']); });
    $fichier = __DIR__ . "/../../assets/data/cameroun_$nom.json";
    file_put_contents($fichier, json_encode($limites, JSON_UNESCAPED_UNICODE));
    printf("\n  %d %s, %s Ko\n", count($limites), $nom, number_format(filesize($fichier) / 1024, 0, ',', ' '));
}

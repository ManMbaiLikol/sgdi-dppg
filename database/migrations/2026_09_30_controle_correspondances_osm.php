<?php
/**
 * Contrôle des correspondances entre dossiers historiques et stations OpenStreetMap
 *
 * Compare le nom de chaque dossier placé sur une station OSM avec le nom et la marque que
 * OpenStreetMap donne à cette station :
 *   - concordant  : même marque ou même nom ;
 *   - discordant  : OSM affiche un autre nom ou une autre marque (ex. dossier SOPROPEC sur une station TotalEnergies) ;
 *   - indéterminé : la station OSM n'a ni nom ni marque ;
 *   - introuvable : plus de station OSM à cette position.
 * Correction des discordances, OpenStreetMap faisant foi sur l'identité de la station : position du dossier
 * retirée (la station reste affichée sous son nom OSM), puis nouvelle attribution (station libre de la même
 * marque, sinon centre de la localité). Les choix faits à la main sont corrigés de la même façon.
 *
 * Usage : php database/migrations/2026_09_30_controle_correspondances_osm.php [--dry-run] [--liste]
 *   --liste : détail de chaque discordance
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Script à exécuter en ligne de commande uniquement.\n");
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/geoloc_historique.php';

$dry_run = in_array('--dry-run', $argv, true);
$liste = in_array('--liste', $argv, true);
set_time_limit(0);
ini_set('memory_limit', '512M');

$user_id = (int) $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY actif DESC, id LIMIT 1")->fetchColumn();
if (!$user_id) exit("Aucun administrateur trouvé.\n");

echo "Contrôle des correspondances avec OpenStreetMap" . ($dry_run ? " (simulation)" : "") . "\n";
echo str_repeat('-', 70) . "\n";

$r = geolocCorrigerDiscordances($user_id, $dry_run);
$par_verdict = ['concordant' => 0, 'discordant' => 0, 'doublon' => 0, 'indetermine' => 0, 'introuvable' => 0];
foreach ($r['controle'] as $c) $par_verdict[$c['verdict']]++;
$manuels = array_filter($r['controle'], function ($c) { return $c['verdict'] === 'discordant' && strpos($c['dossier']['source_gps'], 'OSM (choix parmi') === 0; });

printf("  Dossiers placés sur une station OSM          : %d\n", count($r['controle']));
printf("  Nom concordant avec OSM                      : %d\n", $par_verdict['concordant']);
printf("  Nom discordant (autre nom ou marque dans OSM): %d\n", $par_verdict['discordant']);
printf("    dont choix faits à la main                 : %d\n", count($manuels));
printf("  Station déjà occupée par un autre dossier    : %d\n", $par_verdict['doublon']);
printf("  Positions retirées (OSM fait foi)            : %d\n", $r['corriges']);
printf("  Recalés sur une station saisie deux fois     : %d\n", $r['recales']);
printf("  Station OSM sans nom ni marque (indéterminé) : %d\n", $par_verdict['indetermine']);
printf("  Plus de station OSM à cette position         : %d\n", $par_verdict['introuvable']);

if ($liste || $dry_run) {
    echo str_repeat('-', 70) . "\nDiscordances (dossier SGDI  ≠  station OpenStreetMap) :\n";
    foreach ($r['controle'] as $c) {
        if (!in_array($c['verdict'], ['discordant', 'doublon'], true)) continue;
        $d = $c['dossier'];
        $p = $c['point'];
        printf("  %-6s %-28s %-16s %s %s (%s)%s\n", $d['numero'], mb_substr($d['nom_demandeur'], 0, 28), mb_substr((string) $d['ville'], 0, 16),
            $c['verdict'] === 'doublon' ? '= déjà pris :' : '≠', $p[3] ?: 'sans nom', $p[4], strpos($d['source_gps'], 'OSM (choix parmi') === 0 ? '  [choix manuel]' : '');
    }
}

if ($r['attribution']) {
    $a = $r['attribution'];
    echo str_repeat('-', 70) . "\nNouvelle attribution (même marque uniquement) :\n";
    printf("  Station de la même marque trouvée            : %d\n", $a['sure'] + $a['choix_automatique']);
    printf("  Placés au centre de la localité              : %d\n", $a['approximatif']);
}
echo str_repeat('-', 70) . "\n";
echo $dry_run ? "Simulation terminée, rien n'a été modifié.\n" : "Terminé.\n";

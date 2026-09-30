<?php
/**
 * Attribution automatique des positions GPS des stations historiques
 *
 * Valide toutes les correspondances OpenStreetMap sans validation une à une :
 *   - correspondance sûre : la station trouvée ;
 *   - plusieurs candidates : la meilleure encore libre (source « OSM (attribution automatique – à vérifier) ») ;
 *   - aucune station : centre de la localité (position approximative).
 * Les erreurs se corrigent ensuite au cas par cas dans Gestion GPS.
 *
 * Idempotent : seuls les dossiers sans position ou en position approximative sont traités.
 * Usage : php database/migrations/2026_09_30_attribution_auto_gps.php [--dry-run]
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Script à exécuter en ligne de commande uniquement.\n");
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/geoloc_historique.php';

$dry_run = in_array('--dry-run', $argv, true);
set_time_limit(0);
ini_set('memory_limit', '512M');

// Auteur des entrées d'historique : premier administrateur actif
$user_id = (int) $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY actif DESC, id LIMIT 1")->fetchColumn();
if (!$user_id) exit("Aucun administrateur trouvé.\n");

echo "Attribution automatique des positions GPS" . ($dry_run ? " (simulation)" : "") . "\n";
echo str_repeat('-', 60) . "\n";

$debut = microtime(true);
$stats = geolocAttribuerTout($user_id, $dry_run);

printf("  Correspondances sûres (station trouvée)      : %d\n", $stats['sure']);
printf("  Meilleure candidate attribuée (à vérifier)   : %d\n", $stats['choix_automatique']);
printf("  Centre de la localité (approximatif)         : %d\n", $stats['approximatif']);
printf("  Déjà en position approximative, inchangés    : %d\n", $stats['deja_approximatif']);
printf("  Localité introuvable, non traités            : %d\n", $stats['localite_introuvable']);
echo str_repeat('-', 60) . "\n";
printf("%s en %.1f s\n", $dry_run ? "Simulation terminée, rien n'a été écrit" : "Terminé", microtime(true) - $debut);

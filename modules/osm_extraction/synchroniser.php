<?php
/**
 * Synchronise la référence OpenStreetMap de la carte des infrastructures.
 * Ligne de commande uniquement :
 *   railway ssh -- "cd /var/www/html && php modules/osm_extraction/synchroniser.php"
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Script à exécuter en ligne de commande uniquement.\n");
}

require_once __DIR__ . '/../../includes/osm_sync.php';

echo "Synchronisation OpenStreetMap (Cameroun)...\n";
try {
    $stats = osmSynchroniser();
    printf("OK : %d stations-service, %d points de vente GPL, %d dépôts\n",
        $stats['station'] ?? 0, $stats['gpl'] ?? 0, $stats['depot'] ?? 0);
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, 'Échec : ' . $e->getMessage() . "\n");
    exit(1);
}

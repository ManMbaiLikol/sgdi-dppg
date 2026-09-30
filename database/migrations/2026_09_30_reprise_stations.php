<?php
/**
 * Migration : reprise de stations existantes
 *
 * - dossiers.dossier_repris_id : dossier de la station reprise (historique ou autorisée)
 * - statut « repris » : station existante dont la reprise a été approuvée ;
 *   elle reste dans l'historique mais la station continue sous le dossier de reprise
 *   (nouvelle dénomination, même emplacement).
 *
 * Idempotent : ne modifie que ce qui manque.
 * Usage : php database/migrations/2026_09_30_reprise_stations.php [--dry-run]
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Script à exécuter en ligne de commande uniquement.\n");
}

require_once __DIR__ . '/../../config/database.php';

$dry_run = in_array('--dry-run', $argv, true);
echo "Reprise de stations" . ($dry_run ? " (simulation)" : "") . "\n";
echo str_repeat('-', 60) . "\n";

$requetes = [];

// Colonne de lien vers la station reprise
$existe = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'dossier_repris_id'")->fetchColumn();
if (!$existe) {
    $requetes[] = "ALTER TABLE dossiers ADD COLUMN dossier_repris_id INT NULL DEFAULT NULL COMMENT 'Station existante reprise par ce dossier' AFTER sous_type,
                   ADD KEY idx_dossier_repris (dossier_repris_id)";
} else {
    echo "  Colonne dossier_repris_id : déjà présente\n";
}

// Statut « repris », ajouté à la liste existante sans toucher aux autres valeurs
$colonne = $pdo->query("SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'statut'")->fetch();
if (strpos($colonne['COLUMN_TYPE'], "'repris'") === false) {
    $type = preg_replace('/\)$/', ",'repris')", $colonne['COLUMN_TYPE']);
    $requetes[] = "ALTER TABLE dossiers MODIFY statut $type"
                . ($colonne['IS_NULLABLE'] === 'NO' ? ' NOT NULL' : ' NULL')
                . ($colonne['COLUMN_DEFAULT'] !== null ? ' DEFAULT ' . $pdo->quote(trim($colonne['COLUMN_DEFAULT'], "'")) : '');
} else {
    echo "  Statut « repris » : déjà présent\n";
}

if (!$requetes) {
    echo "Rien à faire.\n";
    exit(0);
}

foreach ($requetes as $sql) {
    echo ($dry_run ? "  [simulation] " : "  ") . preg_replace('/\s+/', ' ', $sql) . "\n";
    if (!$dry_run) $pdo->exec($sql);
}
echo str_repeat('-', 60) . "\n";
echo $dry_run ? "Simulation terminée, rien n'a été modifié.\n" : "Terminé.\n";

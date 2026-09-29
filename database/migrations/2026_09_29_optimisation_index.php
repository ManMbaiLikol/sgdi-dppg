<?php
/**
 * Migration : optimisation des index
 *
 * - Ajoute les index utilisés par les listes et tableaux de bord
 * - Supprime les index en double (même colonne déjà couverte par un autre index),
 *   qui ralentissent les écritures sans accélérer les lectures
 *
 * Idempotent : chaque opération vérifie l'état actuel, le script peut être relancé.
 * Usage : php database/migrations/2026_09_29_optimisation_index.php [--dry-run]
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Script à exécuter en ligne de commande uniquement.\n");
}

require_once __DIR__ . '/../../config/database.php';

$dry_run = in_array('--dry-run', $argv, true);

// Colonnes indexées (dans l'ordre) pour chaque index existant d'une table
function getIndexes(PDO $pdo, $table) {
    $stmt = $pdo->prepare("SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
                           FROM information_schema.STATISTICS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                           GROUP BY INDEX_NAME");
    $stmt->execute([$table]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

function tableHasColumns(PDO $pdo, $table, array $columns) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    foreach ($columns as $col) {
        $stmt->execute([$table, $col]);
        if ($stmt->fetchColumn() == 0) {
            return false;
        }
    }
    return true;
}

function run(PDO $pdo, $sql, $dry_run) {
    echo ($dry_run ? "  [simulation] " : "  ") . $sql . "\n";
    if (!$dry_run) {
        $pdo->exec($sql);
    }
}

// Index à créer : [table, nom, colonnes]
$a_creer = [
    ['dossiers', 'idx_statut_date_creation', ['statut', 'date_creation']], // WHERE statut = ? ORDER BY date_creation
    ['dossiers', 'idx_date_creation',        ['date_creation']],
    ['dossiers', 'idx_date_modification',    ['date_modification']],
    ['dossiers', 'idx_arrondissement',       ['arrondissement']],
    ['visas',    'idx_dossier_role',         ['dossier_id', 'role']],      // circuit de visa
];

// Index devenus inutiles : [table, nom, colonnes couvrant déjà cet index]
// Supprimé seulement si un autre index commence par ces colonnes.
$a_supprimer = [
    ['dossiers',                'idx_statut',                  ['statut']],
    ['dossiers',                'idx_numero',                  ['numero']],
    ['dossiers',                'idx_dossiers_coords',         ['coordonnees_gps']],
    ['visas',                   'idx_dossier',                 ['dossier_id']],
    ['commissions',             'idx_dossier',                 ['dossier_id']],
    ['decisions_finales',       'dossier_id_2',                ['dossier_id']],
    ['decisions_ministerielle', 'idx_dossier',                 ['dossier_id']],
    ['fiches_inspection',       'idx_dossier',                 ['dossier_id']],
    ['registre_public',         'idx_dossier',                 ['dossier_id']],
    ['permissions',             'idx_permissions_code',        ['code']],
    ['users',                   'idx_google_id',               ['google_id']],
    ['user_permissions',        'idx_user_permissions_user',   ['user_id']],
];

echo "Migration optimisation des index" . ($dry_run ? " (simulation)" : "") . "\n";
echo str_repeat('-', 60) . "\n";

$erreurs = 0;

echo "Création :\n";
foreach ($a_creer as [$table, $nom, $colonnes]) {
    if (!tableHasColumns($pdo, $table, $colonnes)) {
        echo "  - $table.$nom ignoré (table ou colonne absente)\n";
        continue;
    }
    $indexes = getIndexes($pdo, $table);
    if (isset($indexes[$nom])) {
        echo "  - $table.$nom existe déjà\n";
        continue;
    }
    try {
        run($pdo, "ALTER TABLE `$table` ADD INDEX `$nom` (`" . implode('`, `', $colonnes) . "`)", $dry_run);
    } catch (PDOException $e) {
        $erreurs++;
        echo "  ! Erreur : " . $e->getMessage() . "\n";
    }
}

echo "Suppression des doublons :\n";
foreach ($a_supprimer as [$table, $nom, $colonnes]) {
    $indexes = getIndexes($pdo, $table);
    if (!isset($indexes[$nom])) {
        echo "  - $table.$nom absent\n";
        continue;
    }
    // Vérifier qu'un autre index (existant, ou créé plus haut) couvre les mêmes colonnes
    $prefixe = implode(',', $colonnes);
    $couvert = false;
    foreach ($indexes as $autre => $cols) {
        if ($autre !== $nom && ($cols === $prefixe || strpos($cols, $prefixe . ',') === 0)) {
            $couvert = true;
            break;
        }
    }
    if (!$couvert && $dry_run) {
        // En simulation, les index de la section création n'existent pas encore
        foreach ($a_creer as [$t, , $c]) {
            $cols = implode(',', $c);
            if ($t === $table && ($cols === $prefixe || strpos($cols, $prefixe . ',') === 0)) {
                $couvert = true;
            }
        }
    }
    if (!$couvert) {
        echo "  - $table.$nom conservé (aucun autre index ne le couvre)\n";
        continue;
    }
    try {
        run($pdo, "ALTER TABLE `$table` DROP INDEX `$nom`", $dry_run);
    } catch (PDOException $e) {
        $erreurs++;
        echo "  ! Erreur : " . $e->getMessage() . "\n";
    }
}

echo str_repeat('-', 60) . "\n";
echo $erreurs === 0 ? "Terminé sans erreur.\n" : "Terminé avec $erreurs erreur(s).\n";
exit($erreurs === 0 ? 0 : 1);

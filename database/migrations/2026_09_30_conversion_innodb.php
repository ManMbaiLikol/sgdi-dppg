<?php
/**
 * Migration : conversion des tables MyISAM en InnoDB
 *
 * MyISAM ignore les transactions : quand une opération échoue en cours de route
 * (visa, décision, paiement…), les modifications déjà faites ne sont pas annulées
 * et le dossier se retrouve dans un état incohérent. InnoDB gère les transactions
 * et le verrouillage par ligne.
 *
 * Idempotent : seules les tables encore en MyISAM sont converties.
 * Usage : php database/migrations/2026_09_30_conversion_innodb.php [--dry-run]
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Script à exécuter en ligne de commande uniquement.\n");
}

require_once __DIR__ . '/../../config/database.php';

$dry_run = in_array('--dry-run', $argv, true);

$tables = $pdo->query("SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND ENGINE = 'MyISAM'
                       ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_KEY_PAIR);

echo "Conversion MyISAM → InnoDB" . ($dry_run ? " (simulation)" : "") . "\n";
echo str_repeat('-', 60) . "\n";

if (!$tables) {
    echo "Aucune table MyISAM : rien à faire.\n";
    exit(0);
}

$erreurs = 0;
foreach ($tables as $table => $lignes) {
    $sql = "ALTER TABLE `$table` ENGINE = InnoDB";
    echo ($dry_run ? "  [simulation] " : "  ") . str_pad($sql, 55) . " (~" . (int) $lignes . " lignes)";
    if ($dry_run) {
        echo "\n";
        continue;
    }
    try {
        $debut = microtime(true);
        $pdo->exec($sql);
        printf(" OK en %.1f s\n", microtime(true) - $debut);
    } catch (PDOException $e) {
        $erreurs++;
        echo "\n  ! Erreur : " . $e->getMessage() . "\n";
    }
}

echo str_repeat('-', 60) . "\n";
echo count($tables) . " table(s) " . ($dry_run ? "à convertir" : "traitée(s)") . ($erreurs ? ", $erreurs erreur(s)" : '') . ".\n";
exit($erreurs ? 1 : 0);

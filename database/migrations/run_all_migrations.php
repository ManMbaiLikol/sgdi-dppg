<?php
/**
 * Script d'exécution de TOUTES les migrations
 *
 * IMPORTANT: Ce script doit être exécuté UNE SEULE FOIS après le déploiement
 * sur Railway pour appliquer les corrections de base de données.
 *
 * URL d'accès: https://votre-app.railway.app/database/migrations/run_all_migrations.php
 *
 * SÉCURITÉ: Ce script vérifie un token secret pour éviter les exécutions non autorisées
 */

// Vérification du token de sécurité
$required_token = getenv('MIGRATION_TOKEN') ?: 'sgdi_migration_2025';
$provided_token = $_GET['token'] ?? '';

if ($provided_token !== $required_token) {
    http_response_code(403);
    die("❌ ACCÈS REFUSÉ: Token invalide\n\nPour exécuter les migrations, ajoutez ?token=XXX à l'URL");
}

require_once __DIR__ . '/../../config/database.php';

echo "═══════════════════════════════════════════════════\n";
echo "  EXÉCUTION DES MIGRATIONS - SGDI\n";
echo "═══════════════════════════════════════════════════\n\n";

$migrations = [
    '001_fix_roles_enum.sql' => 'Correction ENUM rôles et statuts',
    '002_fix_commissions_role_enum.sql' => 'Correction ENUM chef_commission_role'
];

$total_success = 0;
$total_errors = 0;

foreach ($migrations as $filename => $description) {
    echo "\n📋 Migration: $filename\n";
    echo "Description: $description\n";
    echo str_repeat("-", 50) . "\n";

    $sql_file = __DIR__ . '/' . $filename;

    if (!file_exists($sql_file)) {
        echo "⚠️  ATTENTION: Fichier non trouvé, ignoré\n";
        continue;
    }

    $sql = file_get_contents($sql_file);

    // Supprimer les commentaires SQL
    $sql = preg_replace('/--.*$/m', '', $sql);

    // Séparer les requêtes
    $queries = array_filter(
        array_map('trim', explode(';', $sql)),
        function($query) {
            return !empty($query) && strlen($query) > 5;
        }
    );

    echo "Requêtes à exécuter: " . count($queries) . "\n";

    try {
        $pdo->beginTransaction();

        foreach ($queries as $index => $query) {
            try {
                $pdo->exec($query);
                echo "  ✓ Requête " . ($index + 1) . " exécutée\n";
                $total_success++;
            } catch (PDOException $e) {
                // Ignorer les erreurs "already exists" ou "duplicate"
                if (strpos($e->getMessage(), 'Duplicate') !== false ||
                    strpos($e->getMessage(), 'already exists') !== false) {
                    echo "  ⚠️  Requête " . ($index + 1) . " ignorée (déjà appliquée)\n";
                } else {
                    throw $e;
                }
            }
        }

        $pdo->commit();
        echo "✅ Migration $filename terminée avec succès!\n";

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "❌ ERREUR: " . $e->getMessage() . "\n";
        $total_errors++;
    }
}

echo "\n═══════════════════════════════════════════════════\n";
echo "  RÉSUMÉ\n";
echo "═══════════════════════════════════════════════════\n";
echo "✅ Succès: $total_success requête(s)\n";
echo "❌ Erreurs: $total_errors requête(s)\n\n";

if ($total_errors === 0) {
    echo "🎉 TOUTES LES MIGRATIONS ONT ÉTÉ APPLIQUÉES AVEC SUCCÈS!\n\n";
    echo "Vérifications:\n";

    // Vérifier les rôles
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
        $role_column = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✓ Rôles users: " . $role_column['Type'] . "\n";

        $stmt = $pdo->query("SHOW COLUMNS FROM commissions LIKE 'chef_commission_role'");
        $chef_column = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✓ Rôles chef commission: " . $chef_column['Type'] . "\n";

        echo "\n✅ Les filtres de recherche devraient maintenant fonctionner!\n";
        echo "✅ La constitution de commission devrait fonctionner!\n";
    } catch (Exception $e) {
        echo "⚠️  Impossible de vérifier: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️  ATTENTION: Certaines migrations ont échoué.\n";
    echo "Vérifiez les logs ci-dessus pour plus de détails.\n";
}

echo "\n═══════════════════════════════════════════════════\n";
echo "Date d'exécution: " . date('Y-m-d H:i:s') . " UTC\n";
echo "═══════════════════════════════════════════════════\n";
?>

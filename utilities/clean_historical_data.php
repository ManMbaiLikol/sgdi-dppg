<?php
/**
 * Nettoyage des données historiques avant réimport
 */

require_once __DIR__ . '/config/database.php';

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║    NETTOYAGE DES DONNÉES HISTORIQUES                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    // Compter avant suppression
    $stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1");
    $count_before = $stmt->fetchColumn();

    echo "📊 Dossiers historiques actuels : $count_before\n\n";

    if ($count_before == 0) {
        echo "✅ Aucune donnée historique à supprimer.\n";
        exit(0);
    }

    echo "⚠️  Voulez-vous vraiment supprimer ces données ? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);

    if (trim($line) !== 'yes') {
        echo "\n❌ Opération annulée.\n";
        exit(0);
    }

    echo "\n🗑️  Suppression en cours...\n";

    // Supprimer les dossiers historiques
    $stmt = $pdo->prepare("DELETE FROM dossiers WHERE est_historique = 1");
    $stmt->execute();

    $deleted = $stmt->rowCount();

    echo "\n✅ Suppression terminée!\n";
    echo "   - Dossiers supprimés : $deleted\n";

    // Vérifier
    $stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1");
    $count_after = $stmt->fetchColumn();

    echo "   - Dossiers restants  : $count_after\n";

    if ($count_after == 0) {
        echo "\n✅ Base nettoyée avec succès!\n";
        echo "✅ Prêt pour le réimport avec le nouvel algorithme.\n";
    } else {
        echo "\n⚠️  Attention: Il reste encore des dossiers historiques.\n";
    }

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}

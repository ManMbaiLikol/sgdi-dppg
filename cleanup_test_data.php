<?php
/**
 * Script de nettoyage des données de test
 * Supprime les 10 dossiers issus du fichier TEST_PILOTE_10_DOSSIERS.csv
 */

// Activer l'affichage des erreurs pour debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/config/database.php';
} catch (Exception $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Nettoyage données de test</title>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #2c3e50; border-bottom: 3px solid #e74c3c; padding-bottom: 10px; }
    .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107; }
    .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: #e74c3c; color: white; }
    tr:nth-child(even) { background: #f9f9f9; }
    .btn { display: inline-block; padding: 12px 24px; background: #e74c3c; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; cursor: pointer; border: none; font-size: 16px; }
    .btn:hover { background: #c0392b; }
    .btn-secondary { background: #6c757d; }
    .btn-secondary:hover { background: #5a6268; }
</style></head><body>";

echo "<div class='container'>";
echo "<h1>🧹 Nettoyage des données de test</h1>";

// Étape 1: Rechercher les dossiers de test
echo "<h2>📊 Étape 1: Identification des dossiers de test</h2>";

try {
    // Rechercher les dossiers de test via source_import
    $sql = "SELECT id, numero, nom_demandeur, ville, region, type_infrastructure, source_import, date_creation, coordonnees_gps
            FROM dossiers
            WHERE source_import LIKE '%test%' OR source_import LIKE '%Import manuel%'
            ORDER BY id";

    $stmt = $pdo->query($sql);
    $test_dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Requête exécutée:</strong> " . count($test_dossiers) . " résultat(s) trouvé(s)</p>";
    echo "<p><small>Critères: source_import contient 'test' ou 'Import manuel'</small></p>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Erreur SQL</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    echo "<a href='dashboard.php' class='btn btn-secondary'>🏠 Retour Dashboard</a>";
    echo "</div></body></html>";
    exit;
}

if (count($test_dossiers) === 0) {
    echo "<div class='success'>";
    echo "<h3>✅ Aucun dossier de test trouvé</h3>";
    echo "<p>La base de données est propre. Aucune donnée de test à supprimer.</p>";
    echo "<p><small>Recherche effectuée sur la colonne 'source_import' avec les termes 'test' et 'Import manuel'</small></p>";
    echo "</div>";
    echo "<a href='dashboard.php' class='btn btn-secondary'>🏠 Retour Dashboard</a>";
    echo "</div></body></html>";
    exit;
}

echo "<div class='warning'>";
echo "<h3>⚠️ " . count($test_dossiers) . " dossier(s) de test trouvé(s)</h3>";
echo "<p>Ces dossiers ont été créés à partir du fichier <strong>TEST_PILOTE_10_DOSSIERS.csv</strong> et contiennent des données fictives.</p>";
echo "</div>";

echo "<table>";
echo "<tr><th>ID</th><th>Numéro</th><th>Demandeur</th><th>Type</th><th>Ville</th><th>Région</th><th>Date</th><th>Source Import</th><th>GPS</th></tr>";

foreach ($test_dossiers as $d) {
    echo "<tr>";
    echo "<td><strong>" . $d['id'] . "</strong></td>";
    echo "<td>" . htmlspecialchars($d['numero'] ?? 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($d['nom_demandeur'] ?? 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($d['type_infrastructure'] ?? 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($d['ville'] ?? 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($d['region'] ?? 'N/A') . "</td>";
    echo "<td>" . ($d['date_creation'] ? date('d/m/Y H:i', strtotime($d['date_creation'])) : 'N/A') . "</td>";
    echo "<td><small>" . htmlspecialchars($d['source_import'] ?? 'N/A') . "</small></td>";
    echo "<td><small>" . htmlspecialchars($d['coordonnees_gps'] ?? 'N/A') . "</small></td>";
    echo "</tr>";
}
echo "</table>";

// Vérifier les données liées (documents, historique, etc.)
echo "<h2>🔍 Étape 2: Analyse des données liées</h2>";

$ids_list = implode(',', array_column($test_dossiers, 'id'));

// Compter les tables liées
$related_tables = [
    'documents' => "SELECT COUNT(*) FROM documents WHERE dossier_id IN ($ids_list)",
    'historique_dossier' => "SELECT COUNT(*) FROM historique_dossier WHERE dossier_id IN ($ids_list)",
    'paiements' => "SELECT COUNT(*) FROM paiements WHERE dossier_id IN ($ids_list)",
    'inspections' => "SELECT COUNT(*) FROM inspections WHERE dossier_id IN ($ids_list)",
    'notifications' => "SELECT COUNT(*) FROM notifications WHERE dossier_id IN ($ids_list)",
    'visa_dossiers' => "SELECT COUNT(*) FROM visa_dossiers WHERE dossier_id IN ($ids_list)"
];

$total_related = 0;

echo "<table>";
echo "<tr><th>Table</th><th>Enregistrements liés</th></tr>";

foreach ($related_tables as $table => $query) {
    try {
        $count = $pdo->query($query)->fetchColumn();
        $total_related += $count;
        echo "<tr><td>$table</td><td><strong>$count</strong></td></tr>";
    } catch (Exception $e) {
        echo "<tr><td>$table</td><td><em>Table non trouvée ou erreur</em></td></tr>";
    }
}

echo "</table>";

echo "<div class='warning'>";
echo "<h3>📝 Résumé</h3>";
echo "<ul>";
echo "<li><strong>" . count($test_dossiers) . " dossier(s)</strong> de test à supprimer</li>";
echo "<li><strong>$total_related enregistrement(s) lié(s)</strong> dans d'autres tables</li>";
echo "<li><strong>Suppression en cascade</strong> - Toutes les données liées seront supprimées</li>";
echo "</ul>";
echo "</div>";

// Formulaire de confirmation
if (!isset($_POST['confirm_delete'])) {
    echo "<h2>⚠️ Confirmation requise</h2>";
    echo "<div class='warning'>";
    echo "<p><strong>ATTENTION:</strong> Cette action est <strong>irréversible</strong>!</p>";
    echo "<p>Êtes-vous sûr de vouloir supprimer ces " . count($test_dossiers) . " dossier(s) de test et toutes leurs données liées?</p>";
    echo "</div>";

    echo "<form method='POST' action='' onsubmit='return confirm(\"Êtes-vous VRAIMENT sûr? Cette action est IRRÉVERSIBLE!\");'>";
    echo "<button type='submit' name='confirm_delete' value='yes' class='btn'>🗑️ OUI, Supprimer les données de test</button>";
    echo "<a href='dashboard.php' class='btn btn-secondary'>❌ Annuler</a>";
    echo "</form>";

} else {
    // Suppression confirmée
    echo "<h2>🗑️ Étape 3: Suppression en cours...</h2>";

    try {
        $pdo->beginTransaction();

        $deleted_counts = [];

        // Supprimer les données liées dans l'ordre (tables enfants d'abord)
        $delete_queries = [
            'documents' => "DELETE FROM documents WHERE dossier_id IN ($ids_list)",
            'historique_dossier' => "DELETE FROM historique_dossier WHERE dossier_id IN ($ids_list)",
            'paiements' => "DELETE FROM paiements WHERE dossier_id IN ($ids_list)",
            'inspections' => "DELETE FROM inspections WHERE dossier_id IN ($ids_list)",
            'notifications' => "DELETE FROM notifications WHERE dossier_id IN ($ids_list)",
            'visa_dossiers' => "DELETE FROM visa_dossiers WHERE dossier_id IN ($ids_list)",
            'dossiers' => "DELETE FROM dossiers WHERE id IN ($ids_list)"
        ];

        echo "<table>";
        echo "<tr><th>Table</th><th>Enregistrements supprimés</th></tr>";

        foreach ($delete_queries as $table => $query) {
            try {
                $stmt = $pdo->prepare($query);
                $stmt->execute();
                $count = $stmt->rowCount();
                $deleted_counts[$table] = $count;
                echo "<tr><td>$table</td><td><strong>$count</strong></td></tr>";
            } catch (Exception $e) {
                echo "<tr><td>$table</td><td><em>Erreur ou table non trouvée</em></td></tr>";
            }
        }

        echo "</table>";

        $pdo->commit();

        echo "<div class='success'>";
        echo "<h3>✅ Nettoyage terminé avec succès!</h3>";
        echo "<p><strong>" . ($deleted_counts['dossiers'] ?? 0) . " dossier(s) de test</strong> ont été supprimés de la base de données.</p>";
        echo "<p>La base de données est maintenant prête pour l'import des vraies données OSM.</p>";
        echo "</div>";

        echo "<h3>📋 Prochaines étapes</h3>";
        echo "<ol>";
        echo "<li>Extraire les stations OSM réelles: <a href='modules/osm_extraction/'>Module OSM</a></li>";
        echo "<li>Filtrer par qualité (Excellent + Bon)</li>";
        echo "<li>Enrichir avec N° autorisation (Excel)</li>";
        echo "<li>Importer dans SGDI: <a href='modules/import_historique/'>Module Import</a></li>";
        echo "</ol>";

        echo "<a href='dashboard.php' class='btn btn-secondary'>🏠 Retour Dashboard</a>";
        echo "<a href='modules/osm_extraction/' class='btn'>🗺️ Aller à l'extraction OSM</a>";

    } catch (Exception $e) {
        $pdo->rollBack();

        echo "<div class='error'>";
        echo "<h3>❌ Erreur lors de la suppression</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";

        echo "<a href='cleanup_test_data.php' class='btn btn-secondary'>🔄 Réessayer</a>";
    }
}

echo "</div>"; // container
echo "</body></html>";

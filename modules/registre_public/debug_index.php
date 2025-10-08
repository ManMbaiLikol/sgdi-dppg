<?php
/**
 * Diagnostic pour index.php - À utiliser sur Railway
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug index.php</h1>";
echo "<pre>";

// Test 1 : Connexion
echo "=== Test 1: Database connection ===\n";
try {
    require_once '../../config/database.php';
    echo "✓ Database config loaded\n";
    $test = $pdo->query("SELECT 1")->fetchColumn();
    echo "✓ Database connection OK\n\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
    die();
}

// Test 2 : Functions
echo "=== Test 2: Functions file ===\n";
try {
    require_once '../../includes/functions.php';
    echo "✓ Functions file loaded\n";

    // Vérifier si formatTypeInfrastructure existe
    if (function_exists('formatTypeInfrastructure')) {
        echo "✓ formatTypeInfrastructure() exists\n";
    } else {
        echo "✗ formatTypeInfrastructure() NOT FOUND\n";
    }

    // Vérifier si getTypeInfrastructureLabel existe
    if (function_exists('getTypeInfrastructureLabel')) {
        echo "✓ getTypeInfrastructureLabel() exists\n";
    } else {
        echo "✗ getTypeInfrastructureLabel() NOT FOUND\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n\n";
}

// Test 3 : Vérifier table decisions
echo "=== Test 3: Check 'decisions' table ===\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'decisions'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Table 'decisions' exists\n";

        // Vérifier la structure
        $cols = $pdo->query("SHOW COLUMNS FROM decisions")->fetchAll(PDO::FETCH_COLUMN);
        echo "Columns: " . implode(", ", $cols) . "\n";
    } else {
        echo "✗ Table 'decisions' DOES NOT EXIST\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "✗ Error checking decisions table: " . $e->getMessage() . "\n\n";
}

// Test 4 : Requête SQL principale
echo "=== Test 4: Main SQL query ===\n";
try {
    $sql = "SELECT d.*, dec.decision, dec.date_decision, dec.reference_decision,
            d.numero, d.type_infrastructure, d.sous_type, d.region, d.ville,
            d.nom_demandeur, d.operateur_proprietaire, d.entreprise_beneficiaire,
            DATE_FORMAT(dec.date_decision, '%d/%m/%Y') as date_decision_format
            FROM dossiers d
            LEFT JOIN decisions dec ON d.id = dec.dossier_id
            WHERE d.statut IN ('autorise', 'refuse', 'ferme')
            LIMIT 5";

    $stmt = $pdo->query($sql);
    $dossiers = $stmt->fetchAll();
    echo "✓ Query executed successfully\n";
    echo "Rows returned: " . count($dossiers) . "\n\n";
} catch (Exception $e) {
    echo "✗ SQL Error: " . $e->getMessage() . "\n\n";
}

// Test 5 : Requête statistiques
echo "=== Test 5: Stats query ===\n";
try {
    $stats_sql = "SELECT
        COUNT(DISTINCT d.id) as total_autorise,
        COUNT(DISTINCT CASE WHEN d.type_infrastructure = 'station_service' THEN d.id END) as stations,
        COUNT(DISTINCT CASE WHEN d.type_infrastructure = 'point_consommateur' THEN d.id END) as points,
        COUNT(DISTINCT CASE WHEN d.type_infrastructure = 'depot_gpl' THEN d.id END) as depots,
        COUNT(DISTINCT CASE WHEN d.type_infrastructure = 'centre_emplisseur' THEN d.id END) as centres
        FROM dossiers d WHERE d.statut = 'autorise'";
    $stats = $pdo->query($stats_sql)->fetch();
    echo "✓ Stats query OK\n";
    echo "Total autorisé: " . $stats['total_autorise'] . "\n\n";
} catch (Exception $e) {
    echo "✗ Stats Error: " . $e->getMessage() . "\n\n";
}

// Test 6 : Test formatTypeInfrastructure
echo "=== Test 6: Test formatTypeInfrastructure() ===\n";
try {
    if (function_exists('formatTypeInfrastructure')) {
        $test = formatTypeInfrastructure('station_service');
        echo "✓ formatTypeInfrastructure('station_service') = " . $test . "\n";
    } else {
        echo "✗ Function does not exist\n";
        echo "Available functions starting with 'format':\n";
        $functions = get_defined_functions()['user'];
        foreach ($functions as $func) {
            if (strpos($func, 'format') !== false || strpos($func, 'Type') !== false) {
                echo "  - $func\n";
            }
        }
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='index.php'>← Essayer index.php</a></p>";
?>

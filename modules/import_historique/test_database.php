<?php
// Test de la base de données - Module import historique
require_once '../../includes/auth.php';
require_once '../../config/database.php';

requireLogin();

echo "<h1>Test de la base de données - Module Import Historique</h1>";
echo "<hr>";

// Test 1 : Vérifier les colonnes de la table dossiers
echo "<h3>1. Vérification des colonnes ajoutées dans 'dossiers' :</h3>";

$sql = "SHOW COLUMNS FROM dossiers LIKE 'est_historique'";
$result = $pdo->query($sql);
$col1 = $result->fetch();

$sql = "SHOW COLUMNS FROM dossiers LIKE 'importe_le'";
$result = $pdo->query($sql);
$col2 = $result->fetch();

$sql = "SHOW COLUMNS FROM dossiers LIKE 'importe_par'";
$result = $pdo->query($sql);
$col3 = $result->fetch();

$sql = "SHOW COLUMNS FROM dossiers LIKE 'numero_decision_ministerielle'";
$result = $pdo->query($sql);
$col4 = $result->fetch();

echo "<ul>";
echo "<li>est_historique : " . ($col1 ? "✅ Existe" : "❌ <strong>MANQUANTE</strong>") . "</li>";
echo "<li>importe_le : " . ($col2 ? "✅ Existe" : "❌ <strong>MANQUANTE</strong>") . "</li>";
echo "<li>importe_par : " . ($col3 ? "✅ Existe" : "❌ <strong>MANQUANTE</strong>") . "</li>";
echo "<li>numero_decision_ministerielle : " . ($col4 ? "✅ Existe" : "❌ <strong>MANQUANTE</strong>") . "</li>";
echo "</ul>";

// Test 2 : Vérifier la table entreprises_beneficiaires
echo "<h3>2. Vérification de la table 'entreprises_beneficiaires' :</h3>";

try {
    $sql = "SHOW TABLES LIKE 'entreprises_beneficiaires'";
    $result = $pdo->query($sql);
    $table = $result->fetch();
    echo $table ? "✅ La table existe" : "❌ <strong>LA TABLE N'EXISTE PAS</strong>";
} catch (Exception $e) {
    echo "❌ <strong>ERREUR : " . $e->getMessage() . "</strong>";
}
echo "<br><br>";

// Test 3 : Vérifier le statut HISTORIQUE_AUTORISE
echo "<h3>3. Vérification du statut 'HISTORIQUE_AUTORISE' :</h3>";

try {
    // Vérifier si on utilise ENUM ou table statuts_dossier
    $checkTable = $pdo->query("SHOW TABLES LIKE 'statuts_dossier'");
    $useEnum = ($checkTable->rowCount() == 0);

    if ($useEnum) {
        // Structure avec ENUM
        echo "ℹ️ Structure : ENUM (ancienne version)<br>";
        $sql = "SHOW COLUMNS FROM dossiers LIKE 'statut'";
        $result = $pdo->query($sql);
        $column = $result->fetch();

        if ($column && strpos($column['Type'], 'historique_autorise') !== false) {
            echo "✅ Le statut 'historique_autorise' existe dans l'ENUM<br>";
        } else {
            echo "❌ <strong>LE STATUT 'historique_autorise' N'EXISTE PAS dans l'ENUM</strong>";
        }
    } else {
        // Structure avec table statuts_dossier
        echo "ℹ️ Structure : Table statuts_dossier (nouvelle version)<br>";
        $sql = "SELECT * FROM statuts_dossier WHERE code = 'HISTORIQUE_AUTORISE'";
        $result = $pdo->query($sql);
        $statut = $result->fetch();

        if ($statut) {
            echo "✅ Le statut existe<br>";
            echo "<ul>";
            echo "<li>ID : " . $statut['id'] . "</li>";
            echo "<li>Code : " . $statut['code'] . "</li>";
            echo "<li>Libellé : " . $statut['libelle'] . "</li>";
            echo "</ul>";
        } else {
            echo "❌ <strong>LE STATUT N'EXISTE PAS</strong>";
        }
    }
} catch (Exception $e) {
    echo "❌ <strong>ERREUR : " . $e->getMessage() . "</strong>";
}
echo "<br>";

// Test 4 : Vérifier la table logs_import_historique
echo "<h3>4. Vérification de la table 'logs_import_historique' :</h3>";

try {
    $sql = "SHOW TABLES LIKE 'logs_import_historique'";
    $result = $pdo->query($sql);
    $table = $result->fetch();
    echo $table ? "✅ La table existe" : "❌ <strong>LA TABLE N'EXISTE PAS</strong>";
} catch (Exception $e) {
    echo "❌ <strong>ERREUR : " . $e->getMessage() . "</strong>";
}
echo "<br><br>";

// Test 5 : Vérifier la vue v_dossiers_historiques
echo "<h3>5. Vérification de la vue 'v_dossiers_historiques' :</h3>";

try {
    $sql = "SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_" . getenv('DB_NAME') . " = 'v_dossiers_historiques'";
    $result = $pdo->query($sql);
    $view = $result->fetch();
    echo $view ? "✅ La vue existe" : "❌ <strong>LA VUE N'EXISTE PAS</strong>";
} catch (Exception $e) {
    // Essayer une autre méthode
    try {
        $sql = "SELECT 1 FROM v_dossiers_historiques LIMIT 1";
        $pdo->query($sql);
        echo "✅ La vue existe";
    } catch (Exception $e2) {
        echo "❌ <strong>LA VUE N'EXISTE PAS</strong>";
    }
}
echo "<br><br>";

// Résumé
echo "<hr>";
echo "<h3>📋 RÉSUMÉ :</h3>";

$all_ok = $col1 && $col2 && $col3 && $col4;

if ($all_ok) {
    echo "<div style='padding: 20px; background: #d4edda; border: 2px solid green;'>";
    echo "<h4 style='color: green;'>✅ MIGRATION EXÉCUTÉE AVEC SUCCÈS</h4>";
    echo "<p>Toutes les modifications de la base de données sont en place.</p>";
    echo "<p>Le module d'import devrait fonctionner correctement.</p>";
    echo "<p><a href='index.php' style='display: inline-block; padding: 10px 20px; background: green; color: white; text-decoration: none; border-radius: 5px;'>Accéder au module d'import</a></p>";
    echo "</div>";
} else {
    echo "<div style='padding: 20px; background: #f8d7da; border: 2px solid red;'>";
    echo "<h4 style='color: red;'>❌ MIGRATION NON EXÉCUTÉE</h4>";
    echo "<p><strong>La migration SQL doit être exécutée avant d'utiliser le module.</strong></p>";
    echo "<p>Fichier à exécuter : <code>database/migrations/add_import_historique.sql</code></p>";
    echo "<br>";
    echo "<h5>Instructions :</h5>";
    echo "<ol>";
    echo "<li>Accéder au dashboard Railway</li>";
    echo "<li>Se connecter à la base de données MySQL</li>";
    echo "<li>Exécuter le contenu du fichier SQL</li>";
    echo "<li>Rafraîchir cette page pour vérifier</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='test_permissions.php'>← Test des permissions</a> | <a href='../../dashboard.php'>Retour au tableau de bord</a></p>";
?>

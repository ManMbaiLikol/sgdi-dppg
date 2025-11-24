<?php
/**
 * Exécution de la migration : fix_dossiers_edit_columns.sql
 * Ajoute les colonnes manquantes pour l'édition de dossiers
 */

// Déterminer le chemin absolu vers config/database.php
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/database.php';

echo "<h2>Migration : Correction des colonnes pour édition de dossiers</h2>";
echo "<pre>";

try {
    // Lire le fichier SQL
    $sql_file = __DIR__ . '/fix_dossiers_edit_columns.sql';

    if (!file_exists($sql_file)) {
        throw new Exception("Fichier SQL introuvable : $sql_file");
    }

    $sql_content = file_get_contents($sql_file);

    if ($sql_content === false) {
        throw new Exception("Impossible de lire le fichier SQL");
    }

    echo "✓ Fichier SQL chargé : fix_dossiers_edit_columns.sql\n\n";

    // Exécuter le SQL en plusieurs étapes (à cause des PREPARE/EXECUTE)
    $statements = explode(';', $sql_content);
    $executed = 0;
    $errors = 0;

    foreach ($statements as $statement) {
        $statement = trim($statement);

        // Ignorer les commentaires et les lignes vides
        if (empty($statement) ||
            substr($statement, 0, 2) === '--' ||
            substr($statement, 0, 2) === '/*' ||
            substr($statement, 0, 2) === '==') {
            continue;
        }

        try {
            $pdo->exec($statement);
            $executed++;

            // Afficher un point de progression tous les 5 statements
            if ($executed % 5 === 0) {
                echo ".";
            }
        } catch (PDOException $e) {
            // Ignorer certaines erreurs connues (colonnes déjà existantes)
            if (strpos($e->getMessage(), 'Duplicate column name') !== false ||
                strpos($e->getMessage(), 'already exists') !== false) {
                // Colonne déjà existante, pas grave
                echo "i"; // indicateur "ignoré"
            } else {
                $errors++;
                echo "\n⚠ Erreur (ignorée) : " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n\n";
    echo "=" . str_repeat("=", 70) . "\n";
    echo "✅ Migration terminée !\n";
    echo "   - Statements exécutés : $executed\n";
    echo "   - Erreurs rencontrées : $errors\n";
    echo "=" . str_repeat("=", 70) . "\n\n";

    // Vérifier les colonnes ajoutées
    echo "Vérification des colonnes dans la table 'dossiers' :\n\n";

    $cols_to_check = [
        'departement', 'arrondissement', 'quartier', 'zone_type',
        'lieu_dit', 'adresse_precise', 'annee_mise_en_service',
        'operateur_gaz', 'entreprise_constructrice', 'capacite_enfutage'
    ];

    foreach ($cols_to_check as $col) {
        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'dossiers'
            AND COLUMN_NAME = '$col'
        ");
        $exists = $stmt->fetchColumn();

        echo sprintf("  %-30s %s\n", $col, $exists ? "✓ Présent" : "✗ MANQUANT");
    }

    echo "\n";

    // Vérifier les ENUM
    echo "Vérification des ENUM :\n\n";

    $stmt = $pdo->query("
        SELECT COLUMN_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'dossiers'
        AND COLUMN_NAME = 'type_infrastructure'
    ");
    $type_enum = $stmt->fetchColumn();
    echo "  type_infrastructure : $type_enum\n";
    echo "  → centre_emplisseur : " . (strpos($type_enum, 'centre_emplisseur') !== false ? "✓ Présent" : "✗ MANQUANT") . "\n\n";

    $stmt = $pdo->query("
        SELECT COLUMN_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'dossiers'
        AND COLUMN_NAME = 'sous_type'
    ");
    $sous_type_enum = $stmt->fetchColumn();
    echo "  sous_type : $sous_type_enum\n";
    echo "  → remodelage : " . (strpos($sous_type_enum, 'remodelage') !== false ? "✓ Présent" : "✗ MANQUANT") . "\n\n";

    echo "=" . str_repeat("=", 70) . "\n";
    echo "✅ La modification des dossiers historiques devrait maintenant fonctionner !\n";
    echo "=" . str_repeat("=", 70) . "\n";

} catch (Exception $e) {
    echo "\n❌ ERREUR : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>

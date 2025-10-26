<?php
// Script d'exécution de la migration add_lettre_desistement
require_once __DIR__ . '/../../config/database.php';

echo "=== Migration: Ajout lettre_desistement ===\n\n";

try {
    // Vérifier si la colonne existe déjà
    $check = $pdo->query("SHOW COLUMNS FROM fiches_inspection LIKE 'lettre_desistement'");
    if ($check->rowCount() > 0) {
        echo "❌ La colonne 'lettre_desistement' existe déjà.\n";
        exit(0);
    }

    // Lire le fichier SQL
    $sql = file_get_contents(__DIR__ . '/add_lettre_desistement.sql');

    // Supprimer les commentaires
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = trim($sql);

    echo "Exécution de la migration...\n";
    echo "SQL: " . substr($sql, 0, 100) . "...\n\n";

    $pdo->exec($sql);

    echo "✅ Migration exécutée avec succès!\n\n";

    // Vérification
    echo "Vérification de la colonne:\n";
    $result = $pdo->query("SHOW COLUMNS FROM fiches_inspection LIKE 'lettre_desistement'");
    $column = $result->fetch(PDO::FETCH_ASSOC);

    if ($column) {
        echo "✅ Colonne créée: " . $column['Field'] . "\n";
        echo "   Type: " . $column['Type'] . "\n";
        echo "   Null: " . $column['Null'] . "\n";
        echo "   Default: " . $column['Default'] . "\n";
    }

} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Migration terminée ===\n";

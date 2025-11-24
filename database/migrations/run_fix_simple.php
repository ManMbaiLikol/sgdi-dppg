<?php
/**
 * Exécution simplifiée de la migration
 * Ajoute les colonnes manquantes une par une
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/database.php';

echo "<h2>Migration : Correction des colonnes pour édition de dossiers</h2>";
echo "<pre>";

// Configuration PDO pour utiliser le mode buffered
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

$migrations = [
    // 1. Ajout de centre_emplisseur au type_infrastructure
    [
        'check' => "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'type_infrastructure'",
        'action' => "ALTER TABLE dossiers MODIFY COLUMN type_infrastructure ENUM('station_service','point_consommateur','depot_gpl','centre_emplisseur') NOT NULL",
        'condition' => function($result) {
            return strpos($result, 'centre_emplisseur') === false;
        },
        'name' => 'Type centre_emplisseur'
    ],

    // 2. Ajout de remodelage au sous_type
    [
        'check' => "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'sous_type'",
        'action' => "ALTER TABLE dossiers MODIFY COLUMN sous_type ENUM('implantation','reprise','remodelage') NOT NULL",
        'condition' => function($result) {
            return strpos($result, 'remodelage') === false;
        },
        'name' => 'Sous-type remodelage'
    ],

    // 3-10. Ajout des colonnes manquantes
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'departement'",
        'action' => "ALTER TABLE dossiers ADD COLUMN departement VARCHAR(100) NULL AFTER region",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne departement'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'arrondissement'",
        'action' => "ALTER TABLE dossiers ADD COLUMN arrondissement VARCHAR(100) NULL AFTER ville",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne arrondissement'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'quartier'",
        'action' => "ALTER TABLE dossiers ADD COLUMN quartier VARCHAR(100) NULL AFTER arrondissement",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne quartier'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'zone_type'",
        'action' => "ALTER TABLE dossiers ADD COLUMN zone_type ENUM('urbaine','rurale') DEFAULT 'urbaine' AFTER quartier",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne zone_type'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'lieu_dit'",
        'action' => "ALTER TABLE dossiers ADD COLUMN lieu_dit VARCHAR(200) NULL AFTER zone_type",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne lieu_dit'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'adresse_precise'",
        'action' => "ALTER TABLE dossiers ADD COLUMN adresse_precise TEXT NULL AFTER email_demandeur",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne adresse_precise'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'annee_mise_en_service'",
        'action' => "ALTER TABLE dossiers ADD COLUMN annee_mise_en_service YEAR NULL AFTER coordonnees_gps",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne annee_mise_en_service'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'operateur_gaz'",
        'action' => "ALTER TABLE dossiers ADD COLUMN operateur_gaz VARCHAR(200) NULL COMMENT 'Pour centre emplisseur'",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne operateur_gaz'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'entreprise_constructrice'",
        'action' => "ALTER TABLE dossiers ADD COLUMN entreprise_constructrice VARCHAR(200) NULL COMMENT 'Pour centre emplisseur'",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne entreprise_constructrice'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'capacite_enfutage'",
        'action' => "ALTER TABLE dossiers ADD COLUMN capacite_enfutage VARCHAR(100) NULL COMMENT 'Capacité d\'enfûtage (bouteilles/jour)'",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne capacite_enfutage'
    ]
];

$success = 0;
$skipped = 0;
$errors = 0;

foreach ($migrations as $migration) {
    try {
        // Vérifier si la migration est nécessaire
        $stmt = $pdo->query($migration['check']);
        $result = $stmt->fetchColumn();

        if ($migration['condition']($result)) {
            // Exécuter la migration
            $pdo->exec($migration['action']);
            echo "✓ {$migration['name']} : Ajouté avec succès\n";
            $success++;
        } else {
            echo "○ {$migration['name']} : Déjà présent\n";
            $skipped++;
        }
    } catch (PDOException $e) {
        echo "✗ {$migration['name']} : ERREUR - {$e->getMessage()}\n";
        $errors++;
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "Migration terminée !\n";
echo "  ✓ Ajoutés : $success\n";
echo "  ○ Déjà présents : $skipped\n";
echo "  ✗ Erreurs : $errors\n";
echo str_repeat("=", 70) . "\n\n";

// Vérification finale
echo "Vérification finale des colonnes :\n\n";

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
echo "  type_infrastructure :\n";
echo "    → $type_enum\n";
echo "    → centre_emplisseur : " . (strpos($type_enum, 'centre_emplisseur') !== false ? "✓ Présent" : "✗ MANQUANT") . "\n\n";

$stmt = $pdo->query("
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'sous_type'
");
$sous_type_enum = $stmt->fetchColumn();
echo "  sous_type :\n";
echo "    → $sous_type_enum\n";
echo "    → remodelage : " . (strpos($sous_type_enum, 'remodelage') !== false ? "✓ Présent" : "✗ MANQUANT") . "\n\n";

echo str_repeat("=", 70) . "\n";

if ($errors == 0) {
    echo "✅ Migration réussie ! La modification des dossiers historiques devrait maintenant fonctionner.\n";
} else {
    echo "⚠ Migration terminée avec $errors erreur(s). Vérifiez les messages ci-dessus.\n";
}

echo str_repeat("=", 70) . "\n";

echo "</pre>";
?>

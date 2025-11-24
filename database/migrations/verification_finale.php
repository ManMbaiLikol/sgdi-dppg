<?php
/**
 * Vérification finale : s'assurer que tout est en ordre pour la modification des dossiers
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/database.php';

echo "<h2>Vérification finale de la configuration</h2>";
echo "<pre>";

$allGood = true;

// 1. Vérifier la structure de la table dossiers
echo "1. Vérification de la structure de la table 'dossiers'...\n";
echo str_repeat("-", 70) . "\n";

$required_columns = [
    'type_infrastructure' => 'enum',
    'sous_type' => 'enum',
    'nom_demandeur' => 'varchar',
    'contact_demandeur' => 'varchar',
    'telephone_demandeur' => 'varchar',
    'email_demandeur' => 'varchar',
    'adresse_precise' => 'text',
    'region' => 'varchar',
    'departement' => 'varchar',
    'ville' => 'varchar',
    'arrondissement' => 'varchar',
    'quartier' => 'varchar',
    'zone_type' => 'enum',
    'lieu_dit' => 'varchar',
    'coordonnees_gps' => 'varchar',
    'annee_mise_en_service' => 'year',
    'operateur_proprietaire' => 'varchar',
    'entreprise_beneficiaire' => 'varchar',
    'entreprise_installatrice' => 'varchar',
    'contrat_livraison' => 'varchar',
    'operateur_gaz' => 'varchar',
    'entreprise_constructrice' => 'varchar',
    'capacite_enfutage' => 'varchar',
    'date_modification' => 'timestamp'
];

foreach ($required_columns as $col => $type) {
    $stmt = $pdo->query("
        SELECT DATA_TYPE, IS_NULLABLE, COLUMN_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'dossiers'
        AND COLUMN_NAME = '$col'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo sprintf("  ✓ %-30s %s\n", $col, $result['COLUMN_TYPE']);
    } else {
        echo sprintf("  ✗ %-30s MANQUANT !\n", $col);
        $allGood = false;
    }
}

echo "\n";

// 2. Vérifier les valeurs ENUM
echo "2. Vérification des valeurs ENUM...\n";
echo str_repeat("-", 70) . "\n";

$stmt = $pdo->query("
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'type_infrastructure'
");
$type_enum = $stmt->fetchColumn();

$required_types = ['station_service', 'point_consommateur', 'depot_gpl', 'centre_emplisseur'];
foreach ($required_types as $type) {
    $found = strpos($type_enum, $type) !== false;
    echo sprintf("  %s type_infrastructure: %s\n", $found ? '✓' : '✗', $type);
    if (!$found) $allGood = false;
}

echo "\n";

$stmt = $pdo->query("
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'dossiers'
    AND COLUMN_NAME = 'sous_type'
");
$sous_type_enum = $stmt->fetchColumn();

$required_sous_types = ['implantation', 'reprise', 'remodelage'];
foreach ($required_sous_types as $sous_type) {
    $found = strpos($sous_type_enum, $sous_type) !== false;
    echo sprintf("  %s sous_type: %s\n", $found ? '✓' : '✗', $sous_type);
    if (!$found) $allGood = false;
}

echo "\n";

// 3. Compter les dossiers historiques
echo "3. Statistiques des dossiers...\n";
echo str_repeat("-", 70) . "\n";

$stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1");
$nb_historiques = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 0");
$nb_sgdi = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM dossiers");
$nb_total = $stmt->fetchColumn();

echo "  Total de dossiers : $nb_total\n";
echo "  - Dossiers SGDI : $nb_sgdi\n";
echo "  - Dossiers historiques : $nb_historiques\n";

echo "\n";

// 4. Tester la fonction modifierDossier
echo "4. Test de la fonction modifierDossier()...\n";
echo str_repeat("-", 70) . "\n";

require_once $root_path . '/modules/dossiers/functions.php';

// Récupérer un dossier historique
$stmt = $pdo->query("SELECT * FROM dossiers WHERE est_historique = 1 LIMIT 1");
$dossier = $stmt->fetch(PDO::FETCH_ASSOC);

if ($dossier) {
    echo "  Test avec dossier #{$dossier['id']} - {$dossier['numero']}\n";

    // Préparer les données (sans modification)
    $data = [
        'type_infrastructure' => $dossier['type_infrastructure'],
        'sous_type' => $dossier['sous_type'],
        'nom_demandeur' => $dossier['nom_demandeur'],
        'contact_demandeur' => $dossier['contact_demandeur'] ?? '',
        'telephone_demandeur' => $dossier['telephone_demandeur'] ?? '',
        'email_demandeur' => $dossier['email_demandeur'] ?? '',
        'adresse_precise' => $dossier['adresse_precise'] ?? '',
        'region' => $dossier['region'],
        'departement' => $dossier['departement'] ?? '',
        'ville' => $dossier['ville'],
        'arrondissement' => $dossier['arrondissement'] ?? '',
        'quartier' => $dossier['quartier'] ?? '',
        'zone_type' => $dossier['zone_type'] ?? 'urbaine',
        'lieu_dit' => $dossier['lieu_dit'] ?? '',
        'coordonnees_gps' => $dossier['coordonnees_gps'] ?? '',
        'annee_mise_en_service' => $dossier['annee_mise_en_service'] ?? null,
        'operateur_proprietaire' => $dossier['operateur_proprietaire'] ?? '',
        'entreprise_beneficiaire' => $dossier['entreprise_beneficiaire'] ?? '',
        'entreprise_installatrice' => $dossier['entreprise_installatrice'] ?? '',
        'contrat_livraison' => $dossier['contrat_livraison'] ?? '',
        'operateur_gaz' => $dossier['operateur_gaz'] ?? '',
        'entreprise_constructrice' => $dossier['entreprise_constructrice'] ?? '',
        'capacite_enfutage' => $dossier['capacite_enfutage'] ?? ''
    ];

    if (modifierDossier($dossier['id'], $data)) {
        echo "  ✓ Modification réussie\n";
    } else {
        global $derniere_erreur_sql;
        echo "  ✗ Modification échouée\n";
        if (!empty($derniere_erreur_sql)) {
            echo "    Erreur: $derniere_erreur_sql\n";
        }
        $allGood = false;
    }
} else {
    echo "  ⚠ Aucun dossier historique trouvé pour le test\n";
}

echo "\n";

// 5. Résumé final
echo str_repeat("=", 70) . "\n";
if ($allGood) {
    echo "✅ TOUT EST EN ORDRE !\n";
    echo "La modification des dossiers historiques devrait fonctionner parfaitement.\n";
} else {
    echo "⚠ ATTENTION : Certaines vérifications ont échoué.\n";
    echo "Consultez les messages ci-dessus pour plus de détails.\n";
}
echo str_repeat("=", 70) . "\n";

echo "</pre>";
?>

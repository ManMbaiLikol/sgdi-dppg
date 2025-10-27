<?php
// Script de test de sauvegarde pour station-service
require_once __DIR__ . '/../../config/database.php';

echo "=== Test de sauvegarde station-service ===\n\n";

// Trouver un dossier station-service
$dossier_id = 16; // Ajustez si nécessaire

try {
    // Vérifier la fiche
    echo "Vérification de la fiche du dossier $dossier_id:\n";
    $stmt = $pdo->prepare("SELECT * FROM vue_fiches_inspection_completes WHERE dossier_id = ?");
    $stmt->execute([$dossier_id]);
    $fiche = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fiche) {
        echo "❌ Aucune fiche trouvée pour ce dossier\n";
        exit(1);
    }

    echo "✅ Fiche trouvée (ID: {$fiche['id']})\n";
    echo "   Type: {$fiche['type_infra_dossier']}\n\n";

    // Afficher les champs station-service
    echo "Champs station-service actuels:\n";
    $champs_ss = [
        'date_mise_service',
        'autorisation_minee',
        'autorisation_minmidt',
        'type_gestion',
        'type_gestion_autre',
        'plan_ensemble',
        'contrat_bail',
        'permis_batir',
        'certificat_urbanisme',
        'lettre_minepded',
        'plan_masse',
        'lettre_desistement',
        'chef_piste',
        'gerant',
        'observations_generales',
        'recommandations'
    ];

    foreach ($champs_ss as $champ) {
        $valeur = $fiche[$champ] ?? 'NULL';
        if (is_bool($valeur) || $valeur === '0' || $valeur === '1') {
            $valeur = $valeur ? '✓ OUI' : '✗ NON';
        }
        echo sprintf("  %-30s: %s\n", $champ, $valeur);
    }

    echo "\n=== Test UPDATE ===\n\n";

    // Tester un UPDATE simple
    echo "Test UPDATE direct de quelques champs...\n";
    $test_update = "UPDATE fiches_inspection SET
        autorisation_minee = 'TEST-12345',
        lettre_desistement = 1,
        chef_piste = 'Jean Dupont TEST',
        observations_generales = 'Observation de test',
        recommandations = 'Recommandation de test'
        WHERE id = ?";

    $stmt = $pdo->prepare($test_update);
    $result = $stmt->execute([$fiche['id']]);

    if ($result) {
        echo "✅ UPDATE réussi\n\n";

        // Vérifier les valeurs mises à jour
        echo "Vérification des valeurs après UPDATE:\n";
        $stmt = $pdo->prepare("SELECT autorisation_minee, lettre_desistement, chef_piste, observations_generales, recommandations FROM fiches_inspection WHERE id = ?");
        $stmt->execute([$fiche['id']]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);

        foreach ($updated as $key => $val) {
            echo sprintf("  %-30s: %s\n", $key, $val ?? 'NULL');
        }

        // Vérifier via la vue
        echo "\nVérification via la vue:\n";
        $stmt = $pdo->prepare("SELECT autorisation_minee, lettre_desistement, chef_piste, observations_generales, recommandations FROM vue_fiches_inspection_completes WHERE id = ?");
        $stmt->execute([$fiche['id']]);
        $from_view = $stmt->fetch(PDO::FETCH_ASSOC);

        foreach ($from_view as $key => $val) {
            echo sprintf("  %-30s: %s\n", $key, $val ?? 'NULL');
        }

    } else {
        echo "❌ UPDATE échoué\n";
        print_r($stmt->errorInfo());
    }

} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Test terminé ===\n";

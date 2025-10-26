<?php
// Script de recréation de la vue vue_fiches_inspection_completes
require_once __DIR__ . '/../../config/database.php';

echo "=== Recréation de la vue vue_fiches_inspection_completes ===\n\n";

try {
    // Vérifier les colonnes de la table avant
    echo "Vérification des colonnes de la table fiches_inspection:\n";
    $result = $pdo->query("SELECT COUNT(*) as nb_columns FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiches_inspection'");
    $count = $result->fetch(PDO::FETCH_ASSOC);
    echo "✅ Nombre de colonnes dans la table: " . $count['nb_columns'] . "\n\n";

    // Vérifier si lettre_desistement existe
    $check = $pdo->query("SHOW COLUMNS FROM fiches_inspection LIKE 'lettre_desistement'");
    if ($check->rowCount() > 0) {
        echo "✅ Colonne lettre_desistement trouvée\n\n";
    } else {
        echo "❌ Colonne lettre_desistement non trouvée!\n\n";
    }

    // Supprimer l'ancienne vue
    echo "Suppression de l'ancienne vue...\n";
    $pdo->exec("DROP VIEW IF EXISTS vue_fiches_inspection_completes");
    echo "✅ Vue supprimée\n\n";

    // Créer la nouvelle vue avec TOUS les champs
    echo "Création de la nouvelle vue avec tous les champs...\n";
    $sql = "CREATE VIEW vue_fiches_inspection_completes AS
    SELECT
        f.*,
        d.numero as numero_dossier,
        d.nom_demandeur,
        d.type_infrastructure as type_infra_dossier,
        u.nom as inspecteur_nom,
        u.prenom as inspecteur_prenom,
        (SELECT COUNT(*) FROM fiche_inspection_cuves WHERE fiche_id = f.id) as nb_cuves,
        (SELECT COUNT(*) FROM fiche_inspection_pompes WHERE fiche_id = f.id) as nb_pompes
    FROM fiches_inspection f
    JOIN dossiers d ON f.dossier_id = d.id
    LEFT JOIN users u ON f.inspecteur_id = u.id";

    $pdo->exec($sql);
    echo "✅ Vue créée avec succès!\n\n";

    // Vérifier les colonnes de la vue après
    echo "Vérification des colonnes de la vue:\n";
    $result = $pdo->query("SELECT COUNT(*) as nb_columns FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vue_fiches_inspection_completes'");
    $count = $result->fetch(PDO::FETCH_ASSOC);
    echo "✅ Nombre de colonnes dans la vue: " . $count['nb_columns'] . "\n\n";

    // Lister quelques colonnes importantes
    echo "Vérification des colonnes clés:\n";
    $columns_to_check = [
        'lettre_desistement',
        'date_mise_service',
        'autorisation_minee',
        'plan_ensemble',
        'contrat_bail',
        'permis_batir',
        'certificat_urbanisme',
        'lettre_minepded',
        'plan_masse',
        'chef_piste',
        'gerant',
        'numero_contrat_approvisionnement',
        'societe_contractante',
        'besoins_mensuels_litres',
        'nombre_personnels',
        'superficie_site',
        'parc_engin',
        'batiments_site',
        'recommandations'
    ];

    foreach ($columns_to_check as $col) {
        $check = $pdo->query("SELECT COUNT(*) as found FROM INFORMATION_SCHEMA.COLUMNS
                              WHERE TABLE_SCHEMA = DATABASE()
                              AND TABLE_NAME = 'vue_fiches_inspection_completes'
                              AND COLUMN_NAME = '$col'");
        $found = $check->fetch(PDO::FETCH_ASSOC);
        $status = $found['found'] ? '✅' : '❌';
        echo "$status $col\n";
    }

} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Recréation terminée ===\n";

<?php
/**
 * Script pour recréer la vue vue_fiches_inspection_completes sur Railway
 * À exécuter UNE SEULE FOIS puis supprimer
 */

require_once __DIR__ . '/../config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Recréation Vue SQL</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;} ";
echo ".success{background:#d4edda;border:2px solid #28a745;padding:15px;margin:10px 0;border-radius:5px;} ";
echo ".error{background:#f8d7da;border:2px solid #dc3545;padding:15px;margin:10px 0;border-radius:5px;} ";
echo ".info{background:#d1ecf1;border:2px solid #0c5460;padding:15px;margin:10px 0;border-radius:5px;}</style>";
echo "</head><body>";
echo "<h1>🔄 Recréation de la vue vue_fiches_inspection_completes</h1>";

try {
    // 1. Vérifier les colonnes actuelles de la table fiches_inspection
    echo "<div class='info'>";
    echo "<h3>📋 Étape 1: Vérification des colonnes de la table</h3>";

    $stmt = $pdo->query("SHOW COLUMNS FROM fiches_inspection");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>La table <code>fiches_inspection</code> contient " . count($columns) . " colonnes.</p>";

    // Vérifier que les nouveaux champs existent
    $nouveaux_champs = [
        'numero_contrat_approvisionnement',
        'societe_contractante',
        'besoins_mensuels_litres',
        'nombre_personnels',
        'superficie_site',
        'parc_engin',
        'systeme_recuperation_huiles',
        'batiments_site',
        'infra_eau',
        'infra_electricite',
        'reseau_camtel',
        'reseau_mtn',
        'reseau_orange',
        'reseau_nexttel'
    ];

    $colonnes_table = array_column($columns, 'Field');
    $manquants = array_diff($nouveaux_champs, $colonnes_table);

    if (count($manquants) > 0) {
        echo "<p style='color:orange;'>⚠️ Colonnes manquantes dans la table : " . implode(', ', $manquants) . "</p>";
    } else {
        echo "<p style='color:green;'>✅ Tous les nouveaux champs sont présents dans la table</p>";
    }
    echo "</div>";

    // 2. Supprimer l'ancienne vue
    echo "<div class='info'>";
    echo "<h3>🗑️ Étape 2: Suppression de l'ancienne vue</h3>";

    $pdo->exec("DROP VIEW IF EXISTS vue_fiches_inspection_completes");
    echo "<p>✅ Ancienne vue supprimée (si elle existait)</p>";
    echo "</div>";

    // 3. Recréer la vue
    echo "<div class='info'>";
    echo "<h3>🔨 Étape 3: Création de la nouvelle vue</h3>";

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
    echo "<p>✅ Vue recréée avec succès</p>";
    echo "</div>";

    // 4. Vérifier que les nouveaux champs sont dans la vue
    echo "<div class='info'>";
    echo "<h3>🔍 Étape 4: Vérification des colonnes de la vue</h3>";

    $stmt = $pdo->query("SHOW COLUMNS FROM vue_fiches_inspection_completes");
    $view_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>La vue contient " . count($view_columns) . " colonnes.</p>";

    $colonnes_vue = array_column($view_columns, 'Field');
    $manquants_vue = array_diff($nouveaux_champs, $colonnes_vue);

    if (count($manquants_vue) > 0) {
        echo "<p style='color:red;'>❌ Colonnes manquantes dans la vue : " . implode(', ', $manquants_vue) . "</p>";
    } else {
        echo "<p style='color:green;'>✅ Tous les nouveaux champs sont présents dans la vue !</p>";
        echo "<ul>";
        foreach ($nouveaux_champs as $champ) {
            if (in_array($champ, $colonnes_vue)) {
                echo "<li style='color:green;'>✓ $champ</li>";
            }
        }
        echo "</ul>";
    }
    echo "</div>";

    // 5. Test de récupération avec un dossier
    echo "<div class='info'>";
    echo "<h3>🧪 Étape 5: Test de récupération des données</h3>";

    $stmt = $pdo->query("SELECT id, dossier_id FROM fiches_inspection LIMIT 1");
    $test_fiche = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($test_fiche) {
        echo "<p>Test avec fiche ID " . $test_fiche['id'] . " (dossier " . $test_fiche['dossier_id'] . ")</p>";

        $stmt = $pdo->prepare("SELECT
            numero_contrat_approvisionnement,
            societe_contractante,
            besoins_mensuels_litres,
            nombre_personnels,
            superficie_site,
            recommandations
        FROM vue_fiches_inspection_completes WHERE id = ?");
        $stmt->execute([$test_fiche['id']]);
        $test_data = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "<pre>";
        print_r($test_data);
        echo "</pre>";

        echo "<p style='color:green;'>✅ La vue retourne bien les nouveaux champs !</p>";
    } else {
        echo "<p>Aucune fiche d'inspection pour tester</p>";
    }
    echo "</div>";

    // Message final
    echo "<div class='success'>";
    echo "<h2>✅ SUCCÈS - Vue recréée avec succès !</h2>";
    echo "<p><strong>Prochaines étapes :</strong></p>";
    echo "<ol>";
    echo "<li>Testez l'affichage d'une fiche d'inspection</li>";
    echo "<li>Vérifiez que les Sections 3 et 8 s'affichent correctement</li>";
    echo "<li>SUPPRIMEZ ce fichier après vérification</li>";
    echo "</ol>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ ERREUR</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p>Trace:</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</body></html>";

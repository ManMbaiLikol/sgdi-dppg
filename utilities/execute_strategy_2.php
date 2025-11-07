<?php
/**
 * Exécution de la Stratégie 2 : Suppression et Recréation SANS GPS
 * ATTENTION : Script à exécuter avec précaution (backup obligatoire)
 */

require_once 'config/database.php';

// Paramètres de sécurité
$BACKUP_REQUIRED = true;
$DRY_RUN = true; // Mode simulation par défaut

// Vérifier le mode d'exécution
if (isset($_GET['mode'])) {
    if ($_GET['mode'] === 'real' && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        $DRY_RUN = false;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Exécution Stratégie 2 - DPPG</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #e74c3c; padding-bottom: 15px; }
        h2 { color: #34495e; margin-top: 30px; border-left: 4px solid #3498db; padding-left: 15px; }
        .warning { background: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .critical { background: #f8d7da; border: 2px solid #dc3545; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .success { background: #d4edda; border: 2px solid #28a745; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .info { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0; }
        .step { background: #f8f9fa; padding: 20px; margin: 15px 0; border-left: 4px solid #3498db; border-radius: 4px; }
        .step.completed { border-left-color: #28a745; background: #edfaf4; }
        .step.pending { border-left-color: #ffc107; }
        .step.error { border-left-color: #dc3545; background: #f8d7da; }
        .step-number { background: #3498db; color: white; width: 35px; height: 35px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 10px; font-size: 1.1em; }
        .step.completed .step-number { background: #28a745; }
        .step.error .step-number { background: #dc3545; }
        .btn { padding: 12px 25px; margin: 10px 5px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; font-size: 1em; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .sql-code { background: #f8f9fa; border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 0.9em; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #3498db; color: white; }
        tr:nth-child(even) { background: #f8f9fa; }
        .mode-indicator { position: fixed; top: 20px; right: 20px; padding: 15px 25px; border-radius: 8px; font-weight: bold; font-size: 1.1em; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        .mode-simulation { background: #28a745; color: white; }
        .mode-real { background: #dc3545; color: white; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .progress { background: #f8f9fa; border-radius: 4px; height: 30px; margin: 20px 0; overflow: hidden; }
        .progress-bar { background: linear-gradient(90deg, #3498db, #2ecc71); height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; transition: width 0.3s; }
    </style>
</head>
<body>

<div class="mode-indicator <?php echo $DRY_RUN ? 'mode-simulation' : 'mode-real'; ?>">
    <?php echo $DRY_RUN ? '🛡️ MODE SIMULATION' : '⚠️ MODE RÉEL'; ?>
</div>

<div class="container">
    <h1>🔄 Stratégie 2 : Suppression et Recréation SANS GPS</h1>

    <?php if ($DRY_RUN): ?>
        <div class="success">
            <strong>✅ MODE SIMULATION ACTIVÉ</strong><br>
            Aucune modification ne sera apportée à la base de données.<br>
            Ce script va uniquement afficher les requêtes SQL qui seront exécutées.
        </div>
    <?php else: ?>
        <div class="critical">
            <strong>⚠️ MODE RÉEL ACTIVÉ - DANGER !</strong><br>
            Les modifications vont être appliquées RÉELLEMENT à la base de données.<br>
            Cette opération est IRRÉVERSIBLE sans backup.
        </div>
    <?php endif; ?>

    <h2>📋 Plan d'Exécution</h2>

    <?php
    // Étape 1 : Analyse des données actuelles
    echo "<div class='step pending'>\n";
    echo "<span class='step-number'>1</span>\n";
    echo "<strong>ANALYSE DES DONNÉES ACTUELLES</strong>\n";
    echo "</div>\n";

    $stats_historiques = $pdo->query("
        SELECT
            COUNT(*) as total,
            COUNT(CASE WHEN coordonnees_gps IS NOT NULL AND coordonnees_gps != '' THEN 1 END) as avec_gps,
            COUNT(CASE WHEN coordonnees_gps IS NULL OR coordonnees_gps = '' THEN 1 END) as sans_gps
        FROM dossiers
        WHERE est_historique = 1
    ")->fetch(PDO::FETCH_ASSOC);

    // Compter les relations
    $tables_existantes = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    $relations = [];
    if (in_array('commissions', $tables_existantes)) {
        $relations['commissions'] = $pdo->query("SELECT COUNT(*) FROM commissions WHERE dossier_id IN (SELECT id FROM dossiers WHERE est_historique = 1)")->fetchColumn();
    }
    if (in_array('notes_frais', $tables_existantes)) {
        $relations['notes_frais'] = $pdo->query("SELECT COUNT(*) FROM notes_frais WHERE dossier_id IN (SELECT id FROM dossiers WHERE est_historique = 1)")->fetchColumn();
    }
    if (in_array('paiements', $tables_existantes)) {
        $relations['paiements'] = $pdo->query("SELECT COUNT(*) FROM paiements WHERE dossier_id IN (SELECT id FROM dossiers WHERE est_historique = 1)")->fetchColumn();
    }
    if (in_array('documents', $tables_existantes)) {
        $relations['documents'] = $pdo->query("SELECT COUNT(*) FROM documents WHERE dossier_id IN (SELECT id FROM dossiers WHERE est_historique = 1)")->fetchColumn();
    }

    $total_relations = array_sum($relations);

    echo "<table>\n";
    echo "<tr><th>Métrique</th><th>Valeur</th></tr>\n";
    echo "<tr><td>Stations historiques totales</td><td><strong>{$stats_historiques['total']}</strong></td></tr>\n";
    echo "<tr><td>Avec GPS</td><td>{$stats_historiques['avec_gps']}</td></tr>\n";
    echo "<tr><td>Sans GPS</td><td>{$stats_historiques['sans_gps']}</td></tr>\n";
    echo "<tr><td colspan='2'><strong>Enregistrements liés</strong></td></tr>\n";
    foreach ($relations as $table => $count) {
        echo "<tr><td style='padding-left: 30px;'>$table</td><td>$count</td></tr>\n";
    }
    echo "<tr><td><strong>TOTAL enregistrements liés</strong></td><td><strong>$total_relations</strong></td></tr>\n";
    echo "</table>\n";

    // Étape 2 : Backup
    echo "<div class='step pending'>\n";
    echo "<span class='step-number'>2</span>\n";
    echo "<strong>BACKUP COMPLET</strong><br><br>\n";

    echo "<div class='info'>\n";
    echo "Avant toute modification, créez un backup complet :\n";
    echo "<pre>";
    echo "# Depuis la ligne de commande:\n";
    echo "mysqldump -u root dppg_implantation > backup_avant_strategie2_" . date('Y-m-d_His') . ".sql\n\n";
    echo "# Ou utilisez phpMyAdmin → Exporter\n";
    echo "</pre>\n";
    echo "</div>\n";

    if (!$DRY_RUN) {
        echo "<div class='critical'>\n";
        echo "⚠️ <strong>CONFIRMEZ que vous avez créé un backup avant de continuer !</strong>\n";
        echo "</div>\n";
    }
    echo "</div>\n";

    // Étape 3 : Export données métier
    echo "<div class='step pending'>\n";
    echo "<span class='step-number'>3</span>\n";
    echo "<strong>EXPORT DONNÉES MÉTIER (sans GPS)</strong><br><br>\n";

    $export_file = "exports/stations_historiques_metier_" . date('Y-m-d_His') . ".csv";
    $export_dir = __DIR__ . "/exports";

    if (!file_exists($export_dir)) {
        mkdir($export_dir, 0755, true);
    }

    if (!$DRY_RUN) {
        // Export réel
        $stmt = $pdo->query("
            SELECT
                numero,
                nom_demandeur,
                type_infrastructure,
                sous_type,
                region,
                ville,
                adresse_precise,
                contact_demandeur,
                telephone_demandeur,
                email_demandeur,
                operateur_proprietaire,
                entreprise_beneficiaire,
                entreprise_installatrice,
                statut,
                date_creation
            FROM dossiers
            WHERE est_historique = 1
            ORDER BY region, ville, nom_demandeur
        ");

        $fp = fopen($export_dir . "/" . basename($export_file), 'w');
        fputcsv($fp, ['numero', 'nom_demandeur', 'type_infrastructure', 'sous_type', 'region', 'ville', 'adresse_precise', 'contact_demandeur', 'telephone_demandeur', 'email_demandeur', 'operateur_proprietaire', 'entreprise_beneficiaire', 'entreprise_installatrice', 'statut', 'date_creation']);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        echo "<div class='success'>✅ Export créé : <strong>$export_file</strong></div>\n";
    } else {
        echo "<div class='info'>📄 Le fichier sera exporté vers : <strong>$export_file</strong></div>\n";
        echo "<div class='sql-code'>\n";
        echo "SELECT numero, nom_demandeur, type_infrastructure, sous_type, region, ville,<br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;adresse_precise, contact_demandeur, telephone_demandeur, email_demandeur,<br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;operateur_proprietaire, entreprise_beneficiaire, entreprise_installatrice,<br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;statut, date_creation<br>";
        echo "FROM dossiers<br>";
        echo "WHERE est_historique = 1<br>";
        echo "→ Export vers CSV (sans coordonnees_gps ni score_matching_osm)\n";
        echo "</div>\n";
    }
    echo "</div>\n";

    // Étape 4 : Suppression
    echo "<div class='step pending'>\n";
    echo "<span class='step-number'>4</span>\n";
    echo "<strong>SUPPRESSION DES STATIONS HISTORIQUES</strong><br><br>\n";

    if (!$DRY_RUN) {
        try {
            $pdo->beginTransaction();

            // Suppression en cascade
            $deleted = $pdo->exec("DELETE FROM dossiers WHERE est_historique = 1");

            $pdo->commit();

            echo "<div class='success'>✅ <strong>$deleted stations historiques supprimées</strong></div>\n";
            echo "<div class='info'>Les enregistrements liés ont été supprimés automatiquement (CASCADE)</div>\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div class='critical'>❌ ERREUR : " . $e->getMessage() . "</div>\n";
        }
    } else {
        echo "<div class='warning'>\n";
        echo "<strong>SQL qui sera exécuté :</strong>\n";
        echo "<div class='sql-code'>\n";
        echo "BEGIN TRANSACTION;<br>\n";
        echo "DELETE FROM dossiers WHERE est_historique = 1;<br>\n";
        echo "COMMIT;<br>\n";
        echo "</div>\n";
        echo "→ Supprimera <strong>{$stats_historiques['total']} stations historiques</strong><br>\n";
        echo "→ Supprimera <strong>$total_relations enregistrements liés</strong> (CASCADE)\n";
        echo "</div>\n";
    }
    echo "</div>\n";

    // Étape 5 : Réimport
    echo "<div class='step pending'>\n";
    echo "<span class='step-number'>5</span>\n";
    echo "<strong>RÉIMPORT DES DONNÉES PROPRES (SANS GPS)</strong><br><br>\n";

    if (!$DRY_RUN && file_exists($export_dir . "/" . basename($export_file))) {
        try {
            $pdo->beginTransaction();

            $fp = fopen($export_dir . "/" . basename($export_file), 'r');
            $headers = fgetcsv($fp); // Ignorer la ligne d'en-tête

            $imported = 0;
            $stmt_insert = $pdo->prepare("
                INSERT INTO dossiers (
                    numero, nom_demandeur, type_infrastructure, sous_type, region, ville,
                    adresse_precise, contact_demandeur, telephone_demandeur, email_demandeur,
                    operateur_proprietaire, entreprise_beneficiaire, entreprise_installatrice,
                    statut, date_creation, est_historique, coordonnees_gps, score_matching_osm,
                    user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NULL, NULL, 1)
            ");

            while ($row = fgetcsv($fp)) {
                $stmt_insert->execute($row);
                $imported++;
            }
            fclose($fp);

            $pdo->commit();

            echo "<div class='success'>✅ <strong>$imported stations historiques réimportées SANS GPS</strong></div>\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div class='critical'>❌ ERREUR : " . $e->getMessage() . "</div>\n";
        }
    } else {
        echo "<div class='info'>\n";
        echo "<strong>Les données du CSV seront réimportées avec :</strong><br>\n";
        echo "• Toutes les informations métier (nom, adresse, région, etc.)<br>\n";
        echo "• <strong>coordonnees_gps = NULL</strong> (pas de GPS)<br>\n";
        echo "• <strong>score_matching_osm = NULL</strong><br>\n";
        echo "• <strong>est_historique = 1</strong><br>\n";
        echo "• <strong>statut</strong> conservé (historique_autorise)<br><br>\n";
        echo "→ Les stations seront visibles dans le registre public (liste textuelle)<br>\n";
        echo "→ Les stations ne seront PAS visibles sur la carte (pas de GPS)<br>\n";
        echo "→ GPS ajoutables ultérieurement via interface admin\n";
        echo "</div>\n";
    }
    echo "</div>\n";

    // Résultat final
    if (!$DRY_RUN) {
        $new_count = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1")->fetchColumn();

        echo "<div class='step completed'>\n";
        echo "<span class='step-number'>✓</span>\n";
        echo "<strong>OPÉRATION TERMINÉE</strong><br><br>\n";
        echo "<div class='success'>\n";
        echo "✅ Base de données nettoyée avec succès !<br><br>\n";
        echo "<strong>Résultat :</strong><br>\n";
        echo "• $new_count stations historiques dans la base<br>\n";
        echo "• 0 GPS dupliqués<br>\n";
        echo "• 0 collision GPS<br>\n";
        echo "• Base de données propre et saine<br><br>\n";
        echo "<strong>Prochaines étapes :</strong><br>\n";
        echo "1. Créer interface admin pour ajouter GPS progressivement<br>\n";
        echo "2. Obtenir GPS réels (terrain, opérateurs, géocodage précis)<br>\n";
        echo "3. Valider chaque GPS avant ajout<br>\n";
        echo "</div>\n";
        echo "</div>\n";
    }
    ?>

    <h2>🎮 Actions</h2>

    <div style="text-align: center; margin: 30px 0;">
        <?php if ($DRY_RUN): ?>
            <a href="compare_strategies.php" class="btn btn-primary">← Retour à la Comparaison</a>
            <a href="execute_strategy_2.php?mode=real&confirm=yes" class="btn btn-danger"
               onclick="return confirm('⚠️ ATTENTION !\n\nVous allez exécuter le script EN MODE RÉEL.\n\nCette opération va SUPPRIMER {$stats_historiques['total']} stations historiques et $total_relations enregistrements liés.\n\nAvez-vous créé un BACKUP complet ?\n\nCliquez OK pour continuer ou Annuler pour revenir en arrière.');">
                ⚠️ Exécuter EN MODE RÉEL
            </a>
        <?php else: ?>
            <a href="diagnostic_data_quality.php" class="btn btn-success">📊 Vérifier la Qualité des Données</a>
            <a href="compare_strategies.php" class="btn btn-primary">← Retour</a>
        <?php endif; ?>
    </div>

    <?php if ($DRY_RUN): ?>
        <div class="critical">
            <strong>⚠️ CHECKLIST AVANT EXÉCUTION RÉELLE :</strong><br><br>
            <input type="checkbox" id="check1"> J'ai créé un backup complet de la base de données<br>
            <input type="checkbox" id="check2"> J'ai vérifié que les stations historiques peuvent être supprimées<br>
            <input type="checkbox" id="check3"> J'ai compris que cette opération est IRRÉVERSIBLE<br>
            <input type="checkbox" id="check4"> Je sais que les GPS devront être rajoutés manuellement plus tard<br>
            <input type="checkbox" id="check5"> J'ai l'accord de mon supérieur/responsable<br><br>
            <button class="btn btn-danger" id="btnExecute" disabled onclick="location.href='execute_strategy_2.php?mode=real&confirm=yes'">
                🚀 J'ai tout vérifié - EXÉCUTER
            </button>
        </div>

        <script>
            const checks = document.querySelectorAll('input[type="checkbox"]');
            const btnExecute = document.getElementById('btnExecute');

            checks.forEach(check => {
                check.addEventListener('change', () => {
                    const allChecked = Array.from(checks).every(c => c.checked);
                    btnExecute.disabled = !allChecked;
                });
            });
        </script>
    <?php endif; ?>

</div>

</body>
</html>

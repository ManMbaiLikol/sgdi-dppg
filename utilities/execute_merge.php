<?php
/**
 * Script d'exécution des fusions de doublons
 * Fusionne deux stations en une seule
 */

require_once 'config/database.php';
session_start();

// Vérifier les paramètres
if (!isset($_GET['merge']) || !isset($_GET['keep'])) {
    die("Paramètres manquants : merge et keep requis");
}

$merge_id = intval($_GET['merge']);
$keep_id = intval($_GET['keep']);

if ($merge_id === $keep_id) {
    die("Erreur : les IDs doivent être différents");
}

// Mode de prévisualisation par défaut
$preview_mode = !isset($_GET['confirm']) || $_GET['confirm'] !== 'yes';

// Récupérer les deux stations
$stmt = $pdo->prepare("
    SELECT
        d.id,
        d.numero,
        d.nom_demandeur,
        d.type_infrastructure,
        d.coordonnees_gps,
        d.statut,
        d.region,
        d.ville,
        d.adresse_precise,
        d.operateur_proprietaire,
        d.date_creation
    FROM dossiers d
    WHERE d.id IN (?, ?)
");
$stmt->execute([$merge_id, $keep_id]);
$stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($stations) !== 2) {
    die("Erreur : une ou plusieurs stations introuvables");
}

$station_to_merge = null;
$station_to_keep = null;

foreach ($stations as $station) {
    if ($station['id'] == $merge_id) {
        $station_to_merge = $station;
    } else {
        $station_to_keep = $station;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Fusion de Stations - DPPG</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #e74c3c; padding-bottom: 10px; }
        .warning-banner { background: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .warning-banner strong { color: #856404; font-size: 1.2em; }
        .comparison { display: grid; grid-template-columns: 1fr 80px 1fr; gap: 20px; margin: 30px 0; }
        .station-card { padding: 20px; border-radius: 8px; border: 3px solid; }
        .station-card.delete { border-color: #e74c3c; background: #fee; }
        .station-card.keep { border-color: #27ae60; background: #efe; }
        .station-card h2 { margin-top: 0; }
        .station-card .field { margin: 10px 0; padding: 8px; background: white; border-radius: 4px; }
        .station-card .field-label { font-weight: bold; color: #555; font-size: 0.9em; }
        .station-card .field-value { color: #2c3e50; margin-top: 3px; }
        .arrow { display: flex; align-items: center; justify-content: center; font-size: 3em; color: #e74c3c; }
        .impact-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #3498db; }
        .impact-section h3 { margin-top: 0; color: #2c3e50; }
        .impact-list { list-style: none; padding: 0; }
        .impact-list li { padding: 8px 0; border-bottom: 1px solid #dee2e6; }
        .impact-list li:last-child { border-bottom: none; }
        .action-buttons { text-align: center; margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 8px; }
        .btn { padding: 12px 30px; margin: 0 10px; border: none; border-radius: 4px; font-size: 1.1em; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-secondary:hover { background: #7f8c8d; }
        .success-box { background: #d4edda; border: 2px solid #28a745; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0; }
        .success-box h2 { color: #155724; margin-top: 0; }
    </style>
</head>
<body>

<div class="container">
    <?php if ($preview_mode): ?>
        <h1>⚠️ Confirmation de Fusion de Doublons</h1>

        <div class="warning-banner">
            <strong>⚠️ ATTENTION - Action Irréversible</strong><br><br>
            Vous êtes sur le point de fusionner deux stations. Cette opération :
            <ul>
                <li>❌ Supprimera définitivement la station de gauche</li>
                <li>✅ Conservera la station de droite</li>
                <li>🔄 Mettra à jour toutes les références (documents, historiques, etc.)</li>
                <li>📝 Créera un log d'audit de la fusion</li>
            </ul>
            <strong>Cette action ne peut pas être annulée !</strong>
        </div>

        <h2>🔍 Prévisualisation de la Fusion</h2>

        <div class="comparison">
            <!-- Station à supprimer -->
            <div class="station-card delete">
                <h2 style="color: #e74c3c;">❌ À SUPPRIMER</h2>

                <div class="field">
                    <div class="field-label">Nom</div>
                    <div class="field-value"><strong><?php echo htmlspecialchars($station_to_merge['nom_demandeur']); ?></strong></div>
                </div>

                <div class="field">
                    <div class="field-label">Numéro</div>
                    <div class="field-value"><?php echo htmlspecialchars($station_to_merge['numero']); ?></div>
                </div>

                <div class="field">
                    <div class="field-label">Type</div>
                    <div class="field-value"><?php echo htmlspecialchars($station_to_merge['type_infrastructure']); ?></div>
                </div>

                <div class="field">
                    <div class="field-label">Coordonnées GPS</div>
                    <div class="field-value"><?php echo htmlspecialchars($station_to_merge['coordonnees_gps'] ?? 'Non renseignées'); ?></div>
                </div>

                <div class="field">
                    <div class="field-label">Région</div>
                    <div class="field-value"><?php echo htmlspecialchars($station_to_merge['region'] ?? 'N/A'); ?></div>
                </div>

                <div class="field">
                    <div class="field-label">Ville</div>
                    <div class="field-value"><?php echo htmlspecialchars($station_to_merge['ville'] ?? 'N/A'); ?></div>
                </div>

                <div class="field">
                    <div class="field-label">Statut</div>
                    <div class="field-value"><?php echo htmlspecialchars($station_to_merge['statut']); ?></div>
                </div>

                <div class="field">
                    <div class="field-label">Date création</div>
                    <div class="field-value"><?php echo htmlspecialchars($station_to_merge['date_creation']); ?></div>
                </div>
            </div>

            <!-- Flèche -->
            <div class="arrow">→</div>

            <!-- Station à conserver -->
            <div class="station-card keep">
                <h2 style="color: #27ae60;">✅ À CONSERVER</h2>

                <div class="field">
                    <div class="field-label">Nom</div>
                    <div class="field-value"><strong><?php echo htmlspecialchars($station_to_keep['nom_demandeur']); ?></strong></div>
                </div>

                <div class="field">
                    <div class="field-label">Numéro</div>
                    <div class="field-value"><?php echo htmlspecialchars($station_to_keep['numero']); ?></div>
                </div>

                <div class="field">
                    <div class="field-label">Type</div>
                    <div class="field-value"><?php echo htmlspecialchars($station_to_keep['type_infrastructure']); ?></div>
                </div>

                <div class="field">
                    <div class="field-label">Coordonnées GPS</div>
                    <div class="field-value"><?php echo htmlspecialchars($station_to_keep['coordonnees_gps'] ?? 'Non renseignées'); ?></div>
                </div>

                <div class="field">
                    <div class="field-label">Région</div>
                    <div class="field-value"><?php echo htmlspecialchars($station_to_keep['region'] ?? 'N/A'); ?></div>
                </div>

                <div class="field">
                    <div class="field-label">Ville</div>
                    <div class="field-value"><?php echo htmlspecialchars($station_to_keep['ville'] ?? 'N/A'); ?></div>
                </div>

                <div class="field">
                    <div class="field-label">Statut</div>
                    <div class="field-value"><?php echo htmlspecialchars($station_to_keep['statut']); ?></div>
                </div>

                <div class="field">
                    <div class="field-label">Date création</div>
                    <div class="field-value"><?php echo htmlspecialchars($station_to_keep['date_creation']); ?></div>
                </div>
            </div>
        </div>

        <?php
        // Vérifier les dépendances
        $dependencies = [];

        // Documents liés
        $stmt_docs = $pdo->prepare("SELECT COUNT(*) as count FROM documents WHERE dossier_id = ?");
        $stmt_docs->execute([$merge_id]);
        $doc_count = $stmt_docs->fetch()['count'];
        if ($doc_count > 0) {
            $dependencies[] = "$doc_count document(s) seront rattachés à la station conservée";
        }

        // Historique
        try {
            $stmt_hist = $pdo->prepare("SELECT COUNT(*) as count FROM historique WHERE dossier_id = ?");
            $stmt_hist->execute([$merge_id]);
            $hist_count = $stmt_hist->fetch()['count'];
            if ($hist_count > 0) {
                $dependencies[] = "$hist_count entrée(s) d'historique seront conservées";
            }
        } catch (PDOException $e) {
            // Table historique peut ne pas exister
            $hist_count = 0;
        }

        // Commissions
        try {
            $stmt_comm = $pdo->prepare("SELECT COUNT(*) as count FROM commissions WHERE dossier_id = ?");
            $stmt_comm->execute([$merge_id]);
            $comm_count = $stmt_comm->fetch()['count'];
            if ($comm_count > 0) {
                $dependencies[] = "$comm_count commission(s) seront fusionnées";
            }
        } catch (PDOException $e) {
            $comm_count = 0;
        }
        ?>

        <div class="impact-section">
            <h3>📋 Impact de la Fusion</h3>
            <?php if (count($dependencies) > 0): ?>
                <ul class="impact-list">
                    <?php foreach ($dependencies as $dep): ?>
                        <li>🔄 <?php echo $dep; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>✅ Aucune dépendance détectée - fusion simple.</p>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <a href="execute_merge.php?merge=<?php echo $merge_id; ?>&keep=<?php echo $keep_id; ?>&confirm=yes"
               class="btn btn-danger"
               onclick="return confirm('DERNIÈRE CONFIRMATION\n\nÊtes-vous ABSOLUMENT certain de vouloir supprimer la station #<?php echo $merge_id; ?> ?\n\nCette action est IRRÉVERSIBLE.');">
                ✅ CONFIRMER LA FUSION
            </a>
            <a href="clean_and_merge_data.php" class="btn btn-secondary">
                ❌ ANNULER
            </a>
        </div>

    <?php else: ?>
        <?php
        // EXÉCUTION DE LA FUSION
        try {
            $pdo->beginTransaction();

            // 1. Logger la fusion avant suppression
            $log_description = sprintf(
                "Fusion de doublon: Station #%d (%s) fusionnée dans Station #%d (%s). GPS: %s -> %s",
                $merge_id,
                $station_to_merge['nom_demandeur'],
                $keep_id,
                $station_to_keep['nom_demandeur'],
                $station_to_merge['coordonnees_gps'] ?? 'N/A',
                $station_to_keep['coordonnees_gps'] ?? 'N/A'
            );

            $stmt_log = $pdo->prepare("
                INSERT INTO logs_activite (
                    user_id,
                    action,
                    description,
                    date_action
                ) VALUES (
                    ?,
                    'fusion_doublon',
                    ?,
                    NOW()
                )
            ");

            $user_id = $_SESSION['user_id'] ?? 1; // Par défaut admin si pas de session
            $stmt_log->execute([$user_id, $log_description]);

            // 2. Mettre à jour les références
            // Documents
            $pdo->prepare("UPDATE documents SET dossier_id = ? WHERE dossier_id = ?")
                ->execute([$keep_id, $merge_id]);

            // Historique
            try {
                $pdo->prepare("UPDATE historique SET dossier_id = ? WHERE dossier_id = ?")
                    ->execute([$keep_id, $merge_id]);
            } catch (PDOException $e) {
                // Table peut ne pas exister
            }

            // Commissions
            try {
                $pdo->prepare("UPDATE commissions SET dossier_id = ? WHERE dossier_id = ?")
                    ->execute([$keep_id, $merge_id]);
            } catch (PDOException $e) {
                // Table peut ne pas exister
            }

            // Notifications
            try {
                $pdo->prepare("UPDATE notifications SET dossier_id = ? WHERE dossier_id = ?")
                    ->execute([$keep_id, $merge_id]);
            } catch (PDOException $e) {
                // Table peut ne pas exister
            }

            // 3. Marquer le dossier comme archivé avant suppression (sécurité)
            try {
                $pdo->prepare("UPDATE dossiers SET archive = 1 WHERE id = ?")
                    ->execute([$merge_id]);
            } catch (PDOException $e) {
                // Le champ archive peut ne pas exister, on continue
            }

            // 4. Supprimer le doublon
            $pdo->prepare("DELETE FROM dossiers WHERE id = ?")
                ->execute([$merge_id]);

            $pdo->commit();

            $success = true;
            $message = "Fusion réussie !";

        } catch (Exception $e) {
            $pdo->rollBack();
            $success = false;
            $message = "Erreur lors de la fusion : " . $e->getMessage();
        }
        ?>

        <?php if ($success): ?>
            <div class="success-box">
                <h2>✅ Fusion Réussie !</h2>
                <p style="font-size: 1.1em;">La station #<?php echo $merge_id; ?> a été fusionnée avec succès dans la station #<?php echo $keep_id; ?>.</p>
                <p>Toutes les références ont été mises à jour et un log d'audit a été créé.</p>
            </div>

            <div class="action-buttons">
                <a href="clean_and_merge_data.php" class="btn btn-secondary">← Retour au nettoyage</a>
                <a href="modules/registre_public/carte.php" class="btn btn-secondary">🗺️ Voir la carte</a>
            </div>
        <?php else: ?>
            <div class="warning-banner">
                <strong>❌ ERREUR</strong><br><br>
                <?php echo htmlspecialchars($message); ?>
            </div>

            <div class="action-buttons">
                <a href="clean_and_merge_data.php" class="btn btn-secondary">← Retour</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>

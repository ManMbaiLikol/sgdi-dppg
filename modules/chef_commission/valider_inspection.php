<?php
// Validation d'inspection par le Chef de Commission - SGDI
require_once '../../includes/auth.php';
require_once '../dossiers/functions.php';

requireRole('chef_commission');

$dossier_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$dossier_id) {
    redirect(url('modules/chef_commission/dashboard.php'), 'Dossier non spécifié', 'error');
}

// Vérifier que le dossier existe et que l'utilisateur est bien le chef de commission
$sql = "SELECT d.*,
               c.id as commission_id,
               c.chef_commission_id,
               c.cadre_dppg_id,
               c.cadre_daj_id,
               i.id as inspection_id,
               i.date_inspection,
               i.rapport,
               i.recommandations,
               i.conforme,
               i.observations as observations_inspection,
               i.valide_par_chef_commission,
               u_dppg.nom as nom_cadre_dppg,
               u_dppg.prenom as prenom_cadre_dppg,
               u_daj.nom as nom_cadre_daj,
               u_daj.prenom as prenom_cadre_daj
        FROM dossiers d
        INNER JOIN commissions c ON d.id = c.dossier_id
        LEFT JOIN inspections i ON d.id = i.dossier_id
        LEFT JOIN users u_dppg ON c.cadre_dppg_id = u_dppg.id
        LEFT JOIN users u_daj ON c.cadre_daj_id = u_daj.id
        WHERE d.id = ? AND c.chef_commission_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id, $user_id]);
$dossier = $stmt->fetch();

if (!$dossier) {
    redirect(url('modules/chef_commission/dashboard.php'), 'Dossier introuvable ou vous n\'êtes pas le chef de commission', 'error');
}

// Vérifier que le dossier est au statut "inspecté" et que l'inspection existe
if ($dossier['statut'] !== 'inspecte' || !$dossier['inspection_id']) {
    redirect(url('modules/chef_commission/view.php?id=' . $dossier_id),
             'Le dossier n\'est pas au stade de validation d\'inspection', 'error');
}

// Vérifier que l'inspection n'a pas déjà été validée
if ($dossier['valide_par_chef_commission']) {
    redirect(url('modules/chef_commission/view.php?id=' . $dossier_id),
             'L\'inspection a déjà été validée', 'warning');
}

$errors = [];
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $decision = sanitize($_POST['decision'] ?? '');
        $observations = sanitize($_POST['observations'] ?? '');

        if (!in_array($decision, ['valider', 'rejeter'])) {
            $errors[] = 'Décision invalide';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                if ($decision === 'valider') {
                    // Valider l'inspection
                    $sql = "UPDATE inspections SET
                            valide_par_chef_commission = 1,
                            chef_commission_id = ?,
                            date_validation_chef_commission = NOW(),
                            observations_chef_commission = ?
                            WHERE id = ?";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$user_id, $observations, $dossier['inspection_id']]);

                    // Changer le statut du dossier
                    $sql = "UPDATE dossiers SET statut = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['validation_commission', $dossier_id]);

                    // Ajouter à l'historique
                    addHistoriqueDossier($dossier_id, $user_id, 'validation_inspection_chef_commission',
                                       'Inspection validée par le Chef de Commission',
                                       'inspecte', 'validation_commission');

                    // Créer notification pour le directeur
                    $sql = "INSERT INTO notifications (user_id, type, titre, message, dossier_id)
                            SELECT id, 'validation_inspection', 'Inspection validée par le Chef de Commission',
                                   CONCAT('Le dossier ', ?, ' a été validé par le Chef de Commission et attend votre validation finale.'), ?
                            FROM users WHERE role = 'directeur'";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$dossier['numero'], $dossier_id]);

                    $pdo->commit();
                    redirect(url('modules/chef_commission/dashboard.php'),
                             'Inspection validée avec succès. Le dossier a été transmis au directeur.', 'success');
                } else {
                    // Rejeter et renvoyer au cadre DPPG
                    $sql = "UPDATE inspections SET
                            observations_chef_commission = ?
                            WHERE id = ?";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$observations, $dossier['inspection_id']]);

                    // Remettre le statut à analyse_daj pour refaire l'inspection
                    $sql = "UPDATE dossiers SET statut = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['analyse_daj', $dossier_id]);

                    // Ajouter à l'historique
                    addHistoriqueDossier($dossier_id, $user_id, 'rejet_inspection_chef_commission',
                                       'Inspection rejetée par le Chef de Commission - À refaire',
                                       'inspecte', 'analyse_daj');

                    // Créer notification pour le cadre DPPG
                    $sql = "INSERT INTO notifications (user_id, type, titre, message, dossier_id)
                            VALUES (?, 'rejet_inspection', 'Inspection à refaire',
                                   CONCAT('Le Chef de Commission a rejeté votre inspection pour le dossier ', ?. '. Observations: ', ?), ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$dossier['cadre_dppg_id'], $dossier['numero'], $observations, $dossier_id]);

                    $pdo->commit();
                    redirect(url('modules/chef_commission/dashboard.php'),
                             'Inspection rejetée. Le cadre DPPG a été notifié pour refaire l\'inspection.', 'warning');
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Erreur lors de la validation: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Validation d\'inspection - ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-10 offset-md-1">
        <div class="card">
            <div class="card-header bg-warning text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-clipboard-check"></i> Validation d'inspection
                </h5>
                <p class="mb-0">
                    Dossier: <strong><?php echo sanitize($dossier['numero']); ?></strong>
                </p>
            </div>

            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Informations du dossier -->
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle"></i> Informations du dossier</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Type:</strong> <?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?><br>
                            <strong>Demandeur:</strong> <?php echo sanitize($dossier['nom_demandeur']); ?><br>
                            <strong>Localisation:</strong> <?php echo sanitize($dossier['ville'] ?? 'N/A'); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Cadre DPPG:</strong> <?php echo sanitize($dossier['prenom_cadre_dppg'] . ' ' . $dossier['nom_cadre_dppg']); ?><br>
                            <strong>Cadre DAJ:</strong> <?php echo sanitize($dossier['prenom_cadre_daj'] . ' ' . $dossier['nom_cadre_daj']); ?><br>
                            <strong>Date inspection:</strong> <?php echo formatDate($dossier['date_inspection']); ?>
                        </div>
                    </div>
                </div>

                <!-- Analyse DAJ -->
                <?php if (in_array($dossier['statut'], ['analyse_daj', 'inspecte', 'validation_commission', 'valide', 'decide'])): ?>
                <div class="mb-4">
                    <h6 class="text-primary">
                        <i class="fas fa-gavel"></i> Analyse juridique (Cadre DAJ)
                    </h6>
                    <div class="card">
                        <div class="card-body">
                            <div class="alert alert-success mb-0">
                                <i class="fas fa-check-circle"></i>
                                L'analyse juridique DAJ a été effectuée pour ce dossier.
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Rapport d'inspection -->
                <div class="mb-4">
                    <h6 class="text-primary">
                        <i class="fas fa-file-alt"></i> Rapport d'inspection (Cadre DPPG)
                    </h6>
                    <div class="card">
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Conformité technique:</strong>
                                <span class="badge bg-<?php echo $dossier['conforme'] === 'oui' ? 'success' : ($dossier['conforme'] === 'non' ? 'danger' : 'warning'); ?>">
                                    <?php echo ucfirst($dossier['conforme']); ?>
                                </span>
                            </div>

                            <div class="mb-3">
                                <strong>Rapport:</strong>
                                <div class="p-3 bg-light border rounded">
                                    <?php echo nl2br(sanitize($dossier['rapport'])); ?>
                                </div>
                            </div>

                            <?php if ($dossier['recommandations']): ?>
                            <div class="mb-3">
                                <strong>Recommandations:</strong>
                                <div class="p-3 bg-light border rounded">
                                    <?php echo nl2br(sanitize($dossier['recommandations'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($dossier['observations_inspection']): ?>
                            <div class="mb-3">
                                <strong>Observations de l'inspecteur:</strong>
                                <div class="p-3 bg-light border rounded">
                                    <?php echo nl2br(sanitize($dossier['observations_inspection'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Formulaire de validation -->
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="mb-4">
                        <h6 class="text-primary">
                            <i class="fas fa-clipboard-check"></i> Votre décision en tant que Chef de Commission
                        </h6>

                        <div class="mb-3">
                            <label class="form-label">Décision *</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="decision" id="decision_valider"
                                           value="valider" required>
                                    <label class="form-check-label text-success" for="decision_valider">
                                        <i class="fas fa-check-circle"></i> <strong>Valider l'inspection</strong>
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="decision" id="decision_rejeter"
                                           value="rejeter" required>
                                    <label class="form-check-label text-danger" for="decision_rejeter">
                                        <i class="fas fa-times-circle"></i> <strong>Rejeter et demander une nouvelle inspection</strong>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="observations" class="form-label">Observations / Commentaires</label>
                            <textarea class="form-control" id="observations" name="observations" rows="4"
                                      placeholder="Vos observations en tant que Chef de Commission..."><?php echo sanitize($_POST['observations'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">
                                Ces observations seront visibles dans le dossier et notifiées aux parties concernées
                            </small>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle"></i> Important</h6>
                        <ul class="mb-0">
                            <li>Si vous <strong>validez</strong>, le dossier sera transmis au Directeur DPPG pour validation finale</li>
                            <li>Si vous <strong>rejetez</strong>, le dossier retournera au Cadre DPPG pour refaire l'inspection</li>
                        </ul>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?php echo url('modules/chef_commission/dashboard.php'); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Soumettre ma décision
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

<?php
// Apposer visa Chef Service - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

requireRole('chef_service');

$dossier_id = $_GET['id'] ?? null;

if (!$dossier_id || !is_numeric($dossier_id)) {
    redirect(url('modules/dossiers/viser_inspections.php'), 'Dossier non spécifié', 'error');
}

// Récupérer les détails du dossier
$dossier = getDossierDetails($dossier_id);

if (!$dossier) {
    redirect(url('modules/dossiers/viser_inspections.php'), 'Dossier non trouvé', 'error');
}

// Vérifier que le dossier est bien au statut 'inspecte'
if ($dossier['statut'] !== 'inspecte') {
    redirect(url('modules/dossiers/viser_inspections.php'),
        'Ce dossier n\'est pas au statut "inspecté"', 'error');
}

// Vérifier qu'il y a une inspection validée
$sql = "SELECT * FROM inspections WHERE dossier_id = ? AND valide_par_chef_commission = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id]);
$inspection = $stmt->fetch();

if (!$inspection) {
    redirect(url('modules/dossiers/viser_inspections.php'),
        'L\'inspection n\'a pas encore été validée par le Chef de Commission', 'error');
}

// Vérifier qu'il n'y a pas déjà un visa chef service
$sql = "SELECT * FROM visas WHERE dossier_id = ? AND role = 'chef_service'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id]);
$visa_existant = $stmt->fetch();

if ($visa_existant) {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
        'Vous avez déjà visé ce dossier', 'info');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $action = sanitize($_POST['action'] ?? '');
        $observations = sanitize($_POST['observations'] ?? '');

        if (!in_array($action, ['approuve', 'rejete', 'demande_modification'])) {
            throw new Exception('Action invalide');
        }

        // Insérer le visa
        $sql = "INSERT INTO visas (dossier_id, user_id, role, action, observations, date_visa)
                VALUES (?, ?, 'chef_service', ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dossier_id, $_SESSION['user_id'], $action, $observations]);

        // Mettre à jour le statut du dossier
        if ($action === 'approuve') {
            // Transmettre au sous-directeur
            $nouveau_statut = 'visa_chef_service';
            $sql = "UPDATE dossiers SET statut = ?, date_modification = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nouveau_statut, $dossier_id]);

            // Ajouter dans l'historique
            $sql = "INSERT INTO historique_dossier (dossier_id, user_id, action, commentaire, date_action)
                    VALUES (?, ?, 'visa_chef_service', ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $dossier_id,
                $_SESSION['user_id'],
                'Visa Chef Service SDTD approuvé - Transmission au Sous-Directeur SDTD'
            ]);

            $message = 'Votre visa a été apposé avec succès. Le dossier a été transmis au Sous-Directeur SDTD.';
            $type = 'success';

        } elseif ($action === 'rejete') {
            // Rejeter le dossier
            $nouveau_statut = 'rejete';
            $sql = "UPDATE dossiers SET statut = ?, date_modification = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nouveau_statut, $dossier_id]);

            // Ajouter dans l'historique
            $sql = "INSERT INTO historique_dossier (dossier_id, user_id, action, commentaire, date_action)
                    VALUES (?, ?, 'visa_chef_service_rejete', ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $dossier_id,
                $_SESSION['user_id'],
                'Visa Chef Service SDTD rejeté : ' . $observations
            ]);

            $message = 'Le dossier a été rejeté.';
            $type = 'warning';

        } else { // demande_modification
            // Retourner à l'inspection
            $nouveau_statut = 'analyse_daj';
            $sql = "UPDATE dossiers SET statut = ?, date_modification = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nouveau_statut, $dossier_id]);

            // Ajouter dans l'historique
            $sql = "INSERT INTO historique_dossier (dossier_id, user_id, action, commentaire, date_action)
                    VALUES (?, ?, 'demande_modification_visa', ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $dossier_id,
                $_SESSION['user_id'],
                'Demande de modification par Chef Service SDTD : ' . $observations
            ]);

            $message = 'Demande de modification enregistrée. Le dossier retourne à la commission.';
            $type = 'info';
        }

        $pdo->commit();
        redirect(url('modules/dossiers/viser_inspections.php'), $message, $type);

    } catch (Exception $e) {
        $pdo->rollBack();
        redirect(url('modules/dossiers/apposer_visa.php?id=' . $dossier_id),
            'Erreur lors de l\'enregistrement du visa : ' . $e->getMessage(), 'error');
    }
}

$page_title = 'Apposer visa - ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?php echo url('dashboard.php'); ?>">
                            <i class="fas fa-home"></i> Tableau de bord
                        </a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="<?php echo url('modules/dossiers/viser_inspections.php'); ?>">
                            <i class="fas fa-stamp"></i> Viser les dossiers
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Apposer visa - <?php echo sanitize($dossier['numero']); ?>
                    </li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <!-- Informations du dossier -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-folder-open"></i>
                        Informations du dossier
                    </h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Numéro :</dt>
                        <dd class="col-sm-7">
                            <strong class="text-primary"><?php echo sanitize($dossier['numero']); ?></strong>
                        </dd>

                        <dt class="col-sm-5">Type :</dt>
                        <dd class="col-sm-7">
                            <?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?>
                        </dd>

                        <dt class="col-sm-5">Demandeur :</dt>
                        <dd class="col-sm-7">
                            <strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong>
                        </dd>

                        <dt class="col-sm-5">Localisation :</dt>
                        <dd class="col-sm-7">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo sanitize($dossier['ville']); ?>
                        </dd>

                        <dt class="col-sm-5">Statut :</dt>
                        <dd class="col-sm-7">
                            <span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                                <?php echo getStatutLabel($dossier['statut']); ?>
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>

            <!-- Informations de l'inspection -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-clipboard-check"></i>
                        Inspection
                    </h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Conformité :</dt>
                        <dd class="col-sm-7">
                            <?php if ($inspection['conforme']): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle"></i> Conforme
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Non conforme
                                </span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-5">Date :</dt>
                        <dd class="col-sm-7">
                            <?php echo formatDate($inspection['date_inspection'], 'd/m/Y'); ?>
                        </dd>

                        <dt class="col-sm-5">Validée :</dt>
                        <dd class="col-sm-7">
                            <?php if ($inspection['valide_par_chef_commission']): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check"></i> Oui
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-clock"></i> En attente
                                </span>
                            <?php endif; ?>
                        </dd>

                        <?php if ($inspection['observations']): ?>
                        <dt class="col-sm-12 mt-2">Observations :</dt>
                        <dd class="col-sm-12">
                            <div class="alert alert-info mb-0">
                                <?php echo nl2br(sanitize($inspection['observations'])); ?>
                            </div>
                        </dd>
                        <?php endif; ?>
                    </dl>

                    <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>"
                       class="btn btn-outline-primary btn-sm w-100 mt-3"
                       target="_blank">
                        <i class="fas fa-eye"></i> Voir le dossier complet
                    </a>
                </div>
            </div>
        </div>

        <!-- Formulaire de visa -->
        <div class="col-md-8">
            <div class="card border-warning">
                <div class="card-header bg-warning text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-stamp"></i>
                        Apposer votre visa - Chef Service SDTD (Niveau 1/3)
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Important :</strong> En apposant votre visa, vous validez le rapport d'inspection
                        et autorisez la transmission du dossier au Sous-Directeur SDTD pour le visa de niveau 2/3.
                    </div>

                    <form method="POST" id="visaForm">
                        <!-- Action -->
                        <div class="mb-4">
                            <label class="form-label">
                                <strong>Décision <span class="text-danger">*</span></strong>
                            </label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check card">
                                        <div class="card-body">
                                            <input class="form-check-input" type="radio" name="action" id="action_approuve" value="approuve" required>
                                            <label class="form-check-label ms-2" for="action_approuve">
                                                <i class="fas fa-check-circle text-success"></i>
                                                <strong>Approuver</strong>
                                                <br><small class="text-muted">Transmettre au Sous-Directeur</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check card">
                                        <div class="card-body">
                                            <input class="form-check-input" type="radio" name="action" id="action_modification" value="demande_modification">
                                            <label class="form-check-label ms-2" for="action_modification">
                                                <i class="fas fa-edit text-warning"></i>
                                                <strong>Demander modification</strong>
                                                <br><small class="text-muted">Retour à la commission</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check card">
                                        <div class="card-body">
                                            <input class="form-check-input" type="radio" name="action" id="action_rejete" value="rejete">
                                            <label class="form-check-label ms-2" for="action_rejete">
                                                <i class="fas fa-times-circle text-danger"></i>
                                                <strong>Rejeter</strong>
                                                <br><small class="text-muted">Clôturer négativement</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Observations -->
                        <div class="mb-4">
                            <label for="observations" class="form-label">
                                <strong>Observations</strong>
                                <small class="text-muted">(Obligatoire en cas de rejet ou demande de modification)</small>
                            </label>
                            <textarea class="form-control" id="observations" name="observations" rows="5"
                                      placeholder="Indiquez vos observations, remarques ou justifications..."></textarea>
                        </div>

                        <!-- Confirmation -->
                        <div class="mb-4">
                            <div class="alert alert-light border">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirmation" required>
                                    <label class="form-check-label" for="confirmation">
                                        <strong>Je confirme avoir examiné le dossier et le rapport d'inspection, et j'appose mon visa en tant que Chef Service SDTD.</strong>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Boutons -->
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo url('modules/dossiers/viser_inspections.php'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                            <button type="submit" class="btn btn-warning btn-lg">
                                <i class="fas fa-stamp"></i> Apposer mon visa
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Aide -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-question-circle"></i> Aide - Circuit de visa
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">
                                <i class="fas fa-route"></i> Parcours du dossier
                            </h6>
                            <ol class="small">
                                <li><del>Inspection terrain (Cadre DPPG)</del></li>
                                <li><del>Validation inspection (Chef Commission)</del></li>
                                <li><strong class="text-warning">→ Visa Chef Service (Vous êtes ici)</strong></li>
                                <li>Visa Sous-Directeur SDTD (Niveau 2/3)</li>
                                <li>Visa Directeur DPPG (Niveau 3/3)</li>
                                <li>Décision ministérielle</li>
                            </ol>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success">
                                <i class="fas fa-lightbulb"></i> Conseils
                            </h6>
                            <ul class="small mb-0">
                                <li>Vérifiez attentivement le rapport d'inspection</li>
                                <li>Consultez les documents uploadés</li>
                                <li>En cas de doute, demandez une modification</li>
                                <li>Vos observations seront visibles par les niveaux suivants</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validation du formulaire
document.getElementById('visaForm').addEventListener('submit', function(e) {
    const action = document.querySelector('input[name="action"]:checked');
    const observations = document.getElementById('observations').value.trim();
    const confirmation = document.getElementById('confirmation').checked;

    if (!action) {
        e.preventDefault();
        alert('Veuillez sélectionner une décision (Approuver, Demander modification ou Rejeter)');
        return false;
    }

    if (!confirmation) {
        e.preventDefault();
        alert('Veuillez confirmer que vous avez examiné le dossier');
        return false;
    }

    // Observations obligatoires pour rejet ou modification
    if ((action.value === 'rejete' || action.value === 'demande_modification') && !observations) {
        e.preventDefault();
        alert('Veuillez indiquer vos observations pour justifier votre décision');
        document.getElementById('observations').focus();
        return false;
    }

    // Confirmation finale
    const actionText = action.value === 'approuve' ? 'approuver et transmettre au Sous-Directeur' :
                      action.value === 'rejete' ? 'rejeter' : 'demander une modification pour';

    if (!confirm('Confirmez-vous vouloir ' + actionText + ' ce dossier ?\n\nCette action est irréversible.')) {
        e.preventDefault();
        return false;
    }

    return true;
});

// Rendre observations obligatoires si rejet ou modification
document.querySelectorAll('input[name="action"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const observations = document.getElementById('observations');
        if (this.value === 'rejete' || this.value === 'demande_modification') {
            observations.required = true;
            observations.parentElement.querySelector('label').innerHTML =
                '<strong>Observations <span class="text-danger">*</span></strong> <small class="text-muted">(Obligatoire pour cette action)</small>';
        } else {
            observations.required = false;
            observations.parentElement.querySelector('label').innerHTML =
                '<strong>Observations</strong> <small class="text-muted">(Facultatif)</small>';
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

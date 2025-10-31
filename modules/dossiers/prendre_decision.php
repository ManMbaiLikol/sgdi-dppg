<?php
// Prendre décision ministérielle - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

requireRole('ministre');

$dossier_id = $_GET['id'] ?? null;

if (!$dossier_id || !is_numeric($dossier_id)) {
    redirect(url('modules/dossiers/decision_ministre.php'), 'Dossier non spécifié', 'error');
}

// Récupérer les détails du dossier
$dossier = getDossierDetails($dossier_id);

if (!$dossier) {
    redirect(url('modules/dossiers/decision_ministre.php'), 'Dossier non trouvé', 'error');
}

// Vérifier que le dossier est bien au statut 'visa_directeur'
if ($dossier['statut'] !== 'visa_directeur') {
    redirect(url('modules/dossiers/decision_ministre.php'),
        'Ce dossier n\'est pas au statut "visa directeur"', 'error');
}

// Récupérer l'inspection
$sql = "SELECT * FROM inspections WHERE dossier_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id]);
$inspection = $stmt->fetch();

// Récupérer tous les visas
$sql = "SELECT v.*, u.nom, u.prenom, u.email, v.role
        FROM visas v
        JOIN users u ON v.user_id = u.id
        WHERE v.dossier_id = ?
        ORDER BY v.date_visa ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id]);
$visas = $stmt->fetchAll();

// Organiser les visas par rôle
$visas_par_role = [];
foreach ($visas as $visa) {
    $visas_par_role[$visa['role']] = $visa;
}

// Vérifier qu'il n'y a pas déjà une décision ministérielle
$sql = "SELECT * FROM decisions_ministerielle WHERE dossier_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id]);
$decision_existante = $stmt->fetch();

if ($decision_existante) {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
        'Une décision ministérielle a déjà été prise pour ce dossier', 'info');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $decision = sanitize($_POST['decision'] ?? '');
        $observations = sanitize($_POST['observations'] ?? '');
        $numero_arrete = sanitize($_POST['numero_arrete'] ?? '');

        if (!in_array($decision, ['approuve', 'refuse', 'ajourne'])) {
            throw new Exception('Décision invalide');
        }

        if (empty($numero_arrete)) {
            throw new Exception('Le numéro d\'arrêté est obligatoire');
        }

        // Insérer la décision ministérielle
        $sql = "INSERT INTO decisions_ministerielle (dossier_id, user_id, decision, numero_arrete, observations, date_decision)
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dossier_id, $_SESSION['user_id'], $decision, $numero_arrete, $observations]);

        $decision_id = $pdo->lastInsertId();

        // Mettre à jour le statut du dossier
        $nouveau_statut = '';
        if ($decision === 'approuve') {
            $nouveau_statut = 'approuve';
            $commentaire = 'Décision ministérielle : APPROUVÉ - Arrêté n° ' . $numero_arrete;
        } elseif ($decision === 'refuse') {
            $nouveau_statut = 'refuse';
            $commentaire = 'Décision ministérielle : REFUSÉ - Arrêté n° ' . $numero_arrete;
        } else { // ajourne
            $nouveau_statut = 'ajourne';
            $commentaire = 'Décision ministérielle : AJOURNÉ - Arrêté n° ' . $numero_arrete;
        }

        $sql = "UPDATE dossiers SET statut = ?, date_modification = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nouveau_statut, $dossier_id]);

        // Ajouter dans l'historique
        $sql = "INSERT INTO historique_dossier (dossier_id, user_id, action, commentaire, date_action)
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $dossier_id,
            $_SESSION['user_id'],
            'decision_ministerielle',
            $commentaire . ($observations ? ' - ' . $observations : '')
        ]);

        // Publier au registre public si approuvé
        if ($decision === 'approuve') {
            $sql = "INSERT INTO registre_public (
                        dossier_id,
                        numero_dossier,
                        type_infrastructure,
                        sous_type,
                        nom_demandeur,
                        ville,
                        quartier,
                        decision,
                        numero_arrete,
                        date_decision,
                        date_publication
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $dossier_id,
                $dossier['numero'],
                $dossier['type_infrastructure'],
                $dossier['sous_type'],
                $dossier['nom_demandeur'],
                $dossier['ville'],
                $dossier['quartier'] ?? null,
                'approuve',
                $numero_arrete
            ]);
        }

        $pdo->commit();

        $message = 'La décision ministérielle a été enregistrée avec succès.';
        if ($decision === 'approuve') {
            $message .= ' Le dossier a été automatiquement publié au registre public.';
        }

        redirect(url('modules/dossiers/decision_ministre.php'), $message, 'success');

    } catch (Exception $e) {
        $pdo->rollBack();
        redirect(url('modules/dossiers/prendre_decision.php?id=' . $dossier_id),
            'Erreur lors de l\'enregistrement de la décision : ' . $e->getMessage(), 'error');
    }
}

$page_title = 'Décision ministérielle - ' . $dossier['numero'];
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
                        <a href="<?php echo url('modules/dossiers/decision_ministre.php'); ?>">
                            <i class="fas fa-gavel"></i> Décisions ministérielles
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        Décider - <?php echo sanitize($dossier['numero']); ?>
                    </li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <!-- Colonne gauche : Informations -->
        <div class="col-md-4">
            <!-- Informations du dossier -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-folder-open"></i>
                        Dossier
                    </h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5">Numéro :</dt>
                        <dd class="col-sm-7">
                            <strong><?php echo sanitize($dossier['numero']); ?></strong>
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
                            <?php echo sanitize($dossier['ville']); ?>
                            <?php if ($dossier['quartier']): ?>
                                <br><small><?php echo sanitize($dossier['quartier']); ?></small>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>

            <!-- Circuit de visa -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-check-circle"></i>
                        Circuit de visa (3/3 validés)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach (['chef_service' => 'Chef Service SDTD', 'sous_directeur' => 'Sous-Directeur SDTD', 'directeur' => 'Directeur DPPG'] as $role => $label): ?>
                            <?php if (isset($visas_par_role[$role])): ?>
                                <?php $visa = $visas_par_role[$role]; ?>
                                <div class="mb-3">
                                    <div class="d-flex align-items-start">
                                        <div class="me-2">
                                            <i class="fas fa-check-circle text-success"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <strong class="d-block"><?php echo $label; ?></strong>
                                            <small class="text-muted">
                                                <?php echo sanitize($visa['prenom'] . ' ' . $visa['nom']); ?>
                                                <br><?php echo formatDate($visa['date_visa'], 'd/m/Y H:i'); ?>
                                            </small>
                                            <?php if ($visa['observations']): ?>
                                                <div class="alert alert-info alert-sm mt-2 mb-0">
                                                    <small><?php echo nl2br(sanitize($visa['observations'])); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>"
                       class="btn btn-outline-primary btn-sm w-100 mt-3"
                       target="_blank">
                        <i class="fas fa-eye"></i> Voir le dossier complet
                    </a>
                </div>
            </div>

            <!-- Inspection -->
            <?php if ($inspection): ?>
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-clipboard-check"></i>
                        Inspection
                    </h5>
                </div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Résultat :</dt>
                        <dd class="col-sm-7">
                            <?php if ($inspection['conforme']): ?>
                                <span class="badge bg-success">Conforme</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Non conforme</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-5">Date :</dt>
                        <dd class="col-sm-7">
                            <?php echo formatDate($inspection['date_inspection'], 'd/m/Y'); ?>
                        </dd>
                    </dl>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Colonne droite : Formulaire de décision -->
        <div class="col-md-8">
            <div class="card border-dark">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-gavel"></i>
                        Décision ministérielle finale
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-dark">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Attention :</strong> Cette décision est définitive et irréversible.
                        Les dossiers approuvés seront automatiquement publiés au registre public.
                    </div>

                    <form method="POST" id="decisionForm">
                        <!-- Numéro d'arrêté -->
                        <div class="mb-4">
                            <label for="numero_arrete" class="form-label">
                                <strong>Numéro d'arrêté ministériel <span class="text-danger">*</span></strong>
                            </label>
                            <input type="text"
                                   class="form-control form-control-lg"
                                   id="numero_arrete"
                                   name="numero_arrete"
                                   placeholder="Ex: N°0123/MINEE/DPPG/2025"
                                   required>
                            <small class="text-muted">Format officiel requis pour la publication</small>
                        </div>

                        <!-- Décision -->
                        <div class="mb-4">
                            <label class="form-label">
                                <strong>Décision <span class="text-danger">*</span></strong>
                            </label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check card">
                                        <div class="card-body">
                                            <input class="form-check-input" type="radio" name="decision" id="decision_approuve" value="approuve" required>
                                            <label class="form-check-label ms-2" for="decision_approuve">
                                                <i class="fas fa-check-circle text-success"></i>
                                                <strong>Approuver</strong>
                                                <br><small class="text-muted">Autorisation accordée</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check card">
                                        <div class="card-body">
                                            <input class="form-check-input" type="radio" name="decision" id="decision_refuse" value="refuse">
                                            <label class="form-check-label ms-2" for="decision_refuse">
                                                <i class="fas fa-times-circle text-danger"></i>
                                                <strong>Refuser</strong>
                                                <br><small class="text-muted">Demande rejetée</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check card">
                                        <div class="card-body">
                                            <input class="form-check-input" type="radio" name="decision" id="decision_ajourne" value="ajourne">
                                            <label class="form-check-label ms-2" for="decision_ajourne">
                                                <i class="fas fa-pause-circle text-warning"></i>
                                                <strong>Ajourner</strong>
                                                <br><small class="text-muted">Complément requis</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Observations -->
                        <div class="mb-4">
                            <label for="observations" class="form-label">
                                <strong>Observations et motifs</strong>
                                <small class="text-muted">(Obligatoire en cas de refus ou ajournement)</small>
                            </label>
                            <textarea class="form-control" id="observations" name="observations" rows="6"
                                      placeholder="Indiquez les observations, motifs ou conditions de la décision ministérielle..."></textarea>
                            <small class="text-muted">Ces observations seront publiées au registre public en cas d'approbation.</small>
                        </div>

                        <!-- Confirmation -->
                        <div class="mb-4">
                            <div class="alert alert-light border">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirmation" required>
                                    <label class="form-check-label" for="confirmation">
                                        <strong>Je confirme avoir examiné l'intégralité du dossier et je prends cette décision ministérielle en connaissance de cause.</strong>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Boutons -->
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo url('modules/dossiers/decision_ministre.php'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                            <button type="submit" class="btn btn-dark btn-lg">
                                <i class="fas fa-gavel"></i> Prendre la décision
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Aide -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-question-circle"></i> Informations importantes
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">
                                <i class="fas fa-info-circle"></i> Effets de la décision
                            </h6>
                            <ul class="small mb-0">
                                <li><strong>Approuvé</strong> : Publication automatique au registre public</li>
                                <li><strong>Refusé</strong> : Clôture définitive du dossier</li>
                                <li><strong>Ajourné</strong> : Demande de compléments, dossier suspendu</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success">
                                <i class="fas fa-lightbulb"></i> Points de vigilance
                            </h6>
                            <ul class="small mb-0">
                                <li>Vérifier le numéro d'arrêté ministériel</li>
                                <li>Consulter tous les visas et observations</li>
                                <li>Justifier les refus et ajournements</li>
                                <li>Décision irréversible une fois enregistrée</li>
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
document.getElementById('decisionForm').addEventListener('submit', function(e) {
    const decision = document.querySelector('input[name="decision"]:checked');
    const observations = document.getElementById('observations').value.trim();
    const numero_arrete = document.getElementById('numero_arrete').value.trim();
    const confirmation = document.getElementById('confirmation').checked;

    if (!decision) {
        e.preventDefault();
        alert('Veuillez sélectionner une décision');
        return false;
    }

    if (!numero_arrete) {
        e.preventDefault();
        alert('Le numéro d\'arrêté ministériel est obligatoire');
        document.getElementById('numero_arrete').focus();
        return false;
    }

    if (!confirmation) {
        e.preventDefault();
        alert('Veuillez confirmer que vous avez examiné le dossier');
        return false;
    }

    // Observations obligatoires pour refus ou ajournement
    if ((decision.value === 'refuse' || decision.value === 'ajourne') && !observations) {
        e.preventDefault();
        alert('Veuillez indiquer les motifs de votre décision');
        document.getElementById('observations').focus();
        return false;
    }

    // Confirmation finale
    const decisionText = decision.value === 'approuve' ? 'APPROUVER' :
                        decision.value === 'refuse' ? 'REFUSER' : 'AJOURNER';

    if (!confirm('ATTENTION : Vous allez ' + decisionText + ' définitivement ce dossier.\n\nArrêté : ' + numero_arrete + '\n\nCette décision est IRRÉVERSIBLE.\n\nConfirmez-vous ?')) {
        e.preventDefault();
        return false;
    }

    return true;
});

// Rendre observations obligatoires si refus ou ajournement
document.querySelectorAll('input[name="decision"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const observations = document.getElementById('observations');
        if (this.value === 'refuse' || this.value === 'ajourne') {
            observations.required = true;
            observations.parentElement.querySelector('label').innerHTML =
                '<strong>Observations et motifs <span class="text-danger">*</span></strong> <small class="text-muted">(Obligatoire pour cette décision)</small>';
        } else {
            observations.required = false;
            observations.parentElement.querySelector('label').innerHTML =
                '<strong>Observations et motifs</strong> <small class="text-muted">(Facultatif mais recommandé)</small>';
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

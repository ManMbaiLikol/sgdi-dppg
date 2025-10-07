<?php
// Gestion du statut opérationnel des infrastructures - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

// Vérifier les permissions (seulement chef de service)
if ($_SESSION['user_role'] !== 'chef_service') {
    redirect(url('dashboard.php'), 'Accès non autorisé - Seul le chef de service peut gérer le statut opérationnel', 'error');
}

$dossier_id = $_GET['id'] ?? $_POST['dossier_id'] ?? null;

if (!$dossier_id || !is_numeric($dossier_id)) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

// Récupérer le dossier
$dossier = getDossierById($dossier_id);

if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non trouvé', 'error');
}

// Vérifier que le dossier est au statut "autorise"
if ($dossier['statut'] !== 'autorise') {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
             'Seules les infrastructures autorisées peuvent avoir leur statut opérationnel modifié', 'error');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect(url('modules/dossiers/view.php?id=' . $dossier_id), 'Token CSRF invalide', 'error');
    }

    $nouveau_statut = sanitize($_POST['nouveau_statut'] ?? '');
    $motif = sanitize($_POST['motif'] ?? '');
    $date_fermeture = !empty($_POST['date_fermeture']) ? $_POST['date_fermeture'] : null;
    $date_reouverture = !empty($_POST['date_reouverture']) ? $_POST['date_reouverture'] : null;

    // Validation
    $errors = [];
    if (empty($nouveau_statut)) {
        $errors[] = 'Le nouveau statut est requis';
    }
    if (empty($motif)) {
        $errors[] = 'Le motif est requis';
    }
    if (in_array($nouveau_statut, ['ferme_temporaire', 'ferme_definitif', 'demantele']) && empty($date_fermeture)) {
        $errors[] = 'La date de fermeture est requise pour ce statut';
    }

    if (empty($errors)) {
        try {
            $success = changerStatutOperationnel(
                $dossier_id,
                $nouveau_statut,
                $motif,
                $_SESSION['user_id'],
                $date_fermeture,
                $date_reouverture
            );

            if ($success) {
                redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
                         'Le statut opérationnel a été mis à jour avec succès', 'success');
            } else {
                $errors[] = 'Erreur lors de la mise à jour du statut opérationnel';
            }
        } catch (Exception $e) {
            $errors[] = 'Erreur: ' . $e->getMessage();
        }
    }
}

$page_title = 'Gestion opérationnelle - ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-cogs"></i>
                        Gestion opérationnelle
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?php echo url('dashboard.php'); ?>">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo url('modules/dossiers/list.php'); ?>">Dossiers</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>">Dossier <?php echo htmlspecialchars($dossier['numero']); ?></a></li>
                            <li class="breadcrumb-item active">Gestion opérationnelle</li>
                        </ol>
                    </nav>
                </div>
                <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>

            <!-- Messages d'erreur -->
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Informations du dossier -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle"></i>
                                Modification du statut opérationnel
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Information :</strong> Cette action modifiera le statut opérationnel de l'infrastructure
                                et impactera les statistiques du système.
                            </div>

                            <div class="table-responsive mb-4">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="text-muted" style="width: 30%;">Numéro de dossier :</td>
                                        <td><strong><?php echo htmlspecialchars($dossier['numero']); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Type d'infrastructure :</td>
                                        <td><?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Opérateur/Demandeur :</td>
                                        <td><?php echo htmlspecialchars($dossier['nom_demandeur']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Statut administratif :</td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                                                <?php echo getStatutLabel($dossier['statut']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Statut opérationnel actuel :</td>
                                        <td>
                                            <?php
                                            $statut_op = $dossier['statut_operationnel'] ?: 'operationnel';
                                            ?>
                                            <span class="badge bg-<?php echo getStatutOperationnelClass($statut_op); ?>">
                                                <i class="<?php echo getStatutOperationnelIcon($statut_op); ?>"></i>
                                                <?php echo getStatutOperationnelLabel($statut_op); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="dossier_id" value="<?php echo $dossier_id; ?>">

                                <div class="mb-3">
                                    <label for="nouveau_statut" class="form-label">
                                        <i class="fas fa-exchange-alt"></i>
                                        Nouveau statut opérationnel <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="nouveau_statut" name="nouveau_statut" required onchange="toggleDateFields()">
                                        <option value="">Sélectionnez un statut</option>
                                        <option value="operationnel">Opérationnel</option>
                                        <option value="ferme_temporaire">Fermé temporairement</option>
                                        <option value="ferme_definitif">Fermé définitivement</option>
                                        <option value="demantele">Démantelé</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="motif" class="form-label">
                                        <i class="fas fa-comment"></i>
                                        Motif du changement <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control" id="motif" name="motif" rows="3" required
                                              placeholder="Expliquez la raison de ce changement de statut (maintenance, cessation d'activité, démantèlement, etc.)"></textarea>
                                </div>

                                <div id="date-fields" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="date_fermeture" class="form-label">
                                                    <i class="fas fa-calendar-times"></i>
                                                    Date de fermeture
                                                </label>
                                                <input type="date" class="form-control" id="date_fermeture" name="date_fermeture">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="date_reouverture" class="form-label">
                                                    <i class="fas fa-calendar-check"></i>
                                                    Date de réouverture prévue <small class="text-muted">(si applicable)</small>
                                                </label>
                                                <input type="date" class="form-control" id="date_reouverture" name="date_reouverture">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Annuler
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Mettre à jour le statut opérationnel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-info-circle"></i> Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="small">
                                <p class="mb-2">
                                    <i class="fas fa-user"></i>
                                    <strong>Action effectuée par :</strong><br>
                                    <?php echo htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?><br>
                                    <span class="text-muted"><?php echo getRoleLabel($_SESSION['user_role']); ?></span>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-clock"></i>
                                    <strong>Date/Heure :</strong><br>
                                    <?php echo date('d/m/Y à H:i'); ?>
                                </p>
                                <hr>
                                <p class="mb-2">
                                    <strong>Types de statut :</strong><br>
                                    <span class="badge bg-success me-1">Opérationnel</span> Infrastructure en service<br>
                                    <span class="badge bg-warning me-1">Fermé temporaire</span> Maintenance, réparations<br>
                                    <span class="badge bg-danger me-1">Fermé définitif</span> Cessation d'activité<br>
                                    <span class="badge bg-dark me-1">Démantelé</span> Infrastructure supprimée
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDateFields() {
    const statutSelect = document.getElementById('nouveau_statut');
    const dateFields = document.getElementById('date-fields');
    const dateFermeture = document.getElementById('date_fermeture');

    if (['ferme_temporaire', 'ferme_definitif', 'demantele'].includes(statutSelect.value)) {
        dateFields.style.display = 'block';
        dateFermeture.required = true;
    } else {
        dateFields.style.display = 'none';
        dateFermeture.required = false;
        dateFermeture.value = '';
        document.getElementById('date_reouverture').value = '';
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
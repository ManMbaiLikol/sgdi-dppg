<?php
// Marquer un dossier comme autorisé - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

// Vérifier les permissions (seulement chef de service)
if ($_SESSION['user_role'] !== 'chef_service') {
    redirect(url('dashboard.php'), 'Accès non autorisé - Seul le chef de service peut marquer les dossiers comme autorisés', 'error');
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

// Vérifier que le dossier est au statut "decide"
if ($dossier['statut'] !== 'decide') {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
             'Ce dossier ne peut être marqué comme autorisé que s\'il est au statut "Décidé"', 'error');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirect(url('modules/dossiers/view.php?id=' . $dossier_id), 'Token CSRF invalide', 'error');
    }

    $commentaire = sanitize($_POST['commentaire'] ?? '');

    try {
        // Changer le statut vers "autorise"
        $success = changerStatutDossier(
            $dossier_id,
            'autorise',
            $_SESSION['user_id'],
            'Dossier marqué comme autorisé par le ministre. ' . $commentaire
        );

        if ($success) {
            redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
                     'Le dossier a été marqué comme autorisé avec succès', 'success');
        } else {
            redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
                     'Erreur lors de la mise à jour du statut', 'error');
        }
    } catch (Exception $e) {
        redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
                 'Erreur: ' . $e->getMessage(), 'error');
    }
}

$page_title = 'Marquer comme autorisé - ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-check-circle"></i>
                        Marquer comme autorisé
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?php echo url('dashboard.php'); ?>">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo url('modules/dossiers/list.php'); ?>">Dossiers</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>">Dossier <?php echo htmlspecialchars($dossier['numero']); ?></a></li>
                            <li class="breadcrumb-item active">Marquer autorisé</li>
                        </ol>
                    </nav>
                </div>
                <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>

            <!-- Informations du dossier -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-exclamation-triangle text-warning"></i>
                                Confirmation d'autorisation
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Attention :</strong> En tant que Chef de Service SDTD, vous êtes sur le point de marquer ce dossier comme "Autorisé",
                                indiquant que le ministre a signé l'autorisation. Cette action modifiera définitivement le statut du dossier.
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
                                        <td class="text-muted">Demandeur :</td>
                                        <td><?php echo htmlspecialchars($dossier['nom_demandeur']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Statut actuel :</td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                                                <?php echo getStatutLabel($dossier['statut']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Nouveau statut :</td>
                                        <td>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle"></i> Autorisé
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <form method="POST" action="" onsubmit="return confirm('Êtes-vous sûr de vouloir marquer ce dossier comme autorisé ?');">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="dossier_id" value="<?php echo $dossier_id; ?>">

                                <div class="mb-3">
                                    <label for="commentaire" class="form-label">
                                        <i class="fas fa-comment"></i>
                                        Commentaire additionnel <small class="text-muted">(optionnel)</small>
                                    </label>
                                    <textarea class="form-control" id="commentaire" name="commentaire" rows="3"
                                              placeholder="Ajoutez un commentaire sur cette autorisation (référence de signature, date, etc.)"></textarea>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Annuler
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check-circle"></i> Marquer comme autorisé
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
                                <p class="mb-0">
                                    <i class="fas fa-shield-alt"></i>
                                    <strong>Action réservée :</strong><br>
                                    Seul le Chef de Service SDTD peut marquer les dossiers comme "Autorisés"
                                    suite à la signature ministérielle.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
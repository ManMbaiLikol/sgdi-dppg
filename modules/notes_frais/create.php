<?php
// Création d'une note de frais - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

// Seuls le chef service et l'admin peuvent créer des notes de frais
requireAnyRole(['chef_service', 'admin']);

$page_title = 'Créer une note de frais';
$errors = [];
$success = false;

// Récupérer le dossier_id s'il est passé en paramètre
$dossier_id_preselect = intval($_GET['dossier_id'] ?? 0);

// Récupérer les dossiers éligibles
$dossiers_eligibles = getDossiersEligiblesNoteFrais();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $dossier_id = intval($_POST['dossier_id'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');
        $montant_base = floatval($_POST['montant_base'] ?? 0);

        // Validation
        if (!$dossier_id) {
            $errors[] = 'Veuillez sélectionner un dossier';
        }

        if (empty($description)) {
            $errors[] = 'La description est requise';
        }

        if ($montant_base <= 0) {
            $errors[] = 'Le montant de base doit être supérieur à 0';
        }

        if (empty($errors)) {
            $montant_total = $montant_base;

            $data = [
                'dossier_id' => $dossier_id,
                'description' => $description,
                'montant_base' => $montant_base,
                'montant_frais_deplacement' => 0,
                'montant_total' => $montant_total,
                'user_id' => $_SESSION['user_id']
            ];

            $note_id = creerNoteFrais($data);
            if ($note_id) {
                redirect(url('modules/notes_frais/view.php?id=' . $note_id), 'Note de frais créée avec succès', 'success');
            } else {
                $errors[] = 'Erreur lors de la création de la note de frais';
            }
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            <p class="text-muted">Créer une nouvelle note de frais d'inspection</p>
        </div>
        <div>
            <a href="<?php echo url('modules/notes_frais/list.php'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Nouvelle note de frais</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h6><i class="fas fa-exclamation-triangle"></i> Erreurs détectées :</h6>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo sanitize($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($dossiers_eligibles)): ?>
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle"></i> Aucun dossier éligible</h6>
                            <p class="mb-2">Aucun dossier n'est actuellement éligible pour une note de frais.</p>
                            <p class="mb-0">Les dossiers doivent avoir une commission constituée et ne pas avoir déjà de note de frais.</p>
                        </div>
                        <div class="text-center">
                            <a href="<?php echo url('modules/notes_frais/list.php'); ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                        </div>
                    <?php else: ?>

                    <form method="POST" id="noteFreaisForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <!-- Sélection du dossier -->
                        <div class="mb-4">
                            <label for="dossier_id" class="form-label">Dossier concerné <span class="text-danger">*</span></label>
                            <select class="form-select" id="dossier_id" name="dossier_id" required onchange="updateDossierInfo()">
                                <option value="">Sélectionner un dossier</option>
                                <?php foreach ($dossiers_eligibles as $dossier):
                                    // Pré-sélectionner si dossier_id passé en GET ou POST
                                    $selected_dossier_id = intval($_POST['dossier_id'] ?? $dossier_id_preselect);
                                    $is_selected = ($selected_dossier_id === intval($dossier['id']));
                                ?>
                                <option value="<?php echo $dossier['id']; ?>"
                                        data-demandeur="<?php echo sanitize($dossier['nom_demandeur']); ?>"
                                        data-type="<?php echo sanitize($dossier['type_infrastructure']); ?>"
                                        data-region="<?php echo sanitize($dossier['region']); ?>"
                                        <?php echo $is_selected ? 'selected' : ''; ?>>
                                    <?php echo sanitize($dossier['numero'] . ' - ' . $dossier['nom_demandeur']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="dossierInfo" class="form-text mt-2"></div>
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3" required
                                      placeholder="Décrivez les frais à facturer..."><?php echo sanitize($_POST['description'] ?? ''); ?></textarea>
                        </div>

                        <!-- Montant -->
                        <div class="mb-4">
                            <label for="montant_base" class="form-label">Montant total <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="montant_base" name="montant_base"
                                       step="0.01" min="0" required
                                       value="<?php echo sanitize($_POST['montant_base'] ?? ''); ?>">
                                <span class="input-group-text">F CFA</span>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Information :</h6>
                            <p class="mb-0">Cette note de frais sera créée avec le statut "En attente" et devra être validée avant paiement.</p>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?php echo url('modules/notes_frais/list.php'); ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Créer la note de frais
                            </button>
                        </div>
                    </form>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateDossierInfo() {
    const select = document.getElementById('dossier_id');
    const infoDiv = document.getElementById('dossierInfo');

    if (select.value) {
        const option = select.options[select.selectedIndex];
        const demandeur = option.getAttribute('data-demandeur');
        const type = option.getAttribute('data-type');
        const region = option.getAttribute('data-region');

        infoDiv.innerHTML = `
            <div class="d-flex justify-content-between">
                <span><strong>Demandeur:</strong> ${demandeur}</span>
                <span><strong>Type:</strong> ${type}</span>
                <span><strong>Région:</strong> ${region}</span>
            </div>
        `;
    } else {
        infoDiv.innerHTML = '';
    }
}

// Appeler updateDossierInfo au chargement si un dossier est pré-sélectionné
document.addEventListener('DOMContentLoaded', function() {
    updateDossierInfo();
});
</script>

<?php require_once '../../includes/footer.php'; ?>
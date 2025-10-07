<?php
// Modification d'une note de frais - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

$note_id = intval($_GET['id'] ?? 0);
if (!$note_id) {
    redirect(url('modules/notes_frais/list.php'), 'Note de frais non spécifiée', 'error');
}

$note = getNoteFreaisById($note_id);
if (!$note) {
    redirect(url('modules/notes_frais/list.php'), 'Note de frais introuvable', 'error');
}

if (!peutModifierNoteFrais($note, $_SESSION['user_role'], $_SESSION['user_id'])) {
    redirect(url('modules/notes_frais/view.php?id=' . $note_id), 'Vous n\'avez pas les permissions pour modifier cette note', 'error');
}

$page_title = 'Modifier la note de frais #' . $note['id'];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $data = [];

        // Modification du statut (pour billeteur)
        if ($_SESSION['user_role'] === 'billeteur' && isset($_POST['statut'])) {
            $statut = sanitize($_POST['statut'] ?? '');
            if (in_array($statut, ['payee'])) {
                $data['statut'] = $statut;
            }
        }

        // Modification des détails (pour chef service/admin)
        if (hasAnyRole(['chef_service', 'admin']) && $note['statut'] !== 'payee') {
            if (isset($_POST['description'])) {
                $description = sanitize($_POST['description'] ?? '');
                if (!empty($description)) {
                    $data['description'] = $description;
                } else {
                    $errors[] = 'La description est requise';
                }
            }

            if (isset($_POST['montant_base'])) {
                $montant_base = floatval($_POST['montant_base'] ?? 0);
                if ($montant_base > 0) {
                    $data['montant_base'] = $montant_base;
                } else {
                    $errors[] = 'Le montant de base doit être supérieur à 0';
                }
            }

            // Recalculer le total si montant modifié
            if (isset($data['montant_base'])) {
                $data['montant_total'] = $data['montant_base'];
                $data['montant_frais_deplacement'] = 0;
            }

            if (isset($_POST['statut_modification'])) {
                $statut = sanitize($_POST['statut_modification'] ?? '');
                if (in_array($statut, ['en_attente', 'validee', 'annulee'])) {
                    $data['statut'] = $statut;
                }
            }
        }

        if (empty($errors) && !empty($data)) {
            if (mettreAJourNoteFrais($note_id, $data)) {
                // Si le statut est "annulée", rejeter le dossier associé
                if (isset($data['statut']) && $data['statut'] === 'annulee') {
                    require_once '../dossiers/functions.php';
                    changerStatutDossier($note['dossier_id'], 'rejete', $_SESSION['user_id'], 'Dossier rejeté suite à l\'annulation de la note de frais');
                }
                redirect(url('modules/notes_frais/view.php?id=' . $note_id), 'Note de frais modifiée avec succès', 'success');
            } else {
                $errors[] = 'Erreur lors de la modification de la note de frais';
            }
        } elseif (empty($data)) {
            $errors[] = 'Aucune modification détectée';
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
            <p class="text-muted">Modification de la note de frais</p>
        </div>
        <div>
            <a href="<?php echo url('modules/notes_frais/view.php?id=' . $note_id); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-edit"></i> Modifier la note de frais</h5>
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

                    <!-- Action rapide pour billeteur -->
                    <?php if ($_SESSION['user_role'] === 'billeteur' && $note['statut'] === 'validee'): ?>
                    <div class="alert alert-info mb-4">
                        <h6><i class="fas fa-money-bill"></i> Marquer comme payée</h6>
                        <p class="mb-3">Cette note de frais est validée et peut être marquée comme payée.</p>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="statut" value="payee">
                            <button type="submit" class="btn btn-success" onclick="return confirm('Confirmer le paiement de cette note de frais ?')">
                                <i class="fas fa-check"></i> Confirmer le paiement
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Formulaire de modification détaillée -->
                    <?php if (hasAnyRole(['chef_service', 'admin']) && $note['statut'] !== 'payee'): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <!-- Information du dossier (non modifiable) -->
                        <div class="alert alert-light">
                            <h6>Dossier concerné</h6>
                            <p class="mb-0">
                                <strong><?php echo sanitize($note['dossier_numero']); ?></strong> -
                                <?php echo sanitize($note['nom_demandeur']); ?>
                            </p>
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3" required><?php echo sanitize($note['description']); ?></textarea>
                        </div>

                        <!-- Montant -->
                        <div class="mb-4">
                            <label for="montant_base" class="form-label">Montant total <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="montant_base" name="montant_base"
                                       step="0.01" min="0" required
                                       value="<?php echo $note['montant_base']; ?>">
                                <span class="input-group-text">F CFA</span>
                            </div>
                        </div>

                        <!-- Modification du statut -->
                        <div class="mb-4">
                            <label for="statut_modification" class="form-label">Statut</label>
                            <select class="form-select" id="statut_modification" name="statut_modification">
                                <option value="en_attente" <?php echo $note['statut'] === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                <option value="validee" <?php echo $note['statut'] === 'validee' ? 'selected' : ''; ?>>Validée</option>
                                <option value="annulee" <?php echo $note['statut'] === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                            </select>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?php echo url('modules/notes_frais/view.php?id=' . $note_id); ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <p class="mb-0">
                            <?php if ($note['statut'] === 'payee'): ?>
                                Cette note de frais a été payée et ne peut plus être modifiée.
                            <?php else: ?>
                                Vous n'avez pas les permissions pour modifier cette note de frais.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>


<?php require_once '../../includes/footer.php'; ?>
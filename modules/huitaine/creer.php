<?php
// Créer une huitaine pour un dossier - SGDI
require_once '../../includes/auth.php';
require_once '../../includes/huitaine_functions.php';
require_once '../dossiers/functions.php';

requireLogin();

$dossier_id = intval($_GET['id'] ?? 0);

if (!$dossier_id) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

$dossier = getDossierById($dossier_id);

if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier introuvable', 'error');
}

// Vérifier les permissions
if (!hasAnyRole(['chef_service', 'admin', 'cadre_dppg', 'cadre_daj'])) {
    redirect(url('dashboard.php'), 'Accès non autorisé', 'error');
}

$errors = [];
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $type_irregularite = sanitize($_POST['type_irregularite'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        // Validation
        if (empty($type_irregularite)) {
            $errors[] = 'Le type d\'irrégularité est obligatoire';
        }

        if (empty($description)) {
            $errors[] = 'La description est obligatoire';
        }

        if (empty($errors)) {
            $result = creerHuitaine($dossier_id, $type_irregularite, $description, $_SESSION['user_id']);

            if ($result['success']) {
                redirect(
                    url('modules/dossiers/view.php?id=' . $dossier_id),
                    'Huitaine créée avec succès. Délai de régularisation : 8 jours ouvrables.',
                    'success'
                );
            } else {
                $errors[] = $result['error'];
            }
        }
    }
}

$page_title = 'Créer une huitaine - Dossier ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3">
                <i class="fas fa-exclamation-triangle text-warning"></i>
                Créer une huitaine de régularisation
            </h1>
            <p class="text-muted">
                Dossier: <strong><?php echo sanitize($dossier['numero']); ?></strong> -
                <?php echo sanitize($dossier['nom_demandeur']); ?>
            </p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
            <li><?php echo sanitize($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clock"></i> Informations sur la huitaine
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Qu'est-ce qu'une huitaine ?</h6>
                        <p class="mb-0">
                            La huitaine est un délai réglementaire de <strong>8 jours ouvrables</strong> accordé au demandeur
                            pour régulariser une irrégularité constatée dans son dossier.
                        </p>
                        <hr>
                        <ul class="mb-0">
                            <li><strong>J-2</strong> : Première alerte envoyée au demandeur</li>
                            <li><strong>J-1</strong> : Deuxième alerte (urgente)</li>
                            <li><strong>J</strong> : Alerte finale (dernier jour)</li>
                            <li><strong>Après J</strong> : Rejet automatique du dossier si non régularisé</li>
                        </ul>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="mb-3">
                            <label for="type_irregularite" class="form-label">
                                Type d'irrégularité *
                            </label>
                            <select class="form-select" id="type_irregularite" name="type_irregularite" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="document_manquant">Document manquant</option>
                                <option value="info_incomplete">Information incomplète</option>
                                <option value="non_conformite">Non-conformité technique</option>
                                <option value="paiement_partiel">Paiement partiel</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">
                                Description détaillée de l'irrégularité *
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="6" required
                                      placeholder="Décrivez précisément l'irrégularité constatée et les actions attendues du demandeur..."></textarea>
                            <small class="form-text text-muted">
                                Cette description sera envoyée au demandeur. Soyez précis et clair.
                            </small>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>"
                               class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-clock"></i> Créer la huitaine
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
                        <i class="fas fa-info-circle"></i> Informations du dossier
                    </h6>
                </div>
                <div class="card-body">
                    <p><strong>N° Dossier:</strong><br>
                        <code><?php echo sanitize($dossier['numero']); ?></code>
                    </p>

                    <p><strong>Demandeur:</strong><br>
                        <?php echo sanitize($dossier['nom_demandeur']); ?>
                    </p>

                    <p><strong>Type:</strong><br>
                        <?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?>
                    </p>

                    <p><strong>Statut actuel:</strong><br>
                        <span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                            <?php echo getStatutLabel($dossier['statut']); ?>
                        </span>
                    </p>

                    <p><strong>Huitaines précédentes:</strong><br>
                        <?php echo $dossier['nombre_huitaines']; ?>
                    </p>

                    <?php if ($dossier['derniere_regularisation']): ?>
                    <p><strong>Dernière régularisation:</strong><br>
                        <small><?php echo formatDateTime($dossier['derniere_regularisation']); ?></small>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header bg-light">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-calendar-alt"></i> Calcul automatique
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Date de début:</strong></p>
                    <p><?php echo date('d/m/Y'); ?> (Aujourd'hui)</p>

                    <p class="mb-1"><strong>Date limite:</strong></p>
                    <p class="text-danger">
                        <?php
                        $date_limite = new DateTime(calculerDateLimiteHuitaine());
                        echo $date_limite->format('d/m/Y à H:i');
                        ?>
                    </p>

                    <p class="mb-0">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Le calcul exclut les samedis et dimanches
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

<?php
// Régulariser une huitaine - SGDI
require_once '../../includes/auth.php';
require_once '../../includes/huitaine_functions.php';
require_once '../dossiers/functions.php';

requireLogin();

$huitaine_id = intval($_GET['id'] ?? 0);

if (!$huitaine_id) {
    redirect(url('modules/huitaine/list.php'), 'Huitaine non spécifiée', 'error');
}

// Récupérer la huitaine
$sql = "SELECT h.*, d.numero, d.nom_demandeur, d.type_infrastructure, d.sous_type
        FROM huitaine h
        INNER JOIN dossiers d ON h.dossier_id = d.id
        WHERE h.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$huitaine_id]);
$huitaine = $stmt->fetch();

if (!$huitaine) {
    redirect(url('modules/huitaine/list.php'), 'Huitaine introuvable', 'error');
}

// Vérifier les permissions
if (!hasAnyRole(['chef_service', 'admin', 'cadre_dppg', 'cadre_daj'])) {
    redirect(url('dashboard.php'), 'Accès non autorisé', 'error');
}

// Vérifier que la huitaine est en cours
if ($huitaine['statut'] !== 'en_cours') {
    redirect(
        url('modules/dossiers/view.php?id=' . $huitaine['dossier_id']),
        'Cette huitaine a déjà été traitée',
        'warning'
    );
}

$errors = [];
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $commentaire = sanitize($_POST['commentaire'] ?? '');

        // Validation
        if (empty($commentaire)) {
            $errors[] = 'Le commentaire de régularisation est obligatoire';
        }

        if (empty($errors)) {
            $result = regulariserHuitaine($huitaine_id, $commentaire, $_SESSION['user_id']);

            if ($result['success']) {
                redirect(
                    url('modules/dossiers/view.php?id=' . $huitaine['dossier_id']),
                    'Huitaine régularisée avec succès. Le dossier peut continuer son traitement.',
                    'success'
                );
            } else {
                $errors[] = $result['error'];
            }
        }
    }
}

// Calculer les jours restants
$date_limite = new DateTime($huitaine['date_limite']);
$maintenant = new DateTime();
$diff = $maintenant->diff($date_limite);
$jours_restants = $diff->days;
if ($maintenant > $date_limite) {
    $jours_restants = -$jours_restants;
}

$page_title = 'Régulariser une huitaine';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3">
                <i class="fas fa-check-circle text-success"></i>
                Régulariser une huitaine
            </h1>
            <p class="text-muted">
                Dossier: <strong><?php echo sanitize($huitaine['numero']); ?></strong> -
                <?php echo sanitize($huitaine['nom_demandeur']); ?>
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
            <!-- Détails de la huitaine -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-exclamation-triangle"></i> Détails de l'irrégularité
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Type d'irrégularité:</strong></p>
                            <p><?php echo ucfirst(str_replace('_', ' ', $huitaine['type_irregularite'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Date de création:</strong></p>
                            <p><?php echo formatDateTime($huitaine['date_debut']); ?></p>
                        </div>
                    </div>

                    <p class="mb-1"><strong>Description:</strong></p>
                    <div class="alert alert-light">
                        <?php echo nl2br(sanitize($huitaine['description'])); ?>
                    </div>
                </div>
            </div>

            <!-- Formulaire de régularisation -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-check"></i> Confirmer la régularisation
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <i class="fas fa-info-circle"></i>
                        <strong>Régularisation</strong><br>
                        En validant cette régularisation, vous confirmez que le demandeur a apporté les corrections
                        nécessaires. Le dossier reprendra son traitement normal.
                    </div>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="mb-3">
                            <label for="commentaire" class="form-label">
                                Commentaire de régularisation *
                            </label>
                            <textarea class="form-control" id="commentaire" name="commentaire" rows="6" required
                                      placeholder="Décrivez les actions correctives réalisées par le demandeur..."></textarea>
                            <small class="form-text text-muted">
                                Ce commentaire sera ajouté à l'historique du dossier.
                            </small>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?php echo url('modules/dossiers/view.php?id=' . $huitaine['dossier_id']); ?>"
                               class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check-circle"></i> Confirmer la régularisation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Compte à rebours -->
            <div class="card mb-3">
                <div class="card-header bg-<?php echo getHuitaineBadgeClass($jours_restants); ?> text-white">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-clock"></i> Compte à rebours
                    </h6>
                </div>
                <div class="card-body text-center">
                    <div class="display-4 mb-2 text-<?php echo getHuitaineBadgeClass($jours_restants); ?>">
                        <?php if ($jours_restants >= 0): ?>
                            <?php echo $jours_restants; ?>
                        <?php else: ?>
                            <i class="fas fa-exclamation-triangle"></i>
                        <?php endif; ?>
                    </div>
                    <p class="mb-0">
                        <?php echo formatCompteARebours($jours_restants, $diff->h); ?>
                    </p>
                    <hr>
                    <p class="mb-0">
                        <small class="text-muted">
                            Date limite:<br>
                            <strong><?php echo formatDateTime($huitaine['date_limite']); ?></strong>
                        </small>
                    </p>
                </div>
            </div>

            <!-- Alertes envoyées -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-bell"></i> Alertes envoyées
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Alerte J-2</span>
                        <?php if ($huitaine['alerte_j2_envoyee']): ?>
                            <span class="badge bg-success"><i class="fas fa-check"></i> Envoyée</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Non envoyée</span>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Alerte J-1</span>
                        <?php if ($huitaine['alerte_j1_envoyee']): ?>
                            <span class="badge bg-success"><i class="fas fa-check"></i> Envoyée</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Non envoyée</span>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <span>Alerte J (final)</span>
                        <?php if ($huitaine['alerte_j_envoyee']): ?>
                            <span class="badge bg-success"><i class="fas fa-check"></i> Envoyée</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Non envoyée</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Informations du dossier -->
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-folder"></i> Informations
                    </h6>
                </div>
                <div class="card-body">
                    <p><strong>Type:</strong><br>
                        <small><?php echo getTypeLabel($huitaine['type_infrastructure'], $huitaine['sous_type']); ?></small>
                    </p>

                    <p class="mb-0">
                        <a href="<?php echo url('modules/dossiers/view.php?id=' . $huitaine['dossier_id']); ?>"
                           class="btn btn-outline-primary btn-sm w-100">
                            <i class="fas fa-eye"></i> Voir le dossier complet
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

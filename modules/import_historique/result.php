<?php
// Résultats de l'import - SGDI
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

// Vérifier les permissions
if (!peutImporterHistorique($_SESSION['user_role'])) {
    redirect(url('dashboard.php'), 'Vous n\'avez pas les permissions pour accéder à ce module', 'error');
}

$success = intval($_GET['success'] ?? 0);
$errors = intval($_GET['errors'] ?? 0);
$total = $success + $errors;

$pageTitle = "Résultat de l'import";
include '../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <?php if ($success > 0): ?>
                <div class="card shadow border-success">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="fas fa-check-circle"></i> Import réussi !</h4>
                    </div>
                    <div class="card-body text-center">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <h2 class="text-success"><?= $success ?></h2>
                                <p>Dossiers importés</p>
                            </div>
                            <?php if ($errors > 0): ?>
                                <div class="col-md-4">
                                    <h2 class="text-danger"><?= $errors ?></h2>
                                    <p>Erreurs</p>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-4">
                                <h2 class="text-info"><?= $total ?></h2>
                                <p>Total traité</p>
                            </div>
                        </div>

                        <?php if ($errors > 0): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?= $errors ?> dossier(s) n'ont pas pu être importés. Vérifiez les données et réessayez.
                            </div>
                        <?php endif; ?>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Les dossiers importés ont été créés avec le statut <strong>"Dossier Historique Autorisé"</strong>
                            et sont maintenant visibles dans le registre public.
                        </div>

                        <hr>

                        <div class="btn-group-vertical" style="width: 100%; max-width: 400px;">
                            <a href="<?= url('modules/import_historique/dashboard.php') ?>" class="btn btn-primary btn-lg mb-2">
                                <i class="fas fa-chart-bar"></i> Voir le tableau de bord
                            </a>
                            <a href="<?= url('modules/registre_public/index.php') ?>" class="btn btn-success btn-lg mb-2">
                                <i class="fas fa-list"></i> Consulter le registre public
                            </a>
                            <a href="<?= url('modules/import_historique/index.php') ?>" class="btn btn-info btn-lg">
                                <i class="fas fa-plus"></i> Importer d'autres dossiers
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow border-danger">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0"><i class="fas fa-times-circle"></i> Échec de l'import</h4>
                    </div>
                    <div class="card-body text-center">
                        <h2 class="text-danger"><?= $errors ?></h2>
                        <p>Erreur(s) détectée(s)</p>

                        <div class="alert alert-danger">
                            Aucun dossier n'a pu être importé. Veuillez vérifier vos données et réessayer.
                        </div>

                        <a href="<?= url('modules/import_historique/index.php') ?>" class="btn btn-primary btn-lg">
                            <i class="fas fa-arrow-left"></i> Retour à l'import
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

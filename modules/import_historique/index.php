<?php
// Module d'import de dossiers historiques - SGDI
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

// Vérifier les permissions
if (!peutImporterHistorique($_SESSION['user_role'])) {
    redirect(url('dashboard.php'), 'Vous n\'avez pas les permissions pour accéder à ce module', 'error');
}

$pageTitle = "Import de Dossiers Historiques";
include '../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-file-import"></i> Import de Dossiers Historiques</h2>
                    <p class="text-muted">Importer les dossiers autorisés avant la mise en place du SGDI</p>
                </div>
                <div>
                    <a href="<?= url('modules/import_historique/guide.php') ?>" class="btn btn-success me-2">
                        <i class="fas fa-book"></i> Guide d'utilisation
                    </a>
                    <a href="<?= url('modules/import_historique/dashboard.php') ?>" class="btn btn-info">
                        <i class="fas fa-chart-bar"></i> Tableau de bord
                    </a>
                </div>
            </div>

            <!-- Alertes -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?= $_SESSION['flash_type'] ?> alert-dismissible fade show">
                    <?= $_SESSION['flash_message'] ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <div class="row">
                <!-- Colonne gauche : Formulaire d'upload -->
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-upload"></i> Importer un fichier</h5>
                        </div>
                        <div class="card-body">
                            <form id="uploadForm" action="<?= url('modules/import_historique/preview.php') ?>" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> <strong>Format accepté :</strong> CSV (séparateur point-virgule) ou Excel (.xlsx)<br>
                                    <i class="fas fa-exclamation-triangle"></i> <strong>Limite :</strong> 200 lignes maximum par fichier
                                </div>

                                <div class="form-group">
                                    <label for="fichier_import"><strong>Sélectionner un fichier</strong> <span class="text-danger">*</span></label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="fichier_import" name="fichier_import"
                                               accept=".csv,.xlsx,.xls" required>
                                        <label class="custom-file-label" for="fichier_import">Choisir un fichier...</label>
                                    </div>
                                    <small class="form-text text-muted">
                                        Formats acceptés : .csv, .xlsx, .xls (5 MB maximum)
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="source_import"><strong>Source/Description de l'import</strong></label>
                                    <input type="text" class="form-control" id="source_import" name="source_import"
                                           placeholder="Ex: Import stations Littoral - Janvier 2025" maxlength="100">
                                    <small class="form-text text-muted">
                                        Optionnel : Pour identifier facilement cet import dans l'historique
                                    </small>
                                </div>

                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="confirmer_format" required>
                                        <label class="custom-control-label" for="confirmer_format">
                                            Je confirme que mon fichier respecte le format du template
                                        </label>
                                    </div>
                                </div>

                                <hr>

                                <div class="d-flex justify-content-between align-items-center">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-check-circle"></i> Valider et Prévisualiser
                                    </button>
                                    <button type="reset" class="btn btn-secondary">
                                        <i class="fas fa-redo"></i> Réinitialiser
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Statistiques rapides -->
                    <?php
                    $stats = getStatistiquesImport();
                    if ($stats['total'] > 0):
                    ?>
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> Statistiques d'import</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <h3 class="text-success"><?= number_format($stats['total']) ?></h3>
                                    <p class="text-muted">Dossiers importés</p>
                                </div>
                                <div class="col-md-3">
                                    <h3 class="text-info"><?= $stats['nb_importeurs'] ?></h3>
                                    <p class="text-muted">Utilisateurs</p>
                                </div>
                                <div class="col-md-3">
                                    <p class="text-muted mb-0">Premier import</p>
                                    <strong><?= date('d/m/Y', strtotime($stats['premier_import'])) ?></strong>
                                </div>
                                <div class="col-md-3">
                                    <p class="text-muted mb-0">Dernier import</p>
                                    <strong><?= date('d/m/Y', strtotime($stats['dernier_import'])) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Colonne droite : Instructions et templates -->
                <div class="col-lg-4">
                    <!-- Templates -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-download"></i> Templates d'import</h5>
                        </div>
                        <div class="card-body">
                            <p>Téléchargez les templates pré-remplis avec des exemples :</p>

                            <div class="list-group">
                                <a href="<?= url('modules/import_historique/download_template.php?type=stations') ?>"
                                   class="list-group-item list-group-item-action">
                                    <i class="fas fa-gas-pump text-primary"></i>
                                    <strong>Stations-Service</strong>
                                    <small class="d-block text-muted">Implantations et reprises</small>
                                </a>
                                <a href="<?= url('modules/import_historique/download_template.php?type=points_conso') ?>"
                                   class="list-group-item list-group-item-action">
                                    <i class="fas fa-industry text-success"></i>
                                    <strong>Points Consommateurs</strong>
                                    <small class="d-block text-muted">Avec entreprises bénéficiaires</small>
                                </a>
                                <a href="<?= url('modules/import_historique/templates/INSTRUCTIONS_IMPORT.md') ?>"
                                   class="list-group-item list-group-item-action" target="_blank">
                                    <i class="fas fa-book text-info"></i>
                                    <strong>Guide d'utilisation</strong>
                                    <small class="d-block text-muted">Instructions détaillées</small>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Instructions rapides -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-question-circle"></i> Instructions rapides</h5>
                        </div>
                        <div class="card-body">
                            <ol class="pl-3">
                                <li class="mb-2">
                                    <strong>Téléchargez</strong> le template correspondant
                                </li>
                                <li class="mb-2">
                                    <strong>Remplissez</strong> les données (ne pas modifier l'en-tête)
                                </li>
                                <li class="mb-2">
                                    <strong>Enregistrez</strong> au format CSV ou Excel
                                </li>
                                <li class="mb-2">
                                    <strong>Importez</strong> via le formulaire ci-contre
                                </li>
                                <li class="mb-2">
                                    <strong>Vérifiez</strong> la prévisualisation
                                </li>
                                <li>
                                    <strong>Confirmez</strong> l'import
                                </li>
                            </ol>

                            <hr>

                            <h6 class="text-danger"><i class="fas fa-exclamation-triangle"></i> Important</h6>
                            <ul class="small text-muted pl-3">
                                <li>Maximum 200 lignes par fichier</li>
                                <li>Format date : JJ/MM/AAAA</li>
                                <li>Respecter les noms exacts (régions, types)</li>
                                <li>Encoder en UTF-8</li>
                            </ul>

                            <hr>

                            <div class="text-center">
                                <a href="<?= url('modules/import_historique/templates/INSTRUCTIONS_IMPORT.md') ?>"
                                   class="btn btn-sm btn-outline-info btn-block" target="_blank">
                                    <i class="fas fa-book-open"></i> Consulter le guide complet
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Afficher le nom du fichier sélectionné
document.querySelector('.custom-file-input').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || 'Choisir un fichier...';
    const label = e.target.nextElementSibling;
    label.textContent = fileName;

    // Vérifier la taille du fichier
    if (e.target.files[0]) {
        const fileSize = e.target.files[0].size / 1024 / 1024; // en MB
        if (fileSize > 5) {
            alert('Le fichier est trop volumineux (maximum 5 MB)');
            e.target.value = '';
            label.textContent = 'Choisir un fichier...';
        }
    }
});

// Validation du formulaire
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('fichier_import');
    const confirmCheck = document.getElementById('confirmer_format');

    if (!fileInput.files.length) {
        e.preventDefault();
        alert('Veuillez sélectionner un fichier');
        return false;
    }

    if (!confirmCheck.checked) {
        e.preventDefault();
        alert('Veuillez confirmer que votre fichier respecte le format du template');
        return false;
    }

    // Afficher un loader
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validation en cours...';
});
</script>

<?php include '../../includes/footer.php'; ?>

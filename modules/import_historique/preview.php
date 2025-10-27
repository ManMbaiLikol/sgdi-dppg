<?php
// Prévisualisation et validation de l'import - SGDI
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

// Vérifier les permissions
if (!peutImporterHistorique($_SESSION['user_role'])) {
    redirect(url('dashboard.php'), 'Vous n\'avez pas les permissions pour accéder à ce module', 'error');
}

// Vérifier le token CSRF
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    redirect(url('modules/import_historique/index.php'), 'Token de sécurité invalide', 'error');
}

// Vérifier l'upload
if (!isset($_FILES['fichier_import']) || $_FILES['fichier_import']['error'] !== UPLOAD_ERR_OK) {
    redirect(url('modules/import_historique/index.php'), 'Erreur lors de l\'upload du fichier', 'error');
}

$source_import = $_POST['source_import'] ?? 'Import_' . date('Y-m-d_H-i');

// Créer un répertoire temporaire si nécessaire
$tempDir = __DIR__ . '/../../uploads/temp/';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

// Déplacer le fichier uploadé
$uploadedFile = $_FILES['fichier_import']['tmp_name'];
$fileName = $_FILES['fichier_import']['name'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$tempFile = $tempDir . uniqid('import_') . '.' . $fileExtension;

if (!move_uploaded_file($uploadedFile, $tempFile)) {
    redirect(url('modules/import_historique/index.php'), 'Erreur lors de la sauvegarde du fichier', 'error');
}

// Lire le fichier
try {
    $donnees = lireCSV($tempFile);
} catch (Exception $e) {
    unlink($tempFile);
    redirect(url('modules/import_historique/index.php'), 'Erreur de lecture : ' . $e->getMessage(), 'error');
}

// Valider chaque ligne
$erreurs = [];
$lignesValides = [];
$ligneNum = 1;

foreach ($donnees as $ligne) {
    $ligneNum++;

    // Ignorer les lignes vides
    if (empty(array_filter($ligne))) {
        continue;
    }

    $erreursLigne = validerLigneImport($ligne, $ligneNum);

    if (!empty($erreursLigne)) {
        $erreurs = array_merge($erreurs, $erreursLigne);
    } else {
        $lignesValides[] = $ligne;
    }
}

// Limiter à 200 lignes
if (count($lignesValides) > 200) {
    unlink($tempFile);
    redirect(url('modules/import_historique/index.php'),
        'Trop de lignes (' . count($lignesValides) . '). Maximum 200 lignes par fichier', 'error');
}

// Sauvegarder en session pour l'étape suivante
$_SESSION['import_preview'] = [
    'donnees' => $lignesValides,
    'source' => $source_import,
    'temp_file' => $tempFile,
    'erreurs' => $erreurs,
    'uploaded_at' => time()
];

$pageTitle = "Prévisualisation de l'import";
include '../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-search"></i> Prévisualisation de l'import</h2>
                    <p class="text-muted">Vérifiez les données avant de confirmer l'import</p>
                </div>
                <div>
                    <a href="<?= url('modules/import_historique/index.php') ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                </div>
            </div>

            <?php if (!empty($erreurs)): ?>
                <!-- Erreurs détectées -->
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle"></i> Erreurs détectées (<?= count($erreurs) ?>)</h5>
                    <p>Veuillez corriger les erreurs suivantes avant de continuer :</p>
                    <ul class="mb-0">
                        <?php foreach (array_slice($erreurs, 0, 20) as $erreur): ?>
                            <li><?= htmlspecialchars($erreur) ?></li>
                        <?php endforeach; ?>
                        <?php if (count($erreurs) > 20): ?>
                            <li class="text-muted"><em>... et <?= count($erreurs) - 20 ?> autres erreurs</em></li>
                        <?php endif; ?>
                    </ul>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= url('modules/import_historique/export_errors.php') ?>" class="btn btn-warning">
                            <i class="fas fa-download"></i> Télécharger le rapport complet
                        </a>
                        <a href="<?= url('modules/import_historique/index.php') ?>" class="btn btn-secondary">
                            Corriger et réimporter
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Validation réussie -->
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle"></i> Validation réussie !</h5>
                    <p class="mb-0">
                        <strong><?= count($lignesValides) ?> dossiers</strong> prêts à être importés.
                        Vérifiez l'aperçu ci-dessous et confirmez l'import.
                    </p>
                </div>

                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3><?= count($lignesValides) ?></h3>
                                <p class="mb-0">Dossiers à importer</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <?php
                                $types = array_count_values(array_column($lignesValides, 'type_infrastructure'));
                                ?>
                                <h3><?= count($types) ?></h3>
                                <p class="mb-0">Types d'infrastructure</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <?php
                                $regions = array_count_values(array_column($lignesValides, 'region'));
                                ?>
                                <h3><?= count($regions) ?></h3>
                                <p class="mb-0">Régions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <?php
                                $coordonnees = count(array_filter($lignesValides, function($l) {
                                    return !empty($l['latitude']) && !empty($l['longitude']);
                                }));
                                ?>
                                <h3><?= $coordonnees ?></h3>
                                <p class="mb-0">Avec coordonnées GPS</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tableau de prévisualisation -->
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-table"></i> Aperçu des données (premières 50 lignes)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-sm mb-0">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Type</th>
                                        <th>Demandeur</th>
                                        <th>Région</th>
                                        <th>Ville</th>
                                        <th>Date</th>
                                        <th>N° Décision</th>
                                        <th>GPS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($lignesValides, 0, 50) as $index => $ligne): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <small>
                                                    <?php
                                                    $type = $ligne['type_infrastructure'];
                                                    if (strpos($type, 'station-service') !== false) {
                                                        echo '<i class="fas fa-gas-pump text-primary"></i> ';
                                                    } elseif (strpos($type, 'point consommateur') !== false) {
                                                        echo '<i class="fas fa-industry text-success"></i> ';
                                                    } elseif (strpos($type, 'GPL') !== false) {
                                                        echo '<i class="fas fa-fire text-danger"></i> ';
                                                    }
                                                    echo htmlspecialchars($type);
                                                    ?>
                                                </small>
                                            </td>
                                            <td><?= htmlspecialchars($ligne['nom_demandeur']) ?></td>
                                            <td><span class="badge badge-secondary"><?= htmlspecialchars($ligne['region']) ?></span></td>
                                            <td><?= htmlspecialchars($ligne['ville']) ?></td>
                                            <td><?= htmlspecialchars($ligne['date_autorisation']) ?></td>
                                            <td><small><?= htmlspecialchars($ligne['numero_decision']) ?></small></td>
                                            <td class="text-center">
                                                <?php if (!empty($ligne['latitude']) && !empty($ligne['longitude'])): ?>
                                                    <i class="fas fa-map-marker-alt text-success"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-times text-muted"></i>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($lignesValides) > 50): ?>
                            <div class="alert alert-info mb-0 rounded-0">
                                <i class="fas fa-info-circle"></i>
                                Affichage des 50 premières lignes sur <?= count($lignesValides) ?> au total
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="card shadow-sm mt-4">
                    <div class="card-body">
                        <form action="<?= url('modules/import_historique/process.php') ?>" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                            <div class="alert alert-warning">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="confirm_import" name="confirm_import" required>
                                    <label class="custom-control-label" for="confirm_import">
                                        <strong>Je confirme l'import de ces <?= count($lignesValides) ?> dossiers historiques</strong><br>
                                        <small class="text-muted">
                                            Les dossiers seront créés avec le statut "Dossier Historique Autorisé"
                                            et apparaîtront dans le registre public.
                                        </small>
                                    </label>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <a href="<?= url('modules/import_historique/index.php') ?>" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times"></i> Annuler
                                </a>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-check-circle"></i> Confirmer l'import (<?= count($lignesValides) ?> dossiers)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

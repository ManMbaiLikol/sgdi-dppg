<?php
// Suppression de dossier - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

// Seul l'admin peut supprimer les dossiers
requireRole('admin');

$dossier_id = intval($_GET['id'] ?? 0);
if (!$dossier_id) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

// Récupérer le dossier existant
$dossier = getDossierDetails($dossier_id);
if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non trouvé', 'error');
}

$page_title = 'Supprimer le dossier ' . $dossier['numero'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        if (supprimerDossier($dossier_id)) {
            redirect(url('modules/dossiers/list.php'), 'Dossier supprimé avec succès', 'success');
        } else {
            $errors[] = 'Erreur lors de la suppression du dossier';
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-danger"><?php echo $page_title; ?></h1>
                    <p class="text-muted">Suppression définitive du dossier</p>
                </div>
                <div>
                    <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Retour au dossier
                    </a>
                </div>
            </div>

            <!-- Alerte de danger -->
            <div class="alert alert-danger mb-4">
                <h5><i class="fas fa-exclamation-triangle"></i> ATTENTION - Action irréversible</h5>
                <p class="mb-2">Vous êtes sur le point de supprimer définitivement ce dossier. Cette action :</p>
                <ul class="mb-0">
                    <li>Supprimera le dossier et toutes ses informations</li>
                    <li>Supprimera tous les documents associés</li>
                    <li>Supprimera tous les paiements et notes de frais</li>
                    <li>Supprimera l'historique complet du dossier</li>
                    <li>Supprimera les commissions et inspections</li>
                    <li><strong>Ne peut pas être annulée</strong></li>
                </ul>
            </div>

            <!-- Alertes -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h6><i class="fas fa-exclamation-triangle"></i> Erreurs détectées :</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Informations du dossier -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-folder"></i> Dossier à supprimer</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Numéro :</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['numero']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Type :</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['type_infrastructure']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Nature :</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['sous_type']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Demandeur :</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['nom_demandeur']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Statut :</strong></td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                                            <?php echo getStatutLabel($dossier['statut']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Région :</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['region']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Ville :</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['ville']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Date création :</strong></td>
                                    <td><?php echo formatDateTime($dossier['date_creation'], 'd/m/Y à H:i'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulaire de confirmation -->
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-trash"></i> Confirmation de suppression</h5>
                </div>
                <div class="card-body">
                    <p class="text-center mb-4">
                        <strong>Êtes-vous absolument certain de vouloir supprimer ce dossier ?</strong>
                    </p>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="d-flex justify-content-between">
                            <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-danger"
                                    onclick="return confirm('DERNIÈRE CONFIRMATION: Voulez-vous vraiment supprimer définitivement ce dossier et toutes ses données associées ? Cette action ne peut pas être annulée.')">
                                <i class="fas fa-trash"></i> Supprimer définitivement
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Note importante -->
            <div class="alert alert-warning mt-4">
                <h6><i class="fas fa-info-circle"></i> Note importante</h6>
                <p class="mb-0">
                    Cette fonctionnalité est réservée aux administrateurs système pour corriger des erreurs graves ou supprimer des données de test.
                    En fonctionnement normal, les dossiers ne doivent jamais être supprimés mais archivés.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
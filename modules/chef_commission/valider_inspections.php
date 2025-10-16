<?php
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

// Vérifier que l'utilisateur est un chef de commission
if ($_SESSION['user_role'] !== 'chef_commission') {
    $_SESSION['error'] = "Accès réservé aux chefs de commission";
    redirect(url('dashboard.php'));
}

// Récupérer les inspections à valider
$inspections = getInspectionsAValider($_SESSION['user_id']);

// Statistiques
$stats = getStatistiquesChefCommission($_SESSION['user_id']);

$pageTitle = "Inspections à valider";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">
                <i class="fas fa-clipboard-check"></i>
                Inspections à valider
            </h1>
            <p class="text-muted">Liste des inspections validées par les inspecteurs, en attente de votre validation</p>
        </div>
        <div>
            <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
            </a>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">À valider</h6>
                            <h2 class="mb-0 mt-2"><?php echo $stats['a_valider']; ?></h2>
                        </div>
                        <div>
                            <i class="fas fa-hourglass-half fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Approuvées</h6>
                            <h2 class="mb-0 mt-2"><?php echo $stats['approuvees']; ?></h2>
                        </div>
                        <div>
                            <i class="fas fa-check-circle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Rejetées</h6>
                            <h2 class="mb-0 mt-2"><?php echo $stats['rejetees']; ?></h2>
                        </div>
                        <div>
                            <i class="fas fa-times-circle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total</h6>
                            <h2 class="mb-0 mt-2"><?php echo $stats['total']; ?></h2>
                        </div>
                        <div>
                            <i class="fas fa-chart-line fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des inspections -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-list"></i>
                Inspections en attente (<?php echo count($inspections); ?>)
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($inspections)): ?>
                <div class="alert alert-info text-center py-5">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <h4>Aucune inspection en attente</h4>
                    <p class="mb-0">Toutes les inspections ont été traitées.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="10%">Dossier</th>
                                <th width="20%">Type</th>
                                <th width="20%">Demandeur</th>
                                <th width="15%">Localisation</th>
                                <th width="15%">Inspecteur</th>
                                <th width="10%">Date</th>
                                <th width="10%" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inspections as $insp): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($insp['dossier_numero']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($insp['type_infrastructure']); ?>
                                    </span>
                                    <?php if ($insp['sous_type']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($insp['sous_type']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($insp['nom_demandeur']); ?></div>
                                </td>
                                <td>
                                    <?php if ($insp['ville']): ?>
                                        <i class="fas fa-map-marker-alt text-muted"></i>
                                        <?php echo htmlspecialchars($insp['ville']); ?>
                                        <?php if ($insp['quartier']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($insp['quartier']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Non précisé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($insp['inspecteur_nom']): ?>
                                        <i class="fas fa-user text-muted"></i>
                                        <?php echo htmlspecialchars($insp['inspecteur_prenom'] . ' ' . $insp['inspecteur_nom']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        <?php echo date('d/m/Y', strtotime($insp['fiche_date'])); ?>
                                        <br>
                                        <span class="text-muted"><?php echo date('H:i', strtotime($insp['fiche_date'])); ?></span>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <a href="valider_fiche.php?fiche_id=<?php echo $insp['fiche_id']; ?>"
                                       class="btn btn-sm btn-primary"
                                       title="Examiner et valider/rejeter cette inspection">
                                        <i class="fas fa-eye"></i> Examiner
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.opacity-50 {
    opacity: 0.5;
}
</style>

<?php include '../../includes/footer.php'; ?>

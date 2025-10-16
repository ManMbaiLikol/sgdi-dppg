<?php
require_once '../../includes/auth.php';
require_once '../dossiers/functions.php';
require_once 'functions.php';

requireLogin();

// Vérifier que l'utilisateur est un cadre DPPG
if ($_SESSION['user_role'] !== 'cadre_dppg') {
    $_SESSION['error'] = "Accès réservé aux cadres DPPG";
    redirect(url('dashboard.php'));
}

// Récupérer tous les dossiers actifs avec leur fiche d'inspection si elle existe
$sql = "SELECT
            d.id,
            d.numero,
            d.type_infrastructure,
            d.sous_type,
            d.nom_demandeur,
            d.ville,
            d.quartier,
            d.statut,
            d.date_creation,
            u.nom as createur_nom,
            u.prenom as createur_prenom,
            fi.id as fiche_id,
            fi.statut as fiche_statut
        FROM dossiers d
        LEFT JOIN fiches_inspection fi ON d.id = fi.dossier_id
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.statut IN ('en_cours', 'en_attente_inspection', 'commission_constituee', 'paye', 'inspecte', 'valide')
        ORDER BY d.date_creation DESC";

$stmt = $pdo->query($sql);
$dossiers = $stmt->fetchAll();

// Statistiques
$total_dossiers = count($dossiers);
$dossiers_non_inspectes = 0;
$dossiers_inspectes = 0;

foreach ($dossiers as $dossier) {
    if ($dossier['fiche_id']) {
        $dossiers_inspectes++;
    } else {
        $dossiers_non_inspectes++;
    }
}

$pageTitle = "Dossiers à inspecter";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">
                <i class="fas fa-clipboard-list"></i>
                Dossiers à inspecter
            </h1>
            <p class="text-muted">Liste des dossiers sans fiche d'inspection</p>
        </div>
        <div>
            <a href="<?php echo url('modules/fiche_inspection/print_blank.php'); ?>" class="btn btn-outline-info me-2" target="_blank" title="Imprimer une fiche vierge pour l'utiliser sur le terrain">
                <i class="fas fa-print"></i> Imprimer fiche vierge
            </a>
            <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
            </a>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total dossiers</h6>
                            <h2 class="mb-0 mt-2"><?php echo $total_dossiers; ?></h2>
                        </div>
                        <div>
                            <i class="fas fa-folder fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">À inspecter</h6>
                            <h2 class="mb-0 mt-2"><?php echo $dossiers_non_inspectes; ?></h2>
                        </div>
                        <div>
                            <i class="fas fa-clipboard-list fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Déjà inspectés</h6>
                            <h2 class="mb-0 mt-2"><?php echo $dossiers_inspectes; ?></h2>
                        </div>
                        <div>
                            <i class="fas fa-check-circle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des dossiers -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-list"></i>
                Liste des dossiers (<?php echo $total_dossiers; ?>)
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($dossiers)): ?>
                <div class="alert alert-info text-center py-5">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <h4>Aucun dossier</h4>
                    <p class="mb-0">Il n'y a aucun dossier actif pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="10%">Numéro</th>
                                <th width="20%">Type d'infrastructure</th>
                                <th width="20%">Demandeur</th>
                                <th width="15%">Localisation</th>
                                <th width="12%">Statut</th>
                                <th width="13%">Date création</th>
                                <th width="10%" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dossiers as $dossier): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($dossier['numero']); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($dossier['type_infrastructure']); ?>
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($dossier['sous_type']); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="fw-bold">
                                        <?php echo htmlspecialchars($dossier['nom_demandeur']); ?>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($dossier['createur_prenom'] . ' ' . $dossier['createur_nom']); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($dossier['ville']): ?>
                                        <i class="fas fa-map-marker-alt text-muted"></i>
                                        <?php echo htmlspecialchars($dossier['ville']); ?>
                                        <?php if ($dossier['quartier']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($dossier['quartier']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Non précisé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo getStatutClass($dossier['statut']); ?>">
                                        <?php echo getStatutLabel($dossier['statut']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-calendar text-muted"></i>
                                        <?php echo date('d/m/Y', strtotime($dossier['date_creation'])); ?>
                                        <br>
                                        <span class="text-muted"><?php echo date('H:i', strtotime($dossier['date_creation'])); ?></span>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier['id']); ?>"
                                           class="btn btn-sm btn-outline-primary"
                                           title="Voir les détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo url('modules/fiche_inspection/print_prefilled.php?dossier_id=' . $dossier['id']); ?>"
                                           class="btn btn-sm btn-outline-info"
                                           target="_blank"
                                           title="Imprimer une fiche pré-remplie">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <?php if ($dossier['fiche_id']): ?>
                                            <a href="<?php echo url('modules/fiche_inspection/edit.php?dossier_id=' . $dossier['id']); ?>"
                                               class="btn btn-sm btn-info"
                                               title="Voir la fiche d'inspection">
                                                <i class="fas fa-file-alt"></i> Voir l'inspection
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo url('modules/fiche_inspection/edit.php?dossier_id=' . $dossier['id']); ?>"
                                               class="btn btn-sm btn-success"
                                               title="Créer la fiche d'inspection">
                                                <i class="fas fa-clipboard-check"></i> Inspecter
                                            </a>
                                        <?php endif; ?>
                                    </div>
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

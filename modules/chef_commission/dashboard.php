<?php
// Dashboard Chef de Commission - SGDI
require_once '../../includes/auth.php';
require_once '../dossiers/functions.php';

requireRole('chef_commission');

$page_title = 'Tableau de bord - Chef de Commission';
$user_id = $_SESSION['user_id'];

// Récupérer les dossiers où l'utilisateur est chef de commission
$sql = "SELECT d.*,
               c.id as commission_id,
               i.id as inspection_id,
               i.conforme,
               i.valide_par_chef_commission,
               i.date_inspection,
               u_dppg.nom as nom_cadre_dppg,
               u_dppg.prenom as prenom_cadre_dppg,
               u_daj.nom as nom_cadre_daj,
               u_daj.prenom as prenom_cadre_daj
        FROM dossiers d
        INNER JOIN commissions c ON d.id = c.dossier_id
        LEFT JOIN inspections i ON d.id = i.dossier_id
        LEFT JOIN users u_dppg ON c.cadre_dppg_id = u_dppg.id
        LEFT JOIN users u_daj ON c.cadre_daj_id = u_daj.id
        WHERE c.chef_commission_id = ?
        ORDER BY d.date_modification DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$dossiers = $stmt->fetchAll();

// Statistiques
$stats = [
    'total' => count($dossiers),
    'en_attente_validation' => 0,
    'valides' => 0,
    'analyses_daj' => 0
];

foreach ($dossiers as $dossier) {
    if ($dossier['statut'] === 'inspecte' && !$dossier['valide_par_chef_commission']) {
        $stats['en_attente_validation']++;
    }
    if ($dossier['valide_par_chef_commission']) {
        $stats['valides']++;
    }
    if ($dossier['statut'] === 'analyse_daj' || $dossier['statut'] === 'paye') {
        $stats['analyses_daj']++;
    }
}

require_once '../../includes/header.php';
?>

<!-- En-tête de bienvenue -->
<div class="row mb-4">
    <div class="col">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="mb-1">
                            Bienvenue, <?php echo sanitize($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?>
                        </h4>
                        <p class="mb-0 opacity-75">
                            <i class="fas fa-user-tie"></i>
                            Chef de Commission - Validation des inspections
                        </p>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-check fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-3 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-folder fa-2x text-primary mb-2"></i>
                <h4 class="text-primary"><?php echo $stats['total']; ?></h4>
                <p class="text-muted mb-0">Dossiers total</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                <h4 class="text-warning"><?php echo $stats['en_attente_validation']; ?></h4>
                <p class="text-muted mb-0">En attente validation</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h4 class="text-success"><?php echo $stats['valides']; ?></h4>
                <p class="text-muted mb-0">Validés</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-gavel fa-2x text-info mb-2"></i>
                <h4 class="text-info"><?php echo $stats['analyses_daj']; ?></h4>
                <p class="text-muted mb-0">Analyses DAJ</p>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mb-4">
    <div class="col">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt"></i> Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <a href="<?php echo url('modules/chef_commission/list.php?statut=inspecte'); ?>"
                           class="btn btn-warning w-100 d-flex flex-column align-items-center justify-content-center"
                           style="height: 120px; text-align: center;">
                            <i class="fas fa-clipboard-check fa-2x mb-2"></i>
                            <span style="font-size: 0.9rem; line-height: 1.2; font-weight: 500;">Valider les inspections</span>
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="<?php echo url('modules/carte/index.php'); ?>"
                           class="btn btn-success w-100 d-flex flex-column align-items-center justify-content-center"
                           style="height: 120px; text-align: center;">
                            <i class="fas fa-map-marked-alt fa-2x mb-2"></i>
                            <span style="font-size: 0.9rem; line-height: 1.2; font-weight: 500;">Carte des infrastructures</span>
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="<?php echo url('modules/chef_commission/list.php'); ?>"
                           class="btn btn-secondary w-100 d-flex flex-column align-items-center justify-content-center"
                           style="height: 120px; text-align: center;">
                            <i class="fas fa-folder-open fa-2x mb-2"></i>
                            <span style="font-size: 0.9rem; line-height: 1.2; font-weight: 500;">Tous mes dossiers</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Liste des dossiers -->
<div class="row">
    <div class="col">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list"></i> Mes dossiers de commission
                </h5>
                <a href="<?php echo url('modules/chef_commission/list.php'); ?>" class="btn btn-outline-primary btn-sm">
                    Voir tous <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <?php if (empty($dossiers)): ?>
            <div class="card-body text-center py-5">
                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">Aucun dossier assigné pour le moment</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>N° Dossier</th>
                            <th>Type</th>
                            <th>Demandeur</th>
                            <th>Membres commission</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($dossiers, 0, 10) as $dossier): ?>
                        <tr>
                            <td>
                                <code class="text-primary"><?php echo sanitize($dossier['numero']); ?></code>
                            </td>
                            <td>
                                <small><?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong>
                            </td>
                            <td>
                                <small class="d-block">
                                    <i class="fas fa-hard-hat"></i> <?php echo sanitize($dossier['prenom_cadre_dppg'] . ' ' . $dossier['nom_cadre_dppg']); ?>
                                </small>
                                <small class="d-block">
                                    <i class="fas fa-gavel"></i> <?php echo sanitize($dossier['prenom_cadre_daj'] . ' ' . $dossier['nom_cadre_daj']); ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                                    <?php echo getStatutLabel($dossier['statut']); ?>
                                </span>
                                <?php if ($dossier['statut'] === 'inspecte' && !$dossier['valide_par_chef_commission']): ?>
                                <div class="small text-warning mt-1">
                                    <i class="fas fa-exclamation-triangle"></i> À valider
                                </div>
                                <?php endif; ?>
                                <?php if ($dossier['valide_par_chef_commission']): ?>
                                <div class="small text-success mt-1">
                                    <i class="fas fa-check-circle"></i> Validé
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?php echo formatDate($dossier['date_creation']); ?></small>
                            </td>
                            <td>
                                <?php if ($dossier['statut'] === 'inspecte' && !$dossier['valide_par_chef_commission'] && $dossier['inspection_id']): ?>
                                <a href="<?php echo url('modules/chef_commission/valider_inspection.php?id=' . $dossier['id']); ?>"
                                   class="btn btn-warning btn-sm">
                                    <i class="fas fa-check"></i> Valider
                                </a>
                                <?php else: ?>
                                <a href="<?php echo url('modules/chef_commission/view.php?id=' . $dossier['id']); ?>"
                                   class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-eye"></i> Voir
                                </a>
                                <?php endif; ?>
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

<?php require_once '../../includes/footer.php'; ?>

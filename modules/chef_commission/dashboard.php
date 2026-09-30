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

require_once '../../includes/ui.php';
require_once '../../includes/taches.php';
$taches = getTachesAFaire('chef_commission', $user_id);

require_once '../../includes/header.php';

echo uiBandeauAccueil(
    trim($_SESSION['user_prenom'] ?? '') ?: ($_SESSION['user_nom'] ?? ''),
    'Chef de Commission',
    $taches,
    '<a class="btn btn-light" href="' . url('modules/chef_commission/list.php?statut=inspecte') . '"><i class="fas fa-check-double"></i> Valider les inspections</a>'
);
?>

<div class="row row-cols-2 row-cols-xl-4 g-3 mb-4">
    <div class="col"><?php echo uiKpiCard('Dossiers de mes commissions', $stats['total'], 'fa-folder'); ?></div>
    <div class="col"><?php echo uiKpiCard('En attente de validation', $stats['en_attente_validation'], 'fa-hourglass-half', 'paiement', 'Rapports inspectés', url('modules/chef_commission/list.php?statut=inspecte')); ?></div>
    <div class="col"><?php echo uiKpiCard('Validés', $stats['valides'], 'fa-circle-check', 'succes'); ?></div>
    <div class="col"><?php echo uiKpiCard('En analyse DAJ', $stats['analyses_daj'], 'fa-scale-balanced', 'instruction', 'Payés ou analysés'); ?></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h2 class="card-title-sm">À traiter</h2><span class="text-muted-sgdi small">Du plus urgent au moins urgent</span></div>
            <?php echo uiTaskList($taches); ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h2 class="card-title-sm">Raccourcis</h2></div>
            <div class="card-body">
                <?php echo uiRaccourcis([
                    ['fa-clipboard-check', 'Valider les inspections', url('modules/chef_commission/list.php?statut=inspecte'), 'paiement'],
                    ['fa-map-location-dot', 'Carte des infrastructures', url('modules/carte/index.php'), 'succes'],
                    ['fa-folder-open', 'Tous mes dossiers', url('modules/chef_commission/list.php'), 'preparation'],
                ]); ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title-sm">Mes dossiers de commission</h2>
        <a class="card-link-more" href="<?php echo url('modules/chef_commission/list.php'); ?>">Voir tous <i class="fas fa-arrow-right small"></i></a>
    </div>
    <?php if (empty($dossiers)): ?>
        <?php echo uiEmptyState('Aucun dossier assigné pour le moment', 'Les dossiers apparaîtront ici dès que vous serez nommé chef d\'une commission.'); ?>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sgdi table-hover table-stack">
            <thead><tr><th>N° dossier</th><th>Demandeur</th><th>Membres de la commission</th><th>Statut</th><th>Avancement</th><th class="text-end"><span class="visually-hidden">Action</span></th></tr></thead>
            <tbody>
                <?php foreach (array_slice($dossiers, 0, 10) as $dossier):
                    $a_valider = $dossier['statut'] === 'inspecte' && !$dossier['valide_par_chef_commission'] && $dossier['inspection_id']; ?>
                <tr>
                    <td data-label="N° dossier">
                        <a class="cell-main mono" href="<?php echo url('modules/chef_commission/view.php?id=' . (int) $dossier['id']); ?>"><?php echo sanitize($dossier['numero']); ?></a>
                        <span class="cell-sub"><?php echo sanitize(getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type'])); ?> · <?php echo formatDate($dossier['date_creation']); ?></span>
                    </td>
                    <td data-label="Demandeur"><?php echo sanitize($dossier['nom_demandeur']); ?></td>
                    <td data-label="Membres de la commission">
                        <span class="d-block small"><i class="fas fa-helmet-safety text-muted-sgdi me-1" aria-hidden="true"></i>DPPG : <?php echo sanitize(trim($dossier['prenom_cadre_dppg'] . ' ' . $dossier['nom_cadre_dppg']) ?: '—'); ?></span>
                        <span class="d-block small"><i class="fas fa-scale-balanced text-muted-sgdi me-1" aria-hidden="true"></i>DAJ : <?php echo sanitize(trim($dossier['prenom_cadre_daj'] . ' ' . $dossier['nom_cadre_daj']) ?: '—'); ?></span>
                    </td>
                    <td data-label="Statut">
                        <?php echo uiStatutBadge($dossier['statut']); ?>
                        <?php if ($a_valider): ?><span class="cell-sub task-due">À valider</span>
                        <?php elseif ($dossier['valide_par_chef_commission']): ?><span class="cell-sub">Validé par vous</span><?php endif; ?>
                    </td>
                    <td data-label="Avancement"><?php echo uiWorkflowProgress($dossier['statut']); ?></td>
                    <td class="text-end cell-actions">
                        <?php if ($a_valider): ?>
                        <a class="btn btn-sm btn-primary" href="<?php echo url('modules/chef_commission/valider_inspection.php?id=' . (int) $dossier['id']); ?>"><i class="fas fa-check"></i> Valider</a>
                        <?php else: ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo url('modules/chef_commission/view.php?id=' . (int) $dossier['id']); ?>">Ouvrir</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>

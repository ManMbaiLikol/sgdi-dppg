<?php
// Dashboard Sous-Directeur - Circuit de visa
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('sous_directeur');

$page_title = 'Tableau de bord - Sous-Directeur';

$user_id = $_SESSION['user_id'];

// Statistiques
$stats = [
    'en_attente_visa' => 0,
    'dossiers_commission' => 0,
    'approuves_mois' => 0,
    'rejetes_mois' => 0,
    'total_vises' => 0
];

// 1. Dossiers en attente de visa sous-directeur (après visa chef service)
$sql_attente = "SELECT COUNT(*) FROM dossiers WHERE statut = 'visa_chef_service'";
$stats['en_attente_visa'] = $pdo->query($sql_attente)->fetchColumn();

// 2. Dossiers où je suis chef de commission
$sql_commission = "SELECT COUNT(*) FROM commissions WHERE chef_commission_id = ?";
$stmt = $pdo->prepare($sql_commission);
$stmt->execute([$user_id]);
$stats['dossiers_commission'] = $stmt->fetchColumn();

// Mes visas ce mois
$sql_mois = "SELECT COUNT(*) FROM visas
             WHERE role = 'sous_directeur'
             AND MONTH(date_visa) = MONTH(CURRENT_DATE())
             AND YEAR(date_visa) = YEAR(CURRENT_DATE())";
$stats_mois = $pdo->query($sql_mois)->fetchColumn();

// Approuvés vs rejetés
$sql_stats = "SELECT action, COUNT(*) as nb FROM visas
              WHERE role = 'sous_directeur'
              AND MONTH(date_visa) = MONTH(CURRENT_DATE())
              AND YEAR(date_visa) = YEAR(CURRENT_DATE())
              GROUP BY action";
$stmt = $pdo->query($sql_stats);
while ($row = $stmt->fetch()) {
    if ($row['action'] === 'approuve') $stats['approuves_mois'] = $row['nb'];
    if ($row['action'] === 'rejete') $stats['rejetes_mois'] = $row['nb'];
}

// Total de mes visas
$sql_total = "SELECT COUNT(*) FROM visas WHERE role = 'sous_directeur'";
$stats['total_vises'] = $pdo->query($sql_total)->fetchColumn();

// Dossiers à viser (après Chef Service)
$sql_viser = "SELECT d.*,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
        u.nom as createur_nom, u.prenom as createur_prenom
        FROM dossiers d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.statut = 'visa_chef_service'
        ORDER BY d.date_creation ASC";
$dossiers_viser = $pdo->query($sql_viser)->fetchAll();

// Dossiers où je suis chef de commission
$sql_commission = "SELECT d.*,
               c.id as commission_id,
               i.id as inspection_id,
               i.conforme,
               i.valide_par_chef_commission,
               i.date_inspection,
               u_dppg.nom as nom_cadre_dppg,
               u_dppg.prenom as prenom_cadre_dppg,
               u_daj.nom as nom_cadre_daj,
               u_daj.prenom as prenom_cadre_daj,
               DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format
        FROM dossiers d
        INNER JOIN commissions c ON d.id = c.dossier_id
        LEFT JOIN inspections i ON d.id = i.dossier_id
        LEFT JOIN users u_dppg ON c.cadre_dppg_id = u_dppg.id
        LEFT JOIN users u_daj ON c.cadre_daj_id = u_daj.id
        WHERE c.chef_commission_id = ?
        ORDER BY d.date_modification DESC";
$stmt = $pdo->prepare($sql_commission);
$stmt->execute([$user_id]);
$dossiers_commission = $stmt->fetchAll();

// Mes dossiers visés (avec statut visa_sous_directeur ou ultérieur)
$sql_vises = "SELECT d.*,
        v.date_visa,
        v.action as visa_action,
        v.observations as visa_commentaire,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
        u.nom as createur_nom, u.prenom as createur_prenom
        FROM dossiers d
        INNER JOIN visas v ON d.id = v.dossier_id AND v.role = 'sous_directeur'
        LEFT JOIN users u ON d.user_id = u.id
        ORDER BY v.date_visa DESC";
$dossiers_vises = $pdo->query($sql_vises)->fetchAll();

require_once '../../includes/ui.php';
require_once '../../includes/taches.php';
$taches = getTachesAFaire('sous_directeur', $user_id);

require_once '../../includes/header.php';

echo uiBandeauAccueil(
    trim($_SESSION['user_prenom'] ?? '') ?: ($_SESSION['user_nom'] ?? ''),
    'Sous-Directeur SDTD · Visa 2/3',
    $taches,
    '<a class="btn btn-light" href="' . url('modules/sous_directeur/liste_a_viser.php') . '"><i class="fas fa-stamp"></i> Viser les dossiers</a>'
);
?>

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
    <div class="col"><?php echo uiKpiCard('En attente de visa', $stats['en_attente_visa'], 'fa-stamp', 'visa', 'Visés par le Chef de Service', url('modules/sous_directeur/liste_a_viser.php')); ?></div>
    <div class="col"><?php echo uiKpiCard('Dossiers de mes commissions', $stats['dossiers_commission'], 'fa-users', '', '', url('modules/sous_directeur/mes_commissions.php')); ?></div>
    <div class="col"><?php echo uiKpiCard('Approuvés ce mois', $stats['approuves_mois'], 'fa-circle-check', 'succes'); ?></div>
    <div class="col"><?php echo uiKpiCard('Rejetés ce mois', $stats['rejetes_mois'], 'fa-circle-xmark', 'danger'); ?></div>
    <div class="col"><?php echo uiKpiCard('Total visés', $stats['total_vises'], 'fa-check-double', 'decision', 'Depuis la mise en service', url('modules/sous_directeur/mes_dossiers_vises.php')); ?></div>
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
                    ['fa-stamp', 'Viser les dossiers', url('modules/sous_directeur/liste_a_viser.php'), 'visa'],
                    ['fa-users', 'Mes commissions', url('modules/sous_directeur/mes_commissions.php'), 'instruction'],
                    ['fa-check-double', 'Mes dossiers visés', url('modules/sous_directeur/mes_dossiers_vises.php'), 'decision'],
                    ['fa-map-location-dot', 'Carte interactive', url('modules/carte/index.php'), 'succes'],
                ]); ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4" id="a-viser">
    <div class="card-header">
        <h2 class="card-title-sm">Dossiers à viser</h2>
        <a class="card-link-more" href="<?php echo url('modules/sous_directeur/liste_a_viser.php'); ?>">Voir la liste complète <i class="fas fa-arrow-right small"></i></a>
    </div>
    <?php if (empty($dossiers_viser)): ?>
        <?php echo uiEmptyState('Aucun dossier à viser', 'Les dossiers visés par le Chef de Service apparaîtront ici.', 'fa-circle-check'); ?>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sgdi table-hover table-stack">
            <thead><tr><th>N° dossier</th><th>Infrastructure</th><th>Demandeur</th><th>Localisation</th><th>Déposé le</th><th class="text-end"><span class="visually-hidden">Action</span></th></tr></thead>
            <tbody>
                <?php foreach (array_slice($dossiers_viser, 0, 10) as $d): ?>
                <tr>
                    <td data-label="N° dossier"><a class="cell-main mono" href="<?php echo url('modules/dossiers/view.php?id=' . (int) $d['id']); ?>"><?php echo sanitize($d['numero']); ?></a></td>
                    <td data-label="Infrastructure"><?php echo sanitize(getTypeLabel($d['type_infrastructure'], $d['sous_type'])); ?></td>
                    <td data-label="Demandeur"><?php echo sanitize($d['nom_demandeur']); ?></td>
                    <td data-label="Localisation"><?php echo sanitize($d['ville'] ?: $d['region']); ?><?php if ($d['ville'] && $d['region']): ?><span class="cell-sub"><?php echo sanitize($d['region']); ?></span><?php endif; ?></td>
                    <td data-label="Déposé le"><?php echo sanitize($d['date_creation_format']); ?></td>
                    <td class="text-end cell-actions"><a class="btn btn-sm btn-primary" href="<?php echo url('modules/sous_directeur/viser.php?id=' . (int) $d['id']); ?>"><i class="fas fa-stamp"></i> Viser</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<h2 class="h5 mb-3">Statistiques avancées</h2>
<?php require_once __DIR__ . '/../../includes/dashboard_stats_avancees.php'; ?>

<?php require_once '../../includes/footer.php'; ?>

<?php
// Dashboard Directeur - Circuit de visa final
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('directeur');

$page_title = 'Tableau de bord - Directeur DPPG';

// Statistiques
$stats = [
    'en_attente' => 0,
    'approuves_mois' => 0,
    'rejetes_mois' => 0,
    'total_vises' => 0
];

// Dossiers en attente de visa directeur
$sql_attente = "SELECT COUNT(*) FROM dossiers WHERE statut = 'visa_sous_directeur'";
$stats['en_attente'] = $pdo->query($sql_attente)->fetchColumn();

// Mes visas ce mois
$sql_stats = "SELECT action, COUNT(*) as nb FROM visas
              WHERE role = 'directeur'
              AND MONTH(date_visa) = MONTH(CURRENT_DATE())
              AND YEAR(date_visa) = YEAR(CURRENT_DATE())
              GROUP BY action";
$stmt = $pdo->query($sql_stats);
while ($row = $stmt->fetch()) {
    if ($row['action'] === 'approuve') $stats['approuves_mois'] = $row['nb'];
    if ($row['action'] === 'rejete') $stats['rejetes_mois'] = $row['nb'];
}

// Total de mes visas
$sql_total = "SELECT COUNT(*) FROM visas WHERE role = 'directeur'";
$stats['total_vises'] = $pdo->query($sql_total)->fetchColumn();

// Dossiers validés (prêts pour décision)
$sql_valides = "SELECT COUNT(*) FROM dossiers WHERE statut = 'visa_directeur'";
$stats['valides'] = $pdo->query($sql_valides)->fetchColumn();

// Dossiers à viser
$sql = "SELECT d.*,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
        u.nom as createur_nom, u.prenom as createur_prenom
        FROM dossiers d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.statut = 'visa_sous_directeur'
        ORDER BY d.date_creation ASC";

$dossiers = $pdo->query($sql)->fetchAll();

// Dossiers validés (prêts pour transmission au Ministre)
$sql_valides_list = "SELECT d.*,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format
        FROM dossiers d
        WHERE d.statut = 'visa_directeur'
        ORDER BY d.date_modification DESC
        LIMIT 5";

$dossiers_valides = $pdo->query($sql_valides_list)->fetchAll();

require_once '../../includes/ui.php';
require_once '../../includes/taches.php';
$taches = getTachesAFaire('directeur', $_SESSION['user_id']);

require_once '../../includes/header.php';

echo uiBandeauAccueil(
    trim($_SESSION['user_prenom'] ?? '') ?: ($_SESSION['user_nom'] ?? ''),
    'Directeur DPPG · Visa 3/3 avant décision',
    $taches,
    '<a class="btn btn-light" href="#a-viser"><i class="fas fa-stamp"></i> Viser les dossiers</a>'
);

// Tableau commun aux deux listes de dossiers de cette page
$tableau = function (array $liste, $avec_createur, $action) {
    ob_start(); ?>
    <div class="table-responsive">
        <table class="table table-sgdi table-hover table-stack">
            <thead><tr><th>N° dossier</th><th>Infrastructure</th><th>Demandeur</th><th>Localisation</th><th>Déposé le</th><?php if ($avec_createur): ?><th>Créé par</th><?php endif; ?><th class="text-end"><span class="visually-hidden">Action</span></th></tr></thead>
            <tbody>
                <?php foreach ($liste as $d): ?>
                <tr>
                    <td data-label="N° dossier"><a class="cell-main mono" href="<?php echo url('modules/dossiers/view.php?id=' . (int) $d['id']); ?>"><?php echo sanitize($d['numero']); ?></a></td>
                    <td data-label="Infrastructure"><?php echo sanitize(getTypeLabel($d['type_infrastructure'], $d['sous_type'])); ?></td>
                    <td data-label="Demandeur"><?php echo sanitize($d['nom_demandeur']); ?></td>
                    <td data-label="Localisation"><?php echo sanitize($d['ville'] ?: ($d['region'] ?: 'Non précisée')); ?></td>
                    <td data-label="Déposé le"><?php echo sanitize($d['date_creation_format']); ?></td>
                    <?php if ($avec_createur): ?><td data-label="Créé par"><?php echo sanitize(trim($d['createur_prenom'] . ' ' . $d['createur_nom'])); ?></td><?php endif; ?>
                    <td class="text-end cell-actions"><?php echo $action($d); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php return ob_get_clean();
};
?>

<div class="row row-cols-2 row-cols-xl-4 g-3 mb-4">
    <div class="col"><?php echo uiKpiCard('En attente de visa', $stats['en_attente'], 'fa-stamp', 'visa', 'Visés par le Sous-Directeur', '#a-viser'); ?></div>
    <div class="col"><?php echo uiKpiCard('Approuvés ce mois', $stats['approuves_mois'], 'fa-circle-check', 'succes'); ?></div>
    <div class="col"><?php echo uiKpiCard('Rejetés ce mois', $stats['rejetes_mois'], 'fa-circle-xmark', 'danger'); ?></div>
    <div class="col"><?php echo uiKpiCard('Transmis au Ministre', $stats['valides'], 'fa-paper-plane', 'decision', 'En attente de décision', '#transmis'); ?></div>
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
                    ['fa-stamp', 'Viser les dossiers', '#a-viser', 'visa'],
                    ['fa-map-location-dot', 'Carte des infrastructures', url('modules/carte/index.php'), 'succes'],
                    ['fa-folder-open', 'Mes dossiers visés', url('modules/dossiers/list.php'), 'preparation'],
                ]); ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4" id="a-viser">
    <div class="card-header"><h2 class="card-title-sm">Dossiers en attente de votre visa</h2><span class="text-muted-sgdi small">Après le visa du Sous-Directeur</span></div>
    <?php if (empty($dossiers)): ?>
        <?php echo uiEmptyState('Aucun dossier en attente de votre visa', '', 'fa-circle-check'); ?>
    <?php else: ?>
        <?php echo $tableau($dossiers, true, function ($d) {
            return '<a class="btn btn-sm btn-primary" href="' . url('modules/directeur/viser.php?id=' . (int) $d['id']) . '"><i class="fas fa-stamp"></i> Viser</a>';
        }); ?>
    <?php endif; ?>
</div>

<?php if (!empty($dossiers_valides)): ?>
<div class="card mb-4" id="transmis">
    <div class="card-header"><h2 class="card-title-sm">Transmis récemment au Ministre</h2><span class="text-muted-sgdi small">En attente de décision ministérielle</span></div>
    <?php echo $tableau($dossiers_valides, false, function ($d) {
        return '<a class="btn btn-sm btn-outline-secondary" href="' . url('modules/dossiers/view.php?id=' . (int) $d['id']) . '">Ouvrir</a>';
    }); ?>
</div>
<?php endif; ?>

<h2 class="h5 mb-3">Statistiques avancées</h2>
<?php require_once __DIR__ . '/../../includes/dashboard_stats_avancees.php'; ?>

<?php require_once '../../includes/footer.php'; ?>

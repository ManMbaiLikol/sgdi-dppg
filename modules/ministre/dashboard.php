<?php
// Dashboard Cabinet Ministre - Décision finale
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('cabinet');

$page_title = 'Cabinet du Ministre - Décisions';

// Statistiques
$stats = [
    'en_attente' => 0,
    'approuves_mois' => 0,
    'rejetes_mois' => 0,
    'total_decisions' => 0
];

// Dossiers en attente de décision ministérielle
$sql_attente = "SELECT COUNT(*) FROM dossiers WHERE statut = 'visa_directeur'";
$stats['en_attente'] = $pdo->query($sql_attente)->fetchColumn();

// Décisions ce mois
$sql_mois = "SELECT decision, COUNT(*) as nb FROM decisions
             WHERE MONTH(date_decision) = MONTH(CURRENT_DATE())
             AND YEAR(date_decision) = YEAR(CURRENT_DATE())
             GROUP BY decision";
$stmt = $pdo->query($sql_mois);
while ($row = $stmt->fetch()) {
    if ($row['decision'] === 'approuve') $stats['approuves_mois'] = $row['nb'];
    if ($row['decision'] === 'rejete') $stats['rejetes_mois'] = $row['nb'];
}

// Total des décisions
$sql_total = "SELECT COUNT(*) FROM decisions";
$stats['total_decisions'] = $pdo->query($sql_total)->fetchColumn();

// Dossiers en attente de décision
$sql = "SELECT d.*,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
        DATE_FORMAT(d.date_modification, '%d/%m/%Y') as date_validation_format,
        u.nom as createur_nom, u.prenom as createur_prenom
        FROM dossiers d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.statut = 'visa_directeur'
        ORDER BY d.date_modification ASC";

$dossiers = $pdo->query($sql)->fetchAll();

// Décisions récentes
try {
    $sql_recent = "SELECT d.*, dec.decision, dec.reference_decision,
                   DATE_FORMAT(dec.date_decision, '%d/%m/%Y') as date_decision_format
                   FROM dossiers d
                   INNER JOIN decisions dec ON d.id = dec.dossier_id
                   ORDER BY dec.date_decision DESC
                   LIMIT 10";

    $stmt_recent = $pdo->query($sql_recent);
    $decisions_recentes = $stmt_recent->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur SQL dashboard ministre: " . $e->getMessage());
    $decisions_recentes = [];
}

require_once '../../includes/ui.php';
require_once '../../includes/taches.php';
$taches = getTachesAFaire('cabinet', $_SESSION['user_id']);

require_once '../../includes/header.php';

echo uiBandeauAccueil(
    trim($_SESSION['user_prenom'] ?? '') ?: ($_SESSION['user_nom'] ?? ''),
    'Cabinet du Ministre · Décision finale',
    $taches,
    '<a class="btn btn-light" href="#a-decider"><i class="fas fa-gavel"></i> Prendre les décisions</a>'
);
?>

<div class="row row-cols-2 row-cols-xl-4 g-3 mb-4">
    <div class="col"><?php echo uiKpiCard('En attente de décision', $stats['en_attente'], 'fa-gavel', 'decision', 'Visés par le Directeur', '#a-decider'); ?></div>
    <div class="col"><?php echo uiKpiCard('Approuvés ce mois', $stats['approuves_mois'], 'fa-circle-check', 'succes'); ?></div>
    <div class="col"><?php echo uiKpiCard('Refusés ce mois', $stats['rejetes_mois'], 'fa-circle-xmark', 'danger'); ?></div>
    <div class="col"><?php echo uiKpiCard('Total des décisions', $stats['total_decisions'], 'fa-book', '', 'Publiées au registre public'); ?></div>
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
                    ['fa-map-location-dot', 'Carte des infrastructures', url('modules/carte/index.php'), 'succes'],
                    ['fa-gavel', 'Mes décisions', url('modules/dossiers/list.php'), 'decision'],
                    ['fa-circle-check', 'Dossiers autorisés', url('modules/dossiers/list.php?statut=autorise'), 'succes'],
                    ['fa-book-open', 'Registre public', url('modules/registre_public/index.php'), 'instruction'],
                ]); ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4" id="a-decider">
    <div class="card-header"><h2 class="card-title-sm">Dossiers en attente de décision ministérielle</h2></div>
    <?php if (empty($dossiers)): ?>
        <?php echo uiEmptyState('Aucun dossier en attente de décision', '', 'fa-circle-check'); ?>
    <?php else: ?>
    <div class="px-3 pt-3">
        <div class="alert-banner phase-decision mb-0">
            <i class="fas fa-circle-info alert-banner-icon" aria-hidden="true"></i>
            <div class="alert-banner-body">Ces dossiers ont reçu les trois visas requis (Chef de Service, Sous-Directeur, Directeur DPPG) et sont prêts pour la décision finale.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sgdi table-hover table-stack">
            <thead><tr><th>N° dossier</th><th>Infrastructure</th><th>Demandeur</th><th>Localisation</th><th>Visé le</th><th>Créé par</th><th class="text-end"><span class="visually-hidden">Action</span></th></tr></thead>
            <tbody>
                <?php foreach ($dossiers as $d): ?>
                <tr>
                    <td data-label="N° dossier"><a class="cell-main mono" href="<?php echo url('modules/dossiers/view.php?id=' . (int) $d['id']); ?>"><?php echo sanitize($d['numero']); ?></a></td>
                    <td data-label="Infrastructure"><?php echo sanitize(getTypeLabel($d['type_infrastructure'], $d['sous_type'])); ?></td>
                    <td data-label="Demandeur"><?php echo sanitize($d['nom_demandeur']); ?></td>
                    <td data-label="Localisation"><?php echo sanitize($d['ville'] ?: ($d['region'] ?: 'Non précisée')); ?></td>
                    <td data-label="Visé le"><?php echo sanitize($d['date_validation_format']); ?></td>
                    <td data-label="Créé par"><?php echo sanitize(trim($d['createur_prenom'] . ' ' . $d['createur_nom'])); ?></td>
                    <td class="text-end cell-actions"><a class="btn btn-sm btn-primary" href="<?php echo url('modules/ministre/decider.php?id=' . (int) $d['id']); ?>"><i class="fas fa-gavel"></i> Décider</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($decisions_recentes)): ?>
<div class="card mb-4">
    <div class="card-header"><h2 class="card-title-sm">Décisions récentes</h2></div>
    <div class="table-responsive">
        <table class="table table-sgdi table-hover table-stack">
            <thead><tr><th>N° dossier</th><th>Infrastructure</th><th>Demandeur</th><th>Décision</th><th>Référence</th><th>Date</th><th class="text-end"><span class="visually-hidden">Action</span></th></tr></thead>
            <tbody>
                <?php foreach ($decisions_recentes as $dec): $approuve = $dec['decision'] === 'approuve'; ?>
                <tr>
                    <td data-label="N° dossier"><a class="cell-main mono" href="<?php echo url('modules/dossiers/view.php?id=' . (int) $dec['id']); ?>"><?php echo sanitize($dec['numero']); ?></a></td>
                    <td data-label="Infrastructure"><?php echo sanitize(getTypeLabel($dec['type_infrastructure'], $dec['sous_type'])); ?></td>
                    <td data-label="Demandeur"><?php echo sanitize($dec['nom_demandeur']); ?></td>
                    <td data-label="Décision"><span class="status-badge phase-<?php echo $approuve ? 'succes' : 'danger'; ?>"><?php echo $approuve ? 'Approuvé' : 'Refusé'; ?></span></td>
                    <td data-label="Référence"><?php echo sanitize($dec['reference_decision'] ?? ''); ?></td>
                    <td data-label="Date"><?php echo sanitize($dec['date_decision_format']); ?></td>
                    <td class="text-end cell-actions"><a class="btn btn-sm btn-outline-secondary" href="<?php echo url('modules/dossiers/view.php?id=' . (int) $dec['id']); ?>">Ouvrir</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<h2 class="h5 mb-3">Statistiques avancées</h2>
<?php require_once __DIR__ . '/../../includes/dashboard_stats_avancees.php'; ?>

<?php require_once '../../includes/footer.php'; ?>

<?php
// Dashboard avancé pour Chef de Service avec graphiques
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('chef_service');

$page_title = 'Dashboard Avancé - Chef de Service';

global $pdo;

// 1. Statistiques du service
$stats = [
    'total_dossiers' => $pdo->query("SELECT COUNT(*) FROM dossiers")->fetchColumn(),
    'en_cours' => $pdo->query("SELECT COUNT(*) FROM dossiers WHERE statut NOT IN ('autorise', 'rejete')")->fetchColumn(),
    'ce_mois' => $pdo->query("SELECT COUNT(*) FROM dossiers WHERE MONTH(date_creation) = MONTH(CURRENT_DATE()) AND YEAR(date_creation) = YEAR(CURRENT_DATE())")->fetchColumn(),
    'a_viser' => $pdo->query("SELECT COUNT(*) FROM dossiers WHERE statut = 'validation_chef_commission'")->fetchColumn()
];

// 2. Répartition par statut (graphique donut)
$sql = "SELECT statut, COUNT(*) as count FROM dossiers GROUP BY statut ORDER BY count DESC";
$stmt = $pdo->query($sql);
$repartition_statuts = [];
while ($row = $stmt->fetch()) {
    $colors = [
        'brouillon' => '#95a5a6', 'en_cours' => '#3498db', 'paye' => '#9b59b6',
        'analyse_daj' => '#e67e22', 'inspecte' => '#f39c12',
        'validation_chef_commission' => '#16a085', 'visa_chef_service' => '#27ae60',
        'visa_sous_directeur' => '#2ecc71', 'visa_directeur' => '#1abc9c',
        'autorise' => '#2ecc71', 'rejete' => '#e74c3c', 'en_huitaine' => '#e67e22'
    ];
    $repartition_statuts[] = [
        'label' => getStatutLabel($row['statut']),
        'value' => (int)$row['count'],
        'color' => $colors[$row['statut']] ?? '#95a5a6'
    ];
}

// 3. Évolution 6 derniers mois
$sql = 'SELECT DATE_FORMAT(date_creation, "%Y-%m") as month, COUNT(*) as count
        FROM dossiers
        WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month ORDER BY month ASC';
$stmt = $pdo->query($sql);
$evolution = [];
while ($row = $stmt->fetch()) {
    $evolution[] = [
        'month' => date('M Y', strtotime($row['month'] . '-01')),
        'crees' => (int)$row['count'],
        'approuves' => 0,
        'rejetes' => 0
    ];
}

// 4. Performance par région
$sql = "SELECT region, COUNT(*) as count FROM dossiers
        WHERE region IS NOT NULL AND region != ''
        GROUP BY region ORDER BY count DESC LIMIT 10";
$stmt = $pdo->query($sql);
$par_region = [];
while ($row = $stmt->fetch()) {
    $par_region[] = ['region' => $row['region'], 'count' => (int)$row['count']];
}

// 5. Types d'infrastructure
$sql = "SELECT type_infrastructure, COUNT(*) as count FROM dossiers
        GROUP BY type_infrastructure ORDER BY count DESC";
$stmt = $pdo->query($sql);
$par_type = [];
while ($row = $stmt->fetch()) {
    $par_type[] = [
        'type' => getTypeInfrastructureLabel($row['type_infrastructure']),
        'count' => (int)$row['count']
    ];
}

// 6. Taux d'approbation
$taux_approbation = 0;
try {
    $sql = 'SELECT
            SUM(CASE WHEN decision = "approuve" THEN 1 ELSE 0 END) as approuves,
            COUNT(*) as total FROM decisions';
    $row = $pdo->query($sql)->fetch();
    $taux_approbation = $row['total'] > 0 ? round(($row['approuves'] / $row['total']) * 100, 1) : 0;
} catch (PDOException $e) {
    error_log("Erreur taux approbation: " . $e->getMessage());
}

// 7. Activité récente (derniers 30 jours)
$sql = "SELECT DATE(date_creation) as jour, COUNT(*) as count FROM dossiers
        WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY jour ORDER BY jour ASC";
$stmt = $pdo->query($sql);
$activite = [];
while ($row = $stmt->fetch()) {
    $activite[] = [
        'month' => date('d/m', strtotime($row['jour'])),
        'crees' => (int)$row['count']
    ];
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-chart-area"></i> Dashboard Avancé - Chef de Service
            </h1>
            <p class="text-muted mb-0">Statistiques et graphiques détaillés</p>
        </div>
        <div>
            <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Dashboard Standard
            </a>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="mb-1 opacity-75">Total Dossiers</h6>
                            <h2 class="mb-0"><?php echo $stats['total_dossiers']; ?></h2>
                        </div>
                        <i class="fas fa-folder fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="mb-1 opacity-75">En Cours</h6>
                            <h2 class="mb-0"><?php echo $stats['en_cours']; ?></h2>
                        </div>
                        <i class="fas fa-clock fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="mb-1 opacity-75">Ce Mois</h6>
                            <h2 class="mb-0"><?php echo $stats['ce_mois']; ?></h2>
                        </div>
                        <i class="fas fa-calendar fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="mb-1 opacity-75">À Viser</h6>
                            <h2 class="mb-0"><?php echo $stats['a_viser']; ?></h2>
                        </div>
                        <i class="fas fa-stamp fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphiques -->
    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-pie"></i> État des Dossiers</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartStatuts" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="fas fa-building"></i> Par Type</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartTypes" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-percentage"></i> Taux d'Approbation</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartTaux" style="max-height: 250px;"></canvas>
                    <div class="text-center mt-2">
                        <small class="text-muted"><?php
                            try {
                                echo $pdo->query("SELECT COUNT(*) FROM decisions")->fetchColumn();
                            } catch (PDOException $e) {
                                echo "0";
                            }
                        ?> décisions</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-line"></i> Activité (30 derniers jours)</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartActivite" style="height: 250px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-map-marker-alt"></i> Top 10 Régions</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartRegions" style="height: 250px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="<?php echo url('assets/js/charts.js'); ?>"></script>

<script>
const repartitionStatuts = <?php echo json_encode($repartition_statuts); ?>;
const parRegion = <?php echo json_encode($par_region); ?>;
const parType = <?php echo json_encode($par_type); ?>;
const tauxApprobation = <?php echo $taux_approbation; ?>;
const activite = <?php echo json_encode($activite); ?>;

document.addEventListener('DOMContentLoaded', function() {
    createStatutChart('chartStatuts', repartitionStatuts);
    createRegionsChart('chartRegions', parRegion);
    createTypesChart('chartTypes', parType);
    createTauxReussiteGauge('chartTaux', tauxApprobation);

    // Graphique d'activité (ligne)
    const ctx = document.getElementById('chartActivite');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: activite.map(a => a.month),
            datasets: [{
                label: 'Dossiers créés',
                data: activite.map(a => a.crees),
                borderColor: 'rgba(23, 162, 184, 1)',
                backgroundColor: 'rgba(23, 162, 184, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: { size: 11 }
                    }
                },
                x: {
                    ticks: {
                        font: { size: 10 },
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            }
        }
    });
});
</script>

<!-- Statistiques Avancées -->
<div class="container-fluid mt-4">
    <h2 class="h4 mb-3">
        <i class="fas fa-chart-bar"></i> Statistiques Avancées
    </h2>
    <?php require_once __DIR__ . '/../../includes/dashboard_stats_avancees.php'; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>

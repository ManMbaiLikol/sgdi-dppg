<?php
// Dashboard avancé pour Admin avec graphiques
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('admin');

$page_title = 'Dashboard Avancé - Administrateur';

// Récupérer toutes les statistiques
global $pdo;

// 1. Statistiques générales
$stats_generales = [
    'total_dossiers' => $pdo->query("SELECT COUNT(*) FROM dossiers")->fetchColumn(),
    'total_utilisateurs' => $pdo->query("SELECT COUNT(*) FROM users WHERE actif = 1")->fetchColumn(),
    'total_paiements' => $pdo->query("SELECT COUNT(*) FROM paiements")->fetchColumn(),
    'montant_total' => $pdo->query("SELECT COALESCE(SUM(montant), 0) FROM paiements")->fetchColumn()
];

// 2. Répartition par statut (pour graphique donut)
$sql = "SELECT statut, COUNT(*) as count FROM dossiers GROUP BY statut ORDER BY count DESC";
$stmt = $pdo->query($sql);
$repartition_statuts = [];
while ($row = $stmt->fetch()) {
    $colors = [
        'brouillon' => '#95a5a6',
        'en_cours' => '#3498db',
        'paye' => '#9b59b6',
        'analyse_daj' => '#e67e22',
        'inspecte' => '#f39c12',
        'validation_chef_commission' => '#16a085',
        'visa_chef_service' => '#27ae60',
        'visa_sous_directeur' => '#2ecc71',
        'visa_directeur' => '#1abc9c',
        'autorise' => '#2ecc71',
        'rejete' => '#e74c3c',
        'en_huitaine' => '#e67e22'
    ];

    $repartition_statuts[] = [
        'label' => getStatutLabel($row['statut']),
        'value' => (int)$row['count'],
        'color' => $colors[$row['statut']] ?? '#95a5a6'
    ];
}

// 3. Évolution mensuelle (6 derniers mois)
$sql = 'SELECT
        DATE_FORMAT(date_creation, "%Y-%m") as month,
        COUNT(*) as crees
        FROM dossiers
        WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC';
$stmt = $pdo->query($sql);
$evolution = [];
while ($row = $stmt->fetch()) {
    $month_fr = date('M Y', strtotime($row['month'] . '-01'));
    $evolution[$row['month']] = [
        'month' => $month_fr,
        'crees' => (int)$row['crees'],
        'approuves' => 0,
        'rejetes' => 0
    ];
}

// Ajouter les approuvés/rejetés
try {
    $sql = 'SELECT
            DATE_FORMAT(date_decision, "%Y-%m") as month,
            decision,
            COUNT(*) as count
            FROM decisions
            WHERE date_decision >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY month, decision';
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        if (isset($evolution[$row['month']])) {
            if ($row['decision'] === 'approuve') {
                $evolution[$row['month']]['approuves'] = (int)$row['count'];
            } else {
                $evolution[$row['month']]['rejetes'] = (int)$row['count'];
            }
        }
    }
} catch (PDOException $e) {
    error_log("Erreur requête decisions: " . $e->getMessage());
}
$evolution = array_values($evolution);

// 4. Top 10 régions
$sql = "SELECT region, COUNT(*) as count
        FROM dossiers
        WHERE region IS NOT NULL AND region != ''
        GROUP BY region
        ORDER BY count DESC
        LIMIT 10";
$stmt = $pdo->query($sql);
$top_regions = [];
while ($row = $stmt->fetch()) {
    $top_regions[] = [
        'region' => $row['region'],
        'count' => (int)$row['count']
    ];
}

// 5. Types d'infrastructure
$sql = "SELECT type_infrastructure, COUNT(*) as count
        FROM dossiers
        GROUP BY type_infrastructure
        ORDER BY count DESC";
$stmt = $pdo->query($sql);
$types_infra = [];
while ($row = $stmt->fetch()) {
    $types_infra[] = [
        'type' => getTypeInfrastructureLabel($row['type_infrastructure']),
        'count' => (int)$row['count']
    ];
}

// 6. Temps moyen de traitement par mois
$temps_traitement = [];
try {
    $sql = 'SELECT
            DATE_FORMAT(dec.date_decision, "%Y-%m") as month,
            AVG(DATEDIFF(dec.date_decision, d.date_creation)) as duree
            FROM decisions dec
            INNER JOIN dossiers d ON dec.dossier_id = d.id
            WHERE dec.date_decision >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY month
            ORDER BY month ASC';
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch()) {
        $month_fr = date('M Y', strtotime($row['month'] . '-01'));
        $temps_traitement[] = [
            'month' => $month_fr,
            'duree' => round((float)$row['duree'], 1)
        ];
    }
} catch (PDOException $e) {
    error_log("Erreur requête temps traitement: " . $e->getMessage());
}

// 7. Taux d'approbation
$taux_approbation = 0;
try {
    $sql = 'SELECT
            SUM(CASE WHEN decision = "approuve" THEN 1 ELSE 0 END) as approuves,
            COUNT(*) as total
            FROM decisions';
    $row = $pdo->query($sql)->fetch();
    $taux_approbation = $row['total'] > 0 ? round(($row['approuves'] / $row['total']) * 100, 1) : 0;
} catch (PDOException $e) {
    error_log("Erreur requête taux approbation: " . $e->getMessage());
}

// 8. Performance par catégorie (pour radar)
$performance = [
    ['category' => 'Rapidité', 'score' => 75],
    ['category' => 'Qualité', 'score' => 85],
    ['category' => 'Conformité', 'score' => 90],
    ['category' => 'Satisfaction', 'score' => 80],
    ['category' => 'Efficacité', 'score' => 70]
];

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-chart-line"></i> Dashboard Avancé
            </h1>
            <p class="text-muted mb-0">Vue d'ensemble avec statistiques et graphiques</p>
        </div>
        <div>
            <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Dashboard Standard
            </a>
        </div>
    </div>

    <!-- Statistiques principales -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Dossiers</h6>
                            <h3 class="mb-0"><?php echo number_format($stats_generales['total_dossiers']); ?></h3>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-folder fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Utilisateurs Actifs</h6>
                            <h3 class="mb-0"><?php echo $stats_generales['total_utilisateurs']; ?></h3>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Paiements</h6>
                            <h3 class="mb-0"><?php echo number_format($stats_generales['total_paiements']); ?></h3>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-money-bill fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Montant Total</h6>
                            <h3 class="mb-0"><?php echo number_format($stats_generales['montant_total'], 0, ',', ' '); ?></h3>
                            <small class="text-muted">FCFA</small>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-coins fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphiques Ligne 1 -->
    <div class="row mb-4">
        <!-- Répartition par statut -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie"></i> Répartition par Statut
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="chartStatuts" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Types d'infrastructure -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-building"></i> Types d'Infrastructure
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="chartTypes" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphiques Ligne 2 -->
    <div class="row mb-4">
        <!-- Évolution mensuelle -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar"></i> Évolution Mensuelle (6 derniers mois)
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="chartEvolution" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Taux d'approbation -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-percentage"></i> Taux d'Approbation
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="chartTaux" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphiques Ligne 3 -->
    <div class="row mb-4">
        <!-- Top régions -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-map-marker-alt"></i> Top 10 Régions
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="chartRegions" style="height: 350px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Temps de traitement -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-clock"></i> Temps Moyen de Traitement
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="chartTemps" style="height: 350px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="<?php echo url('assets/js/charts.js'); ?>"></script>

<script>
// Données PHP vers JavaScript
const repartitionStatuts = <?php echo json_encode($repartition_statuts); ?>;
const evolution = <?php echo json_encode($evolution); ?>;
const topRegions = <?php echo json_encode($top_regions); ?>;
const typesInfra = <?php echo json_encode($types_infra); ?>;
const tempsTraitement = <?php echo json_encode($temps_traitement); ?>;
const tauxApprobation = <?php echo $taux_approbation; ?>;

// Créer les graphiques
document.addEventListener('DOMContentLoaded', function() {
    createStatutChart('chartStatuts', repartitionStatuts);
    createEvolutionChart('chartEvolution', evolution);
    createRegionsChart('chartRegions', topRegions);
    createTypesChart('chartTypes', typesInfra);
    createTempsTraitementChart('chartTemps', tempsTraitement);
    createTauxReussiteGauge('chartTaux', tauxApprobation);
});
</script>

<?php require_once '../../includes/footer.php'; ?>

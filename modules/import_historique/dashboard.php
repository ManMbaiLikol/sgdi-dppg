<?php
// Tableau de bord des imports historiques - SGDI
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

// Vérifier les permissions
if (!peutImporterHistorique($_SESSION['user_role'])) {
    redirect(url('dashboard.php'), 'Vous n\'avez pas les permissions pour accéder à ce module', 'error');
}

// Récupérer les statistiques
$stats = getStatistiquesImport();
$historique = getHistoriqueImports(100);

// Statistiques par type
$sql = "SELECT ti.nom, COUNT(*) as total
        FROM dossiers d
        JOIN types_infrastructure ti ON d.type_infrastructure_id = ti.id
        WHERE d.est_historique = 1
        GROUP BY ti.nom
        ORDER BY total DESC";
$statsByType = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Statistiques par région
$sql = "SELECT region, COUNT(*) as total
        FROM dossiers
        WHERE est_historique = 1
        GROUP BY region
        ORDER BY total DESC";
$statsByRegion = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Tableau de bord des imports";
include '../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-chart-bar"></i> Tableau de bord des imports historiques</h2>
                    <p class="text-muted">Suivi des dossiers historiques importés</p>
                </div>
                <div>
                    <a href="<?= url('modules/import_historique/index.php') ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nouvel import
                    </a>
                </div>
            </div>

            <?php if ($stats['total'] > 0): ?>
                <!-- Statistiques globales -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white shadow">
                            <div class="card-body text-center">
                                <h2 class="display-4"><?= number_format($stats['total']) ?></h2>
                                <p class="mb-0">Dossiers historiques</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white shadow">
                            <div class="card-body text-center">
                                <h2 class="display-4"><?= $stats['nb_importeurs'] ?></h2>
                                <p class="mb-0">Utilisateurs importeurs</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white shadow">
                            <div class="card-body text-center">
                                <h5>Premier import</h5>
                                <h4><?= date('d/m/Y', strtotime($stats['premier_import'])) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white shadow">
                            <div class="card-body text-center">
                                <h5>Dernier import</h5>
                                <h4><?= date('d/m/Y', strtotime($stats['dernier_import'])) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Répartition par type -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Répartition par type d'infrastructure</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="chartByType"></canvas>
                                <hr>
                                <table class="table table-sm">
                                    <tbody>
                                        <?php foreach ($statsByType as $stat): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($stat['nom']) ?></td>
                                                <td class="text-right">
                                                    <strong><?= number_format($stat['total']) ?></strong>
                                                    <small class="text-muted">
                                                        (<?= number_format($stat['total'] / $stats['total'] * 100, 1) ?>%)
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Répartition par région -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-map-marked-alt"></i> Répartition par région</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="chartByRegion"></canvas>
                                <hr>
                                <table class="table table-sm">
                                    <tbody>
                                        <?php foreach ($statsByRegion as $stat): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($stat['region']) ?></td>
                                                <td class="text-right">
                                                    <strong><?= number_format($stat['total']) ?></strong>
                                                    <small class="text-muted">
                                                        (<?= number_format($stat['total'] / $stats['total'] * 100, 1) ?>%)
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historique des imports -->
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Historique des imports</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($historique)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Date</th>
                                            <th>Heure</th>
                                            <th>Importeur</th>
                                            <th class="text-center">Nombre de dossiers</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historique as $import): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($import['importe_le'])) ?></td>
                                                <td><?= date('H:i', strtotime($import['importe_le'])) ?></td>
                                                <td>
                                                    <i class="fas fa-user"></i>
                                                    <?= htmlspecialchars($import['prenom'] . ' ' . $import['nom']) ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge badge-primary badge-pill">
                                                        <?= $import['nb_dossiers'] ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">Aucun import pour le moment</p>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- Aucun import -->
                <div class="card shadow">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-inbox fa-5x text-muted mb-4"></i>
                        <h4>Aucun dossier historique importé</h4>
                        <p class="text-muted">Commencez par importer vos premiers dossiers historiques</p>
                        <a href="<?= url('modules/import_historique/index.php') ?>" class="btn btn-primary btn-lg mt-3">
                            <i class="fas fa-plus"></i> Démarrer l'import
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($stats['total'] > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Graphique par type
const ctxType = document.getElementById('chartByType').getContext('2d');
new Chart(ctxType, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($statsByType, 'nom')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($statsByType, 'total')) ?>,
            backgroundColor: [
                '#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6f42c1'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Graphique par région
const ctxRegion = document.getElementById('chartByRegion').getContext('2d');
new Chart(ctxRegion, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($statsByRegion, 'region')) ?>,
        datasets: [{
            label: 'Nombre de dossiers',
            data: <?= json_encode(array_column($statsByRegion, 'total')) ?>,
            backgroundColor: '#28a745'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>

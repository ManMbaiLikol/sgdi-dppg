<?php
// Statistiques publiques
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$page_title = 'Statistiques Publiques - Infrastructures Pétrolières';

// Statistiques générales
$stats_generales = $pdo->query("
    SELECT
        COUNT(DISTINCT CASE WHEN statut = 'autorise' THEN id END) as total_autorise,
        COUNT(DISTINCT CASE WHEN statut = 'refuse' THEN id END) as total_refuse,
        COUNT(DISTINCT CASE WHEN statut = 'ferme' THEN id END) as total_ferme,
        COUNT(DISTINCT CASE WHEN type_infrastructure = 'station_service' AND statut = 'autorise' THEN id END) as stations,
        COUNT(DISTINCT CASE WHEN type_infrastructure = 'point_consommateur' AND statut = 'autorise' THEN id END) as points,
        COUNT(DISTINCT CASE WHEN type_infrastructure = 'depot_gpl' AND statut = 'autorise' THEN id END) as depots,
        COUNT(DISTINCT CASE WHEN type_infrastructure = 'centre_emplisseur' AND statut = 'autorise' THEN id END) as centres
    FROM dossiers
")->fetch();

// Statistiques par région
$stats_regions = $pdo->query("
    SELECT region, COUNT(*) as total
    FROM dossiers
    WHERE statut = 'autorise' AND region IS NOT NULL
    GROUP BY region
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();

// Statistiques par type et sous-type
$stats_types = $pdo->query("
    SELECT type_infrastructure, sous_type, COUNT(*) as total
    FROM dossiers
    WHERE statut = 'autorise'
    GROUP BY type_infrastructure, sous_type
    ORDER BY total DESC
")->fetchAll();

// Évolution mensuelle des autorisations (12 derniers mois)
$stats_evolution = $pdo->query("
    SELECT
        DATE_FORMAT(dec.date_decision, '%Y-%m') as mois,
        DATE_FORMAT(dec.date_decision, '%M %Y') as mois_format,
        COUNT(*) as total
    FROM decisions dec
    JOIN dossiers d ON dec.dossier_id = d.id
    WHERE dec.decision = 'approuve' AND dec.date_decision >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(dec.date_decision, '%Y-%m')
    ORDER BY mois ASC
")->fetchAll();

// Top 10 opérateurs
$top_operateurs = $pdo->query("
    SELECT operateur_proprietaire, COUNT(*) as total
    FROM dossiers
    WHERE statut = 'autorise' AND operateur_proprietaire IS NOT NULL AND operateur_proprietaire != ''
    GROUP BY operateur_proprietaire
    ORDER BY total DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - MINEE/DPPG</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <style>
        :root {
            --primary-color: #1e3a8a;
            --secondary-color: #059669;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .public-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%);
            color: white;
            padding: 2rem 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .table-responsive {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="public-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2"><i class="fas fa-chart-line"></i> Statistiques Publiques</h1>
                    <p class="mb-0">Infrastructures Pétrolières - MINEE/DPPG</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php" class="btn btn-light me-2">
                        <i class="fas fa-list"></i> Registre
                    </a>
                    <a href="carte.php" class="btn btn-light">
                        <i class="fas fa-map-marked-alt"></i> Carte
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <!-- Statistiques générales -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="display-4 text-success mb-2">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2><?php echo number_format($stats_generales['total_autorise']); ?></h2>
                    <p class="text-muted mb-0">Infrastructures autorisées</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="display-4 text-primary mb-2">
                        <i class="fas fa-gas-pump"></i>
                    </div>
                    <h2><?php echo number_format($stats_generales['stations']); ?></h2>
                    <p class="text-muted mb-0">Stations-service</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="display-4 text-info mb-2">
                        <i class="fas fa-industry"></i>
                    </div>
                    <h2><?php echo number_format($stats_generales['points']); ?></h2>
                    <p class="text-muted mb-0">Points consommateurs</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="display-4 text-warning mb-2">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <h2><?php echo number_format($stats_generales['depots'] + $stats_generales['centres']); ?></h2>
                    <p class="text-muted mb-0">Dépôts & Centres GPL</p>
                </div>
            </div>
        </div>

        <!-- Graphiques -->
        <div class="row mb-4">
            <!-- Répartition par type -->
            <div class="col-md-6 mb-4">
                <div class="stat-card">
                    <h4 class="mb-4"><i class="fas fa-chart-pie"></i> Répartition par type d'infrastructure</h4>
                    <div class="chart-container">
                        <canvas id="chartTypes"></canvas>
                    </div>
                </div>
            </div>

            <!-- Évolution mensuelle -->
            <div class="col-md-6 mb-4">
                <div class="stat-card">
                    <h4 class="mb-4"><i class="fas fa-chart-line"></i> Évolution des autorisations (12 mois)</h4>
                    <div class="chart-container">
                        <canvas id="chartEvolution"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tableaux statistiques -->
        <div class="row">
            <!-- Top régions -->
            <div class="col-md-6 mb-4">
                <div class="stat-card">
                    <h4 class="mb-4"><i class="fas fa-map"></i> Top 10 régions</h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Région</th>
                                    <th class="text-end">Nombre</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($stats_regions as $region): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($region['region']); ?></td>
                                        <td class="text-end">
                                            <strong><?php echo number_format($region['total']); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Top opérateurs -->
            <div class="col-md-6 mb-4">
                <div class="stat-card">
                    <h4 class="mb-4"><i class="fas fa-building"></i> Top 10 opérateurs</h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Opérateur</th>
                                    <th class="text-end">Nombre</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($top_operateurs as $op): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($op['operateur_proprietaire']); ?></td>
                                        <td class="text-end">
                                            <strong><?php echo number_format($op['total']); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Répartition implantation/reprise -->
        <div class="row">
            <div class="col-md-12">
                <div class="stat-card">
                    <h4 class="mb-4"><i class="fas fa-chart-bar"></i> Répartition par type et sous-type</h4>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Type d'infrastructure</th>
                                    <th>Sous-type</th>
                                    <th class="text-end">Nombre</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($stats_types as $type): ?>
                                    <tr>
                                        <td><?php echo formatTypeInfrastructure($type['type_infrastructure']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo ucfirst($type['sous_type']); ?></span></td>
                                        <td class="text-end"><strong><?php echo number_format($type['total']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Graphique répartition par type
        const ctxTypes = document.getElementById('chartTypes').getContext('2d');
        new Chart(ctxTypes, {
            type: 'pie',
            data: {
                labels: ['Stations-service', 'Points consommateurs', 'Dépôts GPL', 'Centres emplisseurs'],
                datasets: [{
                    data: [
                        <?php echo $stats_generales['stations']; ?>,
                        <?php echo $stats_generales['points']; ?>,
                        <?php echo $stats_generales['depots']; ?>,
                        <?php echo $stats_generales['centres']; ?>
                    ],
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Graphique évolution
        const ctxEvolution = document.getElementById('chartEvolution').getContext('2d');
        new Chart(ctxEvolution, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($stats_evolution, 'mois_format')); ?>,
                datasets: [{
                    label: 'Autorisations',
                    data: <?php echo json_encode(array_column($stats_evolution, 'total')); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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
</body>
</html>

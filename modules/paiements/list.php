<?php
// Consultation des paiements - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

// Seuls certains rôles peuvent consulter les paiements
requireAnyRole(['chef_service', 'admin', 'billeteur', 'directeur']);

$page_title = 'Consultation des paiements';

// Filtres
$filters = [
    'search' => sanitize($_GET['search'] ?? ''),
    'mode_paiement' => sanitize($_GET['mode_paiement'] ?? ''),
    'date_debut' => sanitize($_GET['date_debut'] ?? ''),
    'date_fin' => sanitize($_GET['date_fin'] ?? ''),
    'billeteur_id' => intval($_GET['billeteur_id'] ?? 0)
];

// Si billeteur, filtrer uniquement ses paiements
if ($_SESSION['user_role'] === 'billeteur') {
    $filters['billeteur_id'] = $_SESSION['user_id'];
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Récupérer les données
$paiements = getPaiementsAvecFiltres($filters, $limit, $offset);
$total_paiements = countPaiementsAvecFiltres($filters);
$stats = getStatistiquesPaiements($filters);

$total_pages = ceil($total_paiements / $limit);
$modes_paiement = getModesPaiement();

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-money-check-alt"></i> <?php echo $page_title; ?>
            </h1>
            <p class="text-muted">
                <?php if ($_SESSION['user_role'] === 'chef_service'): ?>
                    Suivi des paiements validés par le billeteur
                <?php elseif ($_SESSION['user_role'] === 'billeteur'): ?>
                    Historique de vos enregistrements de paiements
                <?php else: ?>
                    Consultation des paiements du système
                <?php endif; ?>
            </p>
        </div>
        <div>
            <?php if (!empty($paiements)): ?>
            <a href="<?php echo url('modules/paiements/export.php?' . http_build_query($filters)); ?>" class="btn btn-outline-success">
                <i class="fas fa-file-excel"></i> Exporter
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alerte pour paiements en retard -->
    <?php
    $dossiers_retard = countDossiersEnRetardPaiement();
    if ($dossiers_retard > 0 && in_array($_SESSION['user_role'], ['chef_service', 'billeteur'])):
    ?>
    <div class="alert alert-warning mb-4">
        <h5><i class="fas fa-exclamation-triangle"></i> Attention - Paiements en retard</h5>
        <p class="mb-2">
            <strong><?php echo $dossiers_retard; ?> dossier<?php echo $dossiers_retard > 1 ? 's' : ''; ?></strong>
            en attente de paiement depuis plus de 30 jours.
        </p>
        <a href="<?php echo url('modules/paiements/retards.php'); ?>" class="btn btn-sm btn-outline-warning">
            <i class="fas fa-eye"></i> Voir les dossiers en retard
        </a>
    </div>
    <?php endif; ?>

    <!-- Statistiques rapides -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <h4 class="text-success"><?php echo $stats['paiements_valides']; ?></h4>
                    <p class="text-muted mb-0">Paiements validés</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                    <h4 class="text-warning"><?php echo $stats['paiements_attente']; ?></h4>
                    <p class="text-muted mb-0">Paiements en attente</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                    <h4 class="text-danger"><?php echo $stats['dossiers_rejetes']; ?></h4>
                    <p class="text-muted mb-0">Dossiers rejetés</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-exclamation-triangle fa-2x text-info mb-2"></i>
                    <h4 class="text-info"><?php echo $dossiers_retard; ?></h4>
                    <p class="text-muted mb-0">En retard (+30j)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Répartition par mode de paiement -->
    <?php if (!empty($stats['par_mode'])): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Répartition par mode de paiement</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($stats['par_mode'] as $mode => $data): ?>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <i class="<?php echo getModePaiementIcon($mode); ?> fa-2x text-<?php echo getModePaiementColor($mode); ?>"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1"><?php echo $modes_paiement[$mode]['label'] ?? ucfirst($mode); ?></h6>
                                    <p class="mb-0 text-muted">
                                        <?php echo $data['count']; ?> paiements - <?php echo formatMontantPaiement($data['montant'], 'XAF'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Graphiques -->
    <div class="row mb-4">
        <!-- Courbe d'évolution des paiements par mois -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-line"></i> Évolution des paiements (12 derniers mois)</h6>
                </div>
                <div class="card-body">
                    <canvas id="evolutionChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Histogramme des montants par type de dossier -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Montants encaissés par type de dossier</h6>
                </div>
                <div class="card-body">
                    <canvas id="typeChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Recherche</label>
                    <input type="text" class="form-control" id="search" name="search"
                           placeholder="Numéro, demandeur, référence..."
                           value="<?php echo sanitize($filters['search']); ?>">
                </div>
                <div class="col-md-2">
                    <label for="mode_paiement" class="form-label">Mode</label>
                    <select class="form-select" id="mode_paiement" name="mode_paiement">
                        <option value="">Tous</option>
                        <?php foreach ($modes_paiement as $mode => $info): ?>
                        <option value="<?php echo $mode; ?>" <?php echo $filters['mode_paiement'] === $mode ? 'selected' : ''; ?>>
                            <?php echo $info['label']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_debut" class="form-label">Du</label>
                    <input type="date" class="form-control" id="date_debut" name="date_debut"
                           value="<?php echo sanitize($filters['date_debut']); ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_fin" class="form-label">Au</label>
                    <input type="date" class="form-control" id="date_fin" name="date_fin"
                           value="<?php echo sanitize($filters['date_fin']); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="<?php echo url('modules/paiements/list.php'); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-eraser"></i> Effacer
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des paiements -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list"></i> Paiements enregistrés
                <small class="text-muted">(<?php echo $total_paiements; ?> paiement<?php echo $total_paiements > 1 ? 's' : ''; ?>)</small>
            </h5>
        </div>

        <?php if (empty($paiements)): ?>
        <div class="card-body text-center py-5">
            <i class="fas fa-money-check fa-3x text-muted mb-3"></i>
            <p class="text-muted">Aucun paiement trouvé avec les critères sélectionnés</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Dossier</th>
                        <th>Demandeur</th>
                        <th>Montant</th>
                        <th>Mode paiement</th>
                        <th>Référence</th>
                        <th>Date paiement</th>
                        <th>Billeteur</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paiements as $paiement): ?>
                    <tr>
                        <td>
                            <strong><?php echo sanitize($paiement['dossier_numero']); ?></strong>
                            <br><small class="text-muted"><?php echo sanitize($paiement['type_infrastructure']); ?></small>
                        </td>
                        <td>
                            <?php echo sanitize($paiement['nom_demandeur']); ?>
                            <br><small class="text-muted"><?php echo sanitize($paiement['region'] . ' - ' . $paiement['ville']); ?></small>
                        </td>
                        <td>
                            <strong class="text-success">
                                <?php echo formatMontantPaiement($paiement['montant'], $paiement['devise']); ?>
                            </strong>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo getModePaiementColor($paiement['mode_paiement']); ?>">
                                <i class="<?php echo getModePaiementIcon($paiement['mode_paiement']); ?>"></i>
                                <?php echo $modes_paiement[$paiement['mode_paiement']]['label'] ?? ucfirst($paiement['mode_paiement']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($paiement['reference_paiement']): ?>
                                <code><?php echo sanitize($paiement['reference_paiement']); ?></code>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo formatDateTime($paiement['date_paiement'], 'd/m/Y'); ?>
                            <br><small class="text-muted">Enreg: <?php echo formatDateTime($paiement['date_enregistrement'], 'd/m H:i'); ?></small>
                        </td>
                        <td>
                            <?php if ($paiement['billeteur_nom']): ?>
                                <?php echo sanitize($paiement['billeteur_prenom'] . ' ' . $paiement['billeteur_nom']); ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="<?php echo url('modules/paiements/view.php?id=' . $paiement['id']); ?>"
                                   class="btn btn-outline-primary btn-sm"
                                   title="Voir détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?php echo url('modules/paiements/export_pdf.php?id=' . $paiement['id']); ?>"
                                   class="btn btn-outline-success btn-sm"
                                   title="Télécharger le reçu PDF"
                                   target="_blank">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                                <a href="<?php echo url('modules/dossiers/view.php?id=' . $paiement['dossier_id']); ?>"
                                   class="btn btn-outline-info btn-sm"
                                   title="Voir dossier">
                                    <i class="fas fa-folder"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php
                    $query_params = array_filter($filters, function($value) {
                        return $value !== '' && $value !== 0;
                    });
                    ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($query_params) ? '&' . http_build_query($query_params) : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Données pour le graphique d'évolution des paiements
const evolutionData = {
    labels: [
        <?php
        // Générer les 12 derniers mois
        $mois_labels = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m', strtotime("-$i months"));
            $mois_labels[] = "'" . date('M Y', strtotime("-$i months")) . "'";
        }
        echo implode(', ', $mois_labels);
        ?>
    ],
    datasets: [{
        label: 'Nombre de paiements',
        data: [
            <?php
            $data_values = [];
            for ($i = 11; $i >= 0; $i--) {
                $date = date('Y-m', strtotime("-$i months"));
                $count = $stats['par_mois'][$date]['count'] ?? 0;
                $data_values[] = $count;
            }
            echo implode(', ', $data_values);
            ?>
        ],
        borderColor: 'rgb(75, 192, 192)',
        backgroundColor: 'rgba(75, 192, 192, 0.2)',
        tension: 0.4
    }]
};

// Configuration du graphique d'évolution
const evolutionConfig = {
    type: 'line',
    data: evolutionData,
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
};

// Données pour l'histogramme par type de dossier
const typeData = {
    labels: [
        <?php
        $type_labels = [];
        foreach ($stats['par_type_dossier'] as $type => $data) {
            $type_labels[] = "'" . ucfirst(str_replace('_', ' ', $type)) . "'";
        }
        echo implode(', ', $type_labels);
        ?>
    ],
    datasets: [{
        label: 'Montant (F CFA)',
        data: [
            <?php
            $montant_values = [];
            foreach ($stats['par_type_dossier'] as $type => $data) {
                $montant_values[] = $data['montant'];
            }
            echo implode(', ', $montant_values);
            ?>
        ],
        backgroundColor: [
            'rgba(255, 99, 132, 0.8)',
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 205, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)'
        ],
        borderColor: [
            'rgba(255, 99, 132, 1)',
            'rgba(54, 162, 235, 1)',
            'rgba(255, 205, 86, 1)',
            'rgba(75, 192, 192, 1)',
            'rgba(153, 102, 255, 1)',
            'rgba(255, 159, 64, 1)'
        ],
        borderWidth: 1
    }]
};

// Configuration de l'histogramme
const typeConfig = {
    type: 'bar',
    data: typeData,
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
                    callback: function(value) {
                        return new Intl.NumberFormat('fr-FR').format(value) + ' F CFA';
                    }
                }
            }
        }
    }
};

// Initialiser les graphiques
document.addEventListener('DOMContentLoaded', function() {
    // Graphique d'évolution
    const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
    new Chart(evolutionCtx, evolutionConfig);

    // Histogramme par type
    const typeCtx = document.getElementById('typeChart').getContext('2d');
    new Chart(typeCtx, typeConfig);
});
</script>

<?php require_once '../../includes/footer.php'; ?>
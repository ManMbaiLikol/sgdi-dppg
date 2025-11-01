<?php
/**
 * Module Admin GPS - Gestion des positions géographiques des stations
 * Interface pour visualiser et corriger les coordonnées GPS
 */

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireLogin();

// Vérifier que l'utilisateur est admin ou chef service
if (!hasAnyRole(['admin', 'chef_service'])) {
    redirect(url('dashboard.php'), 'Accès non autorisé', 'error');
}

// Paramètres de pagination et filtres
$page = intval($_GET['page'] ?? 1);
$per_page = 50;
$offset = ($page - 1) * $per_page;

$filters = [
    'search' => cleanInput($_GET['search'] ?? ''),
    'region' => cleanInput($_GET['region'] ?? ''),
    'has_gps' => $_GET['has_gps'] ?? '',
    'est_historique' => $_GET['est_historique'] ?? ''
];

// Construire la requête
$where = ['1=1'];
$params = [];

if (!empty($filters['search'])) {
    $where[] = '(numero LIKE ? OR nom_demandeur LIKE ? OR ville LIKE ?)';
    $search = '%' . $filters['search'] . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

if (!empty($filters['region'])) {
    $where[] = 'region = ?';
    $params[] = $filters['region'];
}

if ($filters['has_gps'] === 'yes') {
    $where[] = 'coordonnees_gps IS NOT NULL AND coordonnees_gps != ""';
} elseif ($filters['has_gps'] === 'no') {
    $where[] = '(coordonnees_gps IS NULL OR coordonnees_gps = "")';
}

if ($filters['est_historique'] === '1') {
    $where[] = 'est_historique = 1';
} elseif ($filters['est_historique'] === '0') {
    $where[] = '(est_historique = 0 OR est_historique IS NULL)';
}

$where_sql = implode(' AND ', $where);

// Compter le total
$count_sql = "SELECT COUNT(*) FROM dossiers WHERE $where_sql";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_count = $stmt->fetchColumn();

// Récupérer les dossiers
$sql = "SELECT
    id,
    numero,
    nom_demandeur,
    type_infrastructure,
    sous_type,
    region,
    ville,
    quartier,
    coordonnees_gps,
    est_historique,
    score_matching_osm,
    source_gps
FROM dossiers
WHERE $where_sql
ORDER BY
    CASE WHEN coordonnees_gps IS NULL OR coordonnees_gps = '' THEN 0 ELSE 1 END,
    region,
    ville
LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dossiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les régions pour le filtre
$regions = $pdo->query("SELECT DISTINCT region FROM dossiers WHERE region IS NOT NULL AND region != '' ORDER BY region")->fetchAll(PDO::FETCH_COLUMN);

// Statistiques
$stats_with_gps = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE coordonnees_gps IS NOT NULL AND coordonnees_gps != ''")->fetchColumn();
$stats_without_gps = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE coordonnees_gps IS NULL OR coordonnees_gps = ''")->fetchColumn();
$stats_total = $stats_with_gps + $stats_without_gps;

$page_title = 'Gestion des Positions GPS';
require_once '../../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<!-- Mobile Responsive pour cartes -->
<link rel="stylesheet" href="../../assets/css/map-mobile-responsive.css">

<style>
.gps-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.gps-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.table-actions {
    white-space: nowrap;
}

.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.stat-card.success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.stat-card.warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-card .value {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.stat-card .label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.filters-card {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
}

.table-container {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.table {
    margin-bottom: 0;
}

.table thead th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

.btn-edit-gps {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
}
</style>

<div class="container-fluid mt-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-map-marked-alt"></i> Gestion des Positions GPS
            </h1>
            <p class="text-muted mb-0">Interface d'administration pour gérer les coordonnées géographiques</p>
        </div>
        <div>
            <a href="map_editor.php" class="btn btn-primary">
                <i class="fas fa-map"></i> Éditeur de Carte Global
            </a>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="stats-cards">
        <div class="stat-card">
            <div class="value"><?= number_format($stats_total) ?></div>
            <div class="label">Total Stations</div>
        </div>
        <div class="stat-card success">
            <div class="value"><?= number_format($stats_with_gps) ?></div>
            <div class="label">Avec GPS (<?= $stats_total > 0 ? round(($stats_with_gps / $stats_total) * 100, 1) : 0 ?>%)</div>
        </div>
        <div class="stat-card warning">
            <div class="value"><?= number_format($stats_without_gps) ?></div>
            <div class="label">Sans GPS (<?= $stats_total > 0 ? round(($stats_without_gps / $stats_total) * 100, 1) : 0 ?>%)</div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="filters-card">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label class="form-label"><i class="fas fa-search"></i> Recherche</label>
                <input type="text" class="form-control" name="search"
                       placeholder="N° dossier, opérateur, ville..."
                       value="<?= htmlspecialchars($filters['search']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="fas fa-map-marker-alt"></i> Région</label>
                <select class="form-select" name="region">
                    <option value="">Toutes</option>
                    <?php foreach ($regions as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>"
                                <?= $filters['region'] === $r ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="fas fa-gps-fixed"></i> GPS</label>
                <select class="form-select" name="has_gps">
                    <option value="">Tous</option>
                    <option value="yes" <?= $filters['has_gps'] === 'yes' ? 'selected' : '' ?>>Avec GPS</option>
                    <option value="no" <?= $filters['has_gps'] === 'no' ? 'selected' : '' ?>>Sans GPS</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="fas fa-history"></i> Type</label>
                <select class="form-select" name="est_historique">
                    <option value="">Tous</option>
                    <option value="1" <?= $filters['est_historique'] === '1' ? 'selected' : '' ?>>Historiques</option>
                    <option value="0" <?= $filters['est_historique'] === '0' ? 'selected' : '' ?>>Nouveaux</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filtrer
                </button>
                <a href="?" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Réinitialiser
                </a>
            </div>
        </form>
    </div>

    <!-- Résultats -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 120px;">N° Dossier</th>
                        <th>Opérateur</th>
                        <th style="width: 150px;">Type</th>
                        <th>Localisation</th>
                        <th style="width: 150px;">Statut GPS</th>
                        <th style="width: 100px;" class="text-center">Score</th>
                        <th style="width: 150px;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dossiers)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-0">Aucun dossier trouvé</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dossiers as $dossier): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($dossier['numero']) ?></strong>
                                    <?php if ($dossier['est_historique']): ?>
                                        <br><span class="badge bg-secondary">Historique</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($dossier['nom_demandeur']) ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?= getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="fas fa-map-marker-alt text-muted"></i>
                                    <?= htmlspecialchars($dossier['ville'] ?: 'Non spécifié') ?>
                                    <?php if ($dossier['region']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($dossier['region']) ?></small>
                                    <?php endif; ?>
                                    <?php if ($dossier['quartier']): ?>
                                        <br><small class="text-muted"><i class="fas fa-home"></i> <?= htmlspecialchars($dossier['quartier']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($dossier['coordonnees_gps'])): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle"></i> GPS OK
                                        </span>
                                        <?php if ($dossier['source_gps']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($dossier['source_gps']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times-circle"></i> Manquant
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($dossier['score_matching_osm']): ?>
                                        <span class="badge bg-<?= $dossier['score_matching_osm'] >= 80 ? 'success' : ($dossier['score_matching_osm'] >= 60 ? 'warning' : 'danger') ?>">
                                            <?= $dossier['score_matching_osm'] ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center table-actions">
                                    <a href="edit_gps.php?id=<?= $dossier['id'] ?>"
                                       class="btn btn-sm btn-primary btn-edit-gps"
                                       title="Modifier la position GPS">
                                        <i class="fas fa-map-marker-alt"></i> GPS
                                    </a>
                                    <a href="../dossiers/view.php?id=<?= $dossier['id'] ?>"
                                       class="btn btn-sm btn-secondary"
                                       title="Voir le dossier">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_count > $per_page): ?>
            <div class="p-3 border-top">
                <?php
                $total_pages = ceil($total_count / $per_page);
                $query_params = $_GET;
                ?>
                <nav aria-label="Navigation des pages">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <?php $query_params['page'] = $page - 1; ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query($query_params) ?>">
                                    <i class="fas fa-chevron-left"></i> Précédent
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php $query_params['page'] = $i; ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query($query_params) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <?php $query_params['page'] = $page + 1; ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query($query_params) ?>">
                                    Suivant <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <p class="text-center text-muted mt-2 mb-0">
                    Page <?= $page ?> sur <?= $total_pages ?>
                    (<?= number_format($total_count) ?> résultats)
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mobile Responsive pour cartes -->
<script src="../../assets/js/map-mobile-responsive.js"></script>
<?php require_once '../../includes/footer.php'; ?>

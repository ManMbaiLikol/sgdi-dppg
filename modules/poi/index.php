<?php
// Gestion des points d'intérêt stratégiques - SGDI
require_once '../../includes/auth.php';
require_once '../../includes/map_functions.php';
require_once '../../includes/contraintes_distance_functions.php';

requireLogin();

// Vérifier les permissions (admin et chef de service)
if (!in_array($_SESSION['user_role'], ['admin', 'chef_service'])) {
    redirect(url('dashboard.php'), 'Accès réservé aux administrateurs et chefs de service', 'error');
}

// Filtres
$filters = [
    'categorie_id' => intval($_GET['categorie'] ?? 0),
    'ville' => sanitize($_GET['ville'] ?? ''),
    'region' => sanitize($_GET['region'] ?? '')
];

// Récupérer les données
$categories = getCategoriesPOI();
$pois = getAllPOIsForMap(array_filter($filters));

// Récupérer les régions et villes pour les filtres
$sql = "SELECT DISTINCT region FROM points_interet WHERE region IS NOT NULL AND region != '' AND actif = 1 ORDER BY region";
$stmt = $pdo->query($sql);
$regions = $stmt->fetchAll(PDO::FETCH_COLUMN);

$sql = "SELECT DISTINCT ville FROM points_interet WHERE ville IS NOT NULL AND ville != '' AND actif = 1 ORDER BY ville";
$stmt = $pdo->query($sql);
$villes = $stmt->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'Gestion des points d\'intérêt stratégiques';
require_once '../../includes/header.php';
?>

<style>
.poi-card {
    border-left: 4px solid;
    transition: all 0.3s;
}

.poi-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.category-badge {
    font-size: 0.85rem;
    padding: 5px 10px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3">
                <i class="fas fa-map-pin"></i> Gestion des points d'intérêt stratégiques
            </h1>
            <p class="text-muted">
                Gestion des établissements soumis aux contraintes de distance réglementaires
            </p>
        </div>
        <div class="col-auto">
            <a href="<?php echo url('modules/poi/import_google.php'); ?>" class="btn btn-success me-2">
                <i class="fas fa-cloud-download-alt"></i> Import Google Places
            </a>
            <a href="<?php echo url('modules/poi/create.php'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Ajouter un POI
            </a>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <h2 class="mb-0"><?php echo count($pois); ?></h2>
                <small>Points d'intérêt actifs</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <h2 class="mb-0"><?php echo count($categories); ?></h2>
                <small>Catégories</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);">
                <h2 class="mb-0">
                    <?php
                    $critique = array_filter($categories, fn($c) => $c['distance_min_metres'] >= 1000);
                    echo count($critique);
                    ?>
                </h2>
                <small>POI critiques (≥1000m)</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h2 class="mb-0">
                    <?php echo count(array_unique(array_column($pois, 'ville'))); ?>
                </h2>
                <small>Villes couvertes</small>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Catégorie</label>
                    <select class="form-select" name="categorie" onchange="this.form.submit()">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $filters['categorie_id'] === $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($cat['nom']); ?> (<?php echo $cat['distance_min_metres']; ?>m)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Région</label>
                    <select class="form-select" name="region" onchange="this.form.submit()">
                        <option value="">Toutes les régions</option>
                        <?php foreach ($regions as $region): ?>
                        <option value="<?php echo htmlspecialchars($region); ?>" <?php echo $filters['region'] === $region ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($region); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Ville</label>
                    <select class="form-select" name="ville" onchange="this.form.submit()">
                        <option value="">Toutes les villes</option>
                        <?php foreach ($villes as $ville): ?>
                        <option value="<?php echo htmlspecialchars($ville); ?>" <?php echo $filters['ville'] === $ville ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ville); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <?php if (array_filter($filters)): ?>
                    <a href="?" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des POI -->
    <div class="row">
        <?php if (empty($pois)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Aucun point d'intérêt trouvé. Commencez par <a href="<?php echo url('modules/poi/create.php'); ?>">ajouter un POI</a>.
            </div>
        </div>
        <?php else: ?>
            <?php foreach ($pois as $poi): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card poi-card h-100" style="border-left-color: <?php echo $poi['couleur_marqueur']; ?>;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="category-badge badge" style="background-color: <?php echo $poi['couleur_marqueur']; ?>;">
                                    <i class="fas fa-<?php echo $poi['icone']; ?>"></i>
                                    <?php echo sanitize($poi['categorie_nom']); ?>
                                </span>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-secondary" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item" href="<?php echo url('modules/poi/edit.php?id=' . $poi['id']); ?>">
                                            <i class="fas fa-edit"></i> Modifier
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="<?php echo url('modules/poi/delete.php?id=' . $poi['id']); ?>"
                                           onclick="return confirm('Êtes-vous sûr de vouloir désactiver ce POI ?');">
                                            <i class="fas fa-trash"></i> Désactiver
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <h5 class="card-title mb-2">
                            <?php echo sanitize($poi['nom']); ?>
                        </h5>

                        <?php if ($poi['description']): ?>
                        <p class="card-text text-muted small mb-2">
                            <?php echo sanitize(substr($poi['description'], 0, 100)); ?>
                            <?php if (strlen($poi['description']) > 100): ?>...<?php endif; ?>
                        </p>
                        <?php endif; ?>

                        <div class="mb-2">
                            <i class="fas fa-map-marker-alt text-muted"></i>
                            <small>
                                <?php echo sanitize($poi['ville'] ?? 'Non spécifié'); ?>
                                <?php if ($poi['region']): ?>
                                    , <?php echo sanitize($poi['region']); ?>
                                <?php endif; ?>
                            </small>
                        </div>

                        <div class="mb-2">
                            <i class="fas fa-crosshairs text-muted"></i>
                            <small class="font-monospace">
                                <?php echo number_format($poi['latitude'], 6); ?>, <?php echo number_format($poi['longitude'], 6); ?>
                            </small>
                        </div>

                        <div class="mb-3">
                            <span class="badge bg-info">
                                Zone <?php echo $poi['zone_type']; ?>
                            </span>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">Distance minimale:</small>
                                <br>
                                <strong class="text-danger">
                                    <?php echo $poi['distance_min_metres']; ?>m
                                </strong>
                                <small class="text-muted">
                                    (<?php echo $poi['distance_min_rural_metres']; ?>m rural)
                                </small>
                            </div>
                            <a href="<?php echo getGoogleMapsLink($poi['latitude'], $poi['longitude']); ?>"
                               target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

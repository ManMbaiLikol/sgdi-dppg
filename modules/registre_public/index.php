<?php
// Registre Public - Interface de consultation publique sans authentification
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../modules/dossiers/functions.php';

$page_title = 'Registre Public des Infrastructures Pétrolières';

// Filtres de recherche
$search = sanitize($_GET['search'] ?? '');
$type_infrastructure = sanitize($_GET['type_infrastructure'] ?? '');
$region = sanitize($_GET['region'] ?? '');
$ville = sanitize($_GET['ville'] ?? '');
$statut = sanitize($_GET['statut'] ?? 'tous'); // Par défaut, tous les statuts publics
$annee = sanitize($_GET['annee'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1)); // Pagination

// Détecter si des filtres ont été appliqués (filtres actifs de l'utilisateur)
$has_filters = !empty($search) || !empty($type_infrastructure) || !empty($region) ||
               !empty($ville) || !empty($annee);

// Si aucun filtre : afficher seulement les 10 derniers
// Si des filtres : afficher 20 par page
$par_page = $has_filters ? 20 : 10;

// Construction de la requête
$where_clause = "WHERE 1=1";
$from_clause = "FROM dossiers d LEFT JOIN decisions decs ON d.id = decs.dossier_id";

$params = [];

// IMPORTANT : Limiter aux statuts publics uniquement
if ($statut && $statut !== 'tous') {
    $where_clause .= " AND d.statut = :statut";
    $params['statut'] = $statut;
} else {
    // "Tous les statuts" = uniquement les statuts publics (pas les brouillons, en_cours, etc.)
    $where_clause .= " AND d.statut IN ('autorise', 'refuse', 'ferme', 'historique_autorise', 'approuve')";
}

if ($search && $search !== '') {
    $where_clause .= " AND (d.numero LIKE :search
              OR d.nom_demandeur LIKE :search
              OR d.operateur_proprietaire LIKE :search
              OR d.ville LIKE :search)";
    $params['search'] = "%$search%";
}

if ($type_infrastructure && $type_infrastructure !== '') {
    $where_clause .= " AND d.type_infrastructure = :type";
    $params['type'] = $type_infrastructure;
}

if ($region && $region !== '') {
    $where_clause .= " AND d.region = :region";
    $params['region'] = $region;
}

if ($ville && $ville !== '') {
    $where_clause .= " AND d.ville = :ville";
    $params['ville'] = $ville;
}

if ($annee && $annee !== '' && is_numeric($annee)) {
    $where_clause .= " AND YEAR(d.date_creation) = :annee";
    $params['annee'] = intval($annee);
}

// Compter le total pour la pagination
// Si aucun filtre : limiter artificiellement le total à 10 (les 10 derniers)
if (!$has_filters) {
    $count_sql = "SELECT COUNT(*) FROM (
                    SELECT d.id $from_clause $where_clause
                    ORDER BY COALESCE(decs.date_decision, d.date_creation) DESC
                    LIMIT 10
                  ) as limited";
} else {
    $count_sql = "SELECT COUNT(*) $from_clause $where_clause";
}
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_resultats = $count_stmt->fetchColumn();
$total_pages = ceil($total_resultats / $par_page);

// Construire la requête complète avec SELECT
$sql = "SELECT d.*,
        d.numero, d.type_infrastructure, d.sous_type, d.region, d.ville,
        d.nom_demandeur, d.operateur_proprietaire, d.entreprise_beneficiaire,
        DATE_FORMAT(COALESCE(decs.date_decision, d.date_creation), '%d/%m/%Y') as date_decision_format,
        decs.decision,
        COALESCE(decs.date_decision, d.date_creation) as date_decision,
        COALESCE(decs.reference_decision, d.numero) as reference_decision
        $from_clause
        $where_clause
        ORDER BY COALESCE(decs.date_decision, d.date_creation) DESC, d.numero DESC
        LIMIT :limit OFFSET :offset";

// Ajouter la pagination
$offset = ($page - 1) * $par_page;

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(":$key", $value);
}
$stmt->bindValue(':limit', $par_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$dossiers = $stmt->fetchAll();

// Récupérer les options de filtres
// Liste complète des 10 régions du Cameroun (affichées même sans dossiers)
$regions = [
    'Adamaoua',
    'Centre',
    'Est',
    'Extrême-Nord',
    'Littoral',
    'Nord',
    'Nord-Ouest',
    'Ouest',
    'Sud',
    'Sud-Ouest'
];

$villes = $pdo->query("SELECT DISTINCT ville FROM dossiers WHERE ville IS NOT NULL AND ville != '' ORDER BY ville")->fetchAll(PDO::FETCH_COLUMN);
$annees = $pdo->query("SELECT DISTINCT YEAR(date_creation) as annee FROM dossiers WHERE date_creation IS NOT NULL ORDER BY annee DESC")->fetchAll(PDO::FETCH_COLUMN);

// Statistiques publiques (tous les statuts publics)
$stats_sql = "SELECT
    COUNT(DISTINCT d.id) as total_autorise,
    COUNT(DISTINCT CASE WHEN d.type_infrastructure = 'station_service' THEN d.id END) as stations,
    COUNT(DISTINCT CASE WHEN d.type_infrastructure = 'point_consommateur' THEN d.id END) as points,
    COUNT(DISTINCT CASE WHEN d.type_infrastructure = 'depot_gpl' THEN d.id END) as depots,
    COUNT(DISTINCT CASE WHEN d.type_infrastructure = 'centre_emplisseur' THEN d.id END) as centres
    FROM dossiers d
    WHERE d.statut IN ('autorise', 'refuse', 'ferme', 'historique_autorise', 'approuve')";
$stats = $pdo->query($stats_sql)->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - MINEE/DPPG</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../../favicon.svg">
    <link rel="shortcut icon" href="../../favicon.svg">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/registre_public.css" rel="stylesheet">
</head>
<body>
    <!-- Header Public -->
    <div class="public-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2"><i class="fas fa-oil-can"></i> Registre Public des Infrastructures Pétrolières</h1>
                    <p class="mb-0">Ministère de l'Eau et de l'Énergie - Direction des Produits Pétroliers et du Gaz (DPPG)</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="carte.php" class="btn btn-success btn-lg me-2">
                        <i class="fas fa-map-marked-alt"></i> Voir la carte
                    </a>
                    <a href="suivi.php" class="btn btn-warning btn-lg">
                        <i class="fas fa-search-location"></i> Suivi de dossier
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <!-- Statistiques publiques -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card text-center">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="mt-2"><?php echo number_format($stats['total_autorise']); ?></h3>
                    <p class="text-muted mb-0">Total autorisées</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card text-center">
                    <div class="stat-icon text-success">
                        <i class="fas fa-gas-pump"></i>
                    </div>
                    <h3 class="mt-2"><?php echo number_format($stats['stations']); ?></h3>
                    <p class="text-muted mb-0">Stations-service</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card text-center">
                    <div class="stat-icon text-info">
                        <i class="fas fa-industry"></i>
                    </div>
                    <h3 class="mt-2"><?php echo number_format($stats['points']); ?></h3>
                    <p class="text-muted mb-0">Points consommateurs</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card text-center">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <h3 class="mt-2"><?php echo number_format($stats['depots'] + $stats['centres']); ?></h3>
                    <p class="text-muted mb-0">Dépôts GPL / Centres</p>
                </div>
            </div>
        </div>

        <!-- Formulaire de recherche -->
        <div class="search-card">
            <h3 class="mb-4"><i class="fas fa-search"></i> Rechercher une infrastructure</h3>
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Recherche par mot-clé</label>
                        <input type="text" class="form-control" name="search"
                               placeholder="N° dossier, nom, opérateur, ville..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Type d'infrastructure</label>
                        <select class="form-select" name="type_infrastructure">
                            <option value="">Tous les types</option>
                            <option value="station_service" <?php echo $type_infrastructure === 'station_service' ? 'selected' : ''; ?>>Station-service</option>
                            <option value="point_consommateur" <?php echo $type_infrastructure === 'point_consommateur' ? 'selected' : ''; ?>>Point consommateur</option>
                            <option value="depot_gpl" <?php echo $type_infrastructure === 'depot_gpl' ? 'selected' : ''; ?>>Dépôt GPL</option>
                            <option value="centre_emplisseur" <?php echo $type_infrastructure === 'centre_emplisseur' ? 'selected' : ''; ?>>Centre emplisseur</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Région</label>
                        <select class="form-select" name="region">
                            <option value="">Toutes les régions</option>
                            <?php foreach($regions as $r): ?>
                                <option value="<?php echo htmlspecialchars($r); ?>"
                                        <?php echo $region === $r ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Ville</label>
                        <input type="text"
                               class="form-control"
                               name="ville"
                               list="villes-list"
                               placeholder="Ex: Douala, Yaoundé..."
                               value="<?php echo htmlspecialchars($ville); ?>">
                        <datalist id="villes-list">
                            <?php foreach($villes as $v): ?>
                                <option value="<?php echo htmlspecialchars($v); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Statut</label>
                        <select class="form-select" name="statut">
                            <option value="tous" <?php echo $statut === 'tous' ? 'selected' : ''; ?>>Toutes</option>
                            <option value="historique_autorise" <?php echo $statut === 'historique_autorise' ? 'selected' : ''; ?>>Autorisations historiques</option>
                            <option value="autorise" <?php echo $statut === 'autorise' ? 'selected' : ''; ?>>Autorisées (nouvelles)</option>
                            <option value="approuve" <?php echo $statut === 'approuve' ? 'selected' : ''; ?>>Approuvées (ministre)</option>
                            <option value="refuse" <?php echo $statut === 'refuse' ? 'selected' : ''; ?>>Refusées</option>
                            <option value="ferme" <?php echo $statut === 'ferme' ? 'selected' : ''; ?>>Fermées</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Année de décision</label>
                        <select class="form-select" name="annee">
                            <option value="">Toutes les années</option>
                            <?php foreach($annees as $a): ?>
                                <option value="<?php echo $a; ?>" <?php echo $annee == $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Rechercher
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Réinitialiser
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Résultats -->
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <h4>
                <?php if (!$has_filters): ?>
                    Affichage des 10 dernières autorisations
                    <small class="text-muted">(Utilisez les filtres pour voir plus de résultats)</small>
                <?php else: ?>
                    <?php echo $total_resultats; ?> résultat(s) trouvé(s)
                    <?php if ($total_pages > 1): ?>
                        <small class="text-muted">(Page <?php echo $page; ?> sur <?php echo $total_pages; ?>)</small>
                    <?php endif; ?>
                <?php endif; ?>
            </h4>
            <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                <i class="fas fa-file-excel"></i> Exporter Excel
            </a>
        </div>

        <?php if (empty($dossiers)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Aucune infrastructure ne correspond à vos critères de recherche.
            </div>
        <?php else: ?>
            <?php foreach($dossiers as $dossier): ?>
                <div class="result-card">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex align-items-start">
                                <div class="flex-grow-1">
                                    <h5 class="mb-2">
                                        <span class="badge badge-infrastructure bg-primary">
                                            <?php echo getTypeInfrastructureLabel($dossier['type_infrastructure']); ?>
                                        </span>
                                        <?php if ($dossier['sous_type']): ?>
                                            <span class="badge bg-secondary"><?php echo ucfirst($dossier['sous_type']); ?></span>
                                        <?php endif; ?>
                                    </h5>
                                    <h4 class="mb-2"><?php echo htmlspecialchars($dossier['nom_demandeur']); ?></h4>
                                    <p class="mb-1">
                                        <i class="fas fa-map-marker-alt text-danger"></i>
                                        <strong><?php echo htmlspecialchars($dossier['ville'] ?? '-'); ?>,
                                                <?php echo htmlspecialchars($dossier['region'] ?? '-'); ?></strong>
                                    </p>
                                    <?php if ($dossier['operateur_proprietaire']): ?>
                                        <p class="mb-1">
                                            <i class="fas fa-building text-primary"></i>
                                            Opérateur: <?php echo htmlspecialchars($dossier['operateur_proprietaire']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($dossier['entreprise_beneficiaire']): ?>
                                        <p class="mb-1">
                                            <i class="fas fa-handshake text-success"></i>
                                            Bénéficiaire: <?php echo htmlspecialchars($dossier['entreprise_beneficiaire']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <h6 class="mb-2">Dossier N° <strong><?php echo htmlspecialchars($dossier['numero']); ?></strong></h6>
                            <?php if ($dossier['statut'] === 'autorise' || $dossier['statut'] === 'historique_autorise'): ?>
                                <span class="badge bg-success fs-6 mb-2">
                                    <i class="fas fa-check-circle"></i> AUTORISÉE
                                </span>
                                <?php if ($dossier['statut'] === 'historique_autorise'): ?>
                                    <br><small class="text-muted"><i class="fas fa-history"></i> Historique</small>
                                <?php endif; ?>
                            <?php elseif ($dossier['statut'] === 'approuve'): ?>
                                <span class="badge bg-primary fs-6 mb-2">
                                    <i class="fas fa-stamp"></i> APPROUVÉE
                                </span>
                            <?php elseif ($dossier['statut'] === 'refuse'): ?>
                                <span class="badge bg-danger fs-6 mb-2">
                                    <i class="fas fa-times-circle"></i> REFUSÉE
                                </span>
                            <?php elseif ($dossier['statut'] === 'ferme'): ?>
                                <span class="badge bg-dark fs-6 mb-2">
                                    <i class="fas fa-ban"></i> FERMÉE
                                </span>
                            <?php endif; ?>

                            <?php if ($dossier['date_decision']): ?>
                                <p class="mb-1 small text-muted">
                                    <i class="fas fa-calendar"></i> <?php echo $dossier['date_decision_format']; ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($dossier['reference_decision']): ?>
                                <p class="mb-2 small">
                                    <strong>Réf:</strong> <?php echo htmlspecialchars($dossier['reference_decision']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Navigation des pages" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <!-- Page précédente -->
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i> Précédent
                            </a>
                        </li>

                        <?php
                        // Afficher les numéros de pages
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);

                        if ($start > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                            </li>
                            <?php if ($start > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Page suivante -->
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                Suivant <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="footer-public">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>MINEE - Direction DPPG</h5>
                    <p class="mb-0">Direction du Pétrole, du Produit Pétrolier et du Gaz</p>
                    <p class="mb-0">République du Cameroun</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-2">
                        <a href="index.php" class="text-white me-3">Registre public</a>
                        <a href="carte.php" class="text-white me-3">Carte</a>
                        <a href="statistiques.php" class="text-white">Statistiques</a>
                    </p>
                    <p class="mb-0 small">© <?php echo date('Y'); ?> MINEE/DPPG - Tous droits réservés</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

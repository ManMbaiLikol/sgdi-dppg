<?php
// Recherche avancée - Lecteur Public
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('lecteur');

$page_title = 'Recherche Avancée - Registre Public';

global $pdo;

// Récupérer les filtres
$numero = sanitize($_GET['numero'] ?? '');
$type = sanitize($_GET['type_infrastructure'] ?? '');
$region = sanitize($_GET['region'] ?? '');
$operateur = sanitize($_GET['operateur'] ?? '');
$statut = sanitize($_GET['statut'] ?? ''); // autorise ou rejete
$date_debut = sanitize($_GET['date_debut'] ?? '');
$date_fin = sanitize($_GET['date_fin'] ?? '');

// Construction de la requête
$sql = "SELECT d.*,
        DATE_FORMAT(d.date_modification, '%d/%m/%Y') as date_decision_format,
        d.statut as decision
        FROM dossiers d
        WHERE d.statut IN ('autorise', 'rejete', 'decide')";

$params = [];

if (!empty($numero)) {
    $sql .= " AND d.numero LIKE ?";
    $params[] = "%$numero%";
}

if (!empty($type)) {
    $sql .= " AND d.type_infrastructure = ?";
    $params[] = $type;
}

if (!empty($region)) {
    $sql .= " AND d.region LIKE ?";
    $params[] = "%$region%";
}

if (!empty($operateur)) {
    $sql .= " AND d.nom_operateur LIKE ?";
    $params[] = "%$operateur%";
}

if (!empty($statut)) {
    $sql .= " AND d.statut = ?";
    $params[] = $statut;
}

if (!empty($date_debut)) {
    $sql .= " AND d.date_modification >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $sql .= " AND d.date_modification <= ?";
    $params[] = $date_fin;
}

$sql .= " ORDER BY d.date_modification DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resultats = $stmt->fetchAll();

// Récupérer les régions disponibles
$sql_regions = "SELECT DISTINCT region FROM dossiers WHERE statut IN ('autorise', 'rejete') AND region IS NOT NULL AND region != '' ORDER BY region";
$regions = $pdo->query($sql_regions)->fetchAll(PDO::FETCH_COLUMN);

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo url('modules/lecteur/dashboard.php'); ?>">Registre Public</a></li>
            <li class="breadcrumb-item active">Recherche Avancée</li>
        </ol>
    </nav>

    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-search"></i> Recherche Avancée
                    </h1>
                    <p class="mb-0 opacity-75">Rechercher dans le registre des décisions publiques</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire de recherche -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Critères de Recherche</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">N° Dossier</label>
                                <input type="text" class="form-control" name="numero"
                                       value="<?php echo htmlspecialchars($numero); ?>"
                                       placeholder="Ex: DPPG-2025-001">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Type d'Infrastructure</label>
                                <select class="form-select" name="type_infrastructure">
                                    <option value="">Tous les types</option>
                                    <option value="implantation_station" <?php echo $type === 'implantation_station' ? 'selected' : ''; ?>>
                                        Implantation Station-Service
                                    </option>
                                    <option value="reprise_station" <?php echo $type === 'reprise_station' ? 'selected' : ''; ?>>
                                        Reprise Station-Service
                                    </option>
                                    <option value="implantation_point_conso" <?php echo $type === 'implantation_point_conso' ? 'selected' : ''; ?>>
                                        Implantation Point Consommateur
                                    </option>
                                    <option value="reprise_point_conso" <?php echo $type === 'reprise_point_conso' ? 'selected' : ''; ?>>
                                        Reprise Point Consommateur
                                    </option>
                                    <option value="implantation_depot_gpl" <?php echo $type === 'implantation_depot_gpl' ? 'selected' : ''; ?>>
                                        Implantation Dépôt GPL
                                    </option>
                                    <option value="implantation_centre_emplisseur" <?php echo $type === 'implantation_centre_emplisseur' ? 'selected' : ''; ?>>
                                        Implantation Centre Emplisseur
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Région</label>
                                <select class="form-select" name="region">
                                    <option value="">Toutes les régions</option>
                                    <?php foreach ($regions as $r): ?>
                                        <option value="<?php echo htmlspecialchars($r); ?>"
                                                <?php echo $region === $r ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($r); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Opérateur</label>
                                <input type="text" class="form-control" name="operateur"
                                       value="<?php echo htmlspecialchars($operateur); ?>"
                                       placeholder="Nom de l'opérateur">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Statut</label>
                                <select class="form-select" name="statut">
                                    <option value="">Tous</option>
                                    <option value="autorise" <?php echo $statut === 'autorise' ? 'selected' : ''; ?>>
                                        Autorisé
                                    </option>
                                    <option value="rejete" <?php echo $statut === 'rejete' ? 'selected' : ''; ?>>
                                        Rejeté
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date Début</label>
                                <input type="date" class="form-control" name="date_debut"
                                       value="<?php echo htmlspecialchars($date_debut); ?>">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date Fin</label>
                                <input type="date" class="form-control" name="date_fin"
                                       value="<?php echo htmlspecialchars($date_fin); ?>">
                            </div>

                            <div class="col-md-3 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Rechercher
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($numero) || !empty($type) || !empty($region) || !empty($operateur) || !empty($statut) || !empty($date_debut) || !empty($date_fin)): ?>
                            <div class="text-center">
                                <a href="<?php echo url('modules/lecteur/recherche.php'); ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Réinitialiser les filtres
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Résultats -->
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Résultats de la Recherche
                        <?php if (!empty($resultats)): ?>
                            <span class="badge bg-light text-dark"><?php echo count($resultats); ?> résultat(s)</span>
                        <?php endif; ?>
                    </h5>
                    <?php if (!empty($resultats)): ?>
                        <a href="<?php echo url('modules/lecteur/export.php?' . $_SERVER['QUERY_STRING']); ?>"
                           class="btn btn-light btn-sm">
                            <i class="fas fa-download"></i> Exporter
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($resultats)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <?php if (!empty($numero) || !empty($type) || !empty($region) || !empty($operateur) || !empty($statut) || !empty($date_debut) || !empty($date_fin)): ?>
                                Aucun résultat ne correspond à vos critères de recherche.
                            <?php else: ?>
                                Veuillez utiliser les filtres ci-dessus pour rechercher dans le registre.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Statut</th>
                                        <th>N° Dossier</th>
                                        <th>Type</th>
                                        <th>Opérateur</th>
                                        <th>Localisation</th>
                                        <th>Région</th>
                                        <th>Date Modification</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resultats as $result): ?>
                                        <tr>
                                            <td>
                                                <?php if ($result['statut'] === 'autorise'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check"></i> Autorisé
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-times"></i> Rejeté
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo sanitize($result['numero']); ?></strong></td>
                                            <td><?php echo getTypeInfrastructureLabel($result['type_infrastructure']); ?></td>
                                            <td><?php echo sanitize($result['operateur_proprietaire'] ?? $result['nom_demandeur']); ?></td>
                                            <td><?php echo sanitize($result['lieu_dit'] ?? ($result['quartier'] . ', ' . $result['ville'])); ?></td>
                                            <td><?php echo sanitize($result['region']); ?></td>
                                            <td><?php echo $result['date_decision_format']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

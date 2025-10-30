<?php
// Mes dossiers visés - Historique des visas du Sous-Directeur SDTD
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('sous_directeur');

$page_title = 'Mes dossiers visés - Sous-Directeur SDTD';
$user_id = $_SESSION['user_id'];

// Filtres
$filtre_action = sanitize($_GET['action'] ?? '');
$filtre_statut = sanitize($_GET['statut'] ?? '');
$filtre_annee = sanitize($_GET['annee'] ?? '');

// Récupérer mes dossiers visés
$sql = "SELECT d.*,
        v.id as visa_id,
        v.date_visa,
        v.action as visa_action,
        v.observations as visa_observations,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
        DATE_FORMAT(v.date_visa, '%d/%m/%Y à %H:%i') as date_visa_format,
        u.nom as createur_nom, u.prenom as createur_prenom,
        -- Vérifier si le dossier a continué après mon visa
        vd.date_visa as date_visa_directeur
        FROM dossiers d
        INNER JOIN visas v ON d.id = v.dossier_id AND v.role = 'sous_directeur' AND v.user_id = ?
        LEFT JOIN users u ON d.user_id = u.id
        LEFT JOIN visas vd ON d.id = vd.dossier_id AND vd.role = 'directeur'
        WHERE 1=1";

$params = [$user_id];

if ($filtre_action) {
    $sql .= " AND v.action = ?";
    $params[] = $filtre_action;
}

if ($filtre_statut) {
    $sql .= " AND d.statut = ?";
    $params[] = $filtre_statut;
}

if ($filtre_annee) {
    $sql .= " AND YEAR(v.date_visa) = ?";
    $params[] = $filtre_annee;
}

$sql .= " ORDER BY v.date_visa DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dossiers = $stmt->fetchAll();

// Statistiques
$stats = [
    'total' => count($dossiers),
    'approuves' => 0,
    'rejetes' => 0,
    'en_cours' => 0,
    'autorises' => 0
];

foreach ($dossiers as $dossier) {
    if ($dossier['visa_action'] === 'approuve') $stats['approuves']++;
    if ($dossier['visa_action'] === 'rejete') $stats['rejetes']++;
    if ($dossier['statut'] === 'autorise') $stats['autorises']++;
    if (in_array($dossier['statut'], ['visa_sous_directeur', 'visa_directeur'])) $stats['en_cours']++;
}

// Récupérer les années disponibles
$sql_annees = "SELECT DISTINCT YEAR(date_visa) as annee
               FROM visas
               WHERE role = 'sous_directeur' AND user_id = ?
               ORDER BY annee DESC";
$stmt = $pdo->prepare($sql_annees);
$stmt->execute([$user_id]);
$annees = $stmt->fetchAll(PDO::FETCH_COLUMN);

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?php echo url('modules/sous_directeur/dashboard.php'); ?>">
                            <i class="fas fa-home"></i> Tableau de bord
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Mes dossiers visés</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-history"></i>
                        Historique de mes visas
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Traçabilité :</strong> Cette page affiche tous les dossiers sur lesquels vous avez apposé votre visa.
                        Vous pouvez suivre leur évolution jusqu'à la décision ministérielle finale.
                    </div>

                    <!-- Statistiques -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                                    <small>Total visés</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $stats['approuves']; ?></h2>
                                    <small>Approuvés</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $stats['autorises']; ?></h2>
                                    <small>Autorisés finalement</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $stats['rejetes']; ?></h2>
                                    <small>Rejetés</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtres -->
                    <form method="GET" class="card bg-light mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">Action de mon visa</label>
                                    <select class="form-select" name="action">
                                        <option value="">Toutes les actions</option>
                                        <option value="approuve" <?php echo $filtre_action === 'approuve' ? 'selected' : ''; ?>>
                                            Approuvés uniquement
                                        </option>
                                        <option value="rejete" <?php echo $filtre_action === 'rejete' ? 'selected' : ''; ?>>
                                            Rejetés uniquement
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Statut actuel</label>
                                    <select class="form-select" name="statut">
                                        <option value="">Tous les statuts</option>
                                        <option value="autorise" <?php echo $filtre_statut === 'autorise' ? 'selected' : ''; ?>>
                                            Autorisé
                                        </option>
                                        <option value="visa_directeur" <?php echo $filtre_statut === 'visa_directeur' ? 'selected' : ''; ?>>
                                            Visa Directeur
                                        </option>
                                        <option value="decide" <?php echo $filtre_statut === 'decide' ? 'selected' : ''; ?>>
                                            Décidé
                                        </option>
                                        <option value="rejete" <?php echo $filtre_statut === 'rejete' ? 'selected' : ''; ?>>
                                            Rejeté
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Année</label>
                                    <select class="form-select" name="annee">
                                        <option value="">Toutes les années</option>
                                        <?php foreach ($annees as $annee): ?>
                                        <option value="<?php echo $annee; ?>" <?php echo $filtre_annee == $annee ? 'selected' : ''; ?>>
                                            <?php echo $annee; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-filter"></i> Filtrer
                                    </button>
                                    <a href="?" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Réinitialiser
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>

                    <?php if (empty($dossiers)): ?>
                        <div class="alert alert-secondary">
                            <i class="fas fa-folder-open"></i>
                            <?php if ($filtre_action || $filtre_statut || $filtre_annee): ?>
                                <strong>Aucun résultat :</strong> Aucun dossier ne correspond aux filtres sélectionnés.
                            <?php else: ?>
                                <strong>Aucun visa :</strong> Vous n'avez encore visé aucun dossier.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-info">
                                    <tr>
                                        <th width="120">Numéro</th>
                                        <th>Type</th>
                                        <th>Demandeur</th>
                                        <th>Mon visa</th>
                                        <th>Statut actuel</th>
                                        <th>Évolution</th>
                                        <th width="100" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dossiers as $dossier): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary">
                                                <?php echo sanitize($dossier['numero']); ?>
                                            </strong>
                                            <br><small class="text-muted">
                                                Créé le<br><?php echo $dossier['date_creation_format']; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo sanitize(getTypeInfrastructureLabel($dossier['type_infrastructure'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong>
                                            <br><small class="text-muted">
                                                <i class="fas fa-map-marker-alt"></i> <?php echo sanitize($dossier['ville']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="mb-1">
                                                <?php if ($dossier['visa_action'] === 'approuve'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check"></i> Approuvé
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-times"></i> Rejeté
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo $dossier['date_visa_format']; ?>
                                            </small>
                                            <?php if ($dossier['visa_observations']): ?>
                                            <br><small class="text-info" data-bs-toggle="tooltip"
                                                   title="<?php echo sanitize($dossier['visa_observations']); ?>">
                                                <i class="fas fa-comment"></i> Observations
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                                                <?php echo getStatutLabel($dossier['statut']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php if ($dossier['date_visa_directeur']): ?>
                                                    <div class="mb-1">
                                                        <i class="fas fa-check text-success"></i>
                                                        Visé par Directeur
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($dossier['decision_ministerielle']): ?>
                                                    <div class="mb-1">
                                                        <i class="fas fa-gavel text-primary"></i>
                                                        Décision: <?php echo ucfirst($dossier['decision_ministerielle']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($dossier['statut'] === 'autorise'): ?>
                                                    <div class="text-success">
                                                        <i class="fas fa-check-circle"></i>
                                                        <strong>Infrastructure autorisée</strong>
                                                    </div>
                                                <?php elseif ($dossier['statut'] === 'rejete'): ?>
                                                    <div class="text-danger">
                                                        <i class="fas fa-times-circle"></i>
                                                        <strong>Dossier rejeté</strong>
                                                    </div>
                                                <?php elseif (in_array($dossier['statut'], ['visa_sous_directeur', 'visa_directeur'])): ?>
                                                    <div class="text-warning">
                                                        <i class="fas fa-clock"></i>
                                                        En cours de traitement
                                                    </div>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier['id']); ?>"
                                               class="btn btn-outline-secondary btn-sm"
                                               title="Consulter le dossier complet"
                                               target="_blank">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Résumé avec filtres -->
                        <?php if ($filtre_action || $filtre_statut || $filtre_annee): ?>
                        <div class="alert alert-light mt-3">
                            <i class="fas fa-filter"></i>
                            <strong>Résultats filtrés :</strong> <?php echo count($dossiers); ?> dossier(s) trouvé(s)
                            <?php if ($filtre_action): ?>
                                - Action: <strong><?php echo ucfirst($filtre_action); ?></strong>
                            <?php endif; ?>
                            <?php if ($filtre_statut): ?>
                                - Statut: <strong><?php echo getStatutLabel($filtre_statut); ?></strong>
                            <?php endif; ?>
                            <?php if ($filtre_annee): ?>
                                - Année: <strong><?php echo $filtre_annee; ?></strong>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Activer les tooltips Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

<?php
// Recherche avancée - Lecteur Public
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('lecteur');

$page_title = 'Recherche Avancée - Suivi de Dossiers';

global $pdo;

// Récupérer les filtres
$numero = sanitize($_GET['numero'] ?? '');
$type = sanitize($_GET['type_infrastructure'] ?? '');
$region = sanitize($_GET['region'] ?? '');
$demandeur = sanitize($_GET['demandeur'] ?? '');
$statut = sanitize($_GET['statut'] ?? '');
$date_debut = sanitize($_GET['date_debut'] ?? '');
$date_fin = sanitize($_GET['date_fin'] ?? '');

// Vérifier si au moins un critère est renseigné
$has_criteria = !empty($numero) || !empty($type) || !empty($region) || !empty($demandeur) || !empty($statut) || !empty($date_debut) || !empty($date_fin);

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50; // Résultats par page
$offset = ($page - 1) * $per_page;

$resultats = [];
$total_resultats = 0;
$total_pages = 0;

if ($has_criteria) {
    // Construction de la requête - inclure TOUS les statuts pour permettre le suivi
    $sql = "SELECT d.*,
            DATE_FORMAT(d.date_modification, '%d/%m/%Y') as date_format,
            DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format
            FROM dossiers d
            WHERE 1=1";

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

    if (!empty($demandeur)) {
        $sql .= " AND (d.nom_demandeur LIKE ? OR d.operateur_proprietaire LIKE ?)";
        $params[] = "%$demandeur%";
        $params[] = "%$demandeur%";
    }

    if (!empty($statut)) {
        $sql .= " AND d.statut = ?";
        $params[] = $statut;
    }

    if (!empty($date_debut)) {
        $sql .= " AND d.date_creation >= ?";
        $params[] = $date_debut;
    }

    if (!empty($date_fin)) {
        $sql .= " AND d.date_creation <= ?";
        $params[] = $date_fin;
    }

    // Compter le total de résultats
    $count_sql = "SELECT COUNT(*) FROM dossiers d WHERE 1=1";

    if (!empty($numero)) {
        $count_sql .= " AND d.numero LIKE ?";
    }
    if (!empty($type)) {
        $count_sql .= " AND d.type_infrastructure = ?";
    }
    if (!empty($region)) {
        $count_sql .= " AND d.region LIKE ?";
    }
    if (!empty($demandeur)) {
        $count_sql .= " AND (d.nom_demandeur LIKE ? OR d.operateur_proprietaire LIKE ?)";
    }
    if (!empty($statut)) {
        $count_sql .= " AND d.statut = ?";
    }
    if (!empty($date_debut)) {
        $count_sql .= " AND d.date_creation >= ?";
    }
    if (!empty($date_fin)) {
        $count_sql .= " AND d.date_creation <= ?";
    }

    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_resultats = $stmt_count->fetchColumn();
    $total_pages = ceil($total_resultats / $per_page);

    // Récupérer les résultats paginés
    $sql .= " ORDER BY d.date_modification DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultats = $stmt->fetchAll();
}

// Récupérer les régions disponibles
$sql_regions = "SELECT DISTINCT region FROM dossiers WHERE region IS NOT NULL AND region != '' ORDER BY region";
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
                    <p class="mb-0 opacity-75">Rechercher et suivre l'évolution de vos dossiers</p>
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
                                <label class="form-label">Nom du Demandeur/Opérateur</label>
                                <input type="text" class="form-control" name="demandeur"
                                       value="<?php echo htmlspecialchars($demandeur); ?>"
                                       placeholder="Nom du demandeur ou opérateur">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Statut du Dossier</label>
                                <select class="form-select" name="statut">
                                    <option value="">Tous les statuts</option>
                                    <option value="brouillon" <?php echo $statut === 'brouillon' ? 'selected' : ''; ?>>Brouillon</option>
                                    <option value="en_cours" <?php echo $statut === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                                    <option value="paye" <?php echo $statut === 'paye' ? 'selected' : ''; ?>>Payé</option>
                                    <option value="analyse_daj" <?php echo $statut === 'analyse_daj' ? 'selected' : ''; ?>>Analysé DAJ</option>
                                    <option value="inspecte" <?php echo $statut === 'inspecte' ? 'selected' : ''; ?>>Inspecté</option>
                                    <option value="validation_commission" <?php echo $statut === 'validation_commission' ? 'selected' : ''; ?>>Validation Chef Commission</option>
                                    <option value="visa_chef_service" <?php echo $statut === 'visa_chef_service' ? 'selected' : ''; ?>>Visa Chef Service</option>
                                    <option value="visa_sous_directeur" <?php echo $statut === 'visa_sous_directeur' ? 'selected' : ''; ?>>Visa Sous-Directeur</option>
                                    <option value="visa_directeur" <?php echo $statut === 'visa_directeur' ? 'selected' : ''; ?>>Visa Directeur</option>
                                    <option value="valide" <?php echo $statut === 'valide' ? 'selected' : ''; ?>>Validé</option>
                                    <option value="decide" <?php echo $statut === 'decide' ? 'selected' : ''; ?>>Décidé</option>
                                    <option value="autorise" <?php echo $statut === 'autorise' ? 'selected' : ''; ?>>Autorisé</option>
                                    <option value="rejete" <?php echo $statut === 'rejete' ? 'selected' : ''; ?>>Rejeté</option>
                                    <option value="en_huitaine" <?php echo $statut === 'en_huitaine' ? 'selected' : ''; ?>>En huitaine</option>
                                    <option value="suspendu" <?php echo $statut === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                                    <option value="ferme" <?php echo $statut === 'ferme' ? 'selected' : ''; ?>>Fermé</option>
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

                        <?php if (!empty($numero) || !empty($type) || !empty($region) || !empty($demandeur) || !empty($statut) || !empty($date_debut) || !empty($date_fin)): ?>
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
                        <?php if ($has_criteria && $total_resultats > 0): ?>
                            <span class="badge bg-light text-dark">
                                <?php echo number_format($total_resultats); ?> résultat(s) trouvé(s)
                                <?php if ($total_resultats > $per_page): ?>
                                    - Page <?php echo $page; ?>/<?php echo $total_pages; ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </h5>
                    <?php if (!empty($resultats)): ?>
                        <a href="<?php echo url('modules/lecteur/export.php?' . http_build_query($_GET)); ?>"
                           class="btn btn-light btn-sm">
                            <i class="fas fa-download"></i> Exporter (<?php echo number_format($total_resultats); ?>)
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!$has_criteria): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Veuillez saisir au moins un critère de recherche</strong> pour afficher les résultats.
                            <br><small>Exemple : recherchez par votre nom dans "Nom du Demandeur/Opérateur" pour voir tous vos dossiers.</small>
                        </div>
                    <?php elseif (empty($resultats)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Aucun résultat ne correspond à vos critères de recherche.
                        </div>
                    <?php else: ?>
                        <!-- Info sur les statuts -->
                        <div class="alert alert-info mb-3">
                            <h6><i class="fas fa-info-circle"></i> Comprendre le statut de votre dossier</h6>
                            <small>
                                <strong>Brouillon/En cours :</strong> Votre dossier est en préparation<br>
                                <strong>Payé :</strong> Les frais ont été réglés, dossier en attente d'inspection<br>
                                <strong>Analysé DAJ/Inspecté :</strong> Votre dossier est en cours d'évaluation technique<br>
                                <strong>Validation/Visa :</strong> Votre dossier est en circuit de validation hiérarchique<br>
                                <strong>Autorisé/Rejeté :</strong> Décision finale prise par le Ministère
                            </small>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>N° Dossier</th>
                                        <th>Type</th>
                                        <th>Demandeur/Opérateur</th>
                                        <th>Localisation</th>
                                        <th>Région</th>
                                        <th>Statut Actuel</th>
                                        <th>Date Création</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resultats as $result): ?>
                                        <tr>
                                            <td><strong><?php echo sanitize($result['numero']); ?></strong></td>
                                            <td><?php echo getTypeInfrastructureLabel($result['type_infrastructure']); ?></td>
                                            <td><?php echo sanitize($result['operateur_proprietaire'] ?? $result['nom_demandeur']); ?></td>
                                            <td><?php echo sanitize($result['lieu_dit'] ?? ($result['quartier'] . ', ' . $result['ville'])); ?></td>
                                            <td><?php echo sanitize($result['region']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatutClass($result['statut']); ?>">
                                                    <?php echo getStatutLabel($result['statut']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $result['date_creation_format']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav class="mt-4" aria-label="Navigation des résultats">
                                <ul class="pagination justify-content-center">
                                    <!-- Bouton Précédent -->
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="fas fa-chevron-left"></i> Précédent
                                        </a>
                                    </li>

                                    <?php
                                    // Afficher les numéros de page
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);

                                    // Première page
                                    if ($start_page > 1):
                                    ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <!-- Pages du milieu -->
                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <!-- Dernière page -->
                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                                <?php echo $total_pages; ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <!-- Bouton Suivant -->
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            Suivant <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>

                                <!-- Info pagination -->
                                <div class="text-center text-muted small">
                                    Affichage de <?php echo ($offset + 1); ?> à <?php echo min($offset + $per_page, $total_resultats); ?>
                                    sur <?php echo number_format($total_resultats); ?> résultat(s)
                                </div>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

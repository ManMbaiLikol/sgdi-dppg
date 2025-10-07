<?php
// Liste des dossiers - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

$page_title = 'Liste des dossiers';

// Filtres
$filters = [
    'statut' => sanitize($_GET['statut'] ?? ''),
    'type_infrastructure' => sanitize($_GET['type_infrastructure'] ?? ''),
    'sous_type' => sanitize($_GET['sous_type'] ?? ''),
    'search' => sanitize($_GET['search'] ?? ''),
    'user_role' => $_SESSION['user_role']
];

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Récupérer les dossiers
$dossiers = getDossiers($filters, $limit, $offset);
$total_dossiers = countDossiers($filters);
$total_pages = ceil($total_dossiers / $limit);

// Statistiques rapides
$stats = getStatistiquesDossiers($_SESSION['user_role']);

require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2>
            <i class="fas fa-folder"></i> Liste des dossiers
            <small class="text-muted">(<?php echo $total_dossiers; ?> dossier<?php echo $total_dossiers > 1 ? 's' : ''; ?>)</small>
        </h2>
    </div>
    <?php if (hasRole('chef_service')): ?>
    <div class="col-auto">
        <a href="<?php echo url('modules/dossiers/create.php'); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nouveau dossier
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body py-3">
                <h5 class="text-primary mb-0"><?php echo $stats['total'] ?? 0; ?></h5>
                <small class="text-muted">Total</small>
            </div>
        </div>
    </div>

    <?php
    $statuts_labels = [
        'brouillon' => 'Brouillons',
        'en_cours' => 'En cours',
        'paye' => 'Payés',
        'inspecte' => 'Inspectés',
        'valide' => 'Validés',
        'decide' => 'Décidés',
        'rejete' => 'Rejetés'
    ];

    foreach ($statuts_labels as $statut => $label):
        $count = $stats['par_statut'][$statut] ?? 0;
        if ($count > 0):
    ?>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body py-3">
                <h5 class="text-<?php echo getStatutClass($statut); ?> mb-0"><?php echo $count; ?></h5>
                <small class="text-muted"><?php echo $label; ?></small>
            </div>
        </div>
    </div>
    <?php endif; endforeach; ?>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="card-title mb-0">
            <i class="fas fa-filter"></i> Filtres
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="statut" class="form-label">Statut</label>
                <select class="form-select form-select-sm" id="statut" name="statut">
                    <option value="">Tous les statuts</option>
                    <?php foreach ($statuts_labels as $statut => $label): ?>
                    <option value="<?php echo $statut; ?>" <?php echo $filters['statut'] === $statut ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="type_infrastructure" class="form-label">Type</label>
                <select class="form-select form-select-sm" id="type_infrastructure" name="type_infrastructure">
                    <option value="">Tous les types</option>
                    <option value="station_service" <?php echo $filters['type_infrastructure'] === 'station_service' ? 'selected' : ''; ?>>
                        Station-service
                    </option>
                    <option value="point_consommateur" <?php echo $filters['type_infrastructure'] === 'point_consommateur' ? 'selected' : ''; ?>>
                        Point consommateur
                    </option>
                    <option value="depot_gpl" <?php echo $filters['type_infrastructure'] === 'depot_gpl' ? 'selected' : ''; ?>>
                        Dépôt GPL
                    </option>
                    <option value="centre_emplisseur" <?php echo $filters['type_infrastructure'] === 'centre_emplisseur' ? 'selected' : ''; ?>>
                        Centre emplisseur
                    </option>
                </select>
            </div>

            <div class="col-md-3">
                <label for="sous_type" class="form-label">Nature</label>
                <select class="form-select form-select-sm" id="sous_type" name="sous_type">
                    <option value="">Toutes</option>
                    <option value="implantation" <?php echo $filters['sous_type'] === 'implantation' ? 'selected' : ''; ?>>
                        Implantation
                    </option>
                    <option value="reprise" <?php echo $filters['sous_type'] === 'reprise' ? 'selected' : ''; ?>>
                        Reprise
                    </option>
                    <option value="remodelage" <?php echo $filters['sous_type'] === 'remodelage' ? 'selected' : ''; ?>>
                        Remodelage
                    </option>
                </select>
            </div>

            <div class="col-md-3">
                <label for="search" class="form-label">Recherche</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?php echo sanitize($filters['search']); ?>"
                           placeholder="N°, demandeur, région, arrondissement, ville, quartier...">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste des dossiers -->
<div class="card">
    <div class="card-header">
        <h6 class="card-title mb-0">Dossiers</h6>
    </div>

    <?php if (empty($dossiers)): ?>
    <div class="card-body text-center py-5">
        <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
        <p class="text-muted">Aucun dossier trouvé avec les critères sélectionnés</p>
        <?php if (hasRole('chef_service')): ?>
        <a href="<?php echo url('modules/dossiers/create.php'); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Créer le premier dossier
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>N° Dossier</th>
                    <th>Type/Nature</th>
                    <th>Demandeur</th>
                    <th>Localisation</th>
                    <th>Statut</th>
                    <th>Date création</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dossiers as $dossier): ?>
                <tr>
                    <td>
                        <code class="text-primary"><?php echo sanitize($dossier['numero']); ?></code>
                    </td>
                    <td>
                        <div>
                            <strong><?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?></strong>
                        </div>
                    </td>
                    <td>
                        <div>
                            <strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong>
                            <?php if ($dossier['contact_demandeur']): ?>
                            <br><small class="text-muted"><?php echo sanitize($dossier['contact_demandeur']); ?></small>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($dossier['ville'] || $dossier['region'] || $dossier['arrondissement']): ?>
                        <i class="fas fa-map-marker-alt text-muted"></i>

                        <!-- Affichage hiérarchique : Région → Arrondissement → Ville → Quartier -->
                        <?php if ($dossier['region']): ?>
                        <strong><?php echo sanitize($dossier['region']); ?></strong>
                        <?php endif; ?>

                        <?php if ($dossier['arrondissement']): ?>
                        <br><small class="text-muted"><i class="fas fa-building"></i> <?php echo sanitize($dossier['arrondissement']); ?></small>
                        <?php endif; ?>

                        <?php if ($dossier['ville']): ?>
                        <br><small class="text-muted"><i class="fas fa-city"></i> <?php echo sanitize($dossier['ville']); ?></small>
                        <?php endif; ?>

                        <?php if ($dossier['quartier']): ?>
                        <br><small class="text-muted"><i class="fas fa-home"></i> <?php echo sanitize($dossier['quartier']); ?></small>
                        <?php endif; ?>

                        <?php else: ?>
                        <span class="text-muted">Non précisé</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                            <?php echo getStatutLabel($dossier['statut']); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo formatDateTime($dossier['date_creation'], 'd/m/Y'); ?>
                        <br><small class="text-muted">
                            par <?php echo sanitize($dossier['createur_prenom'] . ' ' . $dossier['createur_nom']); ?>
                        </small>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <?php
                            $actions = getActionsPossibles($dossier, $_SESSION['user_role']);
                            foreach ($actions as $action):
                                $url_path = '';
                                switch ($action['action']) {
                                    case 'voir_details':
                                        $url_path = 'modules/dossiers/view.php?id=' . $dossier['id'];
                                        break;
                                    case 'constituer_commission':
                                        $url_path = 'modules/dossiers/commission.php?id=' . $dossier['id'];
                                        break;
                                    case 'creer_note_frais':
                                        $url_path = 'modules/notes_frais/create.php?dossier_id=' . $dossier['id'];
                                        break;
                                    case 'enregistrer_paiement':
                                        $url_path = 'modules/dossiers/paiement.php?id=' . $dossier['id'];
                                        break;
                                    case 'faire_inspection':
                                        $url_path = 'modules/dossiers/inspection.php?id=' . $dossier['id'];
                                        break;
                                    case 'valider_rapport':
                                    case 'prendre_decision':
                                        $url_path = 'modules/dossiers/decision.php?id=' . $dossier['id'];
                                        break;
                                    case 'marquer_autorise':
                                        $url_path = 'modules/dossiers/marquer_autorise.php?id=' . $dossier['id'];
                                        break;
                                    case 'gestion_operationnelle':
                                        $url_path = 'modules/dossiers/gestion_operationnelle.php?id=' . $dossier['id'];
                                        break;
                                    case 'upload_documents':
                                        $url_path = 'modules/dossiers/upload_documents.php?id=' . $dossier['id'];
                                        break;
                                    default:
                                        $url_path = 'modules/dossiers/view.php?id=' . $dossier['id'];
                                }
                                $url = url($url_path);
                            ?>
                            <a href="<?php echo $url; ?>" class="btn btn-<?php echo $action['class']; ?> btn-sm">
                                <?php echo $action['label']; ?>
                            </a>
                            <?php endforeach; ?>
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
                <!-- Première page -->
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Pages autour de la page courante -->
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);

                for ($i = $start; $i <= $end; $i++):
                ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>

                <!-- Dernière page -->
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
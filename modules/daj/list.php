<?php
// Liste des dossiers pour analyse DAJ - SGDI MVP
require_once '../../includes/auth.php';
require_once '../dossiers/functions.php';
require_once 'functions.php';

requireLogin();

// Vérifier que l'utilisateur est bien un cadre DAJ
if ($_SESSION['user_role'] !== 'cadre_daj') {
    redirect(url('dashboard.php'), 'Accès non autorisé', 'error');
}

$statut_filtre = $_GET['statut'] ?? 'paye';
$search = sanitize($_GET['search'] ?? '');
$dossiers = getDossiersDAJ($statut_filtre, null, $search);
$stats = getStatistiquesDAJ($_SESSION['user_id']);

$page_title = 'Dossiers DAJ - Analyse Juridique';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-gavel"></i>
                    Analyse Juridique et Réglementaire
                </h1>
                <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-tachometer-alt"></i> Tableau de bord
                </a>
            </div>

            <!-- Statistiques rapides -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h4 class="text-warning"><?php echo $stats['a_analyser']; ?></h4>
                            <p class="text-muted mb-0">À analyser</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-cog fa-2x text-info mb-2"></i>
                            <h4 class="text-info"><?php echo $stats['en_cours']; ?></h4>
                            <p class="text-muted mb-0">En cours</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h4 class="text-success"><?php echo $stats['terminees']; ?></h4>
                            <p class="text-muted mb-0">Terminées</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-calendar fa-2x text-primary mb-2"></i>
                            <h4 class="text-primary"><?php echo $stats['ce_mois']; ?></h4>
                            <p class="text-muted mb-0">Ce mois</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="mb-3">
                        <div class="row align-items-end">
                            <div class="col-md-4">
                                <h5 class="card-title mb-2">
                                    <i class="fas fa-filter"></i>
                                    Filtrer les dossiers
                                </h5>
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Recherche</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="search" name="search"
                                           value="<?php echo htmlspecialchars($search); ?>"
                                           placeholder="N°, demandeur, région, arrondissement, ville...">
                                    <input type="hidden" name="statut" value="<?php echo htmlspecialchars($statut_filtre); ?>">
                                    <button type="submit" class="btn btn-outline-secondary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="btn-group btn-group-sm w-100" role="group">
                                    <a href="?statut=paye<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                                       class="btn <?php echo $statut_filtre === 'paye' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        <i class="fas fa-gavel"></i> À analyser
                                    </a>
                                    <a href="?statut=analyse_daj<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                                       class="btn <?php echo $statut_filtre === 'analyse_daj' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        <i class="fas fa-check"></i> Analysés
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Liste des dossiers -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list"></i>
                        <?php
                        switch ($statut_filtre) {
                            case 'paye':
                                echo 'Dossiers à analyser juridiquement';
                                break;
                            case 'analyse_daj':
                                echo 'Dossiers analysés';
                                break;
                            default:
                                echo 'Tous les dossiers';
                        }
                        ?>
                        <span class="badge badge-secondary ml-2"><?php echo count($dossiers); ?></span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($dossiers): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Numéro</th>
                                        <th>Type Infrastructure</th>
                                        <th>Demandeur</th>
                                        <th>Localisation</th>
                                        <th>Date Création</th>
                                        <th>Statut Analyse</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dossiers as $dossier): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($dossier['numero']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?php echo htmlspecialchars($dossier['type_infrastructure']); ?>
                                                </span><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($dossier['sous_type']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($dossier['nom_demandeur']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($dossier['contact_demandeur']); ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                // Ordre hiérarchique camerounais : Région → Arrondissement → Ville → Quartier
                                                $localisation = [];
                                                if ($dossier['region']) $localisation[] = $dossier['region'];
                                                if ($dossier['arrondissement']) $localisation[] = $dossier['arrondissement'];
                                                if ($dossier['ville']) $localisation[] = $dossier['ville'];
                                                if ($dossier['quartier']) $localisation[] = $dossier['quartier'];

                                                if (count($localisation) > 0) {
                                                    echo '<strong>' . htmlspecialchars($localisation[0]) . '</strong>';
                                                    for ($i = 1; $i < count($localisation); $i++) {
                                                        echo '<br><small class="text-muted">' . htmlspecialchars($localisation[$i]) . '</small>';
                                                    }
                                                } else {
                                                    echo '<span class="text-muted">Non précisé</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php echo date('d/m/Y', strtotime($dossier['date_creation'])); ?><br>
                                                <small class="text-muted"><?php echo date('H:i', strtotime($dossier['date_creation'])); ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $badge_class = 'secondary';
                                                switch ($dossier['statut_analyse']) {
                                                    case 'conforme':
                                                        $badge_class = 'success';
                                                        break;
                                                    case 'conforme_avec_reserves':
                                                        $badge_class = 'warning';
                                                        break;
                                                    case 'non_conforme':
                                                        $badge_class = 'danger';
                                                        break;
                                                    case 'en_cours':
                                                        $badge_class = 'info';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge badge-<?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars($dossier['statut_analyse_libelle']); ?>
                                                </span>
                                                <?php if ($dossier['date_analyse']): ?>
                                                    <br><small class="text-muted">
                                                        <?php echo date('d/m/Y H:i', strtotime($dossier['date_analyse'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group-vertical btn-group-sm">
                                                    <a href="<?php echo url('modules/daj/analyse.php?id=' . $dossier['id']); ?>"
                                                       class="btn btn-primary btn-sm" title="Analyser">
                                                        <i class="fas fa-gavel"></i> Analyser
                                                    </a>
                                                    <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier['id']); ?>"
                                                       class="btn btn-outline-secondary btn-sm" title="Voir détails">
                                                        <i class="fas fa-eye"></i> Détails
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">
                                <?php
                                switch ($statut_filtre) {
                                    case 'paye':
                                        echo 'Aucun dossier en attente d\'analyse juridique';
                                        break;
                                    case 'analyse_daj':
                                        echo 'Aucun dossier analysé pour le moment';
                                        break;
                                    default:
                                        echo 'Aucun dossier trouvé';
                                }
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
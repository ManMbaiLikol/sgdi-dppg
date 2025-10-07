<?php
// Liste des huitaines actives - SGDI
require_once '../../includes/auth.php';
require_once '../../includes/huitaine_functions.php';
require_once '../dossiers/functions.php';

requireLogin();

// Vérifier les permissions
if (!hasAnyRole(['chef_service', 'admin', 'cadre_dppg', 'cadre_daj'])) {
    redirect(url('dashboard.php'), 'Accès non autorisé', 'error');
}

// Filtres
$filtre_urgents = isset($_GET['urgents']) && $_GET['urgents'] == '1';
$filtre_expires = isset($_GET['expires']) && $_GET['expires'] == '1';

$filters = [];
if ($filtre_urgents) {
    $filters['urgents_uniquement'] = true;
}
if ($filtre_expires) {
    $filters['expires_uniquement'] = true;
}

// Récupérer les huitaines
$huitaines = getHuitainesActives($filters);

// Statistiques
$stats = getStatistiquesHuitaine();

$page_title = 'Huitaines actives';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3">
                <i class="fas fa-clock"></i> Gestion des huitaines
            </h1>
            <p class="text-muted">
                Suivi des délais de régularisation (8 jours ouvrables)
            </p>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-2 col-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-clock fa-2x text-info mb-2"></i>
                    <h4 class="text-info"><?php echo $stats['en_cours']; ?></h4>
                    <p class="text-muted mb-0 small">En cours</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                    <h4 class="text-warning"><?php echo $stats['urgents']; ?></h4>
                    <p class="text-muted mb-0 small">Urgents (≤ 2j)</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                    <h4 class="text-danger"><?php echo $stats['expires']; ?></h4>
                    <p class="text-muted mb-0 small">Expirés</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <h4 class="text-success"><?php echo $stats['regularises']; ?></h4>
                    <p class="text-muted mb-0 small">Régularisés</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-ban fa-2x text-dark mb-2"></i>
                    <h4 class="text-dark"><?php echo $stats['rejetes']; ?></h4>
                    <p class="text-muted mb-0 small">Rejetés</p>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-chart-line fa-2x text-primary mb-2"></i>
                    <h4 class="text-primary"><?php echo $stats['duree_moyenne_regularisation']; ?>j</h4>
                    <p class="text-muted mb-0 small">Durée moyenne</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres et actions -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="btn-group" role="group">
                            <a href="?" class="btn btn-<?php echo !$filtre_urgents && !$filtre_expires ? 'primary' : 'outline-primary'; ?>">
                                <i class="fas fa-list"></i> Toutes
                            </a>
                            <a href="?urgents=1" class="btn btn-<?php echo $filtre_urgents ? 'warning' : 'outline-warning'; ?>">
                                <i class="fas fa-exclamation-triangle"></i> Urgentes (≤ 2j)
                            </a>
                            <a href="?expires=1" class="btn btn-<?php echo $filtre_expires ? 'danger' : 'outline-danger'; ?>">
                                <i class="fas fa-times-circle"></i> Expirées
                            </a>
                        </div>

                        <div>
                            <a href="<?php echo url('modules/dossiers/list.php'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-folder"></i> Retour aux dossiers
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des huitaines -->
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list"></i>
                        <?php if ($filtre_urgents): ?>
                            Huitaines urgentes (<?php echo count($huitaines); ?>)
                        <?php elseif ($filtre_expires): ?>
                            Huitaines expirées (<?php echo count($huitaines); ?>)
                        <?php else: ?>
                            Toutes les huitaines actives (<?php echo count($huitaines); ?>)
                        <?php endif; ?>
                    </h5>
                </div>

                <?php if (empty($huitaines)): ?>
                <div class="card-body text-center py-5">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <p class="text-muted mb-0">
                        <?php if ($filtre_urgents): ?>
                            Aucune huitaine urgente pour le moment
                        <?php elseif ($filtre_expires): ?>
                            Aucune huitaine expirée
                        <?php else: ?>
                            Aucune huitaine active
                        <?php endif; ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>N° Dossier</th>
                                <th>Demandeur</th>
                                <th>Type irrégularité</th>
                                <th>Description</th>
                                <th>Date limite</th>
                                <th>Compte à rebours</th>
                                <th>Alertes</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($huitaines as $h): ?>
                            <tr class="<?php echo $h['jours_restants'] < 0 ? 'table-danger' : ($h['jours_restants'] <= 2 ? 'table-warning' : ''); ?>">
                                <td>
                                    <a href="<?php echo url('modules/dossiers/view.php?id=' . $h['dossier_id']); ?>">
                                        <code class="text-primary"><?php echo sanitize($h['numero_dossier']); ?></code>
                                    </a>
                                </td>
                                <td>
                                    <strong><?php echo sanitize($h['nom_demandeur']); ?></strong>
                                </td>
                                <td>
                                    <small><?php echo ucfirst(str_replace('_', ' ', $h['type_irregularite'])); ?></small>
                                </td>
                                <td>
                                    <small>
                                        <?php
                                        $desc = $h['description'];
                                        echo sanitize(strlen($desc) > 60 ? substr($desc, 0, 60) . '...' : $desc);
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <small><?php echo formatDateTime($h['date_limite']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getHuitaineBadgeClass($h['jours_restants']); ?>">
                                        <?php echo formatCompteARebours($h['jours_restants'], $h['heures_restantes']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <?php if ($h['alerte_j2_envoyee']): ?>
                                            <i class="fas fa-bell text-warning" title="J-2 envoyée"></i>
                                        <?php endif; ?>
                                        <?php if ($h['alerte_j1_envoyee']): ?>
                                            <i class="fas fa-bell text-danger" title="J-1 envoyée"></i>
                                        <?php endif; ?>
                                        <?php if ($h['alerte_j_envoyee']): ?>
                                            <i class="fas fa-bell-slash text-dark" title="Alerte finale envoyée"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="<?php echo url('modules/huitaine/regulariser.php?id=' . $h['id']); ?>"
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> Régulariser
                                    </a>
                                </td>
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

<?php require_once '../../includes/footer.php'; ?>

<?php
// Liste des dossiers à viser - Sous-Directeur SDTD
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('sous_directeur');

$page_title = 'Dossiers à viser - Sous-Directeur SDTD';

// Récupérer les dossiers en attente de visa sous-directeur (après visa chef service)
$sql = "SELECT d.*,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
        DATE_FORMAT(d.date_modification, '%d/%m/%Y à %H:%i') as date_modification_format,
        u.nom as createur_nom, u.prenom as createur_prenom,
        vc.date_visa as date_visa_chef_service,
        vc.observations as observations_chef_service
        FROM dossiers d
        LEFT JOIN users u ON d.user_id = u.id
        LEFT JOIN visas vc ON d.id = vc.dossier_id AND vc.role = 'chef_service'
        WHERE d.statut = 'visa_chef_service'
        ORDER BY d.date_modification ASC";

$dossiers = $pdo->query($sql)->fetchAll();

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
                    <li class="breadcrumb-item active" aria-current="page">Dossiers à viser</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card border-warning">
                <div class="card-header bg-warning text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-stamp"></i>
                        Dossiers en attente de votre visa
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Information :</strong> Ces dossiers ont déjà reçu le visa du Chef Service SDTD et attendent maintenant votre visa (niveau 2/3).
                        Après votre approbation, ils seront transmis au Directeur DPPG pour le visa final.
                    </div>

                    <!-- Statistiques rapides -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h2 class="text-warning mb-0"><?php echo count($dossiers); ?></h2>
                                    <small class="text-muted">Dossier(s) en attente</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h2 class="text-info mb-0">2/3</h2>
                                    <small class="text-muted">Niveau de visa</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h2 class="text-primary mb-0">
                                        <i class="fas fa-arrow-right"></i>
                                    </h2>
                                    <small class="text-muted">Vers Directeur DPPG</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($dossiers)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <strong>Excellent !</strong> Aucun dossier en attente de votre visa actuellement.
                            Tous les dossiers sont à jour.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-warning">
                                    <tr>
                                        <th width="120">Numéro</th>
                                        <th>Type d'infrastructure</th>
                                        <th>Demandeur</th>
                                        <th>Localisation</th>
                                        <th>Créé par</th>
                                        <th>Visa Chef Service</th>
                                        <th>En attente depuis</th>
                                        <th width="120" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dossiers as $dossier): ?>
                                    <?php
                                    // Calculer le délai d'attente
                                    $date_visa_chef = strtotime($dossier['date_visa_chef_service'] ?? $dossier['date_modification']);
                                    $jours_attente = floor((time() - $date_visa_chef) / (60 * 60 * 24));
                                    $urgence_class = $jours_attente > 7 ? 'table-danger' : ($jours_attente > 3 ? 'table-warning' : '');
                                    ?>
                                    <tr class="<?php echo $urgence_class; ?>">
                                        <td>
                                            <strong class="text-primary">
                                                <?php echo sanitize($dossier['numero']); ?>
                                            </strong>
                                            <?php if ($jours_attente > 7): ?>
                                            <br><small class="badge bg-danger">URGENT</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo sanitize(getTypeInfrastructureLabel($dossier['type_infrastructure'])); ?>
                                            </span>
                                            <?php if ($dossier['sous_type']): ?>
                                            <br><small class="text-muted"><?php echo sanitize($dossier['sous_type']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong>
                                            <?php if ($dossier['operateur_proprietaire']): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-building"></i> <?php echo sanitize($dossier['operateur_proprietaire']); ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <i class="fas fa-map-marker-alt text-danger"></i>
                                            <?php echo sanitize($dossier['ville'] ?? 'N/A'); ?>
                                            <?php if ($dossier['region']): ?>
                                            <br><small class="text-muted"><?php echo sanitize($dossier['region']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo sanitize($dossier['createur_prenom'] . ' ' . $dossier['createur_nom']); ?>
                                                <br>
                                                <span class="text-muted"><?php echo $dossier['date_creation_format']; ?></span>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($dossier['date_visa_chef_service']): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i> Visé
                                            </span>
                                            <br><small class="text-muted">
                                                <?php echo formatDate($dossier['date_visa_chef_service']); ?>
                                            </small>
                                            <?php if ($dossier['observations_chef_service']): ?>
                                            <br><small class="text-info" title="<?php echo sanitize($dossier['observations_chef_service']); ?>">
                                                <i class="fas fa-comment"></i> Avec observations
                                            </small>
                                            <?php endif; ?>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo $jours_attente; ?> jour(s)</strong>
                                            <?php if ($jours_attente > 7): ?>
                                            <br><small class="text-danger">
                                                <i class="fas fa-exclamation-triangle"></i> Urgent
                                            </small>
                                            <?php elseif ($jours_attente > 3): ?>
                                            <br><small class="text-warning">
                                                <i class="fas fa-clock"></i> À traiter
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="viser.php?id=<?php echo $dossier['id']; ?>"
                                               class="btn btn-warning btn-sm w-100 mb-1"
                                               title="Apposer votre visa sur ce dossier">
                                                <i class="fas fa-stamp"></i> Viser
                                            </a>
                                            <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier['id']); ?>"
                                               class="btn btn-outline-secondary btn-sm w-100"
                                               title="Consulter le dossier complet"
                                               target="_blank">
                                                <i class="fas fa-eye"></i> Voir
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Légende des urgences -->
                        <div class="mt-3">
                            <h6 class="mb-2">Légende :</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="p-2 border rounded">
                                        <span class="badge bg-danger">URGENT</span>
                                        <small class="text-muted"> - Plus de 7 jours d'attente</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2 border rounded bg-light">
                                        <span class="badge bg-warning">À TRAITER</span>
                                        <small class="text-muted"> - Plus de 3 jours d'attente</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-2 border rounded">
                                        <span class="badge bg-secondary">NORMAL</span>
                                        <small class="text-muted"> - Moins de 3 jours</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

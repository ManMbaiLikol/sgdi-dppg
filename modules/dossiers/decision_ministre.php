<?php
// Décisions ministérielles - Cabinet/Secrétariat du Ministre
require_once '../../includes/auth.php';
require_once 'functions.php';

requireRole('ministre');

$page_title = 'Décisions ministérielles - Cabinet du Ministre';

// Récupérer tous les dossiers avec statut 'visa_directeur'
$sql = "SELECT d.*,
               i.id as inspection_id,
               i.conforme,
               v_chef.id as visa_chef_id,
               v_chef.date_visa as visa_chef_date,
               u_chef.nom as nom_chef_service,
               u_chef.prenom as prenom_chef_service,
               v_sd.id as visa_sd_id,
               v_sd.date_visa as visa_sd_date,
               u_sd.nom as nom_sous_directeur,
               u_sd.prenom as prenom_sous_directeur,
               v_dir.id as visa_dir_id,
               v_dir.date_visa as visa_dir_date,
               v_dir.observations as visa_dir_observations,
               u_dir.nom as nom_directeur,
               u_dir.prenom as prenom_directeur,
               DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
               DATE_FORMAT(v_dir.date_visa, '%d/%m/%Y') as date_visa_dir_format,
               DATEDIFF(NOW(), v_dir.date_visa) as jours_depuis_visa_dir
        FROM dossiers d
        LEFT JOIN inspections i ON d.id = i.dossier_id
        LEFT JOIN visas v_chef ON d.id = v_chef.dossier_id AND v_chef.role = 'chef_service'
        LEFT JOIN users u_chef ON v_chef.user_id = u_chef.id
        LEFT JOIN visas v_sd ON d.id = v_sd.dossier_id AND v_sd.role = 'sous_directeur'
        LEFT JOIN users u_sd ON v_sd.user_id = u_sd.id
        LEFT JOIN visas v_dir ON d.id = v_dir.dossier_id AND v_dir.role = 'directeur'
        LEFT JOIN users u_dir ON v_dir.user_id = u_dir.id
        WHERE d.statut = 'visa_directeur'
        ORDER BY v_dir.date_visa ASC";

$stmt = $pdo->query($sql);
$dossiers = $stmt->fetchAll();

// Statistiques
$stats = [
    'total' => count($dossiers),
    'conformes' => 0,
    'non_conformes' => 0,
    'urgent' => 0,  // Plus de 10 jours depuis visa directeur
];

foreach ($dossiers as $dossier) {
    if ($dossier['conforme']) {
        $stats['conformes']++;
    } else {
        $stats['non_conformes']++;
    }

    if ($dossier['jours_depuis_visa_dir'] > 10) {
        $stats['urgent']++;
    }
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?php echo url('dashboard.php'); ?>">
                            <i class="fas fa-home"></i> Tableau de bord
                        </a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="<?php echo url('modules/dossiers/list.php'); ?>">
                            <i class="fas fa-folder"></i> Liste des dossiers
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Décisions ministérielles</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card border-dark">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-gavel"></i>
                        Dossiers en attente de décision ministérielle finale
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-dark">
                        <i class="fas fa-info-circle"></i>
                        <strong>Votre rôle :</strong> En tant que Cabinet/Secrétariat du Ministre, vous devez prendre la décision
                        ministérielle finale sur les dossiers qui ont reçu les 3 visas (Chef Service, Sous-Directeur, Directeur DPPG).
                        Votre décision sera automatiquement publiée au registre public.
                    </div>

                    <!-- Statistiques -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-dark text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                                    <small>Total en attente</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $stats['conformes']; ?></h2>
                                    <small>Conformes</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $stats['non_conformes']; ?></h2>
                                    <small>Non conformes</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $stats['urgent']; ?></h2>
                                    <small>Urgent (>10 jours)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($dossiers)): ?>
                        <div class="alert alert-secondary">
                            <i class="fas fa-check-circle"></i>
                            <strong>Aucun dossier en attente.</strong> Tous les dossiers visés par le Directeur DPPG ont reçu une décision ministérielle.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="120">Numéro</th>
                                        <th>Type</th>
                                        <th>Demandeur</th>
                                        <th>Localisation</th>
                                        <th>Circuit de visa</th>
                                        <th width="220" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dossiers as $dossier): ?>
                                    <?php
                                    // Déterminer la classe d'urgence
                                    $urgence_class = '';
                                    if ($dossier['jours_depuis_visa_dir'] > 10) {
                                        $urgence_class = 'table-danger';
                                    } elseif ($dossier['jours_depuis_visa_dir'] > 5) {
                                        $urgence_class = 'table-warning';
                                    }
                                    ?>
                                    <tr class="<?php echo $urgence_class; ?>">
                                        <td>
                                            <strong class="text-primary">
                                                <?php echo sanitize($dossier['numero']); ?>
                                            </strong>
                                            <br><small class="text-muted">
                                                <?php echo $dossier['date_creation_format']; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong>
                                        </td>
                                        <td>
                                            <i class="fas fa-map-marker-alt text-primary"></i>
                                            <strong><?php echo sanitize($dossier['ville']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo sanitize($dossier['quartier'] ?? 'N/A'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small>
                                                <!-- 3 visas approuvés -->
                                                <div class="mb-1">
                                                    <i class="fas fa-check-circle text-success"></i>
                                                    <strong>Chef Service</strong>
                                                    <br><small class="text-muted"><?php echo sanitize($dossier['prenom_chef_service'] . ' ' . $dossier['nom_chef_service']); ?></small>
                                                </div>
                                                <div class="mb-1">
                                                    <i class="fas fa-check-circle text-success"></i>
                                                    <strong>Sous-Directeur</strong>
                                                    <br><small class="text-muted"><?php echo sanitize($dossier['prenom_sous_directeur'] . ' ' . $dossier['nom_sous_directeur']); ?></small>
                                                </div>
                                                <div>
                                                    <i class="fas fa-check-circle text-success"></i>
                                                    <strong>Directeur DPPG</strong>
                                                    <br><small class="text-muted">
                                                        <?php echo sanitize($dossier['prenom_directeur'] . ' ' . $dossier['nom_directeur']); ?>
                                                        <br><?php echo $dossier['date_visa_dir_format']; ?> (<?php echo $dossier['jours_depuis_visa_dir']; ?> j)
                                                    </small>
                                                </div>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier['id']); ?>"
                                               class="btn btn-primary btn-sm w-100 mb-1"
                                               title="Consulter le dossier complet"
                                               target="_blank">
                                                <i class="fas fa-eye"></i> Voir
                                            </a>
                                            <a href="<?php echo url('modules/dossiers/prendre_decision.php?id=' . $dossier['id']); ?>"
                                               class="btn btn-dark btn-sm w-100"
                                               title="Prendre la décision ministérielle finale">
                                                <i class="fas fa-gavel"></i> Décider
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Aide -->
                        <div class="mt-4">
                            <h6><i class="fas fa-question-circle"></i> Guide</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="fas fa-tasks text-primary"></i> Processus de décision
                                            </h6>
                                            <ol class="small mb-0">
                                                <li>Cliquer sur <strong>"Voir"</strong> pour consulter le dossier complet</li>
                                                <li>Vérifier tous les visas et observations</li>
                                                <li>Cliquer sur <strong>"Décider"</strong> pour prendre la décision finale</li>
                                                <li>La décision sera automatiquement publiée au registre public</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="fas fa-lightbulb text-warning"></i> Décisions possibles
                                            </h6>
                                            <ul class="small mb-0">
                                                <li><span class="badge bg-success">Approuver</span> : Autorisation accordée</li>
                                                <li><span class="badge bg-danger">Refuser</span> : Demande rejetée</li>
                                                <li><span class="badge bg-warning">Ajourner</span> : Complément d'information requis</li>
                                            </ul>
                                        </div>
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

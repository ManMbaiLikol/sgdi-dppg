<?php
// Viser les dossiers - Sous-Directeur SDTD
require_once '../../includes/auth.php';
require_once 'functions.php';

requireRole('sous_directeur');

$page_title = 'Viser les dossiers - Sous-Directeur SDTD';

// Récupérer tous les dossiers avec statut 'visa_chef_service'
$sql = "SELECT d.*,
               i.id as inspection_id,
               i.conforme,
               i.date_inspection,
               i.valide_par_chef_commission,
               v_chef.id as visa_chef_id,
               v_chef.action as visa_chef_action,
               v_chef.observations as visa_chef_observations,
               v_chef.date_visa as visa_chef_date,
               u_chef_service.nom as nom_chef_service,
               u_chef_service.prenom as prenom_chef_service,
               c.id as commission_id,
               u_chef_comm.nom as nom_chef_commission,
               u_chef_comm.prenom as prenom_chef_commission,
               DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
               DATE_FORMAT(v_chef.date_visa, '%d/%m/%Y') as date_visa_chef_format,
               DATEDIFF(NOW(), v_chef.date_visa) as jours_depuis_visa_chef
        FROM dossiers d
        LEFT JOIN inspections i ON d.id = i.dossier_id
        LEFT JOIN visas v_chef ON d.id = v_chef.dossier_id AND v_chef.role = 'chef_service'
        LEFT JOIN users u_chef_service ON v_chef.user_id = u_chef_service.id
        LEFT JOIN commissions c ON d.id = c.dossier_id
        LEFT JOIN users u_chef_comm ON c.chef_commission_id = u_chef_comm.id
        WHERE d.statut = 'visa_chef_service'
        ORDER BY v_chef.date_visa ASC";

$stmt = $pdo->query($sql);
$dossiers = $stmt->fetchAll();

// Statistiques
$stats = [
    'total' => count($dossiers),
    'conformes' => 0,
    'non_conformes' => 0,
    'urgent' => 0,  // Plus de 7 jours depuis visa chef service
    'sans_visa_chef' => 0  // Dossiers sans visa chef (anomalie)
];

foreach ($dossiers as $dossier) {
    // Vérifier si visa chef existe
    if ($dossier['visa_chef_id']) {
        if ($dossier['conforme']) {
            $stats['conformes']++;
        } else {
            $stats['non_conformes']++;
        }

        if ($dossier['jours_depuis_visa_chef'] > 7) {
            $stats['urgent']++;
        }
    } else {
        $stats['sans_visa_chef']++;
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
                    <li class="breadcrumb-item active" aria-current="page">Viser les dossiers (Niveau 2/3)</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-stamp"></i>
                        Dossiers visés par le Chef Service - En attente de votre visa (Niveau 2/3)
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Votre rôle :</strong> En tant que Sous-Directeur SDTD, vous devez apposer votre visa (niveau 2/3)
                        sur les dossiers qui ont été visés par le Chef Service SDTD. Après votre visa, les dossiers
                        seront transmis au Directeur DPPG pour le visa final (niveau 3/3).
                    </div>

                    <!-- Statistiques -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                                    <small>Total à viser</small>
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
                                    <small>Urgent (>7 jours)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($dossiers)): ?>
                        <div class="alert alert-secondary">
                            <i class="fas fa-check-circle"></i>
                            <strong>Aucun dossier en attente.</strong> Tous les dossiers visés par le Chef Service ont été traités.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-info">
                                    <tr>
                                        <th width="120">Numéro</th>
                                        <th>Type</th>
                                        <th>Demandeur</th>
                                        <th>Localisation</th>
                                        <th>Visa Chef Service</th>
                                        <th width="220" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dossiers as $dossier): ?>
                                    <?php
                                    // Déterminer la classe d'urgence
                                    $urgence_class = '';
                                    if ($dossier['jours_depuis_visa_chef'] > 7) {
                                        $urgence_class = 'table-danger';
                                    } elseif ($dossier['jours_depuis_visa_chef'] > 3) {
                                        $urgence_class = 'table-warning';
                                    }
                                    ?>
                                    <tr class="<?php echo $urgence_class; ?>">
                                        <td>
                                            <strong class="text-primary">
                                                <?php echo sanitize($dossier['numero']); ?>
                                            </strong>
                                            <br><small class="text-muted">
                                                Créé le<br>
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
                                            <?php if ($dossier['visa_chef_id']): ?>
                                                <div class="mb-1">
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check-circle"></i> Approuvé
                                                    </span>
                                                </div>
                                                <small class="text-muted">
                                                    Par : <?php echo sanitize($dossier['prenom_chef_service'] . ' ' . $dossier['nom_chef_service']); ?>
                                                    <br>Le : <?php echo $dossier['date_visa_chef_format']; ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-exclamation-triangle"></i> Anomalie
                                                </span>
                                                <br><small class="text-danger">
                                                    Visa chef service introuvable
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier['id']); ?>"
                                               class="btn btn-primary btn-sm w-100 mb-1"
                                               title="Consulter le dossier complet"
                                               target="_blank">
                                                <i class="fas fa-eye"></i> Voir
                                            </a>
                                            <a href="<?php echo url('modules/dossiers/apposer_visa_sous_directeur.php?id=' . $dossier['id']); ?>"
                                               class="btn btn-info btn-sm w-100"
                                               title="Apposer votre visa et transmettre au Directeur DPPG">
                                                <i class="fas fa-stamp"></i> Viser (2/3)
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
                                                <i class="fas fa-tasks text-primary"></i> Processus de visa
                                            </h6>
                                            <ol class="small mb-0">
                                                <li>Cliquer sur <strong>"Voir"</strong> pour consulter le dossier complet</li>
                                                <li>Vérifier le visa du Chef Service et les observations</li>
                                                <li>Cliquer sur <strong>"Viser (2/3)"</strong> pour apposer votre visa</li>
                                                <li>Le dossier sera automatiquement transmis au Directeur DPPG</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="fas fa-lightbulb text-warning"></i> Indicateurs de priorité
                                            </h6>
                                            <ul class="small mb-0">
                                                <li><span class="badge bg-danger">Rouge</span> : Plus de 7 jours - <strong>Urgent</strong></li>
                                                <li><span class="badge bg-warning">Jaune</span> : 3-7 jours - À traiter rapidement</li>
                                                <li><span class="badge bg-success">Vert</span> : Moins de 3 jours - Normal</li>
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

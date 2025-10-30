<?php
// Viser les dossiers inspectés - Chef Service SDTD
require_once '../../includes/auth.php';
require_once 'functions.php';

requireRole('chef_service');

$page_title = 'Viser les dossiers inspectés - Chef Service SDTD';

// Récupérer tous les dossiers avec statut 'inspecte' et inspection validée par chef commission
$sql = "SELECT d.*,
               i.id as inspection_id,
               i.conforme,
               i.date_inspection,
               i.valide_par_chef_commission,
               i.observations as observations_inspection,
               c.id as commission_id,
               u_chef.nom as nom_chef_commission,
               u_chef.prenom as prenom_chef_commission,
               u_dppg.nom as nom_cadre_dppg,
               u_dppg.prenom as prenom_cadre_dppg,
               u_daj.nom as nom_cadre_daj,
               u_daj.prenom as prenom_cadre_daj,
               DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
               DATE_FORMAT(i.date_inspection, '%d/%m/%Y') as date_inspection_format,
               DATEDIFF(NOW(), i.date_inspection) as jours_depuis_inspection
        FROM dossiers d
        INNER JOIN inspections i ON d.id = i.dossier_id
        LEFT JOIN commissions c ON d.id = c.dossier_id
        LEFT JOIN users u_chef ON c.chef_commission_id = u_chef.id
        LEFT JOIN users u_dppg ON c.cadre_dppg_id = u_dppg.id
        LEFT JOIN users u_daj ON c.cadre_daj_id = u_daj.id
        WHERE d.statut = 'inspecte'
        AND i.valide_par_chef_commission = 1
        ORDER BY i.date_inspection ASC";

$stmt = $pdo->query($sql);
$dossiers = $stmt->fetchAll();

// Statistiques
$stats = [
    'total' => count($dossiers),
    'conformes' => 0,
    'non_conformes' => 0,
    'urgent' => 0  // Plus de 7 jours depuis inspection
];

foreach ($dossiers as $dossier) {
    if ($dossier['conforme']) {
        $stats['conformes']++;
    } else {
        $stats['non_conformes']++;
    }

    if ($dossier['jours_depuis_inspection'] > 7) {
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
                    <li class="breadcrumb-item active" aria-current="page">Viser les dossiers inspectés</li>
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
                        Dossiers inspectés en attente de votre visa
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Votre rôle :</strong> En tant que Chef Service SDTD, vous devez apposer votre visa (niveau 1/3)
                        sur les dossiers qui ont été inspectés et validés par le Chef de Commission. Après votre visa, les dossiers
                        seront transmis au Sous-Directeur SDTD pour le visa de niveau 2/3.
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
                            <strong>Aucun dossier en attente.</strong> Tous les dossiers inspectés ont été visés.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-warning">
                                    <tr>
                                        <th width="120">Numéro</th>
                                        <th>Type</th>
                                        <th>Demandeur</th>
                                        <th>Inspection</th>
                                        <th>Commission</th>
                                        <th>Délai</th>
                                        <th width="220" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dossiers as $dossier): ?>
                                    <?php
                                    // Déterminer la classe d'urgence
                                    $urgence_class = '';
                                    if ($dossier['jours_depuis_inspection'] > 7) {
                                        $urgence_class = 'table-danger';
                                    } elseif ($dossier['jours_depuis_inspection'] > 3) {
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
                                            <br><small class="text-muted">
                                                <i class="fas fa-map-marker-alt"></i> <?php echo sanitize($dossier['ville']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="mb-1">
                                                <?php if ($dossier['conforme']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check-circle"></i> Conforme
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-exclamation-triangle"></i> Non conforme
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                Date : <?php echo $dossier['date_inspection_format']; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small>
                                                <div class="mb-1">
                                                    <i class="fas fa-user-tie text-primary"></i>
                                                    <strong>Chef :</strong>
                                                    <?php echo sanitize($dossier['prenom_chef_commission'] . ' ' . $dossier['nom_chef_commission']); ?>
                                                </div>
                                                <div class="mb-1">
                                                    <i class="fas fa-hard-hat text-warning"></i>
                                                    <?php echo sanitize($dossier['prenom_cadre_dppg'] . ' ' . $dossier['nom_cadre_dppg']); ?>
                                                </div>
                                                <div>
                                                    <i class="fas fa-gavel text-info"></i>
                                                    <?php echo sanitize($dossier['prenom_cadre_daj'] . ' ' . $dossier['nom_cadre_daj']); ?>
                                                </div>
                                            </small>
                                        </td>
                                        <td>
                                            <?php
                                            $jours = $dossier['jours_depuis_inspection'];
                                            $badge_class = 'bg-success';
                                            if ($jours > 7) {
                                                $badge_class = 'bg-danger';
                                            } elseif ($jours > 3) {
                                                $badge_class = 'bg-warning';
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo $jours; ?> jour<?php echo $jours > 1 ? 's' : ''; ?>
                                            </span>
                                            <?php if ($jours > 7): ?>
                                                <br><small class="text-danger">
                                                    <i class="fas fa-exclamation-triangle"></i> Urgent
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier['id']); ?>"
                                               class="btn btn-primary btn-sm w-100 mb-1"
                                               title="Consulter le dossier et le rapport d'inspection"
                                               target="_blank">
                                                <i class="fas fa-eye"></i> Voir
                                            </a>
                                            <a href="<?php echo url('modules/dossiers/apposer_visa.php?id=' . $dossier['id']); ?>"
                                               class="btn btn-warning btn-sm w-100"
                                               title="Apposer votre visa et transmettre au Sous-Directeur">
                                                <i class="fas fa-stamp"></i> Viser
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
                                                <li>Cliquer sur <strong>"Voir"</strong> pour consulter le dossier et le rapport d'inspection</li>
                                                <li>Vérifier la conformité et les observations</li>
                                                <li>Cliquer sur <strong>"Viser"</strong> pour apposer votre visa</li>
                                                <li>Le dossier sera automatiquement transmis au Sous-Directeur SDTD</li>
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

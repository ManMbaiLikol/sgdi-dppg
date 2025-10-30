<?php
// Mes commissions - Sous-Directeur SDTD Chef de Commission
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('sous_directeur');

$page_title = 'Mes commissions - Sous-Directeur SDTD';
$user_id = $_SESSION['user_id'];

// Récupérer les dossiers où l'utilisateur est chef de commission
$sql = "SELECT d.*,
               c.id as commission_id,
               c.date_constitution,
               i.id as inspection_id,
               i.conforme,
               i.valide_par_chef_commission,
               i.date_inspection,
               i.observations as observations_inspection,
               u_dppg.nom as nom_cadre_dppg,
               u_dppg.prenom as prenom_cadre_dppg,
               u_dppg.email as email_cadre_dppg,
               u_daj.nom as nom_cadre_daj,
               u_daj.prenom as prenom_cadre_daj,
               u_daj.email as email_cadre_daj,
               DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
               DATE_FORMAT(c.date_constitution, '%d/%m/%Y') as date_constitution_format,
               DATE_FORMAT(i.date_inspection, '%d/%m/%Y') as date_inspection_format
        FROM dossiers d
        INNER JOIN commissions c ON d.id = c.dossier_id
        LEFT JOIN inspections i ON d.id = i.dossier_id
        LEFT JOIN users u_dppg ON c.cadre_dppg_id = u_dppg.id
        LEFT JOIN users u_daj ON c.cadre_daj_id = u_daj.id
        WHERE c.chef_commission_id = ?
        ORDER BY d.date_modification DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$dossiers = $stmt->fetchAll();

// Statistiques
$stats = [
    'total' => count($dossiers),
    'en_attente_validation' => 0,
    'valides' => 0,
    'en_inspection' => 0,
    'termines' => 0
];

foreach ($dossiers as $dossier) {
    if ($dossier['statut'] === 'inspecte' && !$dossier['valide_par_chef_commission']) {
        $stats['en_attente_validation']++;
    }
    if ($dossier['valide_par_chef_commission']) {
        $stats['valides']++;
    }
    if ($dossier['statut'] === 'controle_completude' || $dossier['statut'] === 'paye') {
        $stats['en_inspection']++;
    }
    if (in_array($dossier['statut'], ['visa_chef_service', 'visa_sous_directeur', 'visa_directeur', 'decide', 'autorise'])) {
        $stats['termines']++;
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
                        <a href="<?php echo url('modules/sous_directeur/dashboard.php'); ?>">
                            <i class="fas fa-home"></i> Tableau de bord
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Mes commissions</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-users"></i>
                        Dossiers où je suis Chef de Commission
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Rôle de Chef de Commission :</strong> En tant que chef de commission, vous coordonnez
                        l'équipe composée d'un Cadre DPPG (inspecteur) et d'un Cadre DAJ (juriste). Vous êtes responsable
                        de la validation finale des rapports d'inspection.
                    </div>

                    <!-- Statistiques -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                                    <small>Total dossiers</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $stats['en_attente_validation']; ?></h2>
                                    <small>À valider</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $stats['en_inspection']; ?></h2>
                                    <small>En inspection</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $stats['valides']; ?></h2>
                                    <small>Validés</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($dossiers)): ?>
                        <div class="alert alert-secondary">
                            <i class="fas fa-folder-open"></i>
                            <strong>Information :</strong> Vous n'êtes actuellement nommé chef de commission sur aucun dossier.
                            Le Chef Service SDTD vous assignera des dossiers prochainement.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-primary">
                                    <tr>
                                        <th width="120">Numéro</th>
                                        <th>Type</th>
                                        <th>Demandeur</th>
                                        <th>Membres commission</th>
                                        <th>Statut</th>
                                        <th width="200" class="text-center">Actions</th>
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
                                                Commission créée le<br>
                                                <?php echo $dossier['date_constitution_format'] ?? 'N/A'; ?>
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
                                            <small>
                                                <div class="mb-1">
                                                    <i class="fas fa-hard-hat text-warning"></i>
                                                    <strong>Inspecteur :</strong>
                                                    <?php echo sanitize($dossier['prenom_cadre_dppg'] . ' ' . $dossier['nom_cadre_dppg']); ?>
                                                </div>
                                                <div>
                                                    <i class="fas fa-gavel text-info"></i>
                                                    <strong>Juriste :</strong>
                                                    <?php echo sanitize($dossier['prenom_cadre_daj'] . ' ' . $dossier['nom_cadre_daj']); ?>
                                                </div>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                                                <?php echo getStatutLabel($dossier['statut']); ?>
                                            </span>
                                            <?php if ($dossier['inspection_id']): ?>
                                                <?php if ($dossier['valide_par_chef_commission']): ?>
                                                    <br><small class="text-success mt-1">
                                                        <i class="fas fa-check-circle"></i> Inspection validée
                                                    </small>
                                                <?php else: ?>
                                                    <br><small class="text-warning mt-1">
                                                        <i class="fas fa-exclamation-triangle"></i> Inspection à valider
                                                    </small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($dossier['statut'] === 'inspecte'): ?>
                                                <?php if ($dossier['inspection_id'] && !$dossier['valide_par_chef_commission']): ?>
                                                <a href="<?php echo url('modules/chef_commission/valider_inspection.php?id=' . $dossier['id']); ?>"
                                                   class="btn btn-warning btn-sm w-100 mb-1"
                                                   title="Valider le rapport d'inspection">
                                                    <i class="fas fa-check"></i> Valider
                                                </a>
                                                <?php endif; ?>
                                                <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier['id']); ?>"
                                                   class="btn btn-primary btn-sm w-100"
                                                   title="Consulter le dossier et le rapport d'inspection"
                                                   target="_blank">
                                                    <i class="fas fa-eye"></i> Voir
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Aide -->
                        <div class="mt-4">
                            <h6><i class="fas fa-question-circle"></i> Aide</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="fas fa-tasks text-primary"></i> Votre rôle
                                            </h6>
                                            <ul class="small mb-0">
                                                <li>Coordonner les membres de la commission</li>
                                                <li>Suivre l'avancement des inspections</li>
                                                <li>Valider les rapports d'inspection</li>
                                                <li>S'assurer de la conformité des dossiers</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="fas fa-lightbulb text-warning"></i> Actions attendues
                                            </h6>
                                            <ul class="small mb-0">
                                                <li><strong>Badge "À valider"</strong> : Examiner et valider le rapport</li>
                                                <li><strong>Badge "En cours"</strong> : Attendre l'inspection terrain</li>
                                                <li><strong>Badge "Validée"</strong> : Dossier prêt pour le circuit de visa</li>
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

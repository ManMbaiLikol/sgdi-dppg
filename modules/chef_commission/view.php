<?php
// Vue détaillée d'un dossier - Chef de Commission - SGDI
require_once '../../includes/auth.php';
require_once '../dossiers/functions.php';

requireRole('chef_commission');

$dossier_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$dossier_id) {
    redirect(url('modules/chef_commission/dashboard.php'), 'Dossier non spécifié', 'error');
}

// Vérifier que le dossier existe et que l'utilisateur est bien le chef de commission
$sql = "SELECT d.*,
               c.id as commission_id,
               c.chef_commission_id,
               c.cadre_dppg_id,
               c.cadre_daj_id,
               i.id as inspection_id,
               i.date_inspection,
               i.rapport,
               i.recommandations,
               i.conforme,
               i.observations as observations_inspection,
               i.valide_par_chef_commission,
               i.date_validation_chef_commission,
               i.observations_chef_commission,
               u_dppg.nom as nom_cadre_dppg,
               u_dppg.prenom as prenom_cadre_dppg,
               u_dppg.email as email_cadre_dppg,
               u_daj.nom as nom_cadre_daj,
               u_daj.prenom as prenom_cadre_daj,
               u_daj.email as email_cadre_daj
        FROM dossiers d
        INNER JOIN commissions c ON d.id = c.dossier_id
        LEFT JOIN inspections i ON d.id = i.dossier_id
        LEFT JOIN users u_dppg ON c.cadre_dppg_id = u_dppg.id
        LEFT JOIN users u_daj ON c.cadre_daj_id = u_daj.id
        WHERE d.id = ? AND c.chef_commission_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id, $user_id]);
$dossier = $stmt->fetch();

if (!$dossier) {
    redirect(url('modules/chef_commission/dashboard.php'), 'Dossier introuvable ou vous n\'êtes pas le chef de commission', 'error');
}

// Récupérer les documents
$sql = "SELECT * FROM documents WHERE dossier_id = ? ORDER BY date_upload DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id]);
$documents = $stmt->fetchAll();

// Récupérer l'historique
$sql = "SELECT h.*, u.nom, u.prenom
        FROM historique h
        LEFT JOIN users u ON h.user_id = u.id
        WHERE h.dossier_id = ?
        ORDER BY h.date_action DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id]);
$historique = $stmt->fetchAll();

$page_title = 'Dossier ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <!-- En-tête du dossier -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">
                            <i class="fas fa-folder-open"></i> Dossier <?php echo sanitize($dossier['numero']); ?>
                        </h5>
                        <p class="mb-0">
                            <small>Chef de Commission: <?php echo sanitize($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?></small>
                        </p>
                    </div>
                    <div>
                        <span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?> fs-6">
                            <?php echo getStatutLabel($dossier['statut']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Informations générales</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Type d'infrastructure:</th>
                                <td><?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?></td>
                            </tr>
                            <tr>
                                <th>Demandeur:</th>
                                <td><strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Localisation:</th>
                                <td><?php echo sanitize($dossier['ville'] ?? 'N/A'); ?>, <?php echo sanitize($dossier['region'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Date création:</th>
                                <td><?php echo formatDate($dossier['date_creation']); ?></td>
                            </tr>
                        </table>
                    </div>

                    <div class="col-md-6">
                        <h6 class="text-primary">Membres de la commission</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">Chef de commission:</th>
                                <td>
                                    <i class="fas fa-user-tie text-primary"></i>
                                    <strong><?php echo sanitize($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?></strong>
                                </td>
                            </tr>
                            <tr>
                                <th>Cadre DPPG:</th>
                                <td>
                                    <i class="fas fa-hard-hat text-warning"></i>
                                    <?php echo sanitize($dossier['prenom_cadre_dppg'] . ' ' . $dossier['nom_cadre_dppg']); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Cadre DAJ:</th>
                                <td>
                                    <i class="fas fa-gavel text-info"></i>
                                    <?php echo sanitize($dossier['prenom_cadre_daj'] . ' ' . $dossier['nom_cadre_daj']); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analyse DAJ -->
        <?php if (in_array($dossier['statut'], ['analyse_daj', 'inspecte', 'validation_chef_commission', 'valide', 'decide'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-gavel"></i> Analyse juridique (Cadre DAJ)
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    L'analyse juridique DAJ a été effectuée pour ce dossier.
                    Le dossier est au statut: <strong><?php echo getStatutLabel($dossier['statut']); ?></strong>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Rapport d'inspection -->
        <?php if ($dossier['inspection_id']): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-file-alt"></i> Rapport d'inspection (Cadre DPPG)
                </h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Conformité technique:</strong>
                        <span class="badge bg-<?php echo $dossier['conforme'] === 'oui' ? 'success' : ($dossier['conforme'] === 'non' ? 'danger' : 'warning'); ?>">
                            <?php echo ucfirst($dossier['conforme']); ?>
                        </span>
                    </div>
                    <div class="col-md-4">
                        <strong>Date d'inspection:</strong>
                        <?php echo formatDate($dossier['date_inspection']); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Validation chef commission:</strong>
                        <?php if ($dossier['valide_par_chef_commission']): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle"></i> Validé le <?php echo formatDate($dossier['date_validation_chef_commission']); ?>
                        </span>
                        <?php else: ?>
                        <span class="badge bg-warning">
                            <i class="fas fa-clock"></i> En attente
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <strong>Rapport:</strong>
                    <div class="p-3 bg-light border rounded mt-2">
                        <?php echo nl2br(sanitize($dossier['rapport'])); ?>
                    </div>
                </div>

                <?php if ($dossier['recommandations']): ?>
                <div class="mb-3">
                    <strong>Recommandations:</strong>
                    <div class="p-3 bg-light border rounded mt-2">
                        <?php echo nl2br(sanitize($dossier['recommandations'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($dossier['observations_inspection']): ?>
                <div class="mb-3">
                    <strong>Observations de l'inspecteur:</strong>
                    <div class="p-3 bg-light border rounded mt-2">
                        <?php echo nl2br(sanitize($dossier['observations_inspection'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($dossier['observations_chef_commission']): ?>
                <div class="mb-3">
                    <strong>Vos observations (Chef de Commission):</strong>
                    <div class="p-3 bg-success bg-opacity-10 border border-success rounded mt-2">
                        <?php echo nl2br(sanitize($dossier['observations_chef_commission'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($dossier['statut'] === 'inspecte' && !$dossier['valide_par_chef_commission']): ?>
                <div class="mt-3">
                    <a href="<?php echo url('modules/chef_commission/valider_inspection.php?id=' . $dossier_id); ?>"
                       class="btn btn-warning">
                        <i class="fas fa-clipboard-check"></i> Valider cette inspection
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Documents -->
        <?php if (!empty($documents)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-file-alt"></i> Documents du dossier
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Nom du fichier</th>
                                <th>Type</th>
                                <th>Taille</th>
                                <th>Date d'upload</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><?php echo sanitize($doc['nom_original']); ?></td>
                                <td><?php echo strtoupper($doc['extension']); ?></td>
                                <td><?php echo round($doc['taille_fichier'] / 1024, 2); ?> Ko</td>
                                <td><?php echo formatDate($doc['date_upload']); ?></td>
                                <td>
                                    <a href="<?php echo url('modules/documents/download.php?id=' . $doc['id']); ?>"
                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="fas fa-download"></i> Télécharger
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Historique -->
        <?php if (!empty($historique)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-history"></i> Historique du dossier
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($historique as $h): ?>
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong><?php echo sanitize($h['action']); ?></strong>
                                <?php if ($h['ancien_statut'] && $h['nouveau_statut']): ?>
                                <br>
                                <small class="text-muted">
                                    <span class="badge bg-<?php echo getStatutClass($h['ancien_statut']); ?>"><?php echo getStatutLabel($h['ancien_statut']); ?></span>
                                    <i class="fas fa-arrow-right"></i>
                                    <span class="badge bg-<?php echo getStatutClass($h['nouveau_statut']); ?>"><?php echo getStatutLabel($h['nouveau_statut']); ?></span>
                                </small>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <small class="text-muted"><?php echo formatDateTime($h['date_action']); ?></small>
                            </div>
                        </div>
                        <?php if ($h['description']): ?>
                        <div class="mt-2">
                            <small><?php echo sanitize($h['description']); ?></small>
                        </div>
                        <?php endif; ?>
                        <?php if ($h['nom'] && $h['prenom']): ?>
                        <div class="mt-1">
                            <small class="text-muted">
                                <i class="fas fa-user"></i> <?php echo sanitize($h['prenom'] . ' ' . $h['nom']); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Boutons d'action -->
        <div class="d-flex justify-content-between mb-4">
            <a href="<?php echo url('modules/chef_commission/dashboard.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
            </a>
            <?php if ($dossier['statut'] === 'inspecte' && !$dossier['valide_par_chef_commission'] && $dossier['inspection_id']): ?>
            <a href="<?php echo url('modules/chef_commission/valider_inspection.php?id=' . $dossier_id); ?>"
               class="btn btn-warning">
                <i class="fas fa-clipboard-check"></i> Valider l'inspection
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

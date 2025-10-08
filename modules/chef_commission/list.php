<?php
// Liste des dossiers - Chef de Commission - SGDI
require_once '../../includes/auth.php';
require_once '../dossiers/functions.php';

requireRole('chef_commission');

$page_title = 'Mes dossiers - Chef de Commission';
$user_id = $_SESSION['user_id'];

// Filtres
$statut_filtre = sanitize($_GET['statut'] ?? '');

// Récupérer les dossiers où l'utilisateur est chef de commission
$sql = "SELECT d.*,
               c.id as commission_id,
               i.id as inspection_id,
               i.conforme,
               i.valide_par_chef_commission,
               i.date_inspection,
               u_dppg.nom as nom_cadre_dppg,
               u_dppg.prenom as prenom_cadre_dppg,
               u_daj.nom as nom_cadre_daj,
               u_daj.prenom as prenom_cadre_daj
        FROM dossiers d
        INNER JOIN commissions c ON d.id = c.dossier_id
        LEFT JOIN inspections i ON d.id = i.dossier_id
        LEFT JOIN users u_dppg ON c.cadre_dppg_id = u_dppg.id
        LEFT JOIN users u_daj ON c.cadre_daj_id = u_daj.id
        WHERE c.chef_commission_id = ?";

if ($statut_filtre) {
    $sql .= " AND d.statut = ?";
}

$sql .= " ORDER BY d.date_modification DESC";

$stmt = $pdo->prepare($sql);
if ($statut_filtre) {
    $stmt->execute([$user_id, $statut_filtre]);
} else {
    $stmt->execute([$user_id]);
}
$dossiers = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-folder-open"></i> Mes dossiers de commission
            </h1>
            <p class="text-muted">Dossiers où vous êtes désigné comme chef de commission</p>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="statut" class="form-label">Statut</label>
                    <select class="form-select" id="statut" name="statut" onchange="this.form.submit()">
                        <option value="">Tous les statuts</option>
                        <option value="en_cours" <?php echo $statut_filtre === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                        <option value="paye" <?php echo $statut_filtre === 'paye' ? 'selected' : ''; ?>>Payé</option>
                        <option value="analyse_daj" <?php echo $statut_filtre === 'analyse_daj' ? 'selected' : ''; ?>>Analysé DAJ</option>
                        <option value="inspecte" <?php echo $statut_filtre === 'inspecte' ? 'selected' : ''; ?>>Inspecté</option>
                        <option value="validation_commission" <?php echo $statut_filtre === 'validation_commission' ? 'selected' : ''; ?>>Validation chef commission</option>
                        <option value="valide" <?php echo $statut_filtre === 'valide' ? 'selected' : ''; ?>>Validé</option>
                        <option value="decide" <?php echo $statut_filtre === 'decide' ? 'selected' : ''; ?>>Décidé</option>
                        <option value="autorise" <?php echo $statut_filtre === 'autorise' ? 'selected' : ''; ?>>Autorisé</option>
                        <option value="rejete" <?php echo $statut_filtre === 'rejete' ? 'selected' : ''; ?>>Rejeté</option>
                    </select>
                </div>
                <div class="col-md-8 d-flex align-items-end">
                    <?php if ($statut_filtre): ?>
                    <a href="?" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Effacer les filtres
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistiques rapides -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong><?php echo count($dossiers); ?> dossier(s)</strong> trouvé(s)
                <?php if ($statut_filtre): ?>
                avec le statut <strong><?php echo getStatutLabel($statut_filtre); ?></strong>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Liste des dossiers -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-list"></i> Liste des dossiers
            </h5>
        </div>

        <?php if (empty($dossiers)): ?>
        <div class="card-body text-center py-5">
            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
            <p class="text-muted mb-0">Aucun dossier trouvé</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>N° Dossier</th>
                        <th>Type</th>
                        <th>Demandeur</th>
                        <th>Membres commission</th>
                        <th>Analyse DAJ</th>
                        <th>Inspection</th>
                        <th>Statut</th>
                        <th>Date création</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dossiers as $dossier): ?>
                    <tr>
                        <td>
                            <code class="text-primary"><?php echo sanitize($dossier['numero']); ?></code>
                        </td>
                        <td>
                            <small><?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?></small>
                        </td>
                        <td>
                            <strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong>
                            <br>
                            <small class="text-muted">
                                <?php echo sanitize($dossier['ville'] ?? 'N/A'); ?>
                            </small>
                        </td>
                        <td>
                            <small class="d-block">
                                <i class="fas fa-hard-hat text-primary"></i>
                                <?php echo sanitize($dossier['prenom_cadre_dppg'] . ' ' . $dossier['nom_cadre_dppg']); ?>
                            </small>
                            <small class="d-block">
                                <i class="fas fa-gavel text-info"></i>
                                <?php echo sanitize($dossier['prenom_cadre_daj'] . ' ' . $dossier['nom_cadre_daj']); ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($dossier['statut'] === 'analyse_daj' || in_array($dossier['statut'], ['inspecte', 'validation_commission', 'valide', 'decide'])): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check"></i> Fait
                            </span>
                            <?php else: ?>
                            <span class="text-muted">En attente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($dossier['inspection_id']): ?>
                            <span class="badge bg-<?php echo $dossier['conforme'] === 'oui' ? 'success' : ($dossier['conforme'] === 'non' ? 'danger' : 'warning'); ?>">
                                <?php echo ucfirst($dossier['conforme'] ?? 'N/A'); ?>
                            </span>
                            <br>
                            <?php if ($dossier['valide_par_chef_commission']): ?>
                            <small class="text-success">
                                <i class="fas fa-check-circle"></i> Validé
                            </small>
                            <?php else: ?>
                            <small class="text-warning">
                                <i class="fas fa-clock"></i> À valider
                            </small>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                                <?php echo getStatutLabel($dossier['statut']); ?>
                            </span>
                        </td>
                        <td>
                            <small><?php echo formatDate($dossier['date_creation']); ?></small>
                        </td>
                        <td>
                            <?php if ($dossier['statut'] === 'inspecte' && !$dossier['valide_par_chef_commission'] && $dossier['inspection_id']): ?>
                            <a href="<?php echo url('modules/chef_commission/valider_inspection.php?id=' . $dossier['id']); ?>"
                               class="btn btn-warning btn-sm">
                                <i class="fas fa-check"></i> Valider
                            </a>
                            <?php else: ?>
                            <a href="<?php echo url('modules/chef_commission/view.php?id=' . $dossier['id']); ?>"
                               class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

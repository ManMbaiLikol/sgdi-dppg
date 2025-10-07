<?php
// Dashboard Cabinet Ministre - Décision finale
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('ministre');

$page_title = 'Cabinet du Ministre - Décisions';

// Statistiques
$stats = [
    'en_attente' => 0,
    'approuves_mois' => 0,
    'rejetes_mois' => 0,
    'total_decisions' => 0
];

// Dossiers en attente de décision ministérielle
$sql_attente = "SELECT COUNT(*) FROM dossiers WHERE statut = 'visa_directeur'";
$stats['en_attente'] = $pdo->query($sql_attente)->fetchColumn();

// Décisions ce mois
$sql_mois = "SELECT decision, COUNT(*) as nb FROM decisions
             WHERE MONTH(date_decision) = MONTH(CURRENT_DATE())
             AND YEAR(date_decision) = YEAR(CURRENT_DATE())
             GROUP BY decision";
$stmt = $pdo->query($sql_mois);
while ($row = $stmt->fetch()) {
    if ($row['decision'] === 'approuve') $stats['approuves_mois'] = $row['nb'];
    if ($row['decision'] === 'rejete') $stats['rejetes_mois'] = $row['nb'];
}

// Total des décisions
$sql_total = "SELECT COUNT(*) FROM decisions";
$stats['total_decisions'] = $pdo->query($sql_total)->fetchColumn();

// Dossiers en attente de décision
$sql = "SELECT d.*,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
        DATE_FORMAT(d.date_modification, '%d/%m/%Y') as date_validation_format,
        u.nom as createur_nom, u.prenom as createur_prenom
        FROM dossiers d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.statut = 'visa_directeur'
        ORDER BY d.date_modification ASC";

$dossiers = $pdo->query($sql)->fetchAll();

// Décisions récentes
try {
    $sql_recent = "SELECT d.*, dec.decision, dec.reference_decision,
                   DATE_FORMAT(dec.date_decision, '%d/%m/%Y') as date_decision_format
                   FROM dossiers d
                   INNER JOIN decisions dec ON d.id = dec.dossier_id
                   ORDER BY dec.date_decision DESC
                   LIMIT 10";

    $stmt_recent = $pdo->query($sql_recent);
    $decisions_recentes = $stmt_recent->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur SQL dashboard ministre: " . $e->getMessage());
    $decisions_recentes = [];
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="mb-1">
                                Cabinet du Ministre
                            </h4>
                            <p class="mb-0 opacity-75">
                                <i class="fas fa-gavel"></i>
                                Ministère de l'Eau et de l'Énergie (MINEE) - Décisions finales
                            </p>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-landmark fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">En attente décision</h6>
                            <h3 class="mb-0"><?php echo $stats['en_attente']; ?></h3>
                        </div>
                        <div class="text-dark">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Approuvés ce mois</h6>
                            <h3 class="mb-0 text-success"><?php echo $stats['approuves_mois']; ?></h3>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-check-double fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Refusés ce mois</h6>
                            <h3 class="mb-0 text-danger"><?php echo $stats['rejetes_mois']; ?></h3>
                        </div>
                        <div class="text-danger">
                            <i class="fas fa-ban fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total décisions</h6>
                            <h3 class="mb-0 text-info"><?php echo $stats['total_decisions']; ?></h3>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-gavel fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions rapides -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Actions rapides</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <a href="<?php echo url('modules/carte/index.php'); ?>" class="btn btn-success btn-lg w-100 mb-2">
                                <i class="fas fa-map-marked-alt"></i><br>
                                Carte des infrastructures
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="<?php echo url('modules/dossiers/list.php'); ?>" class="btn btn-dark btn-lg w-100 mb-2">
                                <i class="fas fa-gavel"></i><br>
                                Mes décisions
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="<?php echo url('modules/dossiers/list.php?statut=autorise'); ?>" class="btn btn-success btn-lg w-100 mb-2">
                                <i class="fas fa-check-circle"></i><br>
                                Dossiers autorisés
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dossiers en attente -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">
                <i class="fas fa-folder-open"></i>
                Dossiers validés en attente de décision ministérielle
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($dossiers)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Aucun dossier en attente de décision actuellement.
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Important:</strong> Ces dossiers ont reçu tous les visas requis (Chef Service, Sous-Directeur, Directeur DPPG).
                    Ils sont prêts pour la décision ministérielle finale.
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Numéro</th>
                                <th>Type</th>
                                <th>Demandeur</th>
                                <th>Localisation</th>
                                <th>Validé le</th>
                                <th>Créé par</th>
                                <th width="200">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dossiers as $dossier): ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitize($dossier['numero']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo sanitize(getTypeInfrastructureLabel($dossier['type_infrastructure'])); ?>
                                    </span>
                                </td>
                                <td><?php echo sanitize($dossier['nom_demandeur']); ?></td>
                                <td><?php echo sanitize($dossier['ville'] ?? 'N/A'); ?></td>
                                <td><?php echo $dossier['date_validation_format']; ?></td>
                                <td><?php echo sanitize($dossier['createur_prenom'] . ' ' . $dossier['createur_nom']); ?></td>
                                <td>
                                    <a href="decider.php?id=<?php echo $dossier['id']; ?>"
                                       class="btn btn-sm btn-dark">
                                        <i class="fas fa-gavel"></i> Décider
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

    <!-- Décisions récentes -->
    <?php if (!empty($decisions_recentes)): ?>
    <div class="card">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">
                <i class="fas fa-history"></i>
                Décisions récentes
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Numéro</th>
                            <th>Type</th>
                            <th>Demandeur</th>
                            <th>Décision</th>
                            <th>Référence</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($decisions_recentes as $dec): ?>
                        <tr>
                            <td><?php echo sanitize($dec['numero']); ?></td>
                            <td><?php echo sanitize(getTypeInfrastructureLabel($dec['type_infrastructure'])); ?></td>
                            <td><?php echo sanitize($dec['nom_demandeur']); ?></td>
                            <td>
                                <?php if ($dec['decision'] === 'approuve'): ?>
                                    <span class="badge bg-success">Approuvé</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Refusé</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo sanitize($dec['reference_decision']); ?></td>
                            <td><?php echo $dec['date_decision_format']; ?></td>
                            <td>
                                <a href="<?php echo url('modules/dossiers/view.php?id=' . $dec['id']); ?>"
                                   class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i>
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
</div>


<!-- Statistiques Avancées -->
<div class="container-fluid mt-4">
    <h2 class="h4 mb-3">
        <i class="fas fa-chart-bar"></i> Statistiques Avancées
    </h2>
    <?php require_once __DIR__ . '/../../includes/dashboard_stats_avancees.php'; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>

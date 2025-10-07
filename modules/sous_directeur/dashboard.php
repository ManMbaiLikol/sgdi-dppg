<?php
// Dashboard Sous-Directeur - Circuit de visa
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('sous_directeur');

$page_title = 'Tableau de bord - Sous-Directeur';

// Statistiques
$stats = [
    'en_attente' => 0,
    'approuves_mois' => 0,
    'rejetes_mois' => 0,
    'total_vises' => 0
];

// Dossiers en attente de visa sous-directeur
$sql_attente = "SELECT COUNT(*) FROM dossiers WHERE statut = 'visa_chef_service'";
$stats['en_attente'] = $pdo->query($sql_attente)->fetchColumn();

// Mes visas ce mois
$sql_mois = "SELECT COUNT(*) FROM visas
             WHERE role = 'sous_directeur'
             AND MONTH(date_visa) = MONTH(CURRENT_DATE())
             AND YEAR(date_visa) = YEAR(CURRENT_DATE())";
$stats_mois = $pdo->query($sql_mois)->fetchColumn();

// Approuvés vs rejetés
$sql_stats = "SELECT action, COUNT(*) as nb FROM visas
              WHERE role = 'sous_directeur'
              AND MONTH(date_visa) = MONTH(CURRENT_DATE())
              AND YEAR(date_visa) = YEAR(CURRENT_DATE())
              GROUP BY action";
$stmt = $pdo->query($sql_stats);
while ($row = $stmt->fetch()) {
    if ($row['action'] === 'approuve') $stats['approuves_mois'] = $row['nb'];
    if ($row['action'] === 'rejete') $stats['rejetes_mois'] = $row['nb'];
}

// Total de mes visas
$sql_total = "SELECT COUNT(*) FROM visas WHERE role = 'sous_directeur'";
$stats['total_vises'] = $pdo->query($sql_total)->fetchColumn();

// Dossiers à viser
$sql = "SELECT d.*,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
        u.nom as createur_nom, u.prenom as createur_prenom
        FROM dossiers d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.statut = 'visa_chef_service'
        ORDER BY d.date_creation ASC";

$dossiers = $pdo->query($sql)->fetchAll();

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="mb-1">
                                Bienvenue, <?php echo sanitize($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?>
                            </h4>
                            <p class="mb-0 opacity-75">
                                <i class="fas fa-stamp"></i>
                                Sous-Directeur SDTD - Circuit de visa (Niveau 2/3)
                            </p>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-tie fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">En attente de visa</h6>
                            <h3 class="mb-0"><?php echo $stats['en_attente']; ?></h3>
                        </div>
                        <div class="text-warning">
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
                            <i class="fas fa-check-circle fa-2x"></i>
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
                            <h6 class="text-muted mb-1">Rejetés ce mois</h6>
                            <h3 class="mb-0 text-danger"><?php echo $stats['rejetes_mois']; ?></h3>
                        </div>
                        <div class="text-danger">
                            <i class="fas fa-times-circle fa-2x"></i>
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
                            <h6 class="text-muted mb-1">Total visés</h6>
                            <h3 class="mb-0 text-info"><?php echo $stats['total_vises']; ?></h3>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-stamp fa-2x"></i>
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
                            <a href="#dossiers-viser" class="btn btn-warning btn-lg w-100 mb-2">
                                <i class="fas fa-stamp"></i><br>
                                Viser les dossiers<br>
                                <small>(<?php echo $stats['en_attente']; ?> en attente)</small>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="<?php echo url('modules/carte/index.php'); ?>" class="btn btn-success btn-lg w-100 mb-2">
                                <i class="fas fa-map-marked-alt"></i><br>
                                Carte des infrastructures
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="<?php echo url('modules/dossiers/list.php'); ?>" class="btn btn-primary btn-lg w-100 mb-2">
                                <i class="fas fa-folder-open"></i><br>
                                Mes dossiers visés
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dossiers à viser -->
    <div class="card">
        <div class="card-header bg-warning text-white">
            <h5 class="mb-0">
                <i class="fas fa-folder-open"></i>
                Dossiers en attente de votre visa (après Chef Service)
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($dossiers)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Aucun dossier en attente de votre visa actuellement.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Numéro</th>
                                <th>Type</th>
                                <th>Demandeur</th>
                                <th>Localisation</th>
                                <th>Créé le</th>
                                <th>Créé par</th>
                                <th width="150">Actions</th>
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
                                <td><?php echo $dossier['date_creation_format']; ?></td>
                                <td><?php echo sanitize($dossier['createur_prenom'] . ' ' . $dossier['createur_nom']); ?></td>
                                <td>
                                    <a href="viser.php?id=<?php echo $dossier['id']; ?>"
                                       class="btn btn-sm btn-warning">
                                        <i class="fas fa-stamp"></i> Viser
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


<!-- Statistiques Avancées -->
<div class="container-fluid mt-4">
    <h2 class="h4 mb-3">
        <i class="fas fa-chart-bar"></i> Statistiques Avancées
    </h2>
    <?php require_once __DIR__ . '/../../includes/dashboard_stats_avancees.php'; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>

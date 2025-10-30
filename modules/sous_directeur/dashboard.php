<?php
// Dashboard Sous-Directeur - Circuit de visa
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('sous_directeur');

$page_title = 'Tableau de bord - Sous-Directeur';

$user_id = $_SESSION['user_id'];

// Statistiques
$stats = [
    'en_attente_visa' => 0,
    'dossiers_commission' => 0,
    'approuves_mois' => 0,
    'rejetes_mois' => 0,
    'total_vises' => 0
];

// 1. Dossiers en attente de visa sous-directeur (après visa chef service)
$sql_attente = "SELECT COUNT(*) FROM dossiers WHERE statut = 'visa_chef_service'";
$stats['en_attente_visa'] = $pdo->query($sql_attente)->fetchColumn();

// 2. Dossiers où je suis chef de commission
$sql_commission = "SELECT COUNT(*) FROM commissions WHERE chef_commission_id = ?";
$stmt = $pdo->prepare($sql_commission);
$stmt->execute([$user_id]);
$stats['dossiers_commission'] = $stmt->fetchColumn();

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

// Dossiers à viser (après Chef Service)
$sql_viser = "SELECT d.*,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
        u.nom as createur_nom, u.prenom as createur_prenom
        FROM dossiers d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.statut = 'visa_chef_service'
        ORDER BY d.date_creation ASC";
$dossiers_viser = $pdo->query($sql_viser)->fetchAll();

// Dossiers où je suis chef de commission
$sql_commission = "SELECT d.*,
               c.id as commission_id,
               i.id as inspection_id,
               i.conforme,
               i.valide_par_chef_commission,
               i.date_inspection,
               u_dppg.nom as nom_cadre_dppg,
               u_dppg.prenom as prenom_cadre_dppg,
               u_daj.nom as nom_cadre_daj,
               u_daj.prenom as prenom_cadre_daj,
               DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format
        FROM dossiers d
        INNER JOIN commissions c ON d.id = c.dossier_id
        LEFT JOIN inspections i ON d.id = i.dossier_id
        LEFT JOIN users u_dppg ON c.cadre_dppg_id = u_dppg.id
        LEFT JOIN users u_daj ON c.cadre_daj_id = u_daj.id
        WHERE c.chef_commission_id = ?
        ORDER BY d.date_modification DESC";
$stmt = $pdo->prepare($sql_commission);
$stmt->execute([$user_id]);
$dossiers_commission = $stmt->fetchAll();

// Mes dossiers visés (avec statut visa_sous_directeur ou ultérieur)
$sql_vises = "SELECT d.*,
        v.date_visa,
        v.action as visa_action,
        v.observations as visa_commentaire,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
        u.nom as createur_nom, u.prenom as createur_prenom
        FROM dossiers d
        INNER JOIN visas v ON d.id = v.dossier_id AND v.role = 'sous_directeur'
        LEFT JOIN users u ON d.user_id = u.id
        ORDER BY v.date_visa DESC";
$dossiers_vises = $pdo->query($sql_vises)->fetchAll();

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
                            <h3 class="mb-0"><?php echo $stats['en_attente_visa']; ?></h3>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Dossiers commission</h6>
                            <h3 class="mb-0 text-primary"><?php echo $stats['dossiers_commission']; ?></h3>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-users fa-2x"></i>
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
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt"></i> Actions rapides
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Viser les dossiers -->
                        <div class="col-md-3">
                            <a href="<?php echo url('modules/sous_directeur/liste_a_viser.php'); ?>"
                               class="btn btn-warning w-100 p-3 text-start position-relative"
                               style="min-height: 120px;">
                                <div class="d-flex flex-column h-100">
                                    <div class="mb-2">
                                        <i class="fas fa-stamp fa-2x"></i>
                                    </div>
                                    <h6 class="mb-1">Viser les dossiers</h6>
                                    <small class="text-white opacity-75">
                                        Apposer votre visa niveau 2/3
                                    </small>
                                    <div class="mt-auto pt-2">
                                        <span class="badge bg-white text-warning">
                                            <?php echo $stats['en_attente_visa']; ?> en attente
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Mes commissions -->
                        <div class="col-md-3">
                            <a href="<?php echo url('modules/sous_directeur/mes_commissions.php'); ?>"
                               class="btn btn-primary w-100 p-3 text-start position-relative"
                               style="min-height: 120px;">
                                <div class="d-flex flex-column h-100">
                                    <div class="mb-2">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                    <h6 class="mb-1">Mes commissions</h6>
                                    <small class="text-white opacity-75">
                                        Dossiers en tant que chef
                                    </small>
                                    <div class="mt-auto pt-2">
                                        <span class="badge bg-white text-primary">
                                            <?php echo $stats['dossiers_commission']; ?> dossier(s)
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Mes dossiers visés -->
                        <div class="col-md-3">
                            <a href="<?php echo url('modules/sous_directeur/mes_dossiers_vises.php'); ?>"
                               class="btn btn-info w-100 p-3 text-start position-relative"
                               style="min-height: 120px;">
                                <div class="d-flex flex-column h-100">
                                    <div class="mb-2">
                                        <i class="fas fa-history fa-2x"></i>
                                    </div>
                                    <h6 class="mb-1">Mes dossiers visés</h6>
                                    <small class="text-white opacity-75">
                                        Historique de vos visas
                                    </small>
                                    <div class="mt-auto pt-2">
                                        <span class="badge bg-white text-info">
                                            <?php echo count($dossiers_vises); ?> dossier(s)
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Carte des infrastructures -->
                        <div class="col-md-3">
                            <a href="<?php echo url('modules/carte/index.php'); ?>"
                               class="btn btn-success w-100 p-3 text-start position-relative"
                               style="min-height: 120px;">
                                <div class="d-flex flex-column h-100">
                                    <div class="mb-2">
                                        <i class="fas fa-map-marked-alt fa-2x"></i>
                                    </div>
                                    <h6 class="mb-1">Carte interactive</h6>
                                    <small class="text-white opacity-75">
                                        Visualisation géographique
                                    </small>
                                    <div class="mt-auto pt-2">
                                        <span class="badge bg-white text-success">
                                            <i class="fas fa-globe"></i> Voir la carte
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
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

<?php
// Dashboard principal - SGDI MVP
require_once 'includes/auth.php';
require_once 'modules/dossiers/functions.php';
require_once 'includes/huitaine_functions.php';

requireLogin();

$page_title = 'Tableau de bord';

// Statistiques selon le rôle
$stats = getStatistiquesDossiers($_SESSION['user_role']);

// Statistiques des huitaines (pour certains rôles)
$stats_huitaine = [];
if (hasAnyRole(['chef_service', 'admin', 'cadre_dppg', 'cadre_daj'])) {
    $stats_huitaine = getStatistiquesHuitaine();
}

// Pour le chef de service, ajouter les statistiques géographiques
$stats_geo = [];
$types_infrastructure = [];
$type_filtre = '';
$type_localisation = '';

if ($_SESSION['user_role'] === 'chef_service') {
    $type_filtre = sanitize($_GET['type_infrastructure'] ?? '');
    $type_localisation = sanitize($_GET['type_localisation'] ?? '');
    $stats_geo = getStatistiquesGeographiques($type_filtre, $type_localisation);
    $types_infrastructure = getTypesInfrastructureDisponibles();
}

// Statistiques supplémentaires pour l'admin
$stats_infrastructures = [];
$stats_infrastructures_fermees = [];
$operateurs_actifs = [];
$evolution_paiements = [];
$motifs_rejet = [];

if ($_SESSION['user_role'] === 'admin') {
    $stats_infrastructures = getStatistiquesInfrastructuresParType();
    $stats_infrastructures_fermees = getStatistiquesInfrastructuresParStatut();
    $operateurs_actifs = getOperateursPlusActifs(5);
    $evolution_paiements = getEvolutionMensuellesPaiements();
    $motifs_rejet = getTop5MotifsRejet();
}

// Actions rapides selon le rôle
$actions_rapides = [];
$dossiers_recents = [];

switch ($_SESSION['user_role']) {
    case 'chef_service':
        $actions_rapides = [
            ['url' => url('modules/dossiers/create.php'), 'icon' => 'fas fa-plus-circle', 'label' => 'Nouveau dossier', 'class' => 'primary'],
            ['url' => url('modules/carte/index.php'), 'icon' => 'fas fa-map-marked-alt', 'label' => 'Carte des infrastructures', 'class' => 'success'],
            ['url' => url('modules/dossiers/list.php?statut=autorise'), 'icon' => 'fas fa-cogs', 'label' => 'Gestion opérationnelle', 'class' => 'warning'],
            ['url' => url('modules/notes_frais/list.php'), 'icon' => 'fas fa-file-invoice-dollar', 'label' => 'Notes de frais', 'class' => 'info'],
            ['url' => url('modules/paiements/list.php'), 'icon' => 'fas fa-money-check-alt', 'label' => 'Paiements', 'class' => 'success'],
            ['url' => url('modules/dossiers/list.php'), 'icon' => 'fas fa-folder-open', 'label' => 'Tous dossiers', 'class' => 'secondary']
        ];
        $dossiers_recents = getDossiers(['user_role' => 'chef_service'], 5);
        break;

    case 'billeteur':
        $actions_rapides = [
            ['url' => url('modules/dossiers/list.php?statut=en_cours'), 'icon' => 'fas fa-money-bill', 'label' => 'Enregistrer paiements', 'class' => 'success'],
            ['url' => url('modules/carte/index.php'), 'icon' => 'fas fa-map-marked-alt', 'label' => 'Carte des infrastructures', 'class' => 'info'],
            ['url' => url('modules/dossiers/list.php'), 'icon' => 'fas fa-folder-open', 'label' => 'Tous les dossiers', 'class' => 'secondary']
        ];
        $dossiers_recents = getDossiers(['statut' => 'en_cours', 'user_role' => 'billeteur'], 5);
        break;

    case 'cadre_daj':
        $actions_rapides = [
            ['url' => url('modules/daj/list.php?statut=paye'), 'icon' => 'fas fa-gavel', 'label' => 'Analyser juridiquement', 'class' => 'info'],
            ['url' => url('modules/carte/index.php'), 'icon' => 'fas fa-map-marked-alt', 'label' => 'Carte des infrastructures', 'class' => 'success'],
            ['url' => url('modules/dossiers/list.php'), 'icon' => 'fas fa-folder-open', 'label' => 'Tous les dossiers', 'class' => 'secondary']
        ];
        $dossiers_recents = getDossiers(['statut' => 'paye', 'user_role' => 'cadre_daj'], 5);
        break;

    case 'cadre_dppg':
        $actions_rapides = [
            ['url' => url('modules/dossiers/list.php?statut=analyse_daj'), 'icon' => 'fas fa-search', 'label' => 'Faire inspections', 'class' => 'warning'],
            ['url' => url('modules/carte/index.php'), 'icon' => 'fas fa-map-marked-alt', 'label' => 'Carte des infrastructures', 'class' => 'success'],
            ['url' => url('modules/dossiers/list.php'), 'icon' => 'fas fa-folder-open', 'label' => 'Tous les dossiers', 'class' => 'secondary']
        ];
        $dossiers_recents = getDossiers(['statut' => 'analyse_daj', 'user_role' => 'cadre_dppg'], 5);
        break;

    case 'chef_commission':
        $actions_rapides = [
            ['url' => url('modules/chef_commission/dashboard.php'), 'icon' => 'fas fa-clipboard-check', 'label' => 'Mon tableau de bord', 'class' => 'primary'],
            ['url' => url('modules/chef_commission/list.php?statut=inspecte'), 'icon' => 'fas fa-check-circle', 'label' => 'Valider inspections', 'class' => 'warning'],
            ['url' => url('modules/carte/index.php'), 'icon' => 'fas fa-map-marked-alt', 'label' => 'Carte des infrastructures', 'class' => 'success'],
            ['url' => url('modules/chef_commission/list.php'), 'icon' => 'fas fa-folder-open', 'label' => 'Mes dossiers', 'class' => 'secondary']
        ];
        // Rediriger vers le dashboard spécifique
        redirect(url('modules/chef_commission/dashboard.php'));
        break;

    case 'sous_directeur':
        // Rediriger vers le dashboard spécifique
        redirect(url('modules/sous_directeur/dashboard.php'));
        break;

    case 'directeur':
        // Rediriger vers le dashboard spécifique
        redirect(url('modules/directeur/dashboard.php'));
        break;

    case 'ministre':
        // Rediriger vers le dashboard spécifique
        redirect(url('modules/ministre/dashboard.php'));
        break;

    case 'lecteur':
        // Rediriger vers le registre public
        redirect(url('modules/lecteur/dashboard.php'));
        break;

    case 'admin':
        $actions_rapides = [
            ['url' => url('modules/admin/dashboard_avance.php'), 'icon' => 'fas fa-chart-line', 'label' => 'Dashboard Avancé', 'class' => 'primary'],
            ['url' => url('modules/users/list.php'), 'icon' => 'fas fa-users', 'label' => 'Gérer utilisateurs', 'class' => 'secondary'],
            ['url' => url('modules/dossiers/list.php'), 'icon' => 'fas fa-folder', 'label' => 'Tous les dossiers', 'class' => 'info'],
            ['url' => url('modules/carte/index.php'), 'icon' => 'fas fa-map-marked-alt', 'label' => 'Carte des infrastructures', 'class' => 'success'],
            ['url' => url('modules/users/reset_password.php'), 'icon' => 'fas fa-key', 'label' => 'Réinitialiser mots de passe', 'class' => 'warning']
        ];
        $dossiers_recents = getDossiers([], 10);
        break;
}

require_once 'includes/header.php';
?>

<!-- En-tête de bienvenue -->
<div class="row mb-4">
    <div class="col">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="mb-1">
                            Bienvenue, <?php echo sanitize($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']); ?>
                        </h4>
                        <p class="mb-0 opacity-75">
                            <i class="fas fa-user-tag"></i>
                            <?php echo getRoleLabel($_SESSION['user_role']); ?> -
                            Connecté le <?php echo formatDateTime(date('Y-m-d H:i:s'), 'd/m/Y à H:i'); ?>
                        </p>
                    </div>
                    <?php if ($_SESSION['user_role'] === 'chef_service'): ?>
                    <div class="col-auto">
                        <a href="<?php echo url('modules/chef_service/dashboard_avance.php'); ?>" class="btn btn-light">
                            <i class="fas fa-chart-line"></i> Dashboard Avancé
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="col-auto">
                        <i class="fas fa-building fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <?php
    // Définir les statistiques selon le rôle
    $all_stats = [];
    switch ($_SESSION['user_role']) {
        case 'chef_service':
            $all_stats = [
                'total' => ['icon' => 'fas fa-folder', 'color' => 'primary', 'label' => 'Total dossiers', 'value' => $stats['total'] ?? 0],
                'en_cours' => ['icon' => 'fas fa-clock', 'color' => 'warning', 'label' => 'En cours', 'value' => $stats['par_statut']['en_cours'] ?? 0],
                'decide' => ['icon' => 'fas fa-check', 'color' => 'dark', 'label' => 'Décidés', 'value' => $stats['par_statut']['decide'] ?? 0],
                'autorise' => ['icon' => 'fas fa-check-circle', 'color' => 'success', 'label' => 'Autorisés', 'value' => $stats['par_statut']['autorise'] ?? 0],
                'rejete' => ['icon' => 'fas fa-times-circle', 'color' => 'danger', 'label' => 'Rejetés', 'value' => $stats['par_statut']['rejete'] ?? 0]
            ];
            break;
        case 'admin':
            $utilisateurs_actifs = getUtilisateursActifs30j();
            $all_stats = [
                'total' => ['icon' => 'fas fa-folder', 'color' => 'primary', 'label' => 'Total dossiers', 'value' => $stats['total'] ?? 0],
                'utilisateurs_actifs' => ['icon' => 'fas fa-users', 'color' => 'info', 'label' => 'Utilisateurs actifs (30j)', 'value' => $utilisateurs_actifs],
                'en_cours' => ['icon' => 'fas fa-clock', 'color' => 'warning', 'label' => 'En cours', 'value' => $stats['par_statut']['en_cours'] ?? 0],
                'autorise' => ['icon' => 'fas fa-check-circle', 'color' => 'success', 'label' => 'Autorisés', 'value' => $stats['par_statut']['autorise'] ?? 0],
                'rejete' => ['icon' => 'fas fa-times-circle', 'color' => 'danger', 'label' => 'Rejetés', 'value' => $stats['par_statut']['rejete'] ?? 0]
            ];
            break;
        case 'billeteur':
            $all_stats = [
                'dossiers_a_payer' => ['icon' => 'fas fa-folder', 'color' => 'primary', 'label' => 'Dossiers à payer', 'value' => $stats['par_statut']['en_cours'] ?? 0],
                'total_encaisse' => ['icon' => 'fas fa-money-bill-wave', 'color' => 'success', 'label' => '💰 Total encaissé (Mois)', 'value' => number_format($stats['par_statut']['total_encaisse'] ?? 0, 0, ',', ' ') . ' FCFA'],
                'paye' => ['icon' => 'fas fa-check-circle', 'color' => 'success', 'label' => '✅ Paiements validés', 'value' => $stats['par_statut']['paye'] ?? 0],
                'en_cours' => ['icon' => 'fas fa-clock', 'color' => 'warning', 'label' => '⏳ En attente de paiement', 'value' => $stats['par_statut']['en_cours'] ?? 0],
                'rejete' => ['icon' => 'fas fa-times-circle', 'color' => 'danger', 'label' => '❌ Rejetés faute de paiement', 'value' => $stats['par_statut']['rejete'] ?? 0]
            ];
            break;
        case 'cadre_daj':
            $all_stats = [
                'total' => ['icon' => 'fas fa-folder', 'color' => 'primary', 'label' => 'Dossiers à analyser', 'value' => $stats['total'] ?? 0],
                'paye' => ['icon' => 'fas fa-gavel', 'color' => 'info', 'label' => 'À analyser', 'value' => $stats['par_statut']['paye'] ?? 0],
                'analyse_daj' => ['icon' => 'fas fa-check-circle', 'color' => 'success', 'label' => 'Analysés', 'value' => $stats['par_statut']['analyse_daj'] ?? 0]
            ];
            break;
        case 'cadre_dppg':
            $all_stats = [
                'total' => ['icon' => 'fas fa-folder', 'color' => 'primary', 'label' => 'Dossiers disponibles', 'value' => $stats['total'] ?? 0],
                'paye' => ['icon' => 'fas fa-search', 'color' => 'info', 'label' => 'À inspecter', 'value' => $stats['par_statut']['paye'] ?? 0],
                'inspecte' => ['icon' => 'fas fa-file-alt', 'color' => 'success', 'label' => 'Inspectés', 'value' => $stats['par_statut']['inspecte'] ?? 0]
            ];
            break;
        case 'chef_commission':
            // Redirection déjà faite plus haut, ce code ne sera jamais atteint
            break;
        case 'directeur':
            $all_stats = [
                'total' => ['icon' => 'fas fa-folder', 'color' => 'primary', 'label' => 'Dossiers à valider', 'value' => $stats['total'] ?? 0],
                'validation_commission' => ['icon' => 'fas fa-eye', 'color' => 'warning', 'label' => 'À valider', 'value' => $stats['par_statut']['validation_commission'] ?? 0],
                'valide' => ['icon' => 'fas fa-gavel', 'color' => 'info', 'label' => 'À décider', 'value' => $stats['par_statut']['valide'] ?? 0]
            ];
            break;
        default:
            $all_stats = [
                'total' => ['icon' => 'fas fa-folder', 'color' => 'primary', 'label' => 'Total dossiers', 'value' => $stats['total'] ?? 0]
            ];
    }

    // Calculer la largeur de colonne selon le nombre de statistiques
    $col_count = count($all_stats);
    if ($col_count == 5) {
        $col_class = 'col-md-2 col-6'; // Pour le billeteur et chef_service (5 statistiques)
    } elseif ($col_count == 4) {
        $col_class = 'col-md-3 col-6';
    } elseif ($col_count == 3) {
        $col_class = 'col-md-4 col-6';
    } else {
        $col_class = 'col-md-6 col-6';
    }

    foreach ($all_stats as $key => $config):
    ?>
    <div class="<?php echo $col_class; ?> mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="<?php echo $config['icon']; ?> fa-2x text-<?php echo $config['color']; ?> mb-2"></i>
                <h4 class="text-<?php echo $config['color']; ?>"><?php echo $config['value']; ?></h4>
                <p class="text-muted mb-0"><?php echo $config['label']; ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Alerte huitaines urgentes -->
<?php if (!empty($stats_huitaine) && ($stats_huitaine['urgents'] > 0 || $stats_huitaine['expires'] > 0)): ?>
<div class="row mb-4">
    <div class="col">
        <div class="alert alert-<?php echo $stats_huitaine['expires'] > 0 ? 'danger' : 'warning'; ?> mb-0">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="alert-heading mb-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php if ($stats_huitaine['expires'] > 0): ?>
                            Huitaines expirées nécessitant une action immédiate !
                        <?php else: ?>
                            Huitaines urgentes
                        <?php endif; ?>
                    </h5>
                    <p class="mb-0">
                        <?php if ($stats_huitaine['expires'] > 0): ?>
                            <strong><?php echo $stats_huitaine['expires']; ?></strong> dossier(s) en huitaine expiré(s) - Rejet automatique imminent
                        <?php endif; ?>
                        <?php if ($stats_huitaine['urgents'] > 0): ?>
                            <?php if ($stats_huitaine['expires'] > 0) echo ' | '; ?>
                            <strong><?php echo $stats_huitaine['urgents']; ?></strong> dossier(s) urgent(s) (≤ 2 jours)
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="<?php echo url('modules/huitaine/list.php?urgents=1'); ?>" class="btn btn-light">
                        <i class="fas fa-eye"></i> Voir les huitaines urgentes
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Actions rapides -->
<?php if (!empty($actions_rapides)): ?>
<div class="row mb-4">
    <div class="col">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt"></i> Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    $action_count = count($actions_rapides);
                    if ($action_count == 2) {
                        $action_col_class = 'col-md-6';
                    } elseif ($action_count == 3) {
                        $action_col_class = 'col-md-4 col-6';
                    } elseif ($action_count == 4) {
                        $action_col_class = 'col-md-3 col-6';
                    } elseif ($action_count == 5) {
                        $action_col_class = 'col-md-2 col-6';
                    } elseif ($action_count == 6) {
                        $action_col_class = 'col-md-2 col-6';
                    } else {
                        $action_col_class = 'col-md-4';
                    }

                    foreach ($actions_rapides as $action):
                    ?>
                    <div class="<?php echo $action_col_class; ?> mb-3">
                        <a href="<?php echo $action['url']; ?>" class="btn btn-<?php echo $action['class']; ?> w-100 d-flex flex-column align-items-center justify-content-center" style="height: 120px; text-align: center;">
                            <i class="<?php echo $action['icon']; ?> fa-2x mb-2"></i>
                            <span style="font-size: 0.9rem; line-height: 1.2; font-weight: 500;"><?php echo $action['label']; ?></span>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Dossiers récents/pertinents -->
<div class="row">
    <div class="col">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history"></i>
                    <?php
                    switch ($_SESSION['user_role']) {
                        case 'billeteur':
                            echo 'Dossiers en attente de paiement';
                            break;
                        case 'cadre_daj':
                            echo 'Dossiers à analyser juridiquement';
                            break;
                        case 'cadre_dppg':
                            echo 'Dossiers à inspecter';
                            break;
                        case 'directeur':
                            echo 'Dossiers nécessitant votre attention';
                            break;
                        default:
                            echo 'Dossiers récents';
                    }
                    ?>
                </h5>
                <a href="<?php echo url('modules/dossiers/list.php'); ?>" class="btn btn-outline-primary btn-sm">
                    Voir tous <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <?php if (empty($dossiers_recents)): ?>
            <div class="card-body text-center py-5">
                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">
                    <?php
                    switch ($_SESSION['user_role']) {
                        case 'billeteur':
                            echo 'Aucun dossier en attente de paiement';
                            break;
                        case 'cadre_daj':
                            echo 'Aucun dossier à analyser juridiquement';
                            break;
                        case 'cadre_dppg':
                            echo 'Aucun dossier à inspecter actuellement';
                            break;
                        case 'directeur':
                            echo 'Aucun dossier nécessitant votre attention';
                            break;
                        default:
                            echo 'Aucun dossier pour le moment';
                    }
                    ?>
                </p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>N° Dossier</th>
                            <th>Type</th>
                            <th>Demandeur</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dossiers_recents as $dossier): ?>
                        <tr>
                            <td>
                                <code class="text-primary"><?php echo sanitize($dossier['numero']); ?></code>
                            </td>
                            <td>
                                <small><?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                                    <?php echo getStatutLabel($dossier['statut']); ?>
                                </span>
                                <?php if ($dossier['statut'] === 'autorise'): ?>
                                <div class="small text-success mt-1">
                                    <i class="fas fa-check-circle"></i> Infrastructure autorisée
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?php echo formatDate($dossier['date_creation']); ?></small>
                            </td>
                            <td>
                                <?php
                                $actions = getActionsPossibles($dossier, $_SESSION['user_role']);
                                $action_principale = $actions[0] ?? ['action' => 'voir_details', 'label' => 'Voir', 'class' => 'outline-secondary'];

                                $url_path = '';
                                switch ($action_principale['action']) {
                                    case 'voir_details':
                                        $url_path = 'modules/dossiers/view.php?id=' . $dossier['id'];
                                        break;
                                    case 'constituer_commission':
                                        $url_path = 'modules/dossiers/commission.php?id=' . $dossier['id'];
                                        break;
                                    case 'analyser_dossier':
                                        $url_path = 'modules/dossiers/analyse_daj.php?id=' . $dossier['id'];
                                        break;
                                    case 'enregistrer_paiement':
                                        $url_path = 'modules/dossiers/paiement.php?id=' . $dossier['id'];
                                        break;
                                    case 'faire_inspection':
                                        $url_path = 'modules/dossiers/inspection.php?id=' . $dossier['id'];
                                        break;
                                    case 'valider_rapport':
                                    case 'prendre_decision':
                                        $url_path = 'modules/dossiers/decision.php?id=' . $dossier['id'];
                                        break;
                                    case 'marquer_autorise':
                                        $url_path = 'modules/dossiers/marquer_autorise.php?id=' . $dossier['id'];
                                        break;
                                    case 'gestion_operationnelle':
                                        $url_path = 'modules/dossiers/gestion_operationnelle.php?id=' . $dossier['id'];
                                        break;
                                    case 'upload_documents':
                                        $url_path = 'modules/dossiers/upload_documents.php?id=' . $dossier['id'];
                                        break;
                                    default:
                                        $url_path = 'modules/dossiers/view.php?id=' . $dossier['id'];
                                }
                                $url = url($url_path);
                                ?>
                                <a href="<?php echo $url; ?>" class="btn btn-<?php echo $action_principale['class']; ?> btn-sm">
                                    <?php echo $action_principale['label']; ?>
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

<!-- Statistiques par type d'infrastructure (pour admin et chef service) -->
<?php if (hasAnyRole(['admin', 'chef_service']) && !empty($stats['par_type'])): ?>
<div class="row mt-4">
    <div class="col">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie"></i> Répartition par type d'infrastructure
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($stats['par_type'] as $type => $count): ?>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <div class="flex-grow-1">
                                <strong><?php echo getTypeLabel($type); ?></strong>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary"><?php echo $count; ?></span>
                            </div>
                        </div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar" style="width: <?php echo ($count / $stats['total']) * 100; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Statistiques d'infrastructures (pour admin seulement) -->
<?php if ($_SESSION['user_role'] === 'admin'): ?>
<div class="row mt-4">
    <!-- Infrastructures opérationnelles -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-check-circle text-success"></i> Infrastructures opérationnelles
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_infrastructures)): ?>
                <div class="row">
                    <div class="col-6 mb-3">
                        <div class="text-center">
                            <div class="h4 text-success mb-1"><?php echo $stats_infrastructures['stations']; ?></div>
                            <div class="text-muted small">Stations-service</div>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="text-center">
                            <div class="h4 text-info mb-1"><?php echo $stats_infrastructures['points_consommateurs']; ?></div>
                            <div class="text-muted small">Points consommateurs</div>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="text-center">
                            <div class="h4 text-warning mb-1"><?php echo $stats_infrastructures['depots']; ?></div>
                            <div class="text-muted small">Dépôts GPL</div>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="text-center">
                            <div class="h4 text-danger mb-1"><?php echo $stats_infrastructures['centres_emplisseurs']; ?></div>
                            <div class="text-muted small">Centres emplisseurs</div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                    <p class="mb-0">Aucune infrastructure opérationnelle</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Infrastructures fermées/démantelées -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-times-circle text-danger"></i> Infrastructures fermées/démantelées
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_infrastructures_fermees)): ?>
                <div class="row">
                    <div class="col-12 mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Fermées temporairement</span>
                            <span class="badge bg-warning"><?php echo $stats_infrastructures_fermees['ferme_temporaire']; ?></span>
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Fermées définitivement</span>
                            <span class="badge bg-danger"><?php echo $stats_infrastructures_fermees['ferme_definitif']; ?></span>
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Démantelées</span>
                            <span class="badge bg-dark"><?php echo $stats_infrastructures_fermees['demantele']; ?></span>
                        </div>
                    </div>
                    <hr>
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>Total fermées/démantelées</strong>
                            <span class="badge bg-secondary"><?php echo array_sum($stats_infrastructures_fermees); ?></span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                    <p class="mb-0">Aucune infrastructure fermée</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Statistiques géographiques pour le chef de service -->
<?php if ($_SESSION['user_role'] === 'chef_service' && !empty($types_infrastructure)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-map-marked-alt"></i> Répartition géographique des dossiers
                </h5>
            </div>
            <div class="card-body">
                <!-- Filtres -->
                <form method="GET" class="mb-4" id="filtresGeo">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="type_infrastructure" class="form-label">Type d'infrastructure</label>
                            <select class="form-select" id="type_infrastructure" name="type_infrastructure" onchange="submitForm()">
                                <option value="">Tous les types</option>
                                <?php foreach ($types_infrastructure as $type => $count): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>"
                                        <?php echo $type_filtre === $type ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $type)); ?> (<?php echo $count; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="type_localisation" class="form-label">Type de localisation</label>
                            <select class="form-select" id="type_localisation" name="type_localisation" onchange="submitForm()">
                                <option value="">Affichage complet</option>
                                <option value="region" <?php echo $type_localisation === 'region' ? 'selected' : ''; ?>>
                                    Régions seulement
                                </option>
                                <option value="arrondissement" <?php echo $type_localisation === 'arrondissement' ? 'selected' : ''; ?>>
                                    Arrondissements seulement
                                </option>
                                <option value="ville" <?php echo $type_localisation === 'ville' ? 'selected' : ''; ?>>
                                    Villes seulement
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <?php if (!empty($type_filtre) || !empty($type_localisation)): ?>
                            <a href="?" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Effacer les filtres
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <script>
                function submitForm() {
                    const form = document.getElementById('filtresGeo');
                    const typeInfra = document.getElementById('type_infrastructure').value;
                    const typeLoc = document.getElementById('type_localisation').value;

                    // Construire l'URL avec les deux paramètres
                    let url = '?';
                    if (typeInfra) url += 'type_infrastructure=' + encodeURIComponent(typeInfra) + '&';
                    if (typeLoc) url += 'type_localisation=' + encodeURIComponent(typeLoc) + '&';

                    // Retirer le & final s'il existe
                    url = url.replace(/&$/, '');

                    window.location.href = url;
                }
                </script>

                <!-- Répartitions géographiques -->
                <?php if (empty($type_localisation)): ?>
                <!-- Affichage complet (3 colonnes) -->
                <div class="row">
                    <!-- Répartition par région -->
                    <div class="col-md-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-globe"></i> Par région
                            <?php if (!empty($type_filtre)): ?>
                                <small class="text-muted">(<?php echo ucfirst(str_replace('_', ' ', $type_filtre)); ?>)</small>
                            <?php endif; ?>
                        </h6>
                        <?php if (!empty($stats_geo['par_region'])): ?>
                            <?php $total_regions = array_sum($stats_geo['par_region']); ?>
                            <?php foreach ($stats_geo['par_region'] as $region => $count): ?>
                            <div class="d-flex align-items-center mb-2">
                                <div class="flex-grow-1">
                                    <small><?php echo htmlspecialchars($region); ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-info"><?php echo $count; ?></span>
                                </div>
                            </div>
                            <div class="progress mb-3" style="height: 6px;">
                                <div class="progress-bar bg-info" style="width: <?php echo ($count / $total_regions) * 100; ?>%"></div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted small">Aucune donnée disponible</p>
                        <?php endif; ?>
                    </div>

                    <!-- Répartition par arrondissement -->
                    <div class="col-md-4">
                        <h6 class="text-success mb-3">
                            <i class="fas fa-map"></i> Par arrondissement
                            <?php if (!empty($type_filtre)): ?>
                                <small class="text-muted">(<?php echo ucfirst(str_replace('_', ' ', $type_filtre)); ?>)</small>
                            <?php endif; ?>
                        </h6>
                        <?php if (!empty($stats_geo['par_arrondissement'])): ?>
                            <?php $total_arrondissements = array_sum($stats_geo['par_arrondissement']); ?>
                            <?php foreach (array_slice($stats_geo['par_arrondissement'], 0, 8) as $arrondissement => $count): ?>
                            <div class="d-flex align-items-center mb-2">
                                <div class="flex-grow-1">
                                    <small><?php echo htmlspecialchars($arrondissement); ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success"><?php echo $count; ?></span>
                                </div>
                            </div>
                            <div class="progress mb-3" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: <?php echo ($count / $total_arrondissements) * 100; ?>%"></div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($stats_geo['par_arrondissement']) > 8): ?>
                                <small class="text-muted">... et <?php echo count($stats_geo['par_arrondissement']) - 8; ?> autres</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-muted small">Aucune donnée disponible</p>
                        <?php endif; ?>
                    </div>

                    <!-- Répartition par ville -->
                    <div class="col-md-4">
                        <h6 class="text-warning mb-3">
                            <i class="fas fa-city"></i> Par ville (Top 10)
                            <?php if (!empty($type_filtre)): ?>
                                <small class="text-muted">(<?php echo ucfirst(str_replace('_', ' ', $type_filtre)); ?>)</small>
                            <?php endif; ?>
                        </h6>
                        <?php if (!empty($stats_geo['par_ville'])): ?>
                            <?php $total_villes = array_sum($stats_geo['par_ville']); ?>
                            <?php foreach ($stats_geo['par_ville'] as $ville => $count): ?>
                            <div class="d-flex align-items-center mb-2">
                                <div class="flex-grow-1">
                                    <small><?php echo htmlspecialchars($ville); ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-warning"><?php echo $count; ?></span>
                                </div>
                            </div>
                            <div class="progress mb-3" style="height: 6px;">
                                <div class="progress-bar bg-warning" style="width: <?php echo ($count / $total_villes) * 100; ?>%"></div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted small">Aucune donnée disponible</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <!-- Affichage filtré (1 colonne large) -->
                <div class="row">
                    <div class="col-12">
                        <?php
                        $titre_section = '';
                        $icone_section = '';
                        $couleur_section = '';
                        $data_section = [];

                        switch($type_localisation) {
                            case 'region':
                                $titre_section = 'Répartition par région';
                                $icone_section = 'fas fa-globe';
                                $couleur_section = 'primary';
                                $data_section = $stats_geo['par_region'];
                                break;
                            case 'arrondissement':
                                $titre_section = 'Répartition par arrondissement';
                                $icone_section = 'fas fa-map';
                                $couleur_section = 'success';
                                $data_section = $stats_geo['par_arrondissement'];
                                break;
                            case 'ville':
                                $titre_section = 'Répartition par ville';
                                $icone_section = 'fas fa-city';
                                $couleur_section = 'warning';
                                $data_section = $stats_geo['par_ville'];
                                break;
                        }
                        ?>

                        <h6 class="text-<?php echo $couleur_section; ?> mb-4">
                            <i class="<?php echo $icone_section; ?>"></i> <?php echo $titre_section; ?>
                            <?php if (!empty($type_filtre)): ?>
                                <small class="text-muted">(<?php echo ucfirst(str_replace('_', ' ', $type_filtre)); ?>)</small>
                            <?php endif; ?>
                        </h6>

                        <?php if (!empty($data_section)): ?>
                            <?php $total = array_sum($data_section); ?>
                            <div class="row">
                                <?php $index = 0; ?>
                                <?php foreach ($data_section as $localisation => $count): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="flex-grow-1">
                                                <strong><?php echo htmlspecialchars($localisation); ?></strong>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?php echo $couleur_section; ?>"><?php echo $count; ?></span>
                                            </div>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-<?php echo $couleur_section; ?>"
                                                 style="width: <?php echo ($count / $total) * 100; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo round(($count / $total) * 100, 1); ?>% du total</small>
                                    </div>
                                    <?php
                                    $index++;
                                    if ($type_localisation === 'ville' && $index >= 12) break; // Limiter l'affichage des villes
                                    ?>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($type_localisation === 'ville' && count($data_section) > 12): ?>
                                <div class="alert alert-info mt-3">
                                    <small><i class="fas fa-info-circle"></i>
                                    Affichage limité aux 12 premières villes. Total : <?php echo count($data_section); ?> villes.</small>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Aucune donnée disponible pour cette localisation.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Section des dossiers payés pour le billeteur -->
<?php if ($_SESSION['user_role'] === 'billeteur'): ?>
<div class="row mt-4">
    <div class="col">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-money-check"></i> Dossiers payés
                </h5>
                <div class="d-flex gap-2">
                    <!-- Recherche rapide -->
                    <form method="GET" action="<?php echo url('modules/paiements/list.php'); ?>" class="d-flex gap-2">
                        <input type="text" class="form-control form-control-sm" name="search"
                               placeholder="Recherche par référence..." style="width: 200px;">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>

                    <!-- Export CSV/Excel -->
                    <a href="<?php echo url('modules/paiements/export.php?format=csv'); ?>"
                       class="btn btn-outline-success btn-sm">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>

                    <a href="<?php echo url('modules/paiements/export.php?format=excel'); ?>"
                       class="btn btn-outline-success btn-sm">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>

                    <a href="<?php echo url('modules/paiements/list.php'); ?>" class="btn btn-outline-primary btn-sm">
                        Voir tous <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <?php
            // Récupérer les 10 derniers paiements pour aperçu
            $sql = "SELECT d.id, d.numero, d.nom_demandeur, d.type_infrastructure, d.sous_type, d.statut,
                           p.montant, p.devise, p.mode_paiement, p.date_paiement, p.date_enregistrement,
                           CASE
                               WHEN d.type_infrastructure = 'station_service' THEN COALESCE(NULLIF(d.operateur_proprietaire, ''), d.nom_demandeur)
                               WHEN d.type_infrastructure = 'point_consommateur' THEN COALESCE(NULLIF(d.operateur_proprietaire, ''), d.nom_demandeur)
                               WHEN d.type_infrastructure = 'depot_gpl' THEN COALESCE(NULLIF(d.entreprise_installatrice, ''), d.nom_demandeur)
                               ELSE d.nom_demandeur
                           END as operateur
                    FROM dossiers d
                    JOIN paiements p ON d.id = p.dossier_id
                    WHERE d.statut IN ('paye', 'analyse_daj', 'inspecte', 'valide', 'decide')
                    ORDER BY p.date_enregistrement DESC
                    LIMIT 10";

            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $dossiers_payes = $stmt->fetchAll();
            ?>

            <?php if (empty($dossiers_payes)): ?>
            <div class="card-body text-center py-5">
                <i class="fas fa-money-check fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">Aucun paiement enregistré pour le moment</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Réf. Dossier</th>
                            <th>Opérateur</th>
                            <th>Motif paiement</th>
                            <th>Montant payé</th>
                            <th>Statut</th>
                            <th>Export PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dossiers_payes as $dossier): ?>
                        <tr>
                            <td>
                                <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier['id']); ?>"
                                   class="text-decoration-none">
                                    <code class="text-primary"><?php echo sanitize($dossier['numero']); ?></code>
                                </a>
                            </td>
                            <td>
                                <strong><?php echo sanitize($dossier['operateur']); ?></strong>
                            </td>
                            <td>
                                <small><?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?></small>
                            </td>
                            <td>
                                <strong class="text-success">
                                    <?php echo number_format($dossier['montant'], 0, ',', ' '); ?>
                                    <?php echo sanitize($dossier['devise']); ?>
                                </strong>
                                <br>
                                <small class="text-muted">
                                    <?php echo ucfirst($dossier['mode_paiement']); ?> -
                                    <?php echo formatDate($dossier['date_paiement']); ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                                    <?php echo getStatutLabel($dossier['statut']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo url('modules/paiements/export_pdf.php?dossier_id=' . $dossier['id']); ?>"
                                   class="btn btn-outline-danger btn-sm" target="_blank">
                                    <i class="fas fa-file-pdf"></i> PDF
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
<?php endif; ?>

<!-- Opérateurs les plus actifs (pour admin seulement) -->
<?php if ($_SESSION['user_role'] === 'admin' && !empty($operateurs_actifs)): ?>
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-trophy"></i> Top 5 opérateurs les plus actifs
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($operateurs_actifs as $index => $operateur): ?>
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                        <span class="badge bg-<?php echo $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : ($index === 2 ? 'dark' : 'light')); ?> rounded-pill">
                            #<?php echo $index + 1; ?>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <strong><?php echo sanitize($operateur['operateur']); ?></strong>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary"><?php echo $operateur['nb_dossiers']; ?> dossiers</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Top 5 motifs de rejet -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-exclamation-triangle"></i> Top 5 motifs de rejet/irrégularité
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($motifs_rejet)): ?>
                    <?php foreach ($motifs_rejet as $motif): ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-grow-1">
                            <strong><?php echo sanitize($motif['motif_court']); ?></strong>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-danger"><?php echo $motif['occurrences']; ?></span>
                        </div>
                    </div>
                    <div class="progress mb-3" style="height: 6px;">
                        <div class="progress-bar bg-danger" style="width: <?php echo $motif['occurrences'] > 0 ? 100 : 10; ?>%"></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <p class="mb-0">Aucun rejet ou irrégularité récente</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Évolution mensuelle des paiements (pour admin seulement) -->
<?php if ($_SESSION['user_role'] === 'admin'): ?>
<div class="row mt-4">
    <div class="col">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line"></i> Évolution mensuelle des paiements (12 derniers mois)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($evolution_paiements)): ?>
                <canvas id="chartEvolutionPaiements" height="100"></canvas>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>
                const ctx = document.getElementById('chartEvolutionPaiements').getContext('2d');

                // Données PHP vers JavaScript
                const dataEvolution = <?php echo json_encode($evolution_paiements); ?>;

                // Préparer les données pour Chart.js
                const labels = dataEvolution.map(item => {
                    const [year, month] = item.mois.split('-');
                    const date = new Date(year, month - 1);
                    return date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
                });

                const nombrePaiements = dataEvolution.map(item => parseInt(item.nombre_paiements));
                const montantTotal = dataEvolution.map(item => parseFloat(item.montant_total));

                const chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Nombre de paiements',
                            data: nombrePaiements,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            tension: 0.1,
                            yAxisID: 'y'
                        }, {
                            label: 'Montant total (FCFA)',
                            data: montantTotal,
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            tension: 0.1,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            x: {
                                display: true,
                                title: {
                                    display: true,
                                    text: 'Mois'
                                }
                            },
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Nombre de paiements'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Montant (FCFA)'
                                },
                                grid: {
                                    drawOnChartArea: false,
                                }
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'Évolution des paiements'
                            },
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        }
                    }
                });
                </script>
                <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-chart-line fa-3x mb-3"></i>
                    <p class="mb-0">Aucune donnée de paiement disponible pour générer le graphique</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
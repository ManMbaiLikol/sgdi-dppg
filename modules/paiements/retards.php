<?php
// Dossiers en retard de paiement - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

// Vérifier les permissions
if (!in_array($_SESSION['user_role'], ['chef_service', 'billeteur', 'admin'])) {
    redirect(url('dashboard.php'), 'Vous n\'avez pas les permissions pour consulter les retards de paiement', 'error');
}

$page_title = 'Dossiers en retard de paiement';

// Récupérer les dossiers en retard
$dossiers_retard = getDossiersEnRetardPaiement();

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-exclamation-triangle text-warning"></i> <?php echo $page_title; ?>
            </h1>
            <p class="text-muted">
                Dossiers en attente de paiement depuis plus de 30 jours
            </p>
        </div>
        <div>
            <a href="<?php echo url('modules/paiements/list.php'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Retour aux paiements
            </a>
        </div>
    </div>

    <!-- Alerte d'information -->
    <div class="alert alert-warning mb-4">
        <h5><i class="fas fa-info-circle"></i> Information importante</h5>
        <p class="mb-0">
            Ces dossiers sont en attente de paiement depuis plus de 30 jours.
            Il est recommandé de contacter les demandeurs pour régulariser leur situation.
        </p>
    </div>

    <!-- Liste des dossiers en retard -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list"></i> Dossiers en retard
                <small class="text-muted">(<?php echo count($dossiers_retard); ?> dossier<?php echo count($dossiers_retard) > 1 ? 's' : ''; ?>)</small>
            </h5>
        </div>

        <?php if (empty($dossiers_retard)): ?>
        <div class="card-body text-center py-5">
            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
            <h4 class="text-success">Aucun retard de paiement</h4>
            <p class="text-muted mb-0">Tous les dossiers sont à jour concernant les paiements.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>N° Dossier</th>
                        <th>Type</th>
                        <th>Demandeur</th>
                        <th>Contact</th>
                        <th>Date création</th>
                        <th>Jours de retard</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dossiers_retard as $dossier): ?>
                    <tr>
                        <td>
                            <code class="text-primary"><?php echo sanitize($dossier['numero']); ?></code>
                        </td>
                        <td>
                            <small><?php echo sanitize($dossier['type_infrastructure']); ?></small>
                            <br><small class="text-muted"><?php echo sanitize($dossier['sous_type']); ?></small>
                        </td>
                        <td>
                            <strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong>
                            <br><small class="text-muted"><?php echo sanitize($dossier['region'] . ' - ' . $dossier['ville']); ?></small>
                        </td>
                        <td>
                            <?php if (!empty($dossier['telephone_demandeur'])): ?>
                                <i class="fas fa-phone text-info"></i> <?php echo sanitize($dossier['telephone_demandeur']); ?>
                                <br>
                            <?php endif; ?>
                            <?php if (!empty($dossier['email_demandeur'])): ?>
                                <i class="fas fa-envelope text-info"></i> <?php echo sanitize($dossier['email_demandeur']); ?>
                            <?php endif; ?>
                            <?php if (empty($dossier['telephone_demandeur']) && empty($dossier['email_demandeur'])): ?>
                                <span class="text-muted">Non renseigné</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo formatDateTime($dossier['date_creation'], 'd/m/Y'); ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php
                                $jours = $dossier['jours_attente'];
                                if ($jours > 60) echo 'danger';
                                elseif ($jours > 45) echo 'warning';
                                else echo 'info';
                            ?>">
                                <?php echo $jours; ?> jour<?php echo $jours > 1 ? 's' : ''; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier['id']); ?>"
                                   class="btn btn-outline-primary btn-sm"
                                   title="Voir détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($_SESSION['user_role'] === 'billeteur'): ?>
                                <a href="<?php echo url('modules/dossiers/paiement.php?id=' . $dossier['id']); ?>"
                                   class="btn btn-outline-success btn-sm"
                                   title="Enregistrer paiement">
                                    <i class="fas fa-money-bill"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($dossier['telephone_demandeur'])): ?>
                                <a href="tel:<?php echo $dossier['telephone_demandeur']; ?>"
                                   class="btn btn-outline-info btn-sm"
                                   title="Appeler">
                                    <i class="fas fa-phone"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($dossier['email_demandeur'])): ?>
                                <a href="mailto:<?php echo $dossier['email_demandeur']; ?>?subject=Rappel paiement dossier <?php echo $dossier['numero']; ?>"
                                   class="btn btn-outline-warning btn-sm"
                                   title="Envoyer email">
                                    <i class="fas fa-envelope"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Statistiques des retards -->
    <?php if (!empty($dossiers_retard)): ?>
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-clock fa-2x text-info mb-2"></i>
                    <h4 class="text-info">
                        <?php
                        $retards_moderes = array_filter($dossiers_retard, function($d) { return $d['jours_attente'] <= 45; });
                        echo count($retards_moderes);
                        ?>
                    </h4>
                    <p class="text-muted mb-0">Retards modérés<br><small>(30-45 jours)</small></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                    <h4 class="text-warning">
                        <?php
                        $retards_importants = array_filter($dossiers_retard, function($d) { return $d['jours_attente'] > 45 && $d['jours_attente'] <= 60; });
                        echo count($retards_importants);
                        ?>
                    </h4>
                    <p class="text-muted mb-0">Retards importants<br><small>(45-60 jours)</small></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-exclamation-circle fa-2x text-danger mb-2"></i>
                    <h4 class="text-danger">
                        <?php
                        $retards_critiques = array_filter($dossiers_retard, function($d) { return $d['jours_attente'] > 60; });
                        echo count($retards_critiques);
                        ?>
                    </h4>
                    <p class="text-muted mb-0">Retards critiques<br><small>(+60 jours)</small></p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
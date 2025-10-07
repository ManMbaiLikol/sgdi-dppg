<?php
// Détail d'un paiement - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

$paiement_id = intval($_GET['id'] ?? 0);
if (!$paiement_id) {
    redirect(url('modules/paiements/list.php'), 'Paiement non spécifié', 'error');
}

if (!peutVoirPaiements($_SESSION['user_role'])) {
    redirect(url('dashboard.php'), 'Vous n\'avez pas les permissions pour consulter les paiements', 'error');
}

$paiement = getPaiementById($paiement_id);
if (!$paiement) {
    redirect(url('modules/paiements/list.php'), 'Paiement introuvable', 'error');
}

// Si billeteur, vérifier qu'il peut voir ce paiement
if ($_SESSION['user_role'] === 'billeteur' && $paiement['billeteur_id'] != $_SESSION['user_id']) {
    redirect(url('modules/paiements/list.php'), 'Vous ne pouvez voir que vos propres paiements', 'error');
}

$page_title = 'Paiement #' . $paiement['id'];
$modes_paiement = getModesPaiement();

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            <p class="text-muted">Détails du paiement enregistré</p>
        </div>
        <div>
            <a href="<?php echo url('modules/paiements/list.php'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
            <a href="<?php echo url('modules/paiements/export_pdf.php?id=' . $paiement['id']); ?>"
               class="btn btn-success ms-2" target="_blank">
                <i class="fas fa-file-pdf"></i> Télécharger le reçu PDF
            </a>
            <a href="<?php echo url('modules/dossiers/view.php?id=' . $paiement['dossier_id']); ?>" class="btn btn-outline-info ms-2">
                <i class="fas fa-folder"></i> Voir le dossier
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Informations principales -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-money-check-alt"></i> Détails du paiement</h5>
                </div>
                <div class="card-body">
                    <!-- Dossier concerné -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-folder"></i> Dossier concerné
                            </h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Numéro :</strong></td>
                                    <td>
                                        <a href="<?php echo url('modules/dossiers/view.php?id=' . $paiement['dossier_id']); ?>" class="text-decoration-none">
                                            <?php echo sanitize($paiement['dossier_numero']); ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Demandeur :</strong></td>
                                    <td><?php echo sanitize($paiement['nom_demandeur']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Type :</strong></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo sanitize($paiement['type_infrastructure']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Nature :</strong></td>
                                    <td><?php echo sanitize($paiement['sous_type']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Localisation :</strong></td>
                                    <td><?php echo sanitize($paiement['region'] . ' - ' . $paiement['ville']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Statut dossier :</strong></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo sanitize($paiement['dossier_statut']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-info-circle"></i> Informations du paiement
                            </h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Date de paiement :</strong></td>
                                    <td><?php echo formatDateTime($paiement['date_paiement'], 'd/m/Y'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Date d'enregistrement :</strong></td>
                                    <td><?php echo formatDateTime($paiement['date_enregistrement'], 'd/m/Y à H:i'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Enregistré par :</strong></td>
                                    <td>
                                        <?php if ($paiement['billeteur_nom']): ?>
                                            <?php echo sanitize($paiement['billeteur_prenom'] . ' ' . $paiement['billeteur_nom']); ?>
                                            <br><small class="text-muted">Billeteur DPPG</small>
                                        <?php else: ?>
                                            <span class="text-muted">Non spécifié</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Observations -->
                    <?php if ($paiement['observations']): ?>
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-comment-alt"></i> Observations
                        </h6>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(sanitize($paiement['observations'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Détail financier -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calculator"></i> Détail financier</h5>
                </div>
                <div class="card-body text-center">
                    <div class="mb-4">
                        <h2 class="text-success mb-2">
                            <?php echo formatMontantPaiement($paiement['montant'], $paiement['devise']); ?>
                        </h2>
                        <p class="text-muted mb-0">Montant payé</p>
                    </div>

                    <div class="mb-4">
                        <span class="badge bg-<?php echo getModePaiementColor($paiement['mode_paiement']); ?> fs-6 px-3 py-2">
                            <i class="<?php echo getModePaiementIcon($paiement['mode_paiement']); ?> me-2"></i>
                            <?php echo $modes_paiement[$paiement['mode_paiement']]['label'] ?? ucfirst($paiement['mode_paiement']); ?>
                        </span>
                    </div>

                    <?php if ($paiement['reference_paiement']): ?>
                    <div class="mb-3">
                        <h6 class="text-muted">Référence</h6>
                        <code class="fs-6"><?php echo sanitize($paiement['reference_paiement']); ?></code>
                    </div>
                    <?php endif; ?>

                    <div class="text-muted">
                        <small>
                            <?php if ($paiement['devise'] && $paiement['devise'] !== 'XAF'): ?>
                                Devise: <?php echo sanitize($paiement['devise']); ?>
                            <?php else: ?>
                                Francs CFA
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Actions possibles -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-cogs"></i> Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?php echo url('modules/dossiers/view.php?id=' . $paiement['dossier_id']); ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-folder-open"></i> Consulter le dossier
                        </a>

                        <?php if (hasAnyRole(['admin', 'chef_service'])): ?>
                        <a href="<?php echo url('modules/paiements/recu.php?id=' . $paiement['id']); ?>" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-receipt"></i> Générer reçu
                        </a>
                        <?php endif; ?>

                        <a href="<?php echo url('modules/paiements/list.php'); ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-list"></i> Tous les paiements
                        </a>
                    </div>
                </div>
            </div>

            <!-- Informations système -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info"></i> Informations système</h6>
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        <div class="mb-2">
                            <strong>ID Paiement:</strong> <?php echo $paiement['id']; ?>
                        </div>
                        <div class="mb-2">
                            <strong>ID Dossier:</strong> <?php echo $paiement['dossier_id']; ?>
                        </div>
                        <?php if ($paiement['billeteur_id']): ?>
                        <div class="mb-2">
                            <strong>ID Billeteur:</strong> <?php echo $paiement['billeteur_id']; ?>
                        </div>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
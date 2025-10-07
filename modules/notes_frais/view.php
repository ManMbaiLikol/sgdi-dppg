<?php
// Visualisation d'une note de frais - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

$note_id = intval($_GET['id'] ?? 0);
if (!$note_id) {
    redirect(url('modules/notes_frais/list.php'), 'Note de frais non spécifiée', 'error');
}

$note = getNoteFreaisById($note_id);
if (!$note) {
    redirect(url('modules/notes_frais/list.php'), 'Note de frais introuvable', 'error');
}

$page_title = 'Note de frais #' . $note['id'];

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            <p class="text-muted">Détails de la note de frais</p>
        </div>
        <div>
            <a href="<?php echo url('modules/notes_frais/list.php'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
            <a href="<?php echo url('modules/notes_frais/export_pdf.php?id=' . $note['id']); ?>" class="btn btn-outline-success ms-2" target="_blank">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
            <?php if (peutModifierNoteFrais($note, $_SESSION['user_role'], $_SESSION['user_id'])): ?>
            <a href="<?php echo url('modules/notes_frais/edit.php?id=' . $note['id']); ?>" class="btn btn-outline-warning ms-2">
                <i class="fas fa-edit"></i> Modifier
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Informations principales -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Détails de la note de frais</h5>
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
                                    <td><?php echo sanitize($note['dossier_numero']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Demandeur :</strong></td>
                                    <td><?php echo sanitize($note['nom_demandeur']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Type :</strong></td>
                                    <td><?php echo sanitize($note['type_infrastructure']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Localisation :</strong></td>
                                    <td><?php echo sanitize($note['region'] . ' - ' . $note['ville']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-info-circle"></i> Informations de la note
                            </h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Créée par :</strong></td>
                                    <td><?php echo sanitize($note['createur_prenom'] . ' ' . $note['createur_nom']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Date de création :</strong></td>
                                    <td><?php echo formatDateTime($note['date_creation'], 'd/m/Y à H:i'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Dernière modification :</strong></td>
                                    <td><?php echo formatDateTime($note['date_modification'], 'd/m/Y à H:i'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Statut :</strong></td>
                                    <td>
                                        <?php
                                        $statut_colors = [
                                            'en_attente' => 'warning',
                                            'validee' => 'success',
                                            'payee' => 'info',
                                            'annulee' => 'danger'
                                        ];
                                        $statut_labels = [
                                            'en_attente' => 'En attente',
                                            'validee' => 'Validée',
                                            'payee' => 'Payée',
                                            'annulee' => 'Annulée'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $statut_colors[$note['statut']] ?? 'secondary'; ?>">
                                            <?php echo $statut_labels[$note['statut']] ?? $note['statut']; ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-align-left"></i> Description
                        </h6>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(sanitize($note['description'])); ?>
                        </div>
                    </div>

                    <!-- Actions selon le rôle -->
                    <?php if ($_SESSION['user_role'] === 'billeteur' && $note['statut'] === 'validee'): ?>
                    <div class="alert alert-info">
                        <h6><i class="fas fa-money-bill"></i> Action billeteur</h6>
                        <p class="mb-3">Cette note de frais est validée et peut être marquée comme payée.</p>
                        <form method="POST" action="<?php echo url('modules/notes_frais/edit.php?id=' . $note['id']); ?>" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="statut" value="payee">
                            <button type="submit" class="btn btn-success" onclick="return confirm('Confirmer le paiement de cette note de frais ?')">
                                <i class="fas fa-check"></i> Marquer comme payée
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Détail des montants -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calculator"></i> Détail des montants</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Montant de base :</strong></td>
                            <td class="text-end">
                                <?php echo number_format($note['montant_base'], 0, ',', ' '); ?> F
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Frais déplacement :</strong></td>
                            <td class="text-end">
                                <?php echo number_format($note['montant_frais_deplacement'], 0, ',', ' '); ?> F
                            </td>
                        </tr>
                        <tr class="table-primary">
                            <td><strong>TOTAL :</strong></td>
                            <td class="text-end">
                                <strong><?php echo number_format($note['montant_total'], 0, ',', ' '); ?> F</strong>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Statut et workflow -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-route"></i> Workflow</h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item <?php echo in_array($note['statut'], ['en_attente', 'validee', 'payee']) ? 'completed' : ''; ?>">
                            <div class="timeline-marker <?php echo $note['statut'] === 'en_attente' ? 'active' : ''; ?>"></div>
                            <div class="timeline-content">
                                <h6>En attente</h6>
                                <small>Note créée, en attente de validation</small>
                            </div>
                        </div>
                        <div class="timeline-item <?php echo in_array($note['statut'], ['validee', 'payee']) ? 'completed' : ''; ?>">
                            <div class="timeline-marker <?php echo $note['statut'] === 'validee' ? 'active' : ''; ?>"></div>
                            <div class="timeline-content">
                                <h6>Validée</h6>
                                <small>Approuvée, prête pour paiement</small>
                            </div>
                        </div>
                        <div class="timeline-item <?php echo $note['statut'] === 'payee' ? 'completed' : ''; ?>">
                            <div class="timeline-marker <?php echo $note['statut'] === 'payee' ? 'active' : ''; ?>"></div>
                            <div class="timeline-content">
                                <h6>Payée</h6>
                                <small>Paiement effectué</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #e9ecef;
    border: 2px solid #dee2e6;
}

.timeline-item.completed .timeline-marker {
    background: #28a745;
    border-color: #28a745;
}

.timeline-marker.active {
    background: #007bff;
    border-color: #007bff;
}

.timeline-content h6 {
    margin-bottom: 2px;
    font-size: 0.9rem;
}

.timeline-content small {
    color: #6c757d;
}
</style>

<?php require_once '../../includes/footer.php'; ?>
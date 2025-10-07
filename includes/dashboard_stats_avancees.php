<?php
/**
 * Composant: Sections statistiques avancées pour dashboards
 * Utilisé par: Chef Service, Sous-Directeur, Directeur, Ministre
 */

// Récupérer les statistiques
$stats_infra_operationnelles = getStatistiquesInfrastructuresOperationnelles();
$stats_infra_fermees = getStatistiquesInfrastructuresFermees();
$top_operateurs = getOperateursPlusActifs(5);
$top_motifs_rejet = getTop5MotifsRejet(5);
?>

<!-- Statistiques Avancées -->
<div class="row mb-4">
    <!-- Infrastructures Opérationnelles -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-industry"></i> Infrastructures Opérationnelles
                </h5>
            </div>
            <div class="card-body">
                <?php if ($stats_infra_operationnelles['total'] > 0): ?>
                    <div class="row text-center">
                        <div class="col-md-6 mb-3">
                            <div class="p-3 bg-success bg-opacity-10 rounded">
                                <h3 class="text-success mb-1"><?php echo number_format($stats_infra_operationnelles['operationnels'] ?? 0); ?></h3>
                                <small class="text-muted">Opérationnels</small>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="p-3 bg-warning bg-opacity-10 rounded">
                                <h3 class="text-warning mb-1"><?php echo number_format($stats_infra_operationnelles['fermes_temporaires'] ?? 0); ?></h3>
                                <small class="text-muted">Fermés Temporairement</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-danger bg-opacity-10 rounded">
                                <h3 class="text-danger mb-1"><?php echo number_format($stats_infra_operationnelles['fermes_definitifs'] ?? 0); ?></h3>
                                <small class="text-muted">Fermés Définitivement</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-secondary bg-opacity-10 rounded">
                                <h3 class="text-secondary mb-1"><?php echo number_format($stats_infra_operationnelles['demanteles'] ?? 0); ?></h3>
                                <small class="text-muted">Démantelés</small>
                            </div>
                        </div>
                    </div>

                    <!-- Taux opérationnel -->
                    <?php
                    $total = $stats_infra_operationnelles['total'];
                    $operationnels = $stats_infra_operationnelles['operationnels'] ?? 0;
                    $taux_operationnel = $total > 0 ? round(($operationnels / $total) * 100, 1) : 0;
                    ?>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Taux opérationnel</span>
                            <strong><?php echo $taux_operationnel; ?>%</strong>
                        </div>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar bg-success" role="progressbar"
                                 style="width: <?php echo $taux_operationnel; ?>%"
                                 aria-valuenow="<?php echo $taux_operationnel; ?>"
                                 aria-valuemin="0" aria-valuemax="100">
                                <?php echo $operationnels; ?> / <?php echo $total; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center mb-0">Aucune infrastructure autorisée</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Infrastructures Fermées/Démantelées -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-ban"></i> Infrastructures Fermées/Démantelées
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_infra_fermees)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Statut</th>
                                    <th class="text-end">Nombre</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_infra_fermees as $stat): ?>
                                    <tr>
                                        <td><?php echo getTypeInfrastructureLabel($stat['type_infrastructure']); ?></td>
                                        <td>
                                            <?php
                                            $badge_class = 'secondary';
                                            $label = ucfirst(str_replace('_', ' ', $stat['statut_operationnel']));
                                            if ($stat['statut_operationnel'] === 'ferme_temporaire') {
                                                $badge_class = 'warning';
                                                $label = 'Fermé Temp.';
                                            } elseif ($stat['statut_operationnel'] === 'ferme_definitif') {
                                                $badge_class = 'danger';
                                                $label = 'Fermé Déf.';
                                            } elseif ($stat['statut_operationnel'] === 'demantele') {
                                                $badge_class = 'dark';
                                                $label = 'Démantelé';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $label; ?></span>
                                        </td>
                                        <td class="text-end"><strong><?php echo $stat['count']; ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center mb-0">
                        <i class="fas fa-check-circle text-success"></i>
                        Aucune infrastructure fermée ou démantelée
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Top 5 Opérateurs -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-building"></i> Top 5 Opérateurs les Plus Actifs
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($top_operateurs)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_operateurs as $index => $op): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start px-0">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-primary rounded-circle me-2"><?php echo $index + 1; ?></span>
                                        <strong><?php echo sanitize($op['operateur']); ?></strong>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <span class="badge bg-success"><?php echo $op['nb_autorises']; ?> autorisés</span>
                                            <?php if ($op['nb_rejetes'] > 0): ?>
                                                <span class="badge bg-danger"><?php echo $op['nb_rejetes']; ?> rejetés</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <h4 class="mb-0 text-primary"><?php echo $op['nb_dossiers']; ?></h4>
                                    <small class="text-muted">dossiers</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center mb-0">Aucun opérateur trouvé</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top 5 Motifs de Rejet -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle"></i> Top 5 Motifs de Rejet/Irrégularité
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($top_motifs_rejet)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_motifs_rejet as $index => $motif): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <span class="badge bg-warning text-dark rounded-circle me-2"><?php echo $index + 1; ?></span>
                                        <span><?php echo sanitize($motif['motif_court']); ?></span>
                                    </div>
                                    <span class="badge bg-danger"><?php echo $motif['occurrences']; ?></span>
                                </div>
                                <?php if (isset($motif['motif']) && strlen($motif['motif']) > 100): ?>
                                    <small class="text-muted d-block mt-1 ms-4">
                                        <?php echo sanitize(substr($motif['motif'], 0, 150)) . '...'; ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center mb-0">
                        <i class="fas fa-smile text-success"></i>
                        Aucun rejet enregistré
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

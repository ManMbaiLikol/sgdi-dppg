<?php
// Page de visa final - Directeur DPPG
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('directeur');

$dossier_id = intval($_GET['id'] ?? 0);

// Récupérer le dossier
$sql = "SELECT d.*,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y à %H:%i') as date_creation_format,
        u.nom as createur_nom, u.prenom as createur_prenom
        FROM dossiers d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.id = ? AND d.statut = 'visa_sous_directeur'";

$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id]);
$dossier = $stmt->fetch();

if (!$dossier) {
    redirect(url('modules/directeur/dashboard.php'), 'Dossier non trouvé ou non disponible pour visa', 'error');
}

// Récupérer l'historique complet des visas
$sql_visas = "SELECT v.*, u.nom, u.prenom, u.role,
              DATE_FORMAT(v.date_visa, '%d/%m/%Y à %H:%i') as date_visa_format
              FROM visas v
              LEFT JOIN users u ON v.user_id = u.id
              WHERE v.dossier_id = ?
              ORDER BY v.date_visa ASC";

$stmt_visas = $pdo->prepare($sql_visas);
$stmt_visas->execute([$dossier_id]);
$visas = $stmt_visas->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $action = cleanInput($_POST['action'] ?? '');
        $observations = cleanInput($_POST['observations'] ?? '');

        if (empty($action)) {
            $error = 'Veuillez sélectionner une action';
        } else {
            try {
                $pdo->beginTransaction();

                // Enregistrer le visa
                $sql_insert = "INSERT INTO visas (dossier_id, user_id, role, action, observations)
                               VALUES (?, ?, 'directeur', ?, ?)";
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->execute([$dossier_id, $_SESSION['user_id'], $action, $observations]);

                // Mettre à jour le statut du dossier
                if ($action === 'approuve') {
                    $nouveau_statut = 'visa_directeur';
                    $description = 'Visa final accordé par le Directeur DPPG - Dossier prêt pour décision ministérielle';
                } elseif ($action === 'rejete') {
                    $nouveau_statut = 'rejete';
                    $description = 'Dossier rejeté par le Directeur DPPG';
                } else {
                    $nouveau_statut = 'validation_commission';
                    $description = 'Demande de modification par le Directeur DPPG';
                }

                $sql_update = "UPDATE dossiers SET statut = ? WHERE id = ?";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([$nouveau_statut, $dossier_id]);

                // Logger l'action
                logAction($pdo, $dossier_id, 'visa_directeur', $description, $_SESSION['user_id'], $dossier['statut'], $nouveau_statut);

                $pdo->commit();

                redirect(url('modules/directeur/dashboard.php'), 'Visa enregistré avec succès', 'success');

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Erreur lors de l\'enregistrement du visa: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Viser le dossier ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <a href="<?php echo url('modules/directeur/dashboard.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Informations du dossier -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-folder-open"></i>
                        Dossier <?php echo sanitize($dossier['numero']); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Important:</strong> Ce dossier a déjà reçu les visas du Chef de Service et du Sous-Directeur.
                        Votre visa est le dernier avant la transmission pour décision ministérielle.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Type:</strong><br>
                               <?php echo sanitize(getTypeInfrastructureLabel($dossier['type_infrastructure'])); ?> -
                               <?php echo sanitize(ucfirst($dossier['sous_type'])); ?>
                            </p>
                            <p><strong>Demandeur:</strong><br>
                               <?php echo sanitize($dossier['nom_demandeur']); ?>
                            </p>
                            <p><strong>Localisation:</strong><br>
                               <?php echo sanitize(($dossier['ville'] ?? 'N/A') . ', ' . ($dossier['region'] ?? 'N/A')); ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Créé par:</strong><br>
                               <?php echo sanitize($dossier['createur_prenom'] . ' ' . $dossier['createur_nom']); ?>
                            </p>
                            <p><strong>Date de création:</strong><br>
                               <?php echo $dossier['date_creation_format']; ?>
                            </p>
                            <p><strong>Statut actuel:</strong><br>
                               <span class="badge bg-primary">En attente visa Directeur (3/3)</span>
                            </p>
                        </div>
                    </div>

                    <hr>

                    <div class="text-center">
                        <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>"
                           class="btn btn-info btn-lg" target="_blank">
                            <i class="fas fa-eye"></i> Consulter le dossier complet
                        </a>
                    </div>
                </div>
            </div>

            <!-- Formulaire de visa -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-stamp"></i>
                        Apposer votre visa final
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo sanitize($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="mb-3">
                            <label class="form-label"><strong>Votre décision finale *</strong></label>
                            <div class="form-check mb-2 p-3 border rounded">
                                <input class="form-check-input" type="radio" name="action" value="approuve" id="approuve" required>
                                <label class="form-check-label" for="approuve">
                                    <i class="fas fa-check-circle text-success fa-lg"></i>
                                    <strong class="text-success">Approuver et transmettre au Ministre</strong><br>
                                    <small class="text-muted">Le dossier sera marqué comme validé et prêt pour la décision ministérielle finale</small>
                                </label>
                            </div>
                            <div class="form-check mb-2 p-3 border rounded">
                                <input class="form-check-input" type="radio" name="action" value="demande_modification" id="modification">
                                <label class="form-check-label" for="modification">
                                    <i class="fas fa-edit text-warning fa-lg"></i>
                                    <strong class="text-warning">Demander une modification</strong><br>
                                    <small class="text-muted">Le dossier retournera en arrière pour corrections</small>
                                </label>
                            </div>
                            <div class="form-check p-3 border rounded">
                                <input class="form-check-input" type="radio" name="action" value="rejete" id="rejete">
                                <label class="form-check-label" for="rejete">
                                    <i class="fas fa-times-circle text-danger fa-lg"></i>
                                    <strong class="text-danger">Rejeter définitivement</strong><br>
                                    <small class="text-muted">Le dossier sera définitivement rejeté et archivé</small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="observations" class="form-label"><strong>Observations</strong></label>
                            <textarea class="form-control" id="observations" name="observations" rows="5"
                                      placeholder="Vos observations sur ce dossier..."></textarea>
                            <small class="text-muted">Les observations sont optionnelles mais recommandées, surtout en cas de rejet ou de demande de modification.</small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-stamp"></i> Enregistrer mon visa final
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Circuit de visa -->
            <div class="card mb-3">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-route"></i>
                        Circuit de visa
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php
                        $circuit = [
                            'chef_commission' => 'Chef Commission',
                            'chef_service' => 'Chef Service',
                            'sous_directeur' => 'Sous-Directeur',
                            'directeur' => 'Directeur (vous)'
                        ];

                        foreach ($circuit as $role => $label):
                            $visa_role = array_filter($visas, function($v) use ($role) {
                                return $v['role'] === $role;
                            });
                            $visa = reset($visa_role);
                        ?>
                        <div class="mb-3">
                            <div class="d-flex align-items-center">
                                <?php if ($visa): ?>
                                    <i class="fas fa-check-circle text-success fa-lg me-2"></i>
                                    <div>
                                        <strong><?php echo $label; ?></strong><br>
                                        <small class="text-success">
                                            <?php echo ucfirst($visa['action']); ?> le <?php echo $visa['date_visa_format']; ?>
                                        </small>
                                    </div>
                                <?php elseif ($role === 'directeur'): ?>
                                    <i class="fas fa-hourglass-half text-warning fa-lg me-2"></i>
                                    <div>
                                        <strong><?php echo $label; ?></strong><br>
                                        <small class="text-warning">En attente</small>
                                    </div>
                                <?php else: ?>
                                    <i class="fas fa-circle text-muted fa-lg me-2"></i>
                                    <div>
                                        <strong class="text-muted"><?php echo $label; ?></strong><br>
                                        <small class="text-muted">Non effectué</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Historique détaillé des visas -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-history"></i>
                        Historique détaillé
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($visas)): ?>
                        <p class="text-muted">Aucun visa enregistré.</p>
                    <?php else: ?>
                        <?php foreach ($visas as $visa): ?>
                            <div class="border-bottom mb-3 pb-3">
                                <div class="d-flex">
                                    <div class="me-2">
                                        <?php if ($visa['action'] === 'approuve'): ?>
                                            <i class="fas fa-check-circle text-success fa-lg"></i>
                                        <?php elseif ($visa['action'] === 'rejete'): ?>
                                            <i class="fas fa-times-circle text-danger fa-lg"></i>
                                        <?php else: ?>
                                            <i class="fas fa-edit text-warning fa-lg"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?php echo sanitize(getRoleLabel($visa['role'])); ?></strong><br>
                                        <small><?php echo sanitize($visa['prenom'] . ' ' . $visa['nom']); ?></small><br>
                                        <small class="text-muted"><?php echo $visa['date_visa_format']; ?></small>
                                        <?php if ($visa['observations']): ?>
                                            <p class="mt-2 mb-0 small bg-light p-2 rounded">
                                                <em><?php echo sanitize($visa['observations']); ?></em>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

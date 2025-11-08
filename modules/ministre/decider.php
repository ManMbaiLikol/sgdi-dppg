<?php
// Page de décision ministérielle finale
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('cabinet');

$dossier_id = intval($_GET['id'] ?? 0);

// Récupérer le dossier
$sql = "SELECT d.*,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y à %H:%i') as date_creation_format,
        DATE_FORMAT(d.date_modification, '%d/%m/%Y') as date_validation_format,
        u.nom as createur_nom, u.prenom as createur_prenom
        FROM dossiers d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.id = ? AND d.statut = 'visa_directeur'";

$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id]);
$dossier = $stmt->fetch();

if (!$dossier) {
    redirect(url('modules/ministre/dashboard.php'), 'Dossier non trouvé ou non disponible pour décision', 'error');
}

// Récupérer tous les visas
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
        $decision = cleanInput($_POST['decision'] ?? '');
        $reference = cleanInput($_POST['reference'] ?? '');
        $observations = cleanInput($_POST['observations'] ?? '');

        if (empty($decision) || empty($reference)) {
            $error = 'La décision et la référence sont obligatoires';
        } else {
            try {
                $pdo->beginTransaction();

                // Enregistrer la décision
                $sql_decision = "INSERT INTO decisions (dossier_id, decision, reference_decision, observations, date_decision)
                                 VALUES (?, ?, ?, ?, NOW())";
                $stmt_decision = $pdo->prepare($sql_decision);
                $stmt_decision->execute([$dossier_id, $decision, $reference, $observations]);

                // Mettre à jour le statut du dossier
                if ($decision === 'approuve') {
                    $nouveau_statut = 'autorise';
                    $description = 'Dossier approuvé par décision ministérielle - Réf: ' . $reference;
                } else {
                    $nouveau_statut = 'rejete';
                    $description = 'Dossier refusé par décision ministérielle - Réf: ' . $reference;
                }

                $sql_update = "UPDATE dossiers SET statut = ? WHERE id = ?";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([$nouveau_statut, $dossier_id]);

                // Logger l'action
                logAction($pdo, $dossier_id, 'decision_ministerielle', $description, $_SESSION['user_id'], $dossier['statut'], $nouveau_statut);

                // Si approuvé, créer l'entrée pour le registre public
                if ($decision === 'approuve') {
                    // Vérifier si l'infrastructure est géolocalisée
                    $sql_check_geo = "SELECT id FROM infrastructures_geolocalisees WHERE dossier_id = ?";
                    $stmt_check = $pdo->prepare($sql_check_geo);
                    $stmt_check->execute([$dossier_id]);

                    if (!$stmt_check->fetch()) {
                        // Créer une entrée de base si pas encore géolocalisée
                        $sql_geo = "INSERT INTO infrastructures_geolocalisees
                                    (dossier_id, type_infrastructure, nom, localisation, statut, date_autorisation)
                                    VALUES (?, ?, ?, ?, 'autorise', NOW())";
                        $stmt_geo = $pdo->prepare($sql_geo);
                        $stmt_geo->execute([
                            $dossier_id,
                            $dossier['type_infrastructure'],
                            $dossier['nom_demandeur'],
                            $dossier['ville'] ?? 'Non spécifié'
                        ]);
                    }
                }

                $pdo->commit();

                redirect(url('modules/ministre/dashboard.php'), 'Décision enregistrée avec succès', 'success');

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Erreur lors de l\'enregistrement de la décision: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Décision ministérielle - ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <a href="<?php echo url('modules/ministre/dashboard.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Informations du dossier -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-folder-open"></i>
                        Dossier <?php echo sanitize($dossier['numero']); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <i class="fas fa-check-double"></i>
                        <strong>Dossier complet et validé</strong><br>
                        Ce dossier a reçu tous les visas requis et est prêt pour votre décision finale.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Type d'infrastructure:</strong><br>
                               <span class="badge bg-secondary p-2">
                               <?php echo sanitize(getTypeInfrastructureLabel($dossier['type_infrastructure'])); ?> -
                               <?php echo sanitize(ucfirst($dossier['sous_type'])); ?>
                               </span>
                            </p>
                            <p><strong>Demandeur:</strong><br>
                               <?php echo sanitize($dossier['nom_demandeur']); ?>
                            </p>
                            <?php if ($dossier['operateur_proprietaire']): ?>
                            <p><strong>Opérateur:</strong><br>
                               <?php echo sanitize($dossier['operateur_proprietaire']); ?>
                            </p>
                            <?php endif; ?>
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
                            <p><strong>Date de validation:</strong><br>
                               <?php echo $dossier['date_validation_format']; ?>
                            </p>
                            <p><strong>Statut actuel:</strong><br>
                               <span class="badge bg-success">Validé - En attente décision</span>
                            </p>
                        </div>
                    </div>

                    <hr>

                    <div class="text-center">
                        <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>"
                           class="btn btn-info btn-lg" target="_blank">
                            <i class="fas fa-eye"></i> Consulter le dossier complet et les documents
                        </a>
                    </div>
                </div>
            </div>

            <!-- Formulaire de décision -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-gavel"></i>
                        Décision ministérielle
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

                        <div class="mb-4">
                            <label class="form-label"><strong>Votre décision finale *</strong></label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check p-4 border border-success rounded">
                                        <input class="form-check-input" type="radio" name="decision" value="approuve" id="approuve" required>
                                        <label class="form-check-label w-100" for="approuve">
                                            <i class="fas fa-check-double text-success fa-2x d-block mb-2"></i>
                                            <strong class="text-success">APPROUVER</strong><br>
                                            <small class="text-muted">L'implantation est autorisée et sera publiée au registre public</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check p-4 border border-danger rounded">
                                        <input class="form-check-input" type="radio" name="decision" value="rejete" id="rejete">
                                        <label class="form-check-label w-100" for="rejete">
                                            <i class="fas fa-ban text-danger fa-2x d-block mb-2"></i>
                                            <strong class="text-danger">REFUSER</strong><br>
                                            <small class="text-muted">L'implantation est refusée définitivement</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reference" class="form-label"><strong>Référence de la décision * </strong></label>
                            <input type="text" class="form-control" id="reference" name="reference"
                                   placeholder="Ex: N°123/MINEE/CAB/2024 du 05/10/2024"
                                   required>
                            <small class="text-muted">Numéro et date de l'arrêté ministériel</small>
                        </div>

                        <div class="mb-4">
                            <label for="observations" class="form-label"><strong>Observations</strong></label>
                            <textarea class="form-control" id="observations" name="observations" rows="4"
                                      placeholder="Observations ou conditions particulières de la décision..."></textarea>
                            <small class="text-muted">Optionnel - Conditions particulières, réserves, etc.</small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-dark btn-lg">
                                <i class="fas fa-gavel"></i> Enregistrer la décision ministérielle
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Circuit complet -->
            <div class="card mb-3">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-route"></i>
                        Circuit complet du dossier
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php
                        $circuit_complet = [
                            'chef_commission' => ['label' => 'Chef Commission', 'icon' => 'clipboard-check'],
                            'chef_service' => ['label' => 'Chef Service', 'icon' => 'user-tie'],
                            'sous_directeur' => ['label' => 'Sous-Directeur', 'icon' => 'user-tie'],
                            'directeur' => ['label' => 'Directeur DPPG', 'icon' => 'user-shield']
                        ];

                        foreach ($circuit_complet as $role => $info):
                            $visa_role = array_filter($visas, function($v) use ($role) {
                                return $v['role'] === $role;
                            });
                            $visa = reset($visa_role);
                        ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-start">
                                <?php if ($visa): ?>
                                    <i class="fas fa-<?php echo $info['icon']; ?> text-success fa-lg me-2 mt-1"></i>
                                    <div class="flex-grow-1">
                                        <strong><?php echo $info['label']; ?></strong>
                                        <span class="badge bg-success ms-2">Visé</span><br>
                                        <small class="text-muted"><?php echo $visa['date_visa_format']; ?></small>
                                        <?php if ($visa['observations']): ?>
                                            <p class="mt-1 mb-0 small text-muted">
                                                <em><?php echo sanitize($visa['observations']); ?></em>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <i class="fas fa-<?php echo $info['icon']; ?> text-muted fa-lg me-2 mt-1"></i>
                                    <div class="flex-grow-1">
                                        <strong class="text-muted"><?php echo $info['label']; ?></strong><br>
                                        <small class="text-muted">Non effectué</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="mt-3 p-3 bg-warning bg-opacity-10 rounded">
                            <i class="fas fa-gavel text-warning fa-lg me-2"></i>
                            <strong>Décision Ministre</strong>
                            <span class="badge bg-warning ms-2">En cours</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aide à la décision -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle"></i>
                        Aide à la décision
                    </h5>
                </div>
                <div class="card-body">
                    <p class="small"><strong>Points de vérification:</strong></p>
                    <ul class="small">
                        <li>✓ Tous les visas accordés</li>
                        <li>✓ Documents complets</li>
                        <li>✓ Inspection réalisée</li>
                        <li>✓ Analyse juridique favorable</li>
                        <li>✓ Paiement effectué</li>
                    </ul>

                    <hr>

                    <p class="small mb-0">
                        <i class="fas fa-lightbulb text-warning"></i>
                        <strong>Note:</strong> En cas d'approbation, le dossier sera automatiquement publié
                        dans le registre public des infrastructures pétrolières.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

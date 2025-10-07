<?php
// Page de validation et décision directeur - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

requireRole('directeur');

$dossier_id = intval($_GET['id'] ?? 0);
if (!$dossier_id) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

$dossier = getDossierById($dossier_id);
if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier introuvable', 'error');
}

// Vérifier que le dossier est au bon statut
if (!in_array($dossier['statut'], ['inspecte', 'valide'])) {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
            'Ce dossier n\'est pas au stade de validation/décision', 'error');
}

// Récupérer l'inspection existante
$sql = "SELECT i.*, u.nom as inspecteur_nom, u.prenom as inspecteur_prenom
        FROM inspections i
        JOIN users u ON i.cadre_dppg_id = u.id
        WHERE i.dossier_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id]);
$inspection = $stmt->fetch();

// Récupérer les documents d'inspection
$sql = "SELECT * FROM documents
        WHERE dossier_id = ?
        AND type_document IN ('note_inspection', 'lettre_inspection', 'rapport_inspection', 'decision_motivee')
        ORDER BY type_document";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id]);
$documents_inspection = $stmt->fetchAll();

$errors = [];
$success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $action = sanitize($_POST['action'] ?? '');

        if ($action === 'valider_rapport') {
            // Validation du rapport d'inspection
            $decision_validation = sanitize($_POST['decision_validation'] ?? '');
            $observations_directeur = sanitize($_POST['observations_directeur'] ?? '');

            if (empty($decision_validation)) {
                $errors[] = 'Décision de validation requise';
            }

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();

                    // Mettre à jour l'inspection avec la validation du directeur
                    $sql = "UPDATE inspections SET
                           valide_par_directeur = ?, directeur_id = ?, date_validation = NOW()
                           WHERE dossier_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $validation_value = ($decision_validation === 'valide') ? 1 : 0;
                    $result = $stmt->execute([$validation_value, $_SESSION['user_id'], $dossier_id]);

                    if ($result) {
                        if ($decision_validation === 'valide') {
                            // Changer le statut vers "valide"
                            changerStatutDossier($dossier_id, 'valide', $_SESSION['user_id'],
                                              'Rapport d\'inspection validé par le directeur' .
                                              ($observations_directeur ? ': ' . $observations_directeur : ''), false);
                            $message = 'Rapport d\'inspection validé avec succès. Le dossier peut maintenant faire l\'objet d\'une décision finale.';
                        } else {
                            // Renvoyer vers inspection pour correction
                            changerStatutDossier($dossier_id, 'paye', $_SESSION['user_id'],
                                              'Rapport d\'inspection rejeté par le directeur. Nouvelle inspection requise.' .
                                              ($observations_directeur ? ' Motif: ' . $observations_directeur : ''), false);
                            $message = 'Rapport d\'inspection rejeté. Le dossier est renvoyé pour une nouvelle inspection.';
                        }

                        $pdo->commit();
                        redirect(url('modules/dossiers/view.php?id=' . $dossier_id), $message, 'success');
                    } else {
                        throw new Exception('Erreur lors de la validation du rapport');
                    }

                } catch (Exception $e) {
                    $pdo->rollback();
                    $errors[] = 'Erreur: ' . $e->getMessage();
                }
            }

        } elseif ($action === 'decision_finale') {
            // Décision finale sur le dossier
            $decision_finale = sanitize($_POST['decision_finale'] ?? '');
            $motif_decision = sanitize($_POST['motif_decision'] ?? '');

            if (empty($decision_finale)) {
                $errors[] = 'Décision finale requise';
            }

            if ($decision_finale === 'refuse' && empty($motif_decision)) {
                $errors[] = 'Motif de refus requis';
            }

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();

                    // Enregistrer la décision finale
                    $sql = "INSERT INTO decisions_finales (dossier_id, directeur_id, decision, motif, date_decision)
                           VALUES (?, ?, ?, ?, NOW())
                           ON DUPLICATE KEY UPDATE
                           directeur_id = VALUES(directeur_id),
                           decision = VALUES(decision),
                           motif = VALUES(motif),
                           date_decision = NOW()";
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute([$dossier_id, $_SESSION['user_id'], $decision_finale, $motif_decision]);

                    if ($result) {
                        // Changer le statut final
                        changerStatutDossier($dossier_id, 'decide', $_SESSION['user_id'],
                                          'Décision finale: ' . ucfirst($decision_finale) .
                                          ($motif_decision ? '. Motif: ' . $motif_decision : ''), false);

                        $pdo->commit();
                        $message = 'Décision finale enregistrée avec succès: ' . ucfirst($decision_finale);
                        redirect(url('modules/dossiers/view.php?id=' . $dossier_id), $message, 'success');
                    } else {
                        throw new Exception('Erreur lors de l\'enregistrement de la décision');
                    }

                } catch (Exception $e) {
                    $pdo->rollback();
                    $errors[] = 'Erreur: ' . $e->getMessage();
                }
            }
        }
    }
}

$page_title = 'Validation et Décision - Dossier ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-gavel"></i> Validation et Décision Directeur
                </h5>
                <p class="mb-0">
                    <strong><?php echo sanitize($dossier['numero']); ?></strong> -
                    <?php echo sanitize($dossier['nom_demandeur']); ?>
                </p>
            </div>

            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <ul class="mb-0">
                        <?php foreach ($success as $message): ?>
                        <li><?php echo sanitize($message); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Informations du dossier -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-primary">Informations du dossier</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Type:</strong></td>
                                <td><?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Localisation:</strong></td>
                                <td><?php echo sanitize($dossier['ville'] . ', ' . $dossier['region']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Statut actuel:</strong></td>
                                <td><span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                                    <?php echo getStatutLabel($dossier['statut']); ?>
                                </span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <?php if ($inspection): ?>
                        <h6 class="text-success">Résumé de l'inspection</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Date:</strong></td>
                                <td><?php echo formatDate($inspection['date_inspection']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Inspecteur:</strong></td>
                                <td><?php echo sanitize($inspection['inspecteur_prenom'] . ' ' . $inspection['inspecteur_nom']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Conformité:</strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $inspection['conforme'] === 'oui' ? 'success' : ($inspection['conforme'] === 'non' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($inspection['conforme']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Documents d'inspection -->
                <?php if (!empty($documents_inspection)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-files-o"></i> Documents d'inspection disponibles</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php
                            $types_labels = [
                                'note_inspection' => ['label' => 'Note d\'inspection', 'icon' => 'fas fa-sticky-note', 'color' => 'primary'],
                                'lettre_inspection' => ['label' => 'Lettre d\'inspection', 'icon' => 'fas fa-envelope', 'color' => 'info'],
                                'rapport_inspection' => ['label' => 'Rapport d\'inspection', 'icon' => 'fas fa-file-alt', 'color' => 'success'],
                                'decision_motivee' => ['label' => 'Décision motivée', 'icon' => 'fas fa-gavel', 'color' => 'warning']
                            ];

                            foreach ($types_labels as $type => $config):
                                $doc_trouve = null;
                                foreach ($documents_inspection as $doc) {
                                    if ($doc['type_document'] === $type) {
                                        $doc_trouve = $doc;
                                        break;
                                    }
                                }
                            ?>
                            <div class="col-md-6 col-lg-3 mb-2">
                                <?php if ($doc_trouve): ?>
                                <a href="<?php echo url('modules/documents/download.php?id=' . $doc_trouve['id']); ?>"
                                   class="btn btn-outline-<?php echo $config['color']; ?> w-100">
                                    <i class="<?php echo $config['icon']; ?>"></i><br>
                                    <small><?php echo $config['label']; ?></small>
                                </a>
                                <?php else: ?>
                                <div class="btn btn-light w-100 disabled">
                                    <i class="<?php echo $config['icon']; ?> text-muted"></i><br>
                                    <small class="text-muted"><?php echo $config['label']; ?><br>Non disponible</small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Validation du rapport (si statut = inspecte) -->
                <?php if ($dossier['statut'] === 'inspecte'): ?>
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-check-circle"></i> Validation du rapport d'inspection</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($inspection && $inspection['recommandations']): ?>
                        <div class="alert alert-info">
                            <h6>Recommandations de l'inspecteur :</h6>
                            <p class="mb-0"><?php echo nl2br(sanitize($inspection['recommandations'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="valider_rapport">

                            <div class="mb-3">
                                <label class="form-label">Décision de validation *</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="decision_validation" value="valide" id="valide" required>
                                    <label class="form-check-label text-success" for="valide">
                                        <i class="fas fa-check"></i> Valider le rapport d'inspection
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="decision_validation" value="rejete" id="rejete" required>
                                    <label class="form-check-label text-danger" for="rejete">
                                        <i class="fas fa-times"></i> Rejeter le rapport (nouvelle inspection requise)
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="observations_directeur" class="form-label">Observations du directeur</label>
                                <textarea class="form-control" id="observations_directeur" name="observations_directeur" rows="3"
                                          placeholder="Commentaires, instructions ou motifs de rejet"></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Retour
                                </a>
                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-check"></i> Valider ma décision
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Décision finale (si statut = valide) -->
                <?php if ($dossier['statut'] === 'valide'): ?>
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-gavel"></i> Décision finale sur le dossier</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success">
                            <h6><i class="fas fa-info-circle"></i> Rapport validé</h6>
                            <p class="mb-0">Le rapport d'inspection a été validé. Vous pouvez maintenant prendre la décision finale sur ce dossier.</p>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="decision_finale">

                            <div class="mb-3">
                                <label class="form-label">Décision finale *</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="decision_finale" value="approuve" id="approuve" required>
                                    <label class="form-check-label text-success" for="approuve">
                                        <i class="fas fa-thumbs-up"></i> Approuver le dossier
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="decision_finale" value="refuse" id="refuse" required>
                                    <label class="form-check-label text-danger" for="refuse">
                                        <i class="fas fa-thumbs-down"></i> Refuser le dossier
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="motif_decision" class="form-label">Motif de la décision</label>
                                <textarea class="form-control" id="motif_decision" name="motif_decision" rows="4"
                                          placeholder="Justification de votre décision (obligatoire en cas de refus)"></textarea>
                                <div class="form-text">Ce motif sera communiqué au demandeur et archivé dans le dossier.</div>
                            </div>

                            <div class="alert alert-warning">
                                <h6><i class="fas fa-exclamation-triangle"></i> Attention</h6>
                                <p class="mb-0">
                                    Cette décision est <strong>définitive</strong> et clôturera le dossier.
                                    Le demandeur sera automatiquement notifié de votre décision.
                                </p>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Retour
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-gavel"></i> Prendre la décision finale
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
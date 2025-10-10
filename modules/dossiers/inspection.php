<?php
// Page d'inspection pour cadre DPPG - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

requireRole('cadre_dppg');

$dossier_id = intval($_GET['id'] ?? 0);
if (!$dossier_id) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

$dossier = getDossierById($dossier_id);
if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier introuvable', 'error');
}

// Vérifier que le dossier est au bon statut (payé ou analyse_daj)
if (!in_array($dossier['statut'], ['paye', 'analyse_daj'])) {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
            'Ce dossier n\'est pas au stade d\'inspection', 'error');
}

// Vérifier s'il y a déjà une inspection
$sql = "SELECT * FROM inspections WHERE dossier_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id]);
$inspection_existante = $stmt->fetch();

// Récupérer les documents d'inspection existants
$sql = "SELECT * FROM documents
        WHERE dossier_id = ?
        AND type_document IN ('note_inspection', 'lettre_inspection', 'rapport_inspection', 'decision_motivee')
        ORDER BY type_document, date_upload DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id]);
$documents_inspection = $stmt->fetchAll();

$errors = [];
$success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $action = cleanInput($_POST['action'] ?? '');

        if ($action === 'upload_documents') {
            // Upload des documents
            $types_requis = ['note_inspection', 'lettre_inspection', 'rapport_inspection', 'decision_motivee'];
            $labels = [
                'note_inspection' => 'Note d\'inspection',
                'lettre_inspection' => 'Lettre d\'inspection',
                'rapport_inspection' => 'Rapport d\'inspection',
                'decision_motivee' => 'Décision motivée'
            ];

            $upload_success = 0;
            $upload_errors = [];

            foreach ($types_requis as $type) {
                if (isset($_FILES[$type]) && $_FILES[$type]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$type];

                    // Validation du fichier
                    $allowed_types = ['pdf', 'doc', 'docx'];
                    $max_size = 5 * 1024 * 1024; // 5MB

                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if (!in_array($extension, $allowed_types)) {
                        $upload_errors[] = "Format de fichier non autorisé pour {$labels[$type]}. Formats acceptés: PDF, DOC, DOCX";
                        continue;
                    }

                    if ($file['size'] > $max_size) {
                        $upload_errors[] = "Fichier trop volumineux pour {$labels[$type]}. Taille maximum: 5MB";
                        continue;
                    }

                    // Créer le répertoire de destination
                    $upload_dir = '../../assets/uploads/inspections/' . $dossier['numero'] . '/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    // Générer un nom de fichier unique
                    $nom_fichier = $type . '_' . $dossier['numero'] . '_' . date('YmdHis') . '.' . $extension;
                    $chemin_fichier = $upload_dir . $nom_fichier;

                    if (move_uploaded_file($file['tmp_name'], $chemin_fichier)) {
                        // Enregistrer en base de données
                        $sql = "INSERT INTO documents (dossier_id, nom_fichier, nom_original, type_document,
                                       taille_fichier, extension, chemin_fichier, user_id)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([
                            $dossier_id,
                            $nom_fichier,
                            $file['name'],
                            $type,
                            $file['size'],
                            $extension,
                            'assets/uploads/inspections/' . $dossier['numero'] . '/' . $nom_fichier,
                            $_SESSION['user_id']
                        ]);

                        if ($result) {
                            $upload_success++;
                        } else {
                            $upload_errors[] = "Erreur lors de l'enregistrement de {$labels[$type]}";
                        }
                    } else {
                        $upload_errors[] = "Erreur lors de l'upload de {$labels[$type]}";
                    }
                }
            }

            if ($upload_success > 0) {
                $success[] = "$upload_success document(s) uploadé(s) avec succès";

                // Rafraîchir la liste des documents
                $stmt = $pdo->prepare("SELECT * FROM documents
                                     WHERE dossier_id = ?
                                     AND type_document IN ('note_inspection', 'lettre_inspection', 'rapport_inspection', 'decision_motivee')
                                     ORDER BY type_document, date_upload DESC");
                $stmt->execute([$dossier_id]);
                $documents_inspection = $stmt->fetchAll();
            }

            if (!empty($upload_errors)) {
                $errors = array_merge($errors, $upload_errors);
            }

        } elseif ($action === 'finaliser_inspection') {
            // Vérifier que tous les documents sont présents
            $types_requis = ['note_inspection', 'lettre_inspection', 'rapport_inspection', 'decision_motivee'];
            $documents_par_type = [];
            foreach ($documents_inspection as $doc) {
                $documents_par_type[$doc['type_document']] = true;
            }

            $documents_manquants = [];
            foreach ($types_requis as $type) {
                if (!isset($documents_par_type[$type])) {
                    $documents_manquants[] = $type;
                }
            }

            if (!empty($documents_manquants)) {
                $errors[] = "Tous les documents doivent être uploadés avant de finaliser l'inspection";
            } else {
                $date_inspection = cleanInput($_POST['date_inspection'] ?? '');
                $conforme = cleanInput($_POST['conforme'] ?? '');
                $recommandations = cleanInput($_POST['recommandations'] ?? '');
                $observations = cleanInput($_POST['observations'] ?? '');

                if (empty($date_inspection)) {
                    $errors[] = 'Date d\'inspection requise';
                }

                if (empty($conforme)) {
                    $errors[] = 'Conformité requise';
                }

                if (empty($errors)) {
                    try {
                        $pdo->beginTransaction();

                        if ($inspection_existante) {
                            // Mettre à jour l'inspection existante
                            $sql = "UPDATE inspections SET
                                   date_inspection = ?, conforme = ?, recommandations = ?, observations = ?
                                   WHERE dossier_id = ?";
                            $stmt = $pdo->prepare($sql);
                            $result = $stmt->execute([$date_inspection, $conforme, $recommandations, $observations, $dossier_id]);
                        } else {
                            // Créer une nouvelle inspection
                            $sql = "INSERT INTO inspections (dossier_id, cadre_dppg_id, date_inspection,
                                           rapport, conforme, recommandations, observations)
                                   VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $stmt = $pdo->prepare($sql);
                            $result = $stmt->execute([
                                $dossier_id,
                                $_SESSION['user_id'],
                                $date_inspection,
                                'Rapport d\'inspection soumis avec documents joints',
                                $conforme,
                                $recommandations,
                                $observations
                            ]);
                        }

                        if ($result) {
                            // Changer le statut du dossier vers "inspecte"
                            changerStatutDossier($dossier_id, 'inspecte', $_SESSION['user_id'],
                                              'Inspection terminée avec upload des documents requis', false);

                            $pdo->commit();
                            redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
                                    'Inspection finalisée avec succès. Le dossier est maintenant en attente de validation.', 'success');
                        } else {
                            throw new Exception('Erreur lors de l\'enregistrement de l\'inspection');
                        }

                    } catch (Exception $e) {
                        $pdo->rollback();
                        $errors[] = 'Erreur: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

$page_title = 'Inspection - Dossier ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">
                    <i class="fas fa-search"></i> Inspection du dossier
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
                                <td><strong>Statut:</strong></td>
                                <td><span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                                    <?php echo getStatutLabel($dossier['statut']); ?>
                                </span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <?php if ($inspection_existante): ?>
                        <h6 class="text-success">Inspection existante</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Date:</strong></td>
                                <td><?php echo formatDate($inspection_existante['date_inspection']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Conformité:</strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $inspection_existante['conforme'] === 'oui' ? 'success' : ($inspection_existante['conforme'] === 'non' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($inspection_existante['conforme']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section Upload des documents -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-upload"></i> Documents d'inspection requis</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="upload_documents">

                            <div class="row">
                                <?php
                                $types_documents = [
                                    'note_inspection' => ['label' => 'Note d\'inspection', 'icon' => 'fas fa-sticky-note', 'color' => 'primary'],
                                    'lettre_inspection' => ['label' => 'Lettre d\'inspection', 'icon' => 'fas fa-envelope', 'color' => 'info'],
                                    'rapport_inspection' => ['label' => 'Rapport d\'inspection', 'icon' => 'fas fa-file-alt', 'color' => 'success'],
                                    'decision_motivee' => ['label' => 'Décision motivée', 'icon' => 'fas fa-gavel', 'color' => 'warning']
                                ];

                                foreach ($types_documents as $type => $config):
                                    // Vérifier si un document de ce type existe déjà
                                    $document_existant = null;
                                    foreach ($documents_inspection as $doc) {
                                        if ($doc['type_document'] === $type) {
                                            $document_existant = $doc;
                                            break;
                                        }
                                    }
                                ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-header bg-<?php echo $config['color']; ?> text-white py-2">
                                            <small><i class="<?php echo $config['icon']; ?>"></i> <?php echo $config['label']; ?></small>
                                        </div>
                                        <div class="card-body py-2">
                                            <?php if ($document_existant): ?>
                                            <div class="alert alert-success py-2 mb-2">
                                                <small>
                                                    <i class="fas fa-check"></i> Fichier:
                                                    <strong><?php echo sanitize($document_existant['nom_original']); ?></strong><br>
                                                    Uploadé le: <?php echo formatDateTime($document_existant['date_upload']); ?>
                                                </small>
                                            </div>
                                            <?php endif; ?>

                                            <input type="file" class="form-control form-control-sm"
                                                   name="<?php echo $type; ?>" accept=".pdf,.doc,.docx">
                                            <small class="form-text text-muted">
                                                Formats: PDF, DOC, DOCX (max 5MB)
                                                <?php if ($document_existant): ?>
                                                <br><em>Remplacera le fichier existant</em>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Uploader les documents sélectionnés
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Section Finalisation de l'inspection -->
                <?php
                $tous_documents_presents = true;
                $types_requis = ['note_inspection', 'lettre_inspection', 'rapport_inspection', 'decision_motivee'];
                foreach ($types_requis as $type) {
                    $present = false;
                    foreach ($documents_inspection as $doc) {
                        if ($doc['type_document'] === $type) {
                            $present = true;
                            break;
                        }
                    }
                    if (!$present) {
                        $tous_documents_presents = false;
                        break;
                    }
                }
                ?>

                <?php if ($tous_documents_presents): ?>
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-check-circle"></i> Finalisation de l'inspection</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="finaliser_inspection">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date_inspection" class="form-label">Date d'inspection *</label>
                                        <input type="date" class="form-control" id="date_inspection" name="date_inspection"
                                               value="<?php echo $inspection_existante['date_inspection'] ?? date('Y-m-d'); ?>"
                                               max="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="conforme" class="form-label">Conformité *</label>
                                        <select class="form-select" id="conforme" name="conforme" required>
                                            <option value="">Sélectionner</option>
                                            <option value="oui" <?php echo ($inspection_existante['conforme'] ?? '') === 'oui' ? 'selected' : ''; ?>>
                                                Conforme
                                            </option>
                                            <option value="sous_reserve" <?php echo ($inspection_existante['conforme'] ?? '') === 'sous_reserve' ? 'selected' : ''; ?>>
                                                Conforme sous réserve
                                            </option>
                                            <option value="non" <?php echo ($inspection_existante['conforme'] ?? '') === 'non' ? 'selected' : ''; ?>>
                                                Non conforme
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="recommandations" class="form-label">Recommandations</label>
                                <textarea class="form-control" id="recommandations" name="recommandations" rows="3"
                                          placeholder="Recommandations suite à l'inspection"><?php echo sanitize($inspection_existante['recommandations'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="observations" class="form-label">Observations</label>
                                <textarea class="form-control" id="observations" name="observations" rows="3"
                                          placeholder="Observations particulières"><?php echo sanitize($inspection_existante['observations'] ?? ''); ?></textarea>
                            </div>

                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> Après finalisation</h6>
                                <p class="mb-0">
                                    Le dossier passera au statut <strong>"Inspecté"</strong> et sera transmis
                                    pour validation par le directeur.
                                </p>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Retour
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check"></i> Finaliser l'inspection
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle"></i> Documents manquants</h6>
                    <p class="mb-0">
                        Tous les documents d'inspection doivent être uploadés avant de pouvoir finaliser l'inspection.
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
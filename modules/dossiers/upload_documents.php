<?php
// Upload de documents pour un dossier - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

// Vérifier les permissions (seulement chef de service)
if ($_SESSION['user_role'] !== 'chef_service') {
    redirect(url('dashboard.php'), 'Accès non autorisé - Seul le chef de service peut uploader les documents', 'error');
}

$dossier_id = $_GET['id'] ?? $_POST['dossier_id'] ?? null;

if (!$dossier_id || !is_numeric($dossier_id)) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

// Récupérer le dossier
$dossier = getDossierById($dossier_id);

if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non trouvé', 'error');
}

// Vérifier que le dossier peut recevoir des documents (brouillon ou en cours)
if (!in_array($dossier['statut'], ['brouillon', 'en_cours'])) {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
             'Les documents ne peuvent être uploadés que pour les dossiers en statut "brouillon" ou "en cours"', 'error');
}

$errors = [];
$success_messages = [];

// Traitement de l'upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF invalide';
    } else {
        $type_document = sanitize($_POST['type_document'] ?? '');

        if (empty($type_document)) {
            $errors[] = 'Le type de document est requis';
        }

        if (empty($_FILES['fichier']['name'])) {
            $errors[] = 'Aucun fichier sélectionné';
        } else {
            // Valider le fichier
            $file_errors = validateFile($_FILES['fichier'], ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'], 10485760); // 10MB max

            if (!empty($file_errors)) {
                $errors = array_merge($errors, $file_errors);
            }
        }

        if (empty($errors)) {
            // Créer le répertoire s'il n'existe pas
            $upload_dir = '../../uploads/documents/' . $dossier_id . '/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Générer un nom de fichier unique
            $extension = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
            $nom_fichier = uniqid() . '_' . time() . '.' . $extension;
            $chemin_complet = $upload_dir . $nom_fichier;

            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $chemin_complet)) {
                // Enregistrer en base de données
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO documents (dossier_id, nom_fichier, nom_original, type_document,
                                             taille_fichier, extension, chemin_fichier, user_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $result = $stmt->execute([
                        $dossier_id,
                        $nom_fichier,
                        $_FILES['fichier']['name'],
                        $type_document,
                        $_FILES['fichier']['size'],
                        $extension,
                        $chemin_complet,
                        $_SESSION['user_id']
                    ]);

                    if ($result) {
                        $success_messages[] = 'Document uploadé avec succès';

                        // Logger l'action
                        addHistoriqueDossier($dossier_id, $_SESSION['user_id'], 'upload_document',
                                           "Upload du document: " . $_FILES['fichier']['name'] . " (type: $type_document)");
                    } else {
                        $errors[] = 'Erreur lors de l\'enregistrement en base de données';
                    }
                } catch (Exception $e) {
                    $errors[] = 'Erreur: ' . $e->getMessage();
                    // Supprimer le fichier en cas d'erreur
                    if (file_exists($chemin_complet)) {
                        unlink($chemin_complet);
                    }
                }
            } else {
                $errors[] = 'Erreur lors de l\'upload du fichier';
            }
        }
    }
}

// Récupérer les documents requis et uploadés
$documents_requis = getDocumentsRequis($dossier['type_infrastructure'], $dossier['sous_type']);
$documents_uploaded = getDocumentsUploadedByType($dossier_id);

$page_title = 'Upload documents - ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-cloud-upload-alt"></i>
                        Upload des documents
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?php echo url('dashboard.php'); ?>">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo url('modules/dossiers/list.php'); ?>">Dossiers</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>">Dossier <?php echo htmlspecialchars($dossier['numero']); ?></a></li>
                            <li class="breadcrumb-item active">Upload documents</li>
                        </ol>
                    </nav>
                </div>
                <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>

            <!-- Messages -->
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($success_messages)): ?>
            <div class="alert alert-success">
                <ul class="mb-0">
                    <?php foreach ($success_messages as $message): ?>
                    <li><?php echo htmlspecialchars($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Informations du dossier -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i>
                        Informations du dossier
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Numéro :</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['numero']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Type :</strong></td>
                                    <td><?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Demandeur :</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['nom_demandeur']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Statut :</strong></td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatutClass($dossier['statut']); ?>">
                                            <?php echo getStatutLabel($dossier['statut']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents requis -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list-check"></i>
                        Documents requis pour ce type d'infrastructure
                        <span class="badge bg-info"><?php echo count($documents_requis); ?> documents</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($documents_requis as $type => $info): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card border-<?php echo isset($documents_uploaded[$type]) ? 'success' : 'warning'; ?>">
                                <div class="card-header bg-<?php echo isset($documents_uploaded[$type]) ? 'success' : 'warning'; ?> text-white py-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <small class="fw-bold"><?php echo htmlspecialchars($info['label']); ?></small>
                                        <?php if (isset($documents_uploaded[$type])): ?>
                                        <i class="fas fa-check-circle"></i>
                                        <?php else: ?>
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body py-3">
                                    <p class="small text-muted mb-3"><?php echo htmlspecialchars($info['description']); ?></p>

                                    <?php if (isset($documents_uploaded[$type])): ?>
                                    <!-- Document déjà uploadé -->
                                    <div class="alert alert-success py-2 mb-3">
                                        <small>
                                            <i class="fas fa-file-alt"></i>
                                            <strong><?php echo htmlspecialchars($documents_uploaded[$type]['nom_original']); ?></strong><br>
                                            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($documents_uploaded[$type]['date_upload'])); ?><br>
                                            <i class="fas fa-weight"></i> <?php echo number_format($documents_uploaded[$type]['taille_fichier'] / 1024, 0); ?> KB
                                        </small>
                                    </div>
                                    <a href="<?php echo url('modules/documents/download.php?id=' . $documents_uploaded[$type]['id']); ?>"
                                       class="btn btn-sm btn-outline-success me-2">
                                        <i class="fas fa-download"></i> Télécharger
                                    </a>
                                    <?php endif; ?>

                                    <!-- Formulaire d'upload -->
                                    <form method="POST" enctype="multipart/form-data" class="<?php echo isset($documents_uploaded[$type]) ? 'mt-3 pt-3 border-top' : ''; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="dossier_id" value="<?php echo $dossier_id; ?>">
                                        <input type="hidden" name="type_document" value="<?php echo $type; ?>">

                                        <div class="mb-2">
                                            <input type="file" class="form-control form-control-sm" name="fichier"
                                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                                        </div>
                                        <button type="submit" name="upload_document" class="btn btn-sm btn-<?php echo isset($documents_uploaded[$type]) ? 'warning' : 'primary'; ?> w-100">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <?php echo isset($documents_uploaded[$type]) ? 'Remplacer' : 'Uploader'; ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
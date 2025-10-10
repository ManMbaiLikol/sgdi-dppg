<?php
// Upload de documents - SGDI MVP
require_once '../../includes/auth.php';

requireLogin();

$dossier_id = intval($_GET['dossier_id'] ?? 0);
if (!$dossier_id) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

// Vérifier que le dossier existe et que l'utilisateur peut y accéder
require_once '../dossiers/functions.php';
$dossier = getDossierById($dossier_id);

if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier introuvable', 'error');
}

// Vérifications d'autorisation selon le rôle
$peut_uploader = false;
switch ($_SESSION['user_role']) {
    case 'admin':
        $peut_uploader = true;
        break;
    case 'chef_service':
        $peut_uploader = ($dossier['user_id'] == $_SESSION['user_id']);
        break;
    case 'cadre_dppg':
        $peut_uploader = in_array($dossier['statut'], ['paye', 'inspecte']);
        break;
}

if (!$peut_uploader) {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id), 'Permission insuffisante pour uploader des documents', 'error');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $type_document = cleanInput($_POST['type_document'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');

        if (empty($type_document)) {
            $errors[] = 'Type de document requis';
        }

        if (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Aucun fichier sélectionné';
        } else {
            $file = $_FILES['fichier'];
            $file_errors = validateFile($file);

            if (!empty($file_errors)) {
                $errors = array_merge($errors, $file_errors);
            }
        }

        if (empty($errors)) {
            // Créer le répertoire d'upload s'il n'existe pas
            $upload_dir = __DIR__ . '/../../uploads/' . date('Y') . '/' . date('m') . '/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Générer un nom de fichier unique
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $nom_fichier = 'doc_' . $dossier_id . '_' . time() . '_' . uniqid() . '.' . $extension;
            $chemin_complet = $upload_dir . $nom_fichier;
            $chemin_relatif = '/uploads/' . date('Y') . '/' . date('m') . '/' . $nom_fichier;

            if (move_uploaded_file($file['tmp_name'], $chemin_complet)) {
                // Enregistrer en base de données
                $sql = "INSERT INTO documents (dossier_id, nom_fichier, nom_original, type_document,
                               taille_fichier, extension, chemin_fichier, user_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $dossier_id,
                    $nom_fichier,
                    $file['name'],
                    $type_document,
                    $file['size'],
                    $extension,
                    $chemin_relatif,
                    $_SESSION['user_id']
                ]);

                if ($result) {
                    // Logger l'action
                    logAction($pdo, $dossier_id, 'upload_document',
                             'Upload du document: ' . $file['name'] . ($description ? ' - ' . $description : ''),
                             $_SESSION['user_id']);

                    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
                            'Document uploadé avec succès', 'success');
                } else {
                    // Supprimer le fichier si l'insertion échoue
                    unlink($chemin_complet);
                    $errors[] = 'Erreur lors de l\'enregistrement du document';
                }
            } else {
                $errors[] = 'Erreur lors de l\'upload du fichier';
            }
        }
    }
}

$page_title = 'Upload document - Dossier ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-upload"></i> Upload de document
                </h5>
                <p class="mb-0">
                    Dossier: <strong><?php echo sanitize($dossier['numero']); ?></strong> -
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

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="mb-3">
                        <label for="type_document" class="form-label">Type de document *</label>
                        <select class="form-select" id="type_document" name="type_document" required>
                            <option value="">Sélectionnez le type</option>
                            <option value="piece_identite" <?php echo ($_POST['type_document'] ?? '') === 'piece_identite' ? 'selected' : ''; ?>>
                                Pièce d'identité
                            </option>
                            <option value="plan_implantation" <?php echo ($_POST['type_document'] ?? '') === 'plan_implantation' ? 'selected' : ''; ?>>
                                Plan d'implantation
                            </option>
                            <option value="autorisation_terrain" <?php echo ($_POST['type_document'] ?? '') === 'autorisation_terrain' ? 'selected' : ''; ?>>
                                Autorisation du terrain
                            </option>
                            <option value="etude_impact" <?php echo ($_POST['type_document'] ?? '') === 'etude_impact' ? 'selected' : ''; ?>>
                                Étude d'impact environnemental
                            </option>
                            <option value="autres" <?php echo ($_POST['type_document'] ?? '') === 'autres' ? 'selected' : ''; ?>>
                                Autres documents
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="fichier" class="form-label">Fichier *</label>
                        <input type="file" class="form-control" id="fichier" name="fichier"
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        <div class="form-text">
                            Formats acceptés: PDF, DOC, DOCX, JPG, PNG. Taille maximum: 5MB.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label">Description (optionnel)</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Description ou commentaire sur ce document"><?php echo sanitize($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour au dossier
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Uploader le document
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Aide -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-info-circle"></i> Types de documents requis
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Documents obligatoires:</h6>
                        <ul class="small">
                            <li><strong>Pièce d'identité</strong> - CNI ou passeport du demandeur</li>
                            <li><strong>Plan d'implantation</strong> - Plan détaillé du site</li>
                            <li><strong>Autorisation du terrain</strong> - Titre foncier ou bail</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Documents recommandés:</h6>
                        <ul class="small">
                            <li><strong>Étude d'impact</strong> - Étude environnementale si requise</li>
                            <li><strong>Autres documents</strong> - Tout document complémentaire</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Prévisualisation du fichier sélectionné
document.getElementById('fichier').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';

        // Afficher les informations du fichier
        let info = document.getElementById('file-info');
        if (!info) {
            info = document.createElement('div');
            info.id = 'file-info';
            info.className = 'mt-2 p-2 bg-light rounded';
            this.parentNode.appendChild(info);
        }

        info.innerHTML = `
            <small>
                <i class="fas fa-file"></i> <strong>${fileName}</strong><br>
                <i class="fas fa-weight"></i> Taille: ${fileSize}
            </small>
        `;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
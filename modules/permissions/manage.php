<?php
// Gestion des permissions d'un utilisateur - SGDI
require_once '../../includes/auth.php';
require_once '../users/functions.php';
require_once 'functions.php';

// Seuls les admins peuvent gérer les permissions
requireRole('admin');

$page_title = 'Gérer les Permissions';

// Vérifier que l'utilisateur existe
$user_id = intval($_GET['user_id'] ?? 0);
if (!$user_id) {
    redirect(url('modules/permissions/index.php'), 'Utilisateur non spécifié', 'error');
}

// Récupérer les informations de l'utilisateur
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    redirect(url('modules/permissions/index.php'), 'Utilisateur introuvable', 'error');
}

// Les admins ont toutes les permissions automatiquement
if ($user['role'] === 'admin') {
    redirect(url('modules/permissions/index.php'), 'Les administrateurs ont automatiquement toutes les permissions', 'info');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $permission_ids = $_POST['permissions'] ?? [];

        // Synchroniser les permissions
        if (syncUserPermissions($user_id, $permission_ids, $_SESSION['user_id'])) {
            setFlashMessage('Permissions mises à jour avec succès', 'success');
            redirect(url('modules/permissions/manage.php?user_id=' . $user_id));
        } else {
            $error = 'Erreur lors de la mise à jour des permissions';
        }
    }
}

// Récupérer toutes les permissions groupées par module
$all_permissions = getPermissionsByModule();

// Récupérer les permissions actuelles de l'utilisateur
$user_permission_ids = array_column(getUserPermissions($user_id), 'id');

// Récupérer les permissions recommandées pour ce rôle
$recommended_codes = getRecommendedPermissionsByRole($user['role']);

require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="<?php echo url('modules/permissions/index.php'); ?>">Permissions</a>
                </li>
                <li class="breadcrumb-item active">Gérer les permissions</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-4">
    <div class="col">
        <h2>
            <i class="fas fa-user-shield"></i>
            Gérer les permissions de <?php echo sanitize($user['prenom'] . ' ' . $user['nom']); ?>
        </h2>
        <p class="text-muted">
            <strong>Rôle :</strong> <?php echo getRoleLabel($user['role']); ?> |
            <strong>Username :</strong> <?php echo sanitize($user['username']); ?> |
            <strong>Email :</strong> <?php echo sanitize($user['email']); ?>
        </p>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Formulaire de permissions -->
    <div class="col-md-9">
        <form method="POST" id="permissionsForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-key"></i> Sélectionner les permissions
                    </h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-light" id="selectAll">
                            <i class="fas fa-check-square"></i> Tout sélectionner
                        </button>
                        <button type="button" class="btn btn-light" id="selectNone">
                            <i class="fas fa-square"></i> Tout désélectionner
                        </button>
                        <button type="button" class="btn btn-warning" id="selectRecommended">
                            <i class="fas fa-star"></i> Permissions recommandées
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php foreach ($all_permissions as $module => $permissions): ?>
                    <div class="mb-4">
                        <h5 class="border-bottom pb-2">
                            <i class="fas fa-folder"></i> Module: <strong><?php echo ucfirst($module); ?></strong>
                            <small class="text-muted">(<?php echo count($permissions); ?> permissions)</small>
                        </h5>

                        <div class="row">
                            <?php foreach ($permissions as $perm): ?>
                            <div class="col-md-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input permission-checkbox"
                                           type="checkbox"
                                           name="permissions[]"
                                           value="<?php echo $perm['id']; ?>"
                                           id="perm_<?php echo $perm['id']; ?>"
                                           data-code="<?php echo $perm['code']; ?>"
                                           <?php echo in_array($perm['id'], $user_permission_ids) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="perm_<?php echo $perm['id']; ?>">
                                        <strong><?php echo sanitize($perm['nom']); ?></strong>
                                        <?php if (in_array($perm['code'], $recommended_codes)): ?>
                                            <span class="badge bg-warning text-dark ms-1" title="Recommandé pour ce rôle">
                                                <i class="fas fa-star"></i>
                                            </span>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo sanitize($perm['description']); ?>
                                        </small>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les permissions
                    </button>
                    <a href="<?php echo url('modules/permissions/index.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Panneau latéral d'information -->
    <div class="col-md-3">
        <!-- Statistiques -->
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar"></i> Statistiques
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <strong>Permissions actuelles:</strong>
                    <span class="badge bg-primary float-end" id="currentCount">
                        <?php echo count($user_permission_ids); ?>
                    </span>
                </div>
                <div class="mb-2">
                    <strong>Permissions recommandées:</strong>
                    <span class="badge bg-warning text-dark float-end">
                        <?php echo count($recommended_codes); ?>
                    </span>
                </div>
                <div class="mb-2">
                    <strong>Total disponible:</strong>
                    <span class="badge bg-secondary float-end">
                        <?php echo array_sum(array_map('count', $all_permissions)); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Aide -->
        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle"></i> Aide
                </h6>
            </div>
            <div class="card-body">
                <p class="small mb-2">
                    <i class="fas fa-star text-warning"></i>
                    Les permissions marquées avec une étoile sont <strong>recommandées</strong> pour le rôle de cet utilisateur.
                </p>
                <p class="small mb-2">
                    <i class="fas fa-lightbulb text-info"></i>
                    Utilisez le bouton "Permissions recommandées" pour appliquer automatiquement les permissions standards.
                </p>
                <p class="small mb-0">
                    <i class="fas fa-shield-alt text-primary"></i>
                    Les administrateurs ont automatiquement toutes les permissions.
                </p>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">
                    <i class="fas fa-bolt"></i> Actions rapides
                </h6>
            </div>
            <div class="card-body">
                <button type="button" class="btn btn-sm btn-info w-100 mb-2" id="copyFromUser">
                    <i class="fas fa-copy"></i> Copier d'un autre utilisateur
                </button>
                <a href="<?php echo url('modules/users/edit.php?id=' . $user_id); ?>"
                   class="btn btn-sm btn-secondary w-100">
                    <i class="fas fa-user-edit"></i> Modifier l'utilisateur
                </a>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Compter les permissions sélectionnées
    function updateCount() {
        const count = $('.permission-checkbox:checked').length;
        $('#currentCount').text(count);
    }

    // Tout sélectionner
    $('#selectAll').click(function() {
        $('.permission-checkbox').prop('checked', true);
        updateCount();
    });

    // Tout désélectionner
    $('#selectNone').click(function() {
        $('.permission-checkbox').prop('checked', false);
        updateCount();
    });

    // Sélectionner les permissions recommandées
    $('#selectRecommended').click(function() {
        const recommendedCodes = <?php echo json_encode($recommended_codes); ?>;

        $('.permission-checkbox').each(function() {
            const code = $(this).data('code');
            $(this).prop('checked', recommendedCodes.includes(code));
        });

        updateCount();
    });

    // Mettre à jour le compteur à chaque changement
    $('.permission-checkbox').change(updateCount);

    // Copier les permissions d'un autre utilisateur
    $('#copyFromUser').click(function() {
        // Cette fonctionnalité peut être implémentée avec un modal
        alert('Fonctionnalité à venir : Copier les permissions d\'un autre utilisateur');
    });

    // Confirmation avant soumission
    $('#permissionsForm').submit(function(e) {
        const count = $('.permission-checkbox:checked').length;
        const userName = '<?php echo addslashes($user['prenom'] . ' ' . $user['nom']); ?>';

        if (count === 0) {
            if (!confirm(`Attention : Aucune permission ne sera attribuée à ${userName}. Continuer ?`)) {
                e.preventDefault();
                return false;
            }
        }

        return confirm(`Confirmer l'attribution de ${count} permission(s) à ${userName} ?`);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

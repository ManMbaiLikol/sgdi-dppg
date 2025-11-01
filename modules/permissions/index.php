<?php
// Gestion des permissions - SGDI
require_once '../../includes/auth.php';
require_once 'functions.php';

// Seuls les admins peuvent gérer les permissions
requireRole('admin');

$page_title = 'Gestion des Permissions';

// Récupérer les utilisateurs avec leurs permissions
$users = getUsersWithPermissions();

// Statistiques
$stats = getPermissionsStats();

require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2>
            <i class="fas fa-shield-alt"></i> Gestion des Permissions
        </h2>
        <p class="text-muted">Attribuez des permissions spécifiques à chaque utilisateur</p>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body">
                <h5 class="card-title text-primary">
                    <i class="fas fa-key"></i> Permissions disponibles
                </h5>
                <h2 class="mb-0"><?php echo $stats['total_permissions']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body">
                <h5 class="card-title text-success">
                    <i class="fas fa-users"></i> Utilisateurs avec permissions
                </h5>
                <h2 class="mb-0"><?php echo $stats['users_with_permissions']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-info">
            <div class="card-body">
                <h5 class="card-title text-info">
                    <i class="fas fa-layer-group"></i> Modules couverts
                </h5>
                <h2 class="mb-0"><?php echo count($stats['permissions_by_module']); ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Permissions par module -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-layer-group"></i> Distribution des permissions par module
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($stats['permissions_by_module'] as $module): ?>
                    <div class="col-md-3 mb-2">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-secondary me-2"><?php echo $module['count']; ?></span>
                            <strong><?php echo ucfirst($module['module']); ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Liste des utilisateurs -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-users"></i> Utilisateurs et Permissions
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="usersTable">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Permissions</th>
                        <th>Statut</th>
                        <th width="250">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr class="<?php echo $user['actif'] ? '' : 'table-secondary'; ?>">
                        <td>
                            <strong><?php echo sanitize($user['nom'] . ' ' . $user['prenom']); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo sanitize($user['username']); ?></small>
                        </td>
                        <td><?php echo sanitize($user['email']); ?></td>
                        <td>
                            <span class="badge bg-<?php
                                echo match($user['role']) {
                                    'admin' => 'danger',
                                    'chef_service' => 'primary',
                                    'directeur' => 'success',
                                    'sous_directeur' => 'info',
                                    default => 'secondary'
                                };
                            ?>">
                                <?php echo getRoleLabel($user['role']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['role'] === 'admin'): ?>
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-crown"></i> Toutes (Admin)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-primary">
                                    <?php echo $user['permissions_count']; ?> permission(s)
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['actif']): ?>
                                <span class="badge bg-success">Actif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['role'] !== 'admin'): ?>
                                <a href="<?php echo url('modules/permissions/manage.php?user_id=' . $user['id']); ?>"
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Gérer les permissions
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary" disabled>
                                    <i class="fas fa-lock"></i> Admin (complet)
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
        },
        order: [[0, 'asc']],
        pageLength: 25
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

<?php
// Détails d'un utilisateur - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

// Seuls les administrateurs peuvent voir les détails des utilisateurs
requireRole('admin');

$user_id = intval($_GET['id'] ?? 0);
if (!$user_id) {
    redirect(url('modules/users/list.php'), 'ID utilisateur manquant', 'error');
}

$user = getUserById($user_id);
if (!$user) {
    redirect(url('modules/users/list.php'), 'Utilisateur introuvable', 'error');
}

$page_title = 'Détails de ' . $user['nom'] . ' ' . $user['prenom'];

// Récupérer les statistiques et activités de l'utilisateur
$user_stats = getUserActivityStats($user_id);
$recent_activity = getUserRecentActivity($user_id, 10);

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            <p class="text-muted">Informations détaillées et activité utilisateur</p>
        </div>
        <div>
            <div class="btn-group">
                <a href="<?php echo url('modules/users/edit.php?id=' . $user_id); ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Modifier
                </a>
                <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                    <span class="visually-hidden">Actions</span>
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item" href="<?php echo url('modules/users/reset_password.php?id=' . $user_id); ?>">
                            <i class="fas fa-key"></i> Réinitialiser mot de passe
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php if ($user_id != $_SESSION['user_id']): ?>
                        <li>
                            <button class="dropdown-item text-<?php echo $user['actif'] ? 'warning' : 'success'; ?>"
                                    onclick="toggleUserStatus(<?php echo $user_id; ?>)">
                                <i class="fas fa-<?php echo $user['actif'] ? 'user-times' : 'user-check'; ?>"></i>
                                <?php echo $user['actif'] ? 'Désactiver' : 'Activer'; ?>
                            </button>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            <a href="<?php echo url('modules/users/list.php'); ?>" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Informations principales -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user"></i> Informations personnelles</h5>
                </div>
                <div class="card-body text-center">
                    <!-- Avatar -->
                    <div class="avatar-large bg-<?php echo getRoleColor($user['role']); ?> text-white mx-auto mb-3">
                        <?php echo strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)); ?>
                    </div>

                    <h5 class="mb-1"><?php echo sanitize($user['nom'] . ' ' . $user['prenom']); ?></h5>
                    <p class="text-muted mb-3"><?php echo getRoleLabel($user['role']); ?></p>

                    <!-- Statut -->
                    <div class="mb-3">
                        <?php if ($user['actif']): ?>
                            <span class="badge bg-success fs-6">
                                <i class="fas fa-check-circle"></i> Compte actif
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger fs-6">
                                <i class="fas fa-times-circle"></i> Compte désactivé
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Informations de contact -->
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-user text-muted me-2"></i> Username</span>
                            <code><?php echo sanitize($user['username']); ?></code>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-envelope text-muted me-2"></i> Email</span>
                            <?php if ($user['email']): ?>
                                <a href="mailto:<?php echo sanitize($user['email']); ?>" class="text-decoration-none">
                                    <?php echo sanitize($user['email']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Non renseigné</span>
                            <?php endif; ?>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><i class="fas fa-phone text-muted me-2"></i> Téléphone</span>
                            <?php if ($user['telephone']): ?>
                                <a href="tel:<?php echo sanitize($user['telephone']); ?>" class="text-decoration-none">
                                    <?php echo sanitize($user['telephone']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Non renseigné</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Permissions du rôle -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-shield-alt"></i> Permissions du rôle</h6>
                </div>
                <div class="card-body">
                    <?php $permissions = getRolePermissions($user['role']); ?>
                    <?php if (!empty($permissions)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($permissions as $permission): ?>
                                <div class="list-group-item px-0 py-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    <small><?php echo sanitize($permission); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Aucune permission spécifique</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistiques et activité -->
        <div class="col-md-8">
            <!-- Statistiques -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="card-text">Dossiers créés</p>
                                    <h4 class="mb-0"><?php echo $user_stats['dossiers_crees']; ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-folder-plus fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="card-text">Actions effectuées</p>
                                    <h4 class="mb-0"><?php echo $user_stats['actions_effectuees']; ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-tasks fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="card-text">Dernière connexion</p>
                                    <h6 class="mb-0">
                                        <?php echo $user['derniere_connexion'] ? formatDate($user['derniere_connexion']) : 'Jamais'; ?>
                                    </h6>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-sign-in-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <p class="card-text">Membre depuis</p>
                                    <h6 class="mb-0"><?php echo formatDate($user['date_creation']); ?></h6>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activité récente -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Activité récente</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_activity)): ?>
                        <div class="timeline">
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-<?php echo getActivityTypeColor($activity['type']); ?>">
                                        <i class="fas fa-<?php echo getActivityTypeIcon($activity['type']); ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1"><?php echo sanitize($activity['action']); ?></h6>
                                        <p class="text-muted mb-1"><?php echo sanitize($activity['description']); ?></p>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo formatDateTime($activity['date_action']); ?>
                                        </small>
                                        <?php if ($activity['dossier_numero']): ?>
                                            <div class="mt-1">
                                                <span class="badge bg-light text-dark">
                                                    Dossier: <?php echo sanitize($activity['dossier_numero']); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">Aucune activité récente</h6>
                            <p class="text-muted">Cet utilisateur n'a pas encore effectué d'actions dans le système.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (count($recent_activity) >= 10): ?>
                    <div class="card-footer text-center">
                        <a href="<?php echo url('modules/users/activity.php?id=' . $user_id); ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-plus"></i> Voir toute l'activité
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-large {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: bold;
}

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
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 5px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
    border: 3px solid white;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-left: 15px;
}
</style>

<script>
function toggleUserStatus(userId) {
    if (confirm('Êtes-vous sûr de vouloir changer le statut de cet utilisateur ?')) {
        fetch('<?php echo url("modules/users/ajax/toggle_status.php"); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                user_id: userId,
                csrf_token: '<?php echo generateCSRFToken(); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue');
        });
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
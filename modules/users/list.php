<?php
// Liste des utilisateurs - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

// Seuls les administrateurs peuvent gérer les utilisateurs
requireRole('admin');

$page_title = 'Gestion des utilisateurs';

// Filtres
$filters = [
    'role' => sanitize($_GET['role'] ?? ''),
    'actif' => isset($_GET['actif']) ? intval($_GET['actif']) : '',
    'search' => sanitize($_GET['search'] ?? '')
];

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Récupérer les utilisateurs
$users = getUsersWithFilters($filters, $limit, $offset);
$total_users = countUsersWithFilters($filters);
$total_pages = ceil($total_users / $limit);

// Statistiques rapides
$stats = getUserStats();

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            <p class="text-muted">Gérer les comptes utilisateurs du système</p>
        </div>
        <div>
            <a href="<?php echo url('modules/users/create.php'); ?>" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Nouvel utilisateur
            </a>
        </div>
    </div>

    <!-- Statistiques rapides -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="card-text">Total utilisateurs</p>
                            <h4 class="mb-0"><?php echo $stats['total']; ?></h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x"></i>
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
                            <p class="card-text">Utilisateurs actifs</p>
                            <h4 class="mb-0"><?php echo $stats['actifs']; ?></h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="card-text">Utilisateurs inactifs</p>
                            <h4 class="mb-0"><?php echo $stats['inactifs']; ?></h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-times fa-2x"></i>
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
                            <p class="card-text">Administrateurs</p>
                            <h4 class="mb-0"><?php echo $stats['admins']; ?></h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-shield fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter"></i> Filtres</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Recherche</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?php echo htmlspecialchars($filters['search']); ?>"
                           placeholder="Nom, email, username...">
                </div>
                <div class="col-md-3">
                    <label for="role" class="form-label">Rôle</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">Tous les rôles</option>
                        <option value="admin" <?php echo $filters['role'] === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                        <option value="chef_service" <?php echo $filters['role'] === 'chef_service' ? 'selected' : ''; ?>>Chef Service SDTD</option>
                        <option value="billeteur" <?php echo $filters['role'] === 'billeteur' ? 'selected' : ''; ?>>Billeteur DPPG</option>
                        <option value="chef_commission" <?php echo $filters['role'] === 'chef_commission' ? 'selected' : ''; ?>>Chef de Commission</option>
                        <option value="cadre_daj" <?php echo $filters['role'] === 'cadre_daj' ? 'selected' : ''; ?>>Cadre DAJ</option>
                        <option value="cadre_dppg" <?php echo $filters['role'] === 'cadre_dppg' ? 'selected' : ''; ?>>Cadre DPPG</option>
                        <option value="sous_directeur" <?php echo $filters['role'] === 'sous_directeur' ? 'selected' : ''; ?>>Sous-Directeur SDTD</option>
                        <option value="directeur" <?php echo $filters['role'] === 'directeur' ? 'selected' : ''; ?>>Directeur DPPG</option>
                        <option value="cabinet" <?php echo $filters['role'] === 'cabinet' ? 'selected' : ''; ?>>Cabinet/Secrétariat</option>
                        <option value="lecteur_public" <?php echo $filters['role'] === 'lecteur_public' ? 'selected' : ''; ?>>Lecteur Public</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="actif" class="form-label">Statut</label>
                    <select class="form-select" id="actif" name="actif">
                        <option value="">Tous</option>
                        <option value="1" <?php echo $filters['actif'] === 1 ? 'selected' : ''; ?>>Actif</option>
                        <option value="0" <?php echo $filters['actif'] === 0 ? 'selected' : ''; ?>>Inactif</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="<?php echo url('modules/users/list.php'); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-undo"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des utilisateurs -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list"></i> Liste des utilisateurs
                <span class="badge bg-secondary ms-2"><?php echo $total_users; ?> utilisateur(s)</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucun utilisateur trouvé</h5>
                    <p class="text-muted">Aucun utilisateur ne correspond aux critères de recherche.</p>
                    <a href="<?php echo url('modules/users/create.php'); ?>" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Créer le premier utilisateur
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nom complet</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Téléphone</th>
                                <th>Statut</th>
                                <th>Dernière connexion</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3 bg-primary text-white">
                                                <?php echo strtoupper(substr($user['prenom'] ?? 'U', 0, 1) . substr($user['nom'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo sanitize($user['nom'] . ' ' . $user['prenom']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    Créé le <?php echo formatDate($user['date_creation']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <code><?php echo sanitize($user['username']); ?></code>
                                    </td>
                                    <td>
                                        <?php if ($user['email']): ?>
                                            <a href="mailto:<?php echo sanitize($user['email']); ?>">
                                                <?php echo sanitize($user['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Non renseigné</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getRoleColor($user['role']); ?>">
                                            <?php echo getRoleLabel($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo sanitize($user['telephone'] ?? 'Non renseigné'); ?>
                                    </td>
                                    <td>
                                        <?php if ($user['actif']): ?>
                                            <span class="badge bg-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['derniere_connexion']): ?>
                                            <span title="<?php echo formatDateTime($user['derniere_connexion']); ?>">
                                                <?php echo formatDate($user['derniere_connexion']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Jamais connecté</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo url('modules/users/view.php?id=' . $user['id']); ?>"
                                               class="btn btn-outline-info btn-sm" title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?php echo url('modules/users/edit.php?id=' . $user['id']); ?>"
                                               class="btn btn-outline-primary btn-sm" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button type="button" class="btn btn-outline-<?php echo $user['actif'] ? 'warning' : 'success'; ?> btn-sm"
                                                        onclick="toggleUserStatus(<?php echo $user['id']; ?>)"
                                                        title="<?php echo $user['actif'] ? 'Désactiver' : 'Activer'; ?>">
                                                    <i class="fas fa-<?php echo $user['actif'] ? 'user-times' : 'user-check'; ?>"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Navigation des pages">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                                        Premier
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        Précédent
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        Suivant
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                        Dernier
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
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
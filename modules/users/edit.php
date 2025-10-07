<?php
// Modification d'utilisateur - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

// Seuls les administrateurs peuvent modifier les utilisateurs
requireRole('admin');

$user_id = intval($_GET['id'] ?? 0);
if (!$user_id) {
    redirect(url('modules/users/list.php'), 'ID utilisateur manquant', 'error');
}

$user = getUserById($user_id);
if (!$user) {
    redirect(url('modules/users/list.php'), 'Utilisateur introuvable', 'error');
}

$page_title = 'Modifier ' . $user['nom'] . ' ' . $user['prenom'];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $data = [
            'username' => sanitize($_POST['username'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'nom' => sanitize($_POST['nom'] ?? ''),
            'prenom' => sanitize($_POST['prenom'] ?? ''),
            'telephone' => sanitize($_POST['telephone'] ?? ''),
            'role' => sanitize($_POST['role'] ?? ''),
            'actif' => intval($_POST['actif'] ?? 1)
        ];

        // Validation
        $required_fields = ['username', 'email', 'nom', 'prenom', 'role'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Le champ $field est requis";
            }
        }

        // Validation username unique (sauf pour l'utilisateur actuel)
        if (!empty($data['username']) && usernameExists($data['username'], $user_id)) {
            $errors[] = "Ce nom d'utilisateur existe déjà";
        }

        // Validation email unique (sauf pour l'utilisateur actuel)
        if (!empty($data['email']) && emailExists($data['email'], $user_id)) {
            $errors[] = "Cette adresse email existe déjà";
        }

        // Validation email format
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Format d'email invalide";
        }

        // Empêcher l'auto-désactivation
        if ($user_id == $_SESSION['user_id'] && $data['actif'] == 0) {
            $errors[] = "Vous ne pouvez pas désactiver votre propre compte";
        }

        if (empty($errors)) {
            if (updateUser($user_id, $data)) {
                redirect(url('modules/users/view.php?id=' . $user_id), 'Utilisateur modifié avec succès', 'success');
            } else {
                $errors[] = 'Erreur lors de la modification de l\'utilisateur';
            }
        }
    }
}

$available_roles = getAvailableRoles();

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            <p class="text-muted">Modifier les informations utilisateur</p>
        </div>
        <div>
            <a href="<?php echo url('modules/users/view.php?id=' . $user_id); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-eye"></i> Voir détails
            </a>
            <a href="<?php echo url('modules/users/list.php'); ?>" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>

    <!-- Formulaire -->
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-edit"></i> Informations utilisateur</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h6><i class="fas fa-exclamation-triangle"></i> Erreurs détectées :</h6>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo sanitize($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nom" name="nom"
                                           value="<?php echo sanitize($_POST['nom'] ?? $user['nom']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="prenom" name="prenom"
                                           value="<?php echo sanitize($_POST['prenom'] ?? $user['prenom']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Nom d'utilisateur <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username"
                                           value="<?php echo sanitize($_POST['username'] ?? $user['username']); ?>" required>
                                    <div class="form-text">Utilisé pour la connexion. Doit être unique.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo sanitize($_POST['email'] ?? $user['email']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telephone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="telephone" name="telephone"
                                           value="<?php echo sanitize($_POST['telephone'] ?? $user['telephone']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Rôle <span class="text-danger">*</span></label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Sélectionner un rôle</option>
                                        <?php foreach ($available_roles as $role_key => $role_label): ?>
                                            <option value="<?php echo $role_key; ?>"
                                                    <?php echo (($_POST['role'] ?? $user['role']) === $role_key) ? 'selected' : ''; ?>>
                                                <?php echo sanitize($role_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Statut du compte</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="actif" name="actif" value="1"
                                       <?php echo (($_POST['actif'] ?? $user['actif']) == 1) ? 'checked' : ''; ?>
                                       <?php echo ($user_id == $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                <label class="form-check-label" for="actif">
                                    Compte actif
                                </label>
                                <?php if ($user_id == $_SESSION['user_id']): ?>
                                    <div class="form-text text-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Vous ne pouvez pas désactiver votre propre compte.
                                    </div>
                                    <input type="hidden" name="actif" value="1">
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Informations :</h6>
                            <ul class="mb-0">
                                <li>Le mot de passe ne peut pas être modifié depuis cette page</li>
                                <li>Utilisez la fonction "Réinitialiser mot de passe" si nécessaire</li>
                                <li>Les modifications prendront effet immédiatement</li>
                            </ul>
                        </div>

                        <!-- Informations supplémentaires -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>Informations du compte</h6>
                                        <p class="mb-1"><small><strong>Créé le :</strong> <?php echo formatDateTime($user['date_creation']); ?></small></p>
                                        <p class="mb-0"><small><strong>Dernière connexion :</strong>
                                            <?php echo $user['derniere_connexion'] ? formatDateTime($user['derniere_connexion']) : 'Jamais connecté'; ?>
                                        </small></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>Actions disponibles</h6>
                                        <a href="<?php echo url('modules/users/reset_password.php?id=' . $user_id); ?>" class="btn btn-outline-warning btn-sm">
                                            <i class="fas fa-key"></i> Réinitialiser mot de passe
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="<?php echo url('modules/users/view.php?id=' . $user_id); ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
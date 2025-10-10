<?php
// Création d'utilisateur - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

// Seuls les administrateurs peuvent créer des utilisateurs
requireRole('admin');

$page_title = 'Créer un nouvel utilisateur';
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $data = [
            'username' => cleanInput($_POST['username'] ?? ''),
            'email' => cleanInput($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'nom' => cleanInput($_POST['nom'] ?? ''),
            'prenom' => cleanInput($_POST['prenom'] ?? ''),
            'telephone' => cleanInput($_POST['telephone'] ?? ''),
            'role' => cleanInput($_POST['role'] ?? '')
        ];

        $result = createUserWithValidation($data);

        if ($result['success']) {
            redirect(url('modules/users/list.php'), 'Utilisateur créé avec succès', 'success');
        } else {
            $errors = $result['errors'];
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
            <p class="text-muted">Créer un nouveau compte utilisateur</p>
        </div>
        <div>
            <a href="<?php echo url('modules/users/list.php'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>

    <!-- Formulaire -->
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-plus"></i> Informations utilisateur</h5>
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
                                           value="<?php echo sanitize($_POST['nom'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="prenom" name="prenom"
                                           value="<?php echo sanitize($_POST['prenom'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Nom d'utilisateur <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username"
                                           value="<?php echo sanitize($_POST['username'] ?? ''); ?>" required>
                                    <div class="form-text">Utilisé pour la connexion. Doit être unique.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo sanitize($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-text">Minimum 8 caractères avec majuscules, minuscules et chiffres.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telephone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="telephone" name="telephone"
                                           value="<?php echo sanitize($_POST['telephone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Sélectionner un rôle</option>
                                <?php foreach ($available_roles as $role_key => $role_label): ?>
                                    <option value="<?php echo $role_key; ?>"
                                            <?php echo ($_POST['role'] ?? '') === $role_key ? 'selected' : ''; ?>>
                                        <?php echo sanitize($role_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Information :</h6>
                            <p class="mb-0">
                                L'utilisateur sera créé avec le statut <strong>actif</strong> et pourra se connecter immédiatement.
                                Il recevra ses identifiants par email si cette fonctionnalité est configurée.
                            </p>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?php echo url('modules/users/list.php'); ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Créer l'utilisateur
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
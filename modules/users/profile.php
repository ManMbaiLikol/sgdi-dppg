<?php
// Profil utilisateur - SGDI
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Récupérer les informations de l'utilisateur
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    redirect(url('dashboard.php'), 'Utilisateur introuvable', 'error');
}

// Traitement du formulaire de mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $nom = sanitize($_POST['nom'] ?? '');
        $prenom = sanitize($_POST['prenom'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $telephone = sanitize($_POST['telephone'] ?? '');

        // Validation
        if (empty($nom) || empty($prenom) || empty($email)) {
            $errors[] = 'Le nom, prénom et email sont obligatoires';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email invalide';
        }

        // Vérifier si l'email existe déjà pour un autre utilisateur
        if (empty($errors)) {
            $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = 'Cet email est déjà utilisé par un autre utilisateur';
            }
        }

        if (empty($errors)) {
            try {
                $sql = "UPDATE users SET nom = ?, prenom = ?, email = ?, telephone = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$nom, $prenom, $email, $telephone, $user_id]);

                if ($result) {
                    // Mettre à jour la session
                    $_SESSION['user_nom'] = $nom;
                    $_SESSION['user_prenom'] = $prenom;
                    $_SESSION['user_email'] = $email;

                    // Recharger les données
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();

                    $success = 'Profil mis à jour avec succès';
                } else {
                    $errors[] = 'Erreur lors de la mise à jour du profil';
                }
            } catch (Exception $e) {
                $errors[] = 'Erreur: ' . $e->getMessage();
            }
        }
    }
}

// Traitement du formulaire de changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $errors[] = 'Tous les champs de mot de passe sont obligatoires';
        }

        if (!password_verify($current_password, $user['password'])) {
            $errors[] = 'Mot de passe actuel incorrect';
        }

        if ($new_password !== $confirm_password) {
            $errors[] = 'Les nouveaux mots de passe ne correspondent pas';
        }

        if (strlen($new_password) < 6) {
            $errors[] = 'Le nouveau mot de passe doit contenir au moins 6 caractères';
        }

        if (empty($errors)) {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$hashed_password, $user_id]);

                if ($result) {
                    $success = 'Mot de passe modifié avec succès';
                } else {
                    $errors[] = 'Erreur lors du changement de mot de passe';
                }
            } catch (Exception $e) {
                $errors[] = 'Erreur: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Mon profil';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h1 class="h3 mb-4">
                <i class="fas fa-user-circle"></i> Mon profil
            </h1>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo sanitize($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo sanitize($success); ?>
            </div>
            <?php endif; ?>

            <!-- Informations du profil -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-id-card"></i> Informations personnelles
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="update_profile" value="1">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nom" class="form-label">Nom *</label>
                                    <input type="text" class="form-control" id="nom" name="nom"
                                           value="<?php echo sanitize($user['nom']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="prenom" class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" id="prenom" name="prenom"
                                           value="<?php echo sanitize($user['prenom']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo sanitize($user['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telephone" class="form-label">Téléphone</label>
                                    <input type="text" class="form-control" id="telephone" name="telephone"
                                           value="<?php echo sanitize($user['telephone'] ?? ''); ?>"
                                           placeholder="+237 6XX XXX XXX">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nom d'utilisateur</label>
                            <input type="text" class="form-control" value="<?php echo sanitize($user['username']); ?>" disabled>
                            <small class="form-text text-muted">Le nom d'utilisateur ne peut pas être modifié</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rôle</label>
                            <input type="text" class="form-control" value="<?php echo getRoleLabel($user['role']); ?>" disabled>
                            <small class="form-text text-muted">Seul un administrateur peut modifier votre rôle</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Date de création du compte</label>
                            <input type="text" class="form-control" value="<?php echo formatDateTime($user['date_creation']); ?>" disabled>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Changement de mot de passe -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-lock"></i> Changer mon mot de passe
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="change_password" value="1">

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mot de passe actuel *</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nouveau mot de passe *</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required
                                           minlength="6">
                                    <small class="form-text text-muted">Minimum 6 caractères</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                                           minlength="6">
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Assurez-vous de bien mémoriser votre nouveau mot de passe.
                            Vous serez déconnecté après le changement (fonctionnalité future).
                        </div>

                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key"></i> Changer le mot de passe
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

<?php
// Réinitialisation des mots de passe - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

// Seuls les administrateurs peuvent réinitialiser les mots de passe
requireRole('admin');

$user_id = intval($_GET['id'] ?? 0);
if (!$user_id) {
    redirect(url('modules/users/list.php'), 'ID utilisateur manquant', 'error');
}

$user = getUserById($user_id);
if (!$user) {
    redirect(url('modules/users/list.php'), 'Utilisateur introuvable', 'error');
}

$page_title = 'Réinitialiser le mot de passe de ' . $user['nom'] . ' ' . $user['prenom'];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $force_change = isset($_POST['force_change']) ? 1 : 0;

        // Validation
        if (empty($new_password)) {
            $errors[] = 'Le nouveau mot de passe est requis';
        } elseif (!isStrongPassword($new_password)) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères avec majuscules, minuscules et chiffres';
        }

        if (empty($confirm_password)) {
            $errors[] = 'La confirmation du mot de passe est requise';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'Les mots de passe ne correspondent pas';
        }

        if (empty($errors)) {
            if (resetUserPassword($user_id, $new_password, $force_change)) {
                // Log de l'action
                $admin_name = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];
                logActivity($user_id, 'password_reset', 'Mot de passe réinitialisé par admin ID: ' . $_SESSION['user_id']);

                // Invalider les sessions actives
                invalidateUserSessions($user_id);

                // Envoyer notification email
                sendPasswordResetNotification($user_id, $admin_name);

                redirect(url('modules/users/view.php?id=' . $user_id),
                    'Mot de passe réinitialisé avec succès. L\'utilisateur ' .
                    ($force_change ? 'devra' : 'peut') . ' le modifier à sa prochaine connexion. Une notification a été envoyée.', 'success');
            } else {
                $errors[] = 'Erreur lors de la réinitialisation du mot de passe';
            }
        }
    }
}

// Fonction pour réinitialiser le mot de passe
function resetUserPassword($user_id, $new_password, $force_change = 0) {
    global $pdo;

    try {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Vérifier si les colonnes existent
        $columns_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'force_password_change'");
        $has_force_column = $columns_check->rowCount() > 0;

        $columns_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_reset_date'");
        $has_reset_date_column = $columns_check->rowCount() > 0;

        if ($has_force_column && $has_reset_date_column) {
            // Structure complète avec nouvelles colonnes
            $sql = "UPDATE users SET password = ?, force_password_change = ?, password_reset_date = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$hashed_password, $force_change, $user_id]);
        } else {
            // Structure basique - juste le mot de passe
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$hashed_password, $user_id]);
        }

    } catch (Exception $e) {
        error_log("Erreur réinitialisation mot de passe: " . $e->getMessage());
        return false;
    }
}

// Fonction pour logger l'activité
function logActivity($user_id, $action, $description) {
    global $pdo;

    try {
        // Vérifier si la table logs_activite existe
        $tables_check = $pdo->query("SHOW TABLES LIKE 'logs_activite'");
        $table_exists = $tables_check->rowCount() > 0;

        if ($table_exists) {
            $sql = "INSERT INTO logs_activite (user_id, action, description, date_action, ip_address)
                    VALUES (?, ?, ?, NOW(), ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $action, $description, $_SERVER['REMOTE_ADDR'] ?? '']);
        } else {
            // Fallback - utiliser la table historique existante
            $sql = "INSERT INTO historique (dossier_id, action, description, user_id, date_action)
                    VALUES (0, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$action, $description, $_SESSION['user_id'] ?? $user_id]);
        }
    } catch (Exception $e) {
        error_log("Erreur log activité: " . $e->getMessage());
        // Ne pas faire échouer l'opération principale pour un problème de logging
    }
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            <p class="text-muted">Réinitialisation sécurisée du mot de passe utilisateur</p>
        </div>
        <div>
            <a href="<?php echo url('modules/users/view.php?id=' . $user_id); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-eye"></i> Voir profil
            </a>
            <a href="<?php echo url('modules/users/list.php'); ?>" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>

    <!-- Alerte de sécurité -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning">
                <h5><i class="fas fa-shield-alt"></i> Attention - Action sensible</h5>
                <p class="mb-2">Vous vous apprêtez à réinitialiser le mot de passe de <strong><?php echo sanitize($user['nom'] . ' ' . $user['prenom']); ?></strong>.</p>
                <ul class="mb-0">
                    <li>Cette action sera enregistrée dans les logs du système</li>
                    <li>L'utilisateur sera automatiquement notifié par email</li>
                    <li>Le nouveau mot de passe doit respecter les critères de sécurité</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Informations utilisateur -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-user"></i> Informations de l'utilisateur concerné</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Nom complet :</strong> <?php echo sanitize($user['nom'] . ' ' . $user['prenom']); ?></p>
                            <p><strong>Nom d'utilisateur :</strong> <?php echo sanitize($user['username']); ?></p>
                            <p><strong>Email :</strong> <?php echo sanitize($user['email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Rôle :</strong>
                                <span class="badge bg-<?php echo getRoleColor($user['role']); ?>">
                                    <?php echo sanitize(getAvailableRoles()[$user['role']] ?? $user['role']); ?>
                                </span>
                            </p>
                            <p><strong>Statut :</strong>
                                <span class="badge bg-<?php echo $user['actif'] ? 'success' : 'danger'; ?>">
                                    <?php echo $user['actif'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </p>
                            <p><strong>Dernière connexion :</strong>
                                <?php echo $user['derniere_connexion'] ? formatDateTime($user['derniere_connexion']) : 'Jamais connecté'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Critères de sécurité</h6>
                </div>
                <div class="card-body">
                    <small>
                        Le nouveau mot de passe doit contenir :
                        <ul class="mt-2 mb-0">
                            <li>Au moins 8 caractères</li>
                            <li>Au moins une majuscule</li>
                            <li>Au moins une minuscule</li>
                            <li>Au moins un chiffre</li>
                        </ul>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire de réinitialisation -->
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-key"></i> Nouveau mot de passe</h5>
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

                    <form method="POST" id="resetPasswordForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword1">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength mt-2">
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar" id="strengthBar" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <small id="strengthText" class="text-muted"></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword2">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div id="passwordMatch" class="mt-1"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="force_change" name="force_change" checked>
                                <label class="form-check-label" for="force_change">
                                    <strong>Forcer le changement à la prochaine connexion</strong>
                                </label>
                                <div class="form-text">
                                    Si coché, l'utilisateur devra obligatoirement changer son mot de passe à sa prochaine connexion.
                                    Recommandé pour la sécurité.
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-bell"></i> Actions automatiques :</h6>
                            <ul class="mb-0">
                                <li>Un email de notification sera envoyé à l'utilisateur</li>
                                <li>L'action sera enregistrée dans les logs système</li>
                                <li>Les sessions actives de l'utilisateur seront invalidées</li>
                            </ul>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?php echo url('modules/users/view.php?id=' . $user_id); ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-warning" id="submitBtn" disabled>
                                <i class="fas fa-key"></i> Réinitialiser le mot de passe
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const passwordMatch = document.getElementById('passwordMatch');
    const submitBtn = document.getElementById('submitBtn');
    const togglePassword1 = document.getElementById('togglePassword1');
    const togglePassword2 = document.getElementById('togglePassword2');

    // Toggle password visibility
    togglePassword1.addEventListener('click', function() {
        const type = newPassword.getAttribute('type') === 'password' ? 'text' : 'password';
        newPassword.setAttribute('type', type);
        this.querySelector('i').classList.toggle('fa-eye');
        this.querySelector('i').classList.toggle('fa-eye-slash');
    });

    togglePassword2.addEventListener('click', function() {
        const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPassword.setAttribute('type', type);
        this.querySelector('i').classList.toggle('fa-eye');
        this.querySelector('i').classList.toggle('fa-eye-slash');
    });

    // Password strength checker
    newPassword.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        let color = 'danger';
        let text = 'Très faible';

        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        switch (strength) {
            case 0:
            case 1:
                color = 'danger';
                text = 'Très faible';
                break;
            case 2:
                color = 'warning';
                text = 'Faible';
                break;
            case 3:
                color = 'info';
                text = 'Moyen';
                break;
            case 4:
                color = 'success';
                text = 'Fort';
                break;
            case 5:
                color = 'success';
                text = 'Très fort';
                break;
        }

        const percentage = (strength / 5) * 100;
        strengthBar.style.width = percentage + '%';
        strengthBar.className = `progress-bar bg-${color}`;
        strengthText.textContent = text;
        strengthText.className = `text-${color}`;

        checkFormValidity();
    });

    // Password match checker
    confirmPassword.addEventListener('input', function() {
        const password = newPassword.value;
        const confirm = this.value;

        if (confirm === '') {
            passwordMatch.innerHTML = '';
        } else if (password === confirm) {
            passwordMatch.innerHTML = '<small class="text-success"><i class="fas fa-check"></i> Les mots de passe correspondent</small>';
        } else {
            passwordMatch.innerHTML = '<small class="text-danger"><i class="fas fa-times"></i> Les mots de passe ne correspondent pas</small>';
        }

        checkFormValidity();
    });

    // Form validation
    function checkFormValidity() {
        const password = newPassword.value;
        const confirm = confirmPassword.value;
        const isPasswordStrong = password.length >= 8 &&
                                /[A-Z]/.test(password) &&
                                /[a-z]/.test(password) &&
                                /[0-9]/.test(password);
        const isPasswordMatch = password === confirm && confirm !== '';

        submitBtn.disabled = !(isPasswordStrong && isPasswordMatch);
    }

    // Form submission confirmation
    document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
        if (!confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe de cet utilisateur ?')) {
            e.preventDefault();
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
<?php
// Changement de mot de passe - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

// L'utilisateur doit être connecté
requireLogin();

$user_id = $_SESSION['user_id'];
$must_change = mustChangePassword($user_id);

$page_title = $must_change ? 'Changement de mot de passe obligatoire' : 'Changer mon mot de passe';
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($current_password)) {
            $errors[] = 'Le mot de passe actuel est requis';
        }

        if (empty($new_password)) {
            $errors[] = 'Le nouveau mot de passe est requis';
        } elseif (!isStrongPassword($new_password)) {
            $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères avec majuscules, minuscules et chiffres';
        }

        if (empty($confirm_password)) {
            $errors[] = 'La confirmation du mot de passe est requise';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'Les nouveaux mots de passe ne correspondent pas';
        }

        // Vérifier le mot de passe actuel
        if (!empty($current_password)) {
            global $pdo;
            $sql = "SELECT password FROM users WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $stored_password = $stmt->fetchColumn();

            if (!password_verify($current_password, $stored_password)) {
                $errors[] = 'Le mot de passe actuel est incorrect';
            }
        }

        if (empty($errors)) {
            if (changePassword($user_id, $new_password)) {
                // Marquer le changement comme effectué si c'était forcé
                if ($must_change) {
                    clearPasswordChangeFlag($user_id);
                }

                // Log de l'action
                logActivity($user_id, 'password_change', 'Mot de passe modifié par l\'utilisateur');

                $redirect_url = $must_change ? url('dashboard.php') : url('modules/users/change_password.php');
                redirect($redirect_url, 'Mot de passe modifié avec succès', 'success');
            } else {
                $errors[] = 'Erreur lors de la modification du mot de passe';
            }
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
            <p class="text-muted">
                <?php if ($must_change): ?>
                    Votre administrateur vous demande de changer votre mot de passe
                <?php else: ?>
                    Modifier votre mot de passe de connexion
                <?php endif; ?>
            </p>
        </div>
        <?php if (!$must_change): ?>
        <div>
            <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($must_change): ?>
    <!-- Alerte de changement obligatoire -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle"></i> Changement de mot de passe obligatoire</h5>
                <p class="mb-2">Votre administrateur a réinitialisé votre mot de passe pour des raisons de sécurité.</p>
                <p class="mb-0">Vous devez définir un nouveau mot de passe pour continuer à utiliser le système.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Formulaire -->
    <div class="row justify-content-center">
        <div class="col-md-6">
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

                    <form method="POST" id="changePasswordForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mot de passe actuel <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleCurrent">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleNew">
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

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirm">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" class="mt-1"></div>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Critères de sécurité :</h6>
                            <ul class="mb-0">
                                <li>Au moins 8 caractères</li>
                                <li>Au moins une majuscule (A-Z)</li>
                                <li>Au moins une minuscule (a-z)</li>
                                <li>Au moins un chiffre (0-9)</li>
                            </ul>
                        </div>

                        <div class="d-flex justify-content-between">
                            <?php if (!$must_change): ?>
                            <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <?php else: ?>
                            <div></div>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                <i class="fas fa-save"></i> Modifier le mot de passe
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
    const currentPassword = document.getElementById('current_password');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const passwordMatch = document.getElementById('passwordMatch');
    const submitBtn = document.getElementById('submitBtn');

    // Toggle password visibility
    function setupToggle(toggleId, inputId) {
        const toggle = document.getElementById(toggleId);
        const input = document.getElementById(inputId);

        toggle.addEventListener('click', function() {
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }

    setupToggle('toggleCurrent', 'current_password');
    setupToggle('toggleNew', 'new_password');
    setupToggle('toggleConfirm', 'confirm_password');

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
        const current = currentPassword.value;
        const password = newPassword.value;
        const confirm = confirmPassword.value;

        const isCurrentFilled = current.length > 0;
        const isPasswordStrong = password.length >= 8 &&
                                /[A-Z]/.test(password) &&
                                /[a-z]/.test(password) &&
                                /[0-9]/.test(password);
        const isPasswordMatch = password === confirm && confirm !== '';

        submitBtn.disabled = !(isCurrentFilled && isPasswordStrong && isPasswordMatch);
    }

    // Check validity on current password input
    currentPassword.addEventListener('input', checkFormValidity);

    // Form submission confirmation
    document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
        if (!confirm('Êtes-vous sûr de vouloir modifier votre mot de passe ?')) {
            e.preventDefault();
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
<?php
// Page de connexion - SGDI MVP
require_once 'includes/auth.php';
require_once 'config/google_oauth.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirect(url('dashboard.php'));
}

// URL de connexion Google
$googleLoginUrl = getGoogleLoginUrl();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Nom d\'utilisateur et mot de passe requis';
        } else {
            if (loginUser($username, $password)) {
                // Vérifier si l'utilisateur doit changer son mot de passe
                require_once 'modules/users/functions.php';
                if (mustChangePassword($_SESSION['user_id'])) {
                    redirect(url('modules/users/change_password.php'), 'Vous devez changer votre mot de passe avant de continuer', 'warning');
                } else {
                    redirect(url('dashboard.php'), 'Connexion réussie', 'success');
                }
            } else {
                $error = 'Nom d\'utilisateur ou mot de passe incorrect';
            }
        }
    }
}

$page_title = 'Connexion';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SGDI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 400px;
            width: 100%;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo i {
            font-size: 3rem;
            color: #667eea;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 500;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="login-card">
                <div class="logo">
                    <i class="fas fa-building"></i>
                    <h3 class="mt-3 text-primary">SGDI</h3>
                    <p class="text-muted small">Système de Gestion des Dossiers d'Implantation</p>
                    <p class="text-muted small"><strong>MINEE - DPPG</strong></p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo sanitize($error); ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i> Nom d'utilisateur
                        </label>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?php echo sanitize($_POST['username'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> Mot de passe
                        </label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn btn-login btn-primary w-100">
                        <i class="fas fa-sign-in-alt"></i> Se connecter
                    </button>
                </form>

                <div class="text-center my-3">
                    <span class="text-muted">ou</span>
                </div>

                <!-- Bouton Google OAuth -->
                <a href="<?php echo htmlspecialchars($googleLoginUrl); ?>"
                   class="btn btn-outline-danger w-100 mb-3">
                    <i class="fab fa-google"></i> Se connecter avec Google
                    <small class="d-block text-muted" style="font-size: 0.75rem;">Accès Lecteur Public</small>
                </a>

                <div class="mt-3 text-center">
                    <small class="text-muted">
                        Pour obtenir un compte interne, contactez l'administrateur système
                    </small>
                </div>

                <div class="mt-4 border-top pt-3">
                    <p class="text-center text-muted small mb-0">
                        <strong>Comptes de démonstration:</strong>
                    </p>
                    <div class="row mt-2">
                        <div class="col-4">
                            <small class="text-muted">
                                <strong>admin</strong> / admin123<br>
                                <strong>chef</strong> / chef123<br>
                                <strong>cadre</strong> / cadre123<br>
                                <strong>billeteur</strong> / bill123
                            </small>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">
                                <strong class="text-primary">sousdirecteur</strong> / sousdir123<br>
                                <strong class="text-primary">directeur</strong> / dir123<br>
                                <strong class="text-primary">ministre</strong> / ministre123<br>
                                <strong>lecteur</strong> / lecteur123
                            </small>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">
                                <em>Circuit visa:</em><br>
                                1. Chef Service<br>
                                2. <span class="text-primary">Sous-Dir ✨</span><br>
                                3. <span class="text-primary">Directeur ✨</span><br>
                                4. <span class="text-primary">Ministre ✨</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
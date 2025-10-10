<?php
// Connexion - SGDI MVP
require_once 'includes/auth.php';

// Si déjà connecté, rediriger vers dashboard
if (isLoggedIn()) {
    redirect(url('dashboard.php'));
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        redirect(url('index.php'), 'Veuillez remplir tous les champs', 'error');
    }

    $login_result = loginUser($username, $password);

    // Debug: Log pour Railway
    error_log("Login attempt - Username: $username, Result: " . ($login_result ? 'SUCCESS' : 'FAIL'));

    if ($login_result) {
        // Connexion réussie
        $user = getCurrentUser();
        error_log("Redirecting to dashboard for user: " . $user['prenom']);

        // Rediriger vers le dashboard
        redirect(url('dashboard.php'), 'Bienvenue ' . $user['prenom'] . ' !', 'success');
    } else {
        // Échec de connexion
        error_log("Login failed - redirecting to index.php");
        redirect(url('index.php'), 'Nom d\'utilisateur ou mot de passe incorrect', 'error');
    }
}

// Si accès direct (GET), rediriger vers index
redirect(url('index.php'));
?>

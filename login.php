<?php
// Connexion - SGDI MVP
require_once 'includes/auth.php';

// Si déjà connecté, rediriger vers dashboard
if (isLoggedIn()) {
    redirect(url('dashboard.php'));
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        redirect(url('index.php'), 'Veuillez remplir tous les champs', 'error');
    }

    if (loginUser($username, $password)) {
        // Connexion réussie
        $user = getCurrentUser();

        // Rediriger vers le dashboard
        redirect(url('dashboard.php'), 'Bienvenue ' . $user['prenom'] . ' !', 'success');
    } else {
        // Échec de connexion
        redirect(url('index.php'), 'Nom d\'utilisateur ou mot de passe incorrect', 'error');
    }
}

// Si accès direct (GET), rediriger vers index
redirect(url('index.php'));
?>

<?php
/**
 * Callback Google OAuth - Traite la réponse de Google
 */

require_once '../config/database.php';
require_once '../config/google_oauth.php';
require_once '../includes/auth.php';

// Vérifier qu'on a un code
if (!isset($_GET['code'])) {
    $_SESSION['error'] = "Erreur d'authentification Google: Aucun code reçu";
    redirect(url('index.php'));
    exit;
}

$code = $_GET['code'];

// Échanger le code contre un access token
$accessToken = getGoogleAccessToken($code);

if (!$accessToken) {
    $_SESSION['error'] = "Erreur d'authentification Google: Impossible d'obtenir le token";
    redirect(url('index.php'));
    exit;
}

// Récupérer les infos utilisateur
$userInfo = getGoogleUserInfo($accessToken);

if (!$userInfo) {
    $_SESSION['error'] = "Erreur d'authentification Google: Impossible de récupérer les informations";
    redirect(url('index.php'));
    exit;
}

// Extraire les informations
$google_id = $userInfo['id'];
$email = $userInfo['email'];
$prenom = $userInfo['given_name'] ?? '';
$nom = $userInfo['family_name'] ?? '';
$photo = $userInfo['picture'] ?? '';

// Vérifier si l'utilisateur existe déjà
global $pdo;

$sql = "SELECT * FROM users WHERE google_id = ? OR email = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$google_id, $email]);
$user = $stmt->fetch();

if ($user) {
    // Utilisateur existant - mettre à jour le google_id si nécessaire
    if (empty($user['google_id'])) {
        $sql = "UPDATE users SET google_id = ?, photo_url = ? WHERE id = ?";
        $pdo->prepare($sql)->execute([$google_id, $photo, $user['id']]);
    }

    // Vérifier que c'est un lecteur
    if ($user['role'] !== 'lecteur') {
        $_SESSION['error'] = "Ce compte n'est pas un compte lecteur. Veuillez utiliser votre mot de passe habituel.";
        redirect(url('index.php'));
        exit;
    }
} else {
    // Nouvel utilisateur - créer un compte lecteur automatiquement
    $username = strtolower(str_replace(' ', '_', $prenom . '_' . $nom . '_' . substr($google_id, 0, 4)));

    $sql = "INSERT INTO users (username, email, password, nom, prenom, role, google_id, photo_url, actif, date_creation)
            VALUES (?, ?, ?, ?, ?, 'lecteur', ?, ?, 1, NOW())";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $username,
            $email,
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), // Mot de passe aléatoire
            $nom,
            $prenom,
            $google_id,
            $photo
        ]);

        $user_id = $pdo->lastInsertId();

        // Récupérer l'utilisateur créé
        $user = $pdo->query("SELECT * FROM users WHERE id = $user_id")->fetch();

    } catch (PDOException $e) {
        error_log("Erreur création utilisateur Google: " . $e->getMessage());
        $_SESSION['error'] = "Erreur lors de la création du compte";
        redirect(url('index.php'));
        exit;
    }
}

// Connexion réussie - créer la session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_nom'] = $user['nom'];
$_SESSION['user_prenom'] = $user['prenom'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_photo'] = $photo;
$_SESSION['auth_method'] = 'google';

// Regénérer l'ID de session pour la sécurité
session_regenerate_id(true);

// Logger la connexion
$sql = "INSERT INTO logs_activite (user_id, action, details, ip_address, date_action)
        VALUES (?, 'connexion', 'Connexion via Google OAuth', ?, NOW())";
$pdo->prepare($sql)->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);

// Rediriger vers le dashboard lecteur
$_SESSION['success'] = "Bienvenue " . $prenom . " ! Vous êtes connecté avec Google.";
redirect(url('modules/lecteur/dashboard.php'));
?>

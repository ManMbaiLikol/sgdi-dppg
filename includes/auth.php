<?php
// Système d'authentification - SGDI MVP

// Forcer l'encodage UTF-8 dès le début
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Démarrer la session uniquement si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

// Vérifier si l'utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// Vérifier si l'utilisateur a un rôle spécifique
function hasRole($required_role) {
    return isLoggedIn() && $_SESSION['user_role'] === $required_role;
}

// Vérifier si l'utilisateur a l'un des rôles requis
function hasAnyRole($required_roles) {
    if (!isLoggedIn()) return false;
    return in_array($_SESSION['user_role'], $required_roles);
}

// Forcer la connexion (rediriger si non connecté)
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(url('index.php'), 'Vous devez vous connecter pour accéder à cette page', 'error');
    }

    // Générer un token CSRF s'il n'existe pas
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Vérifier si l'utilisateur doit changer son mot de passe
    // Exclure la page de changement de mot de passe et de déconnexion
    $current_page = $_SERVER['PHP_SELF'];
    $excluded_pages = [
        '/modules/users/change_password.php',
        '/logout.php'
    ];

    $is_excluded = false;
    foreach ($excluded_pages as $excluded) {
        if (strpos($current_page, $excluded) !== false) {
            $is_excluded = true;
            break;
        }
    }

    if (!$is_excluded) {
        require_once __DIR__ . '/../modules/users/functions.php';
        if (mustChangePassword($_SESSION['user_id'])) {
            redirect(url('modules/users/change_password.php'), 'Vous devez changer votre mot de passe avant de continuer', 'warning');
        }
    }
}

// Forcer un rôle spécifique
function requireRole($required_role) {
    requireLogin();
    if (!hasRole($required_role)) {
        redirect(url('dashboard.php'), 'Vous n\'avez pas les permissions nécessaires', 'error');
    }
}

// Forcer l'un des rôles
function requireAnyRole($required_roles) {
    requireLogin();
    if (!hasAnyRole($required_roles)) {
        redirect(url('dashboard.php'), 'Vous n\'avez pas les permissions nécessaires', 'error');
    }
}

// Connecter un utilisateur
function loginUser($username, $password) {
    global $pdo;

    $sql = "SELECT id, username, password, role, nom, prenom, email, actif
            FROM users
            WHERE username = ? AND actif = 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Régénérer l'ID de session
        session_regenerate_id(true);

        // Stocker les informations utilisateur
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_nom'] = $user['nom'];
        $_SESSION['user_prenom'] = $user['prenom'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_username'] = $user['username'];

        // Mettre à jour la dernière connexion
        $update_sql = "UPDATE users SET derniere_connexion = NOW() WHERE id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$user['id']]);

        return true;
    }

    return false;
}

// Déconnecter l'utilisateur
function logoutUser() {
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}

// Obtenir les informations de l'utilisateur connecté
function getCurrentUser() {
    if (!isLoggedIn()) return null;

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['user_username'],
        'role' => $_SESSION['user_role'],
        'nom' => $_SESSION['user_nom'],
        'prenom' => $_SESSION['user_prenom'],
        'email' => $_SESSION['user_email']
    ];
}

// Créer un utilisateur
function createUser($data) {
    global $pdo;

    $sql = "INSERT INTO users (username, email, password, role, nom, prenom, telephone)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['username'],
        $data['email'],
        $hashed_password,
        $data['role'],
        $data['nom'],
        $data['prenom'],
        $data['telephone'] ?? null
    ]);
}

// Modifier le mot de passe
function changePassword($user_id, $new_password) {
    global $pdo;

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $sql = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$hashed_password, $user_id]);
}

// Vérifier la force du mot de passe
function isStrongPassword($password) {
    return strlen($password) >= 8 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/[0-9]/', $password);
}

// Obtenir tous les utilisateurs
function getAllUsers() {
    global $pdo;

    $sql = "SELECT id, username, email, role, nom, prenom, telephone, actif, date_creation
            FROM users
            ORDER BY nom, prenom";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Obtenir les utilisateurs par rôle
function getUsersByRole($role) {
    global $pdo;

    $sql = "SELECT id, username, email, nom, prenom, telephone, role
            FROM users
            WHERE role = ? AND actif = 1
            ORDER BY nom, prenom";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$role]);
    return $stmt->fetchAll();
}

// Activer/désactiver un utilisateur
function toggleUserStatus($user_id) {
    global $pdo;

    $sql = "UPDATE users SET actif = 1 - actif WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$user_id]);
}

// ============================================
// SYSTEME DE PERMISSIONS GRANULAIRES
// ============================================

/**
 * Vérifier si l'utilisateur connecté a une permission spécifique
 * Les admins ont automatiquement toutes les permissions
 *
 * @param string $permission_code Code de la permission (ex: 'dossiers.create')
 * @return bool
 */
function hasPermission($permission_code) {
    if (!isLoggedIn()) {
        return false;
    }

    // Les admins ont toutes les permissions automatiquement
    if ($_SESSION['user_role'] === 'admin') {
        return true;
    }

    return userHasPermission($_SESSION['user_id'], $permission_code);
}

/**
 * Vérifier si un utilisateur a une permission (query DB)
 *
 * @param int $user_id ID de l'utilisateur
 * @param string $permission_code Code de la permission
 * @return bool
 */
function userHasPermission($user_id, $permission_code) {
    global $pdo;

    $sql = "SELECT COUNT(*) as count
            FROM user_permissions up
            INNER JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ? AND p.code = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $permission_code]);
    $result = $stmt->fetch();

    return $result['count'] > 0;
}

/**
 * Vérifier si l'utilisateur a au moins une des permissions listées (OU logique)
 *
 * @param array $permission_codes Tableau de codes de permissions
 * @return bool
 */
function hasAnyPermission($permission_codes) {
    if (!isLoggedIn()) {
        return false;
    }

    // Les admins ont toutes les permissions
    if ($_SESSION['user_role'] === 'admin') {
        return true;
    }

    foreach ($permission_codes as $code) {
        if (userHasPermission($_SESSION['user_id'], $code)) {
            return true;
        }
    }

    return false;
}

/**
 * Vérifier si l'utilisateur a toutes les permissions listées (ET logique)
 *
 * @param array $permission_codes Tableau de codes de permissions
 * @return bool
 */
function hasAllPermissions($permission_codes) {
    if (!isLoggedIn()) {
        return false;
    }

    // Les admins ont toutes les permissions
    if ($_SESSION['user_role'] === 'admin') {
        return true;
    }

    foreach ($permission_codes as $code) {
        if (!userHasPermission($_SESSION['user_id'], $code)) {
            return false;
        }
    }

    return true;
}

/**
 * Forcer une permission (rediriger si l'utilisateur ne l'a pas)
 *
 * @param string $permission_code Code de la permission requise
 * @param string $redirect_url URL de redirection (par défaut: dashboard)
 */
function requirePermission($permission_code, $redirect_url = null) {
    requireLogin();

    if (!hasPermission($permission_code)) {
        $url = $redirect_url ?? url('dashboard.php');
        redirect($url, 'Vous n\'avez pas la permission nécessaire pour accéder à cette page', 'error');
    }
}

/**
 * Forcer au moins une des permissions (rediriger si aucune)
 *
 * @param array $permission_codes Tableau de codes de permissions
 * @param string $redirect_url URL de redirection (par défaut: dashboard)
 */
function requireAnyPermission($permission_codes, $redirect_url = null) {
    requireLogin();

    if (!hasAnyPermission($permission_codes)) {
        $url = $redirect_url ?? url('dashboard.php');
        redirect($url, 'Vous n\'avez pas les permissions nécessaires pour accéder à cette page', 'error');
    }
}
?>
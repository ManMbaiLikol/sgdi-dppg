<?php
// AJAX - Supprimer définitivement un utilisateur
require_once '../../../includes/auth.php';
require_once '../functions.php';

// Vérifier les permissions
requireRole('admin');

// Vérifier que c'est une requête AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    exit;
}

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);

// Vérifier le token CSRF
if (!verifyCSRFToken($input['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token de sécurité invalide']);
    exit;
}

$user_id = intval($input['user_id'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'ID utilisateur manquant']);
    exit;
}

// Vérifier que l'utilisateur existe
$user = getUserById($user_id);
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
    exit;
}

// Empêcher l'auto-suppression
if ($user_id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas supprimer votre propre compte']);
    exit;
}

// Supprimer l'utilisateur
try {
    $success = deleteUser($user_id);

    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => "Utilisateur {$user['nom']} {$user['prenom']} supprimé définitivement"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression de l\'utilisateur']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>

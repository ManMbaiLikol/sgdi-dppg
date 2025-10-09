<?php
// AJAX - Récupérer le contenu d'un email
require_once '../../../includes/auth.php';

// Seuls les administrateurs
requireRole('admin');

header('Content-Type: application/json');

$email_id = intval($_GET['id'] ?? 0);

if (!$email_id) {
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
    exit;
}

try {
    $sql = "SELECT id, destinataire, sujet, corps, statut,
            DATE_FORMAT(date_envoi, '%d/%m/%Y à %H:%i') as date_envoi
            FROM email_logs WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email_id]);
    $email = $stmt->fetch();

    if ($email) {
        echo json_encode([
            'success' => true,
            'email' => $email
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Email introuvable'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}

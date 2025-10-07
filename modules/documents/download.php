<?php
// Téléchargement de documents - SGDI MVP
require_once '../../includes/auth.php';
require_once '../dossiers/functions.php';

requireLogin();

$document_id = intval($_GET['id'] ?? 0);
if (!$document_id) {
    redirect(url('dashboard.php'), 'Document non spécifié', 'error');
}

// Récupérer les informations du document
$sql = "SELECT d.*, dos.numero as dossier_numero, dos.user_id as dossier_owner
        FROM documents d
        JOIN dossiers dos ON d.dossier_id = dos.id
        WHERE d.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$document_id]);
$document = $stmt->fetch();

if (!$document) {
    redirect(url('dashboard.php'), 'Document introuvable', 'error');
}

// Vérifier les permissions d'accès
$can_access = false;
$user_role = $_SESSION['user_role'];

switch ($user_role) {
    case 'admin':
        $can_access = true;
        break;

    case 'chef_service':
        $can_access = true; // Chef service peut voir tous les documents
        break;

    case 'directeur':
        $can_access = true; // Directeur peut voir tous les documents
        break;

    case 'cadre_daj':
        $can_access = true; // DAJ peut voir les documents pour analyse
        break;

    case 'cadre_dppg':
        // Cadre DPPG peut voir tous les documents pour inspection
        $can_access = true;
        break;

    case 'billeteur':
        // Billeteur peut voir les documents des dossiers payés
        $sql_check = "SELECT statut FROM dossiers WHERE id = ?";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$document['dossier_id']]);
        $dossier_statut = $stmt_check->fetchColumn();
        $can_access = in_array($dossier_statut, ['paye', 'analyse_daj', 'inspecte', 'valide', 'decide']);
        break;

    default:
        // Créateur du dossier peut voir ses documents
        $can_access = ($document['dossier_owner'] == $_SESSION['user_id']);
}

if (!$can_access) {
    redirect(url('dashboard.php'), 'Vous n\'avez pas les permissions pour télécharger ce document', 'error');
}

// Construire le chemin complet du fichier
$chemin_fichier = $document['chemin_fichier'];

// Déterminer le chemin correct selon le format stocké
if (strpos($chemin_fichier, '/') === 0) {
    // Chemin absolu (ancien format) - probablement dans assets/uploads/
    $file_path = '../../assets/uploads' . $chemin_fichier;
} else {
    // Chemin relatif (nouveau format)
    $file_path = '../../' . $chemin_fichier;
}

// Si le fichier n'existe toujours pas, essayer d'autres emplacements
if (!file_exists($file_path)) {
    // Essayer directement le chemin stocké
    $file_path = '../../' . ltrim($chemin_fichier, '/');

    if (!file_exists($file_path)) {
        // Essayer sans le préfixe ../../
        $file_path = $chemin_fichier;

        if (!file_exists($file_path)) {
            redirect(url('dashboard.php'), 'Fichier non trouvé sur le serveur. Chemin: ' . $chemin_fichier, 'error');
        }
    }
}

// Enregistrer le téléchargement dans les logs (optionnel)
$sql = "INSERT INTO logs_activite (user_id, action, description, dossier_id)
        VALUES (?, 'download_document', ?, ?)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    $_SESSION['user_id'],
    'Téléchargement du document: ' . $document['nom_original'] . ' (' . $document['type_document'] . ')',
    $document['dossier_id']
]);

// Headers pour le téléchargement
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $document['nom_original'] . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Envoyer le fichier
readfile($file_path);
exit;
?>
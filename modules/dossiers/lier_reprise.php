<?php
/**
 * Lien entre un dossier de reprise et la station reprise (depuis la fiche du dossier)
 * POST action=lier (station_id) | delier
 */
require_once '../../includes/auth.php';
require_once '../../includes/reprise_functions.php';

requireLogin();
if (!hasAnyRole(['chef_service', 'admin'])) {
    redirect(url('dashboard.php'), 'Action réservée au Chef de Service', 'error');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('modules/dossiers/list.php'));
}

$dossier_id = (int) ($_POST['dossier_id'] ?? 0);
$retour = url('modules/dossiers/view.php?id=' . $dossier_id . '#reprise');
exigerCSRF($retour);

if (!repriseDisponible()) {
    redirect($retour, 'La gestion des reprises n\'est pas encore activée (migration à appliquer).', 'error');
}

$stmt = $pdo->prepare("SELECT statut, sous_type FROM dossiers WHERE id = ?");
$stmt->execute([$dossier_id]);
$dossier = $stmt->fetch();
if (!$dossier || $dossier['sous_type'] !== 'reprise' || in_array($dossier['statut'], ['autorise', 'rejete', 'repris'], true)) {
    redirect($retour, 'Le lien de reprise ne peut plus être modifié pour ce dossier.', 'error');
}

if (($_POST['action'] ?? '') === 'delier') {
    repriseDelier($dossier_id, $_SESSION['user_id'])
        ? redirect($retour, 'Lien avec la station reprise retiré.', 'success')
        : redirect($retour, 'Aucun lien à retirer.', 'warning');
}

$station = repriseLier($dossier_id, (int) ($_POST['station_id'] ?? 0), $_SESSION['user_id']);
$station
    ? redirect($retour, 'Dossier lié à la station ' . $station['numero'] . ' (' . $station['nom_demandeur'] . ').', 'success')
    : redirect($retour, 'Cette station ne peut pas être reprise par ce dossier.', 'error');

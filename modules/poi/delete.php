<?php
// Désactivation d'un point d'intérêt - SGDI
require_once '../../includes/auth.php';
require_once '../../includes/contraintes_distance_functions.php';

requireLogin();

// Vérifier les permissions (admin uniquement)
if ($_SESSION['user_role'] !== 'admin') {
    redirect(url('dashboard.php'), 'Accès réservé à l\'administrateur uniquement', 'error');
}

$poi_id = intval($_GET['id'] ?? 0);

if (!$poi_id) {
    redirect(url('modules/poi/index.php'), 'POI non spécifié', 'error');
}

// Désactiver le POI
if (desactiverPOI($poi_id, $_SESSION['user_id'])) {
    redirect(url('modules/poi/index.php'), 'Point d\'intérêt désactivé avec succès', 'success');
} else {
    redirect(url('modules/poi/index.php'), 'Erreur lors de la désactivation', 'error');
}
?>

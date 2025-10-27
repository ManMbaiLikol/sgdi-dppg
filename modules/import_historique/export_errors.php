<?php
// Export des erreurs de validation - SGDI
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

// Vérifier les permissions
if (!peutImporterHistorique($_SESSION['user_role'])) {
    redirect(url('dashboard.php'), 'Vous n\'avez pas les permissions pour accéder à ce module', 'error');
}

// Récupérer les erreurs de la session
if (!isset($_SESSION['import_preview']['erreurs'])) {
    redirect(url('modules/import_historique/index.php'), 'Aucune erreur à exporter', 'error');
}

$erreurs = $_SESSION['import_preview']['erreurs'];
$filename = 'erreurs_import_' . date('Y-m-d_H-i-s') . '.txt';

// Headers pour téléchargement
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Contenu du fichier
echo "RAPPORT D'ERREURS - IMPORT DOSSIERS HISTORIQUES\n";
echo "================================================\n\n";
echo "Date : " . date('d/m/Y H:i:s') . "\n";
echo "Utilisateur : " . $_SESSION['user_name'] . "\n";
echo "Nombre d'erreurs : " . count($erreurs) . "\n\n";
echo "DÉTAIL DES ERREURS\n";
echo "==================\n\n";

foreach ($erreurs as $index => $erreur) {
    echo ($index + 1) . ". " . $erreur . "\n";
}

echo "\n\n";
echo "RECOMMANDATIONS\n";
echo "===============\n\n";
echo "1. Vérifiez que les noms de types d'infrastructure correspondent exactement à la liste valide\n";
echo "2. Assurez-vous que les régions sont bien orthographiées\n";
echo "3. Utilisez le format de date JJ/MM/AAAA (ex: 15/03/2015)\n";
echo "4. Pour les points consommateurs, l'entreprise bénéficiaire est obligatoire\n";
echo "5. Vérifiez les coordonnées GPS (latitude: -90 à 90, longitude: -180 à 180)\n\n";
echo "Consultez le guide d'utilisation pour plus d'informations.\n";
echo "\n---\nSGDI - Système de Gestion des Dossiers d'Implantation - MINEE/DPPG\n";

exit;

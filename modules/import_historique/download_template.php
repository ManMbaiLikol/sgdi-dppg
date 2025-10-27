<?php
// Téléchargement des templates d'import - SGDI
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

// Vérifier les permissions
if (!peutImporterHistorique($_SESSION['user_role'])) {
    redirect(url('dashboard.php'), 'Vous n\'avez pas les permissions pour accéder à ce module', 'error');
}

$type = $_GET['type'] ?? 'stations';

$templates = [
    'stations' => [
        'file' => 'template_import_stations_service.csv',
        'name' => 'Template_Import_Stations_Service.csv'
    ],
    'points_conso' => [
        'file' => 'template_import_points_consommateurs.csv',
        'name' => 'Template_Import_Points_Consommateurs.csv'
    ]
];

if (!isset($templates[$type])) {
    redirect(url('modules/import_historique/index.php'), 'Template non trouvé', 'error');
}

$template = $templates[$type];
$filepath = __DIR__ . '/templates/' . $template['file'];

if (!file_exists($filepath)) {
    redirect(url('modules/import_historique/index.php'), 'Fichier template introuvable', 'error');
}

// Télécharger le fichier
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $template['name'] . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Ajouter le BOM UTF-8 pour Excel
echo "\xEF\xBB\xBF";

readfile($filepath);
exit;

<?php
// Import d'un seul dossier (AJAX) - SGDI
require_once '../../includes/auth.php';
require_once 'functions.php';

header('Content-Type: application/json');

requireLogin();

// Vérifier les permissions
if (!peutImporterHistorique($_SESSION['user_role'])) {
    echo json_encode(['success' => false, 'error' => 'Permission refusée']);
    exit;
}

// Vérifier le token CSRF
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Token invalide']);
    exit;
}

// Récupérer les données
$data = json_decode($_POST['data'] ?? '{}', true);
$source = $_POST['source'] ?? 'Import';

if (empty($data)) {
    echo json_encode(['success' => false, 'error' => 'Données manquantes']);
    exit;
}

// Ajouter la source à data
$data['source_import'] = $source;

// Importer le dossier
$result = insererDossierHistorique($data, $_SESSION['user_id']);

echo json_encode($result);

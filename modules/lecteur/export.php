<?php
// Export des résultats de recherche - Lecteur Public
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('lecteur');

global $pdo;

// Récupérer les mêmes filtres que la recherche
$numero = sanitize($_GET['numero'] ?? '');
$type = sanitize($_GET['type_infrastructure'] ?? '');
$region = sanitize($_GET['region'] ?? '');
$demandeur = sanitize($_GET['demandeur'] ?? '');
$statut = sanitize($_GET['statut'] ?? '');
$date_debut = sanitize($_GET['date_debut'] ?? '');
$date_fin = sanitize($_GET['date_fin'] ?? '');

// Vérifier si au moins un critère est renseigné
$has_criteria = !empty($numero) || !empty($type) || !empty($region) || !empty($demandeur) || !empty($statut) || !empty($date_debut) || !empty($date_fin);

if (!$has_criteria) {
    redirect(url('modules/lecteur/recherche.php'), 'Veuillez saisir au moins un critère de recherche avant d\'exporter.', 'error');
    exit;
}

// Construction de la requête - inclure TOUS les statuts
$sql = "SELECT d.*,
        DATE_FORMAT(d.date_modification, '%d/%m/%Y') as date_format,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format
        FROM dossiers d
        WHERE 1=1";

$params = [];

if (!empty($numero)) {
    $sql .= " AND d.numero LIKE ?";
    $params[] = "%$numero%";
}

if (!empty($type)) {
    $sql .= " AND d.type_infrastructure = ?";
    $params[] = $type;
}

if (!empty($region)) {
    $sql .= " AND d.region LIKE ?";
    $params[] = "%$region%";
}

if (!empty($demandeur)) {
    $sql .= " AND (d.nom_demandeur LIKE ? OR d.operateur_proprietaire LIKE ?)";
    $params[] = "%$demandeur%";
    $params[] = "%$demandeur%";
}

if (!empty($statut)) {
    $sql .= " AND d.statut = ?";
    $params[] = $statut;
}

if (!empty($date_debut)) {
    $sql .= " AND d.date_creation >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $sql .= " AND d.date_creation <= ?";
    $params[] = $date_fin;
}

$sql .= " ORDER BY d.date_creation DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resultats = $stmt->fetchAll();

// Définir les en-têtes pour le téléchargement CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="registre_public_' . date('Y-m-d') . '.csv"');

// Créer le flux de sortie
$output = fopen('php://output', 'w');

// Ajouter le BOM UTF-8 pour Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// En-têtes du CSV
fputcsv($output, [
    'N° Dossier',
    'Type Infrastructure',
    'Demandeur/Opérateur',
    'Localisation',
    'Région',
    'Département',
    'Statut Actuel',
    'Date Création',
    'Latitude',
    'Longitude'
], ';');

// Données
foreach ($resultats as $row) {
    // Extraire lat/lon depuis coordonnees_gps
    $coords = explode(',', $row['coordonnees_gps'] ?? '');
    $latitude = isset($coords[0]) ? trim($coords[0]) : '';
    $longitude = isset($coords[1]) ? trim($coords[1]) : '';

    fputcsv($output, [
        $row['numero'],
        getTypeInfrastructureLabel($row['type_infrastructure']),
        $row['operateur_proprietaire'] ?? $row['nom_demandeur'],
        $row['lieu_dit'] ?? ($row['quartier'] . ', ' . $row['ville']),
        $row['region'],
        $row['departement'],
        getStatutLabel($row['statut']),
        $row['date_creation_format'],
        $latitude,
        $longitude
    ], ';');
}

fclose($output);
exit;
?>

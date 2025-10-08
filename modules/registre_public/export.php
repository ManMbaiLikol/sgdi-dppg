<?php
// Export Excel du registre public
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../modules/dossiers/functions.php';

// Récupérer les filtres de la requête
$search = sanitize($_GET['search'] ?? '');
$type_infrastructure = sanitize($_GET['type_infrastructure'] ?? '');
$region = sanitize($_GET['region'] ?? '');
$ville = sanitize($_GET['ville'] ?? '');
$statut = sanitize($_GET['statut'] ?? 'autorise');
$annee = sanitize($_GET['annee'] ?? '');

// Construction de la requête
$sql = "SELECT d.numero, d.type_infrastructure, d.sous_type, d.nom_demandeur,
        d.region, d.ville, d.adresse_precise,
        d.operateur_proprietaire, d.entreprise_beneficiaire, d.entreprise_installatrice,
        d.statut, dec.decision, dec.reference_decision,
        DATE_FORMAT(dec.date_decision, '%d/%m/%Y') as date_decision,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation
        FROM dossiers d
        LEFT JOIN decisions dec ON d.id = dec.dossier_id
        WHERE d.statut IN ('autorise', 'refuse', 'ferme')";

$params = [];

if ($search) {
    $sql .= " AND (d.numero LIKE :search OR d.nom_demandeur LIKE :search OR d.operateur_proprietaire LIKE :search OR d.ville LIKE :search)";
    $params['search'] = "%$search%";
}

if ($type_infrastructure) {
    $sql .= " AND d.type_infrastructure = :type";
    $params['type'] = $type_infrastructure;
}

if ($region) {
    $sql .= " AND d.region = :region";
    $params['region'] = $region;
}

if ($ville) {
    $sql .= " AND d.ville = :ville";
    $params['ville'] = $ville;
}

if ($statut) {
    $sql .= " AND d.statut = :statut";
    $params['statut'] = $statut;
}

if ($annee) {
    $sql .= " AND YEAR(dec.date_decision) = :annee";
    $params['annee'] = $annee;
}

$sql .= " ORDER BY dec.date_decision DESC, d.numero DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dossiers = $stmt->fetchAll();

// Générer le fichier CSV (compatible Excel)
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="registre_public_infrastructures_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM UTF-8 pour Excel
echo "\xEF\xBB\xBF";

// En-têtes
$headers = [
    'N° Dossier',
    'Type Infrastructure',
    'Sous-type',
    'Nom/Raison sociale',
    'Région',
    'Ville',
    'Adresse',
    'Opérateur propriétaire',
    'Entreprise bénéficiaire',
    'Entreprise installatrice',
    'Statut',
    'Décision',
    'Référence décision',
    'Date décision',
    'Date dépôt'
];

$output = fopen('php://output', 'w');
fputcsv($output, $headers, ';');

// Données
foreach ($dossiers as $d) {
    $row = [
        $d['numero'],
        getTypeInfrastructureLabel($d['type_infrastructure']),
        ucfirst($d['sous_type']),
        $d['nom_demandeur'],
        $d['region'] ?? '-',
        $d['ville'] ?? '-',
        $d['adresse_precise'] ?? '-',
        $d['operateur_proprietaire'] ?? '-',
        $d['entreprise_beneficiaire'] ?? '-',
        $d['entreprise_installatrice'] ?? '-',
        strtoupper($d['statut']),
        $d['decision'] ? strtoupper($d['decision']) : '-',
        $d['reference_decision'] ?? '-',
        $d['date_decision'] ?? '-',
        $d['date_creation'] ?? '-'
    ];
    fputcsv($output, $row, ';');
}

fclose($output);
exit;

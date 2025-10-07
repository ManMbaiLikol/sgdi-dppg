<?php
// Export Excel des dossiers
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireLogin();

// Vérifier les permissions
if (!hasAnyRole(['chef_service', 'admin', 'directeur', 'billeteur'])) {
    die('Accès non autorisé');
}

// Récupérer les filtres
$statut = sanitize($_GET['statut'] ?? '');
$type_infrastructure = sanitize($_GET['type_infrastructure'] ?? '');
$region = sanitize($_GET['region'] ?? '');
$date_debut = sanitize($_GET['date_debut'] ?? '');
$date_fin = sanitize($_GET['date_fin'] ?? '');

// Construction de la requête
$sql = "SELECT d.*, u.nom as user_nom, u.prenom as user_prenom,
        dec.decision, dec.date_decision, dec.reference_decision,
        p.montant as montant_paye, p.date_paiement,
        i.conforme as inspection_conforme,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format,
        DATE_FORMAT(dec.date_decision, '%d/%m/%Y') as date_decision_format,
        DATE_FORMAT(p.date_paiement, '%d/%m/%Y') as date_paiement_format
        FROM dossiers d
        LEFT JOIN users u ON d.user_id = u.id
        LEFT JOIN decisions dec ON d.id = dec.dossier_id
        LEFT JOIN paiements p ON d.id = p.dossier_id
        LEFT JOIN inspections i ON d.id = i.dossier_id
        WHERE 1=1";

$params = [];

if ($statut) {
    $sql .= " AND d.statut = :statut";
    $params['statut'] = $statut;
}

if ($type_infrastructure) {
    $sql .= " AND d.type_infrastructure = :type";
    $params['type'] = $type_infrastructure;
}

if ($region) {
    $sql .= " AND d.region = :region";
    $params['region'] = $region;
}

if ($date_debut) {
    $sql .= " AND d.date_creation >= :date_debut";
    $params['date_debut'] = $date_debut;
}

if ($date_fin) {
    $sql .= " AND d.date_creation <= :date_fin";
    $params['date_fin'] = $date_fin . ' 23:59:59';
}

$sql .= " ORDER BY d.date_creation DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dossiers = $stmt->fetchAll();

// Générer le fichier CSV (compatible Excel)
$filename = 'export_dossiers_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
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
    'Contact demandeur',
    'Téléphone',
    'Email',
    'Région',
    'Ville',
    'Adresse',
    'Opérateur propriétaire',
    'Entreprise bénéficiaire',
    'Entreprise installatrice',
    'Statut',
    'Date création',
    'Créé par',
    'Montant payé',
    'Date paiement',
    'Inspection conforme',
    'Décision',
    'Date décision',
    'Référence décision'
];

$output = fopen('php://output', 'w');
fputcsv($output, $headers, ';');

// Données
foreach ($dossiers as $d) {
    $row = [
        $d['numero'],
        formatTypeInfrastructure($d['type_infrastructure']),
        ucfirst($d['sous_type']),
        $d['nom_demandeur'],
        $d['contact_demandeur'] ?? '-',
        $d['telephone_demandeur'] ?? '-',
        $d['email_demandeur'] ?? '-',
        $d['region'] ?? '-',
        $d['ville'] ?? '-',
        $d['adresse_precise'] ?? '-',
        $d['operateur_proprietaire'] ?? '-',
        $d['entreprise_beneficiaire'] ?? '-',
        $d['entreprise_installatrice'] ?? '-',
        strtoupper($d['statut']),
        $d['date_creation_format'],
        $d['user_prenom'] . ' ' . $d['user_nom'],
        $d['montant_paye'] ? number_format($d['montant_paye'], 0, ',', ' ') . ' FCFA' : '-',
        $d['date_paiement_format'] ?? '-',
        $d['inspection_conforme'] ? strtoupper($d['inspection_conforme']) : '-',
        $d['decision'] ? strtoupper($d['decision']) : '-',
        $d['date_decision_format'] ?? '-',
        $d['reference_decision'] ?? '-'
    ];
    fputcsv($output, $row, ';');
}

fclose($output);
exit;

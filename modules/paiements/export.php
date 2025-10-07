<?php
// Export CSV/Excel des paiements - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';
require_once '../dossiers/functions.php';

requireRole('billeteur');

$format = sanitize($_GET['format'] ?? 'csv');
$search = sanitize($_GET['search'] ?? '');

// Récupérer tous les paiements avec les informations détaillées
$sql = "SELECT d.numero, d.nom_demandeur, d.type_infrastructure, d.sous_type,
               CASE
                   WHEN d.type_infrastructure = 'station_service' THEN COALESCE(NULLIF(d.operateur_proprietaire, ''), d.nom_demandeur)
                   WHEN d.type_infrastructure = 'point_consommateur' THEN COALESCE(NULLIF(d.operateur_proprietaire, ''), d.nom_demandeur)
                   WHEN d.type_infrastructure = 'depot_gpl' THEN COALESCE(NULLIF(d.entreprise_installatrice, ''), d.nom_demandeur)
                   ELSE d.nom_demandeur
               END as operateur,
               p.montant, p.devise, p.mode_paiement, p.reference_paiement,
               p.date_paiement, p.date_enregistrement, p.observations,
               u.nom as billeteur_nom, u.prenom as billeteur_prenom,
               d.statut
        FROM dossiers d
        JOIN paiements p ON d.id = p.dossier_id
        JOIN users u ON p.billeteur_id = u.id
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND d.numero LIKE ?";
    $params[] = '%' . $search . '%';
}

$sql .= " ORDER BY p.date_enregistrement DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$paiements = $stmt->fetchAll();

if (empty($paiements)) {
    redirect(url('modules/paiements/list.php'), 'Aucune donnée à exporter avec les critères sélectionnés', 'warning');
}

$filename = 'export_paiements_' . date('Y-m-d_H-i-s');

if ($format === 'excel') {
    // Export Excel (HTML avec MIME type Excel)
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-type" content="text/html;charset=utf-8" /></head>';
    echo '<body>';
    echo '<table border="1">';
    echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
    echo '<th>Référence Dossier</th>';
    echo '<th>Opérateur</th>';
    echo '<th>Type Infrastructure</th>';
    echo '<th>Montant</th>';
    echo '<th>Devise</th>';
    echo '<th>Mode Paiement</th>';
    echo '<th>Référence Paiement</th>';
    echo '<th>Date Paiement</th>';
    echo '<th>Date Enregistrement</th>';
    echo '<th>Billeteur</th>';
    echo '<th>Statut</th>';
    echo '<th>Observations</th>';
    echo '</tr>';

    foreach ($paiements as $paiement) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($paiement['numero']) . '</td>';
        echo '<td>' . htmlspecialchars($paiement['operateur']) . '</td>';
        echo '<td>' . htmlspecialchars(getTypeLabel($paiement['type_infrastructure'], $paiement['sous_type'])) . '</td>';
        echo '<td style="text-align: right;">' . number_format($paiement['montant'], 0, ',', ' ') . '</td>';
        echo '<td>' . htmlspecialchars($paiement['devise']) . '</td>';
        echo '<td>' . htmlspecialchars(ucfirst($paiement['mode_paiement'])) . '</td>';
        echo '<td>' . htmlspecialchars($paiement['reference_paiement'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars(formatDate($paiement['date_paiement'])) . '</td>';
        echo '<td>' . htmlspecialchars(formatDateTime($paiement['date_enregistrement'])) . '</td>';
        echo '<td>' . htmlspecialchars($paiement['billeteur_prenom'] . ' ' . $paiement['billeteur_nom']) . '</td>';
        echo '<td>' . htmlspecialchars(getStatutLabel($paiement['statut'])) . '</td>';
        echo '<td>' . htmlspecialchars($paiement['observations'] ?? '') . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '</body></html>';

} else {
    // Export CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');

    // BOM pour UTF-8 (pour Excel)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // En-têtes
    fputcsv($output, [
        'Référence Dossier',
        'Opérateur',
        'Type Infrastructure',
        'Montant',
        'Devise',
        'Mode Paiement',
        'Référence Paiement',
        'Date Paiement',
        'Date Enregistrement',
        'Billeteur',
        'Statut',
        'Observations'
    ], ';');

    // Données
    foreach ($paiements as $paiement) {
        fputcsv($output, [
            $paiement['numero'],
            $paiement['operateur'],
            getTypeLabel($paiement['type_infrastructure'], $paiement['sous_type']),
            number_format($paiement['montant'], 2, ',', ' '),
            $paiement['devise'],
            ucfirst($paiement['mode_paiement']),
            $paiement['reference_paiement'] ?? '',
            formatDate($paiement['date_paiement']),
            formatDateTime($paiement['date_enregistrement']),
            $paiement['billeteur_prenom'] . ' ' . $paiement['billeteur_nom'],
            getStatutLabel($paiement['statut']),
            $paiement['observations'] ?? ''
        ], ';');
    }

    fclose($output);
}

exit;
?>
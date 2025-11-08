<?php
// Export Excel du registre public
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../modules/dossiers/functions.php';

// Activer l'affichage des erreurs pour le debug (désactiver en production)
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

try {
    // Récupérer les filtres de la requête (utiliser cleanInput au lieu de sanitize pour éviter l'échappement HTML)
    $search = cleanInput($_GET['search'] ?? '');
    $type_infrastructure = cleanInput($_GET['type_infrastructure'] ?? '');
    $region = cleanInput($_GET['region'] ?? '');
    $ville = cleanInput($_GET['ville'] ?? '');
    $statut = cleanInput($_GET['statut'] ?? 'autorise');
    $annee = cleanInput($_GET['annee'] ?? '');

    // Vérifier quelle table de décisions existe
    $table_decisions = null;

    // Vérifier decisions_ministerielle
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'decisions_ministerielle'");
    if ($stmt_check->rowCount() > 0) {
        $table_decisions = 'decisions_ministerielle';
    } else {
        // Vérifier decisions
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'decisions'");
        if ($stmt_check->rowCount() > 0) {
            $table_decisions = 'decisions';
        }
    }

    // Construction de la requête selon la disponibilité de la table decisions
    if ($table_decisions !== null) {
        // IMPORTANT: Utiliser backticks pour protéger le nom de table (safe car vient de SHOW TABLES)
        $sql = "SELECT d.numero, d.type_infrastructure, d.sous_type, d.nom_demandeur,
                d.region, d.ville, d.adresse_precise,
                d.operateur_proprietaire, d.entreprise_beneficiaire, d.entreprise_installatrice,
                d.statut, dec.decision, dec.reference_decision,
                DATE_FORMAT(dec.date_decision, '%d/%m/%Y') as date_decision,
                DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation
                FROM dossiers d
                LEFT JOIN `" . $table_decisions . "` dec ON d.id = dec.dossier_id
                WHERE d.statut IN ('autorise', 'refuse', 'ferme', 'historique_autorise')";
    } else {
        // Sans table de décisions
        $sql = "SELECT d.numero, d.type_infrastructure, d.sous_type, d.nom_demandeur,
                d.region, d.ville, d.adresse_precise,
                d.operateur_proprietaire, d.entreprise_beneficiaire, d.entreprise_installatrice,
                d.statut,
                NULL as decision, NULL as reference_decision,
                NULL as date_decision,
                DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation
                FROM dossiers d
                WHERE d.statut IN ('autorise', 'refuse', 'ferme', 'historique_autorise')";
    }

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
        if ($table_decisions !== null) {
            $sql .= " AND YEAR(dec.date_decision) = :annee";
            $params['annee'] = $annee;
        } else {
            $sql .= " AND YEAR(d.date_creation) = :annee";
            $params['annee'] = $annee;
        }
    }

    $sql .= ($table_decisions !== null) ? " ORDER BY dec.date_decision DESC, d.numero DESC" : " ORDER BY d.date_creation DESC, d.numero DESC";

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
            $d['numero'] ?? '-',
            getTypeInfrastructureLabel($d['type_infrastructure'] ?? ''),
            ucfirst($d['sous_type'] ?? ''),
            $d['nom_demandeur'] ?? '-',
            $d['region'] ?? '-',
            $d['ville'] ?? '-',
            $d['adresse_precise'] ?? '-',
            $d['operateur_proprietaire'] ?? '-',
            $d['entreprise_beneficiaire'] ?? '-',
            $d['entreprise_installatrice'] ?? '-',
            strtoupper($d['statut'] ?? ''),
            $d['decision'] ? strtoupper($d['decision']) : '-',
            $d['reference_decision'] ?? '-',
            $d['date_decision'] ?? '-',
            $d['date_creation'] ?? '-'
        ];
        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    // Erreur de base de données
    error_log("Erreur export registre public (DB): " . $e->getMessage());
    http_response_code(500);
    die("Erreur lors de l'export des données. Veuillez réessayer ou contacter l'administrateur.");
} catch (Exception $e) {
    // Autre erreur
    error_log("Erreur export registre public: " . $e->getMessage());
    http_response_code(500);
    die("Erreur lors de l'export des données. Veuillez réessayer ou contacter l'administrateur.");
}

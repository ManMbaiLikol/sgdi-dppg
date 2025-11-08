<?php
// Script de debug pour export registre public
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../modules/dossiers/functions.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "=== DEBUG EXPORT REGISTRE PUBLIC ===\n\n";

try {
    // Vérifier les tables existantes
    echo "1. Vérification des tables de décisions :\n";

    $stmt_check = $pdo->query("SHOW TABLES");
    $tables = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables dans la base : " . implode(', ', $tables) . "\n\n";

    // Vérifier decisions_ministerielle
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'decisions_ministerielle'");
    echo "decisions_ministerielle existe : " . ($stmt_check->rowCount() > 0 ? 'OUI' : 'NON') . "\n";

    // Vérifier decisions
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'decisions'");
    echo "decisions existe : " . ($stmt_check->rowCount() > 0 ? 'OUI' : 'NON') . "\n\n";

    // Déterminer quelle table utiliser
    $table_decisions = null;

    $stmt_check = $pdo->query("SHOW TABLES LIKE 'decisions_ministerielle'");
    if ($stmt_check->rowCount() > 0) {
        $table_decisions = 'decisions_ministerielle';
    } else {
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'decisions'");
        if ($stmt_check->rowCount() > 0) {
            $table_decisions = 'decisions';
        }
    }

    echo "2. Table de décisions sélectionnée : " . ($table_decisions ?? 'AUCUNE') . "\n\n";

    // Construire la requête
    if ($table_decisions !== null) {
        $sql = "SELECT d.numero, d.type_infrastructure, d.sous_type, d.nom_demandeur,
                d.region, d.ville, d.adresse_precise,
                d.operateur_proprietaire, d.entreprise_beneficiaire, d.entreprise_installatrice,
                d.statut, decision_info.decision, decision_info.reference_decision,
                DATE_FORMAT(decision_info.date_decision, '%d/%m/%Y') as date_decision,
                DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation
                FROM dossiers d
                LEFT JOIN `" . $table_decisions . "` AS decision_info ON d.id = decision_info.dossier_id
                WHERE d.statut IN ('autorise', 'refuse', 'ferme', 'historique_autorise')";
    } else {
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

    echo "3. Requête SQL générée :\n";
    echo str_repeat('-', 80) . "\n";
    echo $sql . "\n";
    echo str_repeat('-', 80) . "\n\n";

    // Tester l'exécution
    echo "4. Test d'exécution de la requête :\n";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $dossiers = $stmt->fetchAll();

    echo "Nombre de dossiers trouvés : " . count($dossiers) . "\n\n";

    if (count($dossiers) > 0) {
        echo "5. Premier dossier (exemple) :\n";
        print_r($dossiers[0]);
    }

    echo "\n=== FIN DEBUG - SUCCÈS ===\n";

} catch (PDOException $e) {
    echo "\n!!! ERREUR PDO !!!\n";
    echo "Message : " . $e->getMessage() . "\n";
    echo "Code : " . $e->getCode() . "\n";
    echo "Fichier : " . $e->getFile() . ":" . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "\n!!! ERREUR GÉNÉRALE !!!\n";
    echo "Message : " . $e->getMessage() . "\n";
    echo "Code : " . $e->getCode() . "\n";
}

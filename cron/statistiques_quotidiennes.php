<?php
/**
 * Script CRON pour générer les statistiques quotidiennes
 * À exécuter tous les jours à 8h du matin
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/email_functions.php';

$log_file = ROOT_PATH . '/logs/stats_' . date('Y-m') . '.log';
$log_dir = dirname($log_file);

if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== Génération des statistiques quotidiennes ===");

try {
    global $pdo;

    // 1. Statistiques générales
    $stats = [];

    // Dossiers créés hier
    $sql = "SELECT COUNT(*) FROM dossiers WHERE DATE(date_creation) = CURDATE() - INTERVAL 1 DAY";
    $stats['dossiers_crees_hier'] = $pdo->query($sql)->fetchColumn();

    // Paiements enregistrés hier
    $sql = "SELECT COUNT(*), COALESCE(SUM(montant), 0) FROM paiements
            WHERE DATE(date_paiement) = CURDATE() - INTERVAL 1 DAY";
    $stmt = $pdo->query($sql);
    $row = $stmt->fetch();
    $stats['paiements_hier'] = $row[0];
    $stats['montant_paiements_hier'] = $row[1];

    // Décisions prises hier
    $sql = "SELECT COUNT(*) FROM decisions WHERE DATE(date_decision) = CURDATE() - INTERVAL 1 DAY";
    $stats['decisions_hier'] = $pdo->query($sql)->fetchColumn();

    // Dossiers par statut
    $sql = "SELECT statut, COUNT(*) as nb FROM dossiers GROUP BY statut";
    $stmt = $pdo->query($sql);
    $stats['par_statut'] = [];
    while ($row = $stmt->fetch()) {
        $stats['par_statut'][$row['statut']] = $row['nb'];
    }

    // Temps moyen de traitement (du paiement à la décision)
    $sql = "SELECT AVG(DATEDIFF(dec.date_decision, p.date_paiement)) as duree_moyenne
            FROM decisions dec
            INNER JOIN dossiers d ON dec.dossier_id = d.id
            INNER JOIN paiements p ON d.id = p.dossier_id
            WHERE dec.date_decision >= CURDATE() - INTERVAL 30 DAY";
    $stats['duree_moyenne_traitement'] = round($pdo->query($sql)->fetchColumn() ?? 0, 1);

    // Taux d'approbation
    $sql = "SELECT
            SUM(CASE WHEN decision = 'approuve' THEN 1 ELSE 0 END) as approuves,
            SUM(CASE WHEN decision = 'rejete' THEN 1 ELSE 0 END) as rejetes,
            COUNT(*) as total
            FROM decisions
            WHERE date_decision >= CURDATE() - INTERVAL 30 DAY";
    $row = $pdo->query($sql)->fetch();
    if ($row['total'] > 0) {
        $stats['taux_approbation'] = round(($row['approuves'] / $row['total']) * 100, 1);
    } else {
        $stats['taux_approbation'] = 0;
    }

    logMessage("Statistiques collectées:");
    logMessage("- Dossiers créés hier: " . $stats['dossiers_crees_hier']);
    logMessage("- Paiements hier: " . $stats['paiements_hier'] . " (" . number_format($stats['montant_paiements_hier'], 0, ',', ' ') . " FCFA)");
    logMessage("- Décisions hier: " . $stats['decisions_hier']);
    logMessage("- Durée moyenne traitement: " . $stats['duree_moyenne_traitement'] . " jours");
    logMessage("- Taux d'approbation (30j): " . $stats['taux_approbation'] . "%");

    // 2. Sauvegarder dans une table de statistiques
    $sql = "INSERT INTO statistiques_quotidiennes
            (date, dossiers_crees, paiements, montant_paiements, decisions,
             duree_moyenne_traitement, taux_approbation, data_json)
            VALUES (CURDATE() - INTERVAL 1 DAY, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            dossiers_crees = VALUES(dossiers_crees),
            paiements = VALUES(paiements),
            montant_paiements = VALUES(montant_paiements),
            decisions = VALUES(decisions),
            duree_moyenne_traitement = VALUES(duree_moyenne_traitement),
            taux_approbation = VALUES(taux_approbation),
            data_json = VALUES(data_json)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $stats['dossiers_crees_hier'],
        $stats['paiements_hier'],
        $stats['montant_paiements_hier'],
        $stats['decisions_hier'],
        $stats['duree_moyenne_traitement'],
        $stats['taux_approbation'],
        json_encode($stats)
    ]);

    logMessage("Statistiques sauvegardées dans la base");

    // 3. Envoyer un email récapitulatif aux admins (uniquement le lundi)
    if (date('N') == 1) { // Lundi
        envoyerRapportHebdomadaire($stats);
        logMessage("Rapport hebdomadaire envoyé");
    }

    logMessage("=== Génération terminée avec succès ===\n");
    exit(0);

} catch (Exception $e) {
    logMessage("ERREUR: " . $e->getMessage());
    logMessage("=== Génération terminée avec erreur ===\n");
    exit(1);
}

function envoyerRapportHebdomadaire($stats) {
    global $pdo;

    // Récupérer les emails des admins
    $sql = "SELECT email FROM users WHERE role = 'admin' AND actif = 1";
    $emails = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

    if (empty($emails)) return;

    $subject = "SGDI - Rapport hebdomadaire - Semaine du " . date('d/m/Y', strtotime('last monday'));

    $message = "Bonjour,\n\n";
    $message .= "Voici le rapport hebdomadaire du système SGDI.\n\n";
    $message .= "=== ACTIVITÉ DE LA SEMAINE ===\n";
    $message .= "Nouveaux dossiers: " . $stats['dossiers_crees_hier'] . "\n";
    $message .= "Paiements enregistrés: " . $stats['paiements_hier'] . "\n";
    $message .= "Montant total: " . number_format($stats['montant_paiements_hier'], 0, ',', ' ') . " FCFA\n";
    $message .= "Décisions prises: " . $stats['decisions_hier'] . "\n\n";
    $message .= "=== PERFORMANCES ===\n";
    $message .= "Durée moyenne de traitement: " . $stats['duree_moyenne_traitement'] . " jours\n";
    $message .= "Taux d'approbation: " . $stats['taux_approbation'] . "%\n\n";
    $message .= "Consultez le dashboard pour plus de détails: " . url('dashboard.php') . "\n\n";
    $message .= "Cordialement,\nSystème SGDI";

    foreach ($emails as $email) {
        sendEmail($email, $subject, $message, false);
    }
}
?>

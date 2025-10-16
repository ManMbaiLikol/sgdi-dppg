<?php
// Fonctions pour le module Chef de Commission

/**
 * Récupérer les inspections à valider pour un chef de commission
 */
function getInspectionsAValider($chef_commission_id) {
    global $pdo;

    $sql = "SELECT
                fi.id as fiche_id,
                fi.statut as fiche_statut,
                fi.date_validation as fiche_date,
                d.id as dossier_id,
                d.numero as dossier_numero,
                d.nom_demandeur,
                d.ville,
                d.quartier,
                d.type_infrastructure,
                d.sous_type,
                u.nom as inspecteur_nom,
                u.prenom as inspecteur_prenom,
                c.id as commission_id
            FROM commissions c
            INNER JOIN dossiers d ON c.dossier_id = d.id
            INNER JOIN fiches_inspection fi ON d.id = fi.dossier_id
            LEFT JOIN users u ON fi.valideur_id = u.id
            WHERE c.chef_commission_id = ?
            AND fi.statut = 'validee'
            AND d.statut = 'inspecte'
            ORDER BY fi.date_validation DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$chef_commission_id]);

    return $stmt->fetchAll();
}

/**
 * Récupérer les statistiques pour le chef de commission
 */
function getStatistiquesChefCommission($chef_commission_id) {
    global $pdo;

    $stats = [
        'a_valider' => 0,
        'approuvees' => 0,
        'rejetees' => 0,
        'total' => 0
    ];

    // À valider
    $sql = "SELECT COUNT(*) FROM commissions c
            INNER JOIN dossiers d ON c.dossier_id = d.id
            INNER JOIN fiches_inspection fi ON d.id = fi.dossier_id
            WHERE c.chef_commission_id = ?
            AND fi.statut = 'validee'
            AND d.statut = 'inspecte'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$chef_commission_id]);
    $stats['a_valider'] = $stmt->fetchColumn();

    // Approuvées
    $sql = "SELECT COUNT(*) FROM validations_commission
            WHERE chef_commission_id = ?
            AND decision = 'approuve'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$chef_commission_id]);
    $stats['approuvees'] = $stmt->fetchColumn();

    // Rejetées
    $sql = "SELECT COUNT(*) FROM validations_commission
            WHERE chef_commission_id = ?
            AND decision = 'rejete'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$chef_commission_id]);
    $stats['rejetees'] = $stmt->fetchColumn();

    $stats['total'] = $stats['a_valider'] + $stats['approuvees'] + $stats['rejetees'];

    return $stats;
}

/**
 * Approuver une inspection
 */
function approuverInspection($fiche_id, $chef_commission_id, $commentaires = '') {
    global $pdo;

    try {
        // Récupérer la fiche
        require_once '../fiche_inspection/functions.php';
        $fiche = getFicheInspectionById($fiche_id);

        if (!$fiche) {
            throw new Exception("Fiche d'inspection introuvable");
        }

        // Vérifier que le chef de commission est bien assigné à cette commission
        $sql = "SELECT id FROM commissions
                WHERE dossier_id = ? AND chef_commission_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fiche['dossier_id'], $chef_commission_id]);
        $commission = $stmt->fetch();

        if (!$commission) {
            throw new Exception("Vous n'êtes pas autorisé à valider cette inspection");
        }

        // 1. Mettre à jour statut dossier
        $sql = "UPDATE dossiers
                SET statut = 'validation_commission'
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fiche['dossier_id']]);

        // 2. Enregistrer la validation
        $sql = "INSERT INTO validations_commission
                (fiche_id, commission_id, chef_commission_id, decision, commentaires)
                VALUES (?, ?, ?, 'approuve', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $fiche_id,
            $commission['id'],
            $chef_commission_id,
            $commentaires
        ]);

        // 3. Notification chef service
        $sql = "SELECT id FROM users WHERE role = 'chef_service' LIMIT 1";
        $stmt = $pdo->query($sql);
        $chef_service = $stmt->fetch();

        if ($chef_service) {
            $sql = "INSERT INTO notifications (user_id, type, message, lien, date_creation)
                    VALUES (?, 'validation_commission', ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $message = "Inspection approuvée pour le dossier N° " . $fiche['dossier_numero'];
            $lien = "modules/dossiers/view.php?id=" . $fiche['dossier_id'];

            try {
                $stmt->execute([$chef_service['id'], $message, $lien]);
            } catch (Exception $e) {
                error_log("Notification non envoyée: " . $e->getMessage());
            }
        }

        // 4. Historique
        require_once '../dossiers/functions.php';
        ajouterHistoriqueDossier(
            $fiche['dossier_id'],
            'validation_commission',
            "Inspection approuvée par le chef de commission" . ($commentaires ? " - " . $commentaires : ""),
            $chef_commission_id
        );

        return true;

    } catch (Exception $e) {
        error_log("Erreur approbation: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Rejeter une inspection
 */
function rejeterInspection($fiche_id, $chef_commission_id, $motif) {
    global $pdo;

    if (empty($motif)) {
        throw new Exception("Le motif de rejet est obligatoire");
    }

    try {
        // Récupérer la fiche
        require_once '../fiche_inspection/functions.php';
        $fiche = getFicheInspectionById($fiche_id);

        if (!$fiche) {
            throw new Exception("Fiche d'inspection introuvable");
        }

        // Vérifier autorisation
        $sql = "SELECT id FROM commissions
                WHERE dossier_id = ? AND chef_commission_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fiche['dossier_id'], $chef_commission_id]);
        $commission = $stmt->fetch();

        if (!$commission) {
            throw new Exception("Vous n'êtes pas autorisé à rejeter cette inspection");
        }

        // 1. Remettre dossier en inspection
        $sql = "UPDATE dossiers
                SET statut = 'paye'
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fiche['dossier_id']]);

        // 2. Remettre fiche en brouillon
        $sql = "UPDATE fiches_inspection
                SET statut = 'brouillon'
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fiche_id]);

        // 3. Enregistrer le rejet
        $sql = "INSERT INTO validations_commission
                (fiche_id, commission_id, chef_commission_id, decision, commentaires)
                VALUES (?, ?, ?, 'rejete', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $fiche_id,
            $commission['id'],
            $chef_commission_id,
            $motif
        ]);

        // 4. Notification inspecteur
        if ($fiche['valideur_id']) {
            $sql = "INSERT INTO notifications (user_id, type, message, lien, date_creation)
                    VALUES (?, 'inspection_rejetee', ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $message = "Votre inspection a été rejetée : " . $motif;
            $lien = "modules/fiche_inspection/edit.php?dossier_id=" . $fiche['dossier_id'];

            try {
                $stmt->execute([$fiche['valideur_id'], $message, $lien]);
            } catch (Exception $e) {
                error_log("Notification non envoyée: " . $e->getMessage());
            }
        }

        // 5. Historique
        require_once '../dossiers/functions.php';
        ajouterHistoriqueDossier(
            $fiche['dossier_id'],
            'inspection_rejetee',
            "Inspection rejetée par le chef de commission : " . $motif,
            $chef_commission_id
        );

        return true;

    } catch (Exception $e) {
        error_log("Erreur rejet: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Récupérer l'historique des validations d'une fiche
 */
function getHistoriqueValidations($fiche_id) {
    global $pdo;

    $sql = "SELECT vc.*, u.nom, u.prenom
            FROM validations_commission vc
            LEFT JOIN users u ON vc.chef_commission_id = u.id
            WHERE vc.fiche_id = ?
            ORDER BY vc.date_validation DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fiche_id]);

    return $stmt->fetchAll();
}
?>

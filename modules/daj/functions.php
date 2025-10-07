<?php
// Fonctions pour le module DAJ - SGDI MVP

require_once '../../config/database.php';

/**
 * Récupérer une analyse DAJ pour un dossier
 */
function getAnalyseDAJ($dossier_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT a.*, u.nom, u.prenom,
               CONCAT(u.prenom, ' ', u.nom) as analyste
        FROM analyses_daj a
        LEFT JOIN users u ON a.daj_user_id = u.id
        WHERE a.dossier_id = ?
        ORDER BY a.date_analyse DESC
        LIMIT 1
    ");

    $stmt->execute([$dossier_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Enregistrer ou mettre à jour une analyse DAJ
 */
function enregistrerAnalyseDAJ($dossier_id, $daj_user_id, $statut_analyse, $observations, $documents_manquants, $recommandations) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // Vérifier s'il existe déjà une analyse
        $analyse_existante = getAnalyseDAJ($dossier_id);

        if ($analyse_existante) {
            // Mettre à jour l'analyse existante
            $stmt = $pdo->prepare("
                UPDATE analyses_daj
                SET statut_analyse = ?,
                    observations = ?,
                    documents_manquants = ?,
                    recommandations = ?,
                    date_finalisation = CASE WHEN ? != 'en_cours' THEN NOW() ELSE date_finalisation END
                WHERE dossier_id = ?
            ");

            $stmt->execute([
                $statut_analyse,
                $observations,
                $documents_manquants,
                $recommandations,
                $statut_analyse,
                $dossier_id
            ]);
        } else {
            // Créer une nouvelle analyse
            $stmt = $pdo->prepare("
                INSERT INTO analyses_daj (dossier_id, daj_user_id, statut_analyse, observations, documents_manquants, recommandations, date_finalisation)
                VALUES (?, ?, ?, ?, ?, ?, CASE WHEN ? != 'en_cours' THEN NOW() ELSE NULL END)
            ");

            $stmt->execute([
                $dossier_id,
                $daj_user_id,
                $statut_analyse,
                $observations,
                $documents_manquants,
                $recommandations,
                $statut_analyse
            ]);
        }

        // Ajouter une entrée dans l'historique
        addHistoriqueDossier($dossier_id, $daj_user_id, 'analyse_daj',
            "Analyse DAJ: " . ucfirst(str_replace('_', ' ', $statut_analyse)));

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Erreur enregistrement analyse DAJ: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir les statistiques DAJ pour un utilisateur
 */
function getStatistiquesDAJ($user_id) {
    global $pdo;

    $stats = [];

    // Dossiers en attente d'analyse (statut = 'paye')
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM dossiers
        WHERE statut = 'paye'
    ");
    $stmt->execute();
    $stats['a_analyser'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Analyses en cours par cet utilisateur
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM analyses_daj
        WHERE daj_user_id = ? AND statut_analyse = 'en_cours'
    ");
    $stmt->execute([$user_id]);
    $stats['en_cours'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Analyses terminées par cet utilisateur
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM analyses_daj
        WHERE daj_user_id = ? AND statut_analyse != 'en_cours'
    ");
    $stmt->execute([$user_id]);
    $stats['terminees'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total pour ce mois
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM analyses_daj
        WHERE daj_user_id = ?
        AND MONTH(date_analyse) = MONTH(CURRENT_DATE())
        AND YEAR(date_analyse) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$user_id]);
    $stats['ce_mois'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    return $stats;
}

/**
 * Obtenir la liste des dossiers pour analyse DAJ
 */
function getDossiersDAJ($statut = 'paye', $limit = null, $search = '') {
    global $pdo;

    $where_conditions = [];
    $params = [];

    // Filtrer selon le statut demandé
    if ($statut === 'paye') {
        // Dossiers payés mais pas encore analysés
        $where_conditions[] = "d.statut = 'paye'";
        $where_conditions[] = "a.id IS NULL";
    } elseif ($statut === 'analyse_daj') {
        // Dossiers avec une analyse DAJ enregistrée
        $where_conditions[] = "a.id IS NOT NULL";
    } else {
        // Statut de dossier normal
        $where_conditions[] = "d.statut = ?";
        $params[] = $statut;
    }

    // Ajouter la recherche si fournie
    if (!empty($search)) {
        $where_conditions[] = "(d.numero LIKE ? OR d.nom_demandeur LIKE ? OR d.region LIKE ? OR d.arrondissement LIKE ? OR d.ville LIKE ? OR d.quartier LIKE ? OR d.lieu_dit LIKE ?)";
        $search_param = '%' . $search . '%';
        $params[] = $search_param; // numero
        $params[] = $search_param; // nom_demandeur
        $params[] = $search_param; // region
        $params[] = $search_param; // arrondissement
        $params[] = $search_param; // ville
        $params[] = $search_param; // quartier
        $params[] = $search_param; // lieu_dit
    }

    $sql = "
        SELECT d.*,
               a.statut_analyse,
               a.date_analyse,
               CASE
                   WHEN a.id IS NULL THEN 'À analyser'
                   WHEN a.statut_analyse = 'en_cours' THEN 'En cours'
                   WHEN a.statut_analyse = 'conforme' THEN 'Conforme'
                   WHEN a.statut_analyse = 'conforme_avec_reserves' THEN 'Conforme avec réserves'
                   WHEN a.statut_analyse = 'non_conforme' THEN 'Non conforme'
                   ELSE 'Non analysé'
               END as statut_analyse_libelle
        FROM dossiers d
        LEFT JOIN analyses_daj a ON d.id = a.dossier_id
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY d.date_creation DESC
    ";

    if ($limit) {
        $sql .= " LIMIT " . intval($limit);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Vérifier si un dossier peut être analysé par la DAJ
 */
function peutAnalyserDAJ($dossier_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT statut
        FROM dossiers
        WHERE id = ?
    ");
    $stmt->execute([$dossier_id]);
    $dossier = $stmt->fetch(PDO::FETCH_ASSOC);

    return $dossier && $dossier['statut'] === 'paye';
}

/**
 * Marquer un dossier comme analysé par la DAJ
 */
function marquerAnalyseDAJTerminee($dossier_id, $user_id) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // Mettre à jour le statut du dossier
        updateStatutDossier($dossier_id, 'analyse_daj', 'Analyse juridique DAJ terminée');

        // Créer une notification pour le service DPPG
        $message = "Le dossier a été analysé juridiquement et est prêt pour inspection";
        createNotification($dossier_id, 'cadre_dppg', $message, 'analyse_daj');

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Erreur finalisation analyse DAJ: " . $e->getMessage());
        return false;
    }
}
?>
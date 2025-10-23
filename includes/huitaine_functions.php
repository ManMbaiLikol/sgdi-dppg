<?php
/**
 * Fonctions pour la gestion du workflow "Huitaine" (8 jours)
 * Système de compte à rebours automatique avec alertes
 */

/**
 * Créer une nouvelle huitaine pour un dossier
 */
function creerHuitaine($dossier_id, $type_irregularite, $description, $user_id) {
    global $pdo;

    try {
        // Calculer la date limite (8 jours ouvrables)
        $date_limite = calculerDateLimiteHuitaine();

        // Créer la huitaine
        $sql = "INSERT INTO huitaine (dossier_id, type_irregularite, description, date_limite, created_by)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dossier_id, $type_irregularite, $description, $date_limite, $user_id]);

        $huitaine_id = $pdo->lastInsertId();

        // Mettre à jour le statut du dossier
        $sql = "UPDATE dossiers SET statut = 'en_huitaine' WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dossier_id]);

        // Enregistrer dans l'historique
        $sql = "INSERT INTO historique_huitaine (huitaine_id, action, description, user_id)
                VALUES (?, 'creation', ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$huitaine_id, $description, $user_id]);

        // Ajouter à l'historique du dossier
        addHistoriqueDossier(
            $dossier_id,
            $user_id,
            'huitaine_creee',
            "Huitaine créée : $description",
            null,
            'en_huitaine'
        );

        // Créer la notification pour le demandeur
        creerNotificationHuitaine($huitaine_id, 'creation');

        return [
            'success' => true,
            'huitaine_id' => $huitaine_id,
            'date_limite' => $date_limite
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Calculer la date limite (8 jours ouvrables à partir d'aujourd'hui)
 */
function calculerDateLimiteHuitaine($date_debut = null) {
    if (!$date_debut) {
        $date_debut = new DateTime();
    } elseif (is_string($date_debut)) {
        $date_debut = new DateTime($date_debut);
    }

    $jours_ajoutes = 0;
    $date_limite = clone $date_debut;

    // Ajouter 8 jours ouvrables (du lundi au vendredi)
    while ($jours_ajoutes < 8) {
        $date_limite->modify('+1 day');

        // Vérifier si c'est un jour ouvrable (pas samedi ni dimanche)
        $jour_semaine = $date_limite->format('N'); // 1 = lundi, 7 = dimanche
        if ($jour_semaine < 6) { // Du lundi (1) au vendredi (5)
            $jours_ajoutes++;
        }
    }

    return $date_limite->format('Y-m-d H:i:s');
}

/**
 * Régulariser une huitaine
 */
function regulariserHuitaine($huitaine_id, $commentaire, $user_id) {
    global $pdo;

    try {
        // Vérifier que la huitaine existe et est en cours
        $sql = "SELECT * FROM huitaine WHERE id = ? AND statut = 'en_cours'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$huitaine_id]);
        $huitaine = $stmt->fetch();

        if (!$huitaine) {
            return ['success' => false, 'error' => 'Huitaine introuvable ou déjà traitée'];
        }

        // Mettre à jour la huitaine
        $sql = "UPDATE huitaine
                SET statut = 'regularise',
                    date_regularisation = NOW(),
                    regularise_par = ?,
                    commentaire_regularisation = ?
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $commentaire, $huitaine_id]);

        // Restaurer le statut précédent du dossier
        $sql = "UPDATE dossiers
                SET statut = (
                    SELECT ancien_statut
                    FROM historique_dossier
                    WHERE dossier_id = ?
                    AND nouveau_statut = 'en_huitaine'
                    ORDER BY date_action DESC
                    LIMIT 1
                )
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$huitaine['dossier_id'], $huitaine['dossier_id']]);

        // Ajouter à l'historique du dossier
        addHistoriqueDossier(
            $huitaine['dossier_id'],
            $user_id,
            'huitaine_regularisee',
            "Huitaine régularisée : $commentaire",
            'en_huitaine',
            null
        );

        // Notification
        creerNotificationHuitaine($huitaine_id, 'regularise');

        return ['success' => true, 'message' => 'Huitaine régularisée avec succès'];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Obtenir toutes les huitaines actives
 */
function getHuitainesActives($filters = []) {
    global $pdo;

    $sql = "SELECT * FROM huitaines_actives WHERE 1=1";
    $params = [];

    if (!empty($filters['dossier_id'])) {
        $sql .= " AND dossier_id = ?";
        $params[] = $filters['dossier_id'];
    }

    if (!empty($filters['urgents_uniquement'])) {
        $sql .= " AND jours_restants <= 2";
    }

    if (!empty($filters['expires_uniquement'])) {
        $sql .= " AND jours_restants < 0";
    }

    $sql .= " ORDER BY date_limite ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Obtenir les statistiques des huitaines
 */
function getStatistiquesHuitaine() {
    global $pdo;

    $sql = "SELECT * FROM statistiques_huitaine";
    $stmt = $pdo->query($sql);
    $stats = $stmt->fetch();

    return [
        'en_cours' => $stats['en_cours'] ?? 0,
        'regularises' => $stats['regularises'] ?? 0,
        'rejetes' => $stats['rejetes'] ?? 0,
        'annules' => $stats['annules'] ?? 0,
        'urgents' => $stats['urgents'] ?? 0,
        'expires' => $stats['expires'] ?? 0,
        'duree_moyenne_regularisation' => round($stats['duree_moyenne_regularisation'] ?? 0, 1)
    ];
}

/**
 * Vérifier et envoyer les alertes (J-2, J-1, J)
 * À appeler par un CRON toutes les heures
 */
function verifierEtEnvoyerAlertes() {
    global $pdo;

    $alertes_envoyees = 0;

    // Récupérer toutes les huitaines actives
    $sql = "SELECT * FROM huitaine WHERE statut = 'en_cours'";
    $stmt = $pdo->query($sql);
    $huitaines = $stmt->fetchAll();

    foreach ($huitaines as $huitaine) {
        $date_limite = new DateTime($huitaine['date_limite']);
        $maintenant = new DateTime();
        $jours_restants = $maintenant->diff($date_limite)->days;
        $is_before = $maintenant < $date_limite;

        if (!$is_before) {
            $jours_restants = -$jours_restants;
        }

        // Alerte J-2
        if ($jours_restants == 2 && !$huitaine['alerte_j2_envoyee']) {
            envoyerAlerteHuitaine($huitaine['id'], 'j-2');
            $sql = "UPDATE huitaine SET alerte_j2_envoyee = 1 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$huitaine['id']]);
            $alertes_envoyees++;
        }

        // Alerte J-1
        if ($jours_restants == 1 && !$huitaine['alerte_j1_envoyee']) {
            envoyerAlerteHuitaine($huitaine['id'], 'j-1');
            $sql = "UPDATE huitaine SET alerte_j1_envoyee = 1 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$huitaine['id']]);
            $alertes_envoyees++;
        }

        // Alerte J (jour même)
        if ($jours_restants == 0 && !$huitaine['alerte_j_envoyee']) {
            envoyerAlerteHuitaine($huitaine['id'], 'j');
            $sql = "UPDATE huitaine SET alerte_j_envoyee = 1 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$huitaine['id']]);
            $alertes_envoyees++;
        }
    }

    return $alertes_envoyees;
}

/**
 * Rejeter automatiquement les huitaines expirées
 * À appeler par un CRON quotidien
 */
function rejeterHuitainesExpirees() {
    global $pdo;

    $rejets = 0;

    // Récupérer les huitaines expirées
    $sql = "SELECT * FROM huitaine
            WHERE statut = 'en_cours'
            AND date_limite < NOW()";
    $stmt = $pdo->query($sql);
    $huitaines_expirees = $stmt->fetchAll();

    foreach ($huitaines_expirees as $huitaine) {
        // Mettre à jour la huitaine (le trigger mettra à jour le dossier)
        $sql = "UPDATE huitaine SET statut = 'rejete' WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$huitaine['id']]);

        // Notification de rejet
        creerNotificationHuitaine($huitaine['id'], 'rejete');

        $rejets++;
    }

    return $rejets;
}

/**
 * Envoyer une alerte pour une huitaine
 */
function envoyerAlerteHuitaine($huitaine_id, $type_alerte) {
    global $pdo;

    // Récupérer les informations de la huitaine
    $sql = "SELECT h.*, d.numero, d.nom_demandeur, d.email_demandeur
            FROM huitaine h
            INNER JOIN dossiers d ON h.dossier_id = d.id
            WHERE h.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$huitaine_id]);
    $huitaine = $stmt->fetch();

    if (!$huitaine) return false;

    // Enregistrer dans l'historique
    $sql = "INSERT INTO historique_huitaine (huitaine_id, action, description)
            VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$huitaine_id, 'alerte_' . str_replace('-', '', $type_alerte), "Alerte $type_alerte envoyée"]);

    // Créer l'alerte email pour le demandeur
    if (!empty($huitaine['email_demandeur'])) {
        creerAlerteEmail($huitaine_id, $type_alerte, $huitaine['email_demandeur'], $huitaine);
    }

    // Créer la notification in-app pour le chef de service
    creerNotificationInApp($huitaine_id, $type_alerte, $huitaine['created_by']);

    return true;
}

/**
 * Créer une notification pour la huitaine
 */
function creerNotificationHuitaine($huitaine_id, $type) {
    global $pdo;

    // Récupérer les infos
    $sql = "SELECT h.*, d.numero, d.nom_demandeur, u.email
            FROM huitaine h
            INNER JOIN dossiers d ON h.dossier_id = d.id
            INNER JOIN users u ON h.created_by = u.id
            WHERE h.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$huitaine_id]);
    $huitaine = $stmt->fetch();

    if (!$huitaine) return;

    $messages = [
        'creation' => "Une huitaine a été créée pour le dossier {$huitaine['numero']}",
        'regularise' => "La huitaine du dossier {$huitaine['numero']} a été régularisée",
        'rejete' => "Le dossier {$huitaine['numero']} a été rejeté (huitaine expirée)"
    ];

    $message = $messages[$type] ?? "Action sur huitaine : $type";

    // Créer la notification
    createNotification(
        $huitaine['created_by'],
        'huitaine',
        $message,
        "modules/huitaine/view.php?id={$huitaine_id}"
    );
}

/**
 * Créer une alerte email
 */
function creerAlerteEmail($huitaine_id, $type_alerte, $email, $huitaine) {
    global $pdo;

    $sql = "INSERT INTO alertes_huitaine (huitaine_id, type_alerte, destinataire_email, canal)
            VALUES (?, ?, ?, 'email')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$huitaine_id, $type_alerte, $email]);

    // Charger le système d'email
    require_once __DIR__ . '/email.php';

    // Calculer les jours restants
    $date_limite = new DateTime($huitaine['date_limite']);
    $maintenant = new DateTime();
    $diff = $maintenant->diff($date_limite);
    $jours_restants = $diff->days;

    if ($maintenant > $date_limite) {
        $jours_restants = -$jours_restants;
    }

    // Préparer le sujet selon le type d'alerte
    $urgence_label = ($type_alerte === 'j' || $jours_restants <= 0) ? 'URGENT' : 'Rappel';
    $subject = "[$urgence_label] Délai de huitaine - Dossier " . $huitaine['numero'];

    // Préparer le corps de l'email avec le template
    $body = getEmailTemplate('huitaine_alert', [
        'numero' => $huitaine['numero'],
        'nom_demandeur' => $huitaine['nom_demandeur'],
        'jours_restants' => max(0, $jours_restants), // Ne pas afficher de négatif
        'date_limite' => date('d/m/Y à H:i', strtotime($huitaine['date_limite'])),
        'motif_huitaine' => $huitaine['description'] ?? 'Régularisation nécessaire',
        'url_dossier' => url('modules/huitaine/view.php?id=' . $huitaine_id)
    ]);

    // Envoyer l'email réellement
    $sent = sendEmail($email, $subject, $body, true);

    // Logger le résultat
    if (!$sent) {
        error_log("Échec envoi email huitaine ID: $huitaine_id vers $email");
    }

    return $sent;
}

/**
 * Créer une notification in-app
 */
function creerNotificationInApp($huitaine_id, $type_alerte, $user_id) {
    global $pdo;

    $sql = "INSERT INTO alertes_huitaine (huitaine_id, type_alerte, destinataire_user_id, canal)
            VALUES (?, ?, ?, 'in_app')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$huitaine_id, $type_alerte, $user_id]);
}

/**
 * Obtenir le badge CSS selon les jours restants
 */
function getHuitaineBadgeClass($jours_restants) {
    if ($jours_restants < 0) return 'danger';
    if ($jours_restants <= 1) return 'danger';
    if ($jours_restants <= 2) return 'warning';
    return 'info';
}

/**
 * Formater le texte du compte à rebours
 */
function formatCompteARebours($jours_restants, $heures_restantes) {
    if ($jours_restants < 0) {
        return 'EXPIRÉ (' . abs($jours_restants) . ' jour(s) de retard)';
    }

    if ($jours_restants == 0) {
        if ($heures_restantes < 0) {
            return 'EXPIRÉ (aujourd\'hui)';
        }
        return 'Expire aujourd\'hui (' . $heures_restantes . 'h restantes)';
    }

    if ($jours_restants == 1) {
        return 'Expire demain';
    }

    return $jours_restants . ' jours restants';
}

/**
 * Obtenir l'historique d'une huitaine
 */
function getHistoriqueHuitaine($huitaine_id) {
    global $pdo;

    $sql = "SELECT hh.*, u.nom, u.prenom
            FROM historique_huitaine hh
            LEFT JOIN users u ON hh.user_id = u.id
            WHERE hh.huitaine_id = ?
            ORDER BY hh.date_action DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$huitaine_id]);

    return $stmt->fetchAll();
}

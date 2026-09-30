<?php
/**
 * SGDI - Liste « À traiter » par rôle
 *
 * Chaque tâche reprend un objectif du tableau de bord d'origine, avec son compteur
 * calculé en base et un lien vers la page qui permet de la traiter.
 * Les tâches sont définies par ordre d'urgence ; celles à zéro passent en fin de liste.
 */

/**
 * Exécute un COUNT et renvoie 0 si la requête échoue (table ou colonne absente).
 */
function tacheCompter($sql, array $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('Tâche non calculée : ' . $e->getMessage());
        return 0;
    }
}

/**
 * @return array Liste de tâches : titre, detail, nombre, icon, phase, echeance, url
 */
function getTachesAFaire($role, $user_id) {
    $t = [];
    $ajouter = function ($titre, $detail, $nombre, $icon, $phase, $url, $echeance = '') use (&$t) {
        $t[] = compact('titre', 'detail', 'nombre', 'icon', 'phase', 'url', 'echeance');
    };

    // Dossiers dont l'utilisateur est membre de la commission
    $sql_membre = "EXISTS (SELECT 1 FROM commissions c WHERE c.dossier_id = d.id
                   AND (c.cadre_dppg_id = ? OR c.cadre_daj_id = ? OR c.chef_commission_id = ?))";

    switch ($role) {
        case 'chef_service':
            $ajouter('Huitaines à surveiller', 'Relancer les demandeurs avant le rejet automatique',
                tacheCompter("SELECT COUNT(*) FROM huitaine WHERE statut = 'en_cours' AND date_limite <= DATE_ADD(NOW(), INTERVAL 2 DAY)"),
                'fa-hourglass-half', 'danger', url('modules/huitaine/list.php?urgents=1'), 'Échéance ≤ 2 jours');
            $ajouter('Apposer le visa Chef de Service (1/3)', 'Dossiers inspectés · étape 9 sur 11',
                tacheCompter("SELECT COUNT(*) FROM dossiers WHERE statut = 'inspecte'"),
                'fa-stamp', 'visa', url('modules/dossiers/viser_inspections.php'));
            $ajouter('Constituer la commission', '3 membres obligatoires : cadre DPPG, cadre DAJ, chef de commission',
                tacheCompter("SELECT COUNT(*) FROM dossiers d WHERE d.statut IN ('brouillon', 'cree', 'en_cours')
                              AND NOT EXISTS (SELECT 1 FROM commissions c WHERE c.dossier_id = d.id)"),
                'fa-users', 'preparation', url('modules/dossiers/list.php?statut=en_cours'));
            $ajouter('Émettre la note de frais', 'Commission constituée, note non générée',
                tacheCompter("SELECT COUNT(*) FROM dossiers d WHERE d.statut IN ('brouillon', 'cree', 'en_cours')
                              AND EXISTS (SELECT 1 FROM commissions c WHERE c.dossier_id = d.id)
                              AND NOT EXISTS (SELECT 1 FROM notes_frais n WHERE n.dossier_id = d.id AND n.statut <> 'annulee')"),
                'fa-file-invoice', 'paiement', url('modules/notes_frais/list.php'));
            $ajouter('Suivre les paiements attendus', 'Notes de frais émises il y a plus de 15 jours, non payées',
                tacheCompter("SELECT COUNT(*) FROM notes_frais n WHERE n.statut IN ('en_attente', 'validee')
                              AND n.date_creation < DATE_SUB(NOW(), INTERVAL 15 DAY)
                              AND NOT EXISTS (SELECT 1 FROM paiements p WHERE p.dossier_id = n.dossier_id)"),
                'fa-money-bill-wave', 'paiement', url('modules/paiements/retards.php'));
            break;

        case 'billeteur':
            $ajouter('Enregistrer les paiements reçus', 'Notification automatique après enregistrement',
                tacheCompter("SELECT COUNT(*) FROM dossiers d WHERE d.statut IN ('en_cours', 'note_transmise')
                              AND EXISTS (SELECT 1 FROM notes_frais n WHERE n.dossier_id = d.id AND n.statut IN ('en_attente', 'validee'))
                              AND NOT EXISTS (SELECT 1 FROM paiements p WHERE p.dossier_id = d.id)"),
                'fa-money-bill', 'paiement', url('modules/dossiers/list.php?statut=en_cours'));
            $ajouter('Notes impayées depuis 30 jours', 'À signaler au Chef de Service pour relance',
                tacheCompter("SELECT COUNT(*) FROM notes_frais n WHERE n.statut IN ('en_attente', 'validee')
                              AND n.date_creation < DATE_SUB(NOW(), INTERVAL 30 DAY)
                              AND NOT EXISTS (SELECT 1 FROM paiements p WHERE p.dossier_id = n.dossier_id)"),
                'fa-bell', 'danger', url('modules/paiements/retards.php'), 'Plus de 30 jours');
            break;

        case 'cadre_daj':
            $ajouter('Analyse juridique', 'Dossiers payés · vérifier la conformité réglementaire',
                tacheCompter("SELECT COUNT(*) FROM dossiers d WHERE d.statut = 'paye'
                              AND NOT EXISTS (SELECT 1 FROM analyses_daj a WHERE a.dossier_id = d.id)"),
                'fa-gavel', 'instruction', url('modules/daj/list.php?statut=paye'));
            $ajouter('Analyses à finaliser', 'Analyses commencées, non transmises',
                tacheCompter("SELECT COUNT(*) FROM analyses_daj WHERE statut_analyse = 'en_cours' AND daj_user_id = ?", [$user_id]),
                'fa-pen-to-square', 'preparation', url('modules/daj/list.php'));
            $ajouter('Huitaines en cours', 'Pièces manquantes attendues sur vos dossiers',
                tacheCompter("SELECT COUNT(*) FROM huitaine h JOIN dossiers d ON d.id = h.dossier_id
                              WHERE h.statut = 'en_cours' AND $sql_membre", [$user_id, $user_id, $user_id]),
                'fa-hourglass-half', 'attention', url('modules/huitaine/list.php'));
            break;

        case 'cadre_dppg':
            $ajouter('Inspections à réaliser', 'Dossiers de vos commissions prêts pour l\'inspection',
                tacheCompter("SELECT COUNT(*) FROM dossiers d WHERE d.statut IN ('paye', 'analyse_daj')
                              AND $sql_membre
                              AND NOT EXISTS (SELECT 1 FROM fiches_inspection f WHERE f.dossier_id = d.id)", [$user_id, $user_id, $user_id]),
                'fa-clipboard-check', 'instruction', url('modules/fiche_inspection/list_dossiers.php'));
            $ajouter('Fiches d\'inspection à compléter', 'Fiches commencées, non validées',
                tacheCompter("SELECT COUNT(*) FROM fiches_inspection WHERE statut = 'brouillon' AND inspecteur_id = ?", [$user_id]),
                'fa-file-pen', 'paiement', url('modules/fiche_inspection/list_dossiers.php'));
            break;

        case 'chef_commission':
            $ajouter('Valider les rapports d\'inspection', 'Dossiers inspectés de vos commissions, en attente de votre validation',
                tacheCompter("SELECT COUNT(*) FROM dossiers d JOIN commissions c ON c.dossier_id = d.id
                              LEFT JOIN inspections i ON i.dossier_id = d.id
                              WHERE c.chef_commission_id = ? AND d.statut = 'inspecte'
                              AND (i.valide_par_chef_commission IS NULL OR i.valide_par_chef_commission = 0)", [$user_id]),
                'fa-check-double', 'instruction', url('modules/chef_commission/list.php?statut=inspecte'));
            $ajouter('Dossiers en cours d\'instruction', 'Analyse juridique et inspection à suivre',
                tacheCompter("SELECT COUNT(*) FROM dossiers d JOIN commissions c ON c.dossier_id = d.id
                              WHERE c.chef_commission_id = ? AND d.statut IN ('paye', 'analyse_daj')", [$user_id]),
                'fa-users', 'preparation', url('modules/chef_commission/list.php'));
            break;

        case 'sous_directeur':
            $ajouter('Apposer le visa Sous-Directeur (2/3)', 'Dossiers visés par le Chef de Service',
                tacheCompter("SELECT COUNT(*) FROM dossiers WHERE statut = 'visa_chef_service'"),
                'fa-stamp', 'visa', url('modules/sous_directeur/liste_a_viser.php'));
            $ajouter('Mes commissions', 'Dossiers en instruction dont vous présidez la commission',
                tacheCompter("SELECT COUNT(*) FROM dossiers d JOIN commissions c ON c.dossier_id = d.id
                              WHERE c.chef_commission_id = ? AND d.statut IN ('paye', 'analyse_daj', 'inspecte')", [$user_id]),
                'fa-users', 'instruction', url('modules/sous_directeur/mes_commissions.php'));
            break;

        case 'directeur':
            $ajouter('Apposer le visa Directeur (3/3) et transmettre', 'Dossiers visés par le Sous-Directeur',
                tacheCompter("SELECT COUNT(*) FROM dossiers WHERE statut = 'visa_sous_directeur'"),
                'fa-stamp', 'visa', url('modules/directeur/dashboard.php#a-viser'));
            $ajouter('Suivre les transmissions', 'Dossiers en attente de décision ministérielle',
                tacheCompter("SELECT COUNT(*) FROM dossiers WHERE statut = 'visa_directeur'"),
                'fa-paper-plane', 'decision', url('modules/directeur/dashboard.php#transmis'));
            break;

        case 'cabinet':
            $ajouter('Prendre la décision', 'Approbation ou refus · publication automatique au registre public',
                tacheCompter("SELECT COUNT(*) FROM dossiers WHERE statut = 'visa_directeur'"),
                'fa-gavel', 'decision', url('modules/ministre/dashboard.php#a-decider'));
            break;

        case 'admin':
            $ajouter('E-mails en échec', 'Notifications non reçues ces 7 derniers jours',
                tacheCompter("SELECT COUNT(*) FROM email_logs WHERE statut = 'failed' AND date_envoi >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
                'fa-envelope-circle-check', 'danger', url('modules/admin/email_logs.php'));
            $ajouter('Comptes inactifs', 'Comptes actifs sans connexion depuis 90 jours',
                tacheCompter("SELECT COUNT(*) FROM users WHERE actif = 1
                              AND (derniere_connexion IS NULL OR derniere_connexion < DATE_SUB(NOW(), INTERVAL 90 DAY))"),
                'fa-user-clock', 'preparation', url('modules/users/list.php'));
            $ajouter('Infrastructures sans coordonnées GPS', 'Positions à compléter pour la carte',
                tacheCompter("SELECT COUNT(*) FROM dossiers WHERE coordonnees_gps IS NULL OR coordonnees_gps = ''"),
                'fa-location-crosshairs', 'paiement', url('modules/admin_gps/rapprochement.php'));
            break;
    }

    // Tâches en attente d'abord, en conservant l'ordre d'urgence défini ci-dessus
    $avec = array_values(array_filter($t, function ($x) { return $x['nombre'] > 0; }));
    $sans = array_values(array_filter($t, function ($x) { return $x['nombre'] == 0; }));
    return array_merge($avec, $sans);
}

/**
 * Rendu HTML de la liste « À traiter »
 */
function uiTaskList(array $taches) {
    if (!$taches) {
        return uiEmptyState('Rien à traiter', 'Aucune action ne vous est demandée pour le moment.', 'fa-circle-check');
    }
    $html = '<ul class="task-list">';
    foreach ($taches as $x) {
        $a_jour = $x['nombre'] == 0;
        $html .= '<li><a class="task-item phase-' . $x['phase'] . ($a_jour ? ' is-done' : '') . '" href="' . htmlspecialchars($x['url']) . '">'
               . '<span class="task-icon"><i class="fas ' . htmlspecialchars($x['icon']) . '" aria-hidden="true"></i></span>'
               . '<span class="task-body"><span class="task-title d-block">' . htmlspecialchars($x['titre']) . '</span>'
               . '<span class="task-meta d-block">'
               . (!$a_jour && $x['echeance'] !== '' ? '<span class="task-due">' . htmlspecialchars($x['echeance']) . '</span> · ' : '')
               . htmlspecialchars($x['detail']) . '</span></span>'
               . ($a_jour ? '<span class="task-ok">À jour</span>' : '<span class="task-count">' . (int) $x['nombre'] . '</span>')
               . '<i class="fas fa-chevron-right text-muted-sgdi small" aria-hidden="true"></i></a></li>';
    }
    return $html . '</ul>';
}

<?php
/**
 * SGDI - Composants d'interface (socle de design v2)
 *
 * Source unique des métadonnées de statut (libellé, phase, étape du circuit)
 * et fonctions de rendu des composants communs. À utiliser avec assets/css/sgdi-ui.css.
 * Toutes les sorties texte sont échappées ; les paramètres $html_* acceptent du HTML déjà sûr.
 */

// Nombre d'étapes du circuit réglementaire (création → publication au registre)
if (!defined('SGDI_WORKFLOW_ETAPES')) {
    define('SGDI_WORKFLOW_ETAPES', 11);
}

/**
 * Phases du workflow : regroupent les statuts pour l'affichage (couleur, filtres, graphiques)
 */
function uiPhases() {
    return [
        'preparation' => ['label' => 'Préparation', 'icon' => 'fa-pen-to-square'],
        'paiement'    => ['label' => 'Paiement',    'icon' => 'fa-money-bill-wave'],
        'instruction' => ['label' => 'Instruction', 'icon' => 'fa-magnifying-glass'],
        'visa'        => ['label' => 'Circuit des visas', 'icon' => 'fa-stamp'],
        'decision'    => ['label' => 'Décision',    'icon' => 'fa-gavel'],
        'succes'      => ['label' => 'Autorisés',   'icon' => 'fa-circle-check'],
        'danger'      => ['label' => 'Rejetés',     'icon' => 'fa-circle-xmark'],
        'attention'   => ['label' => 'En huitaine', 'icon' => 'fa-hourglass-half'],
    ];
}

/**
 * Métadonnées d'un statut de dossier
 *
 * @return array ['label', 'phase', 'etape' (1..11), 'icon']
 */
function uiStatut($statut) {
    $statuts = uiTableStatuts();
    if (!isset($statuts[$statut])) {
        return ['label' => (string) $statut, 'phase' => 'preparation', 'etape' => 1, 'icon' => 'fa-circle-question'];
    }

    list($label, $phase, $etape, $icon) = $statuts[$statut];
    return ['label' => $label, 'phase' => $phase, 'etape' => $etape, 'icon' => $icon];
}

/**
 * Codes des statuts appartenant à une phase (filtre par phase)
 */
function uiStatutsDePhase($phase) {
    $codes = [];
    foreach (uiTableStatuts() as $code => $s) {
        if ($s[1] === $phase) $codes[] = $code;
    }
    return $codes;
}

/**
 * Table des statuts : code => [libellé, phase, étape du circuit, icône]
 */
function uiTableStatuts() {
    static $statuts = [
        'brouillon'             => ['Brouillon',                'preparation', 1,  'fa-file-pen'],
        'cree'                  => ['Créé',                     'preparation', 1,  'fa-file-circle-plus'],
        'en_cours'              => ['En cours',                 'paiement',    3,  'fa-file-invoice'],
        'note_transmise'        => ['Note transmise',           'paiement',    3,  'fa-paper-plane'],
        'paye'                  => ['Payé',                     'instruction', 4,  'fa-money-bill-wave'],
        'en_huitaine'           => ['En huitaine',              'attention',   5,  'fa-hourglass-half'],
        'analyse_daj'           => ['Analysé',                  'instruction', 5,  'fa-scale-balanced'],
        'inspecte'              => ['Inspecté',                 'instruction', 7,  'fa-clipboard-check'],
        'valide'                => ['Validé',                   'instruction', 8,  'fa-check-double'],
        'validation_commission' => ['Validé par la commission', 'instruction', 8,  'fa-users-viewfinder'],
        'visa_chef_service'     => ['Visa Chef Service',        'visa',        9,  'fa-stamp'],
        'visa_sous_directeur'   => ['Visa Sous-Directeur',      'visa',        9,  'fa-stamp'],
        'visa_directeur'        => ['Visa Directeur',           'visa',        9,  'fa-stamp'],
        'decide'                => ['Décidé',                   'decision',    10, 'fa-gavel'],
        'autorise'              => ['Autorisé',                 'succes',      11, 'fa-circle-check'],
        'rejete'                => ['Rejeté',                   'danger',      11, 'fa-circle-xmark'],
        'ferme'                 => ['Fermé',                    'preparation', 11, 'fa-lock'],
        'suspendu'              => ['Suspendu',                 'attention',   11, 'fa-circle-pause'],
        'historique_autorise'   => ['Autorisé (historique)',    'succes',      11, 'fa-clock-rotate-left'],
    ];
    return $statuts;
}

/**
 * Badge de statut coloré selon la phase
 */
function uiStatutBadge($statut) {
    $s = uiStatut($statut);
    return '<span class="status-badge phase-' . $s['phase'] . '">' . htmlspecialchars($s['label']) . '</span>';
}

/**
 * Barre de progression du dossier dans le circuit en 11 étapes
 */
function uiWorkflowProgress($statut, $avec_libelle = true) {
    $s = uiStatut($statut);
    $html = '<div class="phase-' . $s['phase'] . '">'
          . '<div class="wf-progress" role="img" aria-label="Étape ' . $s['etape'] . ' sur ' . SGDI_WORKFLOW_ETAPES . '">';
    for ($i = 1; $i <= SGDI_WORKFLOW_ETAPES; $i++) {
        $html .= '<span' . ($i <= $s['etape'] ? ' class="done"' : '') . '></span>';
    }
    $html .= '</div>';
    if ($avec_libelle) {
        $html .= '<div class="wf-progress-label">Étape ' . $s['etape'] . '/' . SGDI_WORKFLOW_ETAPES . '</div>';
    }
    return $html . '</div>';
}

/**
 * Les 11 étapes du circuit réglementaire
 */
function uiEtapesWorkflow() {
    return [
        1 => 'Création du dossier', 2 => 'Commission', 3 => 'Note de frais', 4 => 'Paiement',
        5 => 'Analyse juridique', 6 => 'Complétude', 7 => 'Inspection', 8 => 'Validation commission',
        9 => 'Visas', 10 => 'Décision', 11 => 'Publication',
    ];
}

/**
 * Frise détaillée du circuit (fiche dossier) : étapes franchies, étape en cours, étapes à venir.
 * Un dossier rejeté marque l'étape en cours en rouge ; en huitaine, en orange.
 */
function uiWorkflowStepper($statut) {
    $s = uiStatut($statut);
    $termine = in_array($statut, ['autorise', 'historique_autorise'], true);
    $html = '<ol class="wf-stepper phase-' . $s['phase'] . '" aria-label="Circuit du dossier : étape ' . $s['etape'] . ' sur ' . SGDI_WORKFLOW_ETAPES . '">';
    foreach (uiEtapesWorkflow() as $n => $libelle) {
        if ($termine || $n < $s['etape']) {
            $etat = 'done';
        } elseif ($n === $s['etape']) {
            $etat = $statut === 'rejete' ? 'failed' : ($s['phase'] === 'attention' ? 'warning' : 'current');
        } else {
            $etat = 'todo';
        }
        $icone = ['done' => 'fa-check', 'failed' => 'fa-xmark', 'warning' => 'fa-hourglass-half', 'current' => '', 'todo' => ''][$etat];
        $html .= '<li class="wf-step is-' . $etat . '"' . ($etat === 'current' || $etat === 'warning' || $etat === 'failed' ? ' aria-current="step"' : '') . '>'
               . '<span class="wf-step-dot">' . ($icone ? '<i class="fas ' . $icone . '" aria-hidden="true"></i>' : $n) . '</span>'
               . '<span class="wf-step-label">' . htmlspecialchars($libelle) . '</span></li>';
    }
    return $html . '</ol>';
}

/**
 * Carte indicateur (KPI)
 *
 * @param string $phase Phase pour la couleur de l'icône ('' = couleur principale)
 * @param string|null $url Rend la carte cliquable
 */
function uiKpiCard($label, $valeur, $icon, $phase = '', $meta = '', $url = null) {
    $classe_phase = $phase ? ' phase-' . $phase : '';
    $html = '<div class="card h-100' . $classe_phase . '"><div class="kpi-card">'
          . '<div class="kpi-top"><span class="kpi-label">' . htmlspecialchars($label) . '</span>'
          . '<span class="kpi-icon"><i class="fas ' . htmlspecialchars($icon) . '" aria-hidden="true"></i></span></div>'
          . '<div class="kpi-value">' . htmlspecialchars((string) $valeur) . '</div>'
          . ($meta !== '' ? '<div class="kpi-meta">' . htmlspecialchars($meta) . '</div>' : '')
          . '</div></div>';

    return $url ? '<a class="kpi-link" href="' . htmlspecialchars($url) . '">' . $html . '</a>' : $html;
}

/**
 * En-tête de page : titre, sous-titre, fil d'Ariane et boutons d'action
 *
 * @param array $fil_ariane [['label' => ..., 'url' => ...], ...] (dernier élément sans url)
 * @param string $html_actions HTML des boutons (déjà échappé)
 */
function uiPageHeader($titre, $sous_titre = '', $fil_ariane = [], $html_actions = '') {
    $html = '<div class="page-header"><div>';
    if ($fil_ariane) {
        $html .= '<nav aria-label="Fil d\'Ariane"><ol class="breadcrumb">';
        foreach ($fil_ariane as $item) {
            $html .= empty($item['url'])
                ? '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($item['label']) . '</li>'
                : '<li class="breadcrumb-item"><a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['label']) . '</a></li>';
        }
        $html .= '</ol></nav>';
    }
    $html .= '<h1 class="page-title">' . htmlspecialchars($titre) . '</h1>';
    if ($sous_titre !== '') {
        $html .= '<p class="page-subtitle">' . htmlspecialchars($sous_titre) . '</p>';
    }
    $html .= '</div>';
    if ($html_actions !== '') {
        $html .= '<div class="page-actions">' . $html_actions . '</div>';
    }
    return $html . '</div>';
}

/**
 * En-tête des pages d'action sur un dossier (commission, paiement, visa…) :
 * fil d'Ariane jusqu'au dossier, titre de l'action, rappel du demandeur, du type et du statut.
 *
 * @param array $dossier Doit contenir id, numero ; nom_demandeur, type_infrastructure, sous_type, statut si disponibles
 */
function uiEnteteDossier(array $dossier, $titre_action, $html_actions = '') {
    $numero = $dossier['numero'] ?? ('#' . ($dossier['id'] ?? ''));
    $url_dossier = url('modules/dossiers/view.php?id=' . (int) ($dossier['id'] ?? 0));
    $html = '<div class="page-header"><div>'
          . '<nav aria-label="Fil d\'Ariane"><ol class="breadcrumb">'
          . '<li class="breadcrumb-item"><a href="' . url('modules/dossiers/list.php') . '">Dossiers</a></li>'
          . '<li class="breadcrumb-item"><a href="' . htmlspecialchars($url_dossier) . '">' . htmlspecialchars($numero) . '</a></li>'
          . '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($titre_action) . '</li>'
          . '</ol></nav>'
          . '<h1 class="page-title">' . htmlspecialchars($titre_action) . '</h1>'
          . '<p class="page-subtitle d-flex flex-wrap align-items-center gap-2">';
    if (!empty($dossier['nom_demandeur'])) {
        $html .= '<strong>' . htmlspecialchars($dossier['nom_demandeur']) . '</strong> ·';
    }
    $html .= ' <span class="mono">' . htmlspecialchars($numero) . '</span>';
    if (!empty($dossier['type_infrastructure']) && function_exists('getTypeLabel')) {
        $html .= ' · <span>' . htmlspecialchars(getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type'] ?? null)) . '</span>';
    }
    if (!empty($dossier['statut'])) {
        $html .= ' · ' . uiStatutBadge($dossier['statut']);
    }
    $html .= '</p></div><div class="page-actions">'
           . '<a class="btn btn-ghost" href="' . htmlspecialchars($url_dossier) . '"><i class="fas fa-arrow-left"></i> Retour au dossier</a>'
           . $html_actions . '</div></div>';
    return $html;
}

/**
 * État vide (liste sans résultat, etc.)
 */
function uiEmptyState($titre, $message = '', $icon = 'fa-folder-open', $html_action = '') {
    return '<div class="empty-state"><i class="fas ' . htmlspecialchars($icon) . '" aria-hidden="true"></i>'
         . '<h3>' . htmlspecialchars($titre) . '</h3>'
         . ($message !== '' ? '<p class="mb-3">' . htmlspecialchars($message) . '</p>' : '')
         . $html_action . '</div>';
}

/**
 * Date du jour en toutes lettres (« Mercredi 30 septembre 2026 »)
 */
function uiDateDuJour() {
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return ucfirst($jours[date('w')]) . ' ' . date('j') . ' ' . $mois[(int) date('n')] . ' ' . date('Y');
}

/**
 * Bandeau d'accueil des tableaux de bord : salutation, tâche prioritaire, rôle et boutons
 *
 * @param array  $taches        Liste issue de getTachesAFaire() (triée par urgence)
 * @param string $html_boutons  Boutons (HTML déjà échappé)
 */
function uiBandeauAccueil($prenom, $libelle_role, array $taches = [], $html_boutons = '') {
    $priorite = ($taches && $taches[0]['nombre'] > 0) ? $taches[0] : null;
    return '<div class="hero-welcome"><div>'
         . '<h1>Bonjour, ' . htmlspecialchars($prenom) . '</h1>'
         . '<p>' . uiDateDuJour() . ' · '
         . ($priorite ? 'priorité : <strong>' . htmlspecialchars($priorite['titre']) . ' (' . (int) $priorite['nombre'] . ')</strong>' : 'aucune action en attente')
         . '</p><div class="d-flex flex-wrap gap-2 mt-2"><span class="hero-chip"><i class="fas fa-user-tag" aria-hidden="true"></i> ' . htmlspecialchars($libelle_role) . '</span></div></div>'
         . ($html_boutons !== '' ? '<div class="d-flex flex-wrap gap-2">' . $html_boutons . '</div>' : '')
         . '</div>';
}

/**
 * Raccourci (action rapide) : [icône, libellé, url, phase pour la couleur]
 */
function uiRaccourcis(array $liens) {
    $html = '<div class="row row-cols-1 row-cols-sm-2 g-2">';
    foreach ($liens as $l) {
        $html .= '<div class="col"><a class="shortcut phase-' . htmlspecialchars($l[3] ?? 'instruction') . '" href="' . htmlspecialchars($l[2]) . '">'
               . '<i class="fas ' . htmlspecialchars($l[0]) . '" aria-hidden="true"></i>' . htmlspecialchars($l[1]) . '</a></div>';
    }
    return $html . '</div>';
}

/**
 * Initiales pour l'avatar (« Jean Mbarga » → « JM »)
 */
function uiInitiales($prenom, $nom) {
    $i = mb_strtoupper(mb_substr(trim($prenom), 0, 1) . mb_substr(trim($nom), 0, 1));
    return $i !== '' ? $i : '?';
}

/**
 * Script à placer dans <head> : applique le thème avant l'affichage (pas de flash blanc).
 * Réutilise la clé localStorage de l'ancien sélecteur de thème (sgdi_theme).
 */
function uiThemeInitScript() {
    return "<script>(function(){try{var t=localStorage.getItem('sgdi_theme');"
         . "if(!t){t=window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}"
         . "document.documentElement.setAttribute('data-bs-theme',t);"
         . "document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>";
}

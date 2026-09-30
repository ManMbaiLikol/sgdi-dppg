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

    if (!isset($statuts[$statut])) {
        return ['label' => (string) $statut, 'phase' => 'preparation', 'etape' => 1, 'icon' => 'fa-circle-question'];
    }

    list($label, $phase, $etape, $icon) = $statuts[$statut];
    return ['label' => $label, 'phase' => $phase, 'etape' => $etape, 'icon' => $icon];
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
 * État vide (liste sans résultat, etc.)
 */
function uiEmptyState($titre, $message = '', $icon = 'fa-folder-open', $html_action = '') {
    return '<div class="empty-state"><i class="fas ' . htmlspecialchars($icon) . '" aria-hidden="true"></i>'
         . '<h3>' . htmlspecialchars($titre) . '</h3>'
         . ($message !== '' ? '<p class="mb-3">' . htmlspecialchars($message) . '</p>' : '')
         . $html_action . '</div>';
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

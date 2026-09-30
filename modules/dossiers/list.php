<?php
// Liste des dossiers - SGDI
require_once '../../includes/auth.php';
require_once '../../includes/ui.php';
require_once 'functions.php';

requireLogin();

// Vérifier que l'utilisateur a la permission de lister les dossiers
requireAnyPermission(['dossiers.list', 'dossiers.view_all']);

$page_title = 'Dossiers';

// Filtres (le paramètre « statut » reste accepté : liens de la navigation et des anciennes pages)
$phases = uiPhases();
$phase = isset($phases[$_GET['phase'] ?? '']) ? $_GET['phase'] : '';
$filters = [
    'statut' => sanitize($_GET['statut'] ?? ''),
    'statuts' => $phase ? uiStatutsDePhase($phase) : [],
    'type_infrastructure' => sanitize($_GET['type_infrastructure'] ?? ''),
    'sous_type' => sanitize($_GET['sous_type'] ?? ''),
    'region' => sanitize($_GET['region'] ?? ''),
    'search' => sanitize($_GET['search'] ?? ''),
    'user_role' => $_SESSION['user_role']
];

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$dossiers = getDossiers($filters, $limit, $offset);
$total_dossiers = (int) countDossiers($filters);
$total_pages = (int) ceil($total_dossiers / $limit);

// Compteurs des filtres par phase (mêmes filtres et mêmes droits, hors phase/statut)
$par_statut = countDossiersParStatut($filters);
$par_phase = array_fill_keys(array_keys($phases), 0);
foreach ($par_statut as $statut => $n) {
    $par_phase[uiStatut($statut)['phase']] += $n;
}
$total_toutes_phases = array_sum($par_statut);

// Régions présentes dans les dossiers visibles
try {
    $regions = $pdo->query("SELECT DISTINCT region FROM dossiers WHERE region IS NOT NULL AND region <> '' ORDER BY region")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $regions = [];
}

// URL de la liste en conservant les filtres courants
function urlListe(array $changements = []) {
    $params = array_merge($_GET, $changements);
    unset($params['page']);
    if (isset($changements['page'])) $params['page'] = $changements['page'];
    $params = array_filter($params, function ($v) { return $v !== '' && $v !== null; });
    return '?' . http_build_query($params);
}

// Lien de chaque action proposée par getActionsPossibles()
function urlActionDossier($action, $id) {
    $chemins = [
        'constituer_commission' => 'modules/dossiers/commission.php?id=',
        'creer_note_frais' => 'modules/notes_frais/create.php?dossier_id=',
        'enregistrer_paiement' => 'modules/dossiers/paiement.php?id=',
        'faire_inspection' => 'modules/dossiers/inspection.php?id=',
        'valider_rapport' => 'modules/dossiers/decision.php?id=',
        'prendre_decision' => 'modules/dossiers/decision.php?id=',
        'marquer_autorise' => 'modules/dossiers/marquer_autorise.php?id=',
        'gestion_operationnelle' => 'modules/dossiers/gestion_operationnelle.php?id=',
        'upload_documents' => 'modules/dossiers/upload_documents.php?id=',
    ];
    return url(($chemins[$action] ?? 'modules/dossiers/view.php?id=') . (int) $id);
}

$filtres_actifs = $filters['type_infrastructure'] || $filters['sous_type'] || $filters['region'] || $filters['search'] || $filters['statut'] || $phase;

// Boutons de l'en-tête
$actions_entete = '';
if (hasPermission('visa.chef_service') && !hasRole('admin')) {
    $actions_entete .= '<a href="' . url('modules/dossiers/viser_inspections.php') . '" class="btn btn-outline-secondary"><i class="fas fa-stamp"></i> Viser les dossiers inspectés</a>';
}
if (hasPermission('dossiers.create')) {
    $actions_entete .= '<a href="' . url('modules/dossiers/create.php') . '" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau dossier</a>';
}

require_once '../../includes/header.php';

echo uiPageHeader(
    'Dossiers',
    number_format($total_dossiers, 0, ',', ' ') . ' dossier' . ($total_dossiers > 1 ? 's' : '') . ($filtres_actifs ? ' correspondant aux filtres' : '') . ' · du dépôt à la publication au registre',
    [['label' => 'Tableau de bord', 'url' => url('dashboard.php')], ['label' => 'Dossiers']],
    $actions_entete
);
?>

<div class="card">
    <!-- Filtres -->
    <div class="toolbar flex-column align-items-stretch">
        <div class="filter-chips" role="group" aria-label="Filtrer par phase">
            <a class="chip<?php echo !$phase && !$filters['statut'] ? ' active' : ''; ?>" href="<?php echo urlListe(['phase' => '', 'statut' => '']); ?>">
                Toutes <span class="chip-count"><?php echo number_format($total_toutes_phases, 0, ',', ' '); ?></span>
            </a>
            <?php foreach ($phases as $code => $p): if (!$par_phase[$code] && $phase !== $code) continue; ?>
            <a class="chip phase-<?php echo $code; ?><?php echo $phase === $code ? ' active' : ''; ?>" href="<?php echo urlListe(['phase' => $code, 'statut' => '']); ?>"<?php echo $phase === $code ? ' aria-current="true"' : ''; ?>>
                <span class="swatch"></span><?php echo sanitize($p['label']); ?> <span class="chip-count"><?php echo number_format($par_phase[$code], 0, ',', ' '); ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" class="d-flex flex-wrap gap-2" id="form-filtres">
            <?php if ($phase): ?><input type="hidden" name="phase" value="<?php echo sanitize($phase); ?>"><?php endif; ?>
            <?php if ($filters['statut']): ?><input type="hidden" name="statut" value="<?php echo sanitize($filters['statut']); ?>"><?php endif; ?>
            <div class="app-search flex-grow-1" style="max-width: none; min-width: 14rem">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" class="form-control" id="search" name="search" value="<?php echo sanitize($filters['search']); ?>"
                       placeholder="N°, demandeur, opérateur, ville, quartier…" aria-label="Rechercher un dossier">
            </div>
            <select class="form-select w-auto" name="type_infrastructure" aria-label="Type d'infrastructure" data-auto-submit>
                <option value="">Tous les types</option>
                <?php foreach (['station_service' => 'Station-service', 'point_consommateur' => 'Point consommateur', 'depot_gpl' => 'Dépôt GPL', 'centre_emplisseur' => 'Centre emplisseur'] as $v => $l): ?>
                <option value="<?php echo $v; ?>" <?php echo $filters['type_infrastructure'] === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select w-auto" name="sous_type" aria-label="Nature" data-auto-submit>
                <option value="">Toutes natures</option>
                <?php foreach (['implantation' => 'Implantation', 'reprise' => 'Reprise', 'remodelage' => 'Remodelage'] as $v => $l): ?>
                <option value="<?php echo $v; ?>" <?php echo $filters['sous_type'] === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($regions): ?>
            <select class="form-select w-auto" name="region" aria-label="Région" data-auto-submit>
                <option value="">Toutes les régions</option>
                <?php foreach ($regions as $r): ?>
                <option value="<?php echo sanitize($r); ?>" <?php echo $filters['region'] === $r ? 'selected' : ''; ?>><?php echo sanitize($r); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
            <?php if ($filtres_actifs): ?>
            <a class="btn btn-ghost" href="<?php echo url('modules/dossiers/list.php'); ?>"><i class="fas fa-xmark"></i> Réinitialiser</a>
            <?php endif; ?>
        </form>

        <?php if ($filters['statut']): ?>
        <div class="small">
            Statut : <?php echo uiStatutBadge($filters['statut']); ?>
            <a class="ms-1" href="<?php echo urlListe(['statut' => '']); ?>" aria-label="Retirer le filtre de statut"><i class="fas fa-xmark"></i></a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($dossiers)): ?>
        <?php
        $action_vide = $filtres_actifs
            ? '<a class="btn btn-outline-secondary btn-sm" href="' . url('modules/dossiers/list.php') . '">Réinitialiser les filtres</a>'
            : (hasPermission('dossiers.create') ? '<a class="btn btn-primary btn-sm" href="' . url('modules/dossiers/create.php') . '"><i class="fas fa-plus"></i> Créer le premier dossier</a>' : '');
        echo uiEmptyState(
            $filtres_actifs ? 'Aucun dossier ne correspond à ces filtres' : 'Aucun dossier pour le moment',
            $filtres_actifs ? 'Modifiez la recherche ou affichez toutes les phases.' : '',
            'fa-folder-open',
            $action_vide
        );
        ?>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sgdi table-hover table-stack">
            <thead>
                <tr>
                    <th>N° dossier</th>
                    <th>Infrastructure</th>
                    <th>Demandeur</th>
                    <th>Localisation</th>
                    <th>Statut</th>
                    <th>Avancement</th>
                    <th class="text-end"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dossiers as $dossier):
                    $url_voir = url('modules/dossiers/view.php?id=' . (int) $dossier['id']);
                    $actions = array_values(array_filter(getActionsPossibles($dossier, $_SESSION['user_role']), function ($a) {
                        return $a['action'] !== 'voir_details';
                    }));
                    $principale = array_shift($actions);
                    $lieu = array_filter([$dossier['ville'] ?: $dossier['arrondissement'], $dossier['quartier']]);
                ?>
                <tr>
                    <td data-label="N° dossier">
                        <a class="cell-main mono" href="<?php echo $url_voir; ?>"><?php echo sanitize($dossier['numero']); ?></a>
                        <span class="cell-sub">
                            <?php echo formatDateTime($dossier['date_creation'], 'd/m/Y'); ?>
                            <?php if (trim($dossier['createur_prenom'] . $dossier['createur_nom']) !== ''): ?>
                            · <?php echo sanitize(trim($dossier['createur_prenom'] . ' ' . $dossier['createur_nom'])); ?>
                            <?php endif; ?>
                        </span>
                    </td>
                    <td data-label="Infrastructure">
                        <?php echo sanitize(getTypeLabel($dossier['type_infrastructure'])); ?>
                        <?php if ($dossier['sous_type']): ?><span class="cell-sub"><?php echo sanitize(ucfirst($dossier['sous_type'])); ?></span><?php endif; ?>
                    </td>
                    <td data-label="Demandeur">
                        <?php echo sanitize($dossier['nom_demandeur']); ?>
                        <?php if (!empty($dossier['contact_demandeur'])): ?><span class="cell-sub"><?php echo sanitize($dossier['contact_demandeur']); ?></span><?php endif; ?>
                    </td>
                    <td data-label="Localisation">
                        <?php if ($lieu || $dossier['region']): ?>
                            <?php echo sanitize(implode(', ', $lieu) ?: $dossier['region']); ?>
                            <?php if ($lieu && $dossier['region']): ?><span class="cell-sub"><?php echo sanitize($dossier['region']); ?></span><?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted-sgdi">Non précisée</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Statut"><?php echo uiStatutBadge($dossier['statut']); ?></td>
                    <td data-label="Avancement"><?php echo uiWorkflowProgress($dossier['statut']); ?></td>
                    <td class="text-end cell-actions">
                        <div class="btn-group">
                            <?php if ($principale): ?>
                            <a class="btn btn-sm btn-primary" href="<?php echo urlActionDossier($principale['action'], $dossier['id']); ?>"><?php echo sanitize($principale['label']); ?></a>
                            <?php else: ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $url_voir; ?>">Ouvrir</a>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="visually-hidden">Plus d'actions</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo $url_voir; ?>"><i class="fas fa-eye me-2"></i>Voir le dossier</a></li>
                                <?php foreach ($actions as $action): ?>
                                <li><a class="dropdown-item" href="<?php echo urlActionDossier($action['action'], $dossier['id']); ?>"><?php echo sanitize($action['label']); ?></a></li>
                                <?php endforeach; ?>
                                <?php if (!empty($dossier['coordonnees_gps'])): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo url('modules/carte/index.php?dossier=' . (int) $dossier['id']); ?>"><i class="fas fa-map-location-dot me-2"></i>Voir sur la carte</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <span>
            Affichage de <?php echo $offset + 1; ?> à <?php echo min($offset + $limit, $total_dossiers); ?>
            sur <?php echo number_format($total_dossiers, 0, ',', ' '); ?> dossier<?php echo $total_dossiers > 1 ? 's' : ''; ?>
        </span>
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm">
                <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo urlListe(['page' => max(1, $page - 1)]); ?>" aria-label="Page précédente"><i class="fas fa-angle-left"></i></a>
                </li>
                <?php
                $debut = max(1, $page - 2);
                $fin = min($total_pages, $page + 2);
                if ($debut > 1): ?>
                <li class="page-item"><a class="page-link" href="<?php echo urlListe(['page' => 1]); ?>">1</a></li>
                <?php if ($debut > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $debut; $i <= $fin; $i++): ?>
                <li class="page-item<?php echo $i === $page ? ' active' : ''; ?>">
                    <a class="page-link" href="<?php echo urlListe(['page' => $i]); ?>"<?php echo $i === $page ? ' aria-current="page"' : ''; ?>><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($fin < $total_pages): ?>
                <?php if ($fin < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item"><a class="page-link" href="<?php echo urlListe(['page' => $total_pages]); ?>"><?php echo $total_pages; ?></a></li>
                <?php endif; ?>
                <li class="page-item<?php echo $page >= $total_pages ? ' disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo urlListe(['page' => min($total_pages, $page + 1)]); ?>" aria-label="Page suivante"><i class="fas fa-angle-right"></i></a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Les listes déroulantes appliquent le filtre dès qu'on change de valeur
document.querySelectorAll('#form-filtres [data-auto-submit]').forEach(function (s) {
    s.addEventListener('change', function () { s.form.submit(); });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

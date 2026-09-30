<?php
/**
 * Rapprochement GPS des dossiers historiques avec OpenStreetMap
 *
 * - Correspondances sûres : une seule station de la marque dans la localité → validation par lots
 * - À choisir : plusieurs stations possibles (même marque, plus loin, autre marque, sans marque) → choix sur carte
 * - Sans station OSM : position approximative au centre de la localité, par lots
 * - Localité introuvable : saisie manuelle
 * Rien n'est enregistré sans validation ; la position enregistrée est toujours recalculée côté serveur.
 */
require_once '../../includes/auth.php';
require_once '../../includes/ui.php';
require_once '../../includes/geoloc_historique.php';

requireLogin();
if (!hasAnyRole(['admin', 'chef_service'])) {
    redirect(url('dashboard.php'), 'Accès réservé à l\'administrateur et au Chef de Service', 'error');
}

$page_title = 'Rapprochement GPS';
$onglets = ['uniques', 'ambigus', 'aucune', 'introuvables'];
$onglet = in_array($_GET['onglet'] ?? '', $onglets, true) ? $_GET['onglet'] : 'uniques';

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigerCSRF();
    set_time_limit(300);

    // Tout attribuer d'un coup : les erreurs se corrigent ensuite au cas par cas dans Gestion GPS
    if (($_POST['action'] ?? '') === 'attribuer_tout') {
        try {
            $r = geolocAttribuerTout($_SESSION['user_id']);
            redirect(url('modules/admin_gps/index.php?est_historique=1&has_gps=a_verifier'),
                ($r['sure'] + $r['choix_automatique']) . ' station(s) attribuée(s) (dont ' . $r['choix_automatique'] . ' à vérifier), '
                . $r['approximatif'] . ' dossier(s) placé(s) au centre de leur localité.', 'success');
        } catch (Exception $e) {
            error_log('Attribution automatique GPS : ' . $e->getMessage());
            redirect(url('modules/admin_gps/rapprochement.php'), 'L\'attribution automatique a échoué, rien n\'a été modifié.', 'error');
        }
    }
    $propositions = geolocPropositions()['dossiers'];
    $action = $_POST['action'] ?? '';
    $onglet_retour = ['valider_uniques' => 'uniques', 'placer_approx' => 'aucune'][$action] ?? 'ambigus';
    $retour = url('modules/admin_gps/rapprochement.php?onglet=' . $onglet_retour . (isset($_POST['page']) ? '&page=' . (int) $_POST['page'] : ''));

    if ($action === 'valider_uniques' || $action === 'placer_approx') {
        $ok = 0;
        foreach ((array) ($_POST['ids'] ?? []) as $id) {
            $id = (int) $id;
            $p = $propositions[$id] ?? null;
            if (!$p) continue;
            if ($action === 'valider_uniques' && $p['type'] === 'unique' && geolocEnregistrer($id, $p['candidates'][0], GEOLOC_SCORE_UNIQUE, $_SESSION['user_id'])) $ok++;
            if ($action === 'placer_approx' && $p['type'] === 'aucune' && !$p['approximatif'] && $p['localite'] && geolocPlacerApproximatif($id, $p['localite'], $_SESSION['user_id'])) $ok++;
        }
        $message = $action === 'valider_uniques' ? "$ok dossier(s) géolocalisé(s)." : "$ok dossier(s) placé(s) au centre de leur localité (position approximative).";
        redirect($retour, $ok ? $message : 'Aucun dossier sélectionné.', $ok ? 'success' : 'warning');
    }

    if ($action === 'choisir') {
        $id = (int) ($_POST['dossier_id'] ?? 0);
        $i = $_POST['candidate'] ?? '';
        $p = $propositions[$id] ?? null;
        if ($p && $i !== '' && isset($p['candidates'][(int) $i]) && geolocEnregistrer($id, $p['candidates'][(int) $i], GEOLOC_SCORE_CHOIX, $_SESSION['user_id'])) {
            redirect($retour, 'Dossier ' . $p['dossier']['numero'] . ' géolocalisé.', 'success');
        }
        redirect($retour, 'Choisissez une station dans la liste avant d\'enregistrer.', 'warning');
    }

    if ($action === 'ignorer') {
        $id = (int) ($_POST['dossier_id'] ?? 0);
        if (isset($propositions[$id]) && geolocIgnorer($id, $_SESSION['user_id'])) {
            redirect($retour, 'Dossier ' . $propositions[$id]['dossier']['numero'] . ' déplacé dans « Sans station OSM » : position approximative ou relevé sur le terrain.', 'info');
        }
        redirect($retour, 'Action impossible sur ce dossier.', 'error');
    }
    redirect($retour);
}

// ---------- Données ----------
$resultat = geolocPropositions();
$stats = $resultat['stats'];
$par_type = ['unique' => [], 'ambigu' => [], 'aucune' => [], 'localite_introuvable' => []];
foreach ($resultat['dossiers'] as $d) $par_type[$d['type']][] = $d;
$uniques = $par_type['unique'];
$ambigus = $par_type['ambigu'];
$aucune = $par_type['aucune'];
$introuvables = $par_type['localite_introuvable'];
$aucune_a_placer = count(array_filter($aucune, function ($d) { return !$d['approximatif']; }));

$couverture = $pdo->prepare("SELECT COUNT(*) total,
                             SUM(coordonnees_gps IS NOT NULL AND coordonnees_gps <> '' AND (source_gps IS NULL OR source_gps <> ?)) precises,
                             SUM(source_gps = ?) approx
                             FROM dossiers WHERE est_historique = 1");
$couverture->execute([GEOLOC_SOURCE_APPROX, GEOLOC_SOURCE_APPROX]);
$couverture = $couverture->fetch();
$taux = $couverture['total'] ? round(($couverture['precises'] + $couverture['approx']) / $couverture['total'] * 100) : 0;

$par_page = 15;
$page = max(1, (int) ($_GET['page'] ?? 1));
$pages = max(1, (int) ceil(count($ambigus) / $par_page));
$page = min($page, $pages);
$ambigus_page = array_slice($ambigus, ($page - 1) * $par_page, $par_page);
$niveaux = geolocNiveaux();

$formater_distance = function ($m) { return $m < 1000 ? $m . ' m' : number_format($m / 1000, 1, ',', ' ') . ' km'; };

$extra_head = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">';
require_once '../../includes/header.php';

echo uiPageHeader(
    'Rapprochement GPS des dossiers historiques',
    'Positions proposées d\'après OpenStreetMap. Rien n\'est enregistré sans votre validation.',
    [['label' => 'Tableau de bord', 'url' => url('dashboard.php')], ['label' => 'Gestion GPS', 'url' => url('modules/admin_gps/index.php')], ['label' => 'Rapprochement']],
    '<a class="btn btn-ghost" href="' . url('modules/admin_gps/index.php') . '"><i class="fas fa-arrow-left"></i> Gestion GPS</a>'
    . '<form method="post" class="d-inline" onsubmit="return confirm(\'Attribuer automatiquement toutes les correspondances ?\\n\\nPlusieurs candidates : la meilleure station encore libre (marquée « à vérifier »).\\nAucune station : centre de la localité.\\n\\nLes erreurs se corrigent ensuite dans Gestion GPS.\');">'
    . csrfField() . '<input type="hidden" name="action" value="attribuer_tout">'
    . '<button type="submit" class="btn btn-primary"><i class="fas fa-wand-magic-sparkles"></i> Tout attribuer automatiquement</button></form>'
);
?>

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
    <div class="col"><?php echo uiKpiCard('Sur les cartes', ($couverture['precises'] + $couverture['approx']) . ' / ' . $couverture['total'], 'fa-location-dot', 'succes', $taux . ' % · dont ' . (int) $couverture['approx'] . ' approximatives'); ?></div>
    <div class="col"><?php echo uiKpiCard('Correspondances sûres', $stats['unique'], 'fa-circle-check', 'instruction', 'Validation en un clic', url('modules/admin_gps/rapprochement.php')); ?></div>
    <div class="col"><?php echo uiKpiCard('À choisir sur la carte', $stats['ambigu'], 'fa-map-location-dot', 'paiement', 'Plusieurs stations possibles', url('modules/admin_gps/rapprochement.php?onglet=ambigus')); ?></div>
    <div class="col"><?php echo uiKpiCard('Sans station OSM', $stats['aucune'], 'fa-location-crosshairs', 'preparation', $aucune_a_placer . ' à placer au centre de la localité', url('modules/admin_gps/rapprochement.php?onglet=aucune')); ?></div>
    <div class="col"><?php echo uiKpiCard('Localité introuvable', $stats['localite_introuvable'], 'fa-circle-question', 'danger', 'Saisie manuelle', url('modules/admin_gps/rapprochement.php?onglet=introuvables')); ?></div>
</div>

<ul class="nav nav-tabs mb-0" role="tablist">
    <?php foreach (['uniques' => ['Correspondances sûres', count($uniques), 'primary'], 'ambigus' => ['À choisir', count($ambigus), 'warning'],
                    'aucune' => ['Sans station OSM', count($aucune), 'secondary'], 'introuvables' => ['Localité introuvable', count($introuvables), 'danger']] as $code => $o): ?>
    <li class="nav-item"><a class="nav-link<?php echo $onglet === $code ? ' active' : ''; ?>" href="?onglet=<?php echo $code; ?>"><?php echo $o[0]; ?> <span class="badge text-bg-<?php echo $o[2]; ?> ms-1"><?php echo $o[1]; ?></span></a></li>
    <?php endforeach; ?>
</ul>

<?php if ($onglet === 'uniques' || $onglet === 'aucune'):
    $liste = $onglet === 'uniques' ? $uniques : $aucune;
    $action = $onglet === 'uniques' ? 'valider_uniques' : 'placer_approx';
    $selectionnables = $onglet === 'uniques' ? count($uniques) : $aucune_a_placer; ?>
<!-- ================= Correspondances sûres / Sans station OSM ================= -->
<form method="post" class="card" style="border-top-left-radius: 0">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="<?php echo $action; ?>">
    <div class="toolbar">
        <div class="small text-muted-sgdi flex-grow-1">
            <?php if ($onglet === 'uniques'): ?>
            Une seule station de la même marque a été trouvée dans la localité, pour un seul dossier. Vérifiez au besoin sur la carte, puis validez.
            <?php else: ?>
            Aucune station OpenStreetMap ne correspond. Vous pouvez placer ces stations au <strong>centre de leur localité</strong> pour qu'elles apparaissent sur les cartes :
            la position est marquée <strong>approximative</strong>, n'est pas utilisée pour le contrôle des distances et reste à préciser sur le terrain.
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary" id="btn-valider" <?php echo $selectionnables ? '' : 'disabled'; ?>>
            <i class="fas <?php echo $onglet === 'uniques' ? 'fa-check-double' : 'fa-location-crosshairs'; ?>"></i>
            <?php echo $onglet === 'uniques' ? 'Valider la sélection' : 'Placer la sélection (approximatif)'; ?> (<span id="nb-selection"><?php echo $selectionnables; ?></span>)
        </button>
    </div>
    <?php if (!$liste): ?>
        <?php echo uiEmptyState('Rien en attente dans cette catégorie', '', 'fa-circle-check'); ?>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sgdi table-hover table-stack">
            <thead><tr>
                <th style="width: 2.5rem"><input class="form-check-input" type="checkbox" id="tout" checked aria-label="Tout sélectionner"></th>
                <th>Dossier</th><th>Localité (SGDI → OSM)</th>
                <th><?php echo $onglet === 'uniques' ? 'Station OpenStreetMap' : 'Position proposée'; ?></th>
                <th class="text-end"><span class="visually-hidden">Carte</span></th>
            </tr></thead>
            <tbody>
                <?php foreach ($liste as $u): $d = $u['dossier']; $loc = $u['localite'];
                    $point = $onglet === 'uniques' ? $u['candidates'][0] : ['lat' => $loc['lat'], 'lon' => $loc['lon']];
                    $deja = $onglet === 'aucune' && $u['approximatif']; ?>
                <tr>
                    <td><?php if ($deja): ?><i class="fas fa-check text-muted-sgdi" title="Déjà placé (approximatif)"></i><?php else: ?>
                        <input class="form-check-input selection" type="checkbox" name="ids[]" value="<?php echo (int) $d['id']; ?>" checked aria-label="Sélectionner le dossier <?php echo sanitize($d['numero']); ?>"><?php endif; ?></td>
                    <td data-label="Dossier"><a class="cell-main" href="<?php echo url('modules/dossiers/view.php?id=' . (int) $d['id']); ?>"><?php echo sanitize($d['nom_demandeur']); ?></a><span class="cell-sub mono">N° <?php echo sanitize($d['numero']); ?></span></td>
                    <td data-label="Localité"><?php echo sanitize($d['ville']); ?>
                        <span class="cell-sub">→ <?php echo sanitize($loc['nom']); ?> (<?php echo sanitize($loc['region']); ?>)<?php echo $loc['autre_region'] ? ' · <strong>autre région que celle du dossier</strong>' : ''; ?></span></td>
                    <td data-label="Position">
                        <?php if ($onglet === 'uniques'): ?>
                            <?php echo sanitize($point['nom']); ?><span class="cell-sub"><?php echo sanitize($point['marque']); ?> · <?php echo $formater_distance($point['distance']); ?> du centre</span>
                        <?php elseif ($deja): ?>
                            <span class="status-badge phase-preparation">Déjà placé (approximatif)</span>
                        <?php else: ?>
                            Centre de <?php echo sanitize($loc['nom']); ?><span class="cell-sub">Position approximative</span>
                        <?php endif; ?>
                        <?php if ($onglet === 'uniques' && $u['approximatif']): ?><span class="cell-sub">Remplace la position approximative actuelle</span><?php endif; ?>
                    </td>
                    <td class="text-end cell-actions"><a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?php echo $point['lat']; ?>&amp;mlon=<?php echo $point['lon']; ?>#map=<?php echo $onglet === 'uniques' ? 17 : 14; ?>/<?php echo $point['lat']; ?>/<?php echo $point['lon']; ?>"><i class="fas fa-up-right-from-square"></i> Voir</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</form>

<?php elseif ($onglet === 'introuvables'): ?>
<!-- ================= Localité introuvable ================= -->
<div class="card" style="border-top-left-radius: 0">
    <div class="toolbar"><div class="small text-muted-sgdi">La localité saisie n'a pas été retrouvée dans OpenStreetMap (nom vide, lieu non recensé ou orthographe trop éloignée). Saisissez la position à la main, ou corrigez la localité du dossier.</div></div>
    <?php if (!$introuvables): ?>
        <?php echo uiEmptyState('Aucune localité introuvable', '', 'fa-circle-check'); ?>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sgdi table-hover table-stack">
            <thead><tr><th>Dossier</th><th>Localité saisie</th><th>Région</th><th class="text-end"><span class="visually-hidden">Action</span></th></tr></thead>
            <tbody>
                <?php foreach ($introuvables as $u): $d = $u['dossier']; ?>
                <tr>
                    <td data-label="Dossier"><a class="cell-main" href="<?php echo url('modules/dossiers/view.php?id=' . (int) $d['id']); ?>"><?php echo sanitize($d['nom_demandeur']); ?></a><span class="cell-sub mono">N° <?php echo sanitize($d['numero']); ?></span></td>
                    <td data-label="Localité saisie"><?php echo $d['ville'] !== '' && $d['ville'] !== null ? sanitize($d['ville']) : '<span class="text-muted-sgdi">Non renseignée</span>'; ?></td>
                    <td data-label="Région"><?php echo sanitize($d['region']); ?></td>
                    <td class="text-end cell-actions"><a class="btn btn-sm btn-primary" href="<?php echo url('modules/admin_gps/edit_gps.php?id=' . (int) $d['id']); ?>"><i class="fas fa-location-dot"></i> Saisir la position</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ================= À choisir ================= -->
<div class="card mb-3" style="border-top-left-radius: 0"><div class="card-body small text-muted-sgdi">
    Cliquez sur un dossier pour afficher ses stations candidates sur la carte, choisissez la bonne, ou indiquez qu'aucune ne correspond.
    Le niveau de chaque candidate indique sa fiabilité : <strong>même marque</strong> d'abord ; à défaut, <strong>même marque un peu plus loin</strong> ;
    à défaut, les stations d'<strong>une autre marque</strong> ou <strong>sans marque</strong> sur place (station reprise, rebaptisée ou mal renseignée dans OpenStreetMap).
</div></div>

<?php if (!$ambigus): ?>
    <div class="card"><?php echo uiEmptyState('Aucun dossier à choisir', '', 'fa-circle-check'); ?></div>
<?php else: ?>
<div class="row g-3">
    <div class="col-lg-6 d-flex flex-column gap-3" id="liste-ambigus">
        <?php foreach ($ambigus_page as $n => $a): $d = $a['dossier']; ?>
        <form method="post" class="card ambigu" data-index="<?php echo $n; ?>">
            <?php echo csrfField(); ?>
            <input type="hidden" name="dossier_id" value="<?php echo (int) $d['id']; ?>">
            <input type="hidden" name="page" value="<?php echo $page; ?>">
            <div class="card-header">
                <div>
                    <h2 class="card-title-sm"><?php echo sanitize($d['nom_demandeur']); ?> · <?php echo sanitize($d['ville']); ?></h2>
                    <span class="small text-muted-sgdi">N° <?php echo sanitize($d['numero']); ?> · <?php echo sanitize($d['region']); ?> · <?php echo count($a['candidates']); ?> candidate(s)
                        <?php echo $a['localite']['autre_region'] ? ' · localité trouvée dans une autre région (' . sanitize($a['localite']['region']) . ')' : ''; ?>
                        <?php echo $a['approximatif'] ? ' · position approximative actuelle' : ''; ?></span>
                </div>
            </div>
            <div class="card-body pt-2">
                <?php foreach ($a['candidates'] as $i => $c): $niv = $niveaux[$c['niveau']]; ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="candidate" id="c<?php echo (int) $d['id'] . '_' . $i; ?>" value="<?php echo $i; ?>" data-candidate="<?php echo $i; ?>">
                    <label class="form-check-label" for="c<?php echo (int) $d['id'] . '_' . $i; ?>">
                        <span class="badge rounded-pill text-bg-secondary me-1"><?php echo $i + 1; ?></span><?php echo sanitize($c['nom']); ?>
                        <span class="status-badge phase-<?php echo $niv[1]; ?> ms-1"><?php echo $niv[0]; ?><?php echo $c['niveau'] === 'autre_marque' ? ' : ' . sanitize($c['marque']) : ''; ?></span>
                        <span class="text-muted-sgdi small">· <?php echo $formater_distance($c['distance']); ?> du centre</span>
                    </label>
                </div>
                <?php endforeach; ?>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="submit" name="action" value="choisir" class="btn btn-sm btn-primary"><i class="fas fa-check"></i> Enregistrer la station choisie</button>
                    <button type="submit" name="action" value="ignorer" class="btn btn-sm btn-outline-secondary" formnovalidate><i class="fas fa-xmark"></i> Aucune ne correspond</button>
                </div>
            </div>
        </form>
        <?php endforeach; ?>

        <?php if ($pages > 1): ?>
        <nav aria-label="Pagination"><ul class="pagination pagination-sm flex-wrap">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
            <li class="page-item<?php echo $p === $page ? ' active' : ''; ?>"><a class="page-link" href="?onglet=ambigus&amp;page=<?php echo $p; ?>"><?php echo $p; ?></a></li>
            <?php endfor; ?>
        </ul></nav>
        <?php endif; ?>
    </div>
    <div class="col-lg-6">
        <div class="card" style="position: sticky; top: 5rem">
            <div id="carte-candidates" class="map-canvas" style="height: 70vh; border: 0" role="region" aria-label="Stations candidates"></div>
            <div class="map-meta p-2" id="carte-legende">Cliquez sur un dossier pour afficher ses stations candidates.</div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function () {
    'use strict';
    var dossiers = <?php echo json_encode(array_map(function ($a) {
        return ['localite' => [$a['localite']['lat'], $a['localite']['lon'], $a['localite']['nom'], geolocRayon($a['localite']['type'])],
                'candidates' => array_map(function ($c) { return [$c['lat'], $c['lon'], $c['nom'], $c['niveau']]; }, $a['candidates'])];
    }, $ambigus_page), JSON_UNESCAPED_UNICODE); ?>;
    var couleurs = { meme_marque: 'var(--layer-station)', meme_marque_eloignee: 'var(--sgdi-phase-instruction)', autre_marque: 'var(--sgdi-phase-attention)', sans_marque: 'var(--layer-osm)' };
    var carte = L.map('carte-candidates').setView([7.37, 12.35], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© contributeurs OpenStreetMap' }).addTo(carte);
    var calque = L.featureGroup().addTo(carte), marqueurs = [], actif = null;

    function afficher(index) {
        var d = dossiers[index]; if (!d) return;
        actif = index; calque.clearLayers(); marqueurs = [];
        document.querySelectorAll('.ambigu').forEach(function (f) { f.classList.toggle('border-primary', +f.dataset.index === index); });
        L.circle([d.localite[0], d.localite[1]], { radius: d.localite[3], color: '#667eea', weight: 1, fillOpacity: .05, interactive: false }).addTo(calque);
        d.candidates.forEach(function (c, i) {
            var m = L.marker([c[0], c[1]], { icon: L.divIcon({ className: '', html: '<span class="mk-cluster" style="width:26px;height:26px;background:' + couleurs[c[3]] + '">' + (i + 1) + '</span>', iconSize: [26, 26] }) })
                .bindTooltip(c[2]).addTo(calque);
            m.on('click', function () { var r = document.querySelector('.ambigu[data-index="' + index + '"] [data-candidate="' + i + '"]'); if (r) r.checked = true; });
            marqueurs.push(m);
        });
        carte.fitBounds(calque.getBounds(), { padding: [30, 30], maxZoom: 16 });
        document.getElementById('carte-legende').textContent = d.candidates.length + ' candidate(s) autour de ' + d.localite[2] + ' (cercle : zone de recherche). Cliquez sur un numéro pour le sélectionner.';
    }
    document.querySelectorAll('.ambigu').forEach(function (f) {
        f.addEventListener('focusin', function () { if (actif !== +f.dataset.index) afficher(+f.dataset.index); });
        f.addEventListener('click', function () { if (actif !== +f.dataset.index) afficher(+f.dataset.index); });
        f.querySelectorAll('[data-candidate]').forEach(function (r) {
            r.addEventListener('change', function () { var m = marqueurs[+r.dataset.candidate]; if (m) { carte.setView(m.getLatLng(), 17); m.openTooltip(); } });
        });
    });
    afficher(0);
})();
</script>
<?php endif; ?>
<?php endif; ?>

<?php if (($onglet === 'uniques' && $uniques) || ($onglet === 'aucune' && $aucune_a_placer)): ?>
<script>
(function () {
    var cases = document.querySelectorAll('.selection'), tout = document.getElementById('tout');
    function compter() { var n = document.querySelectorAll('.selection:checked').length; document.getElementById('nb-selection').textContent = n; document.getElementById('btn-valider').disabled = !n; }
    tout.addEventListener('change', function () { cases.forEach(function (c) { c.checked = tout.checked; }); compter(); });
    cases.forEach(function (c) { c.addEventListener('change', compter); });
})();
</script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>

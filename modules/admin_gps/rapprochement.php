<?php
/**
 * Rapprochement GPS des dossiers historiques avec OpenStreetMap
 *
 * - Correspondances sûres : une seule station de la marque dans la localité → validation par lots
 * - À choisir : plusieurs stations possibles → choix sur carte, ou « aucune ne correspond »
 * Rien n'est enregistré sans validation ; la position enregistrée est toujours l'une des candidates
 * recalculées côté serveur.
 */
require_once '../../includes/auth.php';
require_once '../../includes/ui.php';
require_once '../../includes/geoloc_historique.php';

requireLogin();
if (!hasAnyRole(['admin', 'chef_service'])) {
    redirect(url('dashboard.php'), 'Accès réservé à l\'administrateur et au Chef de Service', 'error');
}

$page_title = 'Rapprochement GPS';
$onglet = ($_GET['onglet'] ?? '') === 'ambigus' ? 'ambigus' : 'uniques';

// ---------- Actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigerCSRF();
    set_time_limit(120);
    $propositions = geolocPropositions()['dossiers'];
    $action = $_POST['action'] ?? '';
    $retour = url('modules/admin_gps/rapprochement.php?onglet=' . ($action === 'valider_uniques' ? 'uniques' : 'ambigus')
        . (isset($_POST['page']) ? '&page=' . (int) $_POST['page'] : ''));

    if ($action === 'valider_uniques') {
        $ok = 0;
        foreach ((array) ($_POST['ids'] ?? []) as $id) {
            $id = (int) $id;
            $p = $propositions[$id] ?? null;
            if ($p && $p['type'] === 'unique' && geolocEnregistrer($id, $p['candidates'][0], GEOLOC_SCORE_UNIQUE, $_SESSION['user_id'])) {
                $ok++;
            }
        }
        redirect($retour, $ok ? "$ok dossier(s) géolocalisé(s)." : 'Aucun dossier sélectionné.', $ok ? 'success' : 'warning');
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
            redirect($retour, 'Dossier ' . $propositions[$id]['dossier']['numero'] . ' retiré des propositions : position à relever sur le terrain.', 'info');
        }
        redirect($retour, 'Action impossible sur ce dossier.', 'error');
    }
    redirect($retour);
}

// ---------- Données ----------
$resultat = geolocPropositions();
$stats = $resultat['stats'];
$uniques = array_values(array_filter($resultat['dossiers'], function ($d) { return $d['type'] === 'unique'; }));
$ambigus = array_values(array_filter($resultat['dossiers'], function ($d) { return $d['type'] === 'ambigu'; }));

$couverture = $pdo->query("SELECT COUNT(*) total, SUM(coordonnees_gps IS NOT NULL AND coordonnees_gps <> '') geo FROM dossiers WHERE est_historique = 1")->fetch();
$taux = $couverture['total'] ? round($couverture['geo'] / $couverture['total'] * 100) : 0;

$par_page = 15;
$page = max(1, (int) ($_GET['page'] ?? 1));
$pages = max(1, (int) ceil(count($ambigus) / $par_page));
$page = min($page, $pages);
$ambigus_page = array_slice($ambigus, ($page - 1) * $par_page, $par_page);

$extra_head = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">';
require_once '../../includes/header.php';

echo uiPageHeader(
    'Rapprochement GPS des dossiers historiques',
    'Positions proposées d\'après OpenStreetMap : même marque, même localité. Rien n\'est enregistré sans votre validation.',
    [['label' => 'Tableau de bord', 'url' => url('dashboard.php')], ['label' => 'Gestion GPS', 'url' => url('modules/admin_gps/index.php')], ['label' => 'Rapprochement']],
    '<a class="btn btn-ghost" href="' . url('modules/admin_gps/index.php') . '"><i class="fas fa-arrow-left"></i> Gestion GPS</a>'
);
?>

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
    <div class="col"><?php echo uiKpiCard('Dossiers historiques géolocalisés', $couverture['geo'] . ' / ' . $couverture['total'], 'fa-location-dot', 'succes', $taux . ' % de couverture'); ?></div>
    <div class="col"><?php echo uiKpiCard('Correspondances sûres', $stats['unique'], 'fa-circle-check', 'instruction', 'Validation en un clic', url('modules/admin_gps/rapprochement.php')); ?></div>
    <div class="col"><?php echo uiKpiCard('À choisir sur la carte', $stats['ambigu'], 'fa-map-location-dot', 'paiement', 'Plusieurs stations possibles', url('modules/admin_gps/rapprochement.php?onglet=ambigus')); ?></div>
    <div class="col"><?php echo uiKpiCard('Sans station candidate', $stats['aucune'], 'fa-person-walking', 'preparation', 'À relever sur le terrain'); ?></div>
    <div class="col"><?php echo uiKpiCard('Localité introuvable', $stats['localite_introuvable'], 'fa-circle-question', 'danger', 'Nom de localité à corriger'); ?></div>
</div>

<ul class="nav nav-tabs mb-0" role="tablist">
    <li class="nav-item"><a class="nav-link<?php echo $onglet === 'uniques' ? ' active' : ''; ?>" href="?onglet=uniques">Correspondances sûres <span class="badge text-bg-primary ms-1"><?php echo count($uniques); ?></span></a></li>
    <li class="nav-item"><a class="nav-link<?php echo $onglet === 'ambigus' ? ' active' : ''; ?>" href="?onglet=ambigus">À choisir <span class="badge text-bg-warning ms-1"><?php echo count($ambigus); ?></span></a></li>
</ul>

<?php if ($onglet === 'uniques'): ?>
<!-- ================= Correspondances sûres ================= -->
<form method="post" class="card" style="border-top-left-radius: 0">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="valider_uniques">
    <div class="toolbar">
        <div class="small text-muted-sgdi flex-grow-1">Une seule station de la même marque a été trouvée dans la localité, pour un seul dossier. Vérifiez au besoin sur la carte, puis validez.</div>
        <button type="submit" class="btn btn-primary" id="btn-valider" <?php echo $uniques ? '' : 'disabled'; ?>><i class="fas fa-check-double"></i> Valider la sélection (<span id="nb-selection"><?php echo count($uniques); ?></span>)</button>
    </div>
    <?php if (!$uniques): ?>
        <?php echo uiEmptyState('Aucune correspondance sûre en attente', 'Toutes les correspondances uniques ont été traitées. Consultez l\'onglet « À choisir ».', 'fa-circle-check'); ?>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sgdi table-hover table-stack">
            <thead><tr>
                <th style="width: 2.5rem"><input class="form-check-input" type="checkbox" id="tout" checked aria-label="Tout sélectionner"></th>
                <th>Dossier</th><th>Localité (SGDI → OSM)</th><th>Station OpenStreetMap</th><th>Distance du centre</th><th class="text-end"><span class="visually-hidden">Carte</span></th>
            </tr></thead>
            <tbody>
                <?php foreach ($uniques as $u): $c = $u['candidates'][0]; $d = $u['dossier']; ?>
                <tr>
                    <td><input class="form-check-input selection" type="checkbox" name="ids[]" value="<?php echo (int) $d['id']; ?>" checked aria-label="Sélectionner le dossier <?php echo sanitize($d['numero']); ?>"></td>
                    <td data-label="Dossier"><a class="cell-main" href="<?php echo url('modules/dossiers/view.php?id=' . (int) $d['id']); ?>"><?php echo sanitize($d['nom_demandeur']); ?></a><span class="cell-sub mono">N° <?php echo sanitize($d['numero']); ?></span></td>
                    <td data-label="Localité"><?php echo sanitize($d['ville']); ?><span class="cell-sub">→ <?php echo sanitize($u['localite']['nom']); ?> (<?php echo sanitize($d['region']); ?>)</span></td>
                    <td data-label="Station OpenStreetMap"><?php echo sanitize($c['nom']); ?><span class="cell-sub"><?php echo sanitize($c['marque']); ?></span></td>
                    <td data-label="Distance du centre" class="tabular"><?php echo $c['distance'] < 1000 ? $c['distance'] . ' m' : number_format($c['distance'] / 1000, 1, ',', ' ') . ' km'; ?></td>
                    <td class="text-end cell-actions"><a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=<?php echo $c['lat']; ?>&amp;mlon=<?php echo $c['lon']; ?>#map=17/<?php echo $c['lat']; ?>/<?php echo $c['lon']; ?>"><i class="fas fa-up-right-from-square"></i> Voir</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</form>

<?php else: ?>
<!-- ================= À choisir ================= -->
<div class="card mb-3" style="border-top-left-radius: 0"><div class="card-body small text-muted-sgdi">
    Plusieurs stations de la même marque existent dans la localité. Cliquez sur un dossier pour afficher ses candidates sur la carte,
    choisissez la bonne station, ou indiquez qu'aucune ne correspond (le dossier sera alors à géolocaliser sur le terrain).
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
                    <span class="small text-muted-sgdi">N° <?php echo sanitize($d['numero']); ?> · <?php echo sanitize($d['region']); ?> · <?php echo count($a['candidates']); ?> stations candidates</span>
                </div>
            </div>
            <div class="card-body pt-2">
                <?php foreach ($a['candidates'] as $i => $c): ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="candidate" id="c<?php echo (int) $d['id'] . '_' . $i; ?>" value="<?php echo $i; ?>" data-candidate="<?php echo $i; ?>">
                    <label class="form-check-label" for="c<?php echo (int) $d['id'] . '_' . $i; ?>">
                        <span class="badge rounded-pill text-bg-secondary me-1"><?php echo $i + 1; ?></span><?php echo sanitize($c['nom']); ?>
                        <span class="text-muted-sgdi small">· <?php echo $c['distance'] < 1000 ? $c['distance'] . ' m' : number_format($c['distance'] / 1000, 1, ',', ' ') . ' km'; ?> du centre</span>
                    </label>
                </div>
                <?php endforeach; ?>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="submit" name="action" value="choisir" class="btn btn-sm btn-primary"><i class="fas fa-check"></i> Enregistrer la station choisie</button>
                    <button type="submit" name="action" value="ignorer" class="btn btn-sm btn-outline-secondary" formnovalidate><i class="fas fa-person-walking"></i> Aucune ne correspond</button>
                </div>
            </div>
        </form>
        <?php endforeach; ?>

        <?php if ($pages > 1): ?>
        <nav aria-label="Pagination"><ul class="pagination pagination-sm">
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
                'candidates' => array_map(function ($c) { return [$c['lat'], $c['lon'], $c['nom']]; }, $a['candidates'])];
    }, $ambigus_page), JSON_UNESCAPED_UNICODE); ?>;
    var carte = L.map('carte-candidates').setView([7.37, 12.35], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© contributeurs OpenStreetMap' }).addTo(carte);
    var calque = L.featureGroup().addTo(carte), marqueurs = [], actif = null;

    function afficher(index) {
        var d = dossiers[index]; if (!d) return;
        actif = index; calque.clearLayers(); marqueurs = [];
        document.querySelectorAll('.ambigu').forEach(function (f) { f.classList.toggle('border-primary', +f.dataset.index === index); });
        L.circle([d.localite[0], d.localite[1]], { radius: d.localite[3], color: '#667eea', weight: 1, fillOpacity: .05, interactive: false }).addTo(calque);
        d.candidates.forEach(function (c, i) {
            var m = L.marker([c[0], c[1]], { icon: L.divIcon({ className: '', html: '<span class="mk-cluster" style="width:26px;height:26px">' + (i + 1) + '</span>', iconSize: [26, 26] }) })
                .bindTooltip(c[2]).addTo(calque);
            m.on('click', function () { var r = document.querySelector('.ambigu[data-index="' + index + '"] [data-candidate="' + i + '"]'); if (r) r.checked = true; });
            marqueurs.push(m);
        });
        carte.fitBounds(calque.getBounds(), { padding: [30, 30], maxZoom: 16 });
        document.getElementById('carte-legende').textContent = d.candidates.length + ' stations candidates autour de ' + d.localite[2] + ' (cercle : zone de recherche). Cliquez sur un numéro pour le sélectionner.';
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

<?php if ($onglet === 'uniques' && $uniques): ?>
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

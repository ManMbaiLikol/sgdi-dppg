<?php
// Carte des infrastructures - SGDI
// Les données sont chargées par modules/carte/donnees.php et rafraîchies sans recharger la page.
require_once '../../includes/auth.php';
require_once '../../includes/ui.php';

requireLogin();
// Comme la carte d'origine : accessible à tout utilisateur connecté

$page_title = 'Carte des infrastructures';
$peut_synchroniser = hasAnyRole(['admin', 'chef_service']);
$extra_head = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">'
            . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.min.css">';

$actions = '<button class="btn btn-outline-secondary" type="button" id="btn-verifier"><i class="fas fa-location-crosshairs"></i> Vérifier un emplacement</button>'
         . '<button class="btn btn-outline-secondary" type="button" id="btn-mesurer"><i class="fas fa-ruler"></i> Mesurer</button>'
         . '<div class="btn-group">'
         . '<button class="btn btn-primary" type="button" id="btn-actualiser"><i class="fas fa-rotate"></i> Actualiser</button>'
         . ($peut_synchroniser
            ? '<button class="btn btn-primary dropdown-toggle dropdown-toggle-split" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="visually-hidden">Plus d\'options</span></button>'
            . '<ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" id="btn-sync-osm"><i class="fas fa-globe-africa me-2"></i>Synchroniser OpenStreetMap maintenant</button></li></ul>'
            : '')
         . '</div>';

require_once '../../includes/header.php';

echo uiPageHeader(
    'Carte des infrastructures',
    'Dossiers du SGDI et référence OpenStreetMap des points de distribution au Cameroun',
    [['label' => 'Tableau de bord', 'url' => url('dashboard.php')], ['label' => 'Carte des infrastructures']],
    $actions
);
?>

<div class="map-layout">
    <div class="map-panel">
        <div class="card"><div class="card-body">
            <h2 class="card-title-sm mb-2">Couches</h2>

            <div class="layer-group-title">Dossiers du SGDI</div>
            <?php foreach (['station_service' => 'Stations-service', 'point_consommateur' => 'Points consommateurs', 'depot_gpl' => 'Dépôts GPL', 'centre_emplisseur' => 'Centres emplisseurs'] as $code => $libelle): ?>
            <label class="layer-toggle" style="--layer-color: var(--layer-<?php echo ['station_service' => 'station', 'point_consommateur' => 'conso', 'depot_gpl' => 'gpl', 'centre_emplisseur' => 'emplisseur'][$code]; ?>)">
                <input type="checkbox" data-couche="sgdi:<?php echo $code; ?>" checked>
                <span class="layer-dot"<?php echo in_array($code, ['depot_gpl', 'centre_emplisseur'], true) ? ' style="border-radius: 3px"' : ''; ?>></span><?php echo $libelle; ?>
                <span class="layer-count" data-compteur="sgdi:<?php echo $code; ?>">…</span>
            </label>
            <?php endforeach; ?>

            <div class="layer-group-title">Référence OpenStreetMap</div>
            <label class="layer-toggle" style="--layer-color: var(--layer-osm)"><input type="checkbox" data-couche="osm:station" checked><span class="layer-dot"></span>Stations-service<span class="layer-count" data-compteur="osm:station">…</span></label>
            <label class="layer-toggle" style="--layer-color: var(--layer-gpl)"><input type="checkbox" data-couche="osm:gpl" checked><span class="layer-dot"></span>Points de vente GPL<span class="layer-count" data-compteur="osm:gpl">…</span></label>
            <label class="layer-toggle" style="--layer-color: var(--layer-emplisseur)"><input type="checkbox" data-couche="osm:depot" checked><span class="layer-dot" style="border-radius: 3px"></span>Dépôts pétroliers<span class="layer-count" data-compteur="osm:depot">…</span></label>
            <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" role="switch" id="osm-absents">
                <label class="form-check-label small" for="osm-absents">Seulement les stations sans dossier géolocalisé à proximité <span class="badge rounded-pill text-bg-danger" id="nb-absents">…</span></label>
            </div>
            <p class="small text-muted-sgdi mt-2 mb-0" id="couverture"></p>

            <div class="layer-group-title">Contraintes</div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="l-poi">
                <label class="form-check-label" for="l-poi">Points d'intérêt <span class="text-muted-sgdi small" id="nb-poi"></span></label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="l-zones">
                <label class="form-check-label" for="l-zones">Zones de distance minimale</label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="l-densite">
                <label class="form-check-label" for="l-densite">Carte de densité</label>
            </div>
        </div></div>

        <div class="card"><div class="card-body d-flex flex-column gap-2">
            <h2 class="card-title-sm">Filtres</h2>
            <div class="app-search" style="max-width: none">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input id="f-recherche" class="form-control" type="search" placeholder="N°, demandeur, opérateur, ville…" aria-label="Rechercher sur la carte">
            </div>
            <select id="f-region" class="form-select" aria-label="Région"><option value="">Toutes les régions</option></select>
            <select id="f-phase" class="form-select" aria-label="Phase du dossier">
                <option value="">Tous les statuts (SGDI)</option>
                <?php foreach (uiPhases() as $code => $p): ?>
                <option value="<?php echo $code; ?>"><?php echo sanitize($p['label']); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="f-operateur" class="form-select" aria-label="Opérateur ou marque"><option value="">Tous les opérateurs</option></select>
        </div></div>

        <div class="card" id="carte-verif" hidden><div class="card-body">
            <h2 class="card-title-sm mb-2">Vérification d'un emplacement</h2>
            <p class="small text-muted-sgdi mb-2">Cliquez sur la carte ou saisissez des coordonnées.</p>
            <form class="d-flex gap-2 mb-2" id="form-coord">
                <input class="form-control form-control-sm" id="v-lat" inputmode="decimal" placeholder="Latitude (ex. 3.8667)" aria-label="Latitude">
                <input class="form-control form-control-sm" id="v-lon" inputmode="decimal" placeholder="Longitude (ex. 11.5167)" aria-label="Longitude">
                <button class="btn btn-sm btn-primary" type="submit" aria-label="Vérifier ces coordonnées"><i class="fas fa-check"></i></button>
            </form>
            <div id="v-resultat" aria-live="polite"></div>
        </div></div>

        <div class="card"><div class="card-body">
            <h2 class="card-title-sm mb-3">Par région</h2>
            <ul class="region-bars" id="par-region"></ul>
        </div></div>
    </div>

    <div class="d-flex flex-column gap-2" style="min-width: 0">
        <div id="map" class="map-canvas" role="region" aria-label="Carte des infrastructures"></div>
        <div class="map-meta"><span class="map-live"></span><span id="carte-meta">Chargement des données…</span></div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/leaflet.markercluster.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.heat/0.2.0/leaflet-heat.js"></script>
<script>
(function () {
    'use strict';
    var URL_DONNEES = <?php echo json_encode(url('modules/carte/donnees.php')); ?>;
    var URL_REGIONS = <?php echo json_encode(asset('data/cameroun_regions.json')); ?>;
    var URL_DOSSIER = <?php echo json_encode(url('modules/dossiers/view.php?id=')); ?>;
    var CSRF = <?php echo json_encode(generateCSRFToken()); ?>;
    var URL_GPS = <?php echo json_encode(hasAnyRole(['admin', 'chef_service']) ? url('modules/admin_gps/index.php') : ''); ?>;
    var DOSSIER_CIBLE = <?php echo (int) ($_GET['dossier'] ?? 0); ?>;
    var STATUTS = <?php echo json_encode(array_map(function ($s) { return [$s[0], $s[1]]; }, uiTableStatuts()), JSON_UNESCAPED_UNICODE); ?>;
    var TYPES = { station_service: 'Station-service', point_consommateur: 'Point consommateur', depot_gpl: 'Dépôt GPL', centre_emplisseur: 'Centre emplisseur' };
    var CAT_OSM = { station: 'Station-service', gpl: 'Point de vente GPL', depot: 'Dépôt pétrolier' };
    var DISTANCE_URBAINE = 500, DISTANCE_RURALE = 400; // distance minimale entre stations (contraintes_distance_functions.php)
    var RAYON_CORRESPONDANCE = 200;                    // une station OSM à moins de 200 m d'un dossier SGDI est considérée comme connue

    function $(id) { return document.getElementById(id); }
    function esc(t) { var e = document.createElement('span'); e.textContent = t == null ? '' : String(t); return e.innerHTML; }
    function nf(n) { return n.toLocaleString('fr-FR'); }

    var carte = L.map('map', { zoomSnap: .5, minZoom: 5, maxZoom: 19 }).setView([7.37, 12.35], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '© <a href="https://www.openstreetmap.org/copyright">contributeurs OpenStreetMap</a>'
    }).addTo(carte);
    L.control.scale({ imperial: false }).addTo(carte);

    var grappe = L.markerClusterGroup({
        showCoverageOnHover: false, maxClusterRadius: 45, chunkedLoading: true, spiderfyOnMaxZoom: true,
        iconCreateFunction: function (c) {
            var n = c.getChildCount(), s = n < 10 ? 30 : n < 100 ? 38 : n < 500 ? 46 : 54;
            return L.divIcon({ html: '<div class="mk-cluster" style="width:' + s + 'px;height:' + s + 'px">' + n + '</div>', className: '', iconSize: [s, s] });
        }
    }).addTo(carte);
    var couchePoi = L.layerGroup(), coucheZones = L.layerGroup(), coucheDensite = null;
    var D = { sgdi: [], osm: [], poi: [] }, regions = {}, limitesPays = null;

    /* ---------- Contours des régions ---------- */
    fetch(URL_REGIONS).then(function (r) { return r.json(); }).then(function (liste) {
        var groupe = L.featureGroup();
        liste.forEach(function (r) {
            regions[r.nom] = L.polygon(r.ring, { color: '#667eea', weight: 1.5, opacity: .7, fill: false, interactive: false }).addTo(groupe);
        });
        groupe.addTo(carte);
        limitesPays = groupe.getBounds();
        if (!DOSSIER_CIBLE) carte.fitBounds(limitesPays, { padding: [10, 10] });
        remplirRegions();
    }).catch(function () { /* la carte reste utilisable sans les contours */ });

    function remplirRegions() {
        var sel = $('f-region'), actuelle = sel.value;
        var noms = Object.keys(regions).sort(function (a, b) { return a.localeCompare(b, 'fr'); });
        sel.innerHTML = '<option value="">Toutes les régions</option>' + noms.map(function (n) { return '<option>' + esc(n) + '</option>'; }).join('');
        sel.value = actuelle;
    }

    /* ---------- Marqueurs ---------- */
    function marqueurSgdi(p) {
        var s = STATUTS[p[9]] || [p[9], 'preparation'];
        var m = L.marker([p[1], p[2]], { icon: L.divIcon({ className: '', html: '<span class="mk mk-' + p[3] + '"></span>', iconSize: [16, 16], iconAnchor: [8, 8] }) });
        m.bindPopup(function () {
            return '<h3>' + esc(p[5] || 'Demandeur non renseigné') + '</h3>' +
                '<div>' + esc(TYPES[p[3]] || p[3]) + (p[4] ? ' · ' + esc(p[4].charAt(0).toUpperCase() + p[4].slice(1)) : '') + '</div>' +
                (p[6] ? '<div>Opérateur : <strong>' + esc(p[6]) + '</strong></div>' : '') +
                '<div class="text-muted-sgdi">' + esc([p[7], p[8]].filter(Boolean).join(', ')) + '</div>' +
                '<div class="my-2"><span class="status-badge phase-' + s[1] + '">' + esc(s[0]) + '</span></div>' +
                '<div class="d-flex justify-content-between align-items-center gap-2"><span class="small text-muted-sgdi">' + esc(p[10]) + '</span>' +
                '<a class="btn btn-sm btn-primary" href="' + URL_DOSSIER + p[0] + '">Ouvrir le dossier</a></div>';
        });
        m._sgdi = p;
        return m;
    }
    function marqueurOsm(p, absent) {
        var m = L.marker([p[0], p[1]], { icon: L.divIcon({ className: '', html: '<span class="mk mk-osm mk-' + p[2] + (absent ? ' is-absent' : '') + '"></span>', iconSize: [16, 16], iconAnchor: [8, 8] }) });
        m.bindPopup(function () {
            return '<h3>' + esc(p[3] || (CAT_OSM[p[2]] + ' sans nom')) + '</h3>' +
                '<div>' + esc(CAT_OSM[p[2]]) + (p[7] && p[2] === 'station' ? ' · GPL disponible' : '') + '</div>' +
                '<div><strong>' + esc(p[4]) + '</strong></div>' +
                '<div class="text-muted-sgdi">' + esc([p[5], p[6]].filter(Boolean).join(', ')) + '</div>' +
                (absent ? '<div class="verdict phase-danger mt-2">Aucun dossier géolocalisé du SGDI à moins de ' + RAYON_CORRESPONDANCE + ' m.</div>' : '') +
                '<div class="small text-muted-sgdi mt-2">' + p[0].toFixed(5) + ', ' + p[1].toFixed(5) + ' · source OpenStreetMap</div>';
        });
        m._osm = p;
        return m;
    }

    /* ---------- Chargement et rafraîchissement des données ---------- */
    var dernierChargement = null, chargementEnCours = false;
    function charger(manuel) {
        if (chargementEnCours) return Promise.resolve();
        chargementEnCours = true;
        var icone = $('btn-actualiser').querySelector('i');
        if (manuel && icone) icone.classList.add('spin');
        return fetch(URL_DONNEES, { credentials: 'same-origin', cache: manuel ? 'no-store' : 'default' })
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function (json) {
                D.sgdi = json.sgdi.map(marqueurSgdi);
                D.poi = json.poi;
                D.osmMaj = json.osm.maj;
                // Stations OSM sans dossier SGDI proche
                var stationsSgdi = json.sgdi.filter(function (p) { return p[3] === 'station_service'; });
                var absents = 0;
                D.osm = json.osm.points.map(function (p) {
                    var absent = false;
                    if (p[2] === 'station') {
                        absent = !stationsSgdi.some(function (s) {
                            return Math.abs(s[1] - p[0]) < .003 && Math.abs(s[2] - p[1]) < .003 && carte.distance([s[1], s[2]], [p[0], p[1]]) < RAYON_CORRESPONDANCE;
                        });
                        if (absent) absents++;
                    }
                    var m = marqueurOsm(p, absent);
                    m._absent = absent;
                    return m;
                });
                $('nb-absents').textContent = nf(absents);
                afficherCouverture(json.couverture);
                $('nb-poi').textContent = '(' + nf(D.poi.length) + ')';
                remplirOperateurs(json);
                dessinerPoi();
                dernierChargement = new Date();
                filtrer();
                if (DOSSIER_CIBLE) { ouvrirDossier(DOSSIER_CIBLE); DOSSIER_CIBLE = 0; }
            })
            .catch(function () {
                $('carte-meta').textContent = 'Impossible de charger les données de la carte. Vérifiez votre connexion puis cliquez sur « Actualiser ».';
            })
            .finally(function () { chargementEnCours = false; if (icone) icone.classList.remove('spin'); });
    }

    function afficherCouverture(cv) {
        if (!cv || !cv.stations) { $('couverture').textContent = ''; return; }
        var taux = Math.round(cv.geolocalisees / cv.stations * 100);
        $('couverture').innerHTML = 'Couverture GPS du SGDI : <strong>' + nf(cv.geolocalisees) + ' / ' + nf(cv.stations) + ' stations (' + taux + ' %)</strong>.' +
            (taux < 80 ? ' Tant que les dossiers ne sont pas géolocalisés, la comparaison avec OpenStreetMap n\x27est pas significative.' : '') +
            (URL_GPS ? ' <a href="' + URL_GPS + '">Compléter les coordonnées</a>' : '');
    }

    function remplirOperateurs(json) {
        var n = {};
        json.sgdi.forEach(function (p) { if (p[6]) n[p[6]] = (n[p[6]] || 0) + 1; });
        json.osm.points.forEach(function (p) { if (p[4]) n[p[4]] = (n[p[4]] || 0) + 1; });
        var sel = $('f-operateur'), actuel = sel.value;
        sel.innerHTML = '<option value="">Tous les opérateurs</option>' + Object.keys(n).sort(function (a, b) { return n[b] - n[a]; })
            .slice(0, 80).map(function (o) { return '<option value="' + esc(o) + '">' + esc(o) + ' (' + n[o] + ')</option>'; }).join('');
        sel.value = actuel;
    }

    /* ---------- Filtres ---------- */
    function couchesActives() {
        var c = {};
        document.querySelectorAll('[data-couche]').forEach(function (i) { c[i.getAttribute('data-couche')] = i.checked; });
        return c;
    }
    function filtrer(zoomRegion) {
        var c = couchesActives(), region = $('f-region').value, phase = $('f-phase').value,
            operateur = $('f-operateur').value, q = $('f-recherche').value.trim().toLowerCase(), absentsSeuls = $('osm-absents').checked;

        function okSgdi(p, ignorerRegion) {
            return (ignorerRegion || !region || p[8] === region) && (!phase || (STATUTS[p[9]] || [])[1] === phase) &&
                (!operateur || p[6] === operateur) && (!q || (p[10] + ' ' + p[5] + ' ' + p[6] + ' ' + p[7]).toLowerCase().indexOf(q) !== -1);
        }
        function okOsm(p, absent, ignorerRegion) {
            return !phase && (!absentsSeuls || absent) && (ignorerRegion || !region || p[6] === region) &&
                (!operateur || p[4] === operateur) && (!q || (p[3] + ' ' + p[4] + ' ' + p[5]).toLowerCase().indexOf(q) !== -1);
        }

        var visibles = [], compteurs = {}, parRegion = {};
        D.sgdi.forEach(function (m) {
            var p = m._sgdi, cle = 'sgdi:' + p[3];
            if (okSgdi(p, false)) compteurs[cle] = (compteurs[cle] || 0) + 1;
            if (!c[cle]) return;
            if (okSgdi(p, true)) parRegion[p[8]] = (parRegion[p[8]] || 0) + 1;
            if (okSgdi(p, false)) visibles.push(m);
        });
        D.osm.forEach(function (m) {
            var p = m._osm, cle = 'osm:' + p[2];
            if (okOsm(p, m._absent, false)) compteurs[cle] = (compteurs[cle] || 0) + 1;
            if (!c[cle]) return;
            if (okOsm(p, m._absent, true)) parRegion[p[6]] = (parRegion[p[6]] || 0) + 1;
            if (okOsm(p, m._absent, false)) visibles.push(m);
        });

        grappe.clearLayers();
        grappe.addLayers(visibles);
        document.querySelectorAll('[data-compteur]').forEach(function (el) { el.textContent = nf(compteurs[el.getAttribute('data-compteur')] || 0); });

        var noms = Object.keys(parRegion).filter(Boolean).sort(function (a, b) { return parRegion[b] - parRegion[a]; });
        var max = Math.max.apply(null, noms.map(function (n) { return parRegion[n]; }).concat([1]));
        $('par-region').innerHTML = noms.length ? noms.map(function (n) {
            return '<li data-region="' + esc(n) + '"' + (n === region ? ' style="font-weight:700"' : '') + ' title="Zoomer sur la région ' + esc(n) + '"><span>' + esc(n) + '</span>' +
                '<span class="bar" style="width:' + (parRegion[n] / max * 100).toFixed(1) + '%"></span><span class="val">' + nf(parRegion[n]) + '</span></li>';
        }).join('') : '<li class="text-muted-sgdi">Aucun point</li>';

        if (coucheDensite) { carte.removeLayer(coucheDensite); coucheDensite = null; }
        if ($('l-densite').checked && L.heatLayer) {
            coucheDensite = L.heatLayer(visibles.map(function (m) { var ll = m.getLatLng(); return [ll.lat, ll.lng, .6]; }),
                { radius: 22, blur: 18, minOpacity: .25, gradient: { .3: '#667eea', .6: '#f39c12', 1: '#e74c3c' } }).addTo(carte);
        }
        if (zoomRegion) {
            var b = region && regions[region] ? regions[region].getBounds() : limitesPays;
            if (b) carte.flyToBounds(b, { padding: [20, 20], duration: .6 });
        }

        var h = dernierChargement ? dernierChargement.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '';
        var osm = D.osmMaj ? new Date(D.osmMaj).toLocaleDateString('fr-FR') : 'inconnue';
        $('carte-meta').textContent = nf(visibles.length) + ' points affichés · dossiers SGDI actualisés à ' + h +
            ' (automatiquement toutes les 5 min) · référence OpenStreetMap du ' + osm;
    }

    /* ---------- Points d'intérêt et zones de contrainte ---------- */
    function dessinerPoi() {
        couchePoi.clearLayers(); coucheZones.clearLayers();
        D.poi.forEach(function (p) {
            L.marker([p[0], p[1]], { icon: L.divIcon({ className: '', html: '<span class="mk mk-poi" style="--poi-couleur:' + esc(p[6]) + '"></span>', iconSize: [14, 14], iconAnchor: [7, 7] }) })
                .bindPopup('<h3>' + esc(p[2]) + '</h3><div>' + esc(p[3]) + '</div><div class="small mt-1">Distance minimale : <strong>' + p[4] + ' m</strong> (' + p[5] + ' m en zone rurale)</div>')
                .addTo(couchePoi);
            L.circle([p[0], p[1]], { radius: p[4], className: 'zone-contrainte', interactive: false }).addTo(coucheZones);
        });
        D.sgdi.forEach(function (m) {
            if (m._sgdi[3] === 'station_service') L.circle(m.getLatLng(), { radius: DISTANCE_URBAINE, className: 'zone-contrainte', interactive: false }).addTo(coucheZones);
        });
    }
    $('l-poi').addEventListener('change', function () { this.checked ? couchePoi.addTo(carte) : carte.removeLayer(couchePoi); });
    $('l-zones').addEventListener('change', function () { this.checked ? coucheZones.addTo(carte) : carte.removeLayer(coucheZones); });

    /* ---------- Vérifier un emplacement ---------- */
    var mode = null, traceVerif = [], mesure = { points: [], calques: [] };
    function changerMode(m) {
        mode = mode === m ? null : m;
        $('btn-verifier').classList.toggle('active', mode === 'verifier');
        $('btn-mesurer').classList.toggle('active', mode === 'mesurer');
        document.querySelector('.map-layout').classList.toggle('map-mode-clic', !!mode);
        if (mode === 'verifier') $('carte-verif').hidden = false;
        if (mode !== 'mesurer') effacerMesure();
    }
    $('btn-verifier').addEventListener('click', function () { changerMode('verifier'); });
    $('btn-mesurer').addEventListener('click', function () { changerMode('mesurer'); });

    function verifier(latlng) {
        traceVerif.forEach(function (z) { carte.removeLayer(z); });
        traceVerif = [L.circle(latlng, { radius: DISTANCE_URBAINE, className: 'zone-urbaine', interactive: false }).addTo(carte),
                      L.circle(latlng, { radius: DISTANCE_RURALE, className: 'zone-rurale', interactive: false }).addTo(carte),
                      L.marker(latlng).addTo(carte)];
        // Stations : dossiers SGDI + stations OSM absentes du SGDI
        var stations = D.sgdi.filter(function (m) { return m._sgdi[3] === 'station_service'; }).map(function (m) { return { nom: m._sgdi[5] + ' (' + m._sgdi[10] + ')', ll: m.getLatLng() }; })
            .concat(D.osm.filter(function (m) { return m._absent; }).map(function (m) { return { nom: (m._osm[3] || 'Station') + ' (OpenStreetMap)', ll: m.getLatLng() }; }));
        var proches = stations.map(function (s) { return { nom: s.nom, d: carte.distance(latlng, s.ll) }; })
            .filter(function (x) { return x.d < DISTANCE_URBAINE; }).sort(function (a, b) { return a.d - b.d; });
        var poi = D.poi.map(function (p) { return { nom: p[2] + ' (' + p[3] + ')', d: carte.distance(latlng, [p[0], p[1]]), min: p[4], minRural: p[5] }; })
            .filter(function (x) { return x.d < x.min; }).sort(function (a, b) { return a.d - b.d; });

        var nonConformeRural = proches.some(function (x) { return x.d < DISTANCE_RURALE; }) || poi.some(function (x) { return x.d < x.minRural; });
        var nonConformeUrbain = proches.length > 0 || poi.length > 0;
        var phase = !nonConformeUrbain ? 'succes' : nonConformeRural ? 'danger' : 'attention';
        var titre = !nonConformeUrbain ? 'Emplacement conforme : aucune station à moins de 500 m ni point d\'intérêt dans sa zone de protection.'
            : nonConformeRural ? 'Non conforme, en zone urbaine comme en zone rurale.'
            : 'Non conforme en zone urbaine (500 m), conforme en zone rurale (400 m).';
        var liste = proches.slice(0, 5).map(function (x) { return '<li>' + esc(x.nom) + ' : ' + Math.round(x.d) + ' m (manque ' + (DISTANCE_URBAINE - Math.round(x.d)) + ' m)</li>'; })
            .concat(poi.slice(0, 5).map(function (x) { return '<li>' + esc(x.nom) + ' : ' + Math.round(x.d) + ' m (minimum ' + x.min + ' m)</li>'; }));
        $('v-resultat').innerHTML = '<div class="verdict phase-' + phase + '"><strong>' + esc(titre) + '</strong>' + (liste.length ? '<ul>' + liste.join('') + '</ul>' : '') + '</div>' +
            '<p class="small text-muted-sgdi mt-2 mb-0">Point vérifié : ' + latlng.lat.toFixed(5) + ', ' + latlng.lng.toFixed(5) + '</p>';
        $('v-lat').value = latlng.lat.toFixed(6); $('v-lon').value = latlng.lng.toFixed(6);
        carte.flyToBounds(traceVerif[0].getBounds(), { padding: [40, 40], maxZoom: 16, duration: .6 });
    }
    $('form-coord').addEventListener('submit', function (e) {
        e.preventDefault();
        var lat = parseFloat($('v-lat').value.replace(',', '.')), lon = parseFloat($('v-lon').value.replace(',', '.'));
        if (isNaN(lat) || isNaN(lon) || lat < 1.5 || lat > 13.5 || lon < 8 || lon > 16.5) {
            $('v-resultat').innerHTML = '<div class="verdict phase-danger">Coordonnées invalides : la latitude doit être entre 1,5 et 13,5 et la longitude entre 8 et 16,5 (Cameroun).</div>';
            return;
        }
        verifier(L.latLng(lat, lon));
    });

    /* ---------- Mesurer une distance ---------- */
    function effacerMesure() { mesure.calques.forEach(function (c) { carte.removeLayer(c); }); mesure = { points: [], calques: [] }; }
    function mesurer(latlng) {
        mesure.points.push(latlng);
        mesure.calques.push(L.circleMarker(latlng, { radius: 5, color: '#667eea', fillOpacity: 1 }).addTo(carte));
        if (mesure.points.length > 1) {
            var total = 0;
            for (var i = 1; i < mesure.points.length; i++) total += carte.distance(mesure.points[i - 1], mesure.points[i]);
            mesure.calques.push(L.polyline(mesure.points.slice(-2), { className: 'trace-mesure' }).addTo(carte));
            L.popup({ closeButton: false, autoClose: false }).setLatLng(latlng)
                .setContent('<strong>' + (total < 1000 ? Math.round(total) + ' m' : (total / 1000).toFixed(2).replace('.', ',') + ' km') + '</strong><div class="small text-muted-sgdi">Cliquez à nouveau sur « Mesurer » pour effacer</div>')
                .openOn(carte);
        }
    }
    carte.on('click', function (e) { if (mode === 'verifier') verifier(e.latlng); else if (mode === 'mesurer') mesurer(e.latlng); });

    /* ---------- Ouvrir un dossier depuis la liste (?dossier=ID) ---------- */
    function ouvrirDossier(id) {
        var m = D.sgdi.filter(function (x) { return x._sgdi[0] === id; })[0];
        if (!m) { $('carte-meta').textContent = 'Ce dossier n\'a pas de coordonnées GPS exploitables.'; return; }
        grappe.zoomToShowLayer(m, function () { m.openPopup(); });
    }

    /* ---------- Événements ---------- */
    document.querySelectorAll('[data-couche]').forEach(function (i) { i.addEventListener('change', function () { filtrer(); }); });
    ['osm-absents', 'l-densite', 'f-phase', 'f-operateur'].forEach(function (id) { $(id).addEventListener('change', function () { filtrer(); }); });
    $('osm-absents').addEventListener('change', function () {
        if (this.checked) document.querySelector('[data-couche="osm:station"]').checked = true;
        filtrer();
    });
    $('f-region').addEventListener('change', function () { filtrer(true); });
    var minuterie;
    $('f-recherche').addEventListener('input', function () { clearTimeout(minuterie); minuterie = setTimeout(filtrer, 250); });
    $('par-region').addEventListener('click', function (e) {
        var li = e.target.closest('li[data-region]'); if (!li) return;
        var r = li.getAttribute('data-region');
        $('f-region').value = $('f-region').value === r ? '' : r;
        filtrer(true);
    });
    $('btn-actualiser').addEventListener('click', function () { charger(true); });

    var btnSync = $('btn-sync-osm');
    if (btnSync) btnSync.addEventListener('click', function () {
        $('carte-meta').textContent = 'Synchronisation avec OpenStreetMap en cours (jusqu\'à 2 minutes)…';
        var corps = new URLSearchParams({ action: 'synchroniser_osm', csrf_token: CSRF });
        fetch(URL_DONNEES, { method: 'POST', body: corps, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok) throw new Error(j.erreur || 'Erreur');
                return charger(true).then(function () {
                    $('carte-meta').textContent = 'Référence OpenStreetMap mise à jour : ' + nf(j.stats.station || 0) + ' stations, ' + nf(j.stats.gpl || 0) + ' points GPL, ' + nf(j.stats.depot || 0) + ' dépôts.';
                });
            })
            .catch(function (e) { $('carte-meta').textContent = e.message || 'La synchronisation a échoué.'; });
    });

    // Rafraîchissement automatique toutes les 5 minutes, seulement si l'onglet est visible
    setInterval(function () { if (!document.hidden) charger(false); }, 5 * 60 * 1000);

    charger(false);
})();
</script>

<?php require_once '../../includes/footer.php'; ?>

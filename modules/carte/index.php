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
            . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.min.css">'
            . '<link rel="stylesheet" href="' . asset('css/carte-marqueurs.css') . '">';

$actions = '<button class="btn btn-outline-secondary" type="button" id="btn-verifier" aria-pressed="false"><i class="fas fa-location-crosshairs"></i> Vérifier un emplacement</button>'
         . '<button class="btn btn-outline-secondary" type="button" id="btn-mesurer" aria-pressed="false"><i class="fas fa-ruler"></i> Mesurer une distance</button>'
         . '<div class="btn-group">'
         . '<button class="btn btn-primary" type="button" id="btn-actualiser"><i class="fas fa-rotate"></i> Actualiser</button>'
         . ($peut_synchroniser
            ? '<button class="btn btn-primary dropdown-toggle dropdown-toggle-split" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="visually-hidden">Plus d\'options</span></button>'
            . '<ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" id="btn-sync-osm"><i class="fas fa-globe-africa me-2"></i>Synchroniser OpenStreetMap maintenant</button></li></ul>'
            : '')
         . '</div>';

// Types d'infrastructures du SGDI : code => [libellé, variable de couleur, pictogramme]
$types_sgdi = [
    'station_service' => ['Stations-service', 'station', 'fa-gas-pump'],
    'point_consommateur' => ['Points consommateurs', 'conso', 'fa-industry'],
    'depot_gpl' => ['Dépôts GPL', 'gpl', 'fa-fire-flame-simple'],
    'centre_emplisseur' => ['Centres emplisseurs', 'emplisseur', 'fa-fill-drip'],
];
$types_osm = [
    'station' => ['Stations-service', 'osm', ''],
    'gpl' => ['Points de vente GPL', 'gpl', ''],
    'depot' => ['Dépôts pétroliers', 'emplisseur', ''],
];

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

        <!-- Outil actif : vérification d'un emplacement ou mesure -->
        <div class="card carte-outil" id="carte-outil" hidden><div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <h2 class="card-title-sm mb-0" id="outil-titre"></h2>
                <button type="button" class="btn-close" id="outil-fermer" aria-label="Fermer l'outil"></button>
            </div>

            <div id="outil-verifier" hidden>
                <ol class="etapes-outil">
                    <li>Choisissez le type de zone du projet.</li>
                    <li>Cliquez sur la carte à l'emplacement projeté, ou saisissez ses coordonnées.</li>
                    <li>Faites glisser le repère rouge pour ajuster : le résultat se met à jour.</li>
                </ol>
                <div class="btn-group btn-group-sm w-100 mb-2" role="group" aria-label="Type de zone">
                    <input type="radio" class="btn-check" name="v-zone" id="v-zone-u" value="urbaine" checked>
                    <label class="btn btn-outline-primary" for="v-zone-u">Urbaine · 500 m</label>
                    <input type="radio" class="btn-check" name="v-zone" id="v-zone-r" value="rurale">
                    <label class="btn btn-outline-primary" for="v-zone-r">Rurale · 400 m</label>
                </div>
                <form class="d-flex gap-2 mb-2" id="form-coord">
                    <input class="form-control form-control-sm" id="v-lat" inputmode="decimal" placeholder="Latitude (ex. 3.8667)" aria-label="Latitude">
                    <input class="form-control form-control-sm" id="v-lon" inputmode="decimal" placeholder="Longitude (ex. 11.5167)" aria-label="Longitude">
                    <button class="btn btn-sm btn-primary" type="submit" aria-label="Vérifier ces coordonnées"><i class="fas fa-check"></i></button>
                </form>
                <div id="v-resultat" aria-live="polite"></div>
            </div>

            <div id="outil-mesurer" hidden>
                <ol class="etapes-outil">
                    <li>Cliquez sur un premier point : sur la carte ou directement sur une station.</li>
                    <li>Cliquez sur un second point : la distance s'affiche sur le segment.</li>
                    <li>Continuez pour mesurer un trajet en plusieurs segments.</li>
                </ol>
                <div class="mesure-total"><span class="text-muted-sgdi small">Distance totale</span><strong id="m-total">—</strong></div>
                <div id="m-points" class="small text-muted-sgdi mb-2"></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="m-annuler" disabled><i class="fas fa-rotate-left"></i> Dernier point</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="m-effacer" disabled><i class="fas fa-eraser"></i> Effacer</button>
                </div>
            </div>
        </div></div>

        <!-- Recherche et filtres -->
        <div class="card"><div class="card-body d-flex flex-column gap-2">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="card-title-sm mb-0">Rechercher</h2>
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="f-reinit" hidden>
                    <i class="fas fa-filter-circle-xmark"></i> Réinitialiser <span class="badge rounded-pill text-bg-primary" id="nb-filtres"></span>
                </button>
            </div>
            <div class="app-search" style="max-width: none">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input id="f-recherche" class="form-control" type="search" placeholder="N°, demandeur, marque, ville, quartier…" aria-label="Rechercher sur la carte" aria-describedby="aide-recherche">
            </div>
            <div class="form-text mt-0" id="aide-recherche">Entrée : zoomer sur les résultats.</div>
            <select id="f-region" class="form-select form-select-sm" aria-label="Région"><option value="">Toutes les régions</option></select>
            <select id="f-marque" class="form-select form-select-sm" aria-label="Marque ou opérateur"><option value="">Toutes les marques</option></select>
            <select id="f-phase" class="form-select form-select-sm" aria-label="Statut du dossier">
                <option value="">Tous les statuts</option>
                <?php foreach (uiPhases() as $code => $p): ?>
                <option value="<?php echo $code; ?>"><?php echo sanitize($p['label']); ?></option>
                <?php endforeach; ?>
            </select>
            <div>
                <div class="filtre-libelle">Origine du dossier</div>
                <div class="btn-group btn-group-sm w-100" role="group" aria-label="Origine du dossier">
                    <?php foreach (['' => 'Tous', 'historique' => 'Historiques', 'circuit' => 'Circuit SGDI'] as $v => $l): ?>
                    <input type="radio" class="btn-check" name="f-origine" id="f-origine-<?php echo $v ?: 'tous'; ?>" value="<?php echo $v; ?>" <?php echo $v === '' ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-secondary" for="f-origine-<?php echo $v ?: 'tous'; ?>"><?php echo $l; ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <div class="filtre-libelle">Position GPS</div>
                <div class="btn-group btn-group-sm w-100" role="group" aria-label="Précision de la position GPS">
                    <?php foreach (['' => 'Toutes', 'precise' => 'Précises', 'approx' => 'Approx.'] as $v => $l): ?>
                    <input type="radio" class="btn-check" name="f-precision" id="f-precision-<?php echo $v ?: 'toutes'; ?>" value="<?php echo $v; ?>" <?php echo $v === '' ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-secondary" for="f-precision-<?php echo $v ?: 'toutes'; ?>"><?php echo $l; ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div></div>

        <!-- Types d'infrastructures -->
        <div class="card"><div class="card-body">
            <h2 class="card-title-sm mb-2">Types d'infrastructures</h2>

            <div class="type-groupe-tete">
                <span class="layer-group-title">Dossiers du SGDI</span>
                <span class="type-actions"><button type="button" data-tout="sgdi">Tout</button><button type="button" data-aucun="sgdi">Aucun</button></span>
            </div>
            <div class="type-list">
                <?php foreach ($types_sgdi as $code => $t): ?>
                <label class="type-row" style="--layer-color: var(--layer-<?php echo $t[1]; ?>)">
                    <input type="checkbox" data-couche="sgdi:<?php echo $code; ?>" checked>
                    <span class="type-apercu"><span class="pin pin-<?php echo $code; ?>"><i class="fas <?php echo $t[2]; ?>"></i></span></span>
                    <span class="type-libelle"><?php echo $t[0]; ?></span>
                    <span class="layer-count" data-compteur="sgdi:<?php echo $code; ?>">…</span>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="type-groupe-tete mt-3">
                <span class="layer-group-title">Référence OpenStreetMap</span>
                <span class="type-actions"><button type="button" data-tout="osm">Tout</button><button type="button" data-aucun="osm">Aucun</button></span>
            </div>
            <div class="type-list">
                <?php foreach ($types_osm as $code => $t): ?>
                <label class="type-row" style="--layer-color: var(--layer-<?php echo $t[1]; ?>)">
                    <input type="checkbox" data-couche="osm:<?php echo $code; ?>" checked>
                    <span class="type-apercu"><span class="mk-osm mk-<?php echo $code; ?>"></span></span>
                    <span class="type-libelle"><?php echo $t[0]; ?></span>
                    <span class="layer-count" data-compteur="osm:<?php echo $code; ?>">…</span>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" role="switch" id="osm-absents">
                <label class="form-check-label small" for="osm-absents">Seulement les stations OSM absentes du SGDI <span class="badge rounded-pill text-bg-danger" id="nb-absents">…</span></label>
            </div>
            <p class="small text-muted-sgdi mt-2 mb-0" id="couverture"></p>
        </div></div>

        <!-- Couches complémentaires -->
        <div class="card"><div class="card-body">
            <h2 class="card-title-sm mb-2">Affichage</h2>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="l-zones">
                <label class="form-check-label" for="l-zones">Zones de protection des stations <span class="text-muted-sgdi small">(500 m, 400 m en zone rurale)</span></label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="l-poi">
                <label class="form-check-label" for="l-poi">Points d'intérêt protégés <span class="text-muted-sgdi small" id="nb-poi"></span></label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="l-densite">
                <label class="form-check-label" for="l-densite">Carte de densité</label>
            </div>
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
<script src="<?php echo asset('js/carte-groupes.js'); ?>"></script>
<script>
(function () {
    'use strict';
    var URL_DONNEES = <?php echo json_encode(url('modules/carte/donnees.php')); ?>;
    var URL_REGIONS = <?php echo json_encode(asset('data/cameroun_regions.json')); ?>;
    var URL_DOSSIER = <?php echo json_encode(url('modules/dossiers/view.php?id=')); ?>;
    var CSRF = <?php echo json_encode(generateCSRFToken()); ?>;
    var URL_GPS = <?php echo json_encode(hasAnyRole(['admin', 'chef_service']) ? url('modules/admin_gps/rapprochement.php') : ''); ?>;
    var DOSSIER_CIBLE = <?php echo (int) ($_GET['dossier'] ?? 0); ?>;
    var STATUTS = <?php echo json_encode(array_map(function ($s) { return [$s[0], $s[1]]; }, uiTableStatuts()), JSON_UNESCAPED_UNICODE); ?>;
    var TYPES = { station_service: 'Station-service', point_consommateur: 'Point consommateur', depot_gpl: 'Dépôt GPL', centre_emplisseur: 'Centre emplisseur' };
    var CAT_OSM = { station: 'Station-service', gpl: 'Point de vente GPL', depot: 'Dépôt pétrolier' };
    var DISTANCE = { urbaine: 500, rurale: 400 };      // distance minimale entre stations (contraintes_distance_functions.php)
    // Stations prises en compte pour les distances (comme verifierDistanceStations côté serveur)
    var STATUTS_EXISTANTS = ['autorise', 'historique_autorise'];
    var STATUTS_INSTRUCTION = ['paye', 'en_huitaine', 'analyse_daj', 'inspecte', 'valide', 'validation_commission',
                               'visa_chef_service', 'visa_sous_directeur', 'visa_directeur', 'decide'];
    var RAYON_FUSION = 30;                             // station OSM à moins de 30 m d'une station existante : même station, un seul marqueur
    var RAYON_CORRESPONDANCE = 200;                    // une station OSM à moins de 200 m d'un dossier SGDI est considérée comme connue
    var CLE_PREFERENCES = 'sgdi-carte-couches';

    // Index des colonnes du format compact (donnees.php)
    var I = { id: 0, lat: 1, lon: 2, type: 3, nature: 4, nom: 5, operateur: 6, ville: 7, region: 8, statut: 9, numero: 10,
              approx: 11, existante: 12, ancien: 13, quartier: 14, marque: 15, historique: 16, rurale: 17 };

    function $(id) { return document.getElementById(id); }
    function esc(t) { var e = document.createElement('span'); e.textContent = t == null ? '' : String(t); return e.innerHTML; }
    function nf(n) { return n.toLocaleString('fr-FR'); }
    function fd(m) { return m < 1000 ? Math.round(m) + ' m' : (m / 1000).toFixed(2).replace('.', ',') + ' km'; }
    function sansAccents(t) { return String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }

    var carte = L.map('map', { zoomSnap: .5, minZoom: 5, maxZoom: 19 }).setView([7.37, 12.35], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '© <a href="https://www.openstreetmap.org/copyright">contributeurs OpenStreetMap</a>'
    }).addTo(carte);
    L.control.scale({ imperial: false, maxWidth: 160 }).addTo(carte);

    var grappe = L.markerClusterGroup({
        showCoverageOnHover: false, maxClusterRadius: 45, chunkedLoading: true, spiderfyOnMaxZoom: true,
        iconCreateFunction: sgdiIconeGrappe // un marqueur de localité compte pour tous les dossiers qu'il regroupe
    }).addTo(carte);
    var couchePoi = L.layerGroup(), coucheZones = L.layerGroup(), coucheDensite = null;
    var D = { sgdi: [], osm: [], poi: [] }, regions = {}, limitesPays = null, derniersVisibles = [];

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
        var s = STATUTS[p[I.statut]] || [p[I.statut], 'preparation'];
        var m = L.marker([p[I.lat], p[I.lon]], { icon: sgdiIcone(p[I.type], p[I.approx]), riseOnHover: true });
        m.bindPopup(function () {
            return '<h3>' + esc(p[I.nom] || 'Demandeur non renseigné') + '</h3>' +
                '<div>' + esc(TYPES[p[I.type]] || p[I.type]) + (p[I.nature] ? ' · ' + esc(p[I.nature].charAt(0).toUpperCase() + p[I.nature].slice(1)) : '') + '</div>' +
                (p[I.operateur] ? '<div>Opérateur : <strong>' + esc(p[I.operateur]) + '</strong></div>' : '') +
                (p[I.ancien] ? '<div class="small">Anciennement : ' + esc(p[I.ancien]) + '</div>' : '') +
                '<div class="text-muted-sgdi">' + esc([p[I.quartier], p[I.ville], p[I.region]].filter(Boolean).join(', ')) + '</div>' +
                '<div class="my-2"><span class="status-badge phase-' + s[1] + '">' + esc(s[0]) + '</span></div>' +
                (p[I.approx] ? '<div class="verdict phase-attention mb-2">Position approximative : centre de la localité, à préciser sur le terrain.</div>' : '') +
                (m._fusion ? '<div class="small mb-2"><i class="fas fa-link"></i> Même station dans OpenStreetMap : <strong>' + esc(m._fusion[3] || 'sans nom') + '</strong> (' + esc(m._fusion[4]) + ')</div>' : '') +
                '<div class="d-flex justify-content-between align-items-center gap-2"><span class="small text-muted-sgdi">' + esc(p[I.numero]) + '</span>' +
                '<a class="btn btn-sm btn-primary" href="' + URL_DOSSIER + p[I.id] + '">Ouvrir le dossier</a></div>';
        });
        m._sgdi = p;
        return m;
    }
    // Dossiers placés au centre de la même localité : un seul marqueur qui les liste,
    // au lieu d'une pile de points aux coordonnées identiques
    var groupesApprox = [];
    function regrouperApprox(liste) {
        var groupes = {}, res = [];
        liste.forEach(function (m) {
            var p = m._sgdi;
            if (!p || !p[I.approx]) { res.push(m); return; }
            var k = p[I.lat] + ',' + p[I.lon];
            (groupes[k] = groupes[k] || []).push(m);
        });
        groupesApprox = [];
        Object.keys(groupes).forEach(function (k) {
            var g = groupes[k];
            if (g.length === 1) { res.push(g[0]); return; }
            var m = marqueurGroupe(g);
            groupesApprox.push(m);
            res.push(m);
        });
        return res;
    }
    function marqueurGroupe(g) {
        var p0 = g[0]._sgdi, n = g.length;
        var m = L.marker([p0[I.lat], p0[I.lon]], { icon: sgdiIconeGroupe(n) });
        m.bindPopup(function () {
            return '<h3>' + n + ' dossiers · ' + esc(p0[I.ville] || 'localité') + '</h3>' +
                '<div class="verdict phase-attention mb-2">Positions approximatives : centre de la localité, à préciser sur le terrain.</div>' +
                '<ul class="liste-groupe">' + g.map(function (x) {
                    var p = x._sgdi, st = STATUTS[p[I.statut]] || [p[I.statut], 'preparation'];
                    return '<li><a href="' + URL_DOSSIER + p[I.id] + '"><strong>' + esc(p[I.nom] || 'Demandeur non renseigné') + '</strong></a>' +
                        '<span class="small text-muted-sgdi">' + esc(p[I.numero]) + (p[I.operateur] ? ' · ' + esc(p[I.operateur]) : '') + ' · ' + esc(st[0]) + '</span></li>';
                }).join('') + '</ul>';
        }, { maxWidth: 340 });
        m._poids = n;
        m._membres = g.map(function (x) { return x._sgdi[I.id]; });
        return m;
    }
    function marqueurOsm(p, absent) {
        var m = L.marker([p[0], p[1]], { icon: L.divIcon({ className: '', html: '<span class="mk-osm mk-' + p[2] + (absent ? ' is-absent' : '') + '"></span>', iconSize: [11, 11], iconAnchor: [5.5, 5.5] }) });
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
                var stationsSgdi = json.sgdi.filter(function (p) { return p[I.type] === 'station_service' && !p[I.approx]; });
                // Stations existantes calées sur OpenStreetMap : la station OSM et le dossier ne forment qu'un point
                var existantes = D.sgdi.filter(function (m) { var p = m._sgdi; return p[I.type] === 'station_service' && !p[I.approx] && p[I.existante]; });
                var absents = 0, fusions = 0;
                D.osm = json.osm.points.map(function (p) {
                    var absent = false;
                    if (p[2] === 'station') {
                        var h = existantes.filter(function (x) {
                            var s = x._sgdi;
                            return !x._fusion && Math.abs(s[I.lat] - p[0]) < .0005 && Math.abs(s[I.lon] - p[1]) < .0005 && carte.distance([s[I.lat], s[I.lon]], [p[0], p[1]]) < RAYON_FUSION;
                        })[0];
                        if (h) { h._fusion = p; fusions++; return null; }
                        absent = !stationsSgdi.some(function (s) {
                            return Math.abs(s[I.lat] - p[0]) < .003 && Math.abs(s[I.lon] - p[1]) < .003 && carte.distance([s[I.lat], s[I.lon]], [p[0], p[1]]) < RAYON_CORRESPONDANCE;
                        });
                        if (absent) absents++;
                    }
                    var m = marqueurOsm(p, absent);
                    m._absent = absent;
                    return m;
                }).filter(Boolean);
                D.fusions = fusions;
                $('nb-absents').textContent = nf(absents);
                afficherCouverture(json.couverture);
                $('nb-poi').textContent = '(' + nf(D.poi.length) + ')';
                remplirMarques();
                dessinerPoi();
                dernierChargement = new Date();
                filtrer();
                if (verif.point) verifier(verif.point, true);
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
            (D.fusions ? ' ' + nf(D.fusions) + ' stations OpenStreetMap fusionnées avec leur dossier.' : '') +
            (taux < 80 ? ' Tant que les dossiers ne sont pas géolocalisés, la comparaison avec OpenStreetMap n\x27est pas significative.' : '') +
            (URL_GPS ? ' <a href="' + URL_GPS + '">Compléter les coordonnées</a>' : '');
    }

    // Marques normalisées (mêmes libellés pour les dossiers et OpenStreetMap), les plus fréquentes d'abord
    function remplirMarques() {
        var n = {}, AUTRE = 'Autre / indépendant';
        D.sgdi.forEach(function (m) { var k = m._sgdi[I.marque]; if (k) n[k] = (n[k] || 0) + 1; });
        D.osm.forEach(function (m) { var k = m._osm[4]; if (k) n[k] = (n[k] || 0) + 1; });
        var sel = $('f-marque'), actuel = sel.value;
        var cles = Object.keys(n).filter(function (k) { return k !== AUTRE; }).sort(function (a, b) { return n[b] - n[a]; });
        if (n[AUTRE]) cles.push(AUTRE);
        sel.innerHTML = '<option value="">Toutes les marques</option>' + cles.map(function (o) { return '<option value="' + esc(o) + '">' + esc(o) + ' (' + nf(n[o]) + ')</option>'; }).join('');
        sel.value = actuel;
    }

    /* ---------- Filtres ---------- */
    function valeurRadio(nom) { var r = document.querySelector('input[name="' + nom + '"]:checked'); return r ? r.value : ''; }
    function couchesActives() {
        var c = {};
        document.querySelectorAll('[data-couche]').forEach(function (i) { c[i.getAttribute('data-couche')] = i.checked; });
        return c;
    }
    function criteres() {
        return { region: $('f-region').value, marque: $('f-marque').value, phase: $('f-phase').value,
                 origine: valeurRadio('f-origine'), precision: valeurRadio('f-precision'),
                 q: sansAccents($('f-recherche').value.trim()), absents: $('osm-absents').checked };
    }
    function filtrer(zoomRegion) {
        var c = couchesActives(), f = criteres();

        function okSgdi(p, ignorerRegion) {
            return (ignorerRegion || !f.region || p[I.region] === f.region) &&
                (!f.phase || (STATUTS[p[I.statut]] || [])[1] === f.phase) &&
                (!f.marque || p[I.marque] === f.marque) &&
                (!f.origine || (f.origine === 'historique') === !!p[I.historique]) &&
                (!f.precision || (f.precision === 'approx') === !!p[I.approx]) &&
                (!f.q || sansAccents([p[I.numero], p[I.nom], p[I.operateur], p[I.ville], p[I.quartier], p[I.ancien], p[I.marque]].join(' ')).indexOf(f.q) !== -1);
        }
        // Statut et origine ne concernent que les dossiers ; OpenStreetMap n'a que des positions précises
        function okOsm(p, absent, ignorerRegion) {
            return !f.phase && !f.origine && f.precision !== 'approx' && (!f.absents || absent) &&
                (ignorerRegion || !f.region || p[6] === f.region) && (!f.marque || p[4] === f.marque) &&
                (!f.q || sansAccents([p[3], p[4], p[5]].join(' ')).indexOf(f.q) !== -1);
        }

        var visibles = [], compteurs = {}, parRegion = {};
        D.sgdi.forEach(function (m) {
            var p = m._sgdi, cle = 'sgdi:' + p[I.type];
            if (okSgdi(p, false)) compteurs[cle] = (compteurs[cle] || 0) + 1;
            if (!c[cle]) return;
            if (okSgdi(p, true)) parRegion[p[I.region]] = (parRegion[p[I.region]] || 0) + 1;
            if (okSgdi(p, false)) visibles.push(m);
        });
        D.osm.forEach(function (m) {
            var p = m._osm, cle = 'osm:' + p[2];
            if (okOsm(p, m._absent, false)) compteurs[cle] = (compteurs[cle] || 0) + 1;
            if (!c[cle]) return;
            if (okOsm(p, m._absent, true)) parRegion[p[6]] = (parRegion[p[6]] || 0) + 1;
            if (okOsm(p, m._absent, false)) visibles.push(m);
        });
        derniersVisibles = visibles;

        grappe.clearLayers();
        grappe.addLayers(regrouperApprox(visibles));
        document.querySelectorAll('[data-compteur]').forEach(function (el) { el.textContent = nf(compteurs[el.getAttribute('data-compteur')] || 0); });

        // Filtres actifs : bouton de réinitialisation
        var actifs = ['region', 'marque', 'phase', 'origine', 'precision', 'q'].filter(function (k) { return f[k]; }).length + (f.absents ? 1 : 0);
        $('f-reinit').hidden = !actifs;
        $('nb-filtres').textContent = actifs;

        var noms = Object.keys(parRegion).filter(Boolean).sort(function (a, b) { return parRegion[b] - parRegion[a]; });
        var max = Math.max.apply(null, noms.map(function (n) { return parRegion[n]; }).concat([1]));
        $('par-region').innerHTML = noms.length ? noms.map(function (n) {
            return '<li data-region="' + esc(n) + '"' + (n === f.region ? ' style="font-weight:700"' : '') + ' title="Zoomer sur la région ' + esc(n) + '"><span>' + esc(n) + '</span>' +
                '<span class="bar" style="width:' + (parRegion[n] / max * 100).toFixed(1) + '%"></span><span class="val">' + nf(parRegion[n]) + '</span></li>';
        }).join('') : '<li class="text-muted-sgdi">Aucun point</li>';

        if (coucheDensite) { carte.removeLayer(coucheDensite); coucheDensite = null; }
        if ($('l-densite').checked && L.heatLayer) {
            coucheDensite = L.heatLayer(visibles.map(function (m) { var ll = m.getLatLng(); return [ll.lat, ll.lng, .6]; }),
                { radius: 22, blur: 18, minOpacity: .25, gradient: { .3: '#667eea', .6: '#f39c12', 1: '#e74c3c' } }).addTo(carte);
        }
        if (zoomRegion) {
            var b = f.region && regions[f.region] ? regions[f.region].getBounds() : limitesPays;
            if (b) carte.flyToBounds(b, { padding: [20, 20], duration: .6 });
        }

        var h = dernierChargement ? dernierChargement.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '';
        var osm = D.osmMaj ? new Date(D.osmMaj).toLocaleDateString('fr-FR') : 'inconnue';
        $('carte-meta').textContent = nf(visibles.length) + ' points affichés · dossiers SGDI actualisés à ' + h +
            ' (automatiquement toutes les 5 min) · référence OpenStreetMap du ' + osm;
        enregistrerPreferences();
    }

    function zoomerSurResultats() {
        if (!derniersVisibles.length) return;
        var b = L.latLngBounds(derniersVisibles.map(function (m) { return m.getLatLng(); }));
        carte.flyToBounds(b, { padding: [40, 40], maxZoom: 16, duration: .6 });
    }

    function reinitialiserFiltres() {
        $('f-recherche').value = ''; $('f-region').value = ''; $('f-marque').value = ''; $('f-phase').value = '';
        $('f-origine-tous').checked = true; $('f-precision-toutes').checked = true; $('osm-absents').checked = false;
        filtrer(true);
    }

    // Couches cochées mémorisées pour ce navigateur (confort seulement : la carte fonctionne sans)
    function enregistrerPreferences() {
        try { localStorage.setItem(CLE_PREFERENCES, JSON.stringify(couchesActives())); } catch (e) { /* stockage indisponible */ }
    }
    function restaurerPreferences() {
        try {
            var c = JSON.parse(localStorage.getItem(CLE_PREFERENCES) || 'null');
            if (c) document.querySelectorAll('[data-couche]').forEach(function (i) { var k = i.getAttribute('data-couche'); if (k in c) i.checked = !!c[k]; });
        } catch (e) { /* préférences ignorées */ }
    }

    /* ---------- Stations prises en compte pour les distances ---------- */
    // Dossiers de stations existantes ou en instruction avancée, à position précise, et stations OSM absentes du SGDI
    function stationsReference() {
        var liste = [];
        D.sgdi.forEach(function (m) {
            var p = m._sgdi;
            if (p[I.type] !== 'station_service' || p[I.approx]) return;
            var existante = STATUTS_EXISTANTS.indexOf(p[I.statut]) !== -1, instruction = STATUTS_INSTRUCTION.indexOf(p[I.statut]) !== -1;
            if (!existante && !instruction) return;
            liste.push({ nom: p[I.nom] || 'Station', detail: p[I.numero], id: p[I.id], ll: m.getLatLng(), rurale: !!p[I.rurale],
                         etat: existante ? 'Station existante' : 'Dossier en instruction', phase: existante ? 'succes' : 'instruction' });
        });
        D.osm.forEach(function (m) {
            if (m._osm[2] !== 'station' || !m._absent) return;
            liste.push({ nom: m._osm[3] || 'Station sans nom', detail: m._osm[4], ll: m.getLatLng(), etat: 'OpenStreetMap, absente du SGDI', phase: 'attention' });
        });
        return liste;
    }

    /* ---------- Points d'intérêt et zones de protection ---------- */
    function dessinerPoi() {
        couchePoi.clearLayers(); coucheZones.clearLayers();
        D.poi.forEach(function (p) {
            L.marker([p[0], p[1]], { icon: L.divIcon({ className: '', html: '<span class="mk-poi" style="--poi-couleur:' + esc(p[6]) + '"></span>', iconSize: [10, 10], iconAnchor: [5, 5] }) })
                .bindPopup('<h3>' + esc(p[2]) + '</h3><div>' + esc(p[3]) + '</div><div class="small mt-1">Distance minimale : <strong>' + p[4] + ' m</strong> (' + p[5] + ' m en zone rurale)</div>')
                .addTo(couchePoi);
            L.circle([p[0], p[1]], { radius: p[4], className: 'zone-contrainte', interactive: false }).addTo(coucheZones);
        });
        // Rayon en mètres réels (L.circle) : 500 m, ou 400 m pour une station en zone rurale
        stationsReference().forEach(function (s) {
            if (!s.id) return;
            L.circle(s.ll, { radius: s.rurale ? DISTANCE.rurale : DISTANCE.urbaine, className: 'zone-contrainte', interactive: false }).addTo(coucheZones);
        });
    }
    $('l-poi').addEventListener('change', function () { this.checked ? couchePoi.addTo(carte) : carte.removeLayer(couchePoi); });
    $('l-zones').addEventListener('change', function () { this.checked ? coucheZones.addTo(carte) : carte.removeLayer(coucheZones); });

    /* ---------- Outils : vérifier un emplacement, mesurer une distance ---------- */
    var mode = null;
    var Bandeau = L.Control.extend({
        options: { position: 'topright' },
        onAdd: function () {
            var d = L.DomUtil.create('div', 'map-bandeau');
            L.DomEvent.disableClickPropagation(d);
            d.innerHTML = '<i class="fas fa-hand-pointer"></i><span id="bandeau-texte"></span><button type="button" class="btn btn-sm btn-primary" id="bandeau-fin">Terminer</button>';
            d.querySelector('#bandeau-fin').addEventListener('click', function () { changerMode(null); });
            return d;
        }
    });
    var bandeau = new Bandeau();
    function texteBandeau() {
        if (mode === 'verifier') return verif.point ? 'Déplacez le repère ou cliquez ailleurs pour vérifier un autre emplacement.' : 'Cliquez sur la carte à l\x27emplacement projeté.';
        if (mode === 'mesurer') return mesure.etapes.length ? 'Total : ' + fd(mesure.total) + ' · cliquez pour ajouter un point.' : 'Cliquez sur un premier point (carte ou station).';
        return '';
    }
    function majBandeau() { var t = $('bandeau-texte'); if (t) t.textContent = texteBandeau(); }

    function changerMode(m) {
        mode = (m && mode !== m) ? m : null;
        $('btn-verifier').classList.toggle('active', mode === 'verifier');
        $('btn-verifier').setAttribute('aria-pressed', mode === 'verifier');
        $('btn-mesurer').classList.toggle('active', mode === 'mesurer');
        $('btn-mesurer').setAttribute('aria-pressed', mode === 'mesurer');
        document.querySelector('.map-layout').classList.toggle('map-mode-clic', !!mode);
        $('carte-outil').hidden = !mode;
        $('outil-verifier').hidden = mode !== 'verifier';
        $('outil-mesurer').hidden = mode !== 'mesurer';
        $('outil-titre').textContent = mode === 'verifier' ? 'Vérifier un emplacement' : 'Mesurer une distance';
        if (mode !== 'verifier') effacerVerif();
        if (mode !== 'mesurer') effacerMesure();
        if (mode) { bandeau.addTo(carte); majBandeau(); carte.closePopup(); } else { bandeau.remove(); }
        if (mode && window.innerWidth < 992) $('carte-outil').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    $('btn-verifier').addEventListener('click', function () { changerMode('verifier'); });
    $('btn-mesurer').addEventListener('click', function () { changerMode('mesurer'); });
    $('outil-fermer').addEventListener('click', function () { changerMode(null); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && mode) changerMode(null); });

    // Un clic pendant un outil sert de point : sur le fond de carte, ou sur une station (sa position exacte)
    carte.on('click', function (e) { if (mode) pointOutil(e.latlng, null); });
    grappe.on('click', function (e) {
        if (!mode) return;
        carte.closePopup();
        var p = e.layer._sgdi, o = e.layer._osm;
        pointOutil(e.layer.getLatLng(), p ? (p[I.nom] || p[I.numero]) : o ? (o[3] || 'Station OSM') : null);
    });
    function pointOutil(latlng, nom) { if (mode === 'verifier') verifier(latlng); else if (mode === 'mesurer') mesurer(latlng, nom); }

    /* Vérifier un emplacement */
    var verif = { point: null, calques: [] };
    function zoneChoisie() { return valeurRadio('v-zone') || 'urbaine'; }
    function effacerVerif() {
        verif.calques.forEach(function (c) { carte.removeLayer(c); });
        verif = { point: null, calques: [] };
        $('v-resultat').innerHTML = '';
    }
    function verifier(latlng, sansZoom) {
        effacerVerif();
        verif.point = latlng;
        var zone = zoneChoisie(), rayon = DISTANCE[zone];

        // Zone de protection du projet : cercle en mètres réels
        var cercle = L.circle(latlng, { radius: rayon, className: 'zone-projet', interactive: false }).addTo(carte);
        var repere = L.marker(latlng, {
            draggable: true, autoPan: true, zIndexOffset: 1000, title: 'Emplacement projeté (déplaçable)',
            icon: L.divIcon({ className: '', iconSize: [22, 22], iconAnchor: [11, 26], html: '<span class="pin pin-projet"><i class="fas fa-crosshairs"></i></span>' })
        }).addTo(carte);
        repere.on('dragend', function () { verifier(repere.getLatLng(), true); });
        verif.calques.push(cercle, repere);

        var mesures = stationsReference().map(function (s) { s.d = carte.distance(latlng, s.ll); return s; })
            .filter(function (s) { return s.d > 1; }) // le point lui-même (clic sur une station)
            .sort(function (a, b) { return a.d - b.d; });
        var proches = mesures.filter(function (s) { return s.d < rayon; });
        var plusProche = mesures[0];
        var poi = D.poi.map(function (p) { var min = zone === 'rurale' ? p[5] : p[4]; return { nom: p[2], cat: p[3], d: carte.distance(latlng, [p[0], p[1]]), min: min }; })
            .filter(function (x) { return x.d < x.min; }).sort(function (a, b) { return a.d - b.d; });

        // Traits vers les stations trop proches, et vers la plus proche si elle est hors zone ;
        // la distance est affichée sur la station elle-même pour que les étiquettes ne se chevauchent pas
        proches.slice(0, 8).forEach(function (s) { verif.calques.push.apply(verif.calques, repereDistance(latlng, s, 'alerte')); });
        if (plusProche && plusProche.d >= rayon && plusProche.d < 5000) verif.calques.push.apply(verif.calques, repereDistance(latlng, plusProche, 'info'));

        // Stations de la zone connues seulement au centre de leur localité : non mesurables
        var approx = D.sgdi.filter(function (m) { var p = m._sgdi; return p[I.type] === 'station_service' && p[I.approx] && carte.distance(latlng, m.getLatLng()) < 5000; }).length;

        var conforme = !proches.length && !poi.length;
        var titre = conforme ? 'Emplacement conforme en zone ' + zone + ' : aucune station à moins de ' + rayon + ' m ni point d\x27intérêt dans sa zone de protection.'
            : 'Emplacement non conforme en zone ' + zone + ' (' + rayon + ' m minimum).';
        var lignes = proches.slice(0, 8).map(function (s) {
            return '<li><span class="ld-nom">' + (s.id ? '<a href="' + URL_DOSSIER + s.id + '">' + esc(s.nom) + '</a>' : esc(s.nom)) + '</span>' +
                '<strong>' + fd(s.d) + '</strong>' +
                '<span><span class="status-badge phase-' + s.phase + '">' + esc(s.etat) + '</span></span>' +
                '<span class="small text-muted-sgdi">manque ' + fd(rayon - s.d) + '</span></li>';
        }).concat(poi.slice(0, 5).map(function (x) {
            return '<li><span class="ld-nom">' + esc(x.nom) + '</span><strong>' + fd(x.d) + '</strong>' +
                '<span><span class="status-badge phase-danger">' + esc(x.cat) + '</span></span>' +
                '<span class="small text-muted-sgdi">minimum ' + x.min + ' m</span></li>';
        }));
        $('v-resultat').innerHTML =
            '<div class="verdict phase-' + (conforme ? 'succes' : 'danger') + '"><strong>' + esc(titre) + '</strong></div>' +
            (lignes.length ? '<ul class="liste-distances">' + lignes.join('') + '</ul>' : '') +
            (plusProche ? '<p class="small mb-1 mt-2">Station la plus proche : <strong>' + esc(plusProche.nom) + '</strong> à <strong>' + fd(plusProche.d) + '</strong>' +
                (plusProche.d >= rayon ? ' (marge de ' + fd(plusProche.d - rayon) + ')' : '') + '.</p>' : '') +
            (approx ? '<p class="small text-muted-sgdi mb-1"><i class="fas fa-triangle-exclamation"></i> ' + approx + ' station(s) à moins de 5 km n\x27ont qu\x27une position approximative (centre de la localité) et ne peuvent pas être prises en compte.</p>' : '') +
            '<p class="small text-muted-sgdi mb-0">Point vérifié : ' + latlng.lat.toFixed(6) + ', ' + latlng.lng.toFixed(6) + '</p>';
        $('v-lat').value = latlng.lat.toFixed(6); $('v-lon').value = latlng.lng.toFixed(6);
        majBandeau();
        if (!sansZoom) carte.flyToBounds(cercle.getBounds(), { padding: [60, 60], maxZoom: 17, duration: .6 });
    }
    function repereDistance(depart, s, niveau) {
        return [
            L.polyline([depart, s.ll], { className: 'trait-' + niveau, interactive: false }).addTo(carte),
            L.circleMarker(s.ll, { radius: 9, className: 'cible-' + niveau, interactive: false })
                .bindTooltip(fd(s.d), { permanent: true, direction: 'right', offset: [8, 0], className: 'etiquette-distance etiquette-' + niveau }).addTo(carte)
        ];
    }
    function trait(a, b, texte, classe) {
        return L.polyline([a, b], { className: classe, interactive: false })
            .bindTooltip(texte, { permanent: true, direction: 'center', className: 'etiquette-distance' }).addTo(carte);
    }
    document.querySelectorAll('input[name="v-zone"]').forEach(function (r) {
        r.addEventListener('change', function () { if (verif.point) verifier(verif.point, true); });
    });
    $('form-coord').addEventListener('submit', function (e) {
        e.preventDefault();
        var lat = parseFloat($('v-lat').value.replace(',', '.')), lon = parseFloat($('v-lon').value.replace(',', '.'));
        if (isNaN(lat) || isNaN(lon) || lat < 1.5 || lat > 13.5 || lon < 8 || lon > 16.5) {
            $('v-resultat').innerHTML = '<div class="verdict phase-danger">Coordonnées invalides : la latitude doit être entre 1,5 et 13,5 et la longitude entre 8 et 16,5 (Cameroun).</div>';
            return;
        }
        verifier(L.latLng(lat, lon));
    });

    /* Mesurer une distance */
    var mesure = { etapes: [], total: 0 };
    function effacerMesure() {
        mesure.etapes.forEach(function (e) { e.calques.forEach(function (c) { carte.removeLayer(c); }); });
        mesure = { etapes: [], total: 0 };
        majMesure();
    }
    function mesurer(latlng, nom) {
        var calques = [L.circleMarker(latlng, { radius: 6, className: 'point-mesure' }).addTo(carte)];
        var prec = mesure.etapes[mesure.etapes.length - 1], d = 0;
        if (prec) {
            d = carte.distance(prec.ll, latlng);
            calques.push(trait(prec.ll, latlng, fd(d), 'trace-mesure'));
        }
        mesure.etapes.push({ ll: latlng, nom: nom, d: d, calques: calques });
        mesure.total += d;
        majMesure();
    }
    function majMesure() {
        var n = mesure.etapes.length;
        $('m-total').textContent = n > 1 ? fd(mesure.total) : '—';
        $('m-points').innerHTML = mesure.etapes.map(function (e, i) {
            return (i + 1) + '. ' + esc(e.nom || (e.ll.lat.toFixed(5) + ', ' + e.ll.lng.toFixed(5))) + (i ? ' <strong>+' + fd(e.d) + '</strong>' : '');
        }).join('<br>');
        $('m-annuler').disabled = !n;
        $('m-effacer').disabled = !n;
        majBandeau();
    }
    $('m-annuler').addEventListener('click', function () {
        var e = mesure.etapes.pop();
        if (!e) return;
        e.calques.forEach(function (c) { carte.removeLayer(c); });
        mesure.total -= e.d;
        majMesure();
    });
    $('m-effacer').addEventListener('click', effacerMesure);

    /* ---------- Ouvrir un dossier depuis la liste (?dossier=ID) ---------- */
    function ouvrirDossier(id) {
        var m = D.sgdi.filter(function (x) { return x._sgdi[I.id] === id; })[0];
        if (!m) { $('carte-meta').textContent = 'Ce dossier n\'a pas de coordonnées GPS exploitables.'; return; }
        if (!grappe.hasLayer(m)) m = groupesApprox.filter(function (g) { return g._membres.indexOf(id) !== -1; })[0] || m;
        grappe.zoomToShowLayer(m, function () { m.openPopup(); });
    }

    /* ---------- Événements ---------- */
    document.querySelectorAll('[data-couche]').forEach(function (i) { i.addEventListener('change', function () { filtrer(); }); });
    document.querySelectorAll('[data-tout], [data-aucun]').forEach(function (b) {
        b.addEventListener('click', function () {
            var groupe = b.getAttribute('data-tout') || b.getAttribute('data-aucun'), etat = b.hasAttribute('data-tout');
            document.querySelectorAll('[data-couche^="' + groupe + ':"]').forEach(function (i) { i.checked = etat; });
            if (!etat && groupe === 'osm') $('osm-absents').checked = false;
            filtrer();
        });
    });
    ['l-densite', 'f-phase', 'f-marque'].forEach(function (id) { $(id).addEventListener('change', function () { filtrer(); }); });
    document.querySelectorAll('input[name="f-origine"], input[name="f-precision"]').forEach(function (r) { r.addEventListener('change', function () { filtrer(); }); });
    $('osm-absents').addEventListener('change', function () {
        if (this.checked) document.querySelector('[data-couche="osm:station"]').checked = true;
        filtrer();
    });
    $('f-region').addEventListener('change', function () { filtrer(true); });
    $('f-reinit').addEventListener('click', reinitialiserFiltres);
    var minuterie;
    $('f-recherche').addEventListener('input', function () { clearTimeout(minuterie); minuterie = setTimeout(filtrer, 250); });
    $('f-recherche').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); clearTimeout(minuterie); filtrer(); zoomerSurResultats(); }
    });
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

    restaurerPreferences();
    charger(false);
})();
</script>

<?php require_once '../../includes/footer.php'; ?>

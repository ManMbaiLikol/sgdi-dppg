<?php
// Carte publique interactive des infrastructures autorisées (registre public, sans authentification)
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/map_functions.php';
require_once '../../includes/osm_sync.php';

$page_title = 'Carte des infrastructures pétrolières';

// Infrastructures autorisées (y compris historiques) avec une position ; filtrage dynamique dans la page
$infrastructures = [];
foreach (getAllInfrastructuresForMap(['statuts' => ['autorise', 'historique_autorise']]) as $i) {
    $lat = (float) $i['latitude'];
    $lon = (float) $i['longitude'];
    $marketer = trim(preg_replace('/\s+/', ' ', (string) ($i['operateur_proprietaire'] ?: $i['nom_demandeur'])));
    $infrastructures[] = [
        'id' => (int) $i['id'],
        'numero' => (string) $i['numero'],
        'type_infrastructure' => $i['type_infrastructure'],
        'nature' => (string) $i['sous_type'],
        'nom_demandeur' => (string) $i['nom_demandeur'],
        'operateur_proprietaire' => (string) $i['operateur_proprietaire'],
        'entreprise_beneficiaire' => (string) $i['entreprise_beneficiaire'],
        'marketer' => mb_strtoupper($marketer),
        // Région, département et arrondissement d'après la position (ces champs manquent dans les dossiers historiques)
        'region' => osmRegionDuPoint($lat, $lon) ?: (string) $i['region'],
        'departement' => osmLimiteDuPoint($lat, $lon, 'departements') ?: trim((string) $i['departement']),
        'arrondissement' => osmLimiteDuPoint($lat, $lon, 'arrondissements') ?: trim((string) $i['arrondissement']),
        'ville' => trim((string) $i['ville']),
        'quartier' => trim((string) $i['quartier']),
        'lieu_dit' => trim((string) $i['lieu_dit']),
        'latitude' => round($lat, 6),
        'longitude' => round($lon, 6),
        'approximatif' => !empty($i['approximatif']),
        'ancien_nom' => (string) ($i['ancien_nom'] ?? ''),
        'ancien_operateur' => (string) ($i['ancien_operateur'] ?? ''),
        'historique' => $i['statut'] === 'historique_autorise',
    ];
}

$types = [
    'station_service' => ['Stations-service', 'Station-service', 'fa-gas-pump'],
    'point_consommateur' => ['Points consommateurs', 'Point consommateur', 'fa-industry'],
    'depot_gpl' => ['Dépôts GPL', 'Dépôt GPL', 'fa-fire-flame-simple'],
    'centre_emplisseur' => ['Centres emplisseurs', 'Centre emplisseur', 'fa-fill-drip'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - MINEE/DPPG</title>
    <link rel="icon" type="image/svg+xml" href="../../favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="<?php echo asset('css/carte-marqueurs.css'); ?>">
    <link href="../../assets/css/registre_public.css" rel="stylesheet">
    <style>
        html, body { height: 100%; margin: 0; }
        body { display: flex; flex-direction: column; background: #f5f7fa !important; }
        .public-header { padding: .75rem 0 !important; flex: none; z-index: 1100; }
        .public-header h1 { font-size: 1.15rem; margin: 0; }
        .carte-page { flex: 1; display: flex; min-height: 0; position: relative; }
        #map { flex: 1; min-width: 0; }

        /* Panneau des filtres */
        .panneau { width: 340px; flex: none; overflow-y: auto; background: #fff; border-right: 1px solid #e5e7eb; padding: 1rem; display: flex; flex-direction: column; gap: .85rem; }
        .panneau h2 { font-size: .95rem; font-weight: 700; margin: 0; }
        .panneau .libelle { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: .3rem; }
        .panneau .form-select, .panneau .form-control { font-size: .85rem; }
        .recherche { position: relative; }
        .recherche i { position: absolute; left: .7rem; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .recherche input { padding-left: 2rem; }
        .total { display: flex; align-items: baseline; justify-content: space-between; padding: .6rem .8rem; border-radius: .6rem; background: #eef2ff; }
        .total strong { font-size: 1.4rem; color: #1e3a8a; font-variant-numeric: tabular-nums; }

        /* Types d'infrastructures : couleur, pictogramme et forme de marqueur */
        .types { display: flex; flex-direction: column; gap: .35rem; }
        .type-btn { display: flex; align-items: center; gap: .6rem; width: 100%; padding: .45rem .65rem; border-radius: .55rem; border: 1.5px solid #e5e7eb; background: #fff; text-align: left; font-size: .87rem; transition: all .15s ease; }
        .type-btn .apercu { display: grid; place-items: center; width: 1.7rem; height: 1.7rem; flex: none; }
        .type-btn .apercu .pin { transform: rotate(-45deg) scale(.9); }
        .type-btn .nb { margin-left: auto; font-weight: 700; font-variant-numeric: tabular-nums; }
        .type-btn[aria-pressed="true"] { border-color: var(--c); background: color-mix(in srgb, var(--c) 8%, #fff); }
        .type-btn[aria-pressed="false"] { opacity: .5; }
        .type-btn[aria-pressed="false"] .apercu { filter: grayscale(1); }
        .type-btn:disabled { opacity: .35; }

        .leaflet-popup-content { font-size: .85rem; line-height: 1.45; min-width: 15rem; }
        .popup-type { display: inline-flex; align-items: center; gap: .35rem; font-size: .75rem; font-weight: 600; padding: .15rem .5rem; border-radius: 999px; color: #fff; background: var(--c); margin-bottom: .35rem; }
        .popup-approx { margin-top: .4rem; padding: .3rem .5rem; border-radius: .4rem; background: #fff2da; color: #8a5300; font-size: .75rem; }

        .bouton-filtres { display: none; }
        @media (max-width: 767.98px) {
            .panneau { position: absolute; inset: 0 auto 0 0; width: min(340px, 88vw); z-index: 1050; box-shadow: 4px 0 18px rgba(0,0,0,.2); transform: translateX(-105%); transition: transform .2s ease; }
            .panneau.ouvert { transform: none; }
            .bouton-filtres { display: inline-flex; position: absolute; top: .75rem; left: 3.4rem; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,.2); }
        }
    </style>
</head>
<body>
    <header class="public-header">
        <div class="container-fluid d-flex justify-content-between align-items-center gap-2">
            <h1><i class="fas fa-map-marked-alt"></i> Carte des infrastructures pétrolières</h1>
            <a href="index.php" class="btn btn-light btn-sm"><i class="fas fa-list"></i> Voir le registre</a>
        </div>
    </header>

    <div class="carte-page">
        <aside class="panneau" id="panneau" aria-label="Filtres de la carte">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-filter text-primary"></i> Rechercher et filtrer</h2>
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="reinit" hidden><i class="fas fa-rotate-left"></i> Réinitialiser</button>
            </div>

            <div class="total"><span class="small text-muted">Infrastructures affichées</span><strong id="total">…</strong></div>

            <div class="recherche">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" class="form-control" id="f-recherche" placeholder="Nom, marketer, ville, quartier, n°…" aria-label="Rechercher">
            </div>

            <div>
                <div class="libelle">Type d'infrastructure</div>
                <div class="types">
                    <?php foreach ($types as $code => $t): ?>
                    <button type="button" class="type-btn" data-type="<?php echo $code; ?>" aria-pressed="true" style="--c: var(--mk-<?php echo ['station_service' => 'station', 'point_consommateur' => 'conso', 'depot_gpl' => 'gpl', 'centre_emplisseur' => 'emplisseur'][$code]; ?>)">
                        <span class="apercu"><span class="pin pin-<?php echo $code; ?>"><i class="fas <?php echo $t[2]; ?>"></i></span></span>
                        <span><?php echo $t[0]; ?></span>
                        <span class="nb" data-nb="<?php echo $code; ?>">0</span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <div class="libelle">Localisation</div>
                <div class="d-flex flex-column gap-2">
                    <select class="form-select" id="f-region" aria-label="Région"></select>
                    <select class="form-select" id="f-departement" aria-label="Département"></select>
                    <select class="form-select" id="f-arrondissement" aria-label="Arrondissement"></select>
                    <select class="form-select" id="f-ville" aria-label="Ville"></select>
                </div>
            </div>

            <div>
                <div class="libelle">Marketer</div>
                <select class="form-select" id="f-marketer" aria-label="Marketer"></select>
            </div>

            <p class="small text-muted mb-0">Les positions marquées « approximatives » correspondent au centre de la localité, en attendant un relevé sur le terrain.</p>
        </aside>

        <button type="button" class="btn btn-primary btn-sm bouton-filtres" id="bouton-filtres" aria-controls="panneau" aria-expanded="false"><i class="fas fa-filter me-1"></i> Filtres</button>
        <div id="map" role="region" aria-label="Carte des infrastructures"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script src="<?php echo asset('js/carte-groupes.js'); ?>"></script>
    <script>
    (function () {
        'use strict';
        var INFRAS = <?php echo json_encode($infrastructures, JSON_UNESCAPED_UNICODE); ?>;
        var TYPES = <?php echo json_encode(array_map(function ($t) { return ['pluriel' => $t[0], 'nom' => $t[1], 'icone' => $t[2]]; }, $types), JSON_UNESCAPED_UNICODE); ?>;
        var COULEURS = { station_service: 'var(--mk-station)', point_consommateur: 'var(--mk-conso)', depot_gpl: 'var(--mk-gpl)', centre_emplisseur: 'var(--mk-emplisseur)' };
        // Filtres de localisation, du plus large au plus précis : changer un niveau réinitialise les suivants
        var NIVEAUX = [['region', 'Toutes les régions'], ['departement', 'Tous les départements'], ['arrondissement', 'Tous les arrondissements'], ['ville', 'Toutes les villes']];
        var esc = sgdiEsc;
        function $(id) { return document.getElementById(id); }
        function cle(t) { return String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim(); }
        function nf(n) { return n.toLocaleString('fr-FR'); }

        // Valeurs normalisées (« Yaoundé », « YAOUNDE » et « yaounde » ne font qu'un) et texte de recherche
        INFRAS.forEach(function (i) {
            NIVEAUX.concat([['marketer']]).forEach(function (n) { i['_' + n[0]] = cle(i[n[0]]); });
            i._texte = cle([i.nom_demandeur, i.marketer, i.operateur_proprietaire, i.entreprise_beneficiaire, i.numero, i.region,
                            i.departement, i.arrondissement, i.ville, i.quartier, i.lieu_dit, i.ancien_nom, i.ancien_operateur].join(' '));
        });
        // Libellé affiché pour chaque valeur : la graphie la plus fréquente
        var libelles = {};
        NIVEAUX.concat([['marketer']]).forEach(function (n) {
            var f = {};
            INFRAS.forEach(function (i) { var k = i['_' + n[0]]; if (!k) return; f[k] = f[k] || {}; f[k][i[n[0]]] = (f[k][i[n[0]]] || 0) + 1; });
            libelles[n[0]] = {};
            Object.keys(f).forEach(function (k) { libelles[n[0]][k] = Object.keys(f[k]).sort(function (a, b) { return f[k][b] - f[k][a]; })[0]; });
        });

        /* Carte */
        var carte = L.map('map', { zoomSnap: .5, minZoom: 5 }).setView([7.37, 12.35], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© contributeurs OpenStreetMap' }).addTo(carte);
        L.control.scale({ imperial: false }).addTo(carte);
        var grappe = L.markerClusterGroup({ maxClusterRadius: 50, showCoverageOnHover: false, spiderfyOnMaxZoom: true, chunkedLoading: true, iconCreateFunction: sgdiIconeGrappe }).addTo(carte);

        function popup(i) {
            var t = TYPES[i.type_infrastructure] || { nom: i.type_infrastructure, icone: 'fa-location-dot' };
            var titre = i.type_infrastructure === 'point_consommateur' && i.entreprise_beneficiaire ? i.entreprise_beneficiaire : i.nom_demandeur;
            var lieu = [i.lieu_dit, i.quartier, i.ville].filter(Boolean).join(', ');
            var admin = [i.arrondissement, i.departement, i.region].filter(Boolean).join(' · ');
            return '<span class="popup-type" style="--c:' + COULEURS[i.type_infrastructure] + '"><i class="fas ' + t.icone + '"></i>' + esc(t.nom) +
                (i.nature ? ' · ' + esc(i.nature.charAt(0).toUpperCase() + i.nature.slice(1)) : '') + '</span>' +
                '<div class="fw-bold">' + esc(titre || 'Non renseigné') + '</div>' +
                (i.marketer && i.marketer !== String(titre).toUpperCase() ? '<div>Marketer : <strong>' + esc(i.marketer) + '</strong></div>' : '') +
                (i.ancien_nom ? '<div class="small text-muted">Anciennement : ' + esc(i.ancien_operateur || i.ancien_nom) + '</div>' : '') +
                (lieu ? '<div class="mt-1"><i class="fas fa-location-dot text-muted"></i> ' + esc(lieu) + '</div>' : '') +
                (admin ? '<div class="small text-muted">' + esc(admin) + '</div>' : '') +
                '<div class="small text-muted mt-1">N° ' + esc(i.numero) + (i.historique ? ' · autorisation antérieure au SGDI' : '') + '</div>' +
                (i.approximatif ? '<div class="popup-approx">Position approximative (centre de la localité)</div>' : '');
        }
        INFRAS.forEach(function (i) {
            i._marqueur = L.marker([i.latitude, i.longitude], { icon: sgdiIcone(i.type_infrastructure, i.approximatif), riseOnHover: true, title: i.nom_demandeur })
                .bindPopup(function () { return popup(i); });
        });

        /* Filtres */
        var typesActifs = {};
        Object.keys(TYPES).forEach(function (t) { typesActifs[t] = true; });

        function criteres() {
            var c = { q: cle($('f-recherche').value), marketer: $('f-marketer').value };
            NIVEAUX.forEach(function (n) { c[n[0]] = $('f-' + n[0]).value; });
            return c;
        }
        // Une infrastructure passe-t-elle les filtres, en ignorant éventuellement l'un d'eux (pour les compteurs)
        function correspond(i, c, sauf) {
            if (sauf !== 'type' && !typesActifs[i.type_infrastructure]) return false;
            if (sauf !== 'q' && c.q && i._texte.indexOf(c.q) === -1) return false;
            if (sauf !== 'marketer' && c.marketer && i._marketer !== c.marketer) return false;
            for (var k = 0; k < NIVEAUX.length; k++) {
                var n = NIVEAUX[k][0];
                if (sauf === n) continue;
                if (c[n] && i['_' + n] !== c[n]) return false;
            }
            return true;
        }
        function remplirListe(id, champ, vide, c) {
            var sel = $('f-' + id), actuel = sel.value, n = {};
            INFRAS.forEach(function (i) { if (i['_' + champ] && correspond(i, c, champ)) n[i['_' + champ]] = (n[i['_' + champ]] || 0) + 1; });
            if (actuel && !n[actuel]) n[actuel] = 0;
            var cles = Object.keys(n).sort(function (a, b) {
                return champ === 'marketer' ? (n[b] - n[a]) || a.localeCompare(b) : libelles[champ][a].localeCompare(libelles[champ][b], 'fr');
            });
            sel.innerHTML = '<option value="">' + vide + '</option>' + cles.map(function (k) {
                return '<option value="' + esc(k) + '">' + esc(libelles[champ][k] || k) + ' (' + nf(n[k]) + ')</option>';
            }).join('');
            sel.value = actuel;
            sel.disabled = cles.length === 0;
        }

        function filtrer(zoomer) {
            var c = criteres(), visibles = [];
            INFRAS.forEach(function (i) { if (correspond(i, c)) visibles.push(i); });

            grappe.clearLayers();
            grappe.addLayers(sgdiRegrouperApprox(visibles, grappe).map(function (i) { return i._marqueur; }));

            // Compteurs par type (hors filtre de type) et listes proposant seulement les valeurs encore possibles
            Object.keys(TYPES).forEach(function (t) {
                var nb = INFRAS.filter(function (i) { return i.type_infrastructure === t && correspond(i, c, 'type'); }).length;
                document.querySelector('[data-nb="' + t + '"]').textContent = nf(nb);
            });
            NIVEAUX.forEach(function (n) { remplirListe(n[0], n[0], n[1], c); });
            remplirListe('marketer', 'marketer', 'Tous les marketers', c);

            $('total').textContent = nf(visibles.length);
            var actifs = c.q || c.marketer || NIVEAUX.some(function (n) { return c[n[0]]; }) || Object.keys(typesActifs).some(function (t) { return !typesActifs[t]; });
            $('reinit').hidden = !actifs;
            if (zoomer && visibles.length) {
                carte.flyToBounds(L.latLngBounds(visibles.map(function (i) { return [i.latitude, i.longitude]; })), { padding: [40, 40], maxZoom: 15, duration: .6 });
            }
        }

        document.querySelectorAll('.type-btn').forEach(function (b) {
            b.addEventListener('click', function () {
                var t = b.getAttribute('data-type');
                typesActifs[t] = !typesActifs[t];
                b.setAttribute('aria-pressed', typesActifs[t]);
                filtrer(false);
            });
        });
        NIVEAUX.forEach(function (n, k) {
            $('f-' + n[0]).addEventListener('change', function () {
                NIVEAUX.slice(k + 1).forEach(function (m) { $('f-' + m[0]).value = ''; });
                filtrer(true);
            });
        });
        $('f-marketer').addEventListener('change', function () { filtrer(true); });
        var minuterie;
        $('f-recherche').addEventListener('input', function () { clearTimeout(minuterie); minuterie = setTimeout(function () { filtrer(false); }, 200); });
        $('f-recherche').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(minuterie); filtrer(true); } });
        $('reinit').addEventListener('click', function () {
            $('f-recherche').value = ''; $('f-marketer').value = '';
            NIVEAUX.forEach(function (n) { $('f-' + n[0]).value = ''; });
            Object.keys(typesActifs).forEach(function (t) { typesActifs[t] = true; });
            document.querySelectorAll('.type-btn').forEach(function (b) { b.setAttribute('aria-pressed', 'true'); });
            filtrer(true);
        });
        $('bouton-filtres').addEventListener('click', function () {
            var ouvert = $('panneau').classList.toggle('ouvert');
            this.setAttribute('aria-expanded', ouvert);
        });

        filtrer(true);
    })();
    </script>
</body>
</html>

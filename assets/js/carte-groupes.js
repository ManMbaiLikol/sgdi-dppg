/**
 * Marqueurs communs des cartes SGDI (styles : assets/css/carte-marqueurs.css)
 *  - sgdiIcone : épingle à pictogramme selon le type d'infrastructure
 *  - sgdiRegrouperApprox : dossiers placés au centre de la même localité réunis en un seul marqueur
 *  - sgdiIconeGrappe : grappe comptant tous les dossiers regroupés
 */
(function () {
    var PICTOS = {
        station_service: 'fa-gas-pump', point_consommateur: 'fa-industry',
        depot_gpl: 'fa-fire-flame-simple', centre_emplisseur: 'fa-fill-drip'
    };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    window.sgdiEsc = esc;

    window.sgdiIcone = function (type, approximatif) {
        return L.divIcon({
            className: '', iconSize: [22, 22], iconAnchor: [11, 26], popupAnchor: [0, -24], tooltipAnchor: [10, -14],
            html: '<span class="pin pin-' + esc(type) + (approximatif ? ' is-approx' : '') + '"><i class="fas ' + (PICTOS[type] || 'fa-location-dot') + '"></i></span>'
        });
    };

    window.sgdiIconeGroupe = function (n) {
        return L.divIcon({ className: '', iconSize: null, html: '<span class="mk-groupe" title="' + n + ' dossiers, position approximative"><i class="fas fa-location-dot"></i>' + n + '</span>' });
    };

    /**
     * Ajoute un marqueur par groupe de positions approximatives identiques au groupe de grappes
     * et renvoie les infrastructures restantes, à afficher normalement.
     */
    window.sgdiRegrouperApprox = function (infrastructures, grappe) {
        var groupes = {}, restantes = [];
        infrastructures.forEach(function (i) {
            if (!i.approximatif || !i.latitude || !i.longitude) { restantes.push(i); return; }
            var k = i.latitude + ',' + i.longitude;
            (groupes[k] = groupes[k] || []).push(i);
        });
        Object.keys(groupes).forEach(function (k) {
            var g = groupes[k];
            if (g.length === 1) { restantes.push(g[0]); return; }
            var n = g.length, ville = g[0].ville || g[0].arrondissement || 'localité';
            var m = L.marker([g[0].latitude, g[0].longitude], { icon: sgdiIconeGroupe(n) });
            m.bindPopup('<div style="min-width:220px"><h6 class="mb-1">' + n + ' infrastructures · ' + esc(ville) + '</h6>' +
                '<div style="margin:4px 0 6px;padding:4px 8px;border-radius:6px;background:#fff2da;color:#8a5300;font-size:12px">Positions approximatives (centre de la localité)</div>' +
                '<ul class="liste-groupe">' + g.map(function (i) {
                    return '<li><strong>' + esc(i.nom_demandeur || 'Non renseigné') + '</strong>' +
                        '<small class="text-muted">' + esc(i.numero) + (i.operateur_proprietaire ? ' · ' + esc(i.operateur_proprietaire) : '') +
                        (i.ancien_nom ? ' · anciennement ' + esc(i.ancien_operateur || i.ancien_nom) : '') + '</small></li>';
                }).join('') + '</ul></div>', { maxWidth: 320 });
            m._poids = n;
            grappe.addLayer(m);
        });
        return restantes;
    };

    // Grappe comptant tous les dossiers d'un marqueur de localité
    window.sgdiIconeGrappe = function (c) {
        var n = c.getAllChildMarkers().reduce(function (t, m) { return t + (m._poids || 1); }, 0);
        var s = n < 10 ? 28 : n < 100 ? 34 : n < 500 ? 40 : 46;
        return L.divIcon({ html: '<div class="mk-cluster" style="width:' + s + 'px;height:' + s + 'px">' + n + '</div>', className: '', iconSize: [s, s] });
    };
})();

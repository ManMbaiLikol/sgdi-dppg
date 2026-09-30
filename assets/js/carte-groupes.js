/**
 * Cartes publiques : dossiers placés au centre de la même localité (position approximative)
 * réunis en un seul marqueur qui les liste, au lieu d'une pile de points aux coordonnées identiques.
 */
(function () {
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

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
            var m = L.marker([g[0].latitude, g[0].longitude], {
                icon: L.divIcon({
                    className: '', iconSize: [34, 34], iconAnchor: [17, 17],
                    html: '<div style="width:34px;height:34px;border-radius:50%;display:grid;place-items:center;font-weight:700;font-size:13px;' +
                          'color:#1e3a8a;background:#dbe8fd;border:2px dashed #1f74e0;box-shadow:0 1px 4px rgba(0,0,0,.3)">' + n + '</div>'
                })
            });
            m.bindPopup('<div style="min-width:220px"><h6 class="mb-1">' + n + ' infrastructures · ' + esc(ville) + '</h6>' +
                '<div style="margin:4px 0 6px;padding:4px 8px;border-radius:6px;background:#fff2da;color:#8a5300;font-size:12px">Positions approximatives (centre de la localité)</div>' +
                '<ul style="list-style:none;margin:0;padding:0;max-height:220px;overflow-y:auto">' + g.map(function (i) {
                    return '<li style="padding:4px 0;border-top:1px solid #eee"><strong>' + esc(i.nom_demandeur || 'Non renseigné') + '</strong>' +
                        '<br><small class="text-muted">' + esc(i.numero) + (i.operateur_proprietaire ? ' · ' + esc(i.operateur_proprietaire) : '') + '</small></li>';
                }).join('') + '</ul></div>', { maxWidth: 320 });
            m._poids = n;
            grappe.addLayer(m);
        });
        return restantes;
    };

    // Icône de grappe comptant tous les dossiers d'un marqueur de localité
    window.sgdiIconeGrappe = function (c) {
        var n = c.getAllChildMarkers().reduce(function (t, m) { return t + (m._poids || 1); }, 0);
        var taille = n < 10 ? 'small' : n < 100 ? 'medium' : 'large';
        return L.divIcon({ html: '<div><span>' + n + '</span></div>', className: 'marker-cluster marker-cluster-' + taille, iconSize: L.point(40, 40) });
    };
})();

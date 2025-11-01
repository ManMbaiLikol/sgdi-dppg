/**
 * JavaScript pour rendre toutes les cartes Leaflet responsive sur mobile
 * S'active automatiquement sur tous les écrans < 768px
 * Gère les panneaux repliables avec boutons flottants
 */

(function() {
    'use strict';

    // Vérifier si on est sur mobile
    function isMobile() {
        return window.innerWidth <= 768;
    }

    // Initialiser les contrôles mobiles pour les cartes
    function initMobileMapControls() {
        if (!isMobile()) return;

        console.log('📱 Initialisation des contrôles mobiles pour la carte');

        // Trouver tous les panneaux de contrôle
        const panels = {
            filters: document.querySelector('.map-controls, [class*="control"]:not(.leaflet-control)'),
            stats: document.querySelector('.stats-panel, [class*="stats"]:not(.leaflet-control)'),
            legend: document.querySelector('.legend, [class*="legend"]:not(.leaflet-control)')
        };

        // Créer l'overlay
        const overlay = document.createElement('div');
        overlay.className = 'mobile-map-overlay';
        document.body.appendChild(overlay);

        // Créer les boutons flottants
        const buttons = {};
        const icons = {
            filters: '🔍',
            stats: '📊',
            legend: '🗺️'
        };

        const titles = {
            filters: 'Filtres',
            stats: 'Statistiques',
            legend: 'Légende'
        };

        Object.keys(panels).forEach(key => {
            if (!panels[key]) return;

            // Créer le bouton
            const btn = document.createElement('button');
            btn.className = 'mobile-map-toggle';
            btn.setAttribute('data-target', key);
            btn.innerHTML = icons[key];
            btn.title = titles[key];
            btn.setAttribute('aria-label', titles[key]);

            document.body.appendChild(btn);
            buttons[key] = btn;

            // Événement click sur le bouton
            btn.addEventListener('click', function() {
                const isActive = panels[key].classList.contains('mobile-active');

                // Fermer tous les panneaux
                Object.keys(panels).forEach(k => {
                    if (panels[k]) {
                        panels[k].classList.remove('mobile-active');
                        panels[k].style.display = 'none';
                    }
                    if (buttons[k]) {
                        buttons[k].classList.remove('active');
                    }
                });

                // Ouvrir ou fermer le panneau actuel
                if (!isActive) {
                    panels[key].style.display = 'block';
                    panels[key].classList.add('mobile-active');
                    btn.classList.add('active');
                    overlay.classList.add('active');
                } else {
                    overlay.classList.remove('active');
                }
            });
        });

        // Fermer les panneaux en cliquant sur l'overlay
        overlay.addEventListener('click', function() {
            Object.keys(panels).forEach(key => {
                if (panels[key]) {
                    panels[key].classList.remove('mobile-active');
                    panels[key].style.display = 'none';
                }
                if (buttons[key]) {
                    buttons[key].classList.remove('active');
                }
            });
            overlay.classList.remove('active');
        });

        // Fermer avec la touche Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                overlay.click();
            }
        });

        console.log('✅ Contrôles mobiles initialisés');
    }

    // Réinitialiser au changement d'orientation ou de taille
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            // Supprimer les anciens boutons
            document.querySelectorAll('.mobile-map-toggle').forEach(btn => btn.remove());
            document.querySelectorAll('.mobile-map-overlay').forEach(ov => ov.remove());

            // Réinitialiser les panneaux
            document.querySelectorAll('.map-controls, .stats-panel, .legend').forEach(panel => {
                panel.classList.remove('mobile-active');
                if (!isMobile()) {
                    panel.style.display = '';
                }
            });

            // Réinitialiser si on est sur mobile
            if (isMobile()) {
                initMobileMapControls();
            }
        }, 250);
    });

    // Attendre que le DOM soit chargé
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileMapControls);
    } else {
        initMobileMapControls();
    }

    // Exporter la fonction pour réinitialisation manuelle si besoin
    window.reinitMobileMapControls = initMobileMapControls;

})();

/**
 * Theme Toggle - Mode Sombre/Clair SGDI
 * Gestion du thème avec sauvegarde localStorage
 */

(function() {
    'use strict';

    const THEME_KEY = 'sgdi_theme';
    const THEME_DARK = 'dark';
    const THEME_LIGHT = 'light';

    // Classe principale
    class ThemeManager {
        constructor() {
            this.currentTheme = this.getStoredTheme() || this.getSystemTheme();
            this.init();
        }

        /**
         * Initialisation
         */
        init() {
            // Appliquer le thème au chargement
            this.applyTheme(this.currentTheme);

            // Le bouton existe déjà dans header.php, pas besoin de le créer
            // this.createFloatingToggle();
            // this.createHeaderToggle();

            // Écouter les changements de préférence système
            this.watchSystemTheme();

            // Écouter les événements
            this.attachEvents();

            // Connecter le bouton existant
            this.connectExistingButton();
        }

        /**
         * Récupérer le thème stocké
         */
        getStoredTheme() {
            return localStorage.getItem(THEME_KEY);
        }

        /**
         * Récupérer le thème système
         */
        getSystemTheme() {
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return THEME_DARK;
            }
            return THEME_LIGHT;
        }

        /**
         * Sauvegarder le thème
         */
        saveTheme(theme) {
            localStorage.setItem(THEME_KEY, theme);
        }

        /**
         * Appliquer le thème
         */
        applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            this.currentTheme = theme;
            this.updateToggleIcons();
            this.saveTheme(theme);

            // Événement personnalisé
            const event = new CustomEvent('themechange', { detail: { theme } });
            document.dispatchEvent(event);
        }

        /**
         * Basculer le thème
         */
        toggleTheme() {
            const newTheme = this.currentTheme === THEME_DARK ? THEME_LIGHT : THEME_DARK;
            this.applyTheme(newTheme);

            // Animation rotation sur le bouton du header
            const toggleBtn = document.getElementById('theme-toggle');
            if (toggleBtn) {
                toggleBtn.classList.add('rotating');
                setTimeout(() => toggleBtn.classList.remove('rotating'), 500);
            }

            // Message toast (optionnel)
            this.showToast(`Mode ${newTheme === THEME_DARK ? 'sombre' : 'clair'} activé`);
        }

        /**
         * Créer le bouton flottant
         */
        createFloatingToggle() {
            const toggle = document.createElement('button');
            toggle.className = 'theme-toggle';
            toggle.setAttribute('aria-label', 'Changer de thème');
            toggle.setAttribute('title', 'Mode sombre/clair');
            toggle.innerHTML = '<i class="fas fa-moon"></i>';

            toggle.addEventListener('click', () => this.toggleTheme());

            document.body.appendChild(toggle);
        }

        /**
         * Connecter le bouton existant dans le header
         */
        connectExistingButton() {
            const existingButton = document.getElementById('theme-toggle');
            if (existingButton) {
                existingButton.addEventListener('click', () => this.toggleTheme());
            }
        }

        /**
         * Créer le bouton dans le header
         */
        createHeaderToggle() {
            // Ne rien faire - le bouton existe déjà dans le header.php
            // Cette fonction est désactivée pour éviter les doublons
        }

        /**
         * Mettre à jour les icônes
         */
        updateToggleIcons() {
            const icons = document.querySelectorAll('.theme-toggle i, #theme-toggle i');
            const isDark = this.currentTheme === THEME_DARK;

            icons.forEach(icon => {
                icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            });
        }

        /**
         * Surveiller les changements de thème système
         */
        watchSystemTheme() {
            if (window.matchMedia) {
                const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');

                darkModeQuery.addEventListener('change', (e) => {
                    // Seulement si l'utilisateur n'a pas défini de préférence
                    if (!this.getStoredTheme()) {
                        this.applyTheme(e.matches ? THEME_DARK : THEME_LIGHT);
                    }
                });
            }
        }

        /**
         * Attacher les événements
         */
        attachEvents() {
            // Raccourci clavier: Ctrl + Shift + D
            document.addEventListener('keydown', (e) => {
                if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                    e.preventDefault();
                    this.toggleTheme();
                }
            });

            // Double-clic sur le logo (Easter egg)
            const logo = document.querySelector('.logo, .navbar-brand');
            if (logo) {
                let lastClick = 0;
                logo.addEventListener('click', (e) => {
                    const now = Date.now();
                    if (now - lastClick < 300) {
                        this.toggleTheme();
                    }
                    lastClick = now;
                });
            }
        }

        /**
         * Afficher un toast
         */
        showToast(message) {
            // Vérifier si Bootstrap toast existe
            if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
                const toastHtml = `
                    <div class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-palette me-2"></i> ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                `;

                let toastContainer = document.querySelector('.toast-container');
                if (!toastContainer) {
                    toastContainer = document.createElement('div');
                    toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                    document.body.appendChild(toastContainer);
                }

                toastContainer.insertAdjacentHTML('beforeend', toastHtml);
                const toastEl = toastContainer.lastElementChild;
                const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
                toast.show();

                toastEl.addEventListener('hidden.bs.toast', () => {
                    toastEl.remove();
                });
            } else {
                // Fallback simple
                console.log(message);
            }
        }

        /**
         * API publique
         */
        getTheme() {
            return this.currentTheme;
        }

        setTheme(theme) {
            if (theme === THEME_DARK || theme === THEME_LIGHT) {
                this.applyTheme(theme);
            }
        }

        resetToSystemTheme() {
            const systemTheme = this.getSystemTheme();
            localStorage.removeItem(THEME_KEY);
            this.applyTheme(systemTheme);
        }
    }

    // Initialiser au chargement du DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.themeManager = new ThemeManager();
        });
    } else {
        window.themeManager = new ThemeManager();
    }

    // Exporter pour utilisation globale
    window.ThemeManager = ThemeManager;

    // API pratique
    window.toggleTheme = function() {
        if (window.themeManager) {
            window.themeManager.toggleTheme();
        }
    };

    window.setTheme = function(theme) {
        if (window.themeManager) {
            window.themeManager.setTheme(theme);
        }
    };

    window.getTheme = function() {
        return window.themeManager ? window.themeManager.getTheme() : null;
    };

})();

/**
 * Intégration Charts.js pour mode sombre
 */
document.addEventListener('themechange', function(e) {
    const isDark = e.detail.theme === 'dark';

    // Si Chart.js est chargé
    if (typeof Chart !== 'undefined') {
        // Configurer les couleurs par défaut pour les nouveaux graphiques
        Chart.defaults.color = isDark ? '#cbd5e1' : '#666';
        Chart.defaults.borderColor = isDark ? '#4a4a4a' : '#dee2e6';

        // Mettre à jour les graphiques existants
        Chart.instances.forEach(chart => {
            chart.options.plugins.legend.labels.color = isDark ? '#cbd5e1' : '#666';
            chart.options.scales && Object.keys(chart.options.scales).forEach(scaleKey => {
                chart.options.scales[scaleKey].ticks.color = isDark ? '#cbd5e1' : '#666';
                chart.options.scales[scaleKey].grid.color = isDark ? '#4a4a4a' : '#dee2e6';
            });
            chart.update();
        });
    }
});

/**
 * Console message
 */
console.log('%c🌓 Theme Manager chargé', 'color: #3b82f6; font-weight: bold; font-size: 14px;');
console.log('Raccourci clavier: Ctrl+Shift+D');
console.log('API: toggleTheme(), setTheme(\'dark\'), getTheme()');

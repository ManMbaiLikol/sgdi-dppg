/**
 * Gestion du thème sombre/clair
 * Sauvegarde la préférence dans localStorage
 */

(function() {
    'use strict';

    // Récupérer le thème sauvegardé ou détecter la préférence système
    function getPreferredTheme() {
        const savedTheme = localStorage.getItem('sgdi-theme');
        if (savedTheme) {
            return savedTheme;
        }

        // Détecter la préférence système
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }

        return 'light';
    }

    // Appliquer le thème
    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('sgdi-theme', theme);

        // Mettre à jour l'icône du bouton toggle si elle existe
        updateToggleButton(theme);
    }

    // Basculer entre les thèmes
    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        setTheme(newTheme);
    }

    // Mettre à jour le bouton toggle
    function updateToggleButton(theme) {
        const toggleButton = document.getElementById('theme-toggle');
        if (toggleButton) {
            const icon = toggleButton.querySelector('i');
            if (icon) {
                if (theme === 'dark') {
                    icon.className = 'fas fa-moon';
                } else {
                    icon.className = 'fas fa-sun';
                }
            }
        }
    }

    // Initialiser le thème au chargement
    document.addEventListener('DOMContentLoaded', function() {
        const theme = getPreferredTheme();
        setTheme(theme);

        // Attacher l'événement au bouton toggle
        const toggleButton = document.getElementById('theme-toggle');
        if (toggleButton) {
            toggleButton.addEventListener('click', toggleTheme);
        }
    });

    // Écouter les changements de préférence système
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            // Ne changer que si l'utilisateur n'a pas défini de préférence manuelle
            if (!localStorage.getItem('sgdi-theme')) {
                setTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    // Exposer la fonction globalement pour usage externe
    window.SGDI = window.SGDI || {};
    window.SGDI.setTheme = setTheme;
    window.SGDI.toggleTheme = toggleTheme;
    window.SGDI.getTheme = function() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    };
})();

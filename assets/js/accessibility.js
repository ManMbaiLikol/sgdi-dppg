/**
 * Améliorations JavaScript pour l'accessibilité WCAG 2.1
 */

(function() {
    'use strict';

    // ========================================================================
    // 1. DÉTECTION NAVIGATION CLAVIER VS SOURIS
    // ========================================================================

    let isUsingKeyboard = false;

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            isUsingKeyboard = true;
            document.body.classList.remove('mouse-user');
            document.body.classList.add('keyboard-user');
        }
    });

    document.addEventListener('mousedown', function() {
        isUsingKeyboard = false;
        document.body.classList.remove('keyboard-user');
        document.body.classList.add('mouse-user');
    });

    // ========================================================================
    // 2. SKIP LINKS (Aller au contenu principal)
    // ========================================================================

    function createSkipLinks() {
        const skipLinksContainer = document.createElement('div');
        skipLinksContainer.className = 'skip-links';

        const skipToMain = document.createElement('a');
        skipToMain.href = '#main-content';
        skipToMain.className = 'skip-link';
        skipToMain.textContent = 'Aller au contenu principal';

        const skipToNav = document.createElement('a');
        skipToNav.href = '#main-navigation';
        skipToNav.className = 'skip-link';
        skipToNav.textContent = 'Aller à la navigation';

        skipLinksContainer.appendChild(skipToMain);
        skipLinksContainer.appendChild(skipToNav);

        document.body.insertBefore(skipLinksContainer, document.body.firstChild);

        // Ajouter IDs si manquants
        const mainContent = document.querySelector('main') ||
                           document.querySelector('[role="main"]') ||
                           document.querySelector('.container');

        if (mainContent && !mainContent.id) {
            mainContent.id = 'main-content';
            mainContent.setAttribute('role', 'main');
        }

        const nav = document.querySelector('nav') ||
                   document.querySelector('[role="navigation"]');

        if (nav && !nav.id) {
            nav.id = 'main-navigation';
        }

        // Gestion du clic
        document.querySelectorAll('.skip-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const target = document.getElementById(targetId);

                if (target) {
                    target.setAttribute('tabindex', '-1');
                    target.focus();
                    target.scrollIntoView({ behavior: 'smooth' });

                    setTimeout(() => {
                        target.removeAttribute('tabindex');
                    }, 1000);
                }
            });
        });
    }

    // ========================================================================
    // 3. ARIA LIVE REGIONS POUR NOTIFICATIONS
    // ========================================================================

    function createLiveRegion() {
        const liveRegion = document.createElement('div');
        liveRegion.setAttribute('role', 'status');
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.className = 'sr-only';
        liveRegion.id = 'aria-live-region';
        document.body.appendChild(liveRegion);
    }

    window.announceToScreenReader = function(message, priority = 'polite') {
        const liveRegion = document.getElementById('aria-live-region');
        if (liveRegion) {
            liveRegion.setAttribute('aria-live', priority); // 'polite' ou 'assertive'
            liveRegion.textContent = '';
            setTimeout(() => {
                liveRegion.textContent = message;
            }, 100);
        }
    };

    // ========================================================================
    // 4. VALIDATION ATTRIBUTS ARIA MANQUANTS
    // ========================================================================

    function validateAriaAttributes() {
        // Boutons sans label accessible
        document.querySelectorAll('button:not([aria-label]):not([aria-labelledby])').forEach(button => {
            if (!button.textContent.trim() && !button.querySelector('img[alt]')) {
                console.warn('Bouton sans label accessible:', button);
                button.setAttribute('aria-label', 'Bouton');
            }
        });

        // Images sans alt
        document.querySelectorAll('img:not([alt])').forEach(img => {
            console.error('Image sans attribut alt:', img.src);
            img.setAttribute('alt', 'Image sans description');
        });

        // Liens vides
        document.querySelectorAll('a:not([aria-label]):not([aria-labelledby])').forEach(link => {
            if (!link.textContent.trim() && !link.querySelector('img[alt]')) {
                console.warn('Lien sans contenu textuel:', link);
            }
        });

        // Inputs sans label
        document.querySelectorAll('input:not([type="hidden"]):not([aria-label]):not([aria-labelledby])').forEach(input => {
            const label = document.querySelector(`label[for="${input.id}"]`);
            if (!label && !input.closest('label')) {
                console.warn('Input sans label:', input);
            }
        });
    }

    // ========================================================================
    // 5. GESTION DU FOCUS TRAP POUR MODALES
    // ========================================================================

    function trapFocus(element) {
        const focusableElements = element.querySelectorAll(
            'a[href], button:not([disabled]), textarea:not([disabled]), ' +
            'input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
        );

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        element.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            }

            if (e.key === 'Escape') {
                const closeButton = element.querySelector('[data-bs-dismiss="modal"], .btn-close');
                if (closeButton) {
                    closeButton.click();
                }
            }
        });

        // Focus sur le premier élément
        firstElement && firstElement.focus();
    }

    // Appliquer aux modales Bootstrap
    document.addEventListener('shown.bs.modal', function(e) {
        trapFocus(e.target);
        announceToScreenReader('Modal ouverte');
    });

    document.addEventListener('hidden.bs.modal', function() {
        announceToScreenReader('Modal fermée');
    });

    // ========================================================================
    // 6. AMÉLIORATION FORMULAIRES
    // ========================================================================

    function enhanceForms() {
        // Marquer champs requis
        document.querySelectorAll('input[required], select[required], textarea[required]').forEach(field => {
            const label = document.querySelector(`label[for="${field.id}"]`) ||
                         field.closest('label');

            if (label && !label.classList.contains('required')) {
                label.classList.add('required');
            }

            field.setAttribute('aria-required', 'true');
        });

        // Associer messages d'erreur
        document.querySelectorAll('.invalid-feedback, .error-message').forEach(error => {
            const input = error.previousElementSibling;
            if (input && input.matches('input, select, textarea')) {
                const errorId = 'error-' + (input.id || Math.random().toString(36).substr(2, 9));
                error.id = errorId;
                input.setAttribute('aria-describedby', errorId);
                input.setAttribute('aria-invalid', 'true');
            }
        });

        // Validation en temps réel avec annonce
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const invalidFields = form.querySelectorAll(':invalid');
                if (invalidFields.length > 0) {
                    e.preventDefault();
                    announceToScreenReader(`${invalidFields.length} champ(s) invalide(s) détecté(s)`, 'assertive');
                    invalidFields[0].focus();
                }
            });
        });
    }

    // ========================================================================
    // 7. AMÉLIORATION TABLEAUX
    // ========================================================================

    function enhanceTables() {
        document.querySelectorAll('table:not([role])').forEach(table => {
            table.setAttribute('role', 'table');

            // Ajouter caption si manquant
            if (!table.querySelector('caption')) {
                const caption = document.createElement('caption');
                caption.className = 'sr-only';
                caption.textContent = table.getAttribute('aria-label') || 'Tableau de données';
                table.insertBefore(caption, table.firstChild);
            }

            // S'assurer que les en-têtes ont scope
            table.querySelectorAll('th').forEach(th => {
                if (!th.getAttribute('scope')) {
                    th.setAttribute('scope', 'col');
                }
            });
        });
    }

    // ========================================================================
    // 8. NAVIGATION LANDMARKS
    // ========================================================================

    function addLandmarks() {
        // Header
        const header = document.querySelector('header');
        if (header && !header.getAttribute('role')) {
            header.setAttribute('role', 'banner');
        }

        // Navigation
        const nav = document.querySelector('nav');
        if (nav && !nav.getAttribute('role')) {
            nav.setAttribute('role', 'navigation');
            if (!nav.getAttribute('aria-label')) {
                nav.setAttribute('aria-label', 'Navigation principale');
            }
        }

        // Main
        const main = document.querySelector('main');
        if (main && !main.getAttribute('role')) {
            main.setAttribute('role', 'main');
        }

        // Footer
        const footer = document.querySelector('footer');
        if (footer && !footer.getAttribute('role')) {
            footer.setAttribute('role', 'contentinfo');
        }

        // Aside
        document.querySelectorAll('aside').forEach(aside => {
            if (!aside.getAttribute('role')) {
                aside.setAttribute('role', 'complementary');
            }
        });
    }

    // ========================================================================
    // 9. GESTION LANG POUR CONTENU MULTILINGUE
    // ========================================================================

    function handleLanguage() {
        if (!document.documentElement.lang) {
            document.documentElement.lang = 'fr';
        }

        // Marquer contenu dans autre langue
        document.querySelectorAll('[data-lang]').forEach(element => {
            element.setAttribute('lang', element.dataset.lang);
        });
    }

    // ========================================================================
    // 10. RACCOURCIS CLAVIER GLOBAUX
    // ========================================================================

    function setupKeyboardShortcuts() {
        const shortcuts = {
            'Alt+1': () => window.location.href = 'dashboard.php',
            'Alt+N': () => {
                const newBtn = document.querySelector('[href*="create"]');
                newBtn && newBtn.click();
            },
            'Alt+S': () => {
                const searchInput = document.querySelector('input[type="search"], input[name="search"]');
                searchInput && searchInput.focus();
            },
            '/': () => {
                const searchInput = document.querySelector('input[type="search"], input[name="search"]');
                if (searchInput && document.activeElement !== searchInput) {
                    searchInput.focus();
                }
            }
        };

        document.addEventListener('keydown', function(e) {
            const key = (e.altKey ? 'Alt+' : '') +
                       (e.ctrlKey ? 'Ctrl+' : '') +
                       (e.shiftKey ? 'Shift+' : '') +
                       e.key;

            if (shortcuts[key]) {
                e.preventDefault();
                shortcuts[key]();
            }
        });

        // Afficher aide raccourcis avec ?
        document.addEventListener('keydown', function(e) {
            if (e.key === '?' && e.shiftKey) {
                showKeyboardShortcutsHelp();
            }
        });
    }

    function showKeyboardShortcutsHelp() {
        alert(`Raccourcis clavier disponibles:

Alt+1 : Retour au tableau de bord
Alt+N : Nouveau dossier
Alt+S ou / : Rechercher
Ctrl+Shift+D : Changer thème (clair/sombre)
? : Afficher cette aide
Échap : Fermer modal`);
    }

    // ========================================================================
    // INITIALISATION
    // ========================================================================

    function init() {
        createSkipLinks();
        createLiveRegion();
        validateAriaAttributes();
        enhanceForms();
        enhanceTables();
        addLandmarks();
        handleLanguage();
        setupKeyboardShortcuts();

        console.log('%c♿ Accessibilité WCAG 2.1 activée', 'color: #059669; font-weight: bold;');
        console.log('Raccourcis: Shift+? pour aide');
    }

    // Lancer au chargement du DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Exposer API publique
    window.accessibility = {
        announce: announceToScreenReader,
        trapFocus: trapFocus,
        showHelp: showKeyboardShortcutsHelp
    };

})();

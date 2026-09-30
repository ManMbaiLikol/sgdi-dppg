        <footer class="app-footer">
            <span><strong>SGDI</strong> · MINEE - Direction des Produits Pétroliers et du Gaz</span>
            <span>© <?php echo date('Y'); ?></span>
        </footer>
    </div></main> <!-- Fin du contenu -->
<?php if (isLoggedIn()): ?>
    </div> <!-- .app-main -->
</div> <!-- .app-shell -->
<?php else: ?>
</div> <!-- .app-main -->
<?php endif; ?>

<!-- jQuery (doit être chargé avant Bootstrap et DataTables) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- DataTables Buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
<!-- JSZip (Excel) et pdfmake (PDF) : chargés seulement au clic sur un bouton d'export -->
<script src="<?php echo asset('js/datatables-lazy-export.js'); ?>"></script>
<!-- DataTables Config -->
<script src="<?php echo asset('js/datatables-config.js'); ?>"></script>
<!-- Custom JS -->
<script src="<?php echo asset('js/app.js'); ?>"></script>
<!-- Thème clair / sombre (appliqué dès le <head> par uiThemeInitScript) -->
<script>
document.querySelectorAll('#theme-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var root = document.documentElement;
        var t = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', t);
        root.setAttribute('data-theme', t); // anciennes feuilles de style
        try { localStorage.setItem('sgdi_theme', t); } catch (e) {}
    });
});
// Raccourci « / » : recherche globale
document.addEventListener('keydown', function (e) {
    var champ = document.getElementById('recherche-globale');
    if (champ && e.key === '/' && !/input|select|textarea/i.test(document.activeElement.tagName) && !document.activeElement.isContentEditable) {
        e.preventDefault(); champ.focus();
    }
});
</script>
<!-- Accessibility Enhancements -->
<script src="<?php echo asset('js/accessibility.js'); ?>"></script>
<!-- Advanced Tables -->
<script src="<?php echo asset('js/advanced-tables.js'); ?>"></script>
<!-- PWA Registration -->
<script>
// Enregistrement du Service Worker pour PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('<?php echo url('service-worker.js'); ?>')
            .then((registration) => {
                console.log('Service Worker enregistré:', registration.scope);

                // Vérifier les mises à jour
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // Nouvelle version disponible
                            if (confirm('Une nouvelle version est disponible. Actualiser maintenant ?')) {
                                newWorker.postMessage({ type: 'SKIP_WAITING' });
                                window.location.reload();
                            }
                        }
                    });
                });
            })
            .catch((error) => {
                console.error('Erreur d\'enregistrement du Service Worker:', error);
            });

        // Recharger la page lors de l'activation d'un nouveau Service Worker
        let refreshing = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (!refreshing) {
                refreshing = true;
                window.location.reload();
            }
        });
    });
}

// Prompt d'installation PWA
let deferredPrompt;
const installBanner = document.createElement('div');
installBanner.className = 'alert alert-info alert-dismissible fade show position-fixed bottom-0 start-0 end-0 m-3';
installBanner.style.zIndex = '9999';
installBanner.innerHTML = `
    <strong><i class="fas fa-mobile-alt"></i> Installer SGDI</strong><br>
    Installez l'application sur votre appareil pour un accès rapide et hors ligne.
    <button type="button" class="btn btn-sm btn-primary ms-2" id="installBtn">Installer</button>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
`;

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;

    // Afficher le banner après 30 secondes (pour ne pas être intrusif)
    setTimeout(() => {
        if (!localStorage.getItem('pwa-dismissed')) {
            document.body.appendChild(installBanner);
        }
    }, 30000);

    document.getElementById('installBtn')?.addEventListener('click', async () => {
        if (!deferredPrompt) return;

        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;

        console.log(`Installation PWA: ${outcome}`);
        deferredPrompt = null;
        installBanner.remove();
    });
});

// Sauvegarder la préférence de non-installation
installBanner.querySelector('.btn-close')?.addEventListener('click', () => {
    localStorage.setItem('pwa-dismissed', 'true');
});

// Détection de l'installation
window.addEventListener('appinstalled', () => {
    console.log('PWA installée avec succès');
    installBanner.remove();
    localStorage.setItem('pwa-installed', 'true');
});

// Détection de mode standalone (app installée)
if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
    console.log('Application lancée en mode standalone');
    document.body.classList.add('pwa-standalone');
}
</script>


</body>
</html>
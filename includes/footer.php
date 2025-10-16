</div> <!-- Fin du container principal -->

<!-- jQuery (doit être chargé avant Bootstrap et DataTables) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- DataTables Buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
<!-- Theme JS -->
<script src="<?php echo asset('js/theme.js'); ?>"></script>
<!-- DataTables Config -->
<script src="<?php echo asset('js/datatables-config.js'); ?>"></script>
<!-- Custom JS -->
<script src="<?php echo asset('js/app.js'); ?>?v=<?php echo time(); ?>"></script>
<!-- Theme Toggle (Mode Sombre/Clair) -->
<script src="<?php echo asset('js/theme-toggle.js'); ?>"></script>
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

<footer class="bg-light mt-5 py-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <p class="mb-0 text-muted">
                    <strong>SGDI</strong> - Système de Gestion des Dossiers d'Implantation
                </p>
                <p class="mb-0 text-muted small">
                    MINEE - Direction des Produits Pétroliers et du Gaz
                </p>
            </div>
            <div class="col-md-6 text-end">
                <p class="mb-0 text-muted small">
                    Version MVP 1.0 - <?php echo date('Y'); ?>
                </p>
                <?php if (isLoggedIn()): ?>
                <p class="mb-0 text-muted small">
                    Connecté en tant que: <strong><?php echo getRoleLabel($_SESSION['user_role']); ?></strong>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>

</body>
</html>
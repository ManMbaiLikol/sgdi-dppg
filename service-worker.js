/**
 * Service Worker pour SGDI
 * Permet le fonctionnement offline et améliore les performances
 */

const CACHE_NAME = 'sgdi-v1.0';
const CACHE_URLS = [
    '/',
    '/dashboard.php',
    '/login.php',
    '/assets/css/style.css',
    '/assets/css/theme.css',
    '/assets/css/responsive.css',
    '/assets/js/app.js',
    '/assets/js/theme.js',
    '/assets/js/datatables-config.js',
    '/manifest.json',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
    'https://code.jquery.com/jquery-3.6.0.min.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'
];

/**
 * Installation du Service Worker
 */
self.addEventListener('install', (event) => {
    console.log('[SW] Installation en cours...');

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Cache ouvert');
                return cache.addAll(CACHE_URLS);
            })
            .then(() => {
                console.log('[SW] Fichiers mis en cache');
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('[SW] Erreur lors de la mise en cache:', error);
            })
    );
});

/**
 * Activation du Service Worker
 */
self.addEventListener('activate', (event) => {
    console.log('[SW] Activation en cours...');

    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((cacheName) => {
                            // Supprimer les anciens caches
                            return cacheName !== CACHE_NAME;
                        })
                        .map((cacheName) => {
                            console.log('[SW] Suppression du cache:', cacheName);
                            return caches.delete(cacheName);
                        })
                );
            })
            .then(() => {
                console.log('[SW] Service Worker activé');
                return self.clients.claim();
            })
    );
});

/**
 * Interception des requêtes
 * Stratégie: Network First, puis Cache
 */
self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Ignorer les requêtes non-GET
    if (request.method !== 'GET') {
        return;
    }

    // Ignorer les requêtes vers des domaines externes (sauf CDN)
    const url = new URL(request.url);
    if (url.origin !== location.origin &&
        !url.host.includes('cdn.jsdelivr.net') &&
        !url.host.includes('cdnjs.cloudflare.com') &&
        !url.host.includes('code.jquery.com')) {
        return;
    }

    event.respondWith(
        fetch(request)
            .then((response) => {
                // Si la réponse est valide, la mettre en cache
                if (response && response.status === 200 && response.type === 'basic') {
                    const responseToCache = response.clone();

                    caches.open(CACHE_NAME)
                        .then((cache) => {
                            cache.put(request, responseToCache);
                        });
                }

                return response;
            })
            .catch(() => {
                // Si offline, utiliser le cache
                return caches.match(request)
                    .then((cachedResponse) => {
                        if (cachedResponse) {
                            return cachedResponse;
                        }

                        // Si pas en cache, retourner une page offline
                        if (request.headers.get('accept').includes('text/html')) {
                            return caches.match('/offline.html');
                        }
                    });
            })
    );
});

/**
 * Synchronisation en arrière-plan
 */
self.addEventListener('sync', (event) => {
    console.log('[SW] Synchronisation:', event.tag);

    if (event.tag === 'sync-dossiers') {
        event.waitUntil(syncDossiers());
    }
});

/**
 * Notifications push
 */
self.addEventListener('push', (event) => {
    const options = {
        body: event.data ? event.data.text() : 'Nouvelle notification SGDI',
        icon: '/assets/images/icons/icon-192x192.png',
        badge: '/assets/images/icons/icon-72x72.png',
        vibrate: [200, 100, 200],
        tag: 'sgdi-notification',
        actions: [
            {
                action: 'open',
                title: 'Ouvrir',
                icon: '/assets/images/icons/icon-96x96.png'
            },
            {
                action: 'close',
                title: 'Fermer',
                icon: '/assets/images/icons/icon-96x96.png'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification('SGDI', options)
    );
});

/**
 * Clic sur notification
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    if (event.action === 'open') {
        event.waitUntil(
            clients.openWindow('/')
        );
    }
});

/**
 * Fonction de synchronisation des dossiers
 */
async function syncDossiers() {
    try {
        // Récupérer les données en attente depuis IndexedDB
        const db = await openDatabase();
        const pendingData = await getPendingSync(db);

        if (pendingData.length === 0) {
            console.log('[SW] Aucune donnée en attente de synchronisation');
            return;
        }

        // Envoyer les données au serveur
        for (const data of pendingData) {
            try {
                const response = await fetch('/api/sync', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                if (response.ok) {
                    await removePendingSync(db, data.id);
                    console.log('[SW] Synchronisation réussie:', data.id);
                }
            } catch (error) {
                console.error('[SW] Erreur de synchronisation:', error);
            }
        }
    } catch (error) {
        console.error('[SW] Erreur lors de la synchronisation:', error);
    }
}

/**
 * Ouvrir IndexedDB
 */
function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('SGDI_DB', 1);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('pending_sync')) {
                db.createObjectStore('pending_sync', { keyPath: 'id', autoIncrement: true });
            }
        };
    });
}

/**
 * Récupérer les données en attente
 */
function getPendingSync(db) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['pending_sync'], 'readonly');
        const store = transaction.objectStore('pending_sync');
        const request = store.getAll();

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
}

/**
 * Supprimer une donnée synchronisée
 */
function removePendingSync(db, id) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['pending_sync'], 'readwrite');
        const store = transaction.objectStore('pending_sync');
        const request = store.delete(id);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve();
    });
}

/**
 * Messages du client
 */
self.addEventListener('message', (event) => {
    console.log('[SW] Message reçu:', event.data);

    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => caches.delete(cacheName))
                );
            })
        );
    }
});

console.log('[SW] Service Worker chargé');

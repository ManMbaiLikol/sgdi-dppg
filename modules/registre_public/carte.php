<?php
// Carte publique interactive des infrastructures
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/map_functions.php';

$page_title = 'Carte des Infrastructures Pétrolières';

// Récupérer les infrastructures autorisées avec coordonnées GPS (incluant historique_autorise)
$filters = [
    'statuts' => ['autorise', 'historique_autorise'],
    'type_infrastructure' => sanitize($_GET['type'] ?? ''),
    'region' => sanitize($_GET['region'] ?? '')
];

$infrastructures = getAllInfrastructuresForMap($filters);

// Récupérer les régions
$regions = $pdo->query("SELECT DISTINCT region FROM dossiers WHERE region IS NOT NULL AND region != '' AND statut IN ('autorise', 'historique_autorise') ORDER BY region")->fetchAll(PDO::FETCH_COLUMN);

// Statistiques
$stats = [
    'total' => count($infrastructures),
    'stations' => count(array_filter($infrastructures, fn($i) => $i['type_infrastructure'] === 'station_service')),
    'points' => count(array_filter($infrastructures, fn($i) => $i['type_infrastructure'] === 'point_consommateur')),
    'depots' => count(array_filter($infrastructures, fn($i) => $i['type_infrastructure'] === 'depot_gpl')),
    'centres' => count(array_filter($infrastructures, fn($i) => $i['type_infrastructure'] === 'centre_emplisseur'))
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - MINEE/DPPG</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    <link href="../../assets/css/registre_public.css" rel="stylesheet">

    <style>
        body {
            margin: 0;
            padding: 0;
            background: white !important;
        }

        #map {
            position: absolute;
            top: 70px;
            bottom: 0;
            left: 0;
            right: 0;
        }

        .public-header {
            padding: 1rem 0;
            position: relative;
            z-index: 1000;
        }

        .map-controls {
            position: absolute;
            top: 90px;
            left: 20px;
            z-index: 1000;
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-width: 350px;
        }

        .stats-panel {
            position: absolute;
            top: 90px;
            right: 20px;
            z-index: 1000;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-width: 250px;
        }

        .stat-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .legend {
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .legend-item {
            margin: 0.5rem 0;
            display: flex;
            align-items: center;
        }

        .legend-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        .marker-station { background-color: #3b82f6; }
        .marker-point { background-color: #10b981; }
        .marker-depot { background-color: #f59e0b; }
        .marker-centre { background-color: #ef4444; }

        /* Style pour les marqueurs personnalisés type Google Maps */
        .custom-map-marker {
            background: none !important;
            border: none !important;
        }

        .marker-pin {
            width: 30px;
            height: 30px;
            border-radius: 50% 50% 50% 0;
            background: #c30b82;
            position: absolute;
            transform: rotate(-45deg);
            left: 50%;
            top: 50%;
            margin: -15px 0 0 -15px;
            animation-fill-mode: both;
            box-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }

        .marker-pin::after {
            content: '';
            width: 20px;
            height: 20px;
            margin: 5px 0 0 5px;
            background: white;
            position: absolute;
            border-radius: 50%;
        }

        .marker-icon {
            position: absolute;
            width: 18px;
            height: 18px;
            left: 50%;
            top: 50%;
            margin-left: -9px;
            margin-top: -9px;
            font-size: 14px;
            color: white;
            text-align: center;
            transform: rotate(45deg);
            z-index: 1;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="public-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-map-marked-alt"></i> Carte des Infrastructures Pétrolières</h4>
                <a href="index.php" class="btn btn-light">
                    <i class="fas fa-list"></i> Voir le registre
                </a>
            </div>
        </div>
    </div>

    <!-- Carte -->
    <div id="map"></div>

    <!-- Contrôles de filtrage -->
    <div class="map-controls">
        <h5 class="mb-3"><i class="fas fa-filter"></i> Filtres</h5>
        <form method="GET" action="">
            <div class="mb-3">
                <label class="form-label">Type d'infrastructure</label>
                <select class="form-select form-select-sm" name="type" onchange="this.form.submit()">
                    <option value="">Tous les types</option>
                    <option value="station_service" <?php echo (htmlspecialchars($_GET['type'] ?? '', ENT_QUOTES, 'UTF-8') === 'station_service') ? 'selected' : ''; ?>>
                        Stations-service
                    </option>
                    <option value="point_consommateur" <?php echo (htmlspecialchars($_GET['type'] ?? '', ENT_QUOTES, 'UTF-8') === 'point_consommateur') ? 'selected' : ''; ?>>
                        Points consommateurs
                    </option>
                    <option value="depot_gpl" <?php echo (htmlspecialchars($_GET['type'] ?? '', ENT_QUOTES, 'UTF-8') === 'depot_gpl') ? 'selected' : ''; ?>>
                        Dépôts GPL
                    </option>
                    <option value="centre_emplisseur" <?php echo (htmlspecialchars($_GET['type'] ?? '', ENT_QUOTES, 'UTF-8') === 'centre_emplisseur') ? 'selected' : ''; ?>>
                        Centres emplisseurs
                    </option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Région</label>
                <select class="form-select form-select-sm" name="region" onchange="this.form.submit()">
                    <option value="">Toutes les régions</option>
                    <?php foreach($regions as $r): ?>
                        <option value="<?php echo htmlspecialchars($r, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo (htmlspecialchars($_GET['region'] ?? '', ENT_QUOTES, 'UTF-8') === $r) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="carte.php" class="btn btn-secondary btn-sm w-100">
                <i class="fas fa-redo"></i> Réinitialiser
            </a>
        </form>
    </div>

    <!-- Panneau statistiques -->
    <div class="stats-panel">
        <h6 class="mb-3"><i class="fas fa-chart-bar"></i> Statistiques</h6>
        <div class="stat-item">
            <strong><?php echo $stats['total']; ?></strong>
            <small class="text-muted d-block">Total infrastructures</small>
        </div>
        <div class="stat-item">
            <strong><?php echo $stats['stations']; ?></strong>
            <small class="text-muted d-block">Stations-service</small>
        </div>
        <div class="stat-item">
            <strong><?php echo $stats['points']; ?></strong>
            <small class="text-muted d-block">Points consommateurs</small>
        </div>
        <div class="stat-item">
            <strong><?php echo $stats['depots']; ?></strong>
            <small class="text-muted d-block">Dépôts GPL</small>
        </div>
        <div class="stat-item">
            <strong><?php echo $stats['centres']; ?></strong>
            <small class="text-muted d-block">Centres emplisseurs</small>
        </div>
    </div>

    <!-- Légende -->
    <div class="legend">
        <h6 class="mb-2"><i class="fas fa-info-circle"></i> Légende</h6>
        <div class="legend-item">
            <i class="fas fa-gas-pump" style="color: #3b82f6; font-size: 16px; width: 20px;"></i>
            <small>Station-service</small>
        </div>
        <div class="legend-item">
            <i class="fas fa-industry" style="color: #10b981; font-size: 16px; width: 20px;"></i>
            <small>Point consommateur</small>
        </div>
        <div class="legend-item">
            <i class="fas fa-warehouse" style="color: #f59e0b; font-size: 16px; width: 20px;"></i>
            <small>Dépôt GPL</small>
        </div>
        <div class="legend-item">
            <i class="fas fa-fill-drip" style="color: #ef4444; font-size: 16px; width: 20px;"></i>
            <small>Centre emplisseur</small>
        </div>
        <hr style="margin: 10px 0;">
        <div class="legend-item">
            <div style="display: inline-block; width: 20px; height: 20px; border: 2px solid #dc3545; border-radius: 50%; background: rgba(220, 53, 69, 0.1);"></div>
            <small>Zone de contrainte (500m)</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script>
        // Debug: Vérifier que Leaflet est chargé
        console.log('Leaflet chargé:', typeof L !== 'undefined');

        // Initialiser la carte centrée sur le Cameroun
        const map = L.map('map').setView([5.5, 11.5], 7);
        console.log('Carte initialisée:', map);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18
        }).addTo(map);

        // Créer les groupes de clusters
        const markerCluster = L.markerClusterGroup({
            maxClusterRadius: 50,
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false
        });

        // Créer un LayerGroup pour les cercles de contrainte (optimisation performance)
        const circlesLayer = L.layerGroup();
        const circles = []; // Stockage des cercles

        // Données des infrastructures
        const infrastructures = <?php echo json_encode($infrastructures); ?>;
        console.log('Nombre d\'infrastructures:', infrastructures.length);
        console.log('Infrastructures:', infrastructures);

        // Fonction pour obtenir la couleur du marqueur
        function getMarkerColor(type) {
            const colors = {
                'station_service': '#3b82f6',
                'point_consommateur': '#10b981',
                'depot_gpl': '#f59e0b',
                'centre_emplisseur': '#ef4444'
            };
            return colors[type] || '#6b7280';
        }

        // Fonction pour obtenir l'icône selon le type
        function getIconForType(type) {
            const icons = {
                'station_service': 'fa-gas-pump',      // Pompe à essence
                'point_consommateur': 'fa-industry',   // Usine/Industrie
                'depot_gpl': 'fa-warehouse',           // Entrepôt
                'centre_emplisseur': 'fa-fill-drip'    // Remplissage
            };
            return icons[type] || 'fa-map-marker';
        }

        // Fonction pour créer une icône personnalisée type Google Maps
        function createCustomIcon(type) {
            const color = getMarkerColor(type);
            const iconClass = getIconForType(type);

            return L.divIcon({
                className: 'custom-map-marker',
                html: `
                    <div style="position: relative; width: 35px; height: 45px;">
                        <!-- Pin style Google Maps -->
                        <svg width="35" height="45" viewBox="0 0 35 45" xmlns="http://www.w3.org/2000/svg">
                            <!-- Ombre -->
                            <ellipse cx="17.5" cy="42" rx="8" ry="3" fill="rgba(0,0,0,0.2)"/>
                            <!-- Pin -->
                            <path d="M17.5 0C10.5 0 5 5.5 5 12.5c0 8.75 12.5 27.5 12.5 27.5S30 21.25 30 12.5C30 5.5 24.5 0 17.5 0z"
                                  fill="${color}" stroke="white" stroke-width="1.5"/>
                            <!-- Cercle intérieur -->
                            <circle cx="17.5" cy="12.5" r="6" fill="white"/>
                        </svg>
                        <!-- Icône FontAwesome -->
                        <i class="fas ${iconClass}" style="position: absolute; top: 7px; left: 50%; transform: translateX(-50%); font-size: 11px; color: ${color};"></i>
                    </div>
                `,
                iconSize: [35, 45],
                iconAnchor: [17.5, 42],  // Point d'ancrage à la pointe du pin
                popupAnchor: [0, -42]     // Position du popup au-dessus du pin
            });
        }

        // Ajouter les marqueurs avec cercles de contrainte de 500m
        console.log('Début ajout des marqueurs...');
        let markersAdded = 0;
        infrastructures.forEach(infra => {
            console.log('Traitement infrastructure:', infra.numero, 'Lat:', infra.latitude, 'Lng:', infra.longitude);
            if (infra.latitude && infra.longitude) {
                const marker = L.marker([infra.latitude, infra.longitude], {
                    icon: createCustomIcon(infra.type_infrastructure)
                });
                console.log('Marqueur créé pour:', infra.numero);
                markersAdded++;

                const popupContent = `
                    <div style="min-width: 200px;">
                        <h6 class="mb-2"><strong>${infra.nom_demandeur}</strong></h6>
                        <p class="mb-1"><small><i class="fas fa-tag"></i> ${formatTypeInfra(infra.type_infrastructure)}</small></p>
                        <p class="mb-1"><small><i class="fas fa-map-marker-alt"></i> ${infra.ville}, ${infra.region}</small></p>
                        ${infra.operateur_proprietaire ? `<p class="mb-1"><small><i class="fas fa-building"></i> ${infra.operateur_proprietaire}</small></p>` : ''}
                        <hr class="my-2">
                        <p class="mb-0"><small><i class="fas fa-shield-alt"></i> Zone de contrainte: 500m</small></p>
                        <hr class="my-2">
                        <a href="detail.php?numero=${encodeURIComponent(infra.numero)}" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-eye"></i> Voir détails
                        </a>
                    </div>
                `;

                marker.bindPopup(popupContent);
                markerCluster.addLayer(marker);

                // Créer un cercle de contrainte de 500m (ne pas l'ajouter immédiatement pour optimisation)
                const circle = L.circle([infra.latitude, infra.longitude], {
                    color: '#dc3545',
                    fillColor: '#dc3545',
                    fillOpacity: 0.1,
                    radius: 500, // 500 mètres
                    weight: 1,
                    opacity: 0.5
                });

                // Tooltip pour le cercle
                circle.bindTooltip(`Zone de contrainte 500m<br>${infra.nom_demandeur}`, {
                    permanent: false,
                    direction: 'center'
                });

                // Stocker le cercle pour l'afficher conditionnellement
                circles.push(circle);

                console.log('Marqueur créé');
            }
        });
        console.log('Total marqueurs ajoutés:', markersAdded);

        map.addLayer(markerCluster);
        console.log('Cluster ajouté à la carte');

        // Fonction de formatage du type
        function formatTypeInfra(type) {
            const types = {
                'station_service': 'Station-service',
                'point_consommateur': 'Point consommateur',
                'depot_gpl': 'Dépôt GPL',
                'centre_emplisseur': 'Centre emplisseur'
            };
            return types[type] || type;
        }

        // Gestion optimisée des cercles de contrainte selon le niveau de zoom
        let circlesVisible = false;
        const ZOOM_THRESHOLD = 12; // Afficher les cercles à partir du zoom 12

        function toggleCircles() {
            const currentZoom = map.getZoom();

            if (currentZoom >= ZOOM_THRESHOLD && !circlesVisible) {
                // Afficher les cercles quand on zoome suffisamment
                console.log('Affichage des cercles de contrainte');
                circles.forEach(circle => circle.addTo(circlesLayer));
                circlesLayer.addTo(map);
                circlesVisible = true;
            } else if (currentZoom < ZOOM_THRESHOLD && circlesVisible) {
                // Cacher les cercles quand on dézoome
                console.log('Masquage des cercles de contrainte');
                map.removeLayer(circlesLayer);
                circlesVisible = false;
            }
        }

        // Écouter les changements de zoom
        map.on('zoomend', toggleCircles);

        // Vérifier le zoom initial
        toggleCircles();

        // Ajuster la vue pour afficher tous les marqueurs
        if (infrastructures.length > 0) {
            const bounds = markerCluster.getBounds();
            if (bounds.isValid()) {
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }
    </script>
</body>
</html>

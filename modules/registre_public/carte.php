<?php
// Carte publique interactive des infrastructures
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/map_functions.php';

$page_title = 'Carte des Infrastructures Pétrolières';

// Récupérer uniquement les infrastructures autorisées avec coordonnées GPS
$filters = [
    'statut' => 'autorise',
    'type_infrastructure' => sanitize($_GET['type'] ?? ''),
    'region' => sanitize($_GET['region'] ?? '')
];

$infrastructures = getAllInfrastructuresForMap($filters);

// Récupérer les régions
$regions = $pdo->query("SELECT DISTINCT region FROM dossiers WHERE region IS NOT NULL AND region != '' AND statut = 'autorise' ORDER BY region")->fetchAll(PDO::FETCH_COLUMN);

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

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        #map {
            position: absolute;
            top: 70px;
            bottom: 0;
            left: 0;
            right: 0;
        }

        .public-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
                    <option value="station_service" <?php echo ($_GET['type'] ?? '') === 'station_service' ? 'selected' : ''; ?>>
                        Stations-service
                    </option>
                    <option value="point_consommateur" <?php echo ($_GET['type'] ?? '') === 'point_consommateur' ? 'selected' : ''; ?>>
                        Points consommateurs
                    </option>
                    <option value="depot_gpl" <?php echo ($_GET['type'] ?? '') === 'depot_gpl' ? 'selected' : ''; ?>>
                        Dépôts GPL
                    </option>
                    <option value="centre_emplisseur" <?php echo ($_GET['type'] ?? '') === 'centre_emplisseur' ? 'selected' : ''; ?>>
                        Centres emplisseurs
                    </option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Région</label>
                <select class="form-select form-select-sm" name="region" onchange="this.form.submit()">
                    <option value="">Toutes les régions</option>
                    <?php foreach($regions as $r): ?>
                        <option value="<?php echo htmlspecialchars($r); ?>"
                                <?php echo ($_GET['region'] ?? '') === $r ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r); ?>
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
        <h6 class="mb-2">Légende</h6>
        <div class="legend-item">
            <div class="legend-icon marker-station"></div>
            <small>Station-service</small>
        </div>
        <div class="legend-item">
            <div class="legend-icon marker-point"></div>
            <small>Point consommateur</small>
        </div>
        <div class="legend-item">
            <div class="legend-icon marker-depot"></div>
            <small>Dépôt GPL</small>
        </div>
        <div class="legend-item">
            <div class="legend-icon marker-centre"></div>
            <small>Centre emplisseur</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script>
        // Initialiser la carte centrée sur le Cameroun
        const map = L.map('map').setView([5.5, 11.5], 7);

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

        // Données des infrastructures
        const infrastructures = <?php echo json_encode($infrastructures); ?>;

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

        // Fonction pour créer une icône personnalisée
        function createCustomIcon(type) {
            const color = getMarkerColor(type);
            return L.divIcon({
                className: 'custom-marker',
                html: `<div style="background-color: ${color}; width: 25px; height: 25px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
                iconSize: [25, 25],
                iconAnchor: [12, 12]
            });
        }

        // Ajouter les marqueurs
        infrastructures.forEach(infra => {
            if (infra.latitude && infra.longitude) {
                const marker = L.marker([infra.latitude, infra.longitude], {
                    icon: createCustomIcon(infra.type_infrastructure)
                });

                const popupContent = `
                    <div style="min-width: 200px;">
                        <h6 class="mb-2"><strong>${infra.nom_demandeur}</strong></h6>
                        <p class="mb-1"><small><i class="fas fa-tag"></i> ${formatTypeInfra(infra.type_infrastructure)}</small></p>
                        <p class="mb-1"><small><i class="fas fa-map-marker-alt"></i> ${infra.ville}, ${infra.region}</small></p>
                        ${infra.operateur_proprietaire ? `<p class="mb-1"><small><i class="fas fa-building"></i> ${infra.operateur_proprietaire}</small></p>` : ''}
                        <hr class="my-2">
                        <a href="detail.php?numero=${encodeURIComponent(infra.numero)}" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-eye"></i> Voir détails
                        </a>
                    </div>
                `;

                marker.bindPopup(popupContent);
                markerCluster.addLayer(marker);
            }
        });

        map.addLayer(markerCluster);

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

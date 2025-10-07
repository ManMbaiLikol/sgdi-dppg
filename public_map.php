<?php
// Carte publique des infrastructures autorisées - SGDI
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/map_functions.php';

$page_title = 'Registre public - Carte des infrastructures';

// Récupérer les infrastructures avec statuts avancés (payé, inspecté, validé, autorisé)
$filters = [
    'statuts' => ['paye', 'inspecte', 'valide', 'autorise'], // Statuts affichables publiquement
    'type_infrastructure' => sanitize($_GET['type'] ?? ''),
    'region' => sanitize($_GET['region'] ?? '')
];

$infrastructures = getAllInfrastructuresForMap($filters);

// DEBUG - à retirer après
// echo "<pre>Filtres: "; print_r($filters); echo "</pre>";
// echo "<pre>Infrastructures trouvées: " . count($infrastructures) . "</pre>";
// echo "<pre>"; print_r($infrastructures); echo "</pre>";

// Récupérer les régions
$sql = "SELECT DISTINCT region FROM dossiers
        WHERE region IS NOT NULL AND region != ''
        AND statut IN ('paye', 'inspecte', 'valide', 'autorise')
        ORDER BY region";
$stmt = $pdo->query($sql);
$regions = $stmt->fetchAll(PDO::FETCH_COLUMN);

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
    <title><?php echo $page_title; ?> - SGDI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .header-public {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        #map {
            height: 600px;
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .footer-public {
            background: #2c3e50;
            color: white;
            padding: 30px 0;
            margin-top: 50px;
        }

        .legend-public {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* Style des tooltips */
        .leaflet-tooltip {
            background: rgba(0, 0, 0, 0.85) !important;
            color: white !important;
            border: none !important;
            border-radius: 6px !important;
            padding: 8px 12px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
        }

        .leaflet-tooltip::before {
            border-top-color: rgba(0, 0, 0, 0.85) !important;
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header-public">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-2">
                    <i class="fas fa-map-marked-alt"></i> Registre Public des Infrastructures Pétrolières
                </h1>
                <p class="mb-0">
                    Ministère de l'Eau et de l'Énergie (MINEE) - Direction des Produits Pétroliers et du Gaz (DPPG)
                </p>
            </div>
            <div class="col-md-4 text-end">
                <a href="index.php" class="btn btn-light">
                    <i class="fas fa-sign-in-alt"></i> Espace professionnel
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt-4">
    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-2 mb-3">
            <div class="stat-card">
                <div class="stat-number text-primary"><?php echo $stats['total']; ?></div>
                <div class="text-muted">Total autorisées</div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="stat-card">
                <div class="stat-number text-danger"><?php echo $stats['stations']; ?></div>
                <div class="text-muted">Stations-service</div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="stat-card">
                <div class="stat-number text-info"><?php echo $stats['points']; ?></div>
                <div class="text-muted">Points consommateurs</div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="stat-card">
                <div class="stat-number text-warning"><?php echo $stats['depots']; ?></div>
                <div class="text-muted">Dépôts GPL</div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="stat-card">
                <div class="stat-number text-purple"><?php echo $stats['centres']; ?></div>
                <div class="text-muted">Centres emplisseurs</div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="stat-card">
                <div class="stat-number text-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="text-muted">Toutes vérifiées</div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="filters-section">
        <h5 class="mb-3"><i class="fas fa-filter"></i> Filtrer la carte</h5>
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Type d'infrastructure</label>
                <select class="form-select" name="type" onchange="this.form.submit()">
                    <option value="">Tous les types</option>
                    <option value="station_service" <?php echo $filters['type_infrastructure'] === 'station_service' ? 'selected' : ''; ?>>
                        Stations-service
                    </option>
                    <option value="point_consommateur" <?php echo $filters['type_infrastructure'] === 'point_consommateur' ? 'selected' : ''; ?>>
                        Points consommateurs
                    </option>
                    <option value="depot_gpl" <?php echo $filters['type_infrastructure'] === 'depot_gpl' ? 'selected' : ''; ?>>
                        Dépôts GPL
                    </option>
                    <option value="centre_emplisseur" <?php echo $filters['type_infrastructure'] === 'centre_emplisseur' ? 'selected' : ''; ?>>
                        Centres emplisseurs
                    </option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Région</label>
                <select class="form-select" name="region" onchange="this.form.submit()">
                    <option value="">Toutes les régions</option>
                    <?php foreach ($regions as $region): ?>
                    <option value="<?php echo htmlspecialchars($region); ?>" <?php echo $filters['region'] === $region ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($region); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <?php if (array_filter($filters)): ?>
                <a href="?" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i> Réinitialiser
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Carte et légende -->
    <div class="row">
        <div class="col-md-9">
            <div id="map"></div>
        </div>

        <div class="col-md-3">
            <div class="legend-public">
                <h6 class="mb-3"><i class="fas fa-info-circle"></i> Légende</h6>

                <p class="small text-muted">
                    Cette carte présente les infrastructures pétrolières en cours de traitement avancé
                    (payées, inspectées, validées) ou autorisées par le MINEE/DPPG.
                </p>

                <hr>

                <strong class="d-block mb-2">Types:</strong>
                <div class="mb-2">
                    <span style="display: inline-block; width: 20px; height: 20px; background: #ff6b6b; border-radius: 50%;"></span>
                    Stations-service
                </div>
                <div class="mb-2">
                    <span style="display: inline-block; width: 20px; height: 20px; background: #4ecdc4; border-radius: 50%;"></span>
                    Points consommateurs
                </div>
                <div class="mb-2">
                    <span style="display: inline-block; width: 20px; height: 20px; background: #f7b731; border-radius: 50%;"></span>
                    Dépôts GPL
                </div>
                <div class="mb-2">
                    <span style="display: inline-block; width: 20px; height: 20px; background: #5f27cd; border-radius: 50%;"></span>
                    Centres emplisseurs
                </div>

                <hr>

                <div class="alert alert-info small mb-0">
                    <i class="fas fa-shield-alt"></i>
                    Les infrastructures présentées sont <strong>en cours de traitement ou autorisées</strong> par le MINEE/DPPG.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="footer-public">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <h5>MINEE - DPPG</h5>
                <p>Direction des Produits Pétroliers et du Gaz</p>
            </div>
            <div class="col-md-4">
                <h6>Liens utiles</h6>
                <ul class="list-unstyled">
                    <li><a href="#" class="text-white-50">Réglementation</a></li>
                    <li><a href="#" class="text-white-50">Contact</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6>Informations</h6>
                <p class="text-white-50">
                    Dernière mise à jour: <?php echo date('d/m/Y'); ?><br>
                    <?php echo count($infrastructures); ?> infrastructures géolocalisées
                </p>
            </div>
        </div>
        <hr class="bg-white">
        <div class="text-center text-white-50">
            <small>© <?php echo date('Y'); ?> MINEE - Tous droits réservés</small>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
const map = L.map('map').setView([7.3697, 12.3547], 6);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 18
}).addTo(map);

const markers = L.markerClusterGroup();
const infrastructures = <?php echo json_encode($infrastructures); ?>;

const iconColors = {
    'station_service': '#ff6b6b',
    'point_consommateur': '#4ecdc4',
    'depot_gpl': '#f7b731',
    'centre_emplisseur': '#5f27cd'
};

infrastructures.forEach(function(infra) {
    const color = iconColors[infra.type_infrastructure] || '#6c757d';

    const icon = L.divIcon({
        html: `<div style="background: ${color}; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3); font-size: 16px;">
                <i class="fas fa-check"></i>
               </div>`,
        className: 'custom-marker',
        iconSize: [32, 32]
    });

    const marker = L.marker([infra.latitude, infra.longitude], { icon: icon });

    // Tooltip au survol (info rapide)
    const tooltipContent = `
        <strong>${infra.nom_demandeur}</strong><br>
        <small>${infra.numero}</small><br>
        <small><i class="fas fa-map-marker-alt"></i> ${infra.ville}</small>
    `;

    marker.bindTooltip(tooltipContent, {
        permanent: false,
        direction: 'top',
        offset: [0, -20]
    });

    // Popup détaillé au clic
    const statusLabels = {
        'paye': 'En traitement (payée)',
        'inspecte': 'En traitement (inspectée)',
        'valide': 'En traitement (validée)',
        'autorise': 'Autorisée'
    };
    const statusColors = {
        'paye': 'info',
        'inspecte': 'warning',
        'valide': 'primary',
        'autorise': 'success'
    };
    const statusLabel = statusLabels[infra.statut] || infra.statut;
    const statusColor = statusColors[infra.statut] || 'secondary';

    const popupContent = `
        <div style="min-width: 280px;">
            <h6 class="mb-3 text-${statusColor}">
                <i class="fas fa-check-circle"></i> ${statusLabel}
            </h6>
            <table class="table table-sm table-borderless mb-2">
                <tr>
                    <td class="text-muted" style="width: 40%;"><i class="fas fa-file-alt"></i> Dossier:</td>
                    <td><strong>${infra.numero}</strong></td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="fas fa-building"></i> Entreprise:</td>
                    <td><strong>${infra.nom_demandeur}</strong></td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="fas fa-industry"></i> Type:</td>
                    <td>${getTypeLabel(infra.type_infrastructure)}</td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="fas fa-map-marker-alt"></i> Localisation:</td>
                    <td>${infra.ville}<br><small class="text-muted">${infra.region || ''}</small></td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="fas fa-crosshairs"></i> Coordonnées:</td>
                    <td><small>${infra.latitude}, ${infra.longitude}</small></td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="fas fa-calendar"></i> Autorisé le:</td>
                    <td><small>${new Date(infra.date_creation).toLocaleDateString('fr-FR')}</small></td>
                </tr>
            </table>
            <div class="d-grid">
                <a href="https://www.google.com/maps?q=${infra.latitude},${infra.longitude}"
                   target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-external-link-alt"></i> Voir sur Google Maps
                </a>
            </div>
        </div>
    `;

    marker.bindPopup(popupContent);
    markers.addLayer(marker);
});

map.addLayer(markers);

if (infrastructures.length > 0) {
    map.fitBounds(markers.getBounds(), { padding: [50, 50] });
}

function getTypeLabel(type) {
    const types = {
        'station_service': 'Station-service',
        'point_consommateur': 'Point consommateur',
        'depot_gpl': 'Dépôt GPL',
        'centre_emplisseur': 'Centre emplisseur'
    };
    return types[type] || type;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

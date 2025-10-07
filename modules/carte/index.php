<?php
// Carte interactive des infrastructures - SGDI
require_once '../../includes/auth.php';
require_once '../../includes/map_functions.php';
require_once '../dossiers/functions.php';

requireLogin();

$page_title = 'Carte des infrastructures';

// Filtres
$filters = [
    'type_infrastructure' => sanitize($_GET['type'] ?? ''),
    'statut' => sanitize($_GET['statut'] ?? ''),
    'region' => sanitize($_GET['region'] ?? '')
];

// Récupérer toutes les infrastructures avec coordonnées
$infrastructures = getAllInfrastructuresForMap($filters);

// Récupérer les régions pour le filtre
$sql = "SELECT DISTINCT region FROM dossiers WHERE region IS NOT NULL AND region != '' ORDER BY region";
$stmt = $pdo->query($sql);
$regions = $stmt->fetchAll(PDO::FETCH_COLUMN);

require_once '../../includes/header.php';
?>

<style>
#map {
    height: 600px;
    width: 100%;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Style des tooltips au survol */
.leaflet-tooltip {
    background: rgba(0, 0, 0, 0.85) !important;
    color: white !important;
    border: none !important;
    border-radius: 6px !important;
    padding: 8px 12px !important;
    font-size: 13px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
}

.leaflet-tooltip-top:before {
    border-top-color: rgba(0, 0, 0, 0.85) !important;
}

/* Style des popups */
.leaflet-popup-content-wrapper {
    border-radius: 8px !important;
    box-shadow: 0 6px 20px rgba(0,0,0,0.2) !important;
}

.leaflet-popup-content {
    margin: 15px !important;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
}

.custom-popup .leaflet-popup-content-wrapper {
    background: #ffffff !important;
}

/* Animation des marqueurs */
.custom-marker {
    animation: markerBounce 0.5s ease-out;
}

@keyframes markerBounce {
    0% { transform: translateY(-20px); opacity: 0; }
    50% { transform: translateY(5px); }
    100% { transform: translateY(0); opacity: 1; }
}

.map-filters {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.legend {
    background: white;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.legend-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}

.legend-marker {
    width: 24px;
    height: 24px;
    margin-right: 10px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: white;
}

.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}
</style>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3">
                <i class="fas fa-map-marked-alt"></i> Carte des infrastructures pétrolières
            </h1>
            <p class="text-muted">Visualisation géographique des infrastructures du Cameroun</p>
        </div>
    </div>

    <!-- Statistiques rapides -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <h2 class="mb-0"><?php echo count($infrastructures); ?></h2>
                <small>Infrastructures géolocalisées</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <h2 class="mb-0">
                    <?php echo count(array_filter($infrastructures, fn($i) => $i['statut'] === 'autorise')); ?>
                </h2>
                <small>Autorisées</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);">
                <h2 class="mb-0">
                    <?php echo count(array_filter($infrastructures, fn($i) => $i['type_infrastructure'] === 'station_service')); ?>
                </h2>
                <small>Stations-service</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h2 class="mb-0">
                    <?php echo count(array_filter($infrastructures, fn($i) => $i['type_infrastructure'] === 'depot_gpl')); ?>
                </h2>
                <small>Dépôts GPL</small>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="map-filters">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
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

            <div class="col-md-3">
                <label class="form-label">Statut</label>
                <select class="form-select" name="statut" onchange="this.form.submit()">
                    <option value="">Tous les statuts</option>
                    <option value="autorise" <?php echo $filters['statut'] === 'autorise' ? 'selected' : ''; ?>>Autorisé</option>
                    <option value="decide" <?php echo $filters['statut'] === 'decide' ? 'selected' : ''; ?>>Décidé</option>
                    <option value="valide" <?php echo $filters['statut'] === 'valide' ? 'selected' : ''; ?>>Validé</option>
                    <option value="inspecte" <?php echo $filters['statut'] === 'inspecte' ? 'selected' : ''; ?>>Inspecté</option>
                </select>
            </div>

            <div class="col-md-3">
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

            <div class="col-md-3 d-flex align-items-end">
                <?php if (array_filter($filters)): ?>
                <a href="?" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i> Réinitialiser
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="row">
        <div class="col-md-9">
            <!-- Carte -->
            <div id="map"></div>
        </div>

        <div class="col-md-3">
            <!-- Légende -->
            <div class="legend">
                <h6 class="mb-3"><i class="fas fa-list"></i> Légende</h6>

                <strong class="d-block mb-2">Types d'infrastructure:</strong>
                <div class="legend-item">
                    <div class="legend-marker" style="background: #ff6b6b;">
                        <i class="fas fa-gas-pump"></i>
                    </div>
                    <span>Station-service</span>
                </div>
                <div class="legend-item">
                    <div class="legend-marker" style="background: #4ecdc4;">
                        <i class="fas fa-industry"></i>
                    </div>
                    <span>Point consommateur</span>
                </div>
                <div class="legend-item">
                    <div class="legend-marker" style="background: #f7b731;">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <span>Dépôt GPL</span>
                </div>
                <div class="legend-item">
                    <div class="legend-marker" style="background: #5f27cd;">
                        <i class="fas fa-fire"></i>
                    </div>
                    <span>Centre emplisseur</span>
                </div>

                <hr class="my-3">

                <strong class="d-block mb-2">Statuts:</strong>
                <div class="legend-item">
                    <div style="width: 12px; height: 12px; background: #28a745; border-radius: 50%; margin-right: 10px;"></div>
                    <span>Autorisé</span>
                </div>
                <div class="legend-item">
                    <div style="width: 12px; height: 12px; background: #007bff; border-radius: 50%; margin-right: 10px;"></div>
                    <span>Décidé</span>
                </div>
                <div class="legend-item">
                    <div style="width: 12px; height: 12px; background: #ffc107; border-radius: 50%; margin-right: 10px;"></div>
                    <span>En cours</span>
                </div>
            </div>

            <!-- Liste des infrastructures -->
            <div class="legend mt-3">
                <h6 class="mb-3"><i class="fas fa-th-list"></i> Liste (<?php echo count($infrastructures); ?>)</h6>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($infrastructures as $infra): ?>
                    <div class="border-bottom pb-2 mb-2">
                        <small class="text-muted"><?php echo sanitize($infra['numero']); ?></small>
                        <br>
                        <strong><?php echo sanitize($infra['nom_demandeur']); ?></strong>
                        <br>
                        <small>
                            <i class="fas fa-map-marker-alt"></i> <?php echo sanitize($infra['ville']); ?>
                        </small>
                        <br>
                        <span class="badge bg-<?php echo getStatutClass($infra['statut']); ?> badge-sm">
                            <?php echo getStatutLabel($infra['statut']); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
// Initialiser la carte centrée sur le Cameroun
const map = L.map('map').setView([7.3697, 12.3547], 6);

// Ajouter le fond de carte OpenStreetMap
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 18
}).addTo(map);

// Créer un groupe de marqueurs avec clustering
const markers = L.markerClusterGroup({
    chunkedLoading: true,
    spiderfyOnMaxZoom: true,
    showCoverageOnHover: false
});

// Données des infrastructures
const infrastructures = <?php echo json_encode($infrastructures); ?>;

// Définir les icônes personnalisées
const iconColors = {
    'station_service': '#ff6b6b',
    'point_consommateur': '#4ecdc4',
    'depot_gpl': '#f7b731',
    'centre_emplisseur': '#5f27cd'
};

const iconNames = {
    'station_service': 'gas-pump',
    'point_consommateur': 'industry',
    'depot_gpl': 'warehouse',
    'centre_emplisseur': 'fire'
};

// Ajouter les marqueurs
infrastructures.forEach(function(infra) {
    const color = iconColors[infra.type_infrastructure] || '#6c757d';
    const iconName = iconNames[infra.type_infrastructure] || 'map-marker';

    // Créer une icône personnalisée avec Font Awesome
    const icon = L.divIcon({
        html: `<div style="background: ${color}; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">
                <i class="fas fa-${iconName}" style="font-size: 14px;"></i>
               </div>`,
        className: 'custom-marker',
        iconSize: [32, 32],
        iconAnchor: [16, 16]
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
    const popupContent = `
        <div style="min-width: 280px;">
            <div style="border-bottom: 2px solid ${color}; padding-bottom: 8px; margin-bottom: 10px;">
                <h6 class="mb-1"><strong>${infra.numero}</strong></h6>
                <small class="text-muted">Dossier d'infrastructure</small>
            </div>

            <table style="width: 100%; font-size: 13px; margin-bottom: 10px;">
                <tr>
                    <td style="padding: 4px 0;"><i class="fas fa-building" style="width: 20px;"></i></td>
                    <td><strong>${infra.nom_demandeur}</strong></td>
                </tr>
                <tr>
                    <td style="padding: 4px 0;"><i class="fas fa-industry" style="width: 20px;"></i></td>
                    <td>${getTypeLabel(infra.type_infrastructure, infra.sous_type)}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0;"><i class="fas fa-map-marker-alt" style="width: 20px;"></i></td>
                    <td>${infra.ville}${infra.region ? ', ' + infra.region : ''}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0;"><i class="fas fa-crosshairs" style="width: 20px;"></i></td>
                    <td><code style="font-size: 11px;">${infra.latitude.toFixed(6)}, ${infra.longitude.toFixed(6)}</code></td>
                </tr>
                <tr>
                    <td style="padding: 4px 0;"><i class="fas fa-calendar" style="width: 20px;"></i></td>
                    <td><small>Créé le ${new Date(infra.date_creation).toLocaleDateString('fr-FR')}</small></td>
                </tr>
            </table>

            <div style="margin-bottom: 10px;">
                <span class="badge bg-${getStatutClass(infra.statut)}" style="font-size: 12px;">
                    ${getStatutLabel(infra.statut)}
                </span>
            </div>

            <div class="d-grid gap-2">
                <a href="<?php echo url('modules/dossiers/view.php?id='); ?>${infra.id}"
                   class="btn btn-sm btn-primary" target="_blank">
                    <i class="fas fa-eye"></i> Voir le dossier complet
                </a>
                <a href="https://www.google.com/maps?q=${infra.latitude},${infra.longitude}"
                   class="btn btn-sm btn-outline-secondary" target="_blank">
                    <i class="fas fa-external-link-alt"></i> Ouvrir dans Google Maps
                </a>
            </div>
        </div>
    `;

    marker.bindPopup(popupContent, {
        maxWidth: 300,
        className: 'custom-popup'
    });
    markers.addLayer(marker);
});

// Ajouter le groupe de marqueurs à la carte
map.addLayer(markers);

// Ajuster la vue pour inclure tous les marqueurs
if (infrastructures.length > 0) {
    map.fitBounds(markers.getBounds(), { padding: [50, 50] });
}

// Fonctions helper pour le JavaScript
function getTypeLabel(type, sousType) {
    const types = {
        'station_service': 'Station-service',
        'point_consommateur': 'Point consommateur',
        'depot_gpl': 'Dépôt GPL',
        'centre_emplisseur': 'Centre emplisseur'
    };
    return types[type] || type;
}

function getStatutClass(statut) {
    const classes = {
        'autorise': 'success',
        'decide': 'primary',
        'valide': 'info',
        'inspecte': 'warning',
        'en_cours': 'secondary'
    };
    return classes[statut] || 'secondary';
}

function getStatutLabel(statut) {
    const labels = {
        'autorise': 'Autorisé',
        'decide': 'Décidé',
        'valide': 'Validé',
        'inspecte': 'Inspecté',
        'en_cours': 'En cours'
    };
    return labels[statut] || statut;
}
</script>

<?php require_once '../../includes/footer.php'; ?>

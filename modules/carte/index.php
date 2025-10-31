<?php
// Carte interactive des infrastructures - SGDI
require_once '../../includes/auth.php';
require_once '../../includes/map_functions.php';
require_once '../../includes/contraintes_distance_functions.php';
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

// Récupérer les POI (Points d'intérêt stratégiques)
$pois = getAllPOIsForMap();

// Récupérer les catégories de POI
$categories = getCategoriesPOI();

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
        <div class="col-auto">
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
            <a href="<?php echo url('modules/poi/index.php'); ?>" class="btn btn-outline-primary">
                <i class="fas fa-map-pin"></i> Gérer les POI
            </a>
            <?php endif; ?>
            <button class="btn btn-outline-info" id="togglePOI">
                <i class="fas fa-landmark"></i> Afficher les POI (<?php echo count($pois); ?>)
            </button>
            <button class="btn btn-outline-warning" id="toggleZones">
                <i class="fas fa-circle-notch"></i> Afficher les zones de contrainte
            </button>
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
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="white">
                            <path d="M18,10a1,1,0,0,0-1,1v3a1,1,0,0,1-1,1,1,1,0,0,1-1-1V6a2,2,0,0,0-2-2H6A2,2,0,0,0,4,6v9a2,2,0,0,0,2,2v4h2V17h4v4h2V17a2,2,0,0,0,2-2V13h1a3,3,0,0,0,3-3V11A1,1,0,0,0,18,10ZM12,10H6V6h6Z"/>
                        </svg>
                    </div>
                    <span>Station-service</span>
                </div>
                <div class="legend-item">
                    <div class="legend-marker" style="background: #4ecdc4;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="white">
                            <path d="M17,10h3v11h-3ZM13,2h-2L8.5,7l-3-4L4,5v14h5V9h4V19h4Z"/>
                        </svg>
                    </div>
                    <span>Point consommateur</span>
                </div>
                <div class="legend-item">
                    <div class="legend-marker" style="background: #f7b731;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="white">
                            <path d="M18,15H16V17H8V15H6v5h12ZM20,3H4A2,2,0,0,0,2,5V15a2,2,0,0,0,2,2h4V14h8v3h4a2,2,0,0,0,2-2V5A2,2,0,0,0,20,3ZM10,10H8V5h2Zm6,0H14V5h2Z"/>
                        </svg>
                    </div>
                    <span>Dépôt GPL</span>
                </div>
                <div class="legend-item">
                    <div class="legend-marker" style="background: #5f27cd;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="white">
                            <path d="M16,6V4a2,2,0,0,0-2-2H10A2,2,0,0,0,8,4V6a2,2,0,0,0-2,2V20a2,2,0,0,0,2,2h8a2,2,0,0,0,2-2V8A2,2,0,0,0,16,6ZM10,4h4V6H10Zm0,14V10h4v8Z"/>
                        </svg>
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

// Créer des icônes SVG réalistes selon le type d'infrastructure
function createCustomIcon(type, color) {
    let iconSvg = '';

    switch(type) {
        case 'station_service':
            // Icône pompe à essence réaliste (style Google Maps)
            iconSvg = `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                    <path d="M18,10a1,1,0,0,0-1,1v3a1,1,0,0,1-1,1,1,1,0,0,1-1-1V6a2,2,0,0,0-2-2H6A2,2,0,0,0,4,6v9a2,2,0,0,0,2,2v4h2V17h4v4h2V17a2,2,0,0,0,2-2V13h1a3,3,0,0,0,3-3V11A1,1,0,0,0,18,10ZM12,10H6V6h6Z"/>
                </svg>`;
            break;
        case 'point_consommateur':
            // Icône usine/industrie
            iconSvg = `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                    <path d="M17,10h3v11h-3ZM13,2h-2L8.5,7l-3-4L4,5v14h5V9h4V19h4Z"/>
                </svg>`;
            break;
        case 'depot_gpl':
            // Icône réservoir/entrepôt
            iconSvg = `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                    <path d="M18,15H16V17H8V15H6v5h12ZM20,3H4A2,2,0,0,0,2,5V15a2,2,0,0,0,2,2h4V14h8v3h4a2,2,0,0,0,2-2V5A2,2,0,0,0,20,3ZM10,10H8V5h2Zm6,0H14V5h2Z"/>
                </svg>`;
            break;
        case 'centre_emplisseur':
            // Icône bouteille de gaz
            iconSvg = `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                    <path d="M16,6V4a2,2,0,0,0-2-2H10A2,2,0,0,0,8,4V6a2,2,0,0,0-2,2V20a2,2,0,0,0,2,2h8a2,2,0,0,0,2-2V8A2,2,0,0,0,16,6ZM10,4h4V6H10Zm0,14V10h4v8Z"/>
                </svg>`;
            break;
        default:
            // Icône générique marker
            iconSvg = `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                    <path d="M12,2A7,7,0,0,0,5,9c0,5.25,7,13,7,13s7-7.75,7-13A7,7,0,0,0,12,2Zm0,9.5A2.5,2.5,0,1,1,14.5,9,2.5,2.5,0,0,1,12,11.5Z"/>
                </svg>`;
    }

    return L.divIcon({
        html: `<div style="background: ${color}; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; border: 3px solid white; box-shadow: 0 3px 8px rgba(0,0,0,0.4); position: relative;">
                ${iconSvg}
                <div style="position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 6px solid transparent; border-right: 6px solid transparent; border-top: 8px solid ${color}; filter: drop-shadow(0 2px 3px rgba(0,0,0,0.3));"></div>
               </div>`,
        className: 'custom-marker',
        iconSize: [36, 44],
        iconAnchor: [18, 44],
        popupAnchor: [0, -44]
    });
}

// Ajouter les marqueurs
infrastructures.forEach(function(infra) {
    const color = iconColors[infra.type_infrastructure] || '#6c757d';
    const icon = createCustomIcon(infra.type_infrastructure, color);

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

// ========== Gestion des POI et zones de contrainte ==========

// Données des POI
const pois = <?php echo json_encode($pois); ?>;

// Groupes de layers pour les POI et zones
const poiMarkersGroup = L.layerGroup();
const zonesGroup = L.layerGroup();

let poiVisible = false;
let zonesVisible = false;

// Ajouter les POI à la carte
pois.forEach(function(poi) {
    const color = poi.couleur_marqueur || '#dc3545';
    const icon = poi.icone || 'landmark';

    // Créer un marqueur pour le POI
    const poiMarker = L.marker([poi.latitude, poi.longitude], {
        icon: L.divIcon({
            html: `<div style="background: ${color}; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">
                    <i class="fas fa-${icon}" style="font-size: 12px;"></i>
                   </div>`,
            className: 'poi-marker',
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        })
    });

    // Tooltip
    const tooltipContent = `<strong>${poi.nom}</strong><br><small>${poi.categorie_nom}</small>`;
    poiMarker.bindTooltip(tooltipContent, {
        permanent: false,
        direction: 'top',
        offset: [0, -15]
    });

    // Popup détaillé
    const popupContent = `
        <div style="min-width: 220px;">
            <div style="border-bottom: 2px solid ${color}; padding-bottom: 8px; margin-bottom: 10px;">
                <h6 class="mb-1"><strong>${poi.nom}</strong></h6>
                <small class="text-muted">${poi.categorie_nom}</small>
            </div>
            <table style="width: 100%; font-size: 13px; margin-bottom: 10px;">
                <tr>
                    <td style="padding: 4px 0;"><i class="fas fa-map-marker-alt" style="width: 20px;"></i></td>
                    <td>${poi.ville || 'Non spécifié'}${poi.region ? ', ' + poi.region : ''}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0;"><i class="fas fa-ruler" style="width: 20px;"></i></td>
                    <td>
                        <strong style="color: ${color};">${poi.distance_min_metres}m</strong>
                        <small class="text-muted">(${poi.distance_min_rural_metres}m rural)</small>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px 0;"><i class="fas fa-crosshairs" style="width: 20px;"></i></td>
                    <td><code style="font-size: 11px;">${poi.latitude.toFixed(6)}, ${poi.longitude.toFixed(6)}</code></td>
                </tr>
            </table>
            ${poi.description ? '<p class="small mb-2">' + poi.description + '</p>' : ''}
            <div class="d-grid gap-2">
                <a href="https://www.google.com/maps?q=${poi.latitude},${poi.longitude}"
                   class="btn btn-sm btn-outline-secondary" target="_blank">
                    <i class="fas fa-external-link-alt"></i> Google Maps
                </a>
            </div>
        </div>
    `;

    poiMarker.bindPopup(popupContent, {
        maxWidth: 280,
        className: 'custom-popup'
    });

    poiMarkersGroup.addLayer(poiMarker);

    // Créer les cercles de contrainte (pour le groupe zones)
    const radiusNormal = poi.distance_min_metres;
    const circle = L.circle([poi.latitude, poi.longitude], {
        radius: radiusNormal,
        color: color,
        fillColor: color,
        fillOpacity: 0.05,
        opacity: 0.3,
        weight: 2
    });

    circle.bindTooltip(`Zone ${poi.categorie_nom}: ${radiusNormal}m`, {
        permanent: false
    });

    zonesGroup.addLayer(circle);
});

// Ajouter les zones de contrainte autour des stations-service
infrastructures.forEach(function(infra) {
    if (infra.type_infrastructure === 'station_service') {
        // Zone de 500m autour de chaque station
        const circle = L.circle([infra.latitude, infra.longitude], {
            radius: 500,
            color: '#ff6b6b',
            fillColor: '#ff6b6b',
            fillOpacity: 0.15,  // Augmenté de 0.05 à 0.15 (3x plus visible)
            opacity: 0.5,       // Augmenté de 0.3 à 0.5 pour la bordure
            weight: 2,
            dashArray: '5, 10'
        });

        circle.bindTooltip(`Zone station: 500m<br><small>${infra.nom_demandeur}</small>`, {
            permanent: false
        });

        zonesGroup.addLayer(circle);
    }
});

// Boutons de contrôle
document.getElementById('togglePOI').addEventListener('click', function() {
    if (poiVisible) {
        map.removeLayer(poiMarkersGroup);
        this.innerHTML = '<i class="fas fa-landmark"></i> Afficher les POI (<?php echo count($pois); ?>)';
        this.classList.remove('btn-info');
        this.classList.add('btn-outline-info');
        poiVisible = false;
    } else {
        map.addLayer(poiMarkersGroup);
        this.innerHTML = '<i class="fas fa-eye-slash"></i> Masquer les POI (<?php echo count($pois); ?>)';
        this.classList.remove('btn-outline-info');
        this.classList.add('btn-info');
        poiVisible = true;
    }
});

document.getElementById('toggleZones').addEventListener('click', function() {
    if (zonesVisible) {
        map.removeLayer(zonesGroup);
        this.innerHTML = '<i class="fas fa-circle-notch"></i> Afficher les zones de contrainte';
        this.classList.remove('btn-warning');
        this.classList.add('btn-outline-warning');
        zonesVisible = false;
    } else {
        map.addLayer(zonesGroup);
        this.innerHTML = '<i class="fas fa-eye-slash"></i> Masquer les zones';
        this.classList.remove('btn-outline-warning');
        this.classList.add('btn-warning');
        zonesVisible = true;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>

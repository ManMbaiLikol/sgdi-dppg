<?php
/**
 * Éditeur GPS - Interface pour modifier les coordonnées d'une station
 */

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

requireLogin();

if (!hasAnyRole(['admin', 'chef_service'])) {
    redirect(url('dashboard.php'), 'Accès non autorisé', 'error');
}

$dossier_id = intval($_GET['id'] ?? 0);

if (!$dossier_id) {
    redirect(url('modules/admin_gps/index.php'), 'Dossier non spécifié', 'error');
}

// Récupérer le dossier
$stmt = $pdo->prepare("
    SELECT *
    FROM dossiers
    WHERE id = ?
");
$stmt->execute([$dossier_id]);
$dossier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dossier) {
    redirect(url('modules/admin_gps/index.php'), 'Dossier introuvable', 'error');
}

// Parser les coordonnées actuelles
$current_coords = null;
if ($dossier['coordonnees_gps']) {
    $coords = explode(',', $dossier['coordonnees_gps']);
    if (count($coords) == 2) {
        $current_coords = [
            'latitude' => floatval(trim($coords[0])),
            'longitude' => floatval(trim($coords[1]))
        ];
    }
}

// Traitement du formulaire
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        $source_gps = cleanInput($_POST['source_gps'] ?? 'Saisie manuelle admin');

        if (empty($latitude) || empty($longitude)) {
            $errors[] = 'Les coordonnées GPS sont obligatoires';
        } elseif (!is_numeric($latitude) || !is_numeric($longitude)) {
            $errors[] = 'Les coordonnées doivent être numériques';
        } elseif ($latitude < -90 || $latitude > 90) {
            $errors[] = 'La latitude doit être entre -90 et 90';
        } elseif ($longitude < -180 || $longitude > 180) {
            $errors[] = 'La longitude doit être entre -180 et 180';
        } else {
            try {
                $gps_formatted = $latitude . ',' . $longitude;

                $stmt = $pdo->prepare("
                    UPDATE dossiers
                    SET coordonnees_gps = ?,
                        source_gps = ?,
                        score_matching_osm = NULL
                    WHERE id = ?
                ");

                if ($stmt->execute([$gps_formatted, $source_gps, $dossier_id])) {
                    // Log de l'action
                    addHistoriqueDossier(
                        $dossier_id,
                        $_SESSION['user_id'],
                        'modification_gps',
                        'Position GPS modifiée par admin: ' . $gps_formatted,
                        null,
                        null
                    );

                    $success = 'Position GPS enregistrée avec succès';

                    // Recharger le dossier
                    $stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id = ?");
                    $stmt->execute([$dossier_id]);
                    $dossier = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Mettre à jour current_coords
                    $current_coords = [
                        'latitude' => floatval($latitude),
                        'longitude' => floatval($longitude)
                    ];
                } else {
                    $errors[] = 'Erreur lors de la sauvegarde';
                }
            } catch (Exception $e) {
                $errors[] = 'Erreur: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Édition GPS - ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<!-- Mobile Responsive pour cartes -->
<link rel="stylesheet" href="../../assets/css/map-mobile-responsive.css">

<style>
#map {
    height: 600px;
    width: 100%;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.info-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
}

.info-card h5 {
    margin-bottom: 1rem;
    border-bottom: 2px solid rgba(255,255,255,0.3);
    padding-bottom: 0.5rem;
}

.info-item {
    display: flex;
    margin-bottom: 0.75rem;
}

.info-item .label {
    font-weight: 600;
    min-width: 120px;
    opacity: 0.9;
}

.info-item .value {
    flex: 1;
}

.coord-display {
    background: rgba(255,255,255,0.2);
    padding: 0.75rem;
    border-radius: 5px;
    font-family: 'Courier New', monospace;
    font-size: 1.1rem;
}

.instructions {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 1rem;
    border-radius: 5px;
    margin-bottom: 1.5rem;
}

.map-controls {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 1000;
    background: white;
    padding: 10px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
}

.osm-suggestion-item {
    padding: 0.75rem;
    border-bottom: 1px solid #e9ecef;
    cursor: pointer;
    transition: background-color 0.2s;
}

.osm-suggestion-item:hover {
    background-color: #f8f9fa;
}

.osm-suggestion-item:last-child {
    border-bottom: none;
}

.osm-suggestion-item .name {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.25rem;
}

.osm-suggestion-item .details {
    font-size: 0.85rem;
    color: #6c757d;
}

.osm-suggestion-item .distance {
    float: right;
    color: #007bff;
    font-weight: 500;
}

#osmSuggestions {
    max-height: 500px;
    overflow-y: auto;
}

.loading-spinner {
    text-align: center;
    padding: 2rem;
}

.loading-spinner i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<div class="container-fluid mt-4">
    <!-- Navigation -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('dashboard.php') ?>">Tableau de bord</a></li>
            <li class="breadcrumb-item"><a href="<?= url('modules/admin_gps/index.php') ?>">Gestion GPS</a></li>
            <li class="breadcrumb-item active">Édition</li>
        </ol>
    </nav>

    <!-- Titre -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-map-marker-alt"></i> Édition Position GPS
            </h1>
            <p class="text-muted mb-0">Dossier: <strong><?= htmlspecialchars($dossier['numero']) ?></strong></p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <strong><i class="fas fa-exclamation-triangle"></i> Erreurs:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Colonne gauche: Informations -->
        <div class="col-lg-4">
            <!-- Carte d'info -->
            <div class="info-card">
                <h5><i class="fas fa-info-circle"></i> Informations du Dossier</h5>
                <div class="info-item">
                    <span class="label">Numéro:</span>
                    <span class="value"><?= htmlspecialchars($dossier['numero']) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Opérateur:</span>
                    <span class="value"><?= htmlspecialchars($dossier['nom_demandeur']) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Type:</span>
                    <span class="value">
                        <?= getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']) ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">Région:</span>
                    <span class="value"><?= htmlspecialchars($dossier['region'] ?: 'Non spécifié') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Ville:</span>
                    <span class="value"><?= htmlspecialchars($dossier['ville'] ?: 'Non spécifié') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Quartier:</span>
                    <span class="value"><?= htmlspecialchars($dossier['quartier'] ?: 'Non spécifié') ?></span>
                </div>

                <?php if ($current_coords): ?>
                    <hr style="border-color: rgba(255,255,255,0.3);">
                    <div class="info-item">
                        <span class="label">GPS actuel:</span>
                    </div>
                    <div class="coord-display">
                        Lat: <?= $current_coords['latitude'] ?><br>
                        Lng: <?= $current_coords['longitude'] ?>
                    </div>
                    <?php if ($dossier['source_gps']): ?>
                        <small class="d-block mt-2" style="opacity: 0.8;">
                            Source: <?= htmlspecialchars($dossier['source_gps']) ?>
                        </small>
                    <?php endif; ?>
                <?php else: ?>
                    <hr style="border-color: rgba(255,255,255,0.3);">
                    <div class="alert alert-warning mb-0" style="background: rgba(255,255,255,0.2); border: none; color: white;">
                        <i class="fas fa-exclamation-triangle"></i>
                        Aucune coordonnée GPS définie
                    </div>
                <?php endif; ?>
            </div>

            <!-- Formulaire -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-crosshairs"></i> Coordonnées GPS</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="gpsForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                        <div class="mb-3">
                            <label for="latitude" class="form-label">Latitude *</label>
                            <input type="text" class="form-control" id="latitude" name="latitude"
                                   value="<?= $current_coords ? $current_coords['latitude'] : '' ?>"
                                   placeholder="Ex: 3.8667"
                                   pattern="-?\d+\.?\d*"
                                   required>
                            <small class="form-text text-muted">Entre -90 et 90</small>
                        </div>

                        <div class="mb-3">
                            <label for="longitude" class="form-label">Longitude *</label>
                            <input type="text" class="form-control" id="longitude" name="longitude"
                                   value="<?= $current_coords ? $current_coords['longitude'] : '' ?>"
                                   placeholder="Ex: 11.5167"
                                   pattern="-?\d+\.?\d*"
                                   required>
                            <small class="form-text text-muted">Entre -180 et 180</small>
                        </div>

                        <div class="mb-3">
                            <label for="source_gps" class="form-label">Source</label>
                            <select class="form-select" name="source_gps" id="source_gps">
                                <option value="Saisie manuelle admin">Saisie manuelle admin</option>
                                <option value="OSM (sélection manuelle)">OSM (sélection manuelle)</option>
                                <option value="Google Maps">Google Maps</option>
                                <option value="Visite terrain">Visite terrain</option>
                                <option value="GPS mobile">GPS mobile</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Enregistrer la Position
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="resetToOriginal()">
                                <i class="fas fa-undo"></i> Réinitialiser
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Raccourcis -->
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-link"></i> Liens rapides</h6>
                    <?php if ($current_coords): ?>
                        <a href="https://www.google.com/maps?q=<?= $current_coords['latitude'] ?>,<?= $current_coords['longitude'] ?>"
                           target="_blank"
                           class="btn btn-sm btn-outline-primary w-100 mb-2">
                            <i class="fab fa-google"></i> Voir sur Google Maps
                        </a>
                        <a href="https://www.openstreetmap.org/?mlat=<?= $current_coords['latitude'] ?>&mlon=<?= $current_coords['longitude'] ?>&zoom=15"
                           target="_blank"
                           class="btn btn-sm btn-outline-info w-100 mb-2">
                            <i class="fas fa-map"></i> Voir sur OpenStreetMap
                        </a>
                    <?php endif; ?>
                    <a href="../dossiers/view.php?id=<?= $dossier_id ?>"
                       class="btn btn-sm btn-outline-secondary w-100">
                        <i class="fas fa-folder-open"></i> Voir le dossier complet
                    </a>
                </div>
            </div>
        </div>

        <!-- Colonne droite: Carte -->
        <div class="col-lg-8">
            <div class="instructions">
                <strong><i class="fas fa-lightbulb"></i> Instructions:</strong>
                <ul class="mb-0 mt-2">
                    <li><strong>Cliquez sur la carte</strong> pour placer le marqueur à la position souhaitée</li>
                    <li>Les coordonnées GPS seront <strong>automatiquement remplies</strong> dans le formulaire</li>
                    <li>Vous pouvez aussi <strong>saisir manuellement</strong> les coordonnées dans le formulaire</li>
                    <li>Utilisez la <strong>recherche</strong> ou zoomez pour trouver l'emplacement exact</li>
                    <li>Les <strong>pins jaunes</strong> représentent des suggestions OSM à proximité</li>
                </ul>
            </div>

            <div class="row">
                <div class="col-lg-9">
                    <div class="card">
                        <div class="card-body p-0" style="position: relative;">
                            <div id="map"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-lightbulb"></i> Suggestions OSM
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div id="osmSuggestions">
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                                    <p class="mb-0 small">Cliquez sur la carte pour voir les stations OSM à proximité</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Coordonnées par défaut (centre du Cameroun)
let defaultLat = 7.3697;
let defaultLng = 12.3547;
let defaultZoom = 6;

// Si le dossier a des coordonnées, les utiliser
<?php if ($current_coords): ?>
defaultLat = <?= $current_coords['latitude'] ?>;
defaultLng = <?= $current_coords['longitude'] ?>;
defaultZoom = 15;
<?php elseif ($dossier['ville']): ?>
// Coordonnées approximatives des grandes villes
const villeCoords = {
    'Yaoundé': [3.8667, 11.5167],
    'Douala': [4.0511, 9.7679],
    'Garoua': [9.3014, 13.3964],
    'Bafoussam': [5.4781, 10.4179],
    'Bamenda': [5.9597, 10.1453],
    'Maroua': [10.5910, 14.3163],
    'Ngaoundéré': [7.3167, 13.5833],
    'Bertoua': [4.5767, 13.6843],
    'Ebolowa': [2.9000, 11.1500],
    'Kumba': [4.6333, 9.4500]
};
const ville = '<?= addslashes($dossier['ville']) ?>';
if (villeCoords[ville]) {
    defaultLat = villeCoords[ville][0];
    defaultLng = villeCoords[ville][1];
    defaultZoom = 13;
}
<?php endif; ?>

const originalLat = defaultLat;
const originalLng = defaultLng;

// Initialiser la carte
const map = L.map('map').setView([defaultLat, defaultLng], defaultZoom);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
}).addTo(map);

// Fonction pour créer l'icône du marqueur principal
function createMainIcon() {
    return L.divIcon({
        className: 'custom-main-marker',
        html: `
            <div style="position: relative; width: 40px; height: 50px;">
                <svg width="40" height="50" viewBox="0 0 40 50" xmlns="http://www.w3.org/2000/svg">
                    <ellipse cx="20" cy="47" rx="9" ry="3" fill="rgba(0,0,0,0.25)"/>
                    <path d="M20 0C12 0 5.5 6.5 5.5 14.5c0 10 14.5 31 14.5 31S34.5 24.5 34.5 14.5C34.5 6.5 28 0 20 0z"
                          fill="#dc3545" stroke="white" stroke-width="2"/>
                    <circle cx="20" cy="14.5" r="7" fill="white"/>
                </svg>
                <i class="fas fa-map-marker-alt" style="position: absolute; top: 8px; left: 50%; transform: translateX(-50%); font-size: 13px; color: #dc3545;"></i>
            </div>
        `,
        iconSize: [40, 50],
        iconAnchor: [20, 47],
        popupAnchor: [0, -47]
    });
}

// Marqueur principal
let marker = null;
<?php if ($current_coords): ?>
marker = L.marker([defaultLat, defaultLng], {
    draggable: true,
    icon: createMainIcon()
}).addTo(map);

marker.bindPopup('<strong>Position actuelle</strong><br>Déplacez ou cliquez sur la carte').openPopup();

// Événement de drag
marker.on('dragend', function(e) {
    const latlng = marker.getLatLng();
    updateCoordinates(latlng.lat, latlng.lng);
});
<?php endif; ?>

// Cercle de contrainte
let constraintCircle = null;
<?php if ($current_coords): ?>
constraintCircle = L.circle([defaultLat, defaultLng], {
    color: '#dc3545',
    fillColor: '#dc3545',
    fillOpacity: 0.1,
    radius: 500,
    weight: 2
}).addTo(map);
<?php endif; ?>

// Stocker les marqueurs de suggestions
let suggestionMarkers = [];

// Clic sur la carte
map.on('click', function(e) {
    const lat = e.latlng.lat;
    const lng = e.latlng.lng;

    updateCoordinates(lat, lng);

    if (marker) {
        marker.setLatLng([lat, lng]);
        if (constraintCircle) {
            constraintCircle.setLatLng([lat, lng]);
        }
    } else {
        marker = L.marker([lat, lng], {
            draggable: true,
            icon: createMainIcon()
        }).addTo(map);

        marker.bindPopup('<strong>Nouvelle position</strong><br>Déplacez ou cliquez ailleurs');

        marker.on('dragend', function(e) {
            const latlng = marker.getLatLng();
            updateCoordinates(latlng.lat, latlng.lng);
        });

        constraintCircle = L.circle([lat, lng], {
            color: '#dc3545',
            fillColor: '#dc3545',
            fillOpacity: 0.1,
            radius: 500,
            weight: 2
        }).addTo(map);
    }

    // Charger les suggestions OSM
    loadOSMSuggestions(lat, lng);
});

// Fonction pour mettre à jour les coordonnées dans le formulaire
function updateCoordinates(lat, lng) {
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;
}

// Fonction pour réinitialiser
function resetToOriginal() {
    document.getElementById('latitude').value = originalLat;
    document.getElementById('longitude').value = originalLng;

    if (marker) {
        marker.setLatLng([originalLat, originalLng]);
        map.setView([originalLat, originalLng], 15);

        if (constraintCircle) {
            constraintCircle.setLatLng([originalLat, originalLng]);
        }
    }
}

// Mise à jour du marqueur depuis les inputs
document.getElementById('latitude').addEventListener('change', updateMarkerFromInputs);
document.getElementById('longitude').addEventListener('change', updateMarkerFromInputs);

function updateMarkerFromInputs() {
    const lat = parseFloat(document.getElementById('latitude').value);
    const lng = parseFloat(document.getElementById('longitude').value);

    if (!isNaN(lat) && !isNaN(lng)) {
        if (marker) {
            marker.setLatLng([lat, lng]);
            if (constraintCircle) {
                constraintCircle.setLatLng([lat, lng]);
            }
        } else {
            marker = L.marker([lat, lng], {
                draggable: true,
                icon: createMainIcon()
            }).addTo(map);

            marker.on('dragend', function(e) {
                const latlng = marker.getLatLng();
                updateCoordinates(latlng.lat, latlng.lng);
            });

            constraintCircle = L.circle([lat, lng], {
                color: '#dc3545',
                fillColor: '#dc3545',
                fillOpacity: 0.1,
                radius: 500,
                weight: 2
            }).addTo(map);
        }
        map.setView([lat, lng], 15);
    }
}

// Fonction pour créer l'icône des suggestions OSM
function createSuggestionIcon() {
    return L.divIcon({
        className: 'custom-suggestion-marker',
        html: `
            <div style="position: relative; width: 32px; height: 40px;">
                <svg width="32" height="40" viewBox="0 0 32 40" xmlns="http://www.w3.org/2000/svg">
                    <ellipse cx="16" cy="37" rx="7" ry="2.5" fill="rgba(0,0,0,0.2)"/>
                    <path d="M16 0C9.5 0 4.5 5 4.5 11c0 8 11.5 25 11.5 25S27.5 19 27.5 11C27.5 5 22.5 0 16 0z"
                          fill="#ffc107" stroke="white" stroke-width="1.5"/>
                    <circle cx="16" cy="11" r="5.5" fill="white"/>
                </svg>
                <i class="fas fa-gas-pump" style="position: absolute; top: 6px; left: 50%; transform: translateX(-50%); font-size: 10px; color: #ffc107;"></i>
            </div>
        `,
        iconSize: [32, 40],
        iconAnchor: [16, 37],
        popupAnchor: [0, -37]
    });
}

// Fonction pour calculer la distance en mètres entre deux points
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371000; // Rayon de la Terre en mètres
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// Fonction pour charger les suggestions OSM
function loadOSMSuggestions(lat, lng) {
    const suggestionsDiv = document.getElementById('osmSuggestions');

    // Afficher le spinner
    suggestionsDiv.innerHTML = `
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-2x text-primary"></i>
            <p class="mt-2 text-muted">Recherche des stations OSM à proximité...</p>
        </div>
    `;

    // Supprimer les anciens marqueurs de suggestions
    suggestionMarkers.forEach(m => map.removeLayer(m));
    suggestionMarkers = [];

    // Rayon de recherche: 2km
    const radius = 2000;

    // Construction de la requête Overpass API
    const query = `
        [out:json][timeout:25];
        (
          node["amenity"="fuel"](around:${radius},${lat},${lng});
        );
        out body;
    `;

    const overpassUrl = 'https://overpass-api.de/api/interpreter';

    fetch(overpassUrl, {
        method: 'POST',
        body: query
    })
    .then(response => response.json())
    .then(data => {
        if (!data.elements || data.elements.length === 0) {
            suggestionsDiv.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-search fa-2x mb-2"></i>
                    <p class="mb-0">Aucune station OSM trouvée dans un rayon de 2km</p>
                </div>
            `;
            return;
        }

        // Calculer les distances et trier
        const stations = data.elements.map(element => {
            const distance = calculateDistance(lat, lng, element.lat, element.lon);
            return {
                id: element.id,
                name: element.tags.name || element.tags.brand || 'Station sans nom',
                operator: element.tags.operator || element.tags.brand || 'Opérateur inconnu',
                lat: element.lat,
                lon: element.lon,
                distance: distance
            };
        }).sort((a, b) => a.distance - b.distance);

        // Afficher les suggestions dans le panneau
        let html = '';
        stations.forEach((station, idx) => {
            const distanceKm = (station.distance / 1000).toFixed(2);
            html += `
                <div class="osm-suggestion-item" onclick="selectOSMSuggestion(${station.lat}, ${station.lon}, '${station.name.replace(/'/g, "\\'")}')">
                    <div class="name">
                        <i class="fas fa-gas-pump text-warning"></i> ${station.name}
                        <span class="distance">${distanceKm} km</span>
                    </div>
                    <div class="details">
                        ${station.operator}
                    </div>
                    <div class="details">
                        <small class="text-muted">${station.lat}, ${station.lon}</small>
                    </div>
                </div>
            `;

            // Ajouter un marqueur sur la carte (max 10 pour ne pas surcharger)
            if (idx < 10) {
                const suggestionMarker = L.marker([station.lat, station.lon], {
                    icon: createSuggestionIcon()
                }).addTo(map);

                suggestionMarker.bindPopup(`
                    <strong>${station.name}</strong><br>
                    ${station.operator}<br>
                    <small>${distanceKm} km</small><br>
                    <button class="btn btn-sm btn-primary mt-2" onclick="selectOSMSuggestion(${station.lat}, ${station.lon}, '${station.name.replace(/'/g, "\\'")}')">
                        Utiliser cette position
                    </button>
                `);

                suggestionMarkers.push(suggestionMarker);
            }
        });

        suggestionsDiv.innerHTML = html;
    })
    .catch(error => {
        console.error('Erreur lors de la récupération des données OSM:', error);
        suggestionsDiv.innerHTML = `
            <div class="alert alert-warning mb-0">
                <i class="fas fa-exclamation-triangle"></i>
                Erreur lors de la récupération des données OSM.
            </div>
        `;
    });
}

// Fonction pour sélectionner une suggestion OSM
function selectOSMSuggestion(lat, lng, name) {
    updateCoordinates(lat, lng);

    if (marker) {
        marker.setLatLng([lat, lng]);
        if (constraintCircle) {
            constraintCircle.setLatLng([lat, lng]);
        }
    } else {
        marker = L.marker([lat, lng], {
            draggable: true,
            icon: createMainIcon()
        }).addTo(map);

        marker.on('dragend', function(e) {
            const latlng = marker.getLatLng();
            updateCoordinates(latlng.lat, latlng.lng);
        });

        constraintCircle = L.circle([lat, lng], {
            color: '#dc3545',
            fillColor: '#dc3545',
            fillOpacity: 0.1,
            radius: 500,
            weight: 2
        }).addTo(map);
    }

    map.setView([lat, lng], 16);
    marker.bindPopup(`<strong>Position sélectionnée</strong><br>${name}`).openPopup();

    // Changer la source GPS
    document.getElementById('source_gps').value = 'OSM (sélection manuelle)';
}

// Charger les suggestions si on a déjà des coordonnées
<?php if ($current_coords): ?>
loadOSMSuggestions(<?= $current_coords['latitude'] ?>, <?= $current_coords['longitude'] ?>);
<?php endif; ?>

console.log('Carte initialisée avec succès');
</script>

<!-- Mobile Responsive pour cartes -->
<script src="../../assets/js/map-mobile-responsive.js"></script>
<?php require_once '../../includes/footer.php'; ?>

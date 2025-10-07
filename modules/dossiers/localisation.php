<?php
// Gestion de la localisation GPS - SGDI
require_once '../../includes/auth.php';
require_once '../../includes/map_functions.php';
require_once 'functions.php';

requireLogin();

$dossier_id = intval($_GET['id'] ?? 0);

if (!$dossier_id) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

$dossier = getDossierById($dossier_id);

if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier introuvable', 'error');
}

// Vérifier les permissions
if (!hasAnyRole(['chef_service', 'admin', 'cadre_dppg'])) {
    redirect(url('dashboard.php'), 'Accès non autorisé', 'error');
}

$errors = [];
$success = '';
$nearby_infrastructures = [];

// Parser les coordonnées existantes
$current_coords = null;
if ($dossier['coordonnees_gps']) {
    $current_coords = parseGPSCoordinates($dossier['coordonnees_gps']);
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        $adresse_precise = sanitize($_POST['adresse_precise'] ?? '');

        // Validation
        if (empty($latitude) || empty($longitude)) {
            $errors[] = 'Les coordonnées GPS sont obligatoires';
        } else {
            $validation_errors = validateGPSCoordinates($latitude, $longitude);
            $errors = array_merge($errors, $validation_errors);
        }

        if (empty($errors)) {
            try {
                // Formater les coordonnées
                $coords_formatted = formatGPSCoordinates($latitude, $longitude, 'decimal');

                // Mettre à jour le dossier
                $sql = "UPDATE dossiers SET coordonnees_gps = ?, adresse_precise = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([$coords_formatted, $adresse_precise, $dossier_id]);

                if ($result) {
                    // Ajouter à l'historique
                    addHistoriqueDossier(
                        $dossier_id,
                        $_SESSION['user_id'],
                        'mise_a_jour_localisation',
                        'Mise à jour des coordonnées GPS: ' . $coords_formatted,
                        null,
                        null
                    );

                    // Rechercher les infrastructures à proximité
                    $nearby_infrastructures = findNearbyInfrastructures($latitude, $longitude, 5, $dossier_id);

                    $success = 'Localisation enregistrée avec succès';

                    // Recharger le dossier
                    $dossier = getDossierById($dossier_id);
                    $current_coords = parseGPSCoordinates($dossier['coordonnees_gps']);
                } else {
                    $errors[] = 'Erreur lors de l\'enregistrement';
                }
            } catch (Exception $e) {
                $errors[] = 'Erreur: ' . $e->getMessage();
            }
        }
    }
}

// Si des coordonnées existent, chercher les infrastructures à proximité
if ($current_coords && empty($nearby_infrastructures)) {
    $nearby_infrastructures = findNearbyInfrastructures(
        $current_coords['latitude'],
        $current_coords['longitude'],
        5,
        $dossier_id
    );
}

$page_title = 'Localisation GPS - Dossier ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<style>
#map {
    height: 500px;
    width: 100%;
    border-radius: 8px;
    cursor: crosshair;
}

.info-box {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.nearby-item {
    border-left: 3px solid #28a745;
    padding-left: 10px;
    margin-bottom: 10px;
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

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3">
                <i class="fas fa-map-marker-alt"></i> Localisation GPS de l'infrastructure
            </h1>
            <p class="text-muted">
                Dossier: <strong><?php echo sanitize($dossier['numero']); ?></strong> -
                <?php echo sanitize($dossier['nom_demandeur']); ?>
            </p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
            <li><?php echo sanitize($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo sanitize($success); ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <!-- Carte interactive -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-map"></i> Carte interactive - Cliquez pour sélectionner la position
                    </h5>
                </div>
                <div class="card-body">
                    <div class="info-box mb-3">
                        <i class="fas fa-info-circle"></i>
                        <strong>Instructions:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Cliquez sur la carte pour placer le marqueur</li>
                            <li>Les coordonnées seront automatiquement remplies</li>
                            <li>Vous pouvez aussi saisir manuellement les coordonnées</li>
                            <li>Survolez le marqueur pour voir les détails</li>
                            <li>Le système vérifie automatiquement les infrastructures à proximité (rayon 5 km)</li>
                        </ul>
                    </div>

                    <div id="map"></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Formulaire de coordonnées -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-crosshairs"></i> Coordonnées GPS
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="coordsForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="mb-3">
                            <label for="latitude" class="form-label">Latitude *</label>
                            <input type="text" class="form-control" id="latitude" name="latitude"
                                   value="<?php echo $current_coords ? $current_coords['latitude'] : ''; ?>"
                                   placeholder="Ex: 3.8667" required>
                            <small class="form-text text-muted">Latitude (Nord)</small>
                        </div>

                        <div class="mb-3">
                            <label for="longitude" class="form-label">Longitude *</label>
                            <input type="text" class="form-control" id="longitude" name="longitude"
                                   value="<?php echo $current_coords ? $current_coords['longitude'] : ''; ?>"
                                   placeholder="Ex: 11.5167" required>
                            <small class="form-text text-muted">Longitude (Est)</small>
                        </div>

                        <div class="mb-3">
                            <label for="adresse_precise" class="form-label">Adresse précise</label>
                            <textarea class="form-control" id="adresse_precise" name="adresse_precise" rows="3"
                                      placeholder="Ex: Face au marché central, après la station Total"><?php echo sanitize($dossier['adresse_precise'] ?? ''); ?></textarea>
                        </div>

                        <?php if ($current_coords): ?>
                        <div class="alert alert-info">
                            <strong>Coordonnées actuelles:</strong><br>
                            <?php echo formatGPSCoordinates($current_coords['latitude'], $current_coords['longitude'], 'decimal'); ?>
                            <br>
                            <small>
                                <a href="<?php echo getGoogleMapsLink($current_coords['latitude'], $current_coords['longitude']); ?>"
                                   target="_blank" class="text-decoration-none">
                                    <i class="fas fa-external-link-alt"></i> Voir sur Google Maps
                                </a>
                            </small>
                        </div>
                        <?php endif; ?>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer la localisation
                            </button>
                            <a href="<?php echo url('modules/carte/index.php'); ?>"
                               class="btn btn-success">
                                <i class="fas fa-map-marked-alt"></i> Carte des infrastructures
                            </a>
                            <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>"
                               class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour au dossier
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Infrastructures à proximité -->
            <?php if (!empty($nearby_infrastructures)): ?>
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-exclamation-triangle"></i>
                        Infrastructures à proximité (<?php echo count($nearby_infrastructures); ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        Les infrastructures suivantes sont situées dans un rayon de 5 km:
                    </p>

                    <?php foreach ($nearby_infrastructures as $nearby): ?>
                    <div class="nearby-item">
                        <strong><?php echo sanitize($nearby['nom_demandeur']); ?></strong>
                        <br>
                        <small class="text-muted"><?php echo sanitize($nearby['numero']); ?></small>
                        <br>
                        <small>
                            <i class="fas fa-industry"></i>
                            <?php echo getTypeLabel($nearby['type_infrastructure'], $nearby['sous_type']); ?>
                        </small>
                        <br>
                        <small>
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo sanitize($nearby['ville']); ?>
                            - <strong class="text-danger"><?php echo $nearby['distance']; ?> km</strong>
                        </small>
                        <br>
                        <span class="badge bg-<?php echo getStatutClass($nearby['statut']); ?> badge-sm">
                            <?php echo getStatutLabel($nearby['statut']); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>

                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle"></i>
                        <small>
                            Assurez-vous que cette nouvelle infrastructure respecte les distances réglementaires.
                        </small>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Coordonnées du Cameroun par défaut
let defaultLat = 7.3697;
let defaultLng = 12.3547;
let defaultZoom = 6;

// Si des coordonnées existent, les utiliser
<?php if ($current_coords): ?>
defaultLat = <?php echo $current_coords['latitude']; ?>;
defaultLng = <?php echo $current_coords['longitude']; ?>;
defaultZoom = 13;
<?php elseif ($dossier['ville']): ?>
// Centres approximatifs des grandes villes du Cameroun
const villeCoords = {
    'Yaoundé': [3.8667, 11.5167],
    'Douala': [4.0511, 9.7679],
    'Garoua': [9.3014, 13.3964],
    'Bafoussam': [5.4781, 10.4179],
    'Bamenda': [5.9597, 10.1453],
    'Maroua': [10.5910, 14.3163],
    'Ngaoundéré': [7.3167, 13.5833]
};
const ville = '<?php echo $dossier['ville']; ?>';
if (villeCoords[ville]) {
    defaultLat = villeCoords[ville][0];
    defaultLng = villeCoords[ville][1];
    defaultZoom = 12;
}
<?php endif; ?>

// Initialiser la carte
const map = L.map('map').setView([defaultLat, defaultLng], defaultZoom);

// Ajouter le fond de carte
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 18
}).addTo(map);

// Marqueur pour la position sélectionnée
let marker = null;

// Si des coordonnées existent, ajouter le marqueur
<?php if ($current_coords): ?>
marker = L.marker([defaultLat, defaultLng], {
    draggable: false
}).addTo(map);

// Tooltip au survol
const currentTooltip = `
    <strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong><br>
    <small><i class="fas fa-map-marker-alt"></i> <?php echo sanitize($dossier['ville'] ?? 'Non spécifié'); ?></small><br>
    <small><i class="fas fa-map-pin"></i> <?php echo sanitize($dossier['adresse_precise'] ?? 'Non spécifié'); ?></small><br>
    <small><strong><?php echo sanitize($dossier['numero']); ?></strong></small><br>
    <small><i class="fas fa-crosshairs"></i> <?php echo $current_coords['latitude']; ?>, <?php echo $current_coords['longitude']; ?></small>
`;

marker.bindTooltip(currentTooltip, {
    permanent: false,
    direction: 'top',
    offset: [0, -20]
});

marker.bindPopup('<strong>Position actuelle</strong><br>Cliquez sur la carte pour modifier').openPopup();
<?php endif; ?>

// Ajouter les infrastructures à proximité sur la carte
<?php if (!empty($nearby_infrastructures)): ?>
const nearbyInfrastructures = <?php echo json_encode($nearby_infrastructures); ?>;

nearbyInfrastructures.forEach(function(infra) {
    const nearbyMarker = L.marker([infra.parsed_coords.latitude, infra.parsed_coords.longitude], {
        icon: L.divIcon({
            html: `<div style="background: #ffc107; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">
                    <i class="fas fa-exclamation" style="font-size: 12px;"></i>
                   </div>`,
            className: 'nearby-marker',
            iconSize: [24, 24]
        })
    }).addTo(map);

    // Tooltip au survol
    const tooltipContent = `
        <strong>${infra.nom_demandeur}</strong><br>
        <small><i class="fas fa-road"></i> ${infra.distance} km</small>
    `;

    nearbyMarker.bindTooltip(tooltipContent, {
        permanent: false,
        direction: 'top',
        offset: [0, -15]
    });

    // Popup détaillé au clic
    nearbyMarker.bindPopup(`
        <div style="min-width: 220px;">
            <h6 class="mb-2 text-warning">
                <i class="fas fa-exclamation-triangle"></i> Infrastructure proche
            </h6>
            <p class="mb-1"><strong>${infra.nom_demandeur}</strong></p>
            <p class="mb-1"><small class="text-muted">${infra.numero}</small></p>
            <p class="mb-1">
                <i class="fas fa-map-marker-alt"></i> ${infra.ville}
            </p>
            <p class="mb-0">
                <span class="badge bg-warning text-dark">
                    <i class="fas fa-road"></i> ${infra.distance} km
                </span>
            </p>
        </div>
    `);
});
<?php endif; ?>

// Clic sur la carte pour placer le marqueur
map.on('click', function(e) {
    const lat = e.latlng.lat;
    const lng = e.latlng.lng;

    // Mettre à jour les champs
    document.getElementById('latitude').value = lat.toFixed(6);
    document.getElementById('longitude').value = lng.toFixed(6);

    // Ajouter ou déplacer le marqueur
    if (marker) {
        marker.setLatLng([lat, lng]);

        // Mettre à jour le tooltip
        const newTooltip = `
            <strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong><br>
            <small><i class="fas fa-map-marker-alt"></i> <?php echo sanitize($dossier['ville'] ?? 'Non spécifié'); ?></small><br>
            <small><i class="fas fa-map-pin"></i> <?php echo sanitize($dossier['adresse_precise'] ?? 'Non spécifié'); ?></small><br>
            <small><strong><?php echo sanitize($dossier['numero']); ?></strong></small><br>
            <small><i class="fas fa-crosshairs"></i> ${lat.toFixed(6)}, ${lng.toFixed(6)}</small>
        `;
        marker.setTooltipContent(newTooltip);
    } else {
        marker = L.marker([lat, lng], {
            draggable: false
        }).addTo(map);

        const newTooltip = `
            <strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong><br>
            <small><i class="fas fa-map-marker-alt"></i> <?php echo sanitize($dossier['ville'] ?? 'Non spécifié'); ?></small><br>
            <small><i class="fas fa-map-pin"></i> <?php echo sanitize($dossier['adresse_precise'] ?? 'Non spécifié'); ?></small><br>
            <small><strong><?php echo sanitize($dossier['numero']); ?></strong></small><br>
            <small><i class="fas fa-crosshairs"></i> ${lat.toFixed(6)}, ${lng.toFixed(6)}</small>
        `;

        marker.bindTooltip(newTooltip, {
            permanent: false,
            direction: 'top',
            offset: [0, -20]
        });

        marker.bindPopup('<strong>Nouvelle position</strong><br>Cliquez ailleurs pour modifier');
    }

    marker.openPopup();
});

// Mise à jour du marqueur quand on modifie manuellement les coordonnées
document.getElementById('latitude').addEventListener('change', updateMarkerFromInputs);
document.getElementById('longitude').addEventListener('change', updateMarkerFromInputs);

function updateMarkerFromInputs() {
    const lat = parseFloat(document.getElementById('latitude').value);
    const lng = parseFloat(document.getElementById('longitude').value);

    if (!isNaN(lat) && !isNaN(lng)) {
        if (marker) {
            marker.setLatLng([lat, lng]);

            // Mettre à jour le tooltip
            const updatedTooltip = `
                <strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong><br>
                <small><i class="fas fa-map-marker-alt"></i> <?php echo sanitize($dossier['ville'] ?? 'Non spécifié'); ?></small><br>
                <small><i class="fas fa-map-pin"></i> <?php echo sanitize($dossier['adresse_precise'] ?? 'Non spécifié'); ?></small><br>
                <small><strong><?php echo sanitize($dossier['numero']); ?></strong></small><br>
                <small><i class="fas fa-crosshairs"></i> ${lat.toFixed(6)}, ${lng.toFixed(6)}</small>
            `;
            marker.setTooltipContent(updatedTooltip);
        } else {
            marker = L.marker([lat, lng], {
                draggable: false
            }).addTo(map);

            const newTooltip = `
                <strong><?php echo sanitize($dossier['nom_demandeur']); ?></strong><br>
                <small><i class="fas fa-map-marker-alt"></i> <?php echo sanitize($dossier['ville'] ?? 'Non spécifié'); ?></small><br>
                <small><i class="fas fa-map-pin"></i> <?php echo sanitize($dossier['adresse_precise'] ?? 'Non spécifié'); ?></small><br>
                <small><strong><?php echo sanitize($dossier['numero']); ?></strong></small><br>
                <small><i class="fas fa-crosshairs"></i> ${lat.toFixed(6)}, ${lng.toFixed(6)}</small>
            `;

            marker.bindTooltip(newTooltip, {
                permanent: false,
                direction: 'top',
                offset: [0, -20]
            });
        }
        map.setView([lat, lng], 13);
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>

<?php
// Création d'un point d'intérêt stratégique - SGDI
require_once '../../includes/auth.php';
require_once '../../includes/map_functions.php';
require_once '../../includes/contraintes_distance_functions.php';

requireLogin();

// Vérifier les permissions (admin uniquement)
if ($_SESSION['user_role'] !== 'admin') {
    redirect(url('dashboard.php'), 'Accès réservé à l\'administrateur uniquement', 'error');
}

$errors = [];
$success = '';
$categories = getCategoriesPOI();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $data = [
            'categorie_id' => intval($_POST['categorie_id'] ?? 0),
            'nom' => cleanInput($_POST['nom'] ?? ''),
            'description' => cleanInput($_POST['description'] ?? ''),
            'latitude' => trim($_POST['latitude'] ?? ''),
            'longitude' => trim($_POST['longitude'] ?? ''),
            'adresse' => cleanInput($_POST['adresse'] ?? ''),
            'ville' => cleanInput($_POST['ville'] ?? ''),
            'region' => cleanInput($_POST['region'] ?? ''),
            'zone_type' => $_POST['zone_type'] ?? 'urbaine'
        ];

        // Validation
        if (empty($data['categorie_id'])) {
            $errors[] = 'La catégorie est obligatoire';
        }
        if (empty($data['nom'])) {
            $errors[] = 'Le nom est obligatoire';
        }
        if (empty($data['latitude']) || empty($data['longitude'])) {
            $errors[] = 'Les coordonnées GPS sont obligatoires';
        } else {
            $validation_errors = validateGPSCoordinates($data['latitude'], $data['longitude']);
            $errors = array_merge($errors, $validation_errors);
        }

        if (empty($errors)) {
            $poi_id = creerPOI($data, $_SESSION['user_id']);

            if ($poi_id) {
                redirect(
                    url('modules/poi/index.php'),
                    'Point d\'intérêt créé avec succès',
                    'success'
                );
            } else {
                $errors[] = 'Erreur lors de la création du POI';
            }
        }
    }
}

$page_title = 'Ajouter un point d\'intérêt';
require_once '../../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<!-- Mobile Responsive pour cartes -->
<link rel="stylesheet" href="../../assets/css/map-mobile-responsive.css">

<style>
#map {
    height: 400px;
    width: 100%;
    border-radius: 8px;
    cursor: crosshair;
}
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo url('dashboard.php'); ?>">Tableau de bord</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo url('modules/poi/index.php'); ?>">Points d'intérêt</a></li>
                    <li class="breadcrumb-item active">Ajouter</li>
                </ol>
            </nav>

            <h1 class="h3">
                <i class="fas fa-plus-circle"></i> Ajouter un point d'intérêt stratégique
            </h1>
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

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

        <div class="row">
            <div class="col-lg-8">
                <!-- Carte interactive -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-map"></i> Localisation - Cliquez sur la carte
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="map"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Informations du POI -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle"></i> Informations
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="categorie_id" class="form-label">Catégorie *</label>
                            <select class="form-select" id="categorie_id" name="categorie_id" required>
                                <option value="">Sélectionnez une catégorie</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"
                                        data-distance="<?php echo $cat['distance_min_metres']; ?>"
                                        data-distance-rural="<?php echo $cat['distance_min_rural_metres']; ?>">
                                    <?php echo sanitize($cat['nom']); ?>
                                    (<?php echo $cat['distance_min_metres']; ?>m / <?php echo $cat['distance_min_rural_metres']; ?>m rural)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom de l'établissement *</label>
                            <input type="text" class="form-control" id="nom" name="nom" required
                                   placeholder="Ex: Lycée Leclerc">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                      placeholder="Description détaillée (optionnel)"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="ville" class="form-label">Ville</label>
                            <input type="text" class="form-control" id="ville" name="ville"
                                   placeholder="Ex: Yaoundé">
                        </div>

                        <div class="mb-3">
                            <label for="region" class="form-label">Région</label>
                            <input type="text" class="form-control" id="region" name="region"
                                   placeholder="Ex: Centre">
                        </div>

                        <div class="mb-3">
                            <label for="adresse" class="form-label">Adresse</label>
                            <textarea class="form-control" id="adresse" name="adresse" rows="2"
                                      placeholder="Adresse précise"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="zone_type" class="form-label">Type de zone *</label>
                            <select class="form-select" id="zone_type" name="zone_type" required>
                                <option value="urbaine" selected>Zone urbaine</option>
                                <option value="rurale">Zone rurale</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="latitude" class="form-label">Latitude *</label>
                            <input type="text" class="form-control" id="latitude" name="latitude" required
                                   placeholder="Ex: 3.8667" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="longitude" class="form-label">Longitude *</label>
                            <input type="text" class="form-control" id="longitude" name="longitude" required
                                   placeholder="Ex: 11.5167" readonly>
                        </div>
                    </div>
                </div>

                <!-- Boutons -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer
                            </button>
                            <a href="<?php echo url('modules/poi/index.php'); ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Initialiser la carte sur le Cameroun
const map = L.map('map').setView([7.3697, 12.3547], 6);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 18
}).addTo(map);

let marker = null;

// Clic sur la carte
map.on('click', function(e) {
    const lat = e.latlng.lat;
    const lng = e.latlng.lng;

    // Mettre à jour les champs
    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;

    // Ajouter ou déplacer le marqueur
    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng]).addTo(map);
    }

    marker.bindPopup('<strong>Nouvelle position</strong><br>Cliquez ailleurs pour modifier').openPopup();
});
</script>

<!-- Mobile Responsive pour cartes -->
<script src="../../assets/js/map-mobile-responsive.js"></script>
<?php require_once '../../includes/footer.php'; ?>

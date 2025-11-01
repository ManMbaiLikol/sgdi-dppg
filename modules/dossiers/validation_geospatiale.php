<?php
// Validation géospatiale d'une infrastructure - SGDI
require_once '../../includes/auth.php';
require_once '../../includes/map_functions.php';
require_once '../../includes/contraintes_distance_functions.php';
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
if (!hasAnyRole(['chef_service', 'admin', 'cadre_dppg', 'sous_directeur', 'directeur'])) {
    redirect(url('dashboard.php'), 'Accès non autorisé', 'error');
}

$errors = [];
$success = '';
$validation_result = null;

// Parser les coordonnées existantes
$current_coords = null;
if ($dossier['coordonnees_gps']) {
    $current_coords = parseGPSCoordinates($dossier['coordonnees_gps']);
}

if (!$current_coords) {
    redirect(
        url('modules/dossiers/localisation.php?id=' . $dossier_id),
        'Le dossier doit avoir des coordonnées GPS avant la validation',
        'warning'
    );
}

// Traitement du formulaire de validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $zone_type = $_POST['zone_type'] ?? 'urbaine';

        // Effectuer la validation
        $validation_result = validerConformiteGeospatiale(
            $current_coords['latitude'],
            $current_coords['longitude'],
            $dossier_id,
            $zone_type
        );

        // Enregistrer dans la base de données
        $validation_id = enregistrerValidationGeospatiale(
            $dossier_id,
            $current_coords['latitude'],
            $current_coords['longitude'],
            $zone_type,
            $validation_result['conforme'],
            $validation_result['violations'],
            $_SESSION['user_id']
        );

        if ($validation_id) {
            // Ajouter à l'historique
            addHistoriqueDossier(
                $dossier_id,
                $_SESSION['user_id'],
                'validation_geospatiale',
                sprintf(
                    'Validation géospatiale effectuée - Résultat: %s (%d violation(s) détectée(s))',
                    $validation_result['conforme'] ? 'CONFORME' : 'NON CONFORME',
                    $validation_result['nombre_violations']
                ),
                null,
                null
            );

            $success = $validation_result['conforme']
                ? 'Validation réussie : l\'infrastructure est conforme aux normes de distance.'
                : 'Validation terminée : ' . $validation_result['nombre_violations'] . ' violation(s) détectée(s).';

            // Recharger le dossier
            $dossier = getDossierById($dossier_id);
        } else {
            $errors[] = 'Erreur lors de l\'enregistrement de la validation';
        }
    }
}

// Récupérer la dernière validation si elle existe
if (!$validation_result && $dossier['validation_geospatiale_faite']) {
    $derniere_validation = getDerniereValidationGeospatiale($dossier_id);
    if ($derniere_validation) {
        $validation_result = [
            'conforme' => $derniere_validation['conforme'],
            'nombre_violations' => $derniere_validation['nombre_violations'],
            'violations' => getViolationsDossier($dossier_id)
        ];
    }
}

$page_title = 'Validation géospatiale - Dossier ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<style>
#map {
    height: 500px;
    width: 100%;
    border-radius: 8px;
}

.violation-card {
    border-left: 4px solid;
    margin-bottom: 15px;
}

.violation-critique {
    border-left-color: #dc3545;
    background-color: #f8d7da;
}

.violation-majeure {
    border-left-color: #fd7e14;
    background-color: #fff3cd;
}

.violation-mineure {
    border-left-color: #ffc107;
    background-color: #fff9e6;
}

.conforme-badge {
    font-size: 1.2rem;
    padding: 10px 20px;
}

.info-box {
    background: #e7f3ff;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.warning-box {
    background: #fff3cd;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #ffc107;
}

.zone-circle {
    fill: none;
    stroke-width: 2;
    opacity: 0.4;
}

.zone-station {
    stroke: #ff6b6b;
}

.zone-poi-1000 {
    stroke: #8B0000;
}

.zone-poi-500 {
    stroke: #DC143C;
}

.zone-poi-100 {
    stroke: #FF6B6B;
}
</style>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<!-- Mobile Responsive pour cartes -->
<link rel="stylesheet" href="../../assets/css/map-mobile-responsive.css">

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo url('dashboard.php'); ?>">Tableau de bord</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo url('modules/dossiers/list.php'); ?>">Dossiers</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>">
                        <?php echo sanitize($dossier['numero']); ?>
                    </a></li>
                    <li class="breadcrumb-item active">Validation géospatiale</li>
                </ol>
            </nav>

            <h1 class="h3">
                <i class="fas fa-ruler-combined"></i> Validation géospatiale de l'infrastructure
            </h1>
            <p class="text-muted">
                Dossier: <strong><?php echo sanitize($dossier['numero']); ?></strong> -
                <?php echo sanitize($dossier['nom_demandeur']); ?>
            </p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
            <li><?php echo sanitize($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <i class="fas fa-check-circle"></i> <?php echo sanitize($success); ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Formulaire et résultats -->
        <div class="col-lg-4">
            <!-- Informations du dossier -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i> Informations du dossier
                    </h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Numéro:</dt>
                        <dd class="col-sm-7"><?php echo sanitize($dossier['numero']); ?></dd>

                        <dt class="col-sm-5">Type:</dt>
                        <dd class="col-sm-7"><?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?></dd>

                        <dt class="col-sm-5">Localisation:</dt>
                        <dd class="col-sm-7">
                            <?php echo sanitize($dossier['ville']); ?>
                            <?php if ($dossier['region']): ?>
                                , <?php echo sanitize($dossier['region']); ?>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-5">Coordonnées GPS:</dt>
                        <dd class="col-sm-7">
                            <small class="font-monospace">
                                <?php echo $current_coords['latitude']; ?>, <?php echo $current_coords['longitude']; ?>
                            </small>
                        </dd>

                        <dt class="col-sm-5">Zone:</dt>
                        <dd class="col-sm-7">
                            <span class="badge bg-<?php echo $dossier['zone_type'] === 'rurale' ? 'success' : 'info'; ?>">
                                <?php echo ucfirst($dossier['zone_type'] ?? 'urbaine'); ?>
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>

            <!-- Statut de validation actuel -->
            <?php if ($dossier['validation_geospatiale_faite']): ?>
            <div class="card mb-4">
                <div class="card-header bg-<?php echo $dossier['conformite_geospatiale'] === 'conforme' ? 'success' : 'danger'; ?> text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clipboard-check"></i> Statut de conformité
                    </h5>
                </div>
                <div class="card-body text-center">
                    <span class="conforme-badge badge bg-<?php echo $dossier['conformite_geospatiale'] === 'conforme' ? 'success' : 'danger'; ?>">
                        <?php echo $dossier['conformite_geospatiale'] === 'conforme' ? 'CONFORME' : 'NON CONFORME'; ?>
                    </span>

                    <?php if ($validation_result): ?>
                    <p class="mt-3 mb-0">
                        <strong><?php echo $validation_result['nombre_violations']; ?></strong>
                        violation(s) détectée(s)
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Formulaire de validation -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-play-circle"></i>
                        <?php echo $dossier['validation_geospatiale_faite'] ? 'Nouvelle validation' : 'Lancer la validation'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="valider">

                        <div class="mb-3">
                            <label class="form-label">Type de zone *</label>
                            <select class="form-select" name="zone_type" required>
                                <option value="urbaine" <?php echo ($dossier['zone_type'] ?? 'urbaine') === 'urbaine' ? 'selected' : ''; ?>>
                                    Zone urbaine (distances normales)
                                </option>
                                <option value="rurale" <?php echo ($dossier['zone_type'] ?? '') === 'rurale' ? 'selected' : ''; ?>>
                                    Zone rurale (réduction de 20%)
                                </option>
                            </select>
                            <small class="form-text text-muted">
                                Les distances minimales sont réduites de 20% en zone rurale
                            </small>
                        </div>

                        <div class="info-box mb-3">
                            <strong><i class="fas fa-info-circle"></i> Normes vérifiées:</strong>
                            <ul class="mb-0 mt-2 small">
                                <li>Distance entre stations-service: <strong>500m</strong> (400m rural)</li>
                                <li>Présidence/PM/Parlement: <strong>1000m</strong> (800m rural)</li>
                                <li>Gouvernorat/Préfecture/Mairie: <strong>500m</strong> (400m rural)</li>
                                <li>Écoles/Hôpitaux/Lieux de culte: <strong>100m</strong> (80m rural)</li>
                            </ul>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check-circle"></i>
                                <?php echo $dossier['validation_geospatiale_faite'] ? 'Relancer la validation' : 'Valider maintenant'; ?>
                            </button>
                            <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>"
                               class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour au dossier
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Carte et violations -->
        <div class="col-lg-8">
            <!-- Carte -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-map"></i> Carte des contraintes de distance
                    </h5>
                </div>
                <div class="card-body">
                    <div id="map"></div>

                    <div class="mt-3">
                        <strong>Légende des zones:</strong>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <small>
                                    <span style="display: inline-block; width: 20px; height: 3px; background: #ff6b6b; margin-right: 5px;"></span>
                                    Zone de sécurité station (500m/400m)
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small>
                                    <span style="display: inline-block; width: 20px; height: 3px; background: #8B0000; margin-right: 5px;"></span>
                                    Présidence/PM/Parlement (1000m/800m)
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small>
                                    <span style="display: inline-block; width: 20px; height: 3px; background: #DC143C; margin-right: 5px;"></span>
                                    Gouvernorat/Préfecture/Mairie (500m/400m)
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small>
                                    <span style="display: inline-block; width: 20px; height: 3px; background: #FF6B6B; margin-right: 5px;"></span>
                                    Écoles/Hôpitaux/Lieux de culte (100m/80m)
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Violations détectées -->
            <?php if ($validation_result && !empty($validation_result['violations'])): ?>
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-exclamation-triangle"></i>
                        Violations détectées (<?php echo count($validation_result['violations']); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($validation_result['violations'] as $violation): ?>
                    <div class="violation-card violation-<?php echo $violation['severite']; ?> p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">
                                    <i class="fas fa-<?php echo $violation['type'] === 'distance_station' ? 'gas-pump' : 'landmark'; ?>"></i>
                                    <?php echo sanitize($violation['nom_etablissement']); ?>
                                </h6>
                                <p class="mb-2 text-muted small">
                                    <?php echo isset($violation['categorie']) ? sanitize($violation['categorie']) : 'Station-service'; ?>
                                    <?php if (isset($violation['ville'])): ?>
                                        - <?php echo sanitize($violation['ville']); ?>
                                    <?php endif; ?>
                                </p>
                                <p class="mb-0">
                                    <strong>Distance mesurée:</strong> <span class="text-danger"><?php echo number_format($violation['distance_mesuree'], 0); ?> m</span>
                                    <br>
                                    <strong>Distance requise:</strong> <?php echo number_format($violation['distance_requise'], 0); ?> m
                                    <br>
                                    <strong>Écart:</strong> <span class="text-danger"><?php echo number_format($violation['ecart'], 0); ?> m</span>
                                </p>
                            </div>
                            <div>
                                <span class="badge bg-<?php echo $violation['severite'] === 'critique' ? 'danger' : ($violation['severite'] === 'majeure' ? 'warning' : 'info'); ?>">
                                    <?php echo strtoupper($violation['severite']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="warning-box mt-3">
                        <i class="fas fa-info-circle"></i>
                        <strong>Important:</strong>
                        Ces violations doivent être prises en compte dans l'évaluation du dossier.
                        Une dérogation peut être nécessaire pour poursuivre la procédure.
                    </div>
                </div>
            </div>
            <?php elseif ($validation_result && $validation_result['conforme']): ?>
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-check-circle"></i> Infrastructure conforme
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-thumbs-up"></i>
                        <strong>Excellent !</strong>
                        L'infrastructure respecte toutes les normes de distance de sécurité.
                        Aucune violation n'a été détectée.
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Initialiser la carte
const lat = <?php echo $current_coords['latitude']; ?>;
const lng = <?php echo $current_coords['longitude']; ?>;
const zoneType = '<?php echo $dossier['zone_type'] ?? 'urbaine'; ?>';

const map = L.map('map').setView([lat, lng], 14);

// Ajouter le fond de carte
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 18
}).addTo(map);

// Marqueur pour l'infrastructure en cours de validation
const mainMarker = L.marker([lat, lng], {
    icon: L.divIcon({
        html: '<div style="background: #007bff; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; border: 4px solid white; box-shadow: 0 3px 8px rgba(0,0,0,0.4);"><i class="fas fa-map-marker-alt" style="font-size: 18px;"></i></div>',
        className: 'main-marker',
        iconSize: [40, 40],
        iconAnchor: [20, 20]
    })
}).addTo(map);

mainMarker.bindPopup(`
    <div style="min-width: 200px;">
        <h6 class="mb-2"><strong><?php echo sanitize($dossier['numero']); ?></strong></h6>
        <p class="mb-1"><?php echo sanitize($dossier['nom_demandeur']); ?></p>
        <p class="mb-0"><small><?php echo sanitize($dossier['ville']); ?></small></p>
    </div>
`).openPopup();

// Dessiner les cercles de contrainte
// Zone station-service (500m ou 400m)
const radiusStation = zoneType === 'rurale' ? 400 : 500;
L.circle([lat, lng], {
    radius: radiusStation,
    className: 'zone-circle zone-station',
    fillColor: '#ff6b6b',
    fillOpacity: 0.1
}).addTo(map).bindTooltip('Zone station-service: ' + radiusStation + 'm');

<?php if ($validation_result && !empty($validation_result['violations'])): ?>
// Ajouter les marqueurs pour les violations
const violations = <?php echo json_encode($validation_result['violations']); ?>;

violations.forEach(function(violation) {
    if (violation.coordonnees) {
        const coords = violation.coordonnees.split(',').map(c => parseFloat(c.trim()));
        if (coords.length === 2) {
            const color = violation.severite === 'critique' ? '#dc3545' :
                         violation.severite === 'majeure' ? '#fd7e14' : '#ffc107';

            const marker = L.marker([coords[0], coords[1]], {
                icon: L.divIcon({
                    html: `<div style="background: ${color}; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"><i class="fas fa-exclamation" style="font-size: 12px;"></i></div>`,
                    className: 'violation-marker',
                    iconSize: [28, 28]
                })
            }).addTo(map);

            marker.bindPopup(`
                <div style="min-width: 220px;">
                    <h6 class="mb-2 text-${violation.severite === 'critique' ? 'danger' : 'warning'}">
                        <i class="fas fa-exclamation-triangle"></i> Violation ${violation.severite}
                    </h6>
                    <p class="mb-1"><strong>${violation.nom_etablissement}</strong></p>
                    <p class="mb-1 small">${violation.categorie || 'Station-service'}</p>
                    <hr class="my-2">
                    <p class="mb-1 small">
                        <strong>Distance:</strong> <span class="text-danger">${Math.round(violation.distance_mesuree)} m</span>
                    </p>
                    <p class="mb-1 small">
                        <strong>Requis:</strong> ${Math.round(violation.distance_requise)} m
                    </p>
                    <p class="mb-0 small">
                        <strong>Écart:</strong> <span class="text-danger">${Math.round(violation.ecart)} m</span>
                    </p>
                </div>
            `);

            // Dessiner une ligne entre le dossier et la violation
            L.polyline([[lat, lng], [coords[0], coords[1]]], {
                color: color,
                weight: 2,
                opacity: 0.6,
                dashArray: '5, 10'
            }).addTo(map);
        }
    }
});
<?php endif; ?>
</script>

<!-- Mobile Responsive pour cartes -->
<script src="../../assets/js/map-mobile-responsive.js"></script>
<?php require_once '../../includes/footer.php'; ?>

<?php
// Dashboard Lecteur Public - Registre des infrastructures autorisées
require_once '../../includes/auth.php';
require_once '../../modules/dossiers/functions.php';

requireRole('lecteur');

$page_title = 'Registre Public des Infrastructures';

global $pdo;

// Statistiques publiques (uniquement autorisés/rejetés)
$stats = [
    'total_autorise' => 0,
    'total_rejete' => 0,
    'ce_mois' => 0,
    'par_region' => 0
];

// Total autorisés
$sql = "SELECT COUNT(*) FROM dossiers WHERE statut = 'autorise'";
$stats['total_autorise'] = $pdo->query($sql)->fetchColumn();

// Total rejetés
$sql = "SELECT COUNT(*) FROM dossiers WHERE statut = 'rejete'";
$stats['total_rejete'] = $pdo->query($sql)->fetchColumn();

// Ce mois (autorisés uniquement)
$sql = "SELECT COUNT(*) FROM decisions
        WHERE MONTH(date_decision) = MONTH(CURRENT_DATE())
        AND YEAR(date_decision) = YEAR(CURRENT_DATE())
        AND decision = 'approuve'";
$stats['ce_mois'] = $pdo->query($sql)->fetchColumn();

// Nombre de régions avec infrastructures
$sql = "SELECT COUNT(DISTINCT region) FROM dossiers WHERE statut = 'autorise'";
$stats['par_region'] = $pdo->query($sql)->fetchColumn();

// Infrastructures autorisées récentes (pour la carte)
// Utiliser les statuts avancés comme dans public_map
$sql = "SELECT d.*,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_decision_format,
        d.numero as reference_decision
        FROM dossiers d
        WHERE d.statut IN ('paye', 'inspecte', 'valide', 'autorise')
        ORDER BY d.date_creation DESC
        LIMIT 20";

try {
    $infrastructures_recentes = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur SQL lecteur dashboard: " . $e->getMessage());
    $infrastructures_recentes = [];
}

// Statistiques par type d'infrastructure (autorisés seulement)
$sql = "SELECT type_infrastructure, COUNT(*) as count
        FROM dossiers
        WHERE statut = 'autorise'
        GROUP BY type_infrastructure
        ORDER BY count DESC";
$stats_types = $pdo->query($sql)->fetchAll();

// Statistiques par région (top 10)
$sql = "SELECT region, COUNT(*) as count
        FROM dossiers
        WHERE statut = 'autorise' AND region IS NOT NULL AND region != ''
        GROUP BY region
        ORDER BY count DESC
        LIMIT 10";
$stats_regions = $pdo->query($sql)->fetchAll();

// Préparer les données pour la carte
$infrastructures_carte = [];
foreach ($infrastructures_recentes as $infra) {
    // Extraire lat/lon depuis coordonnees_gps (format: "lat,lon")
    $coords = explode(',', $infra['coordonnees_gps'] ?? '');
    $latitude = isset($coords[0]) ? trim($coords[0]) : null;
    $longitude = isset($coords[1]) ? trim($coords[1]) : null;

    if (!empty($latitude) && !empty($longitude) && is_numeric($latitude) && is_numeric($longitude)) {
        $infrastructures_carte[] = [
            'id' => $infra['id'],
            'numero' => $infra['numero'],
            'type' => getTypeInfrastructureLabel($infra['type_infrastructure']),
            'type_infrastructure' => $infra['type_infrastructure'], // Ajout du type brut pour les icônes
            'operateur' => $infra['operateur_proprietaire'] ?? $infra['nom_demandeur'],
            'localisation' => $infra['lieu_dit'] ?? ($infra['quartier'] . ', ' . $infra['ville']),
            'region' => $infra['region'],
            'latitude' => (float)$latitude,
            'longitude' => (float)$longitude,
            'date_decision' => $infra['date_decision_format'],
            'reference' => $infra['reference_decision'],
            'statut' => $infra['statut']
        ];
    }
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h1 class="h3 mb-1">
                                <i class="fas fa-globe"></i> Registre Public des Infrastructures Pétrolières
                            </h1>
                            <p class="mb-0 opacity-75">
                                Consultation publique des infrastructures autorisées au Cameroun
                            </p>
                        </div>
                        <div>
                            <a href="<?php echo url('modules/lecteur/recherche.php'); ?>" class="btn btn-light">
                                <i class="fas fa-search"></i> Recherche Avancée
                            </a>
                            <a href="<?php echo url('modules/registre_public/carte.php'); ?>" class="btn btn-light ms-2">
                                <i class="fas fa-map-marked-alt"></i> Voir la carte
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs Publics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Infrastructures Autorisées</h6>
                            <h3 class="mb-0 text-success"><?php echo number_format($stats['total_autorise']); ?></h3>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-success opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Demandes Rejetées</h6>
                            <h3 class="mb-0 text-danger"><?php echo number_format($stats['total_rejete']); ?></h3>
                        </div>
                        <i class="fas fa-times-circle fa-2x text-danger opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Autorisations ce Mois</h6>
                            <h3 class="mb-0 text-primary"><?php echo number_format($stats['ce_mois']); ?></h3>
                        </div>
                        <i class="fas fa-calendar-check fa-2x text-primary opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Régions Couvertes</h6>
                            <h3 class="mb-0 text-info"><?php echo number_format($stats['par_region']); ?></h3>
                        </div>
                        <i class="fas fa-map-marked-alt fa-2x text-info opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Carte des Infrastructures Autorisées -->
    <div class="row mb-4">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-map-marker-alt"></i> Carte des Infrastructures Autorisées
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div id="map" style="height: 500px;"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Légende</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Infrastructures en cours de traitement ou autorisées par le MINEE/DPPG.
                    </p>
                    <strong class="d-block mb-2">Types d'infrastructure:</strong>
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
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques par Type et Région -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-building"></i> Par Type d'Infrastructure</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($stats_types)): ?>
                        <p class="text-muted">Aucune infrastructure autorisée</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($stats_types as $type): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo getTypeInfrastructureLabel($type['type_infrastructure']); ?>
                                    <span class="badge bg-primary rounded-pill"><?php echo $type['count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-map"></i> Top 10 Régions</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($stats_regions)): ?>
                        <p class="text-muted">Aucune donnée régionale</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($stats_regions as $region): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo sanitize($region['region']); ?>
                                    <span class="badge bg-info rounded-pill"><?php echo $region['count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des Infrastructures Récentes -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Infrastructures Récemment Autorisées</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($infrastructures_recentes)): ?>
                        <p class="text-muted">Aucune infrastructure autorisée récemment</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>N° Dossier</th>
                                        <th>Type</th>
                                        <th>Opérateur</th>
                                        <th>Localisation</th>
                                        <th>Région</th>
                                        <th>Date Autorisation</th>
                                        <th>Référence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($infrastructures_recentes as $infra): ?>
                                        <tr>
                                            <td><span class="badge bg-success"><?php echo sanitize($infra['numero']); ?></span></td>
                                            <td><?php echo getTypeInfrastructureLabel($infra['type_infrastructure']); ?></td>
                                            <td><?php echo sanitize($infra['operateur_proprietaire'] ?? $infra['nom_demandeur']); ?></td>
                                            <td><?php echo sanitize($infra['lieu_dit'] ?? ($infra['quartier'] . ', ' . $infra['ville'])); ?></td>
                                            <td><?php echo sanitize($infra['region']); ?></td>
                                            <td><?php echo $infra['date_decision_format']; ?></td>
                                            <td><small><?php echo sanitize($infra['reference_decision']); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser la carte centrée sur le Cameroun
    const map = L.map('map').setView([6.5, 12.5], 6);

    // Ajouter les tuiles OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 18
    }).addTo(map);

    // Données des infrastructures
    const infrastructures = <?php echo json_encode($infrastructures_carte); ?>;

    // Couleurs par type d'infrastructure (comme public_map.php)
    const iconColors = {
        'station_service': '#ff6b6b',
        'point_consommateur': '#4ecdc4',
        'depot_gpl': '#f7b731',
        'centre_emplisseur': '#5f27cd'
    };

    // Libellés de statut
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

    // Ajouter les marqueurs avec icônes personnalisées par type
    infrastructures.forEach(function(infra) {
        const color = iconColors[infra.type_infrastructure] || '#6c757d';

        // Créer une icône personnalisée avec la couleur du type
        const icon = L.divIcon({
            html: `<div style="background: ${color}; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3); font-size: 16px;">
                    <i class="fas fa-check"></i>
                   </div>`,
            className: 'custom-marker',
            iconSize: [32, 32]
        });

        const marker = L.marker([infra.latitude, infra.longitude], {icon: icon}).addTo(map);

        const statusLabel = statusLabels[infra.statut] || infra.statut;
        const statusColor = statusColors[infra.statut] || 'secondary';

        const popupContent = `
            <div style="min-width: 250px;">
                <h6 class="text-${statusColor}"><i class="fas fa-check-circle"></i> ${statusLabel}</h6>
                <p class="mb-1"><strong>Dossier:</strong> ${infra.numero}</p>
                <p class="mb-1"><strong>Type:</strong> ${infra.type}</p>
                <p class="mb-1"><strong>Opérateur:</strong> ${infra.operateur}</p>
                <p class="mb-1"><strong>Localisation:</strong> ${infra.localisation}</p>
                <p class="mb-1"><strong>Région:</strong> ${infra.region}</p>
                <p class="mb-1"><strong>Date:</strong> ${infra.date_decision}</p>
                <p class="mb-0"><small><strong>Réf:</strong> ${infra.reference}</small></p>
            </div>
        `;

        marker.bindPopup(popupContent);
    });

    // Ajuster la vue pour afficher tous les marqueurs
    if (infrastructures.length > 0) {
        const group = new L.featureGroup(
            infrastructures.map(i => L.marker([i.latitude, i.longitude]))
        );
        map.fitBounds(group.getBounds().pad(0.1));
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>

<?php
// Détail d'une infrastructure publique
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../modules/dossiers/functions.php';

$numero = sanitize($_GET['numero'] ?? '');

if (empty($numero)) {
    redirect('index.php');
}

// Récupérer le dossier
$sql = "SELECT d.*, dec.decision, dec.date_decision, dec.reference_decision, dec.motif,
        DATE_FORMAT(dec.date_decision, '%d/%m/%Y') as date_decision_format,
        DATE_FORMAT(d.date_creation, '%d/%m/%Y') as date_creation_format
        FROM dossiers d
        LEFT JOIN decisions dec ON d.id = dec.dossier_id
        WHERE d.numero = :numero AND d.statut IN ('autorise', 'refuse', 'ferme')";

$stmt = $pdo->prepare($sql);
$stmt->execute(['numero' => $numero]);
$dossier = $stmt->fetch();

if (!$dossier) {
    redirect('index.php');
}

$page_title = 'Détail Infrastructure - ' . $dossier['numero'];
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

    <style>
        :root {
            --primary-color: #1e3a8a;
            --secondary-color: #059669;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .public-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%);
            color: white;
            padding: 1.5rem 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .detail-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .info-row {
            padding: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .label {
            font-weight: 600;
            color: #6b7280;
        }

        #map {
            height: 400px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="public-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0"><i class="fas fa-oil-can"></i> Registre Public DPPG</h3>
                </div>
                <div>
                    <a href="index.php" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Retour au registre
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <!-- Informations principales -->
        <div class="detail-card">
            <div class="row mb-4">
                <div class="col-md-8">
                    <h2 class="mb-3"><?php echo htmlspecialchars($dossier['nom_demandeur']); ?></h2>
                    <div class="mb-2">
                        <span class="badge bg-primary fs-6">
                            <?php echo getTypeInfrastructureLabel($dossier['type_infrastructure']); ?>
                        </span>
                        <span class="badge bg-secondary fs-6">
                            <?php echo ucfirst($dossier['sous_type']); ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <h5 class="mb-2">Dossier N° <strong><?php echo htmlspecialchars($dossier['numero']); ?></strong></h5>
                    <?php if ($dossier['statut'] === 'autorise'): ?>
                        <span class="badge bg-success fs-5">
                            <i class="fas fa-check-circle"></i> AUTORISÉE
                        </span>
                    <?php elseif ($dossier['statut'] === 'refuse'): ?>
                        <span class="badge bg-danger fs-5">
                            <i class="fas fa-times-circle"></i> REFUSÉE
                        </span>
                    <?php elseif ($dossier['statut'] === 'ferme'): ?>
                        <span class="badge bg-dark fs-5">
                            <i class="fas fa-ban"></i> FERMÉE
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="info-row">
                        <div class="label">Localisation</div>
                        <div>
                            <i class="fas fa-map-marker-alt text-danger"></i>
                            <strong><?php echo htmlspecialchars($dossier['ville'] ?? '-'); ?>,
                                    <?php echo htmlspecialchars($dossier['region'] ?? '-'); ?></strong>
                        </div>
                        <?php if ($dossier['adresse_precise']): ?>
                            <div class="small text-muted mt-1">
                                <?php echo nl2br(htmlspecialchars($dossier['adresse_precise'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <div class="label">Date de dépôt du dossier</div>
                        <div><?php echo $dossier['date_creation_format']; ?></div>
                    </div>
                </div>
            </div>

            <?php if ($dossier['operateur_proprietaire']): ?>
                <div class="info-row">
                    <div class="label">Opérateur propriétaire</div>
                    <div><i class="fas fa-building text-primary"></i> <?php echo htmlspecialchars($dossier['operateur_proprietaire']); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($dossier['entreprise_beneficiaire']): ?>
                <div class="info-row">
                    <div class="label">Entreprise bénéficiaire</div>
                    <div><i class="fas fa-handshake text-success"></i> <?php echo htmlspecialchars($dossier['entreprise_beneficiaire']); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($dossier['entreprise_installatrice']): ?>
                <div class="info-row">
                    <div class="label">Entreprise installatrice</div>
                    <div><i class="fas fa-hard-hat text-warning"></i> <?php echo htmlspecialchars($dossier['entreprise_installatrice']); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($dossier['operateur_gaz']): ?>
                <div class="info-row">
                    <div class="label">Opérateur de gaz</div>
                    <div><i class="fas fa-fire text-danger"></i> <?php echo htmlspecialchars($dossier['operateur_gaz']); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Décision -->
        <?php if ($dossier['decision']): ?>
            <div class="detail-card">
                <h4 class="mb-4"><i class="fas fa-gavel"></i> Décision administrative</h4>

                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="label">Décision</div>
                            <div>
                                <?php if ($dossier['decision'] === 'approuve'): ?>
                                    <span class="badge bg-success fs-6">
                                        <i class="fas fa-check-circle"></i> APPROUVÉE
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger fs-6">
                                        <i class="fas fa-times-circle"></i> REFUSÉE
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <div class="label">Date de décision</div>
                            <div><?php echo $dossier['date_decision_format']; ?></div>
                        </div>
                    </div>
                </div>

                <?php if ($dossier['reference_decision']): ?>
                    <div class="info-row">
                        <div class="label">Référence de la décision</div>
                        <div><strong><?php echo htmlspecialchars($dossier['reference_decision']); ?></strong></div>
                    </div>
                <?php endif; ?>

                <?php if ($dossier['motif'] && $dossier['decision'] === 'refuse'): ?>
                    <div class="info-row">
                        <div class="label">Motif du refus</div>
                        <div class="text-danger"><?php echo nl2br(htmlspecialchars($dossier['motif'])); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Carte si coordonnées GPS disponibles -->
        <?php if (!empty($dossier['latitude']) && !empty($dossier['longitude'])): ?>
            <div class="detail-card">
                <h4 class="mb-4"><i class="fas fa-map-marked-alt"></i> Localisation GPS</h4>
                <div id="map"></div>
                <p class="mt-2 small text-muted">
                    <i class="fas fa-info-circle"></i>
                    Coordonnées: <?php echo $dossier['latitude']; ?>, <?php echo $dossier['longitude']; ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-primary btn-lg">
                <i class="fas fa-arrow-left"></i> Retour au registre public
            </a>
            <a href="carte.php" class="btn btn-success btn-lg">
                <i class="fas fa-map-marked-alt"></i> Voir sur la carte
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (!empty($dossier['latitude']) && !empty($dossier['longitude'])): ?>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            // Initialiser la carte
            const map = L.map('map').setView([<?php echo $dossier['latitude']; ?>, <?php echo $dossier['longitude']; ?>], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Ajouter le marqueur
            const marker = L.marker([<?php echo $dossier['latitude']; ?>, <?php echo $dossier['longitude']; ?>]).addTo(map);
            marker.bindPopup(`
                <strong><?php echo htmlspecialchars($dossier['nom_demandeur']); ?></strong><br>
                <?php echo getTypeInfrastructureLabel($dossier['type_infrastructure']); ?><br>
                <?php echo htmlspecialchars($dossier['ville']); ?>
            `).openPopup();
        </script>
    <?php endif; ?>
</body>
</html>

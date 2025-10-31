<?php
require_once '../../includes/auth.php';

requireLogin();

// Vérifier autorisation admin
if (!hasRole('admin')) {
    setFlashMessage('Accès refusé. Réservé aux administrateurs.', 'error');
    redirect('../../dashboard.php');
}

$page_title = 'Extraction Stations OSM';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-map-marked-alt text-primary"></i>
        Extraction Stations OpenStreetMap
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../../dashboard.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Retour Dashboard
        </a>
    </div>
</div>

            <!-- Description du module -->
            <div class="alert alert-info">
                <h5><i class="fas fa-info-circle"></i> À propos de ce module</h5>
                <p class="mb-0">
                    Ce module permet d'extraire les données de stations-service depuis <strong>OpenStreetMap (OSM)</strong>
                    pour constituer une base de données géolocalisées des infrastructures autorisées historiques.
                </p>
            </div>

            <!-- Cartes des options -->
            <div class="row g-4">
                <!-- Carte 1: Extraction complète -->
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-download fa-3x text-primary"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="card-title mb-0">Extraction complète OSM</h5>
                                    <p class="text-muted small mb-0">Toutes les stations du Cameroun</p>
                                </div>
                            </div>

                            <p class="card-text">
                                Récupère <strong>toutes les stations-service</strong> du Cameroun depuis OpenStreetMap
                                avec leurs coordonnées GPS, opérateurs, et informations disponibles.
                            </p>

                            <div class="alert alert-secondary small mb-3">
                                <strong>📊 Résultat attendu:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>~600-700 stations extraites</li>
                                    <li>Coordonnées GPS pour toutes</li>
                                    <li>~50% avec opérateur connu</li>
                                    <li>Export CSV prêt à enrichir</li>
                                </ul>
                            </div>

                            <a href="extract_osm_stations.php" class="btn btn-primary w-100">
                                <i class="fas fa-map-marked-alt"></i> Lancer l'extraction OSM
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Carte 2: Filtrage intelligent -->
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm border-success">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-filter fa-3x text-success"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="card-title mb-0">Filtrage intelligent</h5>
                                    <p class="text-muted small mb-0">Stations de haute qualité</p>
                                </div>
                            </div>

                            <p class="card-text">
                                Filtre les stations par <strong>niveau de qualité</strong> pour ne garder que celles
                                avec nom et opérateur (données exploitables immédiatement).
                            </p>

                            <div class="alert alert-success small mb-3">
                                <strong>✅ Recommandé:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Réduction ~50% du volume</li>
                                    <li>Garde les meilleures données</li>
                                    <li>Plus rapide à valider</li>
                                    <li>Import simplifié</li>
                                </ul>
                            </div>

                            <a href="filter_osm_stations.php" class="btn btn-success w-100">
                                <i class="fas fa-filter"></i> Filtrer les stations
                            </a>
                            <p class="text-muted small mt-2 mb-0">
                                <i class="fas fa-exclamation-triangle"></i>
                                Nécessite d'avoir lancé l'extraction au préalable
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Workflow recommandé -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-tasks"></i> Workflow recommandé
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                                     style="width: 50px; height: 50px; font-size: 1.5em; font-weight: bold;">
                                    1
                                </div>
                                <h6 class="mt-2">Extraction OSM</h6>
                                <p class="small text-muted">Récupérer toutes les stations (~700)</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                                     style="width: 50px; height: 50px; font-size: 1.5em; font-weight: bold;">
                                    2
                                </div>
                                <h6 class="mt-2">Filtrage qualité</h6>
                                <p class="small text-muted">Ne garder que les meilleures (~320)</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <div class="bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                                     style="width: 50px; height: 50px; font-size: 1.5em; font-weight: bold;">
                                    3
                                </div>
                                <h6 class="mt-2">Enrichissement</h6>
                                <p class="small text-muted">Ajouter N° autorisation (Excel)</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <div class="bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                                     style="width: 50px; height: 50px; font-size: 1.5em; font-weight: bold;">
                                    4
                                </div>
                                <h6 class="mt-2">Import SGDI</h6>
                                <p class="small text-muted">
                                    <a href="../import_historique/">Module Import</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations légales -->
            <div class="alert alert-warning mt-4">
                <h6><i class="fas fa-balance-scale"></i> Licence et Attribution</h6>
                <p class="mb-2">
                    <strong>Source:</strong> © OpenStreetMap contributors<br>
                    <strong>Licence:</strong> Open Database License (ODbL)<br>
                    <strong>Attribution:</strong> Les données proviennent d'OpenStreetMap et doivent être créditées
                </p>
                <p class="mb-0 small">
                    <a href="https://www.openstreetmap.org/copyright" target="_blank" class="alert-link">
                        Plus d'informations sur la licence ODbL
                    </a>
                </p>
            </div>

<?php require_once '../../includes/footer.php'; ?>

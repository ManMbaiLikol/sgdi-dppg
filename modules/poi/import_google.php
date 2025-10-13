<?php
require_once '../../includes/auth.php';
require_once '../../includes/contraintes_distance_functions.php';

requireLogin();

// Vérifier que l'utilisateur est admin ou chef de service
if (!in_array($_SESSION['user_role'], ['admin', 'chef_service'])) {
    $_SESSION['error'] = "Accès réservé aux administrateurs et chefs de service";
    redirect('dashboard/index.php');
}

// Vérifier que la clé API Google est configurée
$google_api_key = getEnvVar('GOOGLE_PLACES_API_KEY', '');
$api_configured = !empty($google_api_key);

// Récupérer les catégories POI
$categories = $pdo->query("SELECT * FROM categories_poi WHERE actif = 1 ORDER BY nom")->fetchAll();

// Régions et villes principales du Cameroun
$regions_cameroun = [
    'Adamaoua' => ['Ngaoundéré', 'Meiganga', 'Tibati', 'Tignère', 'Banyo'],
    'Centre' => ['Yaoundé', 'Mbalmayo', 'Obala', 'Mfou', 'Akonolinga', 'Bafia'],
    'Est' => ['Bertoua', 'Batouri', 'Yokadouma', 'Abong-Mbang'],
    'Extrême-Nord' => ['Maroua', 'Kousseri', 'Mokolo', 'Yagoua', 'Kaélé'],
    'Littoral' => ['Douala', 'Edéa', 'Nkongsamba', 'Yabassi'],
    'Nord' => ['Garoua', 'Guider', 'Tcholliré', 'Poli'],
    'Nord-Ouest' => ['Bamenda', 'Kumbo', 'Wum', 'Ndop', 'Bali'],
    'Ouest' => ['Bafoussam', 'Bangangté', 'Foumban', 'Dschang', 'Mbouda'],
    'Sud' => ['Ebolowa', 'Kribi', 'Sangmélima', 'Ambam'],
    'Sud-Ouest' => ['Buea', 'Limbe', 'Kumba', 'Mamfe', 'Tiko']
];

$pageTitle = "Import Google Places";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">
                <i class="fas fa-cloud-download-alt"></i>
                Import Google Places
            </h1>
            <p class="text-muted">Importation automatique des points d'intérêt depuis Google Places API</p>
        </div>
        <div>
            <a href="<?php echo url('modules/poi/index.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>

    <?php if (!$api_configured): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Configuration requise :</strong> La clé API Google Places n'est pas configurée.
        <br><br>
        Pour configurer l'API :
        <ol>
            <li>Créez un projet sur <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a></li>
            <li>Activez l'API "Places API"</li>
            <li>Créez une clé API</li>
            <li>Ajoutez la variable <code>GOOGLE_PLACES_API_KEY</code> dans votre fichier <code>.env</code> ou configurez-la dans <code>config/app.php</code></li>
        </ol>
        <small class="text-muted">Note : Google offre ~28$ de crédits gratuits par mois (~875 recherches)</small>
    </div>
    <?php else: ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <strong>API configurée :</strong> La clé Google Places API est active
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Formulaire de recherche -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-search"></i>
                        Paramètres de recherche
                    </h5>
                </div>
                <div class="card-body">
                    <form id="searchForm">
                        <!-- Sélection de la région -->
                        <div class="mb-3">
                            <label class="form-label">Région *</label>
                            <select class="form-select" id="region" name="region" required <?php echo !$api_configured ? 'disabled' : ''; ?>>
                                <option value="">-- Sélectionnez une région --</option>
                                <?php foreach ($regions_cameroun as $region => $villes): ?>
                                <option value="<?php echo htmlspecialchars($region); ?>">
                                    <?php echo htmlspecialchars($region); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Sélection de la ville -->
                        <div class="mb-3">
                            <label class="form-label">Ville *</label>
                            <select class="form-select" id="ville" name="ville" required <?php echo !$api_configured ? 'disabled' : ''; ?>>
                                <option value="">-- Sélectionnez d'abord une région --</option>
                            </select>
                        </div>

                        <!-- Rayon de recherche -->
                        <div class="mb-3">
                            <label class="form-label">Rayon de recherche</label>
                            <select class="form-select" id="radius" name="radius" <?php echo !$api_configured ? 'disabled' : ''; ?>>
                                <option value="5000">5 km</option>
                                <option value="10000" selected>10 km</option>
                                <option value="15000">15 km</option>
                                <option value="20000">20 km</option>
                            </select>
                            <small class="text-muted">Rayon autour du centre-ville</small>
                        </div>

                        <!-- Catégories à rechercher -->
                        <div class="mb-3">
                            <label class="form-label">Catégories de POI</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllCategories" checked>
                                <label class="form-check-label fw-bold" for="selectAllCategories">
                                    Tout sélectionner
                                </label>
                            </div>
                            <hr>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($categories as $cat): ?>
                                <div class="form-check">
                                    <input class="form-check-input category-checkbox" type="checkbox"
                                           name="categories[]" value="<?php echo $cat['id']; ?>"
                                           id="cat_<?php echo $cat['id']; ?>" checked
                                           <?php echo !$api_configured ? 'disabled' : ''; ?>>
                                    <label class="form-check-label" for="cat_<?php echo $cat['id']; ?>">
                                        <i class="fas fa-<?php echo htmlspecialchars($cat['icone']); ?>"></i>
                                        <?php echo htmlspecialchars($cat['nom']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" id="btnSearch"
                                <?php echo !$api_configured ? 'disabled' : ''; ?>>
                            <i class="fas fa-search"></i>
                            Rechercher sur Google Places
                        </button>
                    </form>

                    <div id="searchProgress" class="mt-3" style="display: none;">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated"
                                 role="progressbar" style="width: 0%">0%</div>
                        </div>
                        <p class="text-center mt-2 mb-0">
                            <small id="progressText">Recherche en cours...</small>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="card mt-3" id="statsCard" style="display: none;">
                <div class="card-header bg-info text-white">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-chart-bar"></i>
                        Statistiques
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>POI trouvés :</span>
                        <strong id="statTotal">0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Sélectionnés :</span>
                        <strong id="statSelected" class="text-success">0</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Coût estimé :</span>
                        <strong id="statCost">$0.00</strong>
                    </div>
                    <small class="text-muted">Tarif : $0.032 par recherche</small>
                </div>
            </div>
        </div>

        <!-- Résultats et aperçu -->
        <div class="col-md-8">
            <div class="card" id="resultsCard" style="display: none;">
                <div class="card-header bg-success text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list"></i>
                            Aperçu avant import
                        </h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-light" id="btnSelectAll">
                                <i class="fas fa-check-square"></i> Tout sélectionner
                            </button>
                            <button type="button" class="btn btn-sm btn-light" id="btnDeselectAll">
                                <i class="fas fa-square"></i> Tout désélectionner
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover table-sm mb-0" id="resultsTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th width="5%">
                                        <input type="checkbox" id="checkAllResults">
                                    </th>
                                    <th width="5%"></th>
                                    <th width="25%">Nom</th>
                                    <th width="20%">Catégorie</th>
                                    <th width="25%">Adresse</th>
                                    <th width="10%">Note</th>
                                    <th width="10%">Type</th>
                                </tr>
                            </thead>
                            <tbody id="resultsBody">
                                <!-- Les résultats seront ajoutés ici via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-primary" id="selectedCount">0 sélectionné(s)</span>
                        </div>
                        <button type="button" class="btn btn-success" id="btnImport" disabled>
                            <i class="fas fa-download"></i>
                            Importer les POI sélectionnés
                        </button>
                    </div>
                </div>
            </div>

            <!-- Message initial -->
            <div class="card" id="emptyState">
                <div class="card-body text-center py-5">
                    <i class="fas fa-map-marked-alt fa-4x text-muted mb-3"></i>
                    <h4>Aucune recherche effectuée</h4>
                    <p class="text-muted">
                        Sélectionnez une région et une ville, puis lancez la recherche<br>
                        pour voir les points d'intérêt disponibles sur Google Places.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
}
</style>

<script>
const regionsCities = <?php echo json_encode($regions_cameroun); ?>;
const apiConfigured = <?php echo json_encode($api_configured); ?>;
let searchResults = [];

// Charger les villes selon la région
document.getElementById('region').addEventListener('change', function() {
    const region = this.value;
    const villeSelect = document.getElementById('ville');

    villeSelect.innerHTML = '<option value="">-- Sélectionnez une ville --</option>';

    if (region && regionsCities[region]) {
        regionsCities[region].forEach(ville => {
            const option = document.createElement('option');
            option.value = ville;
            option.textContent = ville;
            villeSelect.appendChild(option);
        });
    }
});

// Sélectionner/désélectionner toutes les catégories
document.getElementById('selectAllCategories').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.category-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// Gestion du formulaire de recherche
document.getElementById('searchForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    if (!apiConfigured) {
        alert('La clé API Google Places n\'est pas configurée');
        return;
    }

    const formData = new FormData(this);
    const categories = [];
    document.querySelectorAll('.category-checkbox:checked').forEach(cb => {
        categories.push(cb.value);
    });

    if (categories.length === 0) {
        alert('Veuillez sélectionner au moins une catégorie');
        return;
    }

    // Afficher la progression
    document.getElementById('searchProgress').style.display = 'block';
    document.getElementById('btnSearch').disabled = true;
    document.getElementById('emptyState').style.display = 'none';

    try {
        const response = await fetch('<?php echo url('modules/poi/import_google_api.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'search',
                region: formData.get('region'),
                ville: formData.get('ville'),
                radius: formData.get('radius'),
                categories: categories
            })
        });

        const data = await response.json();

        if (data.success) {
            searchResults = data.results;
            displayResults(data.results);
            updateStats(data.results, data.search_count);
        } else {
            alert('Erreur : ' + (data.error || 'Erreur inconnue'));
        }
    } catch (error) {
        console.error('Erreur:', error);
        alert('Erreur lors de la recherche : ' + error.message);
    } finally {
        document.getElementById('searchProgress').style.display = 'none';
        document.getElementById('btnSearch').disabled = false;
    }
});

// Afficher les résultats
function displayResults(results) {
    const tbody = document.getElementById('resultsBody');
    tbody.innerHTML = '';

    if (results.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">Aucun POI trouvé</td></tr>';
        document.getElementById('resultsCard').style.display = 'none';
        document.getElementById('emptyState').style.display = 'block';
        return;
    }

    results.forEach((poi, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <input type="checkbox" class="poi-checkbox" data-index="${index}" checked>
            </td>
            <td>
                <i class="fas fa-${escapeHtml(poi.categorie_icone)} text-${poi.categorie_couleur || 'danger'}"></i>
            </td>
            <td>
                <strong>${escapeHtml(poi.nom)}</strong>
            </td>
            <td>
                <span class="badge" style="background-color: ${escapeHtml(poi.categorie_couleur)}">
                    ${escapeHtml(poi.categorie_nom)}
                </span>
            </td>
            <td>
                <small>${escapeHtml(poi.adresse || 'Non précisée')}</small>
            </td>
            <td>
                ${poi.rating ? `<span class="badge bg-warning text-dark">${poi.rating} ⭐</span>` : '-'}
            </td>
            <td>
                <small class="text-muted">${escapeHtml(poi.zone_type || 'urbaine')}</small>
            </td>
        `;
        tbody.appendChild(row);
    });

    document.getElementById('resultsCard').style.display = 'block';
    document.getElementById('emptyState').style.display = 'none';

    // Ajouter les écouteurs d'événements
    document.querySelectorAll('.poi-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });

    updateSelectedCount();
}

// Mettre à jour les statistiques
function updateStats(results, searchCount) {
    document.getElementById('statTotal').textContent = results.length;
    document.getElementById('statSelected').textContent = results.length;
    document.getElementById('statCost').textContent = '$' + (searchCount * 0.032).toFixed(2);
    document.getElementById('statsCard').style.display = 'block';
}

// Mettre à jour le compteur de sélection
function updateSelectedCount() {
    const checkedCount = document.querySelectorAll('.poi-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = checkedCount + ' sélectionné(s)';
    document.getElementById('statSelected').textContent = checkedCount;
    document.getElementById('btnImport').disabled = checkedCount === 0;
}

// Tout sélectionner/désélectionner
document.getElementById('checkAllResults')?.addEventListener('change', function() {
    document.querySelectorAll('.poi-checkbox').forEach(cb => cb.checked = this.checked);
    updateSelectedCount();
});

document.getElementById('btnSelectAll')?.addEventListener('click', function() {
    document.querySelectorAll('.poi-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('checkAllResults').checked = true;
    updateSelectedCount();
});

document.getElementById('btnDeselectAll')?.addEventListener('click', function() {
    document.querySelectorAll('.poi-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('checkAllResults').checked = false;
    updateSelectedCount();
});

// Import des POI sélectionnés
document.getElementById('btnImport')?.addEventListener('click', async function() {
    const selectedPOIs = [];
    document.querySelectorAll('.poi-checkbox:checked').forEach(cb => {
        const index = parseInt(cb.dataset.index);
        selectedPOIs.push(searchResults[index]);
    });

    if (selectedPOIs.length === 0) {
        alert('Veuillez sélectionner au moins un POI à importer');
        return;
    }

    if (!confirm(`Confirmer l'import de ${selectedPOIs.length} point(s) d'intérêt ?`)) {
        return;
    }

    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Import en cours...';

    try {
        const response = await fetch('<?php echo url('modules/poi/import_google_api.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'import',
                pois: selectedPOIs
            })
        });

        const data = await response.json();

        if (data.success) {
            alert(`✅ Import réussi !\n\n${data.imported} POI importés\n${data.skipped} POI ignorés (doublons)`);
            window.location.href = '<?php echo url('modules/poi/index.php'); ?>';
        } else {
            alert('Erreur : ' + (data.error || 'Erreur inconnue'));
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-download"></i> Importer les POI sélectionnés';
        }
    } catch (error) {
        console.error('Erreur:', error);
        alert('Erreur lors de l\'import : ' + error.message);
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-download"></i> Importer les POI sélectionnés';
    }
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include '../../includes/footer.php'; ?>

<?php
// Traitement de l'import des dossiers historiques - SGDI
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

// Vérifier les permissions
if (!peutImporterHistorique($_SESSION['user_role'])) {
    redirect(url('dashboard.php'), 'Vous n\'avez pas les permissions pour accéder à ce module', 'error');
}

// Vérifier le token CSRF
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    redirect(url('modules/import_historique/index.php'), 'Token de sécurité invalide', 'error');
}

// Vérifier la confirmation
if (!isset($_POST['confirm_import'])) {
    redirect(url('modules/import_historique/index.php'), 'Import non confirmé', 'error');
}

// Récupérer les données de la session
if (!isset($_SESSION['import_preview'])) {
    redirect(url('modules/import_historique/index.php'), 'Session expirée, veuillez réimporter le fichier', 'error');
}

$preview = $_SESSION['import_preview'];

// Vérifier que la session n'est pas expirée (30 minutes max)
if (time() - $preview['uploaded_at'] > 1800) {
    unset($_SESSION['import_preview']);
    if (file_exists($preview['temp_file'])) {
        unlink($preview['temp_file']);
    }
    redirect(url('modules/import_historique/index.php'), 'Session expirée, veuillez réimporter le fichier', 'error');
}

$donnees = $preview['donnees'];
$source = $preview['source'];
$user_id = $_SESSION['user_id'];

// Traiter l'import
$resultats = [
    'success' => 0,
    'errors' => 0,
    'details' => []
];

$pageTitle = "Import en cours...";
include '../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-spinner fa-spin"></i> Import en cours...</h4>
                </div>
                <div class="card-body">
                    <p class="lead">Import de <?= count($donnees) ?> dossiers historiques</p>

                    <div class="progress mb-4" style="height: 30px;">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                             role="progressbar" style="width: 0%">0%</div>
                    </div>

                    <div id="import-log" class="border rounded p-3 bg-light" style="max-height: 400px; overflow-y: auto;">
                        <p><i class="fas fa-info-circle text-info"></i> Début de l'import...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Import progressif avec affichage en temps réel
const donnees = <?= json_encode($donnees) ?>;
const total = donnees.length;
let processed = 0;
let success = 0;
let errors = 0;

const progressBar = document.getElementById('progressBar');
const logDiv = document.getElementById('import-log');

function addLog(message, type = 'info') {
    const icons = {
        'success': 'fas fa-check-circle text-success',
        'error': 'fas fa-times-circle text-danger',
        'info': 'fas fa-info-circle text-info'
    };

    const p = document.createElement('p');
    p.className = 'mb-1';
    p.innerHTML = `<i class="${icons[type]}"></i> ${message}`;
    logDiv.appendChild(p);
    logDiv.scrollTop = logDiv.scrollHeight;
}

function updateProgress() {
    const percent = Math.round((processed / total) * 100);
    progressBar.style.width = percent + '%';
    progressBar.textContent = percent + '%';
}

async function importDossier(data, index) {
    try {
        const formData = new FormData();
        formData.append('data', JSON.stringify(data));
        formData.append('source', '<?= addslashes($source) ?>');
        formData.append('csrf_token', '<?= generateCSRFToken() ?>');

        const response = await fetch('<?= url('modules/import_historique/ajax_import_single.php') ?>', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            success++;
            addLog(`✓ Ligne ${index + 1} : ${data.nom_demandeur} - Dossier N° ${result.numero}`, 'success');
        } else {
            errors++;
            addLog(`✗ Ligne ${index + 1} : ${data.nom_demandeur} - Erreur : ${result.error}`, 'error');
        }
    } catch (error) {
        errors++;
        addLog(`✗ Ligne ${index + 1} : ${data.nom_demandeur} - Erreur réseau`, 'error');
    }

    processed++;
    updateProgress();
}

async function processImport() {
    addLog(`Import de ${total} dossiers...`, 'info');

    // Traiter par lots de 10
    const batchSize = 10;
    for (let i = 0; i < donnees.length; i += batchSize) {
        const batch = donnees.slice(i, i + batchSize);
        await Promise.all(batch.map((data, idx) => importDossier(data, i + idx)));
    }

    // Import terminé
    addLog('─────────────────────────────', 'info');
    addLog(`Import terminé : ${success} réussis, ${errors} erreurs`, success > 0 ? 'success' : 'error');

    // Rediriger après 2 secondes
    setTimeout(() => {
        window.location.href = '<?= url('modules/import_historique/result.php') ?>?success=' + success + '&errors=' + errors;
    }, 2000);
}

// Démarrer l'import
processImport();
</script>

<?php
// Nettoyer la session
unset($_SESSION['import_preview']);
if (file_exists($preview['temp_file'])) {
    unlink($preview['temp_file']);
}

include '../../includes/footer.php';
?>

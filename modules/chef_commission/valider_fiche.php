<?php
require_once '../../includes/auth.php';
require_once 'functions.php';
require_once '../fiche_inspection/functions.php';

requireLogin();

// Vérifier que l'utilisateur est un chef de commission
if ($_SESSION['user_role'] !== 'chef_commission') {
    $_SESSION['error'] = "Accès réservé aux chefs de commission";
    redirect(url('dashboard.php'));
}

$fiche_id = $_GET['fiche_id'] ?? null;

if (!$fiche_id) {
    $_SESSION['error'] = "Fiche non spécifiée";
    redirect(url('modules/chef_commission/valider_inspections.php'));
}

// Récupérer la fiche
$fiche = getFicheInspectionById($fiche_id);

if (!$fiche) {
    $_SESSION['error'] = "Fiche introuvable";
    redirect(url('modules/chef_commission/valider_inspections.php'));
}

// Vérifier que le chef de commission est bien assigné
$sql = "SELECT id FROM commissions WHERE dossier_id = ? AND chef_commission_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$fiche['dossier_id'], $_SESSION['user_id']]);
$commission = $stmt->fetch();

if (!$commission) {
    $_SESSION['error'] = "Vous n'êtes pas autorisé à valider cette inspection";
    redirect(url('modules/chef_commission/valider_inspections.php'));
}

// Traitement approbation/rejet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Token de sécurité invalide";
        redirect(url("modules/chef_commission/valider_fiche.php?fiche_id=$fiche_id"));
    }

    try {
        $pdo->beginTransaction();

        if (isset($_POST['approuver'])) {
            $commentaires = $_POST['commentaires'] ?? '';
            approuverInspection($fiche_id, $_SESSION['user_id'], $commentaires);
            $_SESSION['success'] = "Inspection approuvée avec succès. Le dossier passe au circuit de visa.";
        } elseif (isset($_POST['rejeter'])) {
            $motif = trim($_POST['motif'] ?? '');
            if (empty($motif)) {
                throw new Exception("Le motif de rejet est obligatoire");
            }
            rejeterInspection($fiche_id, $_SESSION['user_id'], $motif);
            $_SESSION['success'] = "Inspection rejetée. L'inspecteur a été notifié et peut corriger la fiche.";
        }

        $pdo->commit();
        redirect(url('modules/chef_commission/valider_inspections.php'));

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        error_log("Erreur validation chef commission: " . $e->getMessage());
    }
}

// Récupérer les données de la fiche
$cuves = getCuvesFiche($fiche_id);
$pompes = getPompesFiche($fiche_id);
$distances_edifices = getDistancesEdifices($fiche_id);
$distances_stations = getDistancesStations($fiche_id);

// Organiser par direction
$edifices_par_direction = [];
$stations_par_direction = [];
foreach ($distances_edifices as $de) {
    $edifices_par_direction[$de['direction']] = $de;
}
foreach ($distances_stations as $ds) {
    $stations_par_direction[$ds['direction']] = $ds;
}

// Historique des validations
$historique = getHistoriqueValidations($fiche_id);

$pageTitle = "Valider inspection - " . $fiche['dossier_numero'];
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">
                <i class="fas fa-clipboard-check"></i>
                Validation d'inspection
            </h1>
            <p class="text-muted">Dossier N° <?php echo htmlspecialchars($fiche['dossier_numero']); ?></p>
        </div>
        <div>
            <a href="<?php echo url('modules/chef_commission/valider_inspections.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>

    <!-- Alert mode consultation -->
    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle"></i>
        <strong>Mode consultation</strong> - Vous examinez cette fiche d'inspection pour décision (approbation ou rejet).
        La fiche a été validée par l'inspecteur le <?php echo date('d/m/Y à H:i', strtotime($fiche['date_validation'])); ?>.
    </div>

    <!-- Historique des validations -->
    <?php if (!empty($historique)): ?>
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning text-white">
            <h5 class="mb-0">
                <i class="fas fa-history"></i>
                Historique des validations
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Chef de commission</th>
                            <th>Décision</th>
                            <th>Commentaires</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historique as $h): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($h['date_validation'])); ?></td>
                            <td><?php echo htmlspecialchars($h['prenom'] . ' ' . $h['nom']); ?></td>
                            <td>
                                <?php if ($h['decision'] === 'approuve'): ?>
                                    <span class="badge bg-success">Approuvé</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rejeté</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($h['commentaires']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contenu de la fiche (lecture seule) -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">1. INFORMATIONS GÉNÉRALES</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Type d'infrastructure</label>
                    <p class="form-control-plaintext"><?php echo htmlspecialchars($fiche['type_infrastructure'] ?? '-'); ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Raison sociale</label>
                    <p class="form-control-plaintext"><?php echo htmlspecialchars($fiche['raison_sociale'] ?? '-'); ?></p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Téléphone</label>
                    <p class="form-control-plaintext"><?php echo htmlspecialchars($fiche['telephone'] ?? '-'); ?></p>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-bold">Email</label>
                    <p class="form-control-plaintext"><?php echo htmlspecialchars($fiche['email'] ?? '-'); ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Localisation</label>
                    <p class="form-control-plaintext">
                        <?php
                        $loc = [];
                        if ($fiche['ville']) $loc[] = $fiche['ville'];
                        if ($fiche['quartier']) $loc[] = $fiche['quartier'];
                        if ($fiche['rue']) $loc[] = $fiche['rue'];
                        echo htmlspecialchars(implode(', ', $loc) ?: '-');
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">2. GÉO-RÉFÉRENCEMENT</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Latitude</label>
                    <p class="form-control-plaintext"><?php echo htmlspecialchars($fiche['latitude'] ?? '-'); ?></p>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Longitude</label>
                    <p class="form-control-plaintext"><?php echo htmlspecialchars($fiche['longitude'] ?? '-'); ?></p>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Heure GMT</label>
                    <p class="form-control-plaintext"><?php echo htmlspecialchars($fiche['heure_gmt'] ?? '-'); ?></p>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Heure locale</label>
                    <p class="form-control-plaintext"><?php echo htmlspecialchars($fiche['heure_locale'] ?? '-'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">3. INSTALLATIONS - CUVES</h5>
        </div>
        <div class="card-body">
            <?php if (empty($cuves)): ?>
                <p class="text-muted">Aucune cuve renseignée</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Produit</th>
                                <th>Type</th>
                                <th>Capacité (L)</th>
                                <th>Nombre</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cuves as $cuve): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cuve['numero']); ?></td>
                                <td>
                                    <?php
                                    echo htmlspecialchars(ucfirst($cuve['produit']));
                                    if ($cuve['produit'] === 'autre' && $cuve['produit_autre']) {
                                        echo ' (' . htmlspecialchars($cuve['produit_autre']) . ')';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars(str_replace('_', ' ', $cuve['type_cuve'])); ?></td>
                                <td><?php echo htmlspecialchars(number_format($cuve['capacite'], 2, ',', ' ')); ?> L</td>
                                <td><?php echo htmlspecialchars($cuve['nombre']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">4. INSTALLATIONS - POMPES</h5>
        </div>
        <div class="card-body">
            <?php if (empty($pompes)): ?>
                <p class="text-muted">Aucune pompe renseignée</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Produit</th>
                                <th>Marque</th>
                                <th>Débit nominal (L/min)</th>
                                <th>Nombre</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pompes as $pompe): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pompe['numero']); ?></td>
                                <td>
                                    <?php
                                    echo htmlspecialchars(ucfirst($pompe['produit']));
                                    if ($pompe['produit'] === 'autre' && $pompe['produit_autre']) {
                                        echo ' (' . htmlspecialchars($pompe['produit_autre']) . ')';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($pompe['marque'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars(number_format($pompe['debit_nominal'], 2, ',', ' ')); ?> L/min</td>
                                <td><?php echo htmlspecialchars($pompe['nombre']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">5. OBSERVATIONS GÉNÉRALES</h5>
        </div>
        <div class="card-body">
            <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($fiche['observations_generales'] ?? 'Aucune observation')); ?></p>
        </div>
    </div>

    <!-- Formulaire de décision -->
    <div class="card border-warning">
        <div class="card-header bg-warning text-white">
            <h5 class="mb-0">
                <i class="fas fa-gavel"></i>
                Votre décision
            </h5>
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo url("modules/chef_commission/valider_fiche.php?fiche_id=$fiche_id"); ?>" id="decisionForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="mb-4">
                    <label class="form-label fw-bold">Commentaires (optionnel pour approbation)</label>
                    <textarea name="commentaires" class="form-control" rows="3" placeholder="Vos commentaires sur cette inspection..."></textarea>
                    <small class="text-muted">Ces commentaires seront visibles dans l'historique du dossier</small>
                </div>

                <div class="mb-4" id="motifRejetDiv" style="display: none;">
                    <label class="form-label fw-bold text-danger">Motif de rejet <span class="text-danger">*</span></label>
                    <textarea name="motif" class="form-control border-danger" rows="4" placeholder="Expliquez pourquoi vous rejetez cette inspection (obligatoire)..."></textarea>
                    <small class="text-danger">Le motif sera transmis à l'inspecteur qui devra corriger la fiche</small>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="<?php echo url('modules/chef_commission/valider_inspections.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <div>
                        <button type="button" class="btn btn-danger me-2" id="btnRejeter">
                            <i class="fas fa-times-circle"></i> Rejeter l'inspection
                        </button>
                        <button type="button" class="btn btn-success" id="btnApprouver">
                            <i class="fas fa-check-circle"></i> Approuver l'inspection
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnApprouver = document.getElementById('btnApprouver');
    const btnRejeter = document.getElementById('btnRejeter');
    const motifDiv = document.getElementById('motifRejetDiv');
    const form = document.getElementById('decisionForm');

    btnApprouver.addEventListener('click', function() {
        if (confirm('Êtes-vous sûr de vouloir APPROUVER cette inspection ? Le dossier passera au circuit de visa.')) {
            // Cacher le motif de rejet
            motifDiv.style.display = 'none';
            // Ajouter un champ caché pour indiquer l'approbation
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'approuver';
            input.value = '1';
            form.appendChild(input);
            form.submit();
        }
    });

    btnRejeter.addEventListener('click', function() {
        // Afficher le champ motif
        motifDiv.style.display = 'block';
        // Scroller vers le motif
        motifDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Attendre un peu puis demander confirmation
        setTimeout(function() {
            const motif = document.querySelector('textarea[name="motif"]').value.trim();
            if (!motif) {
                alert('Le motif de rejet est obligatoire. Veuillez expliquer pourquoi vous rejetez cette inspection.');
                return;
            }

            if (confirm('Êtes-vous sûr de vouloir REJETER cette inspection ?\n\nL\'inspecteur devra corriger la fiche selon votre motif : "' + motif + '"')) {
                // Ajouter un champ caché pour indiquer le rejet
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'rejeter';
                input.value = '1';
                form.appendChild(input);
                form.submit();
            }
        }, 500);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>

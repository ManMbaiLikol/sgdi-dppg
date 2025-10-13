<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../dossiers/functions.php';
require_once 'functions.php';

requireLogin();

// Vérifier les permissions d'accès
$roles_autorises = ['cadre_dppg', 'admin', 'chef_service', 'chef_commission'];
if (!in_array($_SESSION['user_role'], $roles_autorises)) {
    $_SESSION['error'] = "Accès non autorisé";
    redirect('dashboard/index.php');
}

// Seuls les cadres DPPG peuvent créer et modifier
$peut_modifier = ($_SESSION['user_role'] === 'cadre_dppg');
$mode_consultation = !$peut_modifier;

$dossier_id = $_GET['dossier_id'] ?? null;

if (!$dossier_id) {
    $_SESSION['error'] = "Dossier non spécifié";
    redirect('modules/dossiers/index.php');
}

// Récupérer le dossier
$dossier = getDossierById($dossier_id);

if (!$dossier) {
    $_SESSION['error'] = "Dossier introuvable";
    redirect('modules/dossiers/index.php');
}

// Récupérer ou créer la fiche
$fiche = getFicheInspectionByDossier($dossier_id);

if (!$fiche && isset($_POST['creer_fiche'])) {
    // Vérifier que seul le cadre DPPG peut créer
    if (!$peut_modifier) {
        $_SESSION['error'] = "Seuls les cadres DPPG peuvent créer des fiches d'inspection";
        redirect("modules/dossiers/view.php?id=$dossier_id");
    }

    $fiche_id = creerFicheInspection($dossier_id, $_SESSION['user_id']);
    if ($fiche_id) {
        $_SESSION['success'] = "Fiche d'inspection créée avec succès";
        redirect("modules/fiche_inspection/edit.php?dossier_id=$dossier_id");
    } else {
        $_SESSION['error'] = "Erreur lors de la création de la fiche";
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_fiche'])) {
    // Vérifier que seul le cadre DPPG peut modifier
    if (!$peut_modifier) {
        $_SESSION['error'] = "Seuls les cadres DPPG peuvent modifier les fiches d'inspection";
        redirect("modules/dossiers/view.php?id=$dossier_id");
    }

    // Vérifier le token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Token de sécurité invalide";
        redirect("modules/fiche_inspection/edit.php?dossier_id=$dossier_id");
    }

    try {
        $pdo->beginTransaction();

        // Préparer les données de la fiche principale
        $data = [
            'raison_sociale' => $_POST['raison_sociale'] ?? '',
            'bp' => $_POST['bp'] ?? '',
            'telephone' => $_POST['telephone'] ?? '',
            'fax' => $_POST['fax'] ?? '',
            'email' => $_POST['email'] ?? '',
            'ville' => $_POST['ville'] ?? '',
            'quartier' => $_POST['quartier'] ?? '',
            'rue' => $_POST['rue'] ?? '',
            'region' => $_POST['region'] ?? '',
            'departement' => $_POST['departement'] ?? '',
            'arrondissement' => $_POST['arrondissement'] ?? '',
            'lieu_dit' => $_POST['lieu_dit'] ?? '',
            'latitude' => $_POST['latitude'] ?? null,
            'longitude' => $_POST['longitude'] ?? null,
            'heure_gmt' => $_POST['heure_gmt'] ?? null,
            'heure_locale' => $_POST['heure_locale'] ?? null,
            'latitude_degres' => $_POST['latitude_degres'] ?? null,
            'latitude_minutes' => $_POST['latitude_minutes'] ?? null,
            'latitude_secondes' => $_POST['latitude_secondes'] ?? null,
            'longitude_degres' => $_POST['longitude_degres'] ?? null,
            'longitude_minutes' => $_POST['longitude_minutes'] ?? null,
            'longitude_secondes' => $_POST['longitude_secondes'] ?? null,
            'date_mise_service' => $_POST['date_mise_service'] ?? null,
            'autorisation_minee' => $_POST['autorisation_minee'] ?? '',
            'autorisation_minmidt' => $_POST['autorisation_minmidt'] ?? '',
            'type_gestion' => $_POST['type_gestion'] ?? 'libre',
            'type_gestion_autre' => $_POST['type_gestion_autre'] ?? null,
            'plan_ensemble' => isset($_POST['plan_ensemble']) ? 1 : 0,
            'contrat_bail' => isset($_POST['contrat_bail']) ? 1 : 0,
            'permis_batir' => isset($_POST['permis_batir']) ? 1 : 0,
            'certificat_urbanisme' => isset($_POST['certificat_urbanisme']) ? 1 : 0,
            'lettre_minepded' => isset($_POST['lettre_minepded']) ? 1 : 0,
            'plan_masse' => isset($_POST['plan_masse']) ? 1 : 0,
            'chef_piste' => $_POST['chef_piste'] ?? '',
            'gerant' => $_POST['gerant'] ?? '',
            'bouches_incendies' => isset($_POST['bouches_incendies']) ? 1 : 0,
            'decanteur_separateur' => isset($_POST['decanteur_separateur']) ? 1 : 0,
            'autres_dispositions_securite' => $_POST['autres_dispositions_securite'] ?? '',
            'observations_generales' => $_POST['observations_generales'] ?? '',
            'lieu_etablissement' => $_POST['lieu_etablissement'] ?? '',
            'date_etablissement' => $_POST['date_etablissement'] ?? null
        ];

        // Mettre à jour la fiche principale
        if (!mettreAJourFicheInspection($fiche['id'], $data)) {
            throw new Exception("Erreur lors de la mise à jour de la fiche");
        }

        // Sauvegarder les cuves
        $cuves = [];
        if (isset($_POST['cuve_numero'])) {
            foreach ($_POST['cuve_numero'] as $index => $numero) {
                if (!empty($_POST['cuve_produit'][$index])) {
                    $cuves[] = [
                        'numero' => $numero,
                        'produit' => $_POST['cuve_produit'][$index],
                        'produit_autre' => $_POST['cuve_produit_autre'][$index] ?? null,
                        'type_cuve' => $_POST['cuve_type'][$index] ?? 'double_enveloppe',
                        'capacite' => $_POST['cuve_capacite'][$index] ?? null,
                        'nombre' => $_POST['cuve_nombre'][$index] ?? 1
                    ];
                }
            }
        }
        if (!sauvegarderCuves($fiche['id'], $cuves)) {
            throw new Exception("Erreur lors de la sauvegarde des cuves");
        }

        // Sauvegarder les pompes
        $pompes = [];
        if (isset($_POST['pompe_numero'])) {
            foreach ($_POST['pompe_numero'] as $index => $numero) {
                if (!empty($_POST['pompe_produit'][$index])) {
                    $pompes[] = [
                        'numero' => $numero,
                        'produit' => $_POST['pompe_produit'][$index],
                        'produit_autre' => $_POST['pompe_produit_autre'][$index] ?? null,
                        'marque' => $_POST['pompe_marque'][$index] ?? null,
                        'debit_nominal' => $_POST['pompe_debit'][$index] ?? null,
                        'nombre' => $_POST['pompe_nombre'][$index] ?? 1
                    ];
                }
            }
        }
        if (!sauvegarderPompes($fiche['id'], $pompes)) {
            throw new Exception("Erreur lors de la sauvegarde des pompes");
        }

        // Sauvegarder les distances aux édifices
        $distances_edifices = [];
        foreach (['nord', 'sud', 'est', 'ouest'] as $direction) {
            $distances_edifices[$direction] = [
                'description' => $_POST["edifice_description_$direction"] ?? null,
                'distance' => $_POST["edifice_distance_$direction"] ?? null
            ];
        }
        if (!sauvegarderDistancesEdifices($fiche['id'], $distances_edifices)) {
            throw new Exception("Erreur lors de la sauvegarde des distances aux édifices");
        }

        // Sauvegarder les distances aux stations
        $distances_stations = [];
        foreach (['nord', 'sud', 'est', 'ouest'] as $direction) {
            $distances_stations[$direction] = [
                'nom' => $_POST["station_nom_$direction"] ?? null,
                'distance' => $_POST["station_distance_$direction"] ?? null
            ];
        }
        if (!sauvegarderDistancesStations($fiche['id'], $distances_stations)) {
            throw new Exception("Erreur lors de la sauvegarde des distances aux stations");
        }

        // Valider la fiche si demandé
        if (isset($_POST['valider'])) {
            if (!validerFicheInspection($fiche['id'])) {
                throw new Exception("Erreur lors de la validation de la fiche");
            }
            $_SESSION['success'] = "Fiche d'inspection validée avec succès";
        } else {
            $_SESSION['success'] = "Fiche d'inspection enregistrée avec succès";
        }

        $pdo->commit();
        redirect("modules/dossiers/view.php?id=$dossier_id");

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        error_log("Erreur sauvegarde fiche: " . $e->getMessage());
    }
}

// Récupérer les données existantes
$cuves = $fiche ? getCuvesFiche($fiche['id']) : [];
$pompes = $fiche ? getPompesFiche($fiche['id']) : [];
$distances_edifices = $fiche ? getDistancesEdifices($fiche['id']) : [];
$distances_stations = $fiche ? getDistancesStations($fiche['id']) : [];

// Organiser les distances par direction
$edifices_par_direction = [];
$stations_par_direction = [];
foreach ($distances_edifices as $de) {
    $edifices_par_direction[$de['direction']] = $de;
}
foreach ($distances_stations as $ds) {
    $stations_par_direction[$ds['direction']] = $ds;
}

$pageTitle = "Fiche d'inspection - " . $dossier['numero'];
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2">Fiche d'inspection de l'infrastructure pétrolière</h1>
            <p class="text-muted">Dossier N° <?php echo htmlspecialchars($dossier['numero']); ?> - <?php echo htmlspecialchars($dossier['nom_demandeur']); ?></p>
        </div>
        <div>
            <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour au dossier
            </a>
        </div>
    </div>

    <?php if ($mode_consultation): ?>
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle"></i>
            <strong>Mode consultation</strong> - Vous consultez cette fiche en lecture seule. Seuls les cadres DPPG peuvent créer et modifier les fiches d'inspection.
        </div>
    <?php endif; ?>

    <?php if (!$fiche): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                <h4>Aucune fiche d'inspection</h4>
                <?php if ($peut_modifier): ?>
                    <p class="text-muted">Créez une nouvelle fiche d'inspection pour ce dossier</p>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <button type="submit" name="creer_fiche" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Créer une fiche d'inspection
                        </button>
                    </form>
                <?php else: ?>
                    <p class="text-muted">Aucune fiche d'inspection n'a encore été créée pour ce dossier.</p>
                    <p class="text-muted small">Seuls les cadres DPPG peuvent créer des fiches d'inspection.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <form method="post" id="ficheForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <!-- Section 1: Informations générales -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">1. INFORMATIONS D'ORDRE GÉNÉRAL</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type d'infrastructure</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($fiche['type_infrastructure']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Raison sociale <span class="text-danger">*</span></label>
                            <input type="text" name="raison_sociale" class="form-control" value="<?php echo htmlspecialchars($fiche['raison_sociale'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">BP</label>
                            <input type="text" name="bp" class="form-control" value="<?php echo htmlspecialchars($fiche['bp'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="telephone" class="form-control" value="<?php echo htmlspecialchars($fiche['telephone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Fax</label>
                            <input type="text" name="fax" class="form-control" value="<?php echo htmlspecialchars($fiche['fax'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($fiche['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <h6 class="mt-4 mb-3 text-primary">Localisation</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Région</label>
                            <input type="text" name="region" class="form-control" value="<?php echo htmlspecialchars($fiche['region'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Département</label>
                            <input type="text" name="departement" class="form-control" value="<?php echo htmlspecialchars($fiche['departement'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Arrondissement</label>
                            <input type="text" name="arrondissement" class="form-control" value="<?php echo htmlspecialchars($fiche['arrondissement'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Ville</label>
                            <input type="text" name="ville" class="form-control" value="<?php echo htmlspecialchars($fiche['ville'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Quartier</label>
                            <input type="text" name="quartier" class="form-control" value="<?php echo htmlspecialchars($fiche['quartier'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Rue</label>
                            <input type="text" name="rue" class="form-control" value="<?php echo htmlspecialchars($fiche['rue'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Lieu-dit</label>
                            <input type="text" name="lieu_dit" class="form-control" value="<?php echo htmlspecialchars($fiche['lieu_dit'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Géo-référencement -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">2. INFORMATIONS DE GÉO-RÉFÉRENCEMENT</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Latitude (décimal)</label>
                            <input type="number" step="0.00000001" name="latitude" class="form-control" value="<?php echo htmlspecialchars($fiche['latitude'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Longitude (décimal)</label>
                            <input type="number" step="0.00000001" name="longitude" class="form-control" value="<?php echo htmlspecialchars($fiche['longitude'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Heure GMT</label>
                            <input type="time" name="heure_gmt" class="form-control" value="<?php echo htmlspecialchars($fiche['heure_gmt'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Heure locale</label>
                            <input type="time" name="heure_locale" class="form-control" value="<?php echo htmlspecialchars($fiche['heure_locale'] ?? ''); ?>">
                        </div>
                    </div>

                    <h6 class="mt-3 mb-3">Coordonnées en degrés, minutes, secondes (DMS)</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Latitude</label>
                            <div class="input-group">
                                <input type="number" name="latitude_degres" class="form-control" placeholder="Degrés" value="<?php echo htmlspecialchars($fiche['latitude_degres'] ?? ''); ?>">
                                <span class="input-group-text">°</span>
                                <input type="number" name="latitude_minutes" class="form-control" placeholder="Minutes" value="<?php echo htmlspecialchars($fiche['latitude_minutes'] ?? ''); ?>">
                                <span class="input-group-text">'</span>
                                <input type="number" step="0.01" name="latitude_secondes" class="form-control" placeholder="Secondes" value="<?php echo htmlspecialchars($fiche['latitude_secondes'] ?? ''); ?>">
                                <span class="input-group-text">"</span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Longitude</label>
                            <div class="input-group">
                                <input type="number" name="longitude_degres" class="form-control" placeholder="Degrés" value="<?php echo htmlspecialchars($fiche['longitude_degres'] ?? ''); ?>">
                                <span class="input-group-text">°</span>
                                <input type="number" name="longitude_minutes" class="form-control" placeholder="Minutes" value="<?php echo htmlspecialchars($fiche['longitude_minutes'] ?? ''); ?>">
                                <span class="input-group-text">'</span>
                                <input type="number" step="0.01" name="longitude_secondes" class="form-control" placeholder="Secondes" value="<?php echo htmlspecialchars($fiche['longitude_secondes'] ?? ''); ?>">
                                <span class="input-group-text">"</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Informations techniques -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">3. INFORMATIONS TECHNIQUES</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date de mise en service</label>
                            <input type="date" name="date_mise_service" class="form-control" value="<?php echo htmlspecialchars($fiche['date_mise_service'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">N° Autorisation MINEE</label>
                            <input type="text" name="autorisation_minee" class="form-control" value="<?php echo htmlspecialchars($fiche['autorisation_minee'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">N° Autorisation MINMIDT</label>
                            <input type="text" name="autorisation_minmidt" class="form-control" value="<?php echo htmlspecialchars($fiche['autorisation_minmidt'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type de gestion</label>
                            <select name="type_gestion" class="form-select" id="typeGestion">
                                <option value="libre" <?php echo ($fiche['type_gestion'] ?? '') === 'libre' ? 'selected' : ''; ?>>Libre</option>
                                <option value="location" <?php echo ($fiche['type_gestion'] ?? '') === 'location' ? 'selected' : ''; ?>>Location</option>
                                <option value="autres" <?php echo ($fiche['type_gestion'] ?? '') === 'autres' ? 'selected' : ''; ?>>Autres</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="autreGestionDiv" style="display: <?php echo ($fiche['type_gestion'] ?? '') === 'autres' ? 'block' : 'none'; ?>;">
                            <label class="form-label">Préciser (si autres)</label>
                            <input type="text" name="type_gestion_autre" class="form-control" value="<?php echo htmlspecialchars($fiche['type_gestion_autre'] ?? ''); ?>">
                        </div>
                    </div>

                    <h6 class="mt-4 mb-3">Documents techniques disponibles</h6>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input type="checkbox" name="plan_ensemble" class="form-check-input" id="planEnsemble" <?php echo $fiche['plan_ensemble'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="planEnsemble">Plan d'ensemble</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input type="checkbox" name="contrat_bail" class="form-check-input" id="contratBail" <?php echo $fiche['contrat_bail'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="contratBail">Contrat de bail</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input type="checkbox" name="permis_batir" class="form-check-input" id="permisBatir" <?php echo $fiche['permis_batir'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="permisBatir">Permis de bâtir</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input type="checkbox" name="certificat_urbanisme" class="form-check-input" id="certificatUrbanisme" <?php echo $fiche['certificat_urbanisme'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="certificatUrbanisme">Certificat d'urbanisme</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input type="checkbox" name="lettre_minepded" class="form-check-input" id="lettreMinepded" <?php echo $fiche['lettre_minepded'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="lettreMinepded">Lettre MINEPDED</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input type="checkbox" name="plan_masse" class="form-check-input" id="planMasse" <?php echo $fiche['plan_masse'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="planMasse">Plan de masse</label>
                            </div>
                        </div>
                    </div>

                    <h6 class="mt-4 mb-3">Personnel</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Chef de piste</label>
                            <input type="text" name="chef_piste" class="form-control" value="<?php echo htmlspecialchars($fiche['chef_piste'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gérant</label>
                            <input type="text" name="gerant" class="form-control" value="<?php echo htmlspecialchars($fiche['gerant'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Installations (Cuves et Pompes) -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">4. INSTALLATIONS</h5>
                </div>
                <div class="card-body">
                    <h6 class="mb-3">Cuves</h6>
                    <div id="cuvesContainer">
                        <?php if (empty($cuves)): ?>
                            <div class="cuve-row mb-3 p-3 border rounded">
                                <div class="row">
                                    <div class="col-md-2">
                                        <label class="form-label">N°</label>
                                        <input type="number" name="cuve_numero[]" class="form-control" value="1">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Produit</label>
                                        <select name="cuve_produit[]" class="form-select cuve-produit">
                                            <option value="">-</option>
                                            <option value="super">Super</option>
                                            <option value="gasoil">Gasoil</option>
                                            <option value="petrole">Pétrole</option>
                                            <option value="autre">Autre</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 cuve-autre" style="display: none;">
                                        <label class="form-label">Autre produit</label>
                                        <input type="text" name="cuve_produit_autre[]" class="form-control">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Type</label>
                                        <select name="cuve_type[]" class="form-select">
                                            <option value="double_enveloppe">Double enveloppe</option>
                                            <option value="simple_enveloppe">Simple enveloppe</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Capacité (L)</label>
                                        <input type="number" step="0.01" name="cuve_capacite[]" class="form-control">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">Nombre</label>
                                        <input type="number" name="cuve_nombre[]" class="form-control" value="1">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-danger btn-sm remove-cuve w-100">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($cuves as $index => $cuve): ?>
                                <div class="cuve-row mb-3 p-3 border rounded">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <label class="form-label">N°</label>
                                            <input type="number" name="cuve_numero[]" class="form-control" value="<?php echo htmlspecialchars($cuve['numero']); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Produit</label>
                                            <select name="cuve_produit[]" class="form-select cuve-produit">
                                                <option value="">-</option>
                                                <option value="super" <?php echo $cuve['produit'] === 'super' ? 'selected' : ''; ?>>Super</option>
                                                <option value="gasoil" <?php echo $cuve['produit'] === 'gasoil' ? 'selected' : ''; ?>>Gasoil</option>
                                                <option value="petrole" <?php echo $cuve['produit'] === 'petrole' ? 'selected' : ''; ?>>Pétrole</option>
                                                <option value="autre" <?php echo $cuve['produit'] === 'autre' ? 'selected' : ''; ?>>Autre</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 cuve-autre" style="display: <?php echo $cuve['produit'] === 'autre' ? 'block' : 'none'; ?>;">
                                            <label class="form-label">Autre produit</label>
                                            <input type="text" name="cuve_produit_autre[]" class="form-control" value="<?php echo htmlspecialchars($cuve['produit_autre'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Type</label>
                                            <select name="cuve_type[]" class="form-select">
                                                <option value="double_enveloppe" <?php echo $cuve['type_cuve'] === 'double_enveloppe' ? 'selected' : ''; ?>>Double enveloppe</option>
                                                <option value="simple_enveloppe" <?php echo $cuve['type_cuve'] === 'simple_enveloppe' ? 'selected' : ''; ?>>Simple enveloppe</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Capacité (L)</label>
                                            <input type="number" step="0.01" name="cuve_capacite[]" class="form-control" value="<?php echo htmlspecialchars($cuve['capacite'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">Nombre</label>
                                            <input type="number" name="cuve_nombre[]" class="form-control" value="<?php echo htmlspecialchars($cuve['nombre']); ?>">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-danger btn-sm remove-cuve w-100">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addCuve">
                        <i class="fas fa-plus"></i> Ajouter une cuve
                    </button>

                    <hr class="my-4">

                    <h6 class="mb-3">Pompes</h6>
                    <div id="pompesContainer">
                        <?php if (empty($pompes)): ?>
                            <div class="pompe-row mb-3 p-3 border rounded">
                                <div class="row">
                                    <div class="col-md-2">
                                        <label class="form-label">N°</label>
                                        <input type="number" name="pompe_numero[]" class="form-control" value="1">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Produit</label>
                                        <select name="pompe_produit[]" class="form-select pompe-produit">
                                            <option value="">-</option>
                                            <option value="super">Super</option>
                                            <option value="gasoil">Gasoil</option>
                                            <option value="petrole">Pétrole</option>
                                            <option value="autre">Autre</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 pompe-autre" style="display: none;">
                                        <label class="form-label">Autre produit</label>
                                        <input type="text" name="pompe_produit_autre[]" class="form-control">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Marque</label>
                                        <input type="text" name="pompe_marque[]" class="form-control">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Débit nominal (L/min)</label>
                                        <input type="number" step="0.01" name="pompe_debit[]" class="form-control">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">Nombre</label>
                                        <input type="number" name="pompe_nombre[]" class="form-control" value="1">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-danger btn-sm remove-pompe w-100">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pompes as $index => $pompe): ?>
                                <div class="pompe-row mb-3 p-3 border rounded">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <label class="form-label">N°</label>
                                            <input type="number" name="pompe_numero[]" class="form-control" value="<?php echo htmlspecialchars($pompe['numero']); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Produit</label>
                                            <select name="pompe_produit[]" class="form-select pompe-produit">
                                                <option value="">-</option>
                                                <option value="super" <?php echo $pompe['produit'] === 'super' ? 'selected' : ''; ?>>Super</option>
                                                <option value="gasoil" <?php echo $pompe['produit'] === 'gasoil' ? 'selected' : ''; ?>>Gasoil</option>
                                                <option value="petrole" <?php echo $pompe['produit'] === 'petrole' ? 'selected' : ''; ?>>Pétrole</option>
                                                <option value="autre" <?php echo $pompe['produit'] === 'autre' ? 'selected' : ''; ?>>Autre</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 pompe-autre" style="display: <?php echo $pompe['produit'] === 'autre' ? 'block' : 'none'; ?>;">
                                            <label class="form-label">Autre produit</label>
                                            <input type="text" name="pompe_produit_autre[]" class="form-control" value="<?php echo htmlspecialchars($pompe['produit_autre'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Marque</label>
                                            <input type="text" name="pompe_marque[]" class="form-control" value="<?php echo htmlspecialchars($pompe['marque'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Débit nominal (L/min)</label>
                                            <input type="number" step="0.01" name="pompe_debit[]" class="form-control" value="<?php echo htmlspecialchars($pompe['debit_nominal'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">Nombre</label>
                                            <input type="number" name="pompe_nombre[]" class="form-control" value="<?php echo htmlspecialchars($pompe['nombre']); ?>">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-danger btn-sm remove-pompe w-100">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addPompe">
                        <i class="fas fa-plus"></i> Ajouter une pompe
                    </button>
                </div>
            </div>

            <!-- Section 5: Distances -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">5. DISTANCES PAR RAPPORT AUX ÉDIFICES ET STATIONS</h5>
                </div>
                <div class="card-body">
                    <h6 class="mb-3">Distance par rapport aux édifices et places publiques les plus proches</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="20%">Direction</th>
                                    <th width="50%">Description de l'édifice ou la place publique</th>
                                    <th width="30%">Distance (en mètres)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (['nord', 'sud', 'est', 'ouest'] as $direction): ?>
                                    <?php $edifice = $edifices_par_direction[$direction] ?? null; ?>
                                    <tr>
                                        <td><?php echo getDirectionLabel($direction); ?></td>
                                        <td>
                                            <input type="text" name="edifice_description_<?php echo $direction; ?>" class="form-control" value="<?php echo htmlspecialchars($edifice['description_edifice'] ?? ''); ?>">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="edifice_distance_<?php echo $direction; ?>" class="form-control" value="<?php echo htmlspecialchars($edifice['distance_metres'] ?? ''); ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3">Distance par rapport aux stations-services les plus proches</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="20%">Direction</th>
                                    <th width="50%">Nom de la station-service</th>
                                    <th width="30%">Distance (en mètres)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (['nord', 'sud', 'est', 'ouest'] as $direction): ?>
                                    <?php $station = $stations_par_direction[$direction] ?? null; ?>
                                    <tr>
                                        <td><?php echo getDirectionLabel($direction); ?></td>
                                        <td>
                                            <input type="text" name="station_nom_<?php echo $direction; ?>" class="form-control" value="<?php echo htmlspecialchars($station['nom_station'] ?? ''); ?>">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="station_distance_<?php echo $direction; ?>" class="form-control" value="<?php echo htmlspecialchars($station['distance_metres'] ?? ''); ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Section 6: Sécurité et environnement -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">6. SÉCURITÉ ET ENVIRONNEMENT</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" name="bouches_incendies" class="form-check-input" id="bouchesIncendies" <?php echo $fiche['bouches_incendies'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="bouchesIncendies">Bouches d'incendies</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" name="decanteur_separateur" class="form-check-input" id="decanteurSeparateur" <?php echo $fiche['decanteur_separateur'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="decanteurSeparateur">Présence de décanteur/séparateur des eaux usées</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Autres dispositions relatives à la sécurité et environnementales</label>
                        <textarea name="autres_dispositions_securite" class="form-control" rows="3"><?php echo htmlspecialchars($fiche['autres_dispositions_securite'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Section 7: Observations -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">7. OBSERVATIONS GÉNÉRALES</h5>
                </div>
                <div class="card-body">
                    <textarea name="observations_generales" class="form-control" rows="6"><?php echo htmlspecialchars($fiche['observations_generales'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Section 8: Établissement -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">8. ÉTABLISSEMENT DE LA FICHE</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fiche établie à</label>
                            <input type="text" name="lieu_etablissement" class="form-control" value="<?php echo htmlspecialchars($fiche['lieu_etablissement'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Le</label>
                            <input type="date" name="date_etablissement" class="form-control" value="<?php echo htmlspecialchars($fiche['date_etablissement'] ?? date('Y-m-d')); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card">
                <div class="card-body">
                    <?php if ($peut_modifier): ?>
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <div>
                                <button type="submit" name="save_fiche" class="btn btn-primary me-2">
                                    <i class="fas fa-save"></i> Enregistrer le brouillon
                                </button>
                                <button type="submit" name="save_fiche" value="valider" class="btn btn-success">
                                    <i class="fas fa-check"></i> Valider la fiche
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center">
                            <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour au dossier
                            </a>
                            <a href="<?php echo url('modules/fiche_inspection/print_filled.php?dossier_id=' . $dossier_id); ?>"
                               class="btn btn-outline-primary" target="_blank">
                                <i class="fas fa-print"></i> Imprimer la fiche
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
// Gestion du type de gestion "Autres"
document.getElementById('typeGestion').addEventListener('change', function() {
    document.getElementById('autreGestionDiv').style.display = this.value === 'autres' ? 'block' : 'none';
});

// Gestion des cuves
let cuveCounter = <?php echo count($cuves) > 0 ? max(array_column($cuves, 'numero')) : 1; ?>;

document.getElementById('addCuve').addEventListener('click', function() {
    cuveCounter++;
    const container = document.getElementById('cuvesContainer');
    const template = `
        <div class="cuve-row mb-3 p-3 border rounded">
            <div class="row">
                <div class="col-md-2">
                    <label class="form-label">N°</label>
                    <input type="number" name="cuve_numero[]" class="form-control" value="${cuveCounter}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Produit</label>
                    <select name="cuve_produit[]" class="form-select cuve-produit">
                        <option value="">-</option>
                        <option value="super">Super</option>
                        <option value="gasoil">Gasoil</option>
                        <option value="petrole">Pétrole</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                <div class="col-md-2 cuve-autre" style="display: none;">
                    <label class="form-label">Autre produit</label>
                    <input type="text" name="cuve_produit_autre[]" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select name="cuve_type[]" class="form-select">
                        <option value="double_enveloppe">Double enveloppe</option>
                        <option value="simple_enveloppe">Simple enveloppe</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Capacité (L)</label>
                    <input type="number" step="0.01" name="cuve_capacite[]" class="form-control">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Nombre</label>
                    <input type="number" name="cuve_nombre[]" class="form-control" value="1">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm remove-cuve w-100">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', template);
});

document.getElementById('cuvesContainer').addEventListener('click', function(e) {
    if (e.target.closest('.remove-cuve')) {
        e.target.closest('.cuve-row').remove();
    }
});

document.getElementById('cuvesContainer').addEventListener('change', function(e) {
    if (e.target.classList.contains('cuve-produit')) {
        const row = e.target.closest('.cuve-row');
        const autreDiv = row.querySelector('.cuve-autre');
        autreDiv.style.display = e.target.value === 'autre' ? 'block' : 'none';
    }
});

// Gestion des pompes
let pompeCounter = <?php echo count($pompes) > 0 ? max(array_column($pompes, 'numero')) : 1; ?>;

document.getElementById('addPompe').addEventListener('click', function() {
    pompeCounter++;
    const container = document.getElementById('pompesContainer');
    const template = `
        <div class="pompe-row mb-3 p-3 border rounded">
            <div class="row">
                <div class="col-md-2">
                    <label class="form-label">N°</label>
                    <input type="number" name="pompe_numero[]" class="form-control" value="${pompeCounter}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Produit</label>
                    <select name="pompe_produit[]" class="form-select pompe-produit">
                        <option value="">-</option>
                        <option value="super">Super</option>
                        <option value="gasoil">Gasoil</option>
                        <option value="petrole">Pétrole</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                <div class="col-md-2 pompe-autre" style="display: none;">
                    <label class="form-label">Autre produit</label>
                    <input type="text" name="pompe_produit_autre[]" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Marque</label>
                    <input type="text" name="pompe_marque[]" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Débit nominal (L/min)</label>
                    <input type="number" step="0.01" name="pompe_debit[]" class="form-control">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Nombre</label>
                    <input type="number" name="pompe_nombre[]" class="form-control" value="1">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm remove-pompe w-100">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', template);
});

document.getElementById('pompesContainer').addEventListener('click', function(e) {
    if (e.target.closest('.remove-pompe')) {
        e.target.closest('.pompe-row').remove();
    }
});

document.getElementById('pompesContainer').addEventListener('change', function(e) {
    if (e.target.classList.contains('pompe-produit')) {
        const row = e.target.closest('.pompe-row');
        const autreDiv = row.querySelector('.pompe-autre');
        autreDiv.style.display = e.target.value === 'autre' ? 'block' : 'none';
    }
});

// Validation du formulaire
document.getElementById('ficheForm').addEventListener('submit', function(e) {
    const validerBtn = document.querySelector('button[value="valider"]');
    if (e.submitter === validerBtn) {
        if (!confirm('Êtes-vous sûr de vouloir valider cette fiche d\'inspection ? Une fois validée, elle ne pourra plus être modifiée.')) {
            e.preventDefault();
        } else {
            // Ajouter un champ caché pour indiquer la validation
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'valider';
            input.value = '1';
            this.appendChild(input);
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>

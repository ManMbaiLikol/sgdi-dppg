<?php
// Modification de dossier - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

$dossier_id = intval($_GET['id'] ?? 0);
if (!$dossier_id) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

// Récupérer le dossier existant
$dossier = getDossierDetails($dossier_id);
if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non trouvé', 'error');
}

// Vérifier les permissions
$is_admin = $_SESSION['user_role'] === 'admin';
$is_chef_service = $_SESSION['user_role'] === 'chef_service';

// Chef service peut modifier TOUS les dossiers SGDI (quel que soit le statut)
// Admin peut modifier TOUS les dossiers (SGDI + historiques)
if ($is_admin || $is_chef_service) {
    // Permissions OK
} else {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id), 'Vous n\'avez pas la permission de modifier ce dossier', 'error');
}

$page_title = 'Modifier le dossier ' . $dossier['numero'];
$errors = [];
$success = false;

// Fonction helper pour gérer les valeurs NULL dans htmlspecialchars
function safeHtml($value, $default = '') {
    return htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        // Validation des données
        $required_fields = ['type_infrastructure', 'sous_type', 'nom_demandeur'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = 'Le champ ' . $field . ' est requis';
            }
        }

        // Validation de l'email si fourni
        if (!empty($_POST['email_demandeur']) && !filter_var($_POST['email_demandeur'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email invalide';
        }

        // Validation spécifique selon le type
        $type = $_POST['type_infrastructure'] ?? '';
        if ($type === 'station_service' && empty($_POST['operateur_proprietaire'])) {
            $errors[] = 'L\'opérateur propriétaire est requis pour une station-service';
        }

        if ($type === 'point_consommateur') {
            if (empty($_POST['entreprise_beneficiaire'])) {
                $errors[] = 'L\'entreprise bénéficiaire est requise pour un point consommateur';
            }
        }

        if ($type === 'depot_gpl' && empty($_POST['entreprise_installatrice'])) {
            $errors[] = 'L\'entreprise installatrice est requise pour un dépôt GPL';
        }

        if (empty($errors)) {
            $data = [
                'type_infrastructure' => cleanInput($_POST['type_infrastructure']),
                'sous_type' => cleanInput($_POST['sous_type']),
                'nom_demandeur' => cleanInput($_POST['nom_demandeur']),
                'contact_demandeur' => cleanInput($_POST['contact_demandeur'] ?? ''),
                'telephone_demandeur' => cleanInput($_POST['telephone_demandeur'] ?? ''),
                'email_demandeur' => cleanInput($_POST['email_demandeur'] ?? ''),
                'adresse_precise' => cleanInput($_POST['adresse_precise'] ?? ''),
                'region' => cleanInput($_POST['region']),
                'departement' => cleanInput($_POST['departement'] ?? ''),
                'ville' => cleanInput($_POST['ville']),
                'arrondissement' => cleanInput($_POST['arrondissement'] ?? ''),
                'quartier' => cleanInput($_POST['quartier'] ?? ''),
                'zone_type' => cleanInput($_POST['zone_type'] ?? 'urbaine'),
                'lieu_dit' => cleanInput($_POST['lieu_dit'] ?? ''),
                'coordonnees_gps' => cleanInput($_POST['coordonnees_gps'] ?? ''),
                'annee_mise_en_service' => !empty($_POST['annee_mise_en_service']) ? intval($_POST['annee_mise_en_service']) : null,
                'operateur_proprietaire' => cleanInput($_POST['operateur_proprietaire'] ?? ''),
                'entreprise_beneficiaire' => cleanInput($_POST['entreprise_beneficiaire'] ?? ''),
                'entreprise_installatrice' => cleanInput($_POST['entreprise_installatrice'] ?? ''),
                'contrat_livraison' => cleanInput($_POST['contrat_livraison'] ?? ''),
                'operateur_gaz' => cleanInput($_POST['operateur_gaz'] ?? ''),
                'entreprise_constructrice' => cleanInput($_POST['entreprise_constructrice'] ?? ''),
                'capacite_enfutage' => cleanInput($_POST['capacite_enfutage'] ?? '')
            ];

            if (modifierDossier($dossier_id, $data)) {
                // Message différent selon le rôle
                $message_historique = 'Dossier modifié par ' . $_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom'];
                if ($is_admin && $dossier['est_historique']) {
                    $message_historique .= ' (Admin - Dossier historique)';
                }

                addHistoriqueDossier($dossier_id, $_SESSION['user_id'], 'modifie', $message_historique);
                redirect(url('modules/dossiers/view.php?id=' . $dossier_id), 'Dossier modifié avec succès', 'success');
            } else {
                $errors[] = 'Erreur lors de la modification du dossier';
            }
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <?php echo $page_title; ?>
                        <?php if ($dossier['est_historique']): ?>
                            <span class="badge bg-secondary">Historique</span>
                        <?php endif; ?>
                    </h1>
                    <p class="text-muted">
                        Modification des informations du dossier
                        <?php if ($is_admin && $dossier['est_historique']): ?>
                            <span class="badge bg-warning text-dark">
                                <i class="fas fa-shield-alt"></i> Mode Admin
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Retour au dossier
                    </a>
                    <?php if ($_SESSION['user_role'] === 'chef_service' && in_array($dossier['statut'], ['brouillon', 'en_cours'])): ?>
                    <a href="<?php echo url('modules/dossiers/upload_documents.php?id=' . $dossier_id); ?>" class="btn btn-info">
                        <i class="fas fa-upload"></i> Uploader documents
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alertes -->
            <?php if ($is_admin && $dossier['est_historique']): ?>
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle"></i> Mode administrateur - Dossier historique</h6>
                    <p class="mb-0">Vous êtes en train de modifier un dossier historique importé.
                    Les modifications seront enregistrées dans l'historique du dossier.</p>
                    <?php if ($dossier['source_gps']): ?>
                        <small class="d-block mt-2">
                            <strong>Source GPS actuelle:</strong> <?php echo htmlspecialchars($dossier['source_gps']); ?>
                        </small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h6><i class="fas fa-exclamation-triangle"></i> Erreurs détectées :</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <!-- Informations générales -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informations générales</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="type_infrastructure" class="form-label">Type d'infrastructure <span class="text-danger">*</span></label>
                                    <select class="form-select" id="type_infrastructure" name="type_infrastructure" required onchange="updateSousTypes()">
                                        <option value="">Choisir un type</option>
                                        <option value="station_service" <?php echo $dossier['type_infrastructure'] === 'station_service' ? 'selected' : ''; ?>>Station-service</option>
                                        <option value="point_consommateur" <?php echo $dossier['type_infrastructure'] === 'point_consommateur' ? 'selected' : ''; ?>>Point consommateur</option>
                                        <option value="depot_gpl" <?php echo $dossier['type_infrastructure'] === 'depot_gpl' ? 'selected' : ''; ?>>Dépôt GPL</option>
                                        <option value="centre_emplisseur" <?php echo $dossier['type_infrastructure'] === 'centre_emplisseur' ? 'selected' : ''; ?>>Centre emplisseur</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sous_type" class="form-label">Nature de la demande <span class="text-danger">*</span></label>
                                    <select class="form-select" id="sous_type" name="sous_type" required>
                                        <option value="">Choisir une nature</option>
                                        <option value="implantation" <?php echo $dossier['sous_type'] === 'implantation' ? 'selected' : ''; ?>>Implantation</option>
                                        <option value="reprise" <?php echo $dossier['sous_type'] === 'reprise' ? 'selected' : ''; ?>>Reprise</option>
                                        <option value="remodelage" <?php echo $dossier['sous_type'] === 'remodelage' ? 'selected' : ''; ?>>Remodelage</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informations demandeur -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user"></i> Informations du demandeur</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nom_demandeur" class="form-label">Nom/Raison sociale <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nom_demandeur" name="nom_demandeur"
                                           value="<?php echo safeHtml($dossier['nom_demandeur']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_demandeur" class="form-label">Personne de contact</label>
                                    <input type="text" class="form-control" id="contact_demandeur" name="contact_demandeur"
                                           value="<?php echo safeHtml($dossier['contact_demandeur']); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telephone_demandeur" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="telephone_demandeur" name="telephone_demandeur"
                                           value="<?php echo safeHtml($dossier['telephone_demandeur']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email_demandeur" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email_demandeur" name="email_demandeur"
                                           value="<?php echo safeHtml($dossier['email_demandeur']); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="adresse_precise" class="form-label">Adresse précise</label>
                            <textarea class="form-control" id="adresse_precise" name="adresse_precise" rows="2" placeholder="Adresse complète de l'infrastructure..."><?php echo safeHtml($dossier['adresse_precise']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Localisation -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-map-marker-alt"></i> Localisation</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="region" class="form-label">Région <span class="text-danger">*</span></label>
                                    <select class="form-select" id="region" name="region" required>
                                        <option value="">Choisir une région</option>
                                        <?php foreach (getRegions() as $region): ?>
                                        <option value="<?php echo htmlspecialchars($region); ?>"
                                                <?php echo $dossier['region'] === $region ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($region); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="departement" class="form-label">Département</label>
                                    <input type="text" class="form-control" id="departement" name="departement"
                                           placeholder="Ex: Mfoundi, Wouri, Menoua..."
                                           value="<?php echo safeHtml($dossier['departement']); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ville" class="form-label">Ville <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ville" name="ville"
                                           value="<?php echo safeHtml($dossier['ville']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="arrondissement" class="form-label">Arrondissement</label>
                                    <input type="text" class="form-control" id="arrondissement" name="arrondissement"
                                           value="<?php echo safeHtml($dossier['arrondissement']); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="quartier" class="form-label">Quartier</label>
                                    <input type="text" class="form-control" id="quartier" name="quartier"
                                           value="<?php echo safeHtml($dossier['quartier']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="zone_type" class="form-label">Type de zone</label>
                                    <select class="form-select" id="zone_type" name="zone_type">
                                        <option value="urbaine" <?php echo ($dossier['zone_type'] ?? 'urbaine') === 'urbaine' ? 'selected' : ''; ?>>Zone urbaine</option>
                                        <option value="rurale" <?php echo ($dossier['zone_type'] ?? '') === 'rurale' ? 'selected' : ''; ?>>Zone rurale</option>
                                    </select>
                                    <small class="form-text text-muted">Pour les statistiques urbain/rural</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="lieu_dit" class="form-label">Lieu-dit</label>
                                    <input type="text" class="form-control" id="lieu_dit" name="lieu_dit"
                                           value="<?php echo safeHtml($dossier['lieu_dit']); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="coordonnees_gps" class="form-label">Coordonnées GPS</label>
                                    <input type="text" class="form-control" id="coordonnees_gps" name="coordonnees_gps"
                                           placeholder="Ex: 3.8647° N, 11.5122° E"
                                           value="<?php echo safeHtml($dossier['coordonnees_gps']); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="annee_mise_en_service" class="form-label">Année de mise en service</label>
                                    <input type="number" class="form-control" id="annee_mise_en_service" name="annee_mise_en_service"
                                           value="<?php echo safeHtml($dossier['annee_mise_en_service']); ?>"
                                           placeholder="Ex: 2020"
                                           min="1950"
                                           max="<?php echo date('Y'); ?>">
                                    <small class="form-text text-muted">Pour statistiques</small>
                                </div>
                            </div>
                        </div>
                        <!-- Champ adresse_precise déjà affiché plus haut dans "Informations du demandeur" -->
                    </div>
                </div>

                <!-- Informations spécifiques -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cogs"></i> Informations spécifiques</h5>
                    </div>
                    <div class="card-body">
                        <!-- Champs spécifiques selon le type -->
                        <div id="champs-station-service" style="<?php echo $dossier['type_infrastructure'] === 'station_service' ? '' : 'display:none;'; ?>">
                            <div class="mb-3">
                                <label for="operateur_proprietaire" class="form-label">Opérateur propriétaire</label>
                                <input type="text" class="form-control" id="operateur_proprietaire" name="operateur_proprietaire"
                                       value="<?php echo safeHtml($dossier['operateur_proprietaire']); ?>">
                            </div>
                        </div>

                        <div id="champs-point-consommateur" style="<?php echo $dossier['type_infrastructure'] === 'point_consommateur' ? '' : 'display:none;'; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="entreprise_beneficiaire" class="form-label">Entreprise bénéficiaire</label>
                                        <input type="text" class="form-control" id="entreprise_beneficiaire" name="entreprise_beneficiaire"
                                               value="<?php echo safeHtml($dossier['entreprise_beneficiaire']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="contrat_livraison" class="form-label">Contrat de livraison</label>
                                        <input type="text" class="form-control" id="contrat_livraison" name="contrat_livraison"
                                               value="<?php echo safeHtml($dossier['contrat_livraison']); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="champs-depot-gpl" style="<?php echo $dossier['type_infrastructure'] === 'depot_gpl' ? '' : 'display:none;'; ?>">
                            <div class="mb-3">
                                <label for="entreprise_installatrice" class="form-label">Entreprise installatrice</label>
                                <input type="text" class="form-control" id="entreprise_installatrice" name="entreprise_installatrice"
                                       value="<?php echo safeHtml($dossier['entreprise_installatrice']); ?>">
                            </div>
                        </div>

                        <div id="champs-centre-emplisseur" style="<?php echo $dossier['type_infrastructure'] === 'centre_emplisseur' ? '' : 'display:none;'; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="operateur_gaz" class="form-label">Opérateur de gaz</label>
                                        <input type="text" class="form-control" id="operateur_gaz" name="operateur_gaz"
                                               value="<?php echo safeHtml($dossier['operateur_gaz']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="entreprise_constructrice" class="form-label">Entreprise constructrice</label>
                                        <input type="text" class="form-control" id="entreprise_constructrice" name="entreprise_constructrice"
                                               value="<?php echo safeHtml($dossier['entreprise_constructrice']); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="capacite_enfutage" class="form-label">Capacité d'enfûtage (bouteilles/jour)</label>
                                <input type="number" class="form-control" id="capacite_enfutage" name="capacite_enfutage"
                                       value="<?php echo safeHtml($dossier['capacite_enfutage']); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Boutons -->
                <div class="d-flex justify-content-between mb-4">
                    <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateSousTypes() {
    // Fonction pour afficher/masquer les champs spécifiques
    const type = document.getElementById('type_infrastructure').value;
    const sousTypeSelect = document.getElementById('sous_type');
    const optionRemodelage = sousTypeSelect.querySelector('option[value="remodelage"]');

    // Gérer l'option remodelage
    if (type === 'station_service') {
        // Afficher l'option remodelage pour les stations-services
        optionRemodelage.style.display = 'block';
        optionRemodelage.disabled = false;
    } else {
        // Masquer et désactiver l'option remodelage pour les autres types
        optionRemodelage.style.display = 'none';
        optionRemodelage.disabled = true;
        // Réinitialiser la sélection si remodelage était sélectionné
        if (sousTypeSelect.value === 'remodelage') {
            sousTypeSelect.value = '';
        }
    }

    // Masquer tous les champs spécifiques
    document.getElementById('champs-station-service').style.display = 'none';
    document.getElementById('champs-point-consommateur').style.display = 'none';
    document.getElementById('champs-depot-gpl').style.display = 'none';
    document.getElementById('champs-centre-emplisseur').style.display = 'none';

    // Afficher les champs correspondants
    if (type === 'station_service') {
        document.getElementById('champs-station-service').style.display = 'block';
    } else if (type === 'point_consommateur') {
        document.getElementById('champs-point-consommateur').style.display = 'block';
    } else if (type === 'depot_gpl') {
        document.getElementById('champs-depot-gpl').style.display = 'block';
    } else if (type === 'centre_emplisseur') {
        document.getElementById('champs-centre-emplisseur').style.display = 'block';
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
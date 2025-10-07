<?php
// Création de dossier - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

// Seul le Chef de Service SDTD peut créer les dossiers
requireRole('chef_service');

$page_title = 'Créer un nouveau dossier';
$errors = [];
$success = false;

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
        $sous_type = $_POST['sous_type'] ?? '';

        // Le remodelage n'est applicable qu'aux stations-services
        if ($sous_type === 'remodelage' && $type !== 'station_service') {
            $errors[] = 'Le remodelage n\'est applicable qu\'aux stations-services';
        }

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

        if ($type === 'centre_emplisseur') {
            if (empty($_POST['operateur_gaz']) && empty($_POST['entreprise_constructrice'])) {
                $errors[] = 'Un opérateur de gaz OU une entreprise constructrice est requis pour un centre emplisseur';
            }
        }

        if (empty($errors)) {
            $data = [
                'type_infrastructure' => sanitize($_POST['type_infrastructure']),
                'sous_type' => sanitize($_POST['sous_type']),
                'nom_demandeur' => sanitize($_POST['nom_demandeur']),
                'contact_demandeur' => sanitize($_POST['contact_demandeur']),
                'telephone_demandeur' => sanitize($_POST['telephone_demandeur']),
                'email_demandeur' => sanitize($_POST['email_demandeur']),
                'region' => sanitize($_POST['region']),
                'departement' => sanitize($_POST['departement']),
                'ville' => sanitize($_POST['ville']),
                'arrondissement' => sanitize($_POST['arrondissement']),
                'quartier' => sanitize($_POST['quartier']),
                'lieu_dit' => sanitize($_POST['lieu_dit']),
                'coordonnees_gps' => sanitize($_POST['coordonnees_gps']),
                'operateur_proprietaire' => sanitize($_POST['operateur_proprietaire']),
                'entreprise_beneficiaire' => sanitize($_POST['entreprise_beneficiaire']),
                'contrat_livraison' => sanitize($_POST['contrat_livraison']),
                'entreprise_installatrice' => sanitize($_POST['entreprise_installatrice']),
                'operateur_gaz' => sanitize($_POST['operateur_gaz']),
                'entreprise_constructrice' => sanitize($_POST['entreprise_constructrice']),
                'capacite_enfutage' => sanitize($_POST['capacite_enfutage']),
                'user_id' => $_SESSION['user_id']
            ];

            $dossier_id = createDossier($data);

            if ($dossier_id) {
                redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
                        'Dossier créé avec succès', 'success');
            } else {
                $errors[] = 'Erreur lors de la création du dossier';
            }
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-plus-circle"></i> Créer un nouveau dossier
                </h5>
            </div>

            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST" id="dossierForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <!-- Type d'infrastructure -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="type_infrastructure" class="form-label">Type d'infrastructure *</label>
                            <select class="form-select" id="type_infrastructure" name="type_infrastructure" required>
                                <option value="">Sélectionnez un type</option>
                                <option value="station_service" <?php echo ($_POST['type_infrastructure'] ?? '') === 'station_service' ? 'selected' : ''; ?>>
                                    Station-service
                                </option>
                                <option value="point_consommateur" <?php echo ($_POST['type_infrastructure'] ?? '') === 'point_consommateur' ? 'selected' : ''; ?>>
                                    Point consommateur
                                </option>
                                <option value="depot_gpl" <?php echo ($_POST['type_infrastructure'] ?? '') === 'depot_gpl' ? 'selected' : ''; ?>>
                                    Dépôt GPL
                                </option>
                                <option value="centre_emplisseur" <?php echo ($_POST['type_infrastructure'] ?? '') === 'centre_emplisseur' ? 'selected' : ''; ?>>
                                    Centre emplisseur
                                </option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="sous_type" class="form-label">Nature de la demande *</label>
                            <select class="form-select" id="sous_type" name="sous_type" required>
                                <option value="">Sélectionnez</option>
                                <option value="implantation" <?php echo ($_POST['sous_type'] ?? '') === 'implantation' ? 'selected' : ''; ?>>
                                    Implantation
                                </option>
                                <option value="reprise" <?php echo ($_POST['sous_type'] ?? '') === 'reprise' ? 'selected' : ''; ?>>
                                    Reprise
                                </option>
                                <option value="remodelage" <?php echo ($_POST['sous_type'] ?? '') === 'remodelage' ? 'selected' : ''; ?>>
                                    Remodelage
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Informations du demandeur -->
                    <h6 class="text-primary mt-4 mb-3">
                        <i class="fas fa-user"></i> Informations du demandeur
                    </h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nom_demandeur" class="form-label">Nom du demandeur *</label>
                            <input type="text" class="form-control" id="nom_demandeur" name="nom_demandeur"
                                   value="<?php echo sanitize($_POST['nom_demandeur'] ?? ''); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="contact_demandeur" class="form-label">Personne de contact</label>
                            <input type="text" class="form-control" id="contact_demandeur" name="contact_demandeur"
                                   value="<?php echo sanitize($_POST['contact_demandeur'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="telephone_demandeur" class="form-label">Téléphone</label>
                            <input type="text" class="form-control" id="telephone_demandeur" name="telephone_demandeur"
                                   value="<?php echo sanitize($_POST['telephone_demandeur'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="email_demandeur" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email_demandeur" name="email_demandeur"
                                   value="<?php echo sanitize($_POST['email_demandeur'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Localisation -->
                    <h6 class="text-primary mt-4 mb-3">
                        <i class="fas fa-map-marker-alt"></i> Localisation
                    </h6>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="region" class="form-label">Région</label>
                            <input type="text" class="form-control" id="region" name="region"
                                   placeholder="Ex: Centre, Littoral, Ouest..."
                                   value="<?php echo sanitize($_POST['region'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="departement" class="form-label">Département</label>
                            <input type="text" class="form-control" id="departement" name="departement"
                                   placeholder="Ex: Mfoundi, Wouri, Menoua..."
                                   value="<?php echo sanitize($_POST['departement'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="arrondissement" class="form-label">Arrondissement</label>
                            <input type="text" class="form-control" id="arrondissement" name="arrondissement"
                                   placeholder="Ex: Yaoundé 1er, Douala 3ème..."
                                   value="<?php echo sanitize($_POST['arrondissement'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="ville" class="form-label">Ville</label>
                            <input type="text" class="form-control" id="ville" name="ville"
                                   placeholder="Ex: Yaoundé, Douala, Bafoussam..."
                                   value="<?php echo sanitize($_POST['ville'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="quartier" class="form-label">Quartier</label>
                            <input type="text" class="form-control" id="quartier" name="quartier"
                                   placeholder="Ex: Melen, Bonanjo, Tsinga..."
                                   value="<?php echo sanitize($_POST['quartier'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="lieu_dit" class="form-label">Lieu-dit</label>
                            <textarea class="form-control" id="lieu_dit" name="lieu_dit" rows="2"
                                     placeholder="Description précise du lieu d'implantation"><?php echo sanitize($_POST['lieu_dit'] ?? ''); ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label for="coordonnees_gps" class="form-label">Coordonnées GPS</label>
                            <input type="text" class="form-control" id="coordonnees_gps" name="coordonnees_gps"
                                   value="<?php echo sanitize($_POST['coordonnees_gps'] ?? ''); ?>"
                                   placeholder="Ex: 3.848,11.502">
                        </div>
                    </div>

                    <!-- Champs spécifiques selon le type -->
                    <div id="champs_specifiques">
                        <!-- Station-service -->
                        <div class="champ-type" data-type="station_service" style="display: none;">
                            <h6 class="text-primary mt-4 mb-3">
                                <i class="fas fa-gas-pump"></i> Informations Station-service
                            </h6>
                            <div class="mb-3">
                                <label for="operateur_proprietaire" class="form-label">Opérateur propriétaire *</label>
                                <input type="text" class="form-control" id="operateur_proprietaire" name="operateur_proprietaire"
                                       value="<?php echo sanitize($_POST['operateur_proprietaire'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Point consommateur -->
                        <div class="champ-type" data-type="point_consommateur" style="display: none;">
                            <h6 class="text-primary mt-4 mb-3">
                                <i class="fas fa-industry"></i> Informations Point Consommateur
                            </h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="entreprise_beneficiaire" class="form-label">Entreprise bénéficiaire *</label>
                                    <input type="text" class="form-control" id="entreprise_beneficiaire" name="entreprise_beneficiaire"
                                           value="<?php echo sanitize($_POST['entreprise_beneficiaire'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="contrat_livraison" class="form-label">Contrat de livraison</label>
                                    <textarea class="form-control" id="contrat_livraison" name="contrat_livraison" rows="2"><?php echo sanitize($_POST['contrat_livraison'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Dépôt GPL -->
                        <div class="champ-type" data-type="depot_gpl" style="display: none;">
                            <h6 class="text-primary mt-4 mb-3">
                                <i class="fas fa-fire"></i> Informations Dépôt GPL
                            </h6>
                            <div class="mb-3">
                                <label for="entreprise_installatrice" class="form-label">Entreprise installatrice *</label>
                                <input type="text" class="form-control" id="entreprise_installatrice" name="entreprise_installatrice"
                                       value="<?php echo sanitize($_POST['entreprise_installatrice'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Centre Emplisseur -->
                        <div class="champ-type" data-type="centre_emplisseur" style="display: none;">
                            <h6 class="text-primary mt-4 mb-3">
                                <i class="fas fa-industry"></i> Informations Centre Emplisseur
                            </h6>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Note :</strong> Renseignez au moins un des deux champs suivants (Opérateur de gaz OU Entreprise constructrice)
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="operateur_gaz" class="form-label">Opérateur de gaz</label>
                                        <input type="text" class="form-control" id="operateur_gaz" name="operateur_gaz"
                                               placeholder="Nom de l'opérateur de gaz"
                                               value="<?php echo sanitize($_POST['operateur_gaz'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="entreprise_constructrice" class="form-label">Entreprise constructrice</label>
                                        <input type="text" class="form-control" id="entreprise_constructrice" name="entreprise_constructrice"
                                               placeholder="Nom de l'entreprise constructrice"
                                               value="<?php echo sanitize($_POST['entreprise_constructrice'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="capacite_enfutage" class="form-label">Capacité d'enfûtage (bouteilles/jour)</label>
                                <input type="number" class="form-control" id="capacite_enfutage" name="capacite_enfutage"
                                       placeholder="Ex: 1000"
                                       value="<?php echo sanitize($_POST['capacite_enfutage'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?php echo url('modules/dossiers/list.php'); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Créer le dossier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Afficher/masquer les champs spécifiques
document.getElementById('type_infrastructure').addEventListener('change', function() {
    const type = this.value;
    const champsSpecifiques = document.querySelectorAll('.champ-type');
    const sousTypeSelect = document.getElementById('sous_type');
    const optionRemodelage = sousTypeSelect.querySelector('option[value="remodelage"]');

    // Masquer tous les champs
    champsSpecifiques.forEach(champ => {
        champ.style.display = 'none';
        // Désactiver les champs requis
        const required = champ.querySelectorAll('[required]');
        required.forEach(field => field.required = false);
    });

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

    // Afficher et activer les champs du type sélectionné
    if (type) {
        const champType = document.querySelector(`[data-type="${type}"]`);
        if (champType) {
            champType.style.display = 'block';
            const required = champType.querySelectorAll('input[data-required="true"]');
            required.forEach(field => field.required = true);
        }
    }
});

// Initialiser au chargement
document.getElementById('type_infrastructure').dispatchEvent(new Event('change'));
</script>

<?php require_once '../../includes/footer.php'; ?>
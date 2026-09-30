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
                'type_infrastructure' => cleanInput($_POST['type_infrastructure']),
                'sous_type' => cleanInput($_POST['sous_type']),
                'nom_demandeur' => cleanInput($_POST['nom_demandeur']),
                'contact_demandeur' => cleanInput($_POST['contact_demandeur']),
                'telephone_demandeur' => cleanInput($_POST['telephone_demandeur']),
                'email_demandeur' => cleanInput($_POST['email_demandeur']),
                'region' => cleanInput($_POST['region']),
                'departement' => cleanInput($_POST['departement']),
                'ville' => cleanInput($_POST['ville']),
                'arrondissement' => cleanInput($_POST['arrondissement']),
                'quartier' => cleanInput($_POST['quartier']),
                'zone_type' => cleanInput($_POST['zone_type'] ?? 'urbaine'),
                'lieu_dit' => cleanInput($_POST['lieu_dit']),
                'coordonnees_gps' => cleanInput($_POST['coordonnees_gps']),
                'annee_mise_en_service' => !empty($_POST['annee_mise_en_service']) ? intval($_POST['annee_mise_en_service']) : null,
                'operateur_proprietaire' => cleanInput($_POST['operateur_proprietaire']),
                'entreprise_beneficiaire' => cleanInput($_POST['entreprise_beneficiaire']),
                'contrat_livraison' => cleanInput($_POST['contrat_livraison']),
                'entreprise_installatrice' => cleanInput($_POST['entreprise_installatrice']),
                'operateur_gaz' => cleanInput($_POST['operateur_gaz']),
                'entreprise_constructrice' => cleanInput($_POST['entreprise_constructrice']),
                'capacite_enfutage' => cleanInput($_POST['capacite_enfutage']),
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

require_once '../../includes/ui.php';
require_once '../../includes/header.php';

$v = function ($champ, $defaut = '') { return sanitize($_POST[$champ] ?? $defaut); };
$types = [
    'station_service' => ['Station-service', 'fa-gas-pump', 'Opérateur propriétaire'],
    'point_consommateur' => ['Point consommateur', 'fa-industry', 'Opérateur, entreprise bénéficiaire et contrat de livraison'],
    'depot_gpl' => ['Dépôt GPL', 'fa-fire-flame-simple', 'Entreprise installatrice'],
    'centre_emplisseur' => ['Centre emplisseur', 'fa-gas-pump', 'Opérateur de gaz ou entreprise constructrice'],
];
$natures = ['implantation' => 'Implantation', 'reprise' => 'Reprise', 'remodelage' => 'Remodelage'];
$regions = ['Adamaoua', 'Centre', 'Est', 'Extrême-Nord', 'Littoral', 'Nord', 'Nord-Ouest', 'Ouest', 'Sud', 'Sud-Ouest'];
$region_saisie = $_POST['region'] ?? '';

echo uiPageHeader(
    'Nouveau dossier',
    'Demande d\'implantation ou de reprise d\'une infrastructure pétrolière',
    [['label' => 'Tableau de bord', 'url' => url('dashboard.php')], ['label' => 'Dossiers', 'url' => url('modules/dossiers/list.php')], ['label' => 'Nouveau dossier']]
);
?>

<?php if (!empty($errors)): ?>
<div class="alert-banner phase-danger" role="alert">
    <i class="fas fa-circle-exclamation alert-banner-icon" aria-hidden="true"></i>
    <div class="alert-banner-body">
        <strong>Le dossier n'a pas été créé :</strong>
        <ul class="mb-0 mt-1"><?php foreach ($errors as $error): ?><li><?php echo sanitize($error); ?></li><?php endforeach; ?></ul>
    </div>
</div>
<?php endif; ?>

<form method="POST" id="dossierForm" novalidate>
    <?php echo csrfField(); ?>
    <div class="row g-4">
        <div class="col-xl-8">

            <!-- 1. Type et nature -->
            <section class="card form-section mb-4">
                <div class="card-header"><h2 class="card-title-sm"><span class="form-step">1</span> Type d'infrastructure et nature de la demande</h2></div>
                <div class="card-body">
                    <fieldset class="mb-4">
                        <legend class="form-label">Type d'infrastructure <span class="req" aria-hidden="true">*</span></legend>
                        <div class="row row-cols-1 row-cols-sm-2 g-2">
                            <?php foreach ($types as $code => $t): ?>
                            <div class="col">
                                <input class="btn-check" type="radio" name="type_infrastructure" id="type_<?php echo $code; ?>" value="<?php echo $code; ?>" required <?php echo ($_POST['type_infrastructure'] ?? '') === $code ? 'checked' : ''; ?>>
                                <label class="choice-tile" for="type_<?php echo $code; ?>">
                                    <i class="fas <?php echo $t[1]; ?>" aria-hidden="true"></i>
                                    <span><strong class="d-block"><?php echo $t[0]; ?></strong><span class="small text-muted-sgdi"><?php echo $t[2]; ?></span></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend class="form-label">Nature de la demande <span class="req" aria-hidden="true">*</span></legend>
                        <div class="btn-group flex-wrap" role="group">
                            <?php foreach ($natures as $code => $libelle): ?>
                            <input class="btn-check" type="radio" name="sous_type" id="nature_<?php echo $code; ?>" value="<?php echo $code; ?>" required <?php echo ($_POST['sous_type'] ?? '') === $code ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-primary" for="nature_<?php echo $code; ?>"><?php echo $libelle; ?></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text" id="aide-remodelage">Le remodelage ne concerne que les stations-service.</div>
                    </fieldset>
                </div>
            </section>

            <!-- 2. Demandeur -->
            <section class="card form-section mb-4">
                <div class="card-header"><h2 class="card-title-sm"><span class="form-step">2</span> Demandeur</h2></div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label for="nom_demandeur" class="form-label">Nom ou raison sociale <span class="req" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control" id="nom_demandeur" name="nom_demandeur" value="<?php echo $v('nom_demandeur'); ?>" required autocomplete="organization">
                    </div>
                    <div class="col-md-6">
                        <label for="contact_demandeur" class="form-label">Personne de contact</label>
                        <input type="text" class="form-control" id="contact_demandeur" name="contact_demandeur" value="<?php echo $v('contact_demandeur'); ?>" autocomplete="name">
                    </div>
                    <div class="col-md-6">
                        <label for="telephone_demandeur" class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" id="telephone_demandeur" name="telephone_demandeur" value="<?php echo $v('telephone_demandeur'); ?>" placeholder="6XX XX XX XX" autocomplete="tel">
                    </div>
                    <div class="col-md-6">
                        <label for="email_demandeur" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email_demandeur" name="email_demandeur" value="<?php echo $v('email_demandeur'); ?>" autocomplete="email">
                        <div class="form-text">Utilisé pour informer le demandeur de l'avancement.</div>
                    </div>
                </div>
            </section>

            <!-- 3. Localisation -->
            <section class="card form-section mb-4">
                <div class="card-header"><h2 class="card-title-sm"><span class="form-step">3</span> Localisation</h2></div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label for="region" class="form-label">Région</label>
                        <select class="form-select" id="region" name="region">
                            <option value="">Sélectionnez une région</option>
                            <?php foreach ($regions as $r): ?>
                            <option <?php echo $region_saisie === $r ? 'selected' : ''; ?>><?php echo $r; ?></option>
                            <?php endforeach; ?>
                            <?php if ($region_saisie !== '' && !in_array($region_saisie, $regions, true)): ?>
                            <option selected><?php echo sanitize($region_saisie); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="departement" class="form-label">Département</label>
                        <input type="text" class="form-control" id="departement" name="departement" value="<?php echo $v('departement'); ?>" placeholder="Ex. Mfoundi, Wouri, Menoua">
                    </div>
                    <div class="col-md-6">
                        <label for="arrondissement" class="form-label">Arrondissement</label>
                        <input type="text" class="form-control" id="arrondissement" name="arrondissement" value="<?php echo $v('arrondissement'); ?>" placeholder="Ex. Yaoundé 1er, Douala 3e">
                    </div>
                    <div class="col-md-6">
                        <label for="ville" class="form-label">Ville</label>
                        <input type="text" class="form-control" id="ville" name="ville" value="<?php echo $v('ville'); ?>" placeholder="Ex. Yaoundé, Douala, Bafoussam">
                    </div>
                    <div class="col-md-6">
                        <label for="quartier" class="form-label">Quartier</label>
                        <input type="text" class="form-control" id="quartier" name="quartier" value="<?php echo $v('quartier'); ?>" placeholder="Ex. Melen, Bonanjo, Tsinga">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label d-block">Type de zone</label>
                        <div class="btn-group" role="group" aria-label="Type de zone">
                            <?php foreach (['urbaine' => 'Zone urbaine', 'rurale' => 'Zone rurale'] as $code => $libelle): ?>
                            <input class="btn-check" type="radio" name="zone_type" id="zone_<?php echo $code; ?>" value="<?php echo $code; ?>" <?php echo ($_POST['zone_type'] ?? 'urbaine') === $code ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-secondary" for="zone_<?php echo $code; ?>"><?php echo $libelle; ?></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">Distance minimale entre stations : 500 m en zone urbaine, 400 m en zone rurale.</div>
                    </div>
                    <div class="col-12">
                        <label for="lieu_dit" class="form-label">Lieu-dit</label>
                        <textarea class="form-control" id="lieu_dit" name="lieu_dit" rows="2" placeholder="Description précise du lieu d'implantation"><?php echo $v('lieu_dit'); ?></textarea>
                    </div>
                    <div class="col-md-8">
                        <label for="coordonnees_gps" class="form-label">Coordonnées GPS</label>
                        <input type="text" class="form-control" id="coordonnees_gps" name="coordonnees_gps" value="<?php echo $v('coordonnees_gps'); ?>" placeholder="Latitude, longitude — ex. 3.848, 11.502" inputmode="decimal" aria-describedby="aide-gps">
                        <div class="form-text" id="aide-gps">Format décimal. Vous pourrez les compléter plus tard depuis la fiche du dossier.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="annee_mise_en_service" class="form-label">Année de mise en service</label>
                        <input type="number" class="form-control" id="annee_mise_en_service" name="annee_mise_en_service" value="<?php echo $v('annee_mise_en_service'); ?>" placeholder="Ex. 2020" min="1950" max="<?php echo date('Y'); ?>">
                        <div class="form-text">Pour les dossiers historiques.</div>
                    </div>
                </div>
            </section>

            <!-- 4. Informations propres au type -->
            <section class="card form-section mb-4" id="section-specifique">
                <div class="card-header"><h2 class="card-title-sm"><span class="form-step">4</span> Informations propres au type d'infrastructure</h2></div>
                <div class="card-body">
                    <p class="text-muted-sgdi mb-0" id="specifique-vide">Choisissez d'abord un type d'infrastructure (étape 1).</p>

                    <div class="champ-type" data-type="station_service" hidden>
                        <label for="operateur_proprietaire" class="form-label">Opérateur propriétaire <span class="req" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control" id="operateur_proprietaire" name="operateur_proprietaire" value="<?php echo $v('operateur_proprietaire'); ?>" data-required="true">
                    </div>

                    <div class="champ-type row g-3" data-type="point_consommateur" hidden>
                        <div class="col-md-6">
                            <label for="entreprise_beneficiaire" class="form-label">Entreprise bénéficiaire <span class="req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="entreprise_beneficiaire" name="entreprise_beneficiaire" value="<?php echo $v('entreprise_beneficiaire'); ?>" data-required="true">
                        </div>
                        <div class="col-md-6">
                            <label for="contrat_livraison" class="form-label">Contrat de livraison</label>
                            <textarea class="form-control" id="contrat_livraison" name="contrat_livraison" rows="2" placeholder="Références du contrat avec l'opérateur"><?php echo $v('contrat_livraison'); ?></textarea>
                        </div>
                    </div>

                    <div class="champ-type" data-type="depot_gpl" hidden>
                        <label for="entreprise_installatrice" class="form-label">Entreprise installatrice <span class="req" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control" id="entreprise_installatrice" name="entreprise_installatrice" value="<?php echo $v('entreprise_installatrice'); ?>" data-required="true">
                    </div>

                    <div class="champ-type row g-3" data-type="centre_emplisseur" hidden>
                        <div class="col-12"><div class="form-text mt-0">Renseignez au moins l'un des deux : opérateur de gaz ou entreprise constructrice.</div></div>
                        <div class="col-md-6">
                            <label for="operateur_gaz" class="form-label">Opérateur de gaz</label>
                            <input type="text" class="form-control" id="operateur_gaz" name="operateur_gaz" value="<?php echo $v('operateur_gaz'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="entreprise_constructrice" class="form-label">Entreprise constructrice</label>
                            <input type="text" class="form-control" id="entreprise_constructrice" name="entreprise_constructrice" value="<?php echo $v('entreprise_constructrice'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="capacite_enfutage" class="form-label">Capacité d'enfûtage (bouteilles/jour)</label>
                            <input type="number" class="form-control" id="capacite_enfutage" name="capacite_enfutage" value="<?php echo $v('capacite_enfutage'); ?>" placeholder="Ex. 1000" min="0">
                        </div>
                    </div>
                </div>
            </section>

            <div class="form-actions">
                <a href="<?php echo url('modules/dossiers/list.php'); ?>" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Annuler</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Créer le dossier</button>
            </div>
        </div>

        <!-- Aide -->
        <aside class="col-xl-4">
            <div class="card form-aside">
                <div class="card-header"><h2 class="card-title-sm"><i class="fas fa-route me-1" aria-hidden="true"></i> Et ensuite ?</h2></div>
                <div class="card-body">
                    <ol class="timeline-sgdi">
                        <li class="phase-preparation"><span class="tl-dot"></span><p class="tl-title">Pièces du dossier</p><p class="tl-text text-muted-sgdi">Téléverser les documents exigés pour ce type.</p></li>
                        <li class="phase-preparation"><span class="tl-dot"></span><p class="tl-title">Commission</p><p class="tl-text text-muted-sgdi">Nommer 3 membres : cadre DPPG, cadre DAJ, chef de commission.</p></li>
                        <li class="phase-paiement"><span class="tl-dot"></span><p class="tl-title">Note de frais et paiement</p><p class="tl-text text-muted-sgdi">Le Billeteur enregistre le paiement ; vous êtes notifié.</p></li>
                        <li class="phase-instruction"><span class="tl-dot"></span><p class="tl-title">Instruction</p><p class="tl-text text-muted-sgdi">Analyse juridique, contrôle de complétude, inspection.</p></li>
                        <li class="phase-visa"><span class="tl-dot"></span><p class="tl-title">Visas et décision</p><p class="tl-text text-muted-sgdi">Chef de Service, Sous-Directeur, Directeur, puis Ministre.</p></li>
                    </ol>
                </div>
            </div>
        </aside>
    </div>
</form>

<script>
(function () {
    'use strict';
    var form = document.getElementById('dossierForm');
    var radiosType = form.querySelectorAll('input[name="type_infrastructure"]');
    var remodelage = document.getElementById('nature_remodelage');

    function typeChoisi() { var r = form.querySelector('input[name="type_infrastructure"]:checked'); return r ? r.value : ''; }

    // Affiche les champs du type choisi et rend obligatoires ceux qui le sont pour ce type
    function majType() {
        var type = typeChoisi();
        form.querySelectorAll('.champ-type').forEach(function (bloc) {
            var actif = bloc.getAttribute('data-type') === type;
            bloc.hidden = !actif;
            bloc.querySelectorAll('[data-required="true"]').forEach(function (champ) { champ.required = actif; });
        });
        document.getElementById('specifique-vide').hidden = !!type;
        // Le remodelage ne concerne que les stations-service
        var remodelageOk = !type || type === 'station_service';
        remodelage.disabled = !remodelageOk;
        if (!remodelageOk && remodelage.checked) remodelage.checked = false;
    }
    radiosType.forEach(function (r) { r.addEventListener('change', majType); });
    majType();

    // Format GPS « latitude, longitude » dans les limites du Cameroun
    var gps = document.getElementById('coordonnees_gps');
    gps.addEventListener('input', function () {
        var val = gps.value.trim(), m = val.match(/^(-?\d+(?:[.,]\d+)?)\s*[,; ]\s*(-?\d+(?:[.,]\d+)?)$/);
        var ok = !val || (m && +m[1].replace(',', '.') >= 1.5 && +m[1].replace(',', '.') <= 13.5 && +m[2].replace(',', '.') >= 8 && +m[2].replace(',', '.') <= 16.5);
        gps.setCustomValidity(ok ? '' : 'Format attendu : latitude, longitude au Cameroun (ex. 3.848, 11.502)');
        gps.classList.toggle('is-invalid', !ok);
    });

    // Validation du navigateur avec mise en évidence des champs à corriger
    form.addEventListener('submit', function (e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            form.classList.add('was-validated');
            var premier = form.querySelector(':invalid');
            if (premier) { premier.focus(); premier.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
        }
    });
})();
</script>

<?php require_once '../../includes/footer.php'; ?>

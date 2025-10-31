<?php
/**
 * Guide d'utilisation du module Import Historique
 */

require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

if (!peutImporterHistorique($_SESSION['user_role'])) {
    redirect(url('dashboard.php'), 'Vous n\'avez pas les permissions pour accéder à ce module', 'error');
}

$pageTitle = "Guide d'utilisation - Import Historique";
include '../../includes/header.php';
?>

<style>
.guide-section {
    background: white;
    border-left: 4px solid #007bff;
    padding: 20px;
    margin: 20px 0;
    border-radius: 0 8px 8px 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.guide-section.warning { border-left-color: #ffc107; }
.guide-section.success { border-left-color: #28a745; }
.guide-section.danger { border-left-color: #dc3545; }
.code-block {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
    margin: 10px 0;
    overflow-x: auto;
}
.table-formats { font-size: 0.9em; }
.table-formats th { background: #007bff; color: white; }
</style>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-book"></i> Guide d'utilisation - Import Historique</h2>
            <p class="text-muted">Instructions complètes pour importer les dossiers autorisés avant le SGDI</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-upload"></i> Aller à l'import
            </a>
            <a href="<?= url('dashboard.php') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Vue d'ensemble -->
    <div class="guide-section">
        <h3><i class="fas fa-info-circle"></i> Vue d'ensemble</h3>
        <p>Ce module permet d'importer les dossiers d'autorisation traités avant la mise en place du SGDI.</p>

        <div class="row mt-3">
            <div class="col-md-6">
                <div class="alert alert-info">
                    <h5><i class="fas fa-file-csv"></i> Templates disponibles</h5>
                    <ul>
                        <li><a href="download_template.php?type=station_service">template_import_stations_service.csv</a></li>
                        <li><a href="download_template.php?type=point_consommateur">template_import_points_consommateurs.csv</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Limites</h5>
                    <ul>
                        <li><strong>Format</strong>: CSV (`;`) ou Excel (.xlsx)</li>
                        <li><strong>Encodage</strong>: UTF-8</li>
                        <li><strong>Limite</strong>: 200 lignes max</li>
                        <li><strong>Taille</strong>: 5 MB max</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Colonnes obligatoires -->
    <div class="guide-section">
        <h3><i class="fas fa-table"></i> Colonnes du fichier CSV</h3>

        <h5 class="mt-3">Pour TOUS les types d'infrastructure:</h5>
        <table class="table table-formats table-bordered">
            <thead>
                <tr>
                    <th>Colonne</th>
                    <th>Description</th>
                    <th>Format</th>
                    <th>Obligatoire</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>numero_dossier</code></td>
                    <td>Numéro du dossier (laissez vide pour génération auto)</td>
                    <td>Texte</td>
                    <td><span class="badge badge-secondary">Non</span></td>
                </tr>
                <tr>
                    <td><code>type_infrastructure</code></td>
                    <td>Type exact (voir liste ci-dessous)</td>
                    <td>Texte</td>
                    <td><span class="badge badge-danger">Oui</span></td>
                </tr>
                <tr>
                    <td><code>nom_demandeur</code></td>
                    <td>Nom de la société opérateur</td>
                    <td>Texte</td>
                    <td><span class="badge badge-danger">Oui</span></td>
                </tr>
                <tr>
                    <td><code>region</code></td>
                    <td>Région du Cameroun</td>
                    <td>Texte</td>
                    <td><span class="badge badge-danger">Oui</span></td>
                </tr>
                <tr>
                    <td><code>ville</code></td>
                    <td>Ville ou localité</td>
                    <td>Texte</td>
                    <td><span class="badge badge-danger">Oui</span></td>
                </tr>
                <tr>
                    <td><code>latitude</code></td>
                    <td>Coordonnée latitude</td>
                    <td>Nombre décimal</td>
                    <td><span class="badge badge-secondary">Non</span></td>
                </tr>
                <tr>
                    <td><code>longitude</code></td>
                    <td>Coordonnée longitude</td>
                    <td>Nombre décimal</td>
                    <td><span class="badge badge-secondary">Non</span></td>
                </tr>
                <tr>
                    <td><code>date_autorisation</code></td>
                    <td>Date de la décision ministérielle</td>
                    <td>JJ/MM/AAAA</td>
                    <td><span class="badge badge-danger">Oui</span></td>
                </tr>
                <tr>
                    <td><code>numero_decision</code></td>
                    <td>Numéro de la décision ministérielle</td>
                    <td>Texte</td>
                    <td><span class="badge badge-danger">Oui</span></td>
                </tr>
                <tr>
                    <td><code>observations</code></td>
                    <td>Remarques éventuelles</td>
                    <td>Texte</td>
                    <td><span class="badge badge-secondary">Non</span></td>
                </tr>
            </tbody>
        </table>

        <div class="alert alert-info mt-3">
            <h6><i class="fas fa-plus-circle"></i> Colonnes supplémentaires pour POINTS CONSOMMATEURS:</h6>
            <ul>
                <li><code>entreprise_beneficiaire</code> - Nom de l'entreprise bénéficiaire <span class="badge badge-danger">Obligatoire</span></li>
                <li><code>activite_entreprise</code> - Secteur d'activité <span class="badge badge-secondary">Optionnel</span></li>
            </ul>
        </div>
    </div>

    <!-- Valeurs valides -->
    <div class="guide-section warning">
        <h3><i class="fas fa-check-double"></i> Valeurs valides (à copier exactement)</h3>

        <div class="row">
            <div class="col-md-6">
                <h5>Types d'infrastructure:</h5>
                <div class="code-block">
Implantation station-service
Reprise station-service
Implantation point consommateur
Reprise point consommateur
Implantation dépôt GPL
Implantation centre emplisseur
                </div>
            </div>
            <div class="col-md-6">
                <h5>Régions du Cameroun:</h5>
                <div class="code-block">
Adamaoua
Centre
Est
Extrême-Nord
Littoral
Nord
Nord-Ouest
Ouest
Sud
Sud-Ouest
                </div>
            </div>
        </div>

        <div class="alert alert-warning mt-3">
            <strong><i class="fas fa-exclamation-triangle"></i> Important:</strong>
            Respectez exactement l'orthographe et les majuscules! Une seule erreur rendra la ligne invalide.
        </div>
    </div>

    <!-- Format des dates -->
    <div class="guide-section">
        <h3><i class="fas fa-calendar"></i> Format des dates</h3>

        <p>Utilisez <strong>uniquement</strong> le format <code>JJ/MM/AAAA</code></p>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Exemple</th>
                    <th>Valide?</th>
                </tr>
            </thead>
            <tbody>
                <tr class="table-success">
                    <td><code>15/03/2015</code></td>
                    <td><i class="fas fa-check text-success"></i> Correct</td>
                </tr>
                <tr class="table-success">
                    <td><code>01/12/2020</code></td>
                    <td><i class="fas fa-check text-success"></i> Correct</td>
                </tr>
                <tr class="table-danger">
                    <td><code>15-03-2015</code></td>
                    <td><i class="fas fa-times text-danger"></i> Incorrect (tiret au lieu de slash)</td>
                </tr>
                <tr class="table-danger">
                    <td><code>2015/03/15</code></td>
                    <td><i class="fas fa-times text-danger"></i> Incorrect (mauvais ordre)</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Coordonnées GPS -->
    <div class="guide-section success">
        <h3><i class="fas fa-map-marker-alt"></i> Coordonnées GPS (optionnelles mais recommandées)</h3>

        <div class="row">
            <div class="col-md-6">
                <h5>Format:</h5>
                <ul>
                    <li><strong>Latitude</strong>: Entre -90 et 90 (ex: <code>4.0511</code>)</li>
                    <li><strong>Longitude</strong>: Entre -180 et 180 (ex: <code>9.7679</code>)</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h5>Comment trouver les coordonnées:</h5>
                <ol>
                    <li>Ouvrir <a href="https://maps.google.com" target="_blank">Google Maps</a></li>
                    <li>Cliquer droit sur l'emplacement</li>
                    <li>Cliquer sur les coordonnées pour les copier</li>
                </ol>
            </div>
        </div>

        <div class="alert alert-success mt-3">
            <i class="fas fa-lightbulb"></i> <strong>Astuce:</strong> Les stations avec GPS seront visibles sur la carte interactive!
        </div>
    </div>

    <!-- Numérotation automatique -->
    <div class="guide-section">
        <h3><i class="fas fa-hashtag"></i> Numérotation automatique</h3>

        <p>Si vous laissez la colonne <code>numero_dossier</code> <strong>vide</strong>, le système génère automatiquement un numéro:</p>

        <div class="alert alert-info">
            <strong>Format:</strong> <code>HIST-[TYPE]-[REGION]-[ANNEE]-[SEQUENCE]</code>
        </div>

        <h5>Exemples:</h5>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Région</th>
                    <th>Année</th>
                    <th>Numéro généré</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Station-Service</td>
                    <td>Littoral</td>
                    <td>2015</td>
                    <td><code>HIST-SS-LT-2015-001</code></td>
                </tr>
                <tr>
                    <td>Point Consommateur</td>
                    <td>Centre</td>
                    <td>2018</td>
                    <td><code>HIST-PC-CE-2018-045</code></td>
                </tr>
                <tr>
                    <td>Dépôt GPL</td>
                    <td>Ouest</td>
                    <td>2019</td>
                    <td><code>HIST-GPL-OU-2019-003</code></td>
                </tr>
            </tbody>
        </table>

        <div class="row mt-3">
            <div class="col-md-6">
                <h6>Codes types:</h6>
                <ul>
                    <li><code>SS</code> = Station-Service</li>
                    <li><code>PC</code> = Point Consommateur</li>
                    <li><code>GPL</code> = Dépôt GPL</li>
                    <li><code>CE</code> = Centre Emplisseur</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Codes régions:</h6>
                <ul>
                    <li><code>AD</code> = Adamaoua</li>
                    <li><code>CE</code> = Centre</li>
                    <li><code>ES</code> = Est</li>
                    <li><code>EN</code> = Extrême-Nord</li>
                    <li><code>LT</code> = Littoral</li>
                    <li><code>NO</code> = Nord</li>
                    <li><code>NW</code> = Nord-Ouest</li>
                    <li><code>OU</code> = Ouest</li>
                    <li><code>SU</code> = Sud</li>
                    <li><code>SW</code> = Sud-Ouest</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Processus d'import -->
    <div class="guide-section success">
        <h3><i class="fas fa-tasks"></i> Processus d'import (4 étapes)</h3>

        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <strong>Étape 1: Préparation du fichier</strong>
            </div>
            <div class="card-body">
                <ol>
                    <li>Ouvrir le template correspondant dans Excel ou LibreOffice</li>
                    <li><strong>NE PAS modifier la ligne d'en-tête</strong></li>
                    <li>Remplir les données ligne par ligne</li>
                    <li>Commencer à partir de la ligne 6 (les lignes 2-5 contiennent des exemples)</li>
                    <li>Enregistrer au format CSV (séparateur point-virgule)</li>
                </ol>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <strong>Étape 2: Upload et validation</strong>
            </div>
            <div class="card-body">
                <ol>
                    <li>Accéder au <a href="index.php">module "Import Historique"</a></li>
                    <li>Cliquer sur "Choisir un fichier"</li>
                    <li>Sélectionner votre fichier CSV</li>
                    <li>Cliquer sur "Valider et Prévisualiser"</li>
                </ol>
                <div class="alert alert-info mt-2">
                    Le système va automatiquement:
                    <ul>
                        <li>✅ Vérifier le format du fichier</li>
                        <li>✅ Valider chaque ligne</li>
                        <li>✅ Afficher les erreurs éventuelles</li>
                        <li>✅ Montrer un aperçu des données</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-warning text-dark">
                <strong>Étape 3: Correction des erreurs</strong>
            </div>
            <div class="card-body">
                <p>Si des erreurs sont détectées:</p>
                <ol>
                    <li>Télécharger le rapport d'erreurs</li>
                    <li>Corriger les lignes problématiques dans votre fichier</li>
                    <li>Réimporter le fichier</li>
                </ol>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <strong>Étape 4: Import final</strong>
            </div>
            <div class="card-body">
                <p>Une fois la validation réussie:</p>
                <ol>
                    <li>Vérifier la prévisualisation</li>
                    <li>Cliquer sur "Confirmer l'import"</li>
                    <li>Attendre la confirmation</li>
                    <li>Télécharger le rapport d'import</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Erreurs courantes -->
    <div class="guide-section danger">
        <h3><i class="fas fa-exclamation-triangle"></i> Erreurs courantes</h3>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Erreur</th>
                    <th>Cause</th>
                    <th>Solution</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>"Type d'infrastructure invalide"</td>
                    <td>Faute de frappe</td>
                    <td>Copier-coller depuis la liste valide</td>
                </tr>
                <tr>
                    <td>"Région invalide"</td>
                    <td>Nom incorrect</td>
                    <td>Utiliser exactement un nom de la liste</td>
                </tr>
                <tr>
                    <td>"Format de date invalide"</td>
                    <td>Mauvais format</td>
                    <td>Utiliser JJ/MM/AAAA</td>
                </tr>
                <tr>
                    <td>"Entreprise bénéficiaire obligatoire"</td>
                    <td>Champ vide pour PC</td>
                    <td>Remplir le nom de l'entreprise</td>
                </tr>
                <tr>
                    <td>"Latitude/Longitude invalide"</td>
                    <td>Valeur hors limites</td>
                    <td>Vérifier les coordonnées GPS</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Bonnes pratiques -->
    <div class="guide-section">
        <h3><i class="fas fa-thumbs-up"></i> Conseils et bonnes pratiques</h3>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-check"></i> Recommandations
                    </div>
                    <div class="card-body">
                        <ul>
                            <li>Importer par lots de 50-100 dossiers</li>
                            <li>Commencer par une région test</li>
                            <li>Vérifier dans le registre public après chaque import</li>
                            <li>Uniformiser les noms de sociétés (ex: "TOTAL" vs "Total Cameroun")</li>
                            <li>Vérifier l'orthographe des noms</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-info-circle"></i> Données incomplètes
                    </div>
                    <div class="card-body">
                        <ul>
                            <li><strong>Coordonnées GPS manquantes:</strong> Laisser vide (ajout ultérieur possible)</li>
                            <li><strong>Observations:</strong> Laisser vide si rien à noter</li>
                            <li><strong>Date exacte inconnue:</strong> Utiliser 01/01 de l'année connue</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Exemple complet -->
    <div class="guide-section">
        <h3><i class="fas fa-file-code"></i> Exemples complets</h3>

        <h5>Exemple 1: Station-Service</h5>
        <div class="code-block">
;Implantation station-service;TOTAL CAMEROUN;Littoral;Douala;4.0511;9.7679;15/03/2015;N°0125/MINEE/SG/DPPG/SDTD;Station autorisée avant SGDI
        </div>
        <p><strong>Résultat:</strong> Numéro généré: <code>HIST-SS-LT-2015-001</code>, Statut: Historique Autorisé, Visible registre public</p>

        <h5 class="mt-4">Exemple 2: Point Consommateur</h5>
        <div class="code-block">
;Implantation point consommateur;TOTAL CAMEROUN;CIMENCAM;Fabrication de ciment;Littoral;Bonabéri;4.0725;9.7006;22/05/2016;N°0198/MINEE/SG/DPPG/SDTD;Point consommateur autorisé avant SGDI
        </div>
        <p><strong>Résultat:</strong> Numéro généré: <code>HIST-PC-LT-2016-001</code>, Entreprise bénéficiaire enregistrée</p>
    </div>

    <!-- Actions finales -->
    <div class="text-center mt-5 mb-5">
        <a href="index.php" class="btn btn-primary btn-lg">
            <i class="fas fa-upload"></i> Commencer l'import
        </a>
        <a href="download_template.php?type=station_service" class="btn btn-success btn-lg">
            <i class="fas fa-download"></i> Télécharger Template Stations
        </a>
        <a href="download_template.php?type=point_consommateur" class="btn btn-info btn-lg">
            <i class="fas fa-download"></i> Télécharger Template Points Consommateurs
        </a>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

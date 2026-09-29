<?php
/**
 * Guide complet d'utilisation du workflow OSM → SGDI
 */

require_once '../../includes/auth.php';
requireLogin();

if (!hasRole('admin')) {
    setFlashMessage('Accès refusé. Réservé aux administrateurs.', 'error');
    redirect('../../dashboard.php');
}

$page_title = 'Guide Import OSM';
require_once '../../includes/header.php';
?>

<style>
.guide-step {
    background: white;
    border-left: 4px solid #3498db;
    padding: 20px;
    margin: 20px 0;
    border-radius: 0 8px 8px 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.guide-step.success { border-left-color: #27ae60; }
.guide-step.warning { border-left-color: #f39c12; }
.guide-step.info { border-left-color: #3498db; }
.guide-step h3 { color: #2c3e50; margin-top: 0; }
.code-example {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
    margin: 10px 0;
}
.screenshot-placeholder {
    background: #ecf0f1;
    border: 2px dashed #bdc3c7;
    padding: 40px;
    text-align: center;
    margin: 15px 0;
    border-radius: 8px;
}
.checklist li { margin: 8px 0; }
.checklist li:before { content: '✓ '; color: #27ae60; font-weight: bold; margin-right: 5px; }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-book text-primary"></i>
            Guide Complet: Import Stations OSM
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Retour Module OSM
            </a>
        </div>
    </div>

    <!-- Introduction -->
    <div class="alert alert-info">
        <h4><i class="fas fa-info-circle"></i> À propos de ce guide</h4>
        <p class="mb-0">
            Ce guide vous accompagne étape par étape pour extraire les stations-service depuis OpenStreetMap,
            les filtrer, les enrichir et les importer dans le SGDI.
        </p>
    </div>

    <!-- Étape 0: Préparation -->
    <div class="guide-step info">
        <h3><span class="badge badge-info">Étape 0</span> Préparation - Nettoyage des données test</h3>

        <p><strong>Étape déjà réalisée :</strong> les dossiers de test ont été supprimés de la base.</p>

        <p>Le script de nettoyage a été retiré de l'application car il était accessible sans authentification.
        Pour un nouveau nettoyage, s'adresser à l'administrateur système.</p>

        <div class="alert alert-warning">
            <strong>⚠️ Important:</strong> Cette étape garantit que vous n'aurez pas de données fictives mélangées
            avec les vraies stations OSM.
        </div>
    </div>

    <!-- Étape 1: Extraction -->
    <div class="guide-step">
        <h3><span class="badge badge-primary">Étape 1</span> Extraction des stations OSM</h3>

        <p><strong>Objectif:</strong> Récupérer toutes les stations-service du Cameroun depuis OpenStreetMap</p>

        <h5>Procédure:</h5>
        <ol>
            <li>Allez sur: <a href="extract_osm_stations.php" target="_blank">extract_osm_stations.php</a></li>
            <li>Le script se lance automatiquement et va:
                <ul>
                    <li>Interroger l'API Overpass d'OpenStreetMap</li>
                    <li>Récupérer ~682 stations avec coordonnées GPS</li>
                    <li>Détecter automatiquement les régions</li>
                    <li>Générer un fichier CSV complet</li>
                </ul>
            </li>
            <li>Attendez la fin de l'extraction (~30-60 secondes)</li>
            <li><strong>Téléchargez le fichier</strong> généré (bouton en bas de page)</li>
        </ol>

        <h5>Résultat attendu:</h5>
        <div class="code-example">
stations_osm_cameroun_2025-10-31_083855.csv
Contenu: ~682 stations avec nom, opérateur, GPS, ville, région
        </div>

        <div class="alert alert-info">
            <strong>💡 Astuce:</strong> Vous pouvez relancer l'extraction à tout moment pour avoir les données OSM les plus récentes.
        </div>
    </div>

    <!-- Étape 2: Filtrage -->
    <div class="guide-step success">
        <h3><span class="badge badge-success">Étape 2</span> Filtrage par qualité (Recommandé)</h3>

        <p><strong>Objectif:</strong> Ne garder que les stations avec les meilleures données pour faciliter la validation</p>

        <h5>Procédure:</h5>
        <ol>
            <li>Allez sur: <a href="filter_osm_stations.php" target="_blank">filter_osm_stations.php</a></li>
            <li>Le script analyse automatiquement le dernier fichier extrait</li>
            <li>Consultez les statistiques de qualité:
                <ul>
                    <li><strong>Excellent</strong>: Nom + Opérateur + Ville (~150 stations)</li>
                    <li><strong>Bon</strong>: Nom + Opérateur (~170 stations)</li>
                    <li><strong>Moyen</strong>: Nom seulement</li>
                    <li><strong>Faible</strong>: Sans nom</li>
                </ul>
            </li>
            <li><strong>Sélectionnez le filtre "Excellent + Bon"</strong> (recommandé)</li>
            <li>Cliquez sur "Générer CSV Filtré"</li>
            <li><strong>Téléchargez le fichier filtré</strong></li>
        </ol>

        <h5>Résultat attendu:</h5>
        <div class="code-example">
stations_osm_filtrees_excellent+bon_2025-10-31_083956.csv
Contenu: ~320 stations de haute qualité (réduction 50%)
        </div>

        <div class="alert alert-success">
            <strong>✅ Recommandé:</strong> Le filtrage réduit le volume de moitié tout en gardant les meilleures données,
            ce qui accélère la validation et réduit les erreurs.
        </div>
    </div>

    <!-- Étape 3: Conversion -->
    <div class="guide-step warning">
        <h3><span class="badge badge-warning">Étape 3</span> Conversion au format Import Historique</h3>

        <p><strong>Objectif:</strong> Transformer le CSV OSM en format compatible avec le module Import Historique du SGDI</p>

        <h5>Procédure:</h5>
        <ol>
            <li>Allez sur: <a href="convert_for_import.php" target="_blank">convert_for_import.php</a></li>
            <li>Sélectionnez le fichier filtré (ou le fichier complet si vous n'avez pas filtré)</li>
            <li>Cliquez sur "Convertir le fichier sélectionné"</li>
            <li>Le script va automatiquement:
                <ul>
                    <li>Mapper les colonnes OSM → Format SGDI</li>
                    <li>Convertir operateur/nom → nom_demandeur</li>
                    <li>Ajouter la traçabilité OSM dans observations</li>
                    <li>Valider les coordonnées GPS</li>
                </ul>
            </li>
            <li>Consultez l'aperçu des 10 premières lignes</li>
            <li><strong>Téléchargez le fichier converti</strong></li>
        </ol>

        <h5>Format cible:</h5>
        <div class="code-example">
Colonnes: numero_dossier;type_infrastructure;nom_demandeur;region;ville;
          latitude;longitude;date_autorisation;numero_decision;observations
        </div>

        <h5>Résultat attendu:</h5>
        <div class="code-example">
import_historique_osm_2025-10-31_091234.csv
Contenu: ~320 stations au format Import Historique
        </div>

        <div class="alert alert-warning">
            <strong>⚠️ Attention:</strong> Les colonnes <code>date_autorisation</code> et <code>numero_decision</code>
            sont vides et doivent être enrichies à l'étape suivante.
        </div>
    </div>

    <!-- Étape 4: Enrichissement -->
    <div class="guide-step">
        <h3><span class="badge badge-secondary">Étape 4</span> Enrichissement dans Excel</h3>

        <p><strong>Objectif:</strong> Compléter les informations manquantes (N° autorisation, date) avant import</p>

        <h5>Procédure:</h5>
        <ol>
            <li><strong>Ouvrez le fichier converti</strong> dans Excel ou LibreOffice Calc</li>
            <li><strong>Complétez les colonnes obligatoires:</strong>
                <ul class="checklist">
                    <li><code>date_autorisation</code> - Format: JJ/MM/AAAA (ex: 15/03/2015)</li>
                    <li><code>numero_decision</code> - Format: N°XXXX/MINEE/SG/DPPG/SDTD</li>
                </ul>
            </li>
            <li><strong>Vérifiez les autres colonnes:</strong>
                <ul class="checklist">
                    <li><code>nom_demandeur</code> - Corrigez si nécessaire (TOTAL, BOCOM, etc.)</li>
                    <li><code>region</code> - Vérifiez orthographe (Littoral, Centre, etc.)</li>
                    <li><code>ville</code> - Corrigez si nécessaire (Douala, Yaoundé, etc.)</li>
                    <li><code>type_infrastructure</code> - Doit être "Implantation station-service" ou "Reprise station-service"</li>
                </ul>
            </li>
            <li><strong>Supprimez les lignes</strong> de stations non autorisées MINEE</li>
            <li><strong>Sauvegardez le fichier</strong> (gardez le format CSV avec séparateur point-virgule)</li>
        </ol>

        <h5>Exemple de ligne enrichie:</h5>
        <div class="code-example">
;Implantation station-service;TOTAL CAMEROUN;Littoral;Douala;4.0511;9.7679;<strong>15/03/2015;N°0125/MINEE/SG/DPPG/SDTD</strong>;Source: OpenStreetMap (OSM ID: node/123456)
        </div>

        <div class="alert alert-info">
            <strong>💡 Astuce:</strong> Pour aller plus vite, vous pouvez:
            <ul>
                <li>Trier par opérateur et copier-coller les dates/numéros similaires</li>
                <li>Utiliser un format de numérotation séquentiel (ex: HIST-SS-LT-2015-001, 002, 003...)</li>
            </ul>
        </div>
    </div>

    <!-- Étape 5: Import -->
    <div class="guide-step info">
        <h3><span class="badge badge-info">Étape 5</span> Import dans le SGDI</h3>

        <p><strong>Objectif:</strong> Importer les stations enrichies dans la base de données SGDI</p>

        <h5>Procédure:</h5>
        <ol>
            <li>Allez sur le module Import Historique:
                <a href="<?php echo url('modules/import_historique/'); ?>" target="_blank" class="btn btn-sm btn-primary">
                    <i class="fas fa-file-import"></i> Module Import Historique
                </a>
            </li>
            <li><strong>Sélectionnez votre fichier enrichi</strong> (CSV ou XLSX)</li>
            <li>Entrez une description (ex: "Import stations OSM Cameroun - Octobre 2025")</li>
            <li>Cochez "Je confirme que mon fichier respecte le format du template"</li>
            <li>Cliquez sur <strong>"Valider et Prévisualiser"</strong></li>
            <li><strong>Vérifiez l'aperçu:</strong>
                <ul>
                    <li>Nombre de lignes détectées</li>
                    <li>Aperçu des 10 premières lignes</li>
                    <li>Erreurs éventuelles (en rouge)</li>
                </ul>
            </li>
            <li>Si tout est correct, cliquez sur <strong>"Lancer l'import"</strong></li>
            <li>Attendez la fin du traitement (barre de progression)</li>
            <li>Consultez le rapport final:
                <ul>
                    <li>Nombre de dossiers importés ✅</li>
                    <li>Erreurs éventuelles ⚠️</li>
                </ul>
            </li>
        </ol>

        <div class="alert alert-success">
            <strong>✅ Félicitations!</strong> Vos stations sont maintenant dans le SGDI avec le statut
            <code>historique_autorise</code> et <code>est_historique = 1</code>.
        </div>
    </div>

    <!-- Étape 6: Visualisation -->
    <div class="guide-step success">
        <h3><span class="badge badge-dark">Étape 6</span> Visualisation sur la carte</h3>

        <p><strong>Objectif:</strong> Vérifier visuellement que les stations sont bien positionnées</p>

        <h5>Procédure:</h5>
        <ol>
            <li>Allez sur la carte interactive:
                <a href="<?php echo url('modules/carte/'); ?>" target="_blank" class="btn btn-sm btn-success">
                    <i class="fas fa-map-marked-alt"></i> Carte des Infrastructures
                </a>
            </li>
            <li><strong>Utilisez les filtres:</strong>
                <ul>
                    <li>Type: Station-service</li>
                    <li>Statut: Autorisé (pour voir les dossiers historiques)</li>
                    <li>Région: Sélectionnez une région spécifique</li>
                </ul>
            </li>
            <li><strong>Vérifiez les marqueurs:</strong>
                <ul>
                    <li>Icônes pompes à essence rouges</li>
                    <li>Survol: Info rapide (nom, numéro, ville)</li>
                    <li>Clic: Popup détaillé avec toutes les infos</li>
                </ul>
            </li>
            <li><strong>Vérifiez le positionnement:</strong>
                <ul>
                    <li>Les stations sont-elles au bon endroit?</li>
                    <li>Y a-t-il des marqueurs aberrants?</li>
                </ul>
            </li>
        </ol>

        <div class="alert alert-info">
            <strong>💡 Astuce:</strong> Si vous trouvez des erreurs de positionnement, vous pouvez:
            <ul>
                <li>Modifier le dossier directement dans le SGDI</li>
                <li>Ou corriger le CSV et réimporter (après suppression des doublons)</li>
            </ul>
        </div>
    </div>

    <!-- Résumé rapide -->
    <div class="card mt-4 border-primary">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-list-check"></i> Checklist Récapitulatif</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Fichiers générés à chaque étape:</h5>
                    <ul class="checklist">
                        <li><code>stations_osm_cameroun_*.csv</code> - Extraction brute</li>
                        <li><code>stations_osm_filtrees_*.csv</code> - Filtrage qualité</li>
                        <li><code>import_historique_osm_*.csv</code> - Conversion format</li>
                        <li><code>import_historique_osm_*_enrichi.csv</code> - Enrichissement</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5>Temps estimé par étape:</h5>
                    <ul>
                        <li><strong>Étape 0</strong>: Nettoyage - 2 min</li>
                        <li><strong>Étape 1</strong>: Extraction OSM - 1 min</li>
                        <li><strong>Étape 2</strong>: Filtrage - 1 min</li>
                        <li><strong>Étape 3</strong>: Conversion - 30 sec</li>
                        <li><strong>Étape 4</strong>: Enrichissement - 30-60 min ⏱️</li>
                        <li><strong>Étape 5</strong>: Import SGDI - 2-5 min</li>
                        <li><strong>Étape 6</strong>: Vérification - 5 min</li>
                    </ul>
                    <p><strong>Total</strong>: ~40-75 minutes (dont 30-60 min enrichissement Excel)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ -->
    <div class="card mt-4">
        <div class="card-header bg-secondary text-white">
            <h4 class="mb-0"><i class="fas fa-question-circle"></i> Questions Fréquentes (FAQ)</h4>
        </div>
        <div class="card-body">
            <h5>❓ Que faire si l'extraction OSM échoue?</h5>
            <p><strong>R:</strong> Réessayez dans quelques minutes. L'API Overpass peut être temporairement surchargée.
               Si le problème persiste, contactez l'administrateur système.</p>

            <h5>❓ Dois-je enrichir TOUTES les lignes?</h5>
            <p><strong>R:</strong> Non! Supprimez les lignes de stations non autorisées MINEE. Enrichissez uniquement
               les stations dont vous avez l'autorisation officielle.</p>

            <h5>❓ Quel format de date utiliser?</h5>
            <p><strong>R:</strong> Toujours JJ/MM/AAAA (ex: 15/03/2015). Le système n'accepte pas d'autres formats.</p>

            <h5>❓ Puis-je importer en plusieurs fois?</h5>
            <p><strong>R:</strong> Oui! Vous pouvez diviser l'import par région, par opérateur, etc. Le système
               détecte automatiquement les doublons (basé sur coordonnées GPS).</p>

            <h5>❓ Comment corriger une erreur après import?</h5>
            <p><strong>R:</strong> Allez dans "Tous les dossiers", recherchez le dossier concerné, et modifiez-le
               directement. Les dossiers historiques sont modifiables par les admins.</p>
        </div>
    </div>

    <!-- Boutons d'action -->
    <div class="text-center mt-5 mb-5">
        <a href="index.php" class="btn btn-primary btn-lg">
            <i class="fas fa-rocket"></i> Commencer l'import OSM
        </a>
        <a href="<?php echo url('modules/import_historique/'); ?>" class="btn btn-info btn-lg">
            <i class="fas fa-file-import"></i> Module Import Historique
        </a>
        <a href="<?php echo url('modules/carte/'); ?>" class="btn btn-success btn-lg">
            <i class="fas fa-map-marked-alt"></i> Voir la Carte
        </a>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

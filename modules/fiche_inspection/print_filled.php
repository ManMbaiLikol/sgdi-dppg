<?php
require_once '../../includes/auth.php';
require_once '../dossiers/functions.php';
require_once 'functions.php';

requireLogin();

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

// Récupérer la fiche
$fiche = getFicheInspectionByDossier($dossier_id);

if (!$fiche) {
    $_SESSION['error'] = "Aucune fiche d'inspection disponible pour ce dossier";
    redirect('modules/dossiers/view.php?id=' . $dossier_id);
}

// Déterminer le type d'infrastructure pour adapter le formulaire
$est_point_consommateur = ($dossier['type_infrastructure'] === 'point_consommateur');
$titre_type = $est_point_consommateur ? 'Point Consommateur' : 'Station-Service';

// Récupérer les données associées
$cuves = getCuvesFiche($fiche['id']);
$pompes = getPompesFiche($fiche['id']);
$distances_edifices = getDistancesEdifices($fiche['id']);
$distances_stations = getDistancesStations($fiche['id']);

// Organiser les distances par direction
$edifices_par_direction = [];
$stations_par_direction = [];
foreach ($distances_edifices as $de) {
    $edifices_par_direction[$de['direction']] = $de;
}
foreach ($distances_stations as $ds) {
    $stations_par_direction[$ds['direction']] = $ds;
}

// Fonction helper pour afficher une valeur ou un tiret
function displayValue($value, $default = '—') {
    return !empty($value) ? htmlspecialchars($value) : $default;
}

// Fonction pour afficher une checkbox
function displayCheckbox($value) {
    return $value ? '☑' : '☐';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche d'inspection - <?php echo htmlspecialchars($dossier['numero']); ?></title>
    <style>
        @page {
            size: A4;
            margin: 1.2cm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.3;
            color: #000;
        }

        .header {
            text-align: center;
            margin-bottom: 12px;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }

        .header h1 {
            font-size: 13pt;
            font-weight: bold;
            margin: 3px 0;
            text-transform: uppercase;
        }

        .header h2 {
            font-size: 11pt;
            font-weight: bold;
            margin: 3px 0;
        }

        .section-title {
            font-weight: bold;
            text-decoration: underline;
            margin-top: 8px;
            margin-bottom: 4px;
            font-size: 9.5pt;
        }

        .field-row {
            display: flex;
            margin-bottom: 3px;
        }

        .field-label {
            font-weight: normal;
            min-width: 180px;
        }

        .field-value {
            flex: 1;
            border-bottom: 1px dotted #000;
            min-height: 14px;
            padding: 0 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }

        table th, table td {
            border: 1px solid #000;
            padding: 3px;
            text-align: left;
            font-size: 8pt;
        }

        table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }

        .checkbox-group {
            margin: 4px 0;
        }

        .checkbox-item {
            display: inline-block;
            margin-right: 25px;
            margin-bottom: 3px;
        }

        .checkbox {
            display: inline-block;
            width: 13px;
            height: 13px;
            margin-right: 4px;
            vertical-align: middle;
            font-size: 12pt;
        }

        .observations {
            width: 100%;
            border: 1px solid #000;
            padding: 5px;
            min-height: 60px;
            white-space: pre-wrap;
            line-height: 1.3;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
            page-break-inside: avoid;
        }

        .signature-box {
            width: 45%;
            text-align: center;
            border: 1px solid #000;
            padding: 5px;
            min-height: 60px;
        }

        .signature-label {
            font-weight: bold;
            margin-bottom: 30px;
        }

        .page-break {
            page-break-after: always;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                margin: 0;
                padding: 0;
            }
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            z-index: 1000;
        }

        .print-button:hover {
            background-color: #0056b3;
        }

        .dms-coords {
            display: flex;
            gap: 5px;
            align-items: center;
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="print-button no-print">🖨️ Imprimer</button>

    <div class="header">
        <h1>République du Cameroun</h1>
        <p style="margin: 3px 0;"><em>Paix – Travail – Patrie</em></p>
        <p style="margin: 3px 0;">--------</p>
        <p style="margin: 3px 0;">Ministère de l'Eau et de l'Énergie</p>
        <p style="margin: 3px 0;">--------</p>
        <p style="margin: 3px 0;">Direction des Produits Pétroliers et du Gaz</p>
        <h2 style="margin-top: 15px;">FICHE DE RÉCOLTE DES DONNÉES SUR LES INFRASTRUCTURES PÉTROLIÈRES</h2>
        <p style="margin-top: 10px;">Dossier N° <strong><?php echo htmlspecialchars($dossier['numero']); ?></strong></p>
    </div>

    <!-- Section 1 -->
    <div class="section-title">1. INFORMATIONS D'ORDRE GÉNÉRAL</div>

    <div class="field-row">
        <div class="field-label">Type d'infrastructure :</div>
        <div class="field-value"><?php echo displayValue($fiche['type_infrastructure']); ?></div>
    </div>

    <div class="field-row">
        <div class="field-label">Raison sociale :</div>
        <div class="field-value"><?php echo displayValue($fiche['raison_sociale']); ?></div>
    </div>

    <div class="field-row">
        <div class="field-label">BP :</div>
        <div class="field-value" style="max-width: 200px;"><?php echo displayValue($fiche['bp']); ?></div>
        <div class="field-label" style="margin-left: 20px;">Tél :</div>
        <div class="field-value"><?php echo displayValue($fiche['telephone']); ?></div>
    </div>

    <div class="field-row">
        <div class="field-label">Fax :</div>
        <div class="field-value" style="max-width: 200px;"><?php echo displayValue($fiche['fax']); ?></div>
        <div class="field-label" style="margin-left: 20px;">Email :</div>
        <div class="field-value"><?php echo displayValue($fiche['email']); ?></div>
    </div>

    <div class="field-row">
        <div class="field-label">Région :</div>
        <div class="field-value"><?php echo displayValue($fiche['region']); ?></div>
    </div>

    <div class="field-row">
        <div class="field-label">Département :</div>
        <div class="field-value"><?php echo displayValue($fiche['departement']); ?></div>
    </div>

    <div class="field-row">
        <div class="field-label">Arrondissement :</div>
        <div class="field-value"><?php echo displayValue($fiche['arrondissement']); ?></div>
    </div>

    <div class="field-row">
        <div class="field-label">Ville :</div>
        <div class="field-value"><?php echo displayValue($fiche['ville']); ?></div>
    </div>

    <div class="field-row">
        <div class="field-label">Quartier :</div>
        <div class="field-value"><?php echo displayValue($fiche['quartier']); ?></div>
    </div>

    <div class="field-row">
        <div class="field-label">Rue :</div>
        <div class="field-value"><?php echo displayValue($fiche['rue']); ?></div>
    </div>

    <div class="field-row">
        <div class="field-label">Lieu-dit :</div>
        <div class="field-value"><?php echo displayValue($fiche['lieu_dit']); ?></div>
    </div>

    <!-- Section 2 -->
    <div class="section-title">2. INFORMATIONS DE GÉO-RÉFÉRENCEMENT</div>

    <div class="field-row">
        <div class="field-label">Latitude (décimal) :</div>
        <div class="field-value" style="max-width: 150px;"><?php echo displayValue($fiche['latitude']); ?></div>
        <div class="field-label" style="margin-left: 20px;">Longitude (décimal) :</div>
        <div class="field-value" style="max-width: 150px;"><?php echo displayValue($fiche['longitude']); ?></div>
    </div>

    <div class="field-row">
        <div class="field-label">Heure GMT :</div>
        <div class="field-value" style="max-width: 150px;"><?php echo displayValue($fiche['heure_gmt']); ?></div>
        <div class="field-label" style="margin-left: 20px;">Heure locale :</div>
        <div class="field-value" style="max-width: 150px;"><?php echo displayValue($fiche['heure_locale']); ?></div>
    </div>

    <div class="field-row">
        <div class="field-label">Latitude (DMS) :</div>
        <div class="dms-coords">
            <div class="field-value" style="max-width: 60px;"><?php echo displayValue($fiche['latitude_degres']); ?></div>
            <span>°</span>
            <div class="field-value" style="max-width: 60px;"><?php echo displayValue($fiche['latitude_minutes']); ?></div>
            <span>'</span>
            <div class="field-value" style="max-width: 60px;"><?php echo displayValue($fiche['latitude_secondes']); ?></div>
            <span>"</span>
        </div>
    </div>

    <div class="field-row">
        <div class="field-label">Longitude (DMS) :</div>
        <div class="dms-coords">
            <div class="field-value" style="max-width: 60px;"><?php echo displayValue($fiche['longitude_degres']); ?></div>
            <span>°</span>
            <div class="field-value" style="max-width: 60px;"><?php echo displayValue($fiche['longitude_minutes']); ?></div>
            <span>'</span>
            <div class="field-value" style="max-width: 60px;"><?php echo displayValue($fiche['longitude_secondes']); ?></div>
            <span>"</span>
        </div>
    </div>

    <!-- Section 3 -->
    <div class="section-title">3. INFORMATIONS TECHNIQUES</div>

    <?php if ($est_point_consommateur): ?>
        <!-- Section spécifique aux POINTS CONSOMMATEURS -->
        <div class="field-row">
            <div class="field-label">Numéro du contrat d'approvisionnement :</div>
            <div class="field-value"><?php echo displayValue($fiche['numero_contrat_approvisionnement']); ?></div>
        </div>

        <div class="field-row">
            <div class="field-label">Nom de la société contractante :</div>
            <div class="field-value"><?php echo displayValue($fiche['societe_contractante']); ?></div>
        </div>

        <div class="field-row">
            <div class="field-label">Besoins moyens mensuels en produits pétroliers :</div>
            <div class="field-value"><?php echo displayValue($fiche['besoins_mensuels_litres'] ? number_format($fiche['besoins_mensuels_litres'], 0, ',', ' ') . ' litres' : ''); ?></div>
        </div>

        <div class="field-row">
            <div class="field-label">Nombre de personnels employés :</div>
            <div class="field-value"><?php echo displayValue($fiche['nombre_personnels']); ?></div>
        </div>

        <div class="field-row">
            <div class="field-label">Superficie du site :</div>
            <div class="field-value"><?php echo displayValue($fiche['superficie_site'] ? number_format($fiche['superficie_site'], 0, ',', ' ') . ' m²' : ''); ?></div>
        </div>

        <div class="field-row">
            <div class="field-label">Système de récupération des huiles usées :</div>
            <div class="field-value"><?php echo displayValue($fiche['systeme_recuperation_huiles']); ?></div>
        </div>

        <div style="margin-top: 6px; margin-bottom: 3px; font-weight: bold;">Parc d'engin de la société :</div>
        <div style="border: 1px solid #000; padding: 5px; margin-bottom: 6px; min-height: 30px; line-height: 1.3; white-space: pre-wrap;">
            <?php echo nl2br(htmlspecialchars($fiche['parc_engin'] ?? '—')); ?>
        </div>

        <div style="margin-top: 6px; margin-bottom: 3px; font-weight: bold;">Bâtiments du site :</div>
        <div style="border: 1px solid #000; padding: 5px; margin-bottom: 6px; min-height: 30px; line-height: 1.3; white-space: pre-wrap;">
            <?php echo nl2br(htmlspecialchars($fiche['batiments_site'] ?? '—')); ?>
        </div>

        <div style="margin-top: 6px; margin-bottom: 4px; font-weight: bold;">Infrastructures d'approvisionnement :</div>
        <div class="checkbox-group">
            <div class="checkbox-item">
                <span class="checkbox"><?php echo displayCheckbox($fiche['infra_eau']); ?></span> Eau
            </div>
            <div class="checkbox-item">
                <span class="checkbox"><?php echo displayCheckbox($fiche['infra_electricite']); ?></span> Électricité
            </div>
        </div>

        <div style="margin-top: 6px; margin-bottom: 4px; font-weight: bold;">Réseaux de télécommunication :</div>
        <div class="checkbox-group">
            <div class="checkbox-item">
                <span class="checkbox"><?php echo displayCheckbox($fiche['reseau_camtel']); ?></span> CAMTEL
            </div>
            <div class="checkbox-item">
                <span class="checkbox"><?php echo displayCheckbox($fiche['reseau_mtn']); ?></span> MTN
            </div>
            <div class="checkbox-item">
                <span class="checkbox"><?php echo displayCheckbox($fiche['reseau_orange']); ?></span> ORANGE
            </div>
            <div class="checkbox-item">
                <span class="checkbox"><?php echo displayCheckbox($fiche['reseau_nexttel']); ?></span> NEXTTEL
            </div>
        </div>

    <?php else: ?>
        <!-- Section par défaut pour STATIONS-SERVICES -->
        <div class="field-row">
            <div class="field-label">Date de mise en service :</div>
            <div class="field-value"><?php echo displayValue($fiche['date_mise_service'] ? date('d/m/Y', strtotime($fiche['date_mise_service'])) : ''); ?></div>
        </div>

        <div class="field-row">
            <div class="field-label">N° Autorisation MINEE :</div>
            <div class="field-value"><?php echo displayValue($fiche['autorisation_minee']); ?></div>
        </div>

        <div class="field-row">
            <div class="field-label">N° Autorisation MINMIDT :</div>
            <div class="field-value"><?php echo displayValue($fiche['autorisation_minmidt']); ?></div>
        </div>

        <div class="field-row">
            <div class="field-label">Type de gestion :</div>
            <span class="checkbox"><?php echo $fiche['type_gestion'] === 'libre' ? '☑' : '☐'; ?></span> Libre
            <span style="margin: 0 10px;"></span>
            <span class="checkbox"><?php echo $fiche['type_gestion'] === 'location' ? '☑' : '☐'; ?></span> Location
            <span style="margin: 0 10px;"></span>
            <span class="checkbox"><?php echo $fiche['type_gestion'] === 'autres' ? '☑' : '☐'; ?></span> Autres : <?php echo displayValue($fiche['type_gestion_autre'], ''); ?>
        </div>

        <div style="margin-top: 6px; margin-bottom: 4px; font-weight: bold;">Documents techniques disponibles :</div>
        <div class="checkbox-group">
            <div class="checkbox-item">
                <span class="checkbox"><?php echo displayCheckbox($fiche['plan_ensemble']); ?></span> Plan d'ensemble
            </div>
            <div class="checkbox-item">
                <span class="checkbox"><?php echo displayCheckbox($fiche['contrat_bail']); ?></span> Contrat de bail
            </div>
            <div class="checkbox-item">
                <span class="checkbox"><?php echo displayCheckbox($fiche['permis_batir']); ?></span> Permis de bâtir
            </div>
            <div class="checkbox-item">
                <span class="checkbox"><?php echo displayCheckbox($fiche['certificat_urbanisme']); ?></span> Certificat d'urbanisme
            </div>
            <div class="checkbox-item">
                <span class="checkbox"><?php echo displayCheckbox($fiche['lettre_minepded']); ?></span> Lettre MINEPDED
            </div>
            <div class="checkbox-item">
                <span class="checkbox"><?php echo displayCheckbox($fiche['plan_masse']); ?></span> Plan de masse
            </div>
            <div class="checkbox-item">
                <span class="checkbox"><?php echo displayCheckbox($fiche['lettre_desistement']); ?></span> Lettre de désistement
            </div>
        </div>

        <div style="margin-top: 6px; margin-bottom: 4px; font-weight: bold;">Effectifs du personnel :</div>
        <div class="field-row">
            <div class="field-label">Chef de piste :</div>
            <div class="field-value"><?php echo displayValue($fiche['chef_piste']); ?></div>
        </div>
        <div class="field-row">
            <div class="field-label">Gérant :</div>
            <div class="field-value"><?php echo displayValue($fiche['gerant']); ?></div>
        </div>
    <?php endif; ?>

    <!-- Section 4 - Cuves -->
    <div class="section-title">4. INSTALLATIONS - CUVES</div>
    <table>
        <thead>
            <tr>
                <th width="10%">N°</th>
                <th width="20%">Produit</th>
                <th width="30%">Type de cuve</th>
                <th width="20%">Capacité (L)</th>
                <th width="20%">Nombre</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($cuves)): ?>
                <?php foreach ($cuves as $cuve): ?>
                <tr>
                    <td style="text-align: center;"><?php echo htmlspecialchars($cuve['numero']); ?></td>
                    <td>
                        <?php
                        if ($cuve['produit'] === 'autre') {
                            echo htmlspecialchars($cuve['produit_autre']);
                        } else {
                            echo ucfirst(htmlspecialchars($cuve['produit']));
                        }
                        ?>
                    </td>
                    <td><?php echo $cuve['type_cuve'] === 'double_enveloppe' ? 'Double enveloppe' : 'Simple enveloppe'; ?></td>
                    <td style="text-align: right;"><?php echo number_format($cuve['capacite'], 0, ',', ' '); ?></td>
                    <td style="text-align: center;"><?php echo htmlspecialchars($cuve['nombre']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; font-style: italic;">Aucune cuve renseignée</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Section 4 - Pompes -->
    <div class="section-title">4. INSTALLATIONS - POMPES</div>
    <table>
        <thead>
            <tr>
                <th width="10%">N°</th>
                <th width="20%">Produit</th>
                <th width="30%">Marque</th>
                <th width="20%">Débit nominal (L/min)</th>
                <th width="20%">Nombre</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($pompes)): ?>
                <?php foreach ($pompes as $pompe): ?>
                <tr>
                    <td style="text-align: center;"><?php echo htmlspecialchars($pompe['numero']); ?></td>
                    <td>
                        <?php
                        if ($pompe['produit'] === 'autre') {
                            echo htmlspecialchars($pompe['produit_autre']);
                        } else {
                            echo ucfirst(htmlspecialchars($pompe['produit']));
                        }
                        ?>
                    </td>
                    <td><?php echo displayValue($pompe['marque']); ?></td>
                    <td style="text-align: right;"><?php echo displayValue($pompe['debit_nominal'] ? number_format($pompe['debit_nominal'], 0, ',', ' ') : ''); ?></td>
                    <td style="text-align: center;"><?php echo htmlspecialchars($pompe['nombre']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; font-style: italic;">Aucune pompe renseignée</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (!$est_point_consommateur): ?>
    <!-- Section 5 - Distances édifices (uniquement pour stations-services) -->
    <div class="section-title">Distance par rapport aux édifices et places publiques les plus proches</div>
    <table>
        <thead>
            <tr>
                <th width="20%"></th>
                <th width="50%">Description de l'édifice ou la place publique</th>
                <th width="30%">Distance (en mètres)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (['nord', 'sud', 'est', 'ouest'] as $direction): ?>
                <?php $edifice = $edifices_par_direction[$direction] ?? null; ?>
                <tr>
                    <td><strong><?php echo getDirectionLabel($direction); ?></strong></td>
                    <td><?php echo displayValue($edifice['description_edifice'] ?? '', ''); ?></td>
                    <td style="text-align: right;"><?php echo displayValue($edifice['distance_metres'] ? number_format($edifice['distance_metres'], 2, ',', ' ') : ''); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Section 5 - Distances stations (uniquement pour stations-services) -->
    <div class="section-title">Distance par rapport aux stations-services les plus proches</div>
    <table>
        <thead>
            <tr>
                <th width="20%"></th>
                <th width="50%">Nom de la station-service</th>
                <th width="30%">Distance (en mètres)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (['nord', 'sud', 'est', 'ouest'] as $direction): ?>
                <?php $station = $stations_par_direction[$direction] ?? null; ?>
                <tr>
                    <td><strong><?php echo getDirectionLabel($direction); ?></strong></td>
                    <td><?php echo displayValue($station['nom_station'] ?? '', ''); ?></td>
                    <td style="text-align: right;"><?php echo displayValue($station['distance_metres'] ? number_format($station['distance_metres'], 2, ',', ' ') : ''); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Section 6 - Sécurité -->
    <div class="section-title">SÉCURITÉ ET ENVIRONNEMENT</div>

    <div class="checkbox-group">
        <div class="checkbox-item">
            <span class="checkbox"><?php echo displayCheckbox($fiche['bouches_incendies']); ?></span> Bouches d'incendies
        </div>
        <div style="margin: 10px 0;">
            <span class="checkbox"><?php echo displayCheckbox($fiche['decanteur_separateur']); ?></span> Présence de décanteur/séparateur des eaux usées
        </div>
    </div>

    <div style="margin-top: 5px;">
        <strong>Autres dispositions relatives à la sécurité et environnementales :</strong>
    </div>
    <?php if (!empty($fiche['autres_dispositions_securite'])): ?>
        <div class="observations" style="min-height: 40px;">
            <?php echo nl2br(htmlspecialchars($fiche['autres_dispositions_securite'])); ?>
        </div>
    <?php else: ?>
        <div style="margin-top: 3px;">
            <div style="border-bottom: 1px dotted #000; min-height: 16px; margin-bottom: 3px;">—</div>
        </div>
    <?php endif; ?>

    <!-- Section 7 - Observations -->
    <div class="section-title">7. OBSERVATIONS GÉNÉRALES</div>
    <?php if (!empty($fiche['observations_generales'])): ?>
        <div class="observations">
            <?php echo nl2br(htmlspecialchars($fiche['observations_generales'])); ?>
        </div>
    <?php else: ?>
        <div class="observations" style="font-style: italic; color: #666;">
            Aucune observation
        </div>
    <?php endif; ?>

    <!-- Section 8 - Recommandations -->
    <div class="section-title" style="margin-top: 8px;">8. RECOMMANDATIONS</div>
    <?php if (!empty($fiche['recommandations'])): ?>
        <div class="observations">
            <?php echo nl2br(htmlspecialchars($fiche['recommandations'])); ?>
        </div>
    <?php else: ?>
        <div class="observations" style="font-style: italic; color: #666;">
            Aucune recommandation
        </div>
    <?php endif; ?>

    <!-- Établissement -->
    <div style="margin-top: 12px;">
        <div class="field-row">
            <div class="field-label">Fiche établie à :</div>
            <div class="field-value" style="max-width: 300px;"><?php echo displayValue($fiche['lieu_etablissement']); ?></div>
            <div class="field-label" style="margin-left: 20px;">Le :</div>
            <div class="field-value" style="max-width: 200px;">
                <?php echo displayValue($fiche['date_etablissement'] ? date('d/m/Y', strtotime($fiche['date_etablissement'])) : ''); ?>
            </div>
        </div>
    </div>

    <!-- Signatures -->
    <div style="text-align: center; margin: 12px 0 8px 0;">
        <strong>Ont signé :</strong>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-label">POUR LE MINEE</div>
            <?php if ($fiche['inspecteur_nom'] || $fiche['inspecteur_prenom']): ?>
                <div style="margin-top: 30px;">
                    <strong><?php echo htmlspecialchars($fiche['inspecteur_prenom'] . ' ' . $fiche['inspecteur_nom']); ?></strong>
                </div>
            <?php endif; ?>
            <div style="margin-top: 12px; border-top: 1px solid #000; display: inline-block; padding-top: 3px;">
                Signature et cachet
            </div>
        </div>

        <div class="signature-box">
            <div class="signature-label">POUR LE DEMANDEUR</div>
            <div style="margin-top: 30px;">
                <strong><?php echo htmlspecialchars($dossier['nom_demandeur']); ?></strong>
            </div>
            <div style="margin-top: 12px; border-top: 1px solid #000; display: inline-block; padding-top: 3px;">
                Signature et cachet
            </div>
        </div>
    </div>

    <?php if ($fiche['statut']): ?>
        <div style="margin-top: 12px; text-align: center; font-size: 8pt; color: #666;">
            <em>Statut: <?php
                switch($fiche['statut']) {
                    case 'brouillon': echo 'Brouillon'; break;
                    case 'validee': echo 'Validée'; break;
                    case 'signee': echo 'Signée'; break;
                    default: echo ucfirst($fiche['statut']);
                }
            ?></em>
            <?php if ($fiche['date_modification']): ?>
                <em> - Dernière modification: <?php echo date('d/m/Y à H:i', strtotime($fiche['date_modification'])); ?></em>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>

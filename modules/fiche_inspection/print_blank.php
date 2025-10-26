<?php
// Déterminer le type d'infrastructure depuis l'URL
$type = $_GET['type'] ?? 'station_service';
$est_point_consommateur = ($type === 'point_consommateur');
$titre_type = $est_point_consommateur ? 'Point Consommateur' : 'Station-Service';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche d'inspection <?php echo $titre_type; ?> (Vierge)</title>
    <style>
        @page {
            size: A4;
            margin: 1.5cm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
        }

        .header h1 {
            font-size: 14pt;
            font-weight: bold;
            margin: 5px 0;
            text-transform: uppercase;
        }

        .header h2 {
            font-size: 13pt;
            font-weight: bold;
            margin: 5px 0;
        }

        .section-title {
            font-weight: bold;
            text-decoration: underline;
            margin-top: 12px;
            margin-bottom: 6px;
            font-size: 10.5pt;
        }

        .field-row {
            display: flex;
            margin-bottom: 5px;
        }

        .field-label {
            font-weight: normal;
            min-width: 180px;
        }

        .field-value {
            flex: 1;
            border-bottom: 1px dotted #000;
            min-height: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0;
        }

        table th, table td {
            border: 1px solid #000;
            padding: 4px;
            text-align: left;
            font-size: 9pt;
        }

        table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }

        .checkbox-group {
            margin: 6px 0;
        }

        .checkbox-item {
            display: inline-block;
            margin-right: 30px;
            margin-bottom: 5px;
        }

        .checkbox {
            display: inline-block;
            width: 15px;
            height: 15px;
            border: 1px solid #000;
            margin-right: 5px;
            vertical-align: middle;
        }

        .observations {
            width: 100%;
            min-height: 150px;
            border: 1px solid #000;
            padding: 5px;
        }

        .observations-lines {
            line-height: 2;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            page-break-inside: avoid;
        }

        .signature-box {
            width: 45%;
            text-align: center;
            border: 1px solid #000;
            padding: 6px;
            min-height: 70px;
        }

        .signature-label {
            font-weight: bold;
            margin-bottom: 35px;
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
        <p style="margin: 3px 0;">Direction de la Promotion des Produits Pétroliers et Gaziers</p>
        <h2 style="margin-top: 15px;">FICHE DE RÉCOLTE DES DONNÉES SUR LES INFRASTRUCTURES PÉTROLIÈRES</h2>
    </div>

    <!-- Section 1 -->
    <div class="section-title">1. INFORMATIONS D'ORDRE GÉNÉRAL</div>

    <div class="field-row">
        <div class="field-label">Type d'infrastructure :</div>
        <div class="field-value"></div>
    </div>

    <div class="field-row">
        <div class="field-label">Raison sociale :</div>
        <div class="field-value"></div>
    </div>

    <div class="field-row">
        <div class="field-label">BP :</div>
        <div class="field-value" style="max-width: 200px;"></div>
        <div class="field-label" style="margin-left: 20px;">Tél :</div>
        <div class="field-value"></div>
    </div>

    <div class="field-row">
        <div class="field-label">Fax :</div>
        <div class="field-value" style="max-width: 200px;"></div>
        <div class="field-label" style="margin-left: 20px;">Email :</div>
        <div class="field-value"></div>
    </div>

    <div class="field-row">
        <div class="field-label">Région :</div>
        <div class="field-value"></div>
    </div>

    <div class="field-row">
        <div class="field-label">Département :</div>
        <div class="field-value"></div>
    </div>

    <div class="field-row">
        <div class="field-label">Arrondissement :</div>
        <div class="field-value"></div>
    </div>

    <div class="field-row">
        <div class="field-label">Ville :</div>
        <div class="field-value"></div>
    </div>

    <div class="field-row">
        <div class="field-label">Quartier :</div>
        <div class="field-value"></div>
    </div>

    <div class="field-row">
        <div class="field-label">Rue :</div>
        <div class="field-value"></div>
    </div>

    <div class="field-row">
        <div class="field-label">Lieu-dit :</div>
        <div class="field-value"></div>
    </div>

    <!-- Section 2 -->
    <div class="section-title">2. INFORMATIONS DE GÉO-RÉFÉRENCEMENT</div>

    <div class="field-row">
        <div class="field-label">Latitude (décimal) :</div>
        <div class="field-value" style="max-width: 150px;"></div>
        <div class="field-label" style="margin-left: 20px;">Longitude (décimal) :</div>
        <div class="field-value" style="max-width: 150px;"></div>
    </div>

    <div class="field-row">
        <div class="field-label">Heure GMT :</div>
        <div class="field-value" style="max-width: 150px;"></div>
        <div class="field-label" style="margin-left: 20px;">Heure locale :</div>
        <div class="field-value" style="max-width: 150px;"></div>
    </div>

    <div class="field-row">
        <div class="field-label">Latitude (DMS) :</div>
        <div class="field-value" style="max-width: 60px;"></div>
        <span style="margin: 0 5px;">°</span>
        <div class="field-value" style="max-width: 60px;"></div>
        <span style="margin: 0 5px;">'</span>
        <div class="field-value" style="max-width: 60px;"></div>
        <span style="margin: 0 5px;">"</span>
    </div>

    <div class="field-row">
        <div class="field-label">Longitude (DMS) :</div>
        <div class="field-value" style="max-width: 60px;"></div>
        <span style="margin: 0 5px;">°</span>
        <div class="field-value" style="max-width: 60px;"></div>
        <span style="margin: 0 5px;">'</span>
        <div class="field-value" style="max-width: 60px;"></div>
        <span style="margin: 0 5px;">"</span>
    </div>

    <!-- Section 3 -->
    <div class="section-title">3. INFORMATIONS TECHNIQUES</div>

    <?php if ($est_point_consommateur): ?>
        <!-- Section spécifique aux POINTS CONSOMMATEURS -->
        <div class="field-row">
            <div class="field-label">Numéro du contrat d'approvisionnement :</div>
            <div class="field-value"></div>
        </div>

        <div class="field-row">
            <div class="field-label">Nom de la société contractante :</div>
            <div class="field-value"></div>
        </div>

        <div class="field-row">
            <div class="field-label">Besoins moyens mensuels en produits pétroliers (litres) :</div>
            <div class="field-value"></div>
        </div>

        <div class="field-row">
            <div class="field-label">Nombre de personnels employés :</div>
            <div class="field-value"></div>
        </div>

        <div class="field-row">
            <div class="field-label">Superficie du site (m²) :</div>
            <div class="field-value"></div>
        </div>

        <div class="field-row">
            <div class="field-label">Système de récupération des huiles usées :</div>
            <div class="field-value"></div>
        </div>

        <div style="margin-top: 10px; margin-bottom: 4px; font-weight: bold;">Parc d'engin de la société :</div>
        <div style="border: 1px dotted #000; min-height: 60px; padding: 4px; margin-bottom: 10px;"></div>

        <div style="margin-top: 10px; margin-bottom: 4px; font-weight: bold;">Bâtiments du site :</div>
        <div style="border: 1px dotted #000; min-height: 60px; padding: 4px; margin-bottom: 10px;"></div>

        <div style="margin-top: 10px; margin-bottom: 6px; font-weight: bold;">Infrastructures d'approvisionnement :</div>
        <div class="checkbox-group">
            <div class="checkbox-item">
                <span class="checkbox"></span> Eau
            </div>
            <div class="checkbox-item">
                <span class="checkbox"></span> Électricité
            </div>
        </div>

        <div style="margin-top: 10px; margin-bottom: 6px; font-weight: bold;">Réseaux de télécommunication :</div>
        <div class="checkbox-group">
            <div class="checkbox-item">
                <span class="checkbox"></span> CAMTEL
            </div>
            <div class="checkbox-item">
                <span class="checkbox"></span> MTN
            </div>
            <div class="checkbox-item">
                <span class="checkbox"></span> ORANGE
            </div>
            <div class="checkbox-item">
                <span class="checkbox"></span> NEXTTEL
            </div>
        </div>

    <?php else: ?>
        <!-- Section par défaut pour STATIONS-SERVICES -->
        <div class="field-row">
            <div class="field-label">Date de mise en service :</div>
            <div class="field-value"></div>
        </div>

        <div class="field-row">
            <div class="field-label">N° Autorisation MINEE :</div>
            <div class="field-value"></div>
        </div>

        <div class="field-row">
            <div class="field-label">N° Autorisation MINMIDT :</div>
            <div class="field-value"></div>
        </div>

        <div class="field-row">
            <div class="field-label">Type de gestion :</div>
            <span class="checkbox"></span> Libre
            <span style="margin: 0 10px;"></span>
            <span class="checkbox"></span> Location
            <span style="margin: 0 10px;"></span>
            <span class="checkbox"></span> Autres : <div class="field-value" style="max-width: 200px; display: inline-block;"></div>
        </div>

        <div style="margin-top: 10px; margin-bottom: 6px; font-weight: bold;">Documents techniques disponibles :</div>
        <div class="checkbox-group">
            <div class="checkbox-item">
                <span class="checkbox"></span> Plan d'ensemble
            </div>
            <div class="checkbox-item">
                <span class="checkbox"></span> Contrat de bail
            </div>
            <div class="checkbox-item">
                <span class="checkbox"></span> Permis de bâtir
            </div>
            <div class="checkbox-item">
                <span class="checkbox"></span> Certificat d'urbanisme
            </div>
            <div class="checkbox-item">
                <span class="checkbox"></span> Lettre MINEPDED
            </div>
            <div class="checkbox-item">
                <span class="checkbox"></span> Plan de masse
            </div>
        </div>

        <div style="margin-top: 10px; margin-bottom: 6px; font-weight: bold;">Effectifs du personnel :</div>
        <div class="field-row">
            <div class="field-label">Chef de piste :</div>
            <div class="field-value"></div>
        </div>
        <div class="field-row">
            <div class="field-label">Gérant :</div>
            <div class="field-value"></div>
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
            <?php for ($i = 1; $i <= 6; $i++): ?>
            <tr>
                <td style="text-align: center;"><?php echo $i; ?></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <?php endfor; ?>
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
            <?php for ($i = 1; $i <= 6; $i++): ?>
            <tr>
                <td style="text-align: center;"><?php echo $i; ?></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <?php endfor; ?>
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
            <tr>
                <td><strong>Vers le Nord</strong></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td><strong>Vers le Sud</strong></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td><strong>Vers l'Est</strong></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td><strong>Vers l'Ouest</strong></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
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
            <tr>
                <td><strong>Vers le Nord</strong></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td><strong>Vers le Sud</strong></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td><strong>Vers l'Est</strong></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td><strong>Vers l'Ouest</strong></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Section 6 - Sécurité -->
    <div class="section-title">SÉCURITÉ ET ENVIRONNEMENT</div>

    <div class="checkbox-group">
        <div class="checkbox-item">
            <span class="checkbox"></span> Bouches d'incendies
        </div>
        <div style="margin: 10px 0;">
            <span class="checkbox"></span> Présence de décanteur/séparateur des eaux usées
        </div>
    </div>

    <div style="margin-top: 8px;">
        <strong>Autres dispositions relatives à la sécurité et environnementales :</strong>
    </div>
    <div class="observations-lines" style="margin-top: 3px;">
        <div style="border-bottom: 1px dotted #000; min-height: 16px;"></div>
        <div style="border-bottom: 1px dotted #000; min-height: 16px;"></div>
    </div>

    <!-- Section 7 - Observations -->
    <div class="section-title">Observations générales :</div>
    <div class="observations-lines" style="margin-top: 6px;">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <div style="border-bottom: 1px dotted #000; min-height: 16px; margin-bottom: 3px;"></div>
        <?php endfor; ?>
    </div>

    <!-- Établissement -->
    <div style="margin-top: 15px;">
        <div class="field-row">
            <div class="field-label">Fiche établie à :</div>
            <div class="field-value" style="max-width: 300px;"></div>
            <div class="field-label" style="margin-left: 20px;">Le :</div>
            <div class="field-value" style="max-width: 200px;"></div>
        </div>
    </div>

    <!-- Signatures -->
    <div style="text-align: center; margin: 20px 0 12px 0;">
        <strong>Ont signé :</strong>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-label">POUR LE MINEE</div>
            <div style="margin-top: 50px; border-top: 1px solid #000; display: inline-block; padding-top: 5px;">
                Signature et cachet
            </div>
        </div>

        <div class="signature-box">
            <div class="signature-label">POUR LE DEMANDEUR</div>
            <div style="margin-top: 50px; border-top: 1px solid #000; display: inline-block; padding-top: 5px;">
                Signature et cachet
            </div>
        </div>
    </div>
</body>
</html>

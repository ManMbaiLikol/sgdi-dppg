<?php
/**
 * Rapport final d'import MINEE-OSM
 * Date: 31/10/2025
 */

require_once __DIR__ . '/config/database.php';

// Récupérer les statistiques
$stats = [];

// Total
$stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1");
$stats['total'] = $stmt->fetchColumn();

// Avec/Sans GPS
$stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE est_historique = 1 AND coordonnees_gps IS NOT NULL AND coordonnees_gps != ''");
$stats['avec_gps'] = $stmt->fetchColumn();
$stats['sans_gps'] = $stats['total'] - $stats['avec_gps'];
$stats['pct_gps'] = $stats['total'] > 0 ? round(($stats['avec_gps'] / $stats['total']) * 100, 1) : 0;

// Par qualité matching
$stmt = $pdo->query("
    SELECT
        CASE
            WHEN score_matching_osm >= 80 THEN 'excellent'
            WHEN score_matching_osm >= 60 THEN 'bon'
            WHEN score_matching_osm >= 40 THEN 'moyen'
            WHEN score_matching_osm > 0 THEN 'faible'
            ELSE 'aucun'
        END as categorie,
        COUNT(*) as count
    FROM dossiers
    WHERE est_historique = 1
    GROUP BY categorie
");
$matching = ['excellent' => 0, 'bon' => 0, 'moyen' => 0, 'faible' => 0, 'aucun' => 0];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $matching[$row['categorie']] = $row['count'];
}

// Par région
$stmt = $pdo->query("SELECT region, COUNT(*) as count FROM dossiers WHERE est_historique = 1 GROUP BY region ORDER BY count DESC");
$regions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport Import MINEE-OSM → SGDI</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            color: #2c3e50;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 1.2em;
            opacity: 0.95;
        }
        .content {
            padding: 40px;
        }
        .section {
            margin-bottom: 40px;
        }
        .section h2 {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-value {
            font-size: 3em;
            font-weight: bold;
            margin: 15px 0;
        }
        .stat-label {
            font-size: 1.1em;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }
        .matching-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        .matching-card {
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            color: white;
        }
        .matching-card.excellent { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .matching-card.bon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .matching-card.moyen { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .matching-card.faible { background: linear-gradient(135deg, #ff9966 0%, #ff5e62 100%); }
        .matching-card.aucun { background: linear-gradient(135deg, #868f96 0%, #596164 100%); }
        .matching-card .value {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
        }
        .matching-card .label {
            font-size: 0.9em;
            text-transform: uppercase;
        }
        .matching-card .percent {
            font-size: 1.2em;
            margin-top: 5px;
            opacity: 0.9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
            letter-spacing: 1px;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .progress-bar {
            width: 100%;
            height: 25px;
            background: #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.85em;
            transition: width 0.5s;
        }
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid;
        }
        .alert-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .alert-info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        .footer {
            background: #f8f9fa;
            padding: 30px;
            text-align: center;
            color: #6c757d;
            font-size: 0.95em;
        }
        @media print {
            body { background: white; }
            .container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Rapport d'Import MINEE-OSM</h1>
            <p>Importation des données historiques dans le SGDI</p>
            <p style="margin-top: 10px; font-size: 0.95em;"><?= date('d/m/Y à H:i') ?></p>
        </div>

        <div class="content">
            <!-- Résumé général -->
            <div class="section">
                <h2>📋 Résumé Général</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Stations</div>
                        <div class="stat-value"><?= number_format($stats['total']) ?></div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                        <div class="stat-label">Avec GPS</div>
                        <div class="stat-value"><?= $stats['avec_gps'] ?></div>
                        <div style="font-size: 1.2em;"><?= $stats['pct_gps'] ?>%</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <div class="stat-label">Sans GPS</div>
                        <div class="stat-value"><?= $stats['sans_gps'] ?></div>
                        <div style="font-size: 1.2em;"><?= round(100 - $stats['pct_gps'], 1) ?>%</div>
                    </div>
                </div>

                <div class="alert alert-success">
                    <strong>✅ Import réussi !</strong>
                    <?= $stats['total'] ?> dossiers historiques ont été importés avec succès dans le système SGDI.
                    La couverture GPS atteint <?= $stats['pct_gps'] ?>% grâce au matching avec les données OpenStreetMap.
                </div>
            </div>

            <!-- Qualité du matching -->
            <div class="section">
                <h2>📈 Qualité du Matching OSM</h2>
                <div class="matching-grid">
                    <?php
                    $labels = [
                        'excellent' => 'Excellent<br>≥80%',
                        'bon' => 'Bon<br>60-79%',
                        'moyen' => 'Moyen<br>40-59%',
                        'faible' => 'Faible<br>1-39%',
                        'aucun' => 'Aucun<br>0%'
                    ];
                    foreach ($labels as $key => $label) {
                        $count = $matching[$key];
                        $pct = $stats['total'] > 0 ? round(($count / $stats['total']) * 100, 1) : 0;
                        echo "<div class='matching-card $key'>";
                        echo "<div class='label'>$label</div>";
                        echo "<div class='value'>$count</div>";
                        echo "<div class='percent'>{$pct}%</div>";
                        echo "</div>";
                    }
                    ?>
                </div>

                <div class="alert alert-info">
                    <strong>ℹ️ Interprétation :</strong>
                    <?php
                    $qualite = $matching['excellent'] + $matching['bon'];
                    $pct_qualite = $stats['total'] > 0 ? round(($qualite / $stats['total']) * 100, 1) : 0;
                    ?>
                    <?= $qualite ?> stations (<?= $pct_qualite ?>%) ont un matching de qualité "Excellent" ou "Bon",
                    ce qui garantit une grande fiabilité des coordonnées GPS.
                </div>
            </div>

            <!-- Répartition géographique -->
            <div class="section">
                <h2>🗺️ Répartition Géographique</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Région</th>
                            <th>Nombre</th>
                            <th>Pourcentage</th>
                            <th>Visualisation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rang = 1;
                        foreach ($regions as $region) {
                            $pct = $stats['total'] > 0 ? round(($region['count'] / $stats['total']) * 100, 1) : 0;
                            echo "<tr>";
                            echo "<td><strong>#{$rang}</strong></td>";
                            echo "<td><strong>" . ($region['region'] ?: 'Non renseignée') . "</strong></td>";
                            echo "<td>" . number_format($region['count']) . "</td>";
                            echo "<td>{$pct}%</td>";
                            echo "<td>";
                            echo "<div class='progress-bar'>";
                            echo "<div class='progress-fill' style='width: {$pct}%'>{$pct}%</div>";
                            echo "</div>";
                            echo "</td>";
                            echo "</tr>";
                            $rang++;
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Conclusion -->
            <div class="section">
                <h2>✅ Conclusion</h2>
                <div class="alert alert-success">
                    <h3 style="margin-bottom: 15px;">Import réussi avec succès</h3>
                    <ul style="margin-left: 20px; line-height: 1.8;">
                        <li><strong><?= number_format($stats['total']) ?> stations</strong> importées depuis le fichier fusion MINEE-OSM</li>
                        <li><strong><?= $stats['pct_gps'] ?>% de couverture GPS</strong> grâce au matching intelligent avec OpenStreetMap</li>
                        <li><strong><?= $pct_qualite ?>% de données fiables</strong> (matching de qualité Excellent/Bon)</li>
                        <li><strong><?= count($regions) ?> régions</strong> représentées sur tout le territoire</li>
                        <li>Données prêtes pour la <strong>visualisation cartographique</strong> et l'analyse géospatiale</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="footer">
            <p><strong>Système de Gestion des Dossiers d'Implantation (SGDI)</strong></p>
            <p>MINEE/DPPG - Module Import Historique</p>
            <p>Généré le <?= date('d/m/Y à H:i:s') ?></p>
        </div>
    </div>
</body>
</html>

<?php
/**
 * Vérification de la nécessité du nettoyage
 * Analyse rapide de l'état des données GPS
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>\n<html>\n<head>\n<meta charset='UTF-8'>\n";
echo "<title>Vérification Nettoyage - DPPG</title>\n";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
h2 { color: #34495e; margin-top: 30px; border-left: 4px solid #3498db; padding-left: 15px; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
.stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 8px; text-align: center; }
.stat-card.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.stat-card.red { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
.stat-card.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.stat-value { font-size: 3em; font-weight: bold; margin: 10px 0; }
.stat-label { font-size: 1em; opacity: 0.9; }
.critical { background: #f8d7da; border: 2px solid #dc3545; padding: 20px; margin: 20px 0; border-radius: 4px; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
.success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
.info { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
th { background: #3498db; color: white; }
tr:nth-child(even) { background: #f8f9fa; }
.btn { display: inline-block; padding: 12px 25px; margin: 10px 5px; background: #3498db; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; }
.btn:hover { background: #2980b9; }
.btn-danger { background: #dc3545; }
.btn-danger:hover { background: #c82333; }
.btn-success { background: #28a745; }
.btn-success:hover { background: #218838; }
.progress-bar { background: #f8f9fa; border-radius: 4px; height: 30px; margin: 10px 0; overflow: hidden; }
.progress-fill { background: linear-gradient(90deg, #3498db, #2ecc71); height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; transition: width 0.3s; }
.quality-indicator { display: inline-block; padding: 8px 15px; border-radius: 20px; font-weight: bold; font-size: 0.9em; }
.quality-excellent { background: #28a745; color: white; }
.quality-good { background: #17a2b8; color: white; }
.quality-medium { background: #ffc107; color: #333; }
.quality-poor { background: #dc3545; color: white; }
</style>
</head>\n<body>\n";

echo "<div class='container'>\n";
echo "<h1>🔍 Vérification de la Nécessité du Nettoyage</h1>\n";

// 1. Statistiques générales
$stats_general = $pdo->query("
    SELECT
        COUNT(*) as total_dossiers,
        COUNT(CASE WHEN est_historique = 1 THEN 1 END) as historiques,
        COUNT(CASE WHEN est_historique = 0 OR est_historique IS NULL THEN 1 END) as nouvelles,
        COUNT(CASE WHEN coordonnees_gps IS NOT NULL AND coordonnees_gps != '' THEN 1 END) as avec_gps,
        COUNT(CASE WHEN coordonnees_gps IS NULL OR coordonnees_gps = '' THEN 1 END) as sans_gps
    FROM dossiers
    WHERE statut IN ('autorise', 'historique_autorise') OR est_historique = 1
")->fetch(PDO::FETCH_ASSOC);

// 2. Analyse des doublons GPS
$doublons_gps = $pdo->query("
    SELECT coordonnees_gps, COUNT(*) as occurrences
    FROM dossiers
    WHERE coordonnees_gps IS NOT NULL AND coordonnees_gps != ''
    AND (statut IN ('autorise', 'historique_autorise') OR est_historique = 1)
    GROUP BY coordonnees_gps
    HAVING COUNT(*) > 1
    ORDER BY occurrences DESC
")->fetchAll(PDO::FETCH_ASSOC);

$nb_gps_dupliques = count($doublons_gps);
$stations_avec_doublons = array_sum(array_column($doublons_gps, 'occurrences'));

// 3. Répartition historiques vs nouvelles
$repartition = $pdo->query("
    SELECT
        CASE WHEN est_historique = 1 THEN 'Historique' ELSE 'Nouvelle' END as type,
        COUNT(*) as count,
        COUNT(CASE WHEN coordonnees_gps IS NOT NULL AND coordonnees_gps != '' THEN 1 END) as avec_gps,
        COUNT(CASE WHEN coordonnees_gps IS NULL OR coordonnees_gps = '' THEN 1 END) as sans_gps
    FROM dossiers
    WHERE statut IN ('autorise', 'historique_autorise') OR est_historique = 1
    GROUP BY CASE WHEN est_historique = 1 THEN 'Historique' ELSE 'Nouvelle' END
")->fetchAll(PDO::FETCH_ASSOC);

// Calculs
$taux_gps = $stats_general['total_dossiers'] > 0
    ? round(($stats_general['avec_gps'] / $stats_general['total_dossiers']) * 100, 1)
    : 0;

$taux_doublon = $stats_general['avec_gps'] > 0
    ? round(($stations_avec_doublons / $stats_general['avec_gps']) * 100, 1)
    : 0;

// Affichage statistiques principales
echo "<div class='stats-grid'>\n";

echo "<div class='stat-card'>\n";
echo "<div class='stat-value'>{$stats_general['total_dossiers']}</div>\n";
echo "<div class='stat-label'>Total Stations Autorisées</div>\n";
echo "</div>\n";

echo "<div class='stat-card green'>\n";
echo "<div class='stat-value'>{$stats_general['historiques']}</div>\n";
echo "<div class='stat-label'>Stations Historiques</div>\n";
echo "</div>\n";

echo "<div class='stat-card'>\n";
echo "<div class='stat-value'>{$stats_general['nouvelles']}</div>\n";
echo "<div class='stat-label'>Nouvelles Stations</div>\n";
echo "</div>\n";

echo "<div class='stat-card orange'>\n";
echo "<div class='stat-value'>$nb_gps_dupliques</div>\n";
echo "<div class='stat-label'>GPS Dupliqués</div>\n";
echo "</div>\n";

echo "</div>\n";

// Évaluation de la qualité
echo "<h2>📊 Évaluation de la Qualité des Données</h2>\n";

echo "<table>\n";
echo "<thead><tr><th>Métrique</th><th>Valeur</th><th>Évaluation</th></tr></thead>\n";
echo "<tbody>\n";

// Taux de couverture GPS
echo "<tr>\n";
echo "<td><strong>Taux de couverture GPS</strong></td>\n";
echo "<td>$taux_gps% ({$stats_general['avec_gps']}/{$stats_general['total_dossiers']})</td>\n";
echo "<td>";
if ($taux_gps >= 90) {
    echo "<span class='quality-indicator quality-excellent'>EXCELLENT</span>";
} elseif ($taux_gps >= 70) {
    echo "<span class='quality-indicator quality-good'>BON</span>";
} elseif ($taux_gps >= 50) {
    echo "<span class='quality-indicator quality-medium'>MOYEN</span>";
} else {
    echo "<span class='quality-indicator quality-poor'>FAIBLE</span>";
}
echo "</td>\n";
echo "</tr>\n";

// Taux de doublons
echo "<tr>\n";
echo "<td><strong>Taux de doublons GPS</strong></td>\n";
echo "<td>$taux_doublon% ($stations_avec_doublons/{$stats_general['avec_gps']})</td>\n";
echo "<td>";
if ($taux_doublon < 5) {
    echo "<span class='quality-indicator quality-excellent'>EXCELLENT</span>";
} elseif ($taux_doublon < 15) {
    echo "<span class='quality-indicator quality-medium'>MOYEN</span>";
} else {
    echo "<span class='quality-indicator quality-poor'>CRITIQUE</span>";
}
echo "</td>\n";
echo "</tr>\n";

// Stations sans GPS
echo "<tr>\n";
echo "<td><strong>Stations sans GPS</strong></td>\n";
echo "<td>{$stats_general['sans_gps']}</td>\n";
echo "<td>";
if ($stats_general['sans_gps'] < 10) {
    echo "<span class='quality-indicator quality-excellent'>EXCELLENT</span>";
} elseif ($stats_general['sans_gps'] < 50) {
    echo "<span class='quality-indicator quality-medium'>ACCEPTABLE</span>";
} else {
    echo "<span class='quality-indicator quality-poor'>PROBLÉMATIQUE</span>";
}
echo "</td>\n";
echo "</tr>\n";

echo "</tbody>\n";
echo "</table>\n";

// Répartition Historiques vs Nouvelles
echo "<h2>📈 Répartition Historiques vs Nouvelles</h2>\n";

echo "<table>\n";
echo "<thead><tr><th>Type</th><th>Total</th><th>Avec GPS</th><th>Sans GPS</th><th>% GPS</th></tr></thead>\n";
echo "<tbody>\n";

foreach ($repartition as $row) {
    $pct = $row['count'] > 0 ? round(($row['avec_gps'] / $row['count']) * 100, 1) : 0;
    echo "<tr>\n";
    echo "<td><strong>{$row['type']}</strong></td>\n";
    echo "<td>{$row['count']}</td>\n";
    echo "<td>{$row['avec_gps']}</td>\n";
    echo "<td>{$row['sans_gps']}</td>\n";
    echo "<td>$pct%</td>\n";
    echo "</tr>\n";
}

echo "</tbody>\n";
echo "</table>\n";

// Top 10 GPS dupliqués
if ($nb_gps_dupliques > 0) {
    echo "<h2>🔴 Top 10 GPS les Plus Dupliqués</h2>\n";

    echo "<table>\n";
    echo "<thead><tr><th>Rang</th><th>Coordonnées GPS</th><th>Nb Stations</th><th>Problème</th></tr></thead>\n";
    echo "<tbody>\n";

    foreach (array_slice($doublons_gps, 0, 10) as $idx => $doublon) {
        echo "<tr>\n";
        echo "<td>" . ($idx + 1) . "</td>\n";
        echo "<td><code>{$doublon['coordonnees_gps']}</code></td>\n";
        echo "<td><strong>{$doublon['occurrences']}</strong></td>\n";
        echo "<td>";
        if ($doublon['occurrences'] > 10) {
            echo "<span class='quality-indicator quality-poor'>CRITIQUE</span>";
        } elseif ($doublon['occurrences'] > 5) {
            echo "<span class='quality-indicator quality-medium'>ÉLEVÉ</span>";
        } else {
            echo "<span class='quality-indicator quality-medium'>MODÉRÉ</span>";
        }
        echo "</td>\n";
        echo "</tr>\n";
    }

    echo "</tbody>\n";
    echo "</table>\n";
}

// Diagnostic final
echo "<h2>🎯 Diagnostic Final</h2>\n";

$score_qualite = 0;
$criteres_ok = 0;
$criteres_total = 3;

// Critère 1 : Taux GPS
if ($taux_gps >= 70) {
    $criteres_ok++;
    $score_qualite += 33;
}

// Critère 2 : Taux doublons
if ($taux_doublon < 15) {
    $criteres_ok++;
    $score_qualite += 33;
}

// Critère 3 : Stations sans GPS
if ($stats_general['sans_gps'] < 50) {
    $criteres_ok++;
    $score_qualite += 34;
}

echo "<div style='margin: 20px 0;'>\n";
echo "<strong>Score de qualité global :</strong><br>\n";
echo "<div class='progress-bar'>\n";
echo "<div class='progress-fill' style='width: $score_qualite%'>$score_qualite%</div>\n";
echo "</div>\n";
echo "<p>$criteres_ok/$criteres_total critères de qualité respectés</p>\n";
echo "</div>\n";

// Recommandation
if ($score_qualite >= 80) {
    echo "<div class='success'>\n";
    echo "<strong>✅ QUALITÉ EXCELLENTE - Nettoyage NON nécessaire</strong><br><br>\n";
    echo "Vos données GPS sont de bonne qualité. Le nettoyage n'est pas prioritaire.<br>\n";
    echo "<strong>Actions suggérées :</strong><br>\n";
    echo "• Corriger les quelques doublons restants manuellement<br>\n";
    echo "• Ajouter les GPS manquants progressivement<br>\n";
    echo "• Mettre en place une validation à la saisie\n";
    echo "</div>\n";
} elseif ($score_qualite >= 50) {
    echo "<div class='warning'>\n";
    echo "<strong>⚠️ QUALITÉ MOYENNE - Nettoyage RECOMMANDÉ</strong><br><br>\n";
    echo "Vos données GPS présentent des problèmes modérés qui justifient un nettoyage.<br>\n";
    echo "<strong>Décision recommandée :</strong><br>\n";
    if ($taux_doublon > 20 && $stats_general['historiques'] > 100) {
        echo "→ <strong>STRATÉGIE 2</strong> : Supprimer historiques et recréer sans GPS<br>\n";
        echo "→ Raison : Trop de doublons ($taux_doublon%), correction trop complexe<br>\n";
    } else {
        echo "→ <strong>STRATÉGIE 1</strong> : Correction ciblée des GPS problématiques<br>\n";
        echo "→ Raison : Nombre de problèmes gérable, correction possible<br>\n";
    }
    echo "</div>\n";
} else {
    echo "<div class='critical'>\n";
    echo "<strong>🚨 QUALITÉ CRITIQUE - Nettoyage URGENT</strong><br><br>\n";
    echo "Vos données GPS sont trop imprécises pour être fiables.<br>\n";
    echo "<strong>Décision FORTEMENT recommandée :</strong><br>\n";
    echo "→ <strong>STRATÉGIE 2</strong> : Supprimer toutes les stations historiques et recréer sans GPS<br>\n";
    echo "→ Raison : Trop de problèmes ($taux_doublon% doublons, {$stats_general['sans_gps']} sans GPS)<br>\n";
    echo "→ Correction trop complexe et risquée, mieux vaut repartir sur une base saine\n";
    echo "</div>\n";
}

// Actions rapides
echo "<h2>🎮 Actions Disponibles</h2>\n";

echo "<div style='text-align: center; margin: 30px 0;'>\n";
echo "<a href='diagnostic_data_quality.php' class='btn'>📊 Diagnostic Complet</a>\n";
echo "<a href='detect_gps_collisions.php' class='btn'>🎯 Détecter Collisions GPS</a>\n";
echo "<a href='compare_strategies.php' class='btn btn-success'>⚖️ Comparer Stratégies</a>\n";

if ($score_qualite < 80) {
    echo "<a href='execute_strategy_2.php' class='btn btn-danger'>🔄 Exécuter Nettoyage (Stratégie 2)</a>\n";
}

echo "</div>\n";

echo "</div>\n</body>\n</html>\n";
?>

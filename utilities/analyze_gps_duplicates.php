<?php
require_once __DIR__ . '/config/database.php';

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║    ANALYSE DES DOUBLONS DE COORDONNÉES GPS                  ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

// 1. Trouver toutes les coordonnées en doublon
$stmt = $pdo->query("
    SELECT
        coordonnees_gps,
        COUNT(*) as count,
        GROUP_CONCAT(numero SEPARATOR ', ') as dossiers,
        GROUP_CONCAT(DISTINCT ville SEPARATOR ', ') as villes,
        GROUP_CONCAT(DISTINCT region SEPARATOR ', ') as regions
    FROM dossiers
    WHERE coordonnees_gps IS NOT NULL AND coordonnees_gps != ''
    GROUP BY coordonnees_gps
    HAVING COUNT(*) > 1
    ORDER BY count DESC
");

$doublons = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📊 STATISTIQUES GLOBALES\n";
echo str_repeat("─", 70) . "\n";

// Total des stations avec GPS
$total_with_gps = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE coordonnees_gps IS NOT NULL AND coordonnees_gps != ''")->fetchColumn();

// Stations avec coordonnées uniques
$unique_coords = $pdo->query("
    SELECT COUNT(DISTINCT coordonnees_gps)
    FROM dossiers
    WHERE coordonnees_gps IS NOT NULL AND coordonnees_gps != ''
")->fetchColumn();

$affected = 0;
foreach ($doublons as $d) {
    $affected += $d['count'];
}

echo "Total stations avec GPS   : $total_with_gps\n";
echo "Coordonnées uniques       : $unique_coords\n";
echo "Groupes de doublons       : " . count($doublons) . "\n";
echo "Stations affectées        : $affected (" . round(($affected / $total_with_gps) * 100, 1) . "%)\n";
echo "\n";

echo "🔴 TOP 20 - COORDONNÉES LES PLUS DUPLIQUÉES\n";
echo str_repeat("─", 70) . "\n\n";

$top = array_slice($doublons, 0, 20);
$rank = 1;

foreach ($top as $doublon) {
    $coords = explode(',', $doublon['coordonnees_gps']);
    $lat = trim($coords[0]);
    $lng = trim($coords[1]);

    echo "#{$rank}. GPS: {$lat}, {$lng}\n";
    echo "    Nombre de stations : {$doublon['count']}\n";
    echo "    Villes concernées  : " . (strlen($doublon['villes']) > 60 ? substr($doublon['villes'], 0, 60) . '...' : $doublon['villes']) . "\n";
    echo "    Régions            : {$doublon['regions']}\n";

    // Afficher quelques dossiers
    $dossiers_list = explode(', ', $doublon['dossiers']);
    $sample = array_slice($dossiers_list, 0, 5);
    echo "    Exemples dossiers  : " . implode(', ', $sample);
    if (count($dossiers_list) > 5) {
        echo " (+" . (count($dossiers_list) - 5) . " autres)";
    }
    echo "\n\n";
    $rank++;
}

// Recommandations
echo "\n";
echo "💡 RECOMMANDATIONS\n";
echo str_repeat("─", 70) . "\n";
echo "1. ⚠️  Le matching OSM a créé de nombreux doublons de coordonnées\n";
echo "2. 🔧 Il faut affiner l'algorithme pour éviter les réutilisations\n";
echo "3. 📍 Utiliser un système de décalage pour les stations sur même site\n";
echo "4. 🗺️  Ajouter une vérification \"une coordonnée = une station\"\n";
echo "5. 🔄 Relancer le matching avec contraintes d'unicité\n";

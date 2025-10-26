<?php
require_once 'config/database.php';

echo "=== Checking Dossier ID 17 ===\n\n";

// Get dossier info
$stmt = $pdo->prepare("SELECT id, type_infrastructure, sous_type FROM dossiers WHERE id = 17");
$stmt->execute();
$dossier = $stmt->fetch(PDO::FETCH_ASSOC);

if ($dossier) {
    echo "Dossier found:\n";
    echo "  ID: " . $dossier['id'] . "\n";
    echo "  type_infrastructure: '" . $dossier['type_infrastructure'] . "'\n";
    echo "  sous_type: '" . $dossier['sous_type'] . "'\n\n";

    // Check if it matches point_consommateur
    $is_point_consommateur = ($dossier['type_infrastructure'] === 'point_consommateur');
    echo "  Is point_consommateur? " . ($is_point_consommateur ? 'YES' : 'NO') . "\n\n";
} else {
    echo "Dossier not found\n\n";
}

// Get fiche info
echo "=== Checking Fiche Inspection for Dossier 17 ===\n\n";
$stmt = $pdo->prepare("SELECT id, numero_contrat_approvisionnement, societe_contractante,
    besoins_mensuels_litres, nombre_personnels, superficie_site,
    recommandations
    FROM fiches_inspection WHERE dossier_id = 17");
$stmt->execute();
$fiche = $stmt->fetch(PDO::FETCH_ASSOC);

if ($fiche) {
    echo "Fiche found (ID: " . $fiche['id'] . "):\n";
    echo "  numero_contrat_approvisionnement: '" . ($fiche['numero_contrat_approvisionnement'] ?? 'NULL') . "'\n";
    echo "  societe_contractante: '" . ($fiche['societe_contractante'] ?? 'NULL') . "'\n";
    echo "  besoins_mensuels_litres: " . ($fiche['besoins_mensuels_litres'] ?? 'NULL') . "\n";
    echo "  nombre_personnels: " . ($fiche['nombre_personnels'] ?? 'NULL') . "\n";
    echo "  superficie_site: " . ($fiche['superficie_site'] ?? 'NULL') . "\n";
    echo "  recommandations: '" . substr($fiche['recommandations'] ?? '', 0, 100) . "'\n";
} else {
    echo "Fiche not found\n";
}

// List all valid types
echo "\n\n=== Valid Infrastructure Types ===\n\n";
$stmt = $pdo->query("SELECT id, type, sous_type FROM types_infrastructure ORDER BY id");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . '. ' . $row['type'];
    if ($row['sous_type']) {
        echo ' (' . $row['sous_type'] . ')';
    }
    echo "\n";
}

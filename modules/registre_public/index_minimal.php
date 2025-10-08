<?php
// Version minimale pour tester
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Tester si modules/dossiers/functions.php existe
$functions_path = '../../modules/dossiers/functions.php';
if (file_exists($functions_path)) {
    echo "✓ Le fichier functions.php existe<br>";
    require_once $functions_path;
} else {
    echo "✗ Le fichier functions.php n'existe PAS au chemin: " . realpath(__DIR__ . '/' . $functions_path) . "<br>";
    die("Impossible de continuer");
}

// Test fonction
if (function_exists('getTypeInfrastructureLabel')) {
    echo "✓ La fonction getTypeInfrastructureLabel existe<br>";
    echo "Test: " . getTypeInfrastructureLabel('station_service') . "<br>";
} else {
    echo "✗ La fonction getTypeInfrastructureLabel n'existe pas<br>";
}

echo "<hr>";
echo "<h2>Infrastructures autorisées</h2>";

// Requête simple
$sql = "SELECT * FROM dossiers WHERE statut = 'autorise' LIMIT 5";
$stmt = $pdo->query($sql);
$dossiers = $stmt->fetchAll();

echo "<p>Nombre de dossiers: " . count($dossiers) . "</p>";

foreach($dossiers as $d) {
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
    echo "<strong>N°:</strong> " . htmlspecialchars($d['numero']) . "<br>";
    echo "<strong>Type:</strong> " . htmlspecialchars($d['type_infrastructure']) . "<br>";
    echo "<strong>Nom:</strong> " . htmlspecialchars($d['nom_demandeur']) . "<br>";
    echo "</div>";
}
?>

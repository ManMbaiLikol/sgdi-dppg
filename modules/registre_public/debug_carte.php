<?php
/**
 * Page de debug pour la carte - À utiliser temporairement sur Railway
 * URL: /modules/registre_public/debug_carte.php
 */
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/map_functions.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Debug Carte - Railway</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #ddd; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
        h2 { color: #333; border-bottom: 2px solid #0066cc; padding-bottom: 5px; }
    </style>
</head>
<body>

<h1>🔍 Debug Carte du Lecteur - Railway</h1>

<?php
// Test 1 : Connexion base de données
echo '<div class="section">';
echo '<h2>1. Connexion Base de Données</h2>';
try {
    $test = $pdo->query("SELECT 1")->fetchColumn();
    echo '<p class="success">✓ Connexion OK</p>';
    echo '<p>Base de données: ' . DB_NAME . '</p>';
    echo '<p>Host: ' . DB_HOST . '</p>';
} catch (Exception $e) {
    echo '<p class="error">✗ Erreur de connexion: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '</div>';

// Test 2 : Vérifier le dossier SS2025091201
echo '<div class="section">';
echo '<h2>2. Vérification du dossier SS2025091201</h2>';
$stmt = $pdo->prepare("SELECT * FROM dossiers WHERE numero = 'SS2025091201'");
$stmt->execute();
$dossier = $stmt->fetch();

if ($dossier) {
    echo '<p class="success">✓ Dossier trouvé</p>';
    echo '<pre>';
    echo "Numéro: " . $dossier['numero'] . "\n";
    echo "Type: " . $dossier['type_infrastructure'] . "\n";
    echo "Nom: " . $dossier['nom_demandeur'] . "\n";
    echo "Statut: " . $dossier['statut'] . "\n";
    echo "Coordonnées GPS: " . ($dossier['coordonnees_gps'] ?: '(vide)') . "\n";
    echo "Opérateur: " . ($dossier['operateur_proprietaire'] ?: '(vide)') . "\n";
    echo '</pre>';
} else {
    echo '<p class="error">✗ Dossier NON trouvé</p>';
}
echo '</div>';

// Test 3 : Fonction getAllInfrastructuresForMap
echo '<div class="section">';
echo '<h2>3. Test getAllInfrastructuresForMap()</h2>';

$filters = [
    'statut' => 'autorise',
    'type_infrastructure' => '',
    'region' => ''
];

echo '<p>Filtres appliqués:</p>';
echo '<pre>' . print_r($filters, true) . '</pre>';

$infrastructures = getAllInfrastructuresForMap($filters);

echo '<p>Nombre d\'infrastructures retournées: <strong>' . count($infrastructures) . '</strong></p>';

if (count($infrastructures) > 0) {
    echo '<p class="success">✓ Des infrastructures sont retournées</p>';
    echo '<p>Détails des infrastructures:</p>';
    echo '<pre>' . print_r($infrastructures, true) . '</pre>';

    // Vérifier si SS2025091201 est dans la liste
    $trouve = false;
    foreach ($infrastructures as $infra) {
        if ($infra['numero'] === 'SS2025091201') {
            $trouve = true;
            echo '<p class="success">✓ Le dossier SS2025091201 EST dans la liste</p>';
            break;
        }
    }
    if (!$trouve) {
        echo '<p class="error">✗ Le dossier SS2025091201 N\'EST PAS dans la liste</p>';
    }
} else {
    echo '<p class="error">✗ Aucune infrastructure retournée</p>';
}
echo '</div>';

// Test 4 : JSON encode (ce qui sera envoyé au JavaScript)
echo '<div class="section">';
echo '<h2>4. JSON envoyé au JavaScript</h2>';
$json = json_encode($infrastructures);
if ($json === false) {
    echo '<p class="error">✗ Erreur JSON: ' . json_last_error_msg() . '</p>';
} else {
    echo '<p class="success">✓ JSON valide</p>';
    echo '<p>Taille du JSON: ' . strlen($json) . ' caractères</p>';
    echo '<pre>' . htmlspecialchars($json) . '</pre>';
}
echo '</div>';

// Test 5 : Requête SQL brute
echo '<div class="section">';
echo '<h2>5. Requête SQL brute</h2>';
$sql = "SELECT id, numero, type_infrastructure, sous_type, nom_demandeur,
               ville, region, coordonnees_gps, statut, date_creation,
               operateur_proprietaire
        FROM dossiers
        WHERE coordonnees_gps IS NOT NULL
        AND coordonnees_gps != ''
        AND statut = 'autorise'
        ORDER BY date_creation DESC";

echo '<p>Requête SQL:</p>';
echo '<pre>' . htmlspecialchars($sql) . '</pre>';

$stmt = $pdo->query($sql);
$results = $stmt->fetchAll();

echo '<p>Résultats: <strong>' . count($results) . '</strong> ligne(s)</p>';
if (count($results) > 0) {
    echo '<pre>' . print_r($results, true) . '</pre>';
}
echo '</div>';

// Test 6 : Variables d'environnement
echo '<div class="section">';
echo '<h2>6. Variables d\'environnement</h2>';
echo '<pre>';
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Database: " . DB_NAME . "\n";
echo "Host: " . DB_HOST . "\n";
echo "Port: " . DB_PORT . "\n";
echo '</pre>';
echo '</div>';
?>

<div class="section">
    <h2>7. Actions à faire</h2>
    <?php if (count($infrastructures) > 0): ?>
        <p class="success">✓ Les données sont disponibles en PHP</p>
        <p>Si la carte est vide, le problème est dans le JavaScript</p>
        <p>Actions :</p>
        <ol>
            <li>Vider le cache du navigateur (Ctrl+Shift+R)</li>
            <li>Vérifier la console JavaScript pour des erreurs</li>
            <li>Vérifier que Leaflet charge correctement</li>
        </ol>
    <?php else: ?>
        <p class="error">✗ Aucune donnée disponible en PHP</p>
        <p>Le problème est côté serveur/base de données</p>
        <p>Actions :</p>
        <ol>
            <li>Vérifier que le dossier a bien le statut 'autorise'</li>
            <li>Vérifier que les coordonnées GPS existent</li>
            <li>Redémarrer le service Railway</li>
        </ol>
    <?php endif; ?>

    <p><a href="carte.php">← Retour à la carte</a></p>
</div>

</body>
</html>

<?php
/**
 * Script d'exécution de la suite de tests
 * Lance tous les tests disponibles et affiche un résumé
 */

// Mode CLI ou HTTP
$is_cli = php_sapi_name() === 'cli';
$token_required = 'sgdi_test_2025';

if (!$is_cli) {
    // En mode HTTP, vérifier le token de sécurité
    if (!isset($_GET['token']) || $_GET['token'] !== $token_required) {
        http_response_code(403);
        die('❌ Accès refusé. Token requis.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║               SGDI - SUITE DE TESTS COMPLÈTE                       ║\n";
echo "║               Système de Gestion des Dossiers d'Implantation      ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$test_files = [
    'permissions/test_dossiers_permissions.php' => 'Tests Permissions Dossiers'
];

$total_passed = 0;
$total_failed = 0;

foreach ($test_files as $file => $description) {
    $filepath = __DIR__ . '/' . $file;

    if (!file_exists($filepath)) {
        echo "⚠️  Fichier de test introuvable: $file\n";
        continue;
    }

    echo "🧪 Exécution: $description\n";
    echo str_repeat("-", 70) . "\n";

    // Capturer la sortie du test
    ob_start();
    $_GET['run'] = 'tests';
    include $filepath;
    $output = ob_get_clean();

    echo $output;

    // Analyser le résultat (basique)
    if (strpos($output, 'TOUS LES TESTS SONT PASSÉS') !== false) {
        $total_passed++;
    } else {
        $total_failed++;
    }

    echo "\n";
}

// Résumé global
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║                    RÉSUMÉ GLOBAL                                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Suites de tests exécutées: " . ($total_passed + $total_failed) . "\n";
echo "✅ Suites réussies: $total_passed\n";
echo "❌ Suites échouées: $total_failed\n";
echo "\n";

if ($total_failed === 0) {
    echo "🎉🎉🎉 SUCCÈS COMPLET! 🎉🎉🎉\n";
    echo "Toutes les suites de tests ont réussi.\n";
} else {
    echo "⚠️  ATTENTION: $total_failed suite(s) de tests ont échoué!\n";
}

echo "\n" . str_repeat("=", 70) . "\n";

exit($total_failed === 0 ? 0 : 1);
?>

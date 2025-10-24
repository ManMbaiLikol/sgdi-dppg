<?php
/**
 * ENDPOINT TEMPORAIRE pour vérifier l'ENUM chef_commission_role
 * ⚠️ À SUPPRIMER après utilisation!
 */

define('CHECK_TOKEN', 'sgdi_migration_2025');

if (!isset($_GET['token']) || $_GET['token'] !== CHECK_TOKEN) {
    http_response_code(403);
    die('❌ Accès refusé');
}

header('Content-Type: text/plain; charset=utf-8');

$host = getenv('MYSQL_HOST');
$port = getenv('MYSQL_PORT') ?: '3306';
$user = getenv('MYSQL_USER');
$password = getenv('MYSQL_PASSWORD');
$database = getenv('MYSQL_DATABASE');

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "═══════════════════════════════════════\n";
    echo "  VÉRIFICATION ENUM chef_commission_role\n";
    echo "═══════════════════════════════════════\n\n";

    // Vérifier l'ENUM
    $stmt = $pdo->query("SHOW COLUMNS FROM commissions LIKE 'chef_commission_role'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($column) {
        echo "Type de colonne: " . $column['Type'] . "\n\n";

        preg_match("/^enum\((.+)\)$/i", $column['Type'], $matches);
        if ($matches) {
            $enum_values = str_getcsv($matches[1], ',', "'");
            echo "Rôles disponibles (" . count($enum_values) . "):\n";
            foreach ($enum_values as $value) {
                echo "  ✅ $value\n";
            }

            echo "\n";

            // Vérifier si on a bien les 4 rôles
            $expected = ['chef_service', 'chef_commission', 'sous_directeur', 'directeur'];
            $missing = array_diff($expected, $enum_values);

            if (empty($missing)) {
                echo "✅✅✅ MIGRATION RÉUSSIE! ✅✅✅\n";
                echo "Tous les rôles attendus sont présents.\n";
            } else {
                echo "⚠️ Rôles manquants:\n";
                foreach ($missing as $role) {
                    echo "  ❌ $role\n";
                }
            }
        }
    } else {
        echo "❌ Colonne chef_commission_role non trouvée\n";
    }

    echo "\n═══════════════════════════════════════\n";

} catch (PDOException $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>

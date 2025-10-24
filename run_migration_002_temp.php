<?php
/**
 * ENDPOINT TEMPORAIRE pour exécuter la migration 002 sur Railway
 *
 * ⚠️ SÉCURITÉ: À SUPPRIMER après utilisation!
 *
 * URL: https://sgdi-dppg-production.up.railway.app/run_migration_002_temp.php?token=TOKEN
 */

// Token de sécurité
define('MIGRATION_TOKEN', 'sgdi_migration_2025');

// Vérifier le token
if (!isset($_GET['token']) || $_GET['token'] !== MIGRATION_TOKEN) {
    http_response_code(403);
    die('❌ Accès refusé. Token invalide.');
}

// Headers pour affichage texte
header('Content-Type: text/plain; charset=utf-8');

echo "===========================================\n";
echo "Migration 002: Correction ENUM chef_commission_role\n";
echo "===========================================\n\n";

// Connexion via variables d'environnement Railway
$host = getenv('MYSQL_HOST');
$port = getenv('MYSQL_PORT') ?: '3306';
$user = getenv('MYSQL_USER');
$password = getenv('MYSQL_PASSWORD');
$database = getenv('MYSQL_DATABASE');

if (!$host || !$user || !$database) {
    die("❌ ERREUR: Variables d'environnement Railway manquantes!\n");
}

echo "Connexion à la base Railway...\n";
echo "  Host: $host\n";
echo "  Database: $database\n\n";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "✅ Connexion établie\n\n";

    // État AVANT migration
    echo "État AVANT migration:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM commissions LIKE 'chef_commission_role'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($column) {
        echo "  Type actuel: " . $column['Type'] . "\n\n";
    }

    // Exécuter la migration
    $sql = "ALTER TABLE commissions
            MODIFY COLUMN chef_commission_role ENUM(
                'chef_service',
                'chef_commission',
                'sous_directeur',
                'directeur'
            ) NOT NULL";

    echo "Exécution de la migration... ";
    $pdo->exec($sql);
    echo "✅ OK\n\n";

    // État APRÈS migration
    echo "État APRÈS migration:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM commissions LIKE 'chef_commission_role'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($column) {
        echo "  Type actuel: " . $column['Type'] . "\n\n";

        preg_match("/^enum\((.+)\)$/i", $column['Type'], $matches);
        if ($matches) {
            $enum_values = str_getcsv($matches[1], ',', "'");
            echo "Rôles disponibles pour chef de commission:\n";
            foreach ($enum_values as $value) {
                echo "  ✅ $value\n";
            }

            echo "\n✅ Total: " . count($enum_values) . " rôles\n";
        }
    }

    echo "\n✅✅✅ Migration 002 terminée avec succès! ✅✅✅\n\n";
    echo "⚠️  IMPORTANT: Supprimez maintenant ce fichier:\n";
    echo "   git rm run_migration_002_temp.php\n";
    echo "   git commit -m \"Remove: Migration temp file\"\n";
    echo "   git push origin main\n\n";

} catch (PDOException $e) {
    http_response_code(500);
    echo "\n❌ ERREUR FATALE lors de la migration:\n";
    echo $e->getMessage() . "\n\n";
    exit(1);
}
?>

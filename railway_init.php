<?php
/**
 * Script d'initialisation pour Railway.app
 *
 * Ce script affiche les informations de connexion Railway
 * et permet de vérifier que la configuration fonctionne
 *
 * IMPORTANT : Supprimez ce fichier après utilisation pour des raisons de sécurité
 */

// Afficher les erreurs pour debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Railway.app - Vérification Configuration</h1>";

// Vérifier les variables d'environnement
echo "<h2>1. Variables d'Environnement MySQL</h2>";
echo "<ul>";
echo "<li><strong>MYSQL_HOST:</strong> " . (getenv('MYSQL_HOST') ? '✅ Configuré' : '❌ Non configuré') . "</li>";
echo "<li><strong>MYSQL_DATABASE:</strong> " . (getenv('MYSQL_DATABASE') ? '✅ Configuré' : '❌ Non configuré') . "</li>";
echo "<li><strong>MYSQL_USER:</strong> " . (getenv('MYSQL_USER') ? '✅ Configuré' : '❌ Non configuré') . "</li>";
echo "<li><strong>MYSQL_PASSWORD:</strong> " . (getenv('MYSQL_PASSWORD') ? '✅ Configuré' : '❌ Non configuré') . "</li>";
echo "</ul>";

// Tester la connexion
echo "<h2>2. Test de Connexion à la Base de Données</h2>";

try {
    $host = getenv('MYSQL_HOST') ?: 'localhost';
    $dbname = getenv('MYSQL_DATABASE') ?: 'sgdi_mvp';
    $user = getenv('MYSQL_USER') ?: 'root';
    $pass = getenv('MYSQL_PASSWORD') ?: '';

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "<p style='color: green;'>✅ <strong>Connexion réussie !</strong></p>";

    // Vérifier les tables
    echo "<h2>3. Vérification des Tables</h2>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "<p style='color: orange;'>⚠️ Aucune table trouvée. Vous devez importer la base de données.</p>";
        echo "<p><strong>Instructions :</strong></p>";
        echo "<ol>";
        echo "<li>Exportez votre base locale depuis phpMyAdmin (format SQL)</li>";
        echo "<li>Utilisez MySQL Workbench ou HeidiSQL pour vous connecter à Railway</li>";
        echo "<li>Importez le fichier .sql</li>";
        echo "</ol>";

        echo "<h3>Informations de Connexion MySQL</h3>";
        echo "<pre>";
        echo "Host: " . $host . "\n";
        echo "Database: " . $dbname . "\n";
        echo "User: " . $user . "\n";
        echo "Password: " . (getenv('MYSQL_PASSWORD') ? '[Configuré - voir Railway Variables]' : '[Non configuré]') . "\n";
        echo "Port: " . (getenv('MYSQL_PORT') ?: '3306') . "\n";
        echo "</pre>";
    } else {
        echo "<p style='color: green;'>✅ <strong>" . count($tables) . " tables trouvées</strong></p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";

        // Vérifier les utilisateurs
        echo "<h2>4. Vérification des Utilisateurs</h2>";
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            $userCount = $stmt->fetch()['count'];
            echo "<p style='color: green;'>✅ <strong>$userCount utilisateur(s) trouvé(s)</strong></p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
        }
    }

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ <strong>Erreur de connexion :</strong> " . $e->getMessage() . "</p>";
    echo "<p>Vérifiez que :</p>";
    echo "<ul>";
    echo "<li>Le service MySQL est actif dans Railway</li>";
    echo "<li>Les variables d'environnement sont correctement configurées</li>";
    echo "<li>Le service web peut communiquer avec MySQL</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h2>5. Configuration Générale</h2>";
echo "<ul>";
echo "<li><strong>PHP Version:</strong> " . phpversion() . "</li>";
echo "<li><strong>Serveur:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</li>";
echo "<li><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "</ul>";

echo "<hr>";
echo "<p style='color: red;'><strong>IMPORTANT :</strong> Supprimez ce fichier (railway_init.php) après vérification pour des raisons de sécurité.</p>";
?>

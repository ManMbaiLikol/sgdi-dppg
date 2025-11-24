<?php
/**
 * Script web pour exécuter la migration sur Railway
 * URL: https://votre-app.railway.app/database/migrations/run_migration_web.php
 *
 * SÉCURITÉ: Ce script vérifie un token pour éviter l'exécution non autorisée
 */

// Token de sécurité - À définir via variable d'environnement MIGRATION_TOKEN
$required_token = getenv('MIGRATION_TOKEN') ?: 'sgdi-migration-2025';
$provided_token = $_GET['token'] ?? '';

if ($provided_token !== $required_token) {
    http_response_code(403);
    die("❌ Accès refusé. Token invalide.\n\nUtilisation: ?token=VOTRE_TOKEN");
}

// Configuration
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration SGDI - Colonnes edit dossiers</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #252526;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        h2 {
            color: #569cd6;
            margin-top: 30px;
        }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        .info { color: #9cdcfe; }
        pre {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border-left: 3px solid #4ec9b0;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #2d2d30;
            border-radius: 5px;
        }
        .step-title {
            font-weight: bold;
            color: #dcdcaa;
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        hr {
            border: none;
            border-top: 1px solid #3e3e42;
            margin: 30px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Migration SGDI - Fix Edit Dossiers</h1>
        <p class="info">Exécution de la migration pour ajouter les colonnes manquantes</p>

<?php
// Configuration PDO pour utiliser le mode buffered
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

echo "<div class='step'>";
echo "<div class='step-title'>📋 Informations de connexion</div>";
echo "<pre>";
echo "Base de données : " . getenv('DB_NAME') . "\n";
echo "Serveur : " . getenv('DB_HOST') . "\n";
echo "Date : " . date('Y-m-d H:i:s') . "\n";
echo "</pre>";
echo "</div>";

$migrations = [
    // 1. Ajout de centre_emplisseur au type_infrastructure
    [
        'check' => "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'type_infrastructure'",
        'action' => "ALTER TABLE dossiers MODIFY COLUMN type_infrastructure ENUM('station_service','point_consommateur','depot_gpl','centre_emplisseur') NOT NULL",
        'condition' => function($result) {
            return strpos($result, 'centre_emplisseur') === false;
        },
        'name' => 'Type centre_emplisseur'
    ],

    // 2. Ajout de remodelage au sous_type
    [
        'check' => "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'sous_type'",
        'action' => "ALTER TABLE dossiers MODIFY COLUMN sous_type ENUM('implantation','reprise','remodelage') NOT NULL",
        'condition' => function($result) {
            return strpos($result, 'remodelage') === false;
        },
        'name' => 'Sous-type remodelage'
    ],

    // 3-12. Ajout des colonnes manquantes
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'departement'",
        'action' => "ALTER TABLE dossiers ADD COLUMN departement VARCHAR(100) NULL AFTER region",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne departement'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'arrondissement'",
        'action' => "ALTER TABLE dossiers ADD COLUMN arrondissement VARCHAR(100) NULL AFTER ville",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne arrondissement'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'quartier'",
        'action' => "ALTER TABLE dossiers ADD COLUMN quartier VARCHAR(100) NULL AFTER arrondissement",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne quartier'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'zone_type'",
        'action' => "ALTER TABLE dossiers ADD COLUMN zone_type ENUM('urbaine','rurale') DEFAULT 'urbaine' AFTER quartier",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne zone_type'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'lieu_dit'",
        'action' => "ALTER TABLE dossiers ADD COLUMN lieu_dit VARCHAR(200) NULL AFTER zone_type",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne lieu_dit'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'adresse_precise'",
        'action' => "ALTER TABLE dossiers ADD COLUMN adresse_precise TEXT NULL AFTER email_demandeur",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne adresse_precise'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'annee_mise_en_service'",
        'action' => "ALTER TABLE dossiers ADD COLUMN annee_mise_en_service YEAR NULL AFTER coordonnees_gps",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne annee_mise_en_service'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'operateur_gaz'",
        'action' => "ALTER TABLE dossiers ADD COLUMN operateur_gaz VARCHAR(200) NULL COMMENT 'Pour centre emplisseur'",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne operateur_gaz'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'entreprise_constructrice'",
        'action' => "ALTER TABLE dossiers ADD COLUMN entreprise_constructrice VARCHAR(200) NULL COMMENT 'Pour centre emplisseur'",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne entreprise_constructrice'
    ],
    [
        'check' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dossiers' AND COLUMN_NAME = 'capacite_enfutage'",
        'action' => "ALTER TABLE dossiers ADD COLUMN capacite_enfutage VARCHAR(100) NULL COMMENT 'Capacité d\'enfûtage (bouteilles/jour)'",
        'condition' => function($result) { return $result == 0; },
        'name' => 'Colonne capacite_enfutage'
    ]
];

echo "<h2>⚙️ Exécution des migrations</h2>";

$success = 0;
$skipped = 0;
$errors = 0;

foreach ($migrations as $i => $migration) {
    echo "<div class='step'>";
    echo "<div class='step-title'>Migration #" . ($i + 1) . " : {$migration['name']}</div>";
    echo "<pre>";

    try {
        // Vérifier si la migration est nécessaire
        $stmt = $pdo->query($migration['check']);
        $result = $stmt->fetchColumn();

        if ($migration['condition']($result)) {
            // Exécuter la migration
            $pdo->exec($migration['action']);
            echo "<span class='success'>✓ {$migration['name']} : Ajouté avec succès</span>\n";
            $success++;
        } else {
            echo "<span class='warning'>○ {$migration['name']} : Déjà présent</span>\n";
            $skipped++;
        }
    } catch (PDOException $e) {
        echo "<span class='error'>✗ {$migration['name']} : ERREUR</span>\n";
        echo "<span class='error'>   " . htmlspecialchars($e->getMessage()) . "</span>\n";
        $errors++;
    }

    echo "</pre>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>📊 Résumé</h2>";
echo "<div class='step'>";
echo "<pre>";
echo "<span class='success'>✓ Ajoutés : $success</span>\n";
echo "<span class='warning'>○ Déjà présents : $skipped</span>\n";
echo "<span class='" . ($errors > 0 ? 'error' : 'success') . "'>✗ Erreurs : $errors</span>\n";
echo "</pre>";
echo "</div>";

// Vérification finale
echo "<h2>🔍 Vérification finale</h2>";
echo "<div class='step'>";
echo "<pre>";

$cols_to_check = [
    'departement', 'arrondissement', 'quartier', 'zone_type',
    'lieu_dit', 'adresse_precise', 'annee_mise_en_service',
    'operateur_gaz', 'entreprise_constructrice', 'capacite_enfutage'
];

foreach ($cols_to_check as $col) {
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'dossiers'
        AND COLUMN_NAME = '$col'
    ");
    $exists = $stmt->fetchColumn();

    $status = $exists ? "<span class='success'>✓ Présent</span>" : "<span class='error'>✗ MANQUANT</span>";
    echo sprintf("%-30s %s\n", $col, $status);
}

echo "</pre>";
echo "</div>";

echo "<hr>";

if ($errors == 0) {
    echo "<h2 class='success'>✅ Migration réussie !</h2>";
    echo "<p class='info'>La modification des dossiers historiques devrait maintenant fonctionner.</p>";
} else {
    echo "<h2 class='error'>⚠️ Migration terminée avec des erreurs</h2>";
    echo "<p class='warning'>Consultez les messages ci-dessus pour plus de détails.</p>";
}

echo "<p style='margin-top: 30px; padding: 15px; background: #2d2d30; border-radius: 5px;'>";
echo "<strong>🔒 Sécurité :</strong> Pour des raisons de sécurité, supprimez ce fichier après l'exécution :<br>";
echo "<code style='color: #ce9178;'>rm database/migrations/run_migration_web.php</code>";
echo "</p>";
?>
    </div>
</body>
</html>

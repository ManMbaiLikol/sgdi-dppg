<?php
/**
 * Script de migration SQL directe (sans fichier)
 * Exécute les CREATE TABLE directement dans le code
 */

// Token de sécurité
define('MIGRATION_TOKEN', 'sgdi-migration-2025-secure-token-' . md5('dppg-minee-cameroun'));

if (!isset($_GET['token']) || $_GET['token'] !== MIGRATION_TOKEN) {
    http_response_code(403);
    die("❌ Accès refusé");
}

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Migration Directe</title>";
echo "<style>body{font-family:monospace;background:#1e1e1e;color:#d4d4d4;padding:20px;}";
echo ".success{color:#4ec9b0;}.error{color:#f48771;}.info{color:#569cd6;}</style></head><body>";
echo "<h1>🔧 MIGRATION SQL DIRECTE</h1><pre>";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // TABLE 1: decisions_ministerielle
    echo "<span class='info'>▶ Création table decisions_ministerielle...</span>\n";
    $sql1 = "CREATE TABLE IF NOT EXISTS decisions_ministerielle (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dossier_id INT NOT NULL,
        user_id INT NOT NULL,
        decision ENUM('approuve', 'refuse', 'ajourne') NOT NULL,
        numero_arrete VARCHAR(100) NOT NULL,
        observations TEXT,
        date_decision DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_dossier (dossier_id),
        INDEX idx_decision (decision),
        INDEX idx_date_decision (date_decision),
        UNIQUE KEY unique_decision_per_dossier (dossier_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql1);
    echo "<span class='success'>  ✅ Table decisions_ministerielle créée</span>\n\n";

    // TABLE 2: registre_public
    echo "<span class='info'>▶ Création table registre_public...</span>\n";
    $sql2 = "CREATE TABLE IF NOT EXISTS registre_public (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dossier_id INT NOT NULL,
        numero_dossier VARCHAR(50) NOT NULL,
        type_infrastructure VARCHAR(50) NOT NULL,
        sous_type VARCHAR(50),
        nom_demandeur VARCHAR(200) NOT NULL,
        ville VARCHAR(100),
        quartier VARCHAR(100),
        region VARCHAR(100),
        operateur_proprietaire VARCHAR(200),
        entreprise_beneficiaire VARCHAR(200),
        decision ENUM('approuve') NOT NULL DEFAULT 'approuve',
        numero_arrete VARCHAR(100) NOT NULL,
        observations TEXT,
        date_decision DATETIME NOT NULL,
        date_publication DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_dossier (dossier_id),
        INDEX idx_numero (numero_dossier),
        INDEX idx_type (type_infrastructure),
        INDEX idx_ville (ville),
        INDEX idx_region (region),
        INDEX idx_date_decision (date_decision),
        INDEX idx_date_publication (date_publication),
        INDEX idx_numero_arrete (numero_arrete),
        UNIQUE KEY unique_dossier_publication (dossier_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql2);
    echo "<span class='success'>  ✅ Table registre_public créée</span>\n\n";

    // Vérification
    echo "=== VÉRIFICATION ===\n";
    $tables = ['decisions_ministerielle', 'registre_public'];

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            $desc = $pdo->query("DESCRIBE $table");
            $cols = $desc->fetchAll();
            echo "<span class='success'>✅ $table</span> (" . count($cols) . " colonnes)\n";
        } else {
            echo "<span class='error'>❌ $table introuvable</span>\n";
        }
    }

    echo "\n<span class='success'>🎉 Migration réussie!</span>\n";

} catch (PDOException $e) {
    echo "<span class='error'>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</span>\n";
}

echo "</pre></body></html>";

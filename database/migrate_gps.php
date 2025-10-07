<?php
/**
 * Script de migration pour les fonctionnalités géographiques
 * Exécuter ce script une seule fois via le navigateur ou en ligne de commande
 */

require_once __DIR__ . '/../config/database.php';

// Sécurité : à exécuter uniquement une fois
// Décommenter cette ligne après la première exécution
// die('Migration déjà effectuée. Commentez cette ligne pour réexécuter.');

echo "<h1>Migration des fonctionnalités géographiques - SGDI</h1>";
echo "<pre>";

$errors = [];
$success = [];

try {
    // 1. Vérifier et modifier coordonnees_gps
    echo "1. Vérification de la colonne coordonnees_gps...\n";
    try {
        $pdo->exec("ALTER TABLE dossiers MODIFY COLUMN coordonnees_gps VARCHAR(100)");
        $success[] = "✓ Colonne coordonnees_gps mise à jour";
    } catch (PDOException $e) {
        $errors[] = "⚠ coordonnees_gps: " . $e->getMessage();
    }

    // 2. Ajouter adresse_precise si elle n'existe pas
    echo "\n2. Ajout de la colonne adresse_precise...\n";
    try {
        // Vérifier si la colonne existe
        $stmt = $pdo->query("SHOW COLUMNS FROM dossiers LIKE 'adresse_precise'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE dossiers ADD COLUMN adresse_precise TEXT AFTER coordonnees_gps");
            $success[] = "✓ Colonne adresse_precise ajoutée";
        } else {
            $success[] = "✓ Colonne adresse_precise existe déjà";
        }
    } catch (PDOException $e) {
        $errors[] = "⚠ adresse_precise: " . $e->getMessage();
    }

    // 3. Ajouter index sur coordonnees_gps
    echo "\n3. Ajout de l'index idx_coordonnees_gps...\n";
    try {
        // Vérifier si l'index existe
        $stmt = $pdo->query("SHOW INDEX FROM dossiers WHERE Key_name = 'idx_coordonnees_gps'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE dossiers ADD INDEX idx_coordonnees_gps (coordonnees_gps)");
            $success[] = "✓ Index idx_coordonnees_gps ajouté";
        } else {
            $success[] = "✓ Index idx_coordonnees_gps existe déjà";
        }
    } catch (PDOException $e) {
        $errors[] = "⚠ idx_coordonnees_gps: " . $e->getMessage();
    }

    // 4. Créer table zones_restreintes
    echo "\n4. Création de la table zones_restreintes...\n";
    try {
        $sql = "CREATE TABLE IF NOT EXISTS zones_restreintes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nom VARCHAR(255) NOT NULL,
            type ENUM('militaire', 'parc_national', 'reserve', 'zone_protegee', 'autre') NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            rayon_km DECIMAL(10, 2) NOT NULL COMMENT 'Rayon de la zone restreinte en km',
            description TEXT,
            actif TINYINT DEFAULT 1,
            date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);
        $success[] = "✓ Table zones_restreintes créée";
    } catch (PDOException $e) {
        $errors[] = "⚠ zones_restreintes: " . $e->getMessage();
    }

    // 5. Créer table historique_localisation
    echo "\n5. Création de la table historique_localisation...\n";
    try {
        $sql = "CREATE TABLE IF NOT EXISTS historique_localisation (
            id INT PRIMARY KEY AUTO_INCREMENT,
            dossier_id INT NOT NULL,
            anciennes_coordonnees VARCHAR(100),
            nouvelles_coordonnees VARCHAR(100),
            user_id INT NOT NULL,
            date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";
        $pdo->exec($sql);
        $success[] = "✓ Table historique_localisation créée";
    } catch (PDOException $e) {
        $errors[] = "⚠ historique_localisation: " . $e->getMessage();
    }

    // 6. Ajouter index région et ville
    echo "\n6. Ajout des index géographiques...\n";
    try {
        $stmt = $pdo->query("SHOW INDEX FROM dossiers WHERE Key_name = 'idx_region'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE dossiers ADD INDEX idx_region (region)");
            $success[] = "✓ Index idx_region ajouté";
        } else {
            $success[] = "✓ Index idx_region existe déjà";
        }

        $stmt = $pdo->query("SHOW INDEX FROM dossiers WHERE Key_name = 'idx_ville'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE dossiers ADD INDEX idx_ville (ville)");
            $success[] = "✓ Index idx_ville ajouté";
        } else {
            $success[] = "✓ Index idx_ville existe déjà";
        }
    } catch (PDOException $e) {
        $errors[] = "⚠ Index géographiques: " . $e->getMessage();
    }

    // 7. Créer la vue infrastructures_geolocalisees
    echo "\n7. Création de la vue infrastructures_geolocalisees...\n";
    try {
        $pdo->exec("DROP VIEW IF EXISTS infrastructures_geolocalisees");
        $sql = "CREATE VIEW infrastructures_geolocalisees AS
            SELECT
                d.id,
                d.numero,
                d.type_infrastructure,
                d.sous_type,
                d.nom_demandeur,
                d.ville,
                d.region,
                d.coordonnees_gps,
                d.adresse_precise,
                d.statut,
                d.date_creation,
                CAST(SUBSTRING_INDEX(d.coordonnees_gps, ',', 1) AS DECIMAL(10,8)) as latitude,
                CAST(SUBSTRING_INDEX(d.coordonnees_gps, ',', -1) AS DECIMAL(11,8)) as longitude
            FROM dossiers d
            WHERE d.coordonnees_gps IS NOT NULL
            AND d.coordonnees_gps != ''
            AND d.coordonnees_gps REGEXP '^-?[0-9]+\\\\.?[0-9]*,[ ]?-?[0-9]+\\\\.?[0-9]*$'";
        $pdo->exec($sql);
        $success[] = "✓ Vue infrastructures_geolocalisees créée";
    } catch (PDOException $e) {
        $errors[] = "⚠ Vue infrastructures_geolocalisees: " . $e->getMessage();
    }

    // 8. Créer la vue infrastructures_publiques
    echo "\n8. Création de la vue infrastructures_publiques...\n";
    try {
        $pdo->exec("DROP VIEW IF EXISTS infrastructures_publiques");
        $sql = "CREATE VIEW infrastructures_publiques AS
            SELECT * FROM infrastructures_geolocalisees
            WHERE statut = 'autorise'";
        $pdo->exec($sql);
        $success[] = "✓ Vue infrastructures_publiques créée";
    } catch (PDOException $e) {
        $errors[] = "⚠ Vue infrastructures_publiques: " . $e->getMessage();
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "\nRÉSULTAT DE LA MIGRATION:\n\n";

    if (!empty($success)) {
        echo "SUCCÈS (" . count($success) . "):\n";
        foreach ($success as $msg) {
            echo "  $msg\n";
        }
    }

    if (!empty($errors)) {
        echo "\nERREURS/AVERTISSEMENTS (" . count($errors) . "):\n";
        foreach ($errors as $msg) {
            echo "  $msg\n";
        }
    }

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "\n✓ MIGRATION TERMINÉE !\n\n";
    echo "Les fonctionnalités géographiques sont maintenant disponibles.\n";
    echo "Vous pouvez accéder à:\n";
    echo "  - Carte interne: /modules/carte/index.php\n";
    echo "  - Gestion GPS: /modules/dossiers/localisation.php?id=X\n";
    echo "  - Carte publique: /public_map.php\n\n";

} catch (Exception $e) {
    echo "\n❌ ERREUR FATALE:\n";
    echo $e->getMessage() . "\n\n";
}

echo "</pre>";
?>
<style>
body {
    font-family: 'Consolas', 'Monaco', monospace;
    background: #f5f5f5;
    padding: 20px;
}
h1 {
    color: #333;
    border-bottom: 3px solid #667eea;
    padding-bottom: 10px;
}
pre {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    line-height: 1.6;
}
</style>

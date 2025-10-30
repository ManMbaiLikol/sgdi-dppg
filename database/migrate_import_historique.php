<?php
/**
 * Script de migration automatique pour le module Import Historique
 * SGDI - Système de Gestion des Dossiers d'Implantation
 *
 * Ce script exécute la migration SQL pour ajouter les fonctionnalités
 * d'import de dossiers historiques.
 *
 * Utilisation : Accéder à ce fichier via le navigateur
 * URL : https://votre-domaine.railway.app/database/migrate_import_historique.php
 */

// Sécurité : À supprimer en production ou protéger par mot de passe
$MIGRATION_PASSWORD = 'sgdi2025'; // Changez ce mot de passe !

// Vérifier si l'accès est autorisé
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $MIGRATION_PASSWORD) {
        $_SESSION['migration_authorized'] = true;
    } else {
        $error = "Mot de passe incorrect !";
    }
}

if (!isset($_SESSION['migration_authorized'])) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Migration - Module Import Historique</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            .login-box {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                max-width: 400px;
                width: 100%;
            }
            h1 {
                color: #333;
                margin-bottom: 10px;
                font-size: 24px;
            }
            .subtitle {
                color: #666;
                margin-bottom: 30px;
                font-size: 14px;
            }
            input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 2px solid #ddd;
                border-radius: 5px;
                font-size: 16px;
                box-sizing: border-box;
                margin-bottom: 20px;
            }
            button {
                width: 100%;
                padding: 12px;
                background: #667eea;
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                cursor: pointer;
                transition: background 0.3s;
            }
            button:hover {
                background: #5568d3;
            }
            .error {
                color: #e74c3c;
                margin-bottom: 20px;
                padding: 10px;
                background: #fadbd8;
                border-radius: 5px;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔐 Migration Sécurisée</h1>
            <p class="subtitle">Module Import Historique - SGDI</p>
            <?php if (isset($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Mot de passe de migration" required autofocus>
                <button type="submit">Déverrouiller la migration</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Configuration
require_once __DIR__ . '/../config/database.php';

// Styles CSS
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration - Module Import Historique</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .content {
            padding: 30px;
        }
        .step {
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 5px;
        }
        .step h3 {
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .step-number {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .success {
            color: #27ae60;
            font-weight: bold;
        }
        .error {
            color: #e74c3c;
            font-weight: bold;
        }
        .warning {
            color: #f39c12;
            font-weight: bold;
        }
        .info {
            color: #3498db;
            font-weight: bold;
        }
        .icon {
            font-size: 20px;
            margin-right: 5px;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 13px;
            margin-top: 10px;
        }
        .summary {
            margin-top: 30px;
            padding: 25px;
            background: #e8f5e9;
            border-radius: 5px;
            border: 2px solid #4caf50;
        }
        .summary h2 {
            color: #2e7d32;
            margin-bottom: 15px;
        }
        .summary-error {
            background: #ffebee;
            border-color: #f44336;
        }
        .summary-error h2 {
            color: #c62828;
        }
        ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        li {
            margin-bottom: 8px;
        }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
        .logout-btn {
            background: #e74c3c;
            float: right;
        }
        .logout-btn:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Migration du Module Import Historique</h1>
            <p>SGDI - Système de Gestion des Dossiers d'Implantation</p>
        </div>
        <div class="content">
            <?php

            $results = [];
            $hasErrors = false;

            // ÉTAPE 1 : Vérifier la connexion à la base de données
            $results[] = [
                'step' => 'Connexion à la base de données',
                'status' => 'success',
                'message' => 'Connexion établie avec succès',
                'details' => 'Base de données : ' . DB_NAME . ' sur ' . DB_HOST
            ];

            try {
                // ÉTAPE 2 : Ajouter les colonnes à la table dossiers
                echo '<div class="step">';
                echo '<h3><span class="step-number">1</span> Ajout des colonnes à la table "dossiers"</h3>';

                $columns = [
                    'est_historique' => 'BOOLEAN DEFAULT FALSE',
                    'importe_le' => 'DATETIME NULL',
                    'importe_par' => 'INT NULL',
                    'source_import' => 'VARCHAR(100) NULL',
                    'numero_decision_ministerielle' => 'VARCHAR(100) NULL',
                    'date_decision_ministerielle' => 'DATE NULL'
                ];

                $columnsAdded = [];
                $columnsExisting = [];

                foreach ($columns as $columnName => $columnDef) {
                    // Vérifier si la colonne existe déjà
                    $checkStmt = $pdo->query("SHOW COLUMNS FROM dossiers LIKE '$columnName'");
                    if ($checkStmt->rowCount() == 0) {
                        $pdo->exec("ALTER TABLE dossiers ADD COLUMN $columnName $columnDef");
                        $columnsAdded[] = $columnName;
                    } else {
                        $columnsExisting[] = $columnName;
                    }
                }

                echo '<p>';
                if (count($columnsAdded) > 0) {
                    echo '<span class="success icon">✅</span> Colonnes ajoutées : ' . implode(', ', $columnsAdded) . '<br>';
                }
                if (count($columnsExisting) > 0) {
                    echo '<span class="info icon">ℹ️</span> Colonnes déjà existantes : ' . implode(', ', $columnsExisting);
                }
                echo '</p>';
                echo '</div>';

                // ÉTAPE 3 : Ajouter les index
                echo '<div class="step">';
                echo '<h3><span class="step-number">2</span> Création des index</h3>';

                $indexes = [
                    'idx_est_historique' => 'est_historique',
                    'idx_importe_par' => 'importe_par',
                    'idx_numero_decision' => 'numero_decision_ministerielle'
                ];

                $indexesAdded = [];
                $indexesExisting = [];

                foreach ($indexes as $indexName => $columnName) {
                    // Vérifier si l'index existe déjà
                    $checkStmt = $pdo->query("SHOW INDEX FROM dossiers WHERE Key_name = '$indexName'");
                    if ($checkStmt->rowCount() == 0) {
                        $pdo->exec("ALTER TABLE dossiers ADD INDEX $indexName ($columnName)");
                        $indexesAdded[] = $indexName;
                    } else {
                        $indexesExisting[] = $indexName;
                    }
                }

                echo '<p>';
                if (count($indexesAdded) > 0) {
                    echo '<span class="success icon">✅</span> Index créés : ' . implode(', ', $indexesAdded) . '<br>';
                }
                if (count($indexesExisting) > 0) {
                    echo '<span class="info icon">ℹ️</span> Index déjà existants : ' . implode(', ', $indexesExisting);
                }
                echo '</p>';
                echo '</div>';

                // ÉTAPE 4 : Ajouter la clé étrangère
                echo '<div class="step">';
                echo '<h3><span class="step-number">3</span> Ajout de la clé étrangère</h3>';

                $checkFkStmt = $pdo->query("
                    SELECT COUNT(*) as count FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'dossiers'
                    AND CONSTRAINT_NAME = 'fk_dossiers_importe_par'
                ");
                $fkExists = $checkFkStmt->fetch()['count'] > 0;

                if (!$fkExists) {
                    $pdo->exec("
                        ALTER TABLE dossiers
                        ADD CONSTRAINT fk_dossiers_importe_par
                        FOREIGN KEY (importe_par) REFERENCES users(id) ON DELETE SET NULL
                    ");
                    echo '<p><span class="success icon">✅</span> Clé étrangère "fk_dossiers_importe_par" créée</p>';
                } else {
                    echo '<p><span class="info icon">ℹ️</span> Clé étrangère "fk_dossiers_importe_par" déjà existante</p>';
                }
                echo '</div>';

                // ÉTAPE 5 : Créer le statut HISTORIQUE_AUTORISE
                echo '<div class="step">';
                echo '<h3><span class="step-number">4</span> Création du statut "HISTORIQUE_AUTORISE"</h3>';

                $pdo->exec("
                    INSERT INTO statuts_dossier (code, libelle, description, ordre, created_at)
                    VALUES (
                        'HISTORIQUE_AUTORISE',
                        'Dossier Historique Autorisé',
                        'Dossier d''autorisation traité et approuvé avant la mise en place du SGDI',
                        100,
                        NOW()
                    )
                    ON DUPLICATE KEY UPDATE
                        libelle = 'Dossier Historique Autorisé',
                        description = 'Dossier d''autorisation traité et approuvé avant la mise en place du SGDI',
                        ordre = 100
                ");

                echo '<p><span class="success icon">✅</span> Statut créé ou mis à jour</p>';
                echo '</div>';

                // ÉTAPE 6 : Créer la table entreprises_beneficiaires
                echo '<div class="step">';
                echo '<h3><span class="step-number">5</span> Création de la table "entreprises_beneficiaires"</h3>';

                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS entreprises_beneficiaires (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        dossier_id INT NOT NULL,
                        nom VARCHAR(200) NOT NULL,
                        activite VARCHAR(200) NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
                        INDEX idx_dossier (dossier_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");

                echo '<p><span class="success icon">✅</span> Table créée</p>';
                echo '</div>';

                // ÉTAPE 7 : Créer la table logs_import_historique
                echo '<div class="step">';
                echo '<h3><span class="step-number">6</span> Création de la table "logs_import_historique"</h3>';

                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS logs_import_historique (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        fichier_nom VARCHAR(255) NOT NULL,
                        source_import VARCHAR(100) NULL,
                        nb_lignes_total INT NOT NULL,
                        nb_success INT NOT NULL DEFAULT 0,
                        nb_errors INT NOT NULL DEFAULT 0,
                        duree_secondes INT NULL,
                        details TEXT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        INDEX idx_user (user_id),
                        INDEX idx_date (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");

                echo '<p><span class="success icon">✅</span> Table créée</p>';
                echo '</div>';

                // ÉTAPE 8 : Créer la vue v_dossiers_historiques
                echo '<div class="step">';
                echo '<h3><span class="step-number">7</span> Création de la vue "v_dossiers_historiques"</h3>';

                $pdo->exec("DROP VIEW IF EXISTS v_dossiers_historiques");
                $pdo->exec("
                    CREATE VIEW v_dossiers_historiques AS
                    SELECT
                        d.id,
                        d.numero,
                        d.nom_demandeur,
                        ti.nom as type_infrastructure,
                        d.region,
                        d.ville,
                        d.latitude,
                        d.longitude,
                        d.numero_decision_ministerielle,
                        d.date_decision_ministerielle,
                        d.observations,
                        d.importe_le,
                        d.source_import,
                        CONCAT(u.prenom, ' ', u.nom) as importe_par_nom,
                        s.libelle as statut,
                        eb.nom as entreprise_beneficiaire,
                        eb.activite as activite_entreprise,
                        d.created_at
                    FROM dossiers d
                    LEFT JOIN types_infrastructure ti ON d.type_infrastructure_id = ti.id
                    LEFT JOIN statuts_dossier s ON d.statut_id = s.id
                    LEFT JOIN users u ON d.importe_par = u.id
                    LEFT JOIN entreprises_beneficiaires eb ON d.id = eb.dossier_id
                    WHERE d.est_historique = TRUE
                    ORDER BY d.importe_le DESC, d.numero
                ");

                echo '<p><span class="success icon">✅</span> Vue créée</p>';
                echo '</div>';

                // RÉSUMÉ
                echo '<div class="summary">';
                echo '<h2>✅ Migration réussie !</h2>';
                echo '<p>Toutes les étapes ont été exécutées avec succès.</p>';
                echo '<ul>';
                echo '<li>✅ Colonnes ajoutées à la table "dossiers"</li>';
                echo '<li>✅ Index créés</li>';
                echo '<li>✅ Clé étrangère configurée</li>';
                echo '<li>✅ Statut "HISTORIQUE_AUTORISE" créé</li>';
                echo '<li>✅ Table "entreprises_beneficiaires" créée</li>';
                echo '<li>✅ Table "logs_import_historique" créée</li>';
                echo '<li>✅ Vue "v_dossiers_historiques" créée</li>';
                echo '</ul>';
                echo '<p style="margin-top: 20px;"><strong>Le module d\'import historique est maintenant prêt à être utilisé !</strong></p>';
                echo '<a href="../modules/import_historique/test_database.php" class="btn">Tester la configuration</a>';
                echo '<a href="?logout=1" class="btn logout-btn">Déconnexion</a>';
                echo '</div>';

            } catch (PDOException $e) {
                $hasErrors = true;
                echo '<div class="summary summary-error">';
                echo '<h2>❌ Erreur lors de la migration</h2>';
                echo '<p><strong>Message d\'erreur :</strong></p>';
                echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
                echo '<p style="margin-top: 20px;">Veuillez vérifier les logs et réessayer.</p>';
                echo '<a href="?logout=1" class="btn logout-btn">Déconnexion</a>';
                echo '</div>';
            }

            // Déconnexion
            if (isset($_GET['logout'])) {
                session_destroy();
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }

            ?>
        </div>
    </div>
</body>
</html>

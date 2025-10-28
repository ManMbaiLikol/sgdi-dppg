<?php
// Script de migration automatique pour Railway
// À exécuter UNE SEULE FOIS via : https://votre-app.railway.app/modules/import_historique/migrate_database.php

require_once '../../includes/auth.php';
require_once '../../config/database.php';

requireLogin();

// Vérifier les permissions (admin seulement)
if ($_SESSION['user_role'] !== 'admin') {
    die('❌ Accès refusé. Seul un administrateur peut exécuter cette migration.');
}

$pageTitle = "Migration de la base de données";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-warning text-dark">
            <h3><i class="fas fa-database"></i> Migration de la base de données</h3>
            <p class="mb-0">Module d'import de dossiers historiques</p>
        </div>
        <div class="card-body">
            <?php
            // Vérifier si la migration a déjà été exécutée (vérifier les colonnes critiques)
            $colonnes_critiques = ['est_historique', 'importe_le', 'importe_par', 'numero_decision_ministerielle'];
            $migration_complete = true;

            foreach ($colonnes_critiques as $col) {
                $sql = "SHOW COLUMNS FROM dossiers LIKE '$col'";
                $result = $pdo->query($sql);
                if ($result->rowCount() == 0) {
                    $migration_complete = false;
                    break;
                }
            }

            if ($migration_complete && !isset($_POST['force_migration'])) {
                echo '<div class="alert alert-success">';
                echo '<h4>✅ Migration déjà exécutée</h4>';
                echo '<p>Toutes les colonnes nécessaires existent déjà.</p>';
                echo '<p>Si vous voulez ré-exécuter la migration quand même, cliquez ci-dessous :</p>';
                echo '<form method="POST">';
                echo '<input type="hidden" name="force_migration" value="1">';
                echo '<button type="submit" class="btn btn-warning">Forcer la ré-exécution</button>';
                echo '</form>';
                echo '</div>';
            } else {
                if (!$migration_complete) {
                    echo '<div class="alert alert-warning">';
                    echo '<h4>⚠️ Migration incomplète détectée</h4>';
                    echo '<p>Certaines colonnes sont manquantes. La migration va être exécutée.</p>';
                    echo '</div>';
                }
                // Exécuter la migration
                echo '<div class="alert alert-warning">';
                echo '<h4>⚠️ Attention</h4>';
                echo '<p>Cette opération va modifier la structure de la base de données.</p>';
                echo '<p><strong>Assurez-vous d\'avoir une sauvegarde avant de continuer.</strong></p>';
                echo '</div>';

                if (!isset($_POST['confirm_migration'])) {
                    // Demander confirmation
                    echo '<form method="POST">';
                    echo '<div class="custom-control custom-checkbox mb-3">';
                    echo '<input type="checkbox" class="custom-control-input" id="confirm" name="confirm_migration" required>';
                    echo '<label class="custom-control-label" for="confirm">';
                    echo 'Je confirme vouloir exécuter la migration de la base de données';
                    echo '</label>';
                    echo '</div>';
                    echo '<button type="submit" class="btn btn-primary btn-lg">Exécuter la migration</button>';
                    echo '</form>';
                } else {
                    // Exécuter la migration
                    echo '<h4>📋 Exécution de la migration...</h4>';
                    echo '<hr>';

                    // Vérifier d'abord si on utilise ENUM ou table statuts_dossier
                    $useEnum = false;
                    try {
                        $checkTable = $pdo->query("SHOW TABLES LIKE 'statuts_dossier'");
                        $useEnum = ($checkTable->rowCount() == 0);
                    } catch (Exception $e) {
                        $useEnum = true;
                    }

                    // Étapes de migration
                    $steps = [
                        [
                            'name' => 'Ajout colonne est_historique',
                            'check' => "SHOW COLUMNS FROM dossiers LIKE 'est_historique'",
                            'sql' => "ALTER TABLE dossiers ADD COLUMN est_historique BOOLEAN DEFAULT FALSE"
                        ],
                        [
                            'name' => 'Ajout colonne importe_le',
                            'check' => "SHOW COLUMNS FROM dossiers LIKE 'importe_le'",
                            'sql' => "ALTER TABLE dossiers ADD COLUMN importe_le DATETIME NULL"
                        ],
                        [
                            'name' => 'Ajout colonne importe_par',
                            'check' => "SHOW COLUMNS FROM dossiers LIKE 'importe_par'",
                            'sql' => "ALTER TABLE dossiers ADD COLUMN importe_par INT NULL"
                        ],
                        [
                            'name' => 'Ajout colonne source_import',
                            'check' => "SHOW COLUMNS FROM dossiers LIKE 'source_import'",
                            'sql' => "ALTER TABLE dossiers ADD COLUMN source_import VARCHAR(100) NULL"
                        ],
                        [
                            'name' => 'Ajout colonne numero_decision_ministerielle',
                            'check' => "SHOW COLUMNS FROM dossiers LIKE 'numero_decision_ministerielle'",
                            'sql' => "ALTER TABLE dossiers ADD COLUMN numero_decision_ministerielle VARCHAR(100) NULL"
                        ],
                        [
                            'name' => 'Ajout colonne date_decision_ministerielle',
                            'check' => "SHOW COLUMNS FROM dossiers LIKE 'date_decision_ministerielle'",
                            'sql' => "ALTER TABLE dossiers ADD COLUMN date_decision_ministerielle DATE NULL"
                        ],
                        [
                            'name' => 'Ajout index est_historique',
                            'check' => "SHOW INDEX FROM dossiers WHERE Key_name = 'idx_est_historique'",
                            'sql' => "ALTER TABLE dossiers ADD INDEX idx_est_historique (est_historique)"
                        ],
                        [
                            'name' => 'Ajout index importe_par',
                            'check' => "SHOW INDEX FROM dossiers WHERE Key_name = 'idx_importe_par'",
                            'sql' => "ALTER TABLE dossiers ADD INDEX idx_importe_par (importe_par)"
                        ],
                        [
                            'name' => 'Ajout index numero_decision',
                            'check' => "SHOW INDEX FROM dossiers WHERE Key_name = 'idx_numero_decision'",
                            'sql' => "ALTER TABLE dossiers ADD INDEX idx_numero_decision (numero_decision_ministerielle)"
                        ],
                        [
                            'name' => 'Ajout clé étrangère importe_par',
                            'check' => "SELECT COUNT(*) as count FROM information_schema.TABLE_CONSTRAINTS
                                       WHERE CONSTRAINT_SCHEMA = DATABASE()
                                       AND TABLE_NAME = 'dossiers'
                                       AND CONSTRAINT_NAME = 'fk_dossiers_importe_par'",
                            'sql' => "ALTER TABLE dossiers ADD CONSTRAINT fk_dossiers_importe_par
                                     FOREIGN KEY (importe_par) REFERENCES users(id) ON DELETE SET NULL"
                        ],
                        [
                            'name' => 'Création table entreprises_beneficiaires',
                            'check' => "SHOW TABLES LIKE 'entreprises_beneficiaires'",
                            'sql' => "CREATE TABLE IF NOT EXISTS entreprises_beneficiaires (
                                id INT PRIMARY KEY AUTO_INCREMENT,
                                dossier_id INT NOT NULL,
                                nom VARCHAR(200) NOT NULL,
                                activite VARCHAR(200) NULL,
                                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
                                INDEX idx_dossier (dossier_id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                        ],
                        [
                            'name' => 'Création table logs_import_historique',
                            'check' => "SHOW TABLES LIKE 'logs_import_historique'",
                            'sql' => "CREATE TABLE IF NOT EXISTS logs_import_historique (
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
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                        ]
                    ];

                    // Ajouter l'étape pour le statut selon la structure de la BDD
                    if ($useEnum) {
                        // Structure avec ENUM : modifier la colonne statut
                        echo '<div class="alert alert-info">';
                        echo 'ℹ️ Structure détectée : ENUM pour les statuts (ancienne version)';
                        echo '</div>';

                        $steps[] = [
                            'name' => 'Ajout statut historique_autorise dans ENUM',
                            'check' => "SHOW COLUMNS FROM dossiers LIKE 'statut'",
                            'sql' => "ALTER TABLE dossiers MODIFY COLUMN statut ENUM(
                                'brouillon','cree','en_cours','note_transmise','paye','en_huitaine',
                                'analyse_daj','inspecte','validation_commission','visa_chef_service',
                                'visa_sous_directeur','visa_directeur','valide','decide','autorise',
                                'rejete','ferme','suspendu','historique_autorise'
                            ) DEFAULT 'brouillon'"
                        ];

                        // Vue simplifiée sans les tables manquantes
                        // Vérifier d'abord quelles tables existent
                        $tablesExistantes = [];
                        $checkTables = ['types_infrastructure', 'users', 'entreprises_beneficiaires'];
                        foreach ($checkTables as $tableName) {
                            $result = $pdo->query("SHOW TABLES LIKE '$tableName'");
                            if ($result->rowCount() > 0) {
                                $tablesExistantes[] = $tableName;
                            }
                        }

                        // Construire la requête SQL en fonction des tables disponibles
                        $selectFields = [
                            'd.id',
                            'd.numero',
                            'd.nom_demandeur',
                            'd.region',
                            'd.ville',
                            'd.latitude',
                            'd.longitude',
                            'd.numero_decision_ministerielle',
                            'd.date_decision_ministerielle',
                            'd.observations',
                            'd.importe_le',
                            'd.source_import',
                            'd.statut',
                            'd.created_at'
                        ];

                        $joins = [];

                        if (in_array('types_infrastructure', $tablesExistantes)) {
                            $selectFields[] = "ti.nom as type_infrastructure";
                            $joins[] = "LEFT JOIN types_infrastructure ti ON d.type_infrastructure_id = ti.id";
                        } else {
                            $selectFields[] = "d.type_infrastructure_id as type_infrastructure";
                        }

                        if (in_array('users', $tablesExistantes)) {
                            $selectFields[] = "CONCAT(u.prenom, ' ', u.nom) as importe_par_nom";
                            $joins[] = "LEFT JOIN users u ON d.importe_par = u.id";
                        } else {
                            $selectFields[] = "d.importe_par as importe_par_nom";
                        }

                        if (in_array('entreprises_beneficiaires', $tablesExistantes)) {
                            $selectFields[] = "eb.nom as entreprise_beneficiaire";
                            $selectFields[] = "eb.activite as activite_entreprise";
                            $joins[] = "LEFT JOIN entreprises_beneficiaires eb ON d.id = eb.dossier_id";
                        } else {
                            $selectFields[] = "NULL as entreprise_beneficiaire";
                            $selectFields[] = "NULL as activite_entreprise";
                        }

                        $viewSql = "CREATE OR REPLACE VIEW v_dossiers_historiques AS
                            SELECT " . implode(", ", $selectFields) . "
                            FROM dossiers d
                            " . implode(" ", $joins) . "
                            WHERE d.est_historique = TRUE
                            ORDER BY d.importe_le DESC, d.numero";

                        $steps[] = [
                            'name' => 'Création vue v_dossiers_historiques',
                            'check' => "SELECT COUNT(*) FROM information_schema.VIEWS
                                       WHERE TABLE_SCHEMA = DATABASE()
                                       AND TABLE_NAME = 'v_dossiers_historiques'",
                            'sql' => $viewSql
                        ];
                    } else {
                        // Structure avec table statuts_dossier
                        echo '<div class="alert alert-info">';
                        echo 'ℹ️ Structure détectée : Table statuts_dossier (nouvelle version)';
                        echo '</div>';

                        $steps[] = [
                            'name' => 'Création statut HISTORIQUE_AUTORISE',
                            'check' => "SELECT COUNT(*) FROM statuts_dossier WHERE code = 'HISTORIQUE_AUTORISE'",
                            'sql' => "INSERT INTO statuts_dossier (code, libelle, description, ordre, created_at)
                                     VALUES ('HISTORIQUE_AUTORISE', 'Dossier Historique Autorisé',
                                     'Dossier d''autorisation traité et approuvé avant la mise en place du SGDI',
                                     100, NOW())"
                        ];

                        $steps[] = [
                            'name' => 'Création vue v_dossiers_historiques',
                            'check' => "SELECT COUNT(*) FROM information_schema.VIEWS
                                       WHERE TABLE_SCHEMA = DATABASE()
                                       AND TABLE_NAME = 'v_dossiers_historiques'",
                            'sql' => "CREATE OR REPLACE VIEW v_dossiers_historiques AS
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
                                ORDER BY d.importe_le DESC, d.numero"
                        ];
                    }

                    $success = 0;
                    $errors = 0;

                    foreach ($steps as $index => $step) {
                        $stepNum = $index + 1;
                        $stepName = $step['name'];

                        try {
                            // Vérifier si l'opération a déjà été effectuée
                            $checkResult = $pdo->query($step['check']);
                            $exists = false;

                            if ($checkResult) {
                                $row = $checkResult->fetch();
                                // Déterminer si l'élément existe déjà
                                if (is_array($row)) {
                                    if (isset($row['count'])) {
                                        $exists = ($row['count'] > 0);
                                    } else if (isset($row[0])) {
                                        $exists = !empty($row[0]);
                                    } else {
                                        $exists = (count($row) > 0);
                                    }
                                } else {
                                    $exists = ($checkResult->rowCount() > 0);
                                }
                            }

                            if ($exists && strpos($step['sql'], 'CREATE OR REPLACE') === false && strpos($step['sql'], 'INSERT') === false) {
                                echo '<div class="alert alert-info">';
                                echo 'ℹ️ Étape ' . $stepNum . ' (' . $stepName . ') : Déjà effectuée (ignorée)';
                                echo '</div>';
                                $success++;
                            } else {
                                // Exécuter la requête SQL
                                $pdo->exec($step['sql']);
                                echo '<div class="alert alert-success">';
                                echo '✅ Étape ' . $stepNum . ' (' . $stepName . ') : Réussie';
                                echo '</div>';
                                $success++;
                            }
                        } catch (PDOException $e) {
                            $errorMsg = $e->getMessage();
                            $code = $e->getCode();

                            // Ignorer certaines erreurs non critiques
                            if (strpos($errorMsg, 'Duplicate column') !== false ||
                                strpos($errorMsg, 'Duplicate key') !== false ||
                                strpos($errorMsg, 'already exists') !== false ||
                                strpos($errorMsg, 'Duplicate entry') !== false ||
                                $code == '42S21' || $code == '23000') {
                                echo '<div class="alert alert-info">';
                                echo 'ℹ️ Étape ' . $stepNum . ' (' . $stepName . ') : Déjà effectuée';
                                echo '</div>';
                                $success++;
                            } else {
                                echo '<div class="alert alert-danger">';
                                echo '❌ Étape ' . $stepNum . ' (' . $stepName . ') : Erreur<br>';
                                echo '<strong>Code :</strong> ' . htmlspecialchars($code) . '<br>';
                                echo '<strong>Message :</strong> ' . htmlspecialchars($errorMsg);
                                echo '</div>';
                                $errors++;
                            }
                        }
                    }

                    echo '<hr>';

                    if ($errors === 0) {
                        echo '<div class="alert alert-success">';
                        echo '<h4>✅ Migration terminée avec succès !</h4>';
                        echo '<p>' . $success . ' opération(s) exécutée(s)</p>';
                        echo '<p>Le module d\'import de dossiers historiques est maintenant prêt.</p>';
                        echo '<a href="index.php" class="btn btn-primary">Accéder au module d\'import</a>';
                        echo '</div>';
                    } else {
                        echo '<div class="alert alert-warning">';
                        echo '<h4>⚠️ Migration terminée avec des erreurs</h4>';
                        echo '<p>Succès : ' . $success . ' | Erreurs : ' . $errors . '</p>';
                        echo '<p>Certaines tables existaient peut-être déjà.</p>';
                        echo '<a href="test_database.php" class="btn btn-info">Vérifier la base</a>';
                        echo '</div>';
                    }
                }
            }
            ?>

            <hr>
            <p class="text-center">
                <a href="test_database.php" class="btn btn-secondary">Test de la base</a>
                <a href="index.php" class="btn btn-primary">Retour au module</a>
                <a href="../../dashboard.php" class="btn btn-outline-secondary">Dashboard</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>

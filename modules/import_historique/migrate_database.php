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
            // Vérifier si la migration a déjà été exécutée
            $sql = "SHOW TABLES LIKE 'entreprises_beneficiaires'";
            $result = $pdo->query($sql);
            $table_existe = $result->fetch();

            if ($table_existe && !isset($_POST['force_migration'])) {
                echo '<div class="alert alert-info">';
                echo '<h4>✅ Migration déjà exécutée</h4>';
                echo '<p>La table <code>entreprises_beneficiaires</code> existe déjà.</p>';
                echo '<p>Si vous voulez ré-exécuter la migration, cliquez ci-dessous :</p>';
                echo '<form method="POST">';
                echo '<input type="hidden" name="force_migration" value="1">';
                echo '<button type="submit" class="btn btn-warning">Forcer la ré-exécution</button>';
                echo '</form>';
                echo '</div>';
            } else {
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

                    $migrations = [
                        // 1. Modifier l'ENUM statut
                        "ALTER TABLE dossiers MODIFY COLUMN statut ENUM(
                            'brouillon','cree','en_cours','note_transmise','paye','en_huitaine',
                            'analyse_daj','inspecte','validation_commission','visa_chef_service',
                            'visa_sous_directeur','visa_directeur','valide','decide','autorise',
                            'rejete','ferme','suspendu','historique_autorise'
                        ) DEFAULT 'brouillon'",

                        // 2. Créer table entreprises_beneficiaires
                        "CREATE TABLE IF NOT EXISTS entreprises_beneficiaires (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            dossier_id INT NOT NULL,
                            nom VARCHAR(200) NOT NULL,
                            activite VARCHAR(200) NULL,
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_dossier (dossier_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

                        // 3. Créer table logs_import_historique
                        "CREATE TABLE IF NOT EXISTS logs_import_historique (
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
                            INDEX idx_user (user_id),
                            INDEX idx_date (created_at)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                    ];

                    $success = 0;
                    $errors = 0;

                    foreach ($migrations as $index => $sql) {
                        try {
                            $pdo->exec($sql);
                            echo '<div class="alert alert-success">';
                            echo '✅ Étape ' . ($index + 1) . ' : Réussie';
                            echo '</div>';
                            $success++;
                        } catch (PDOException $e) {
                            echo '<div class="alert alert-danger">';
                            echo '❌ Étape ' . ($index + 1) . ' : Erreur<br>';
                            echo '<small>' . htmlspecialchars($e->getMessage()) . '</small>';
                            echo '</div>';
                            $errors++;
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

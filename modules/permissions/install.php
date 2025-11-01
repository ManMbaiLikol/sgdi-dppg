<?php
/**
 * Script d'installation du système de permissions granulaires
 * SGDI - Système de Gestion des Dossiers d'Implantation
 *
 * Ce script crée les tables nécessaires et insère les permissions par défaut
 */

require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Seuls les admins peuvent installer
requireRole('admin');

$page_title = 'Installation du système de permissions';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-shield-alt"></i> Installation du système de permissions granulaires
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $errors = [];
                        $success = [];

                        // Étape 1 : Créer la table permissions
                        try {
                            $sql = "CREATE TABLE IF NOT EXISTS permissions (
                                id INT PRIMARY KEY AUTO_INCREMENT,
                                code VARCHAR(100) UNIQUE NOT NULL,
                                module VARCHAR(50) NOT NULL,
                                nom VARCHAR(150) NOT NULL,
                                description TEXT,
                                date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                INDEX idx_permissions_module (module),
                                INDEX idx_permissions_code (code)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

                            $pdo->exec($sql);
                            $success[] = "✓ Table 'permissions' créée avec succès";
                        } catch (PDOException $e) {
                            $errors[] = "✗ Erreur création table 'permissions': " . $e->getMessage();
                        }

                        // Étape 2 : Créer la table user_permissions
                        try {
                            $sql = "CREATE TABLE IF NOT EXISTS user_permissions (
                                id INT PRIMARY KEY AUTO_INCREMENT,
                                user_id INT NOT NULL,
                                permission_id INT NOT NULL,
                                accordee_par INT NOT NULL,
                                date_attribution TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                                FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
                                FOREIGN KEY (accordee_par) REFERENCES users(id),
                                UNIQUE KEY unique_user_permission (user_id, permission_id),
                                INDEX idx_user_permissions_user (user_id),
                                INDEX idx_user_permissions_permission (permission_id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

                            $pdo->exec($sql);
                            $success[] = "✓ Table 'user_permissions' créée avec succès";
                        } catch (PDOException $e) {
                            $errors[] = "✗ Erreur création table 'user_permissions': " . $e->getMessage();
                        }

                        // Étape 3 : Insérer les permissions par défaut
                        try {
                            // Lire et exécuter le fichier SQL des permissions
                            $sql_file = __DIR__ . '/../../database/permissions_schema.sql';

                            if (file_exists($sql_file)) {
                                $sql_content = file_get_contents($sql_file);

                                // Extraire uniquement les INSERT INTO permissions
                                preg_match_all('/INSERT INTO permissions.*?;/s', $sql_content, $matches);

                                $inserted = 0;
                                foreach ($matches[0] as $insert_sql) {
                                    try {
                                        $pdo->exec($insert_sql);
                                        $inserted++;
                                    } catch (PDOException $e) {
                                        // Ignorer les doublons
                                        if ($e->getCode() != 23000) {
                                            throw $e;
                                        }
                                    }
                                }

                                $success[] = "✓ $inserted permissions insérées avec succès";
                            } else {
                                $errors[] = "✗ Fichier permissions_schema.sql introuvable";
                            }
                        } catch (PDOException $e) {
                            $errors[] = "✗ Erreur insertion permissions: " . $e->getMessage();
                        }

                        // Afficher les résultats
                        if (!empty($success)): ?>
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle"></i> Succès</h5>
                                <ul class="mb-0">
                                    <?php foreach ($success as $msg): ?>
                                        <li><?php echo $msg; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif;

                        if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <h5><i class="fas fa-exclamation-triangle"></i> Erreurs</h5>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $msg): ?>
                                        <li><?php echo $msg; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif;

                        // Vérifications finales
                        try {
                            $count_permissions = $pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
                            $count_modules = $pdo->query("SELECT COUNT(DISTINCT module) FROM permissions")->fetchColumn();
                            ?>

                            <div class="alert alert-info mt-3">
                                <h5><i class="fas fa-info-circle"></i> Statistiques</h5>
                                <ul class="mb-0">
                                    <li><strong><?php echo $count_permissions; ?></strong> permissions disponibles</li>
                                    <li><strong><?php echo $count_modules; ?></strong> modules couverts</li>
                                </ul>
                            </div>

                            <?php if (empty($errors)): ?>
                                <div class="alert alert-success mt-3">
                                    <h5><i class="fas fa-check-double"></i> Installation terminée</h5>
                                    <p class="mb-0">
                                        Le système de permissions granulaires est maintenant opérationnel.
                                        Vous pouvez commencer à attribuer des permissions aux utilisateurs.
                                    </p>
                                </div>
                            <?php endif;

                        } catch (PDOException $e) {
                            echo '<div class="alert alert-warning">Impossible de vérifier l\'installation: ' . $e->getMessage() . '</div>';
                        }
                        ?>

                        <div class="mt-4 d-flex gap-2">
                            <?php if (empty($errors)): ?>
                                <a href="<?php echo url('modules/permissions/index.php'); ?>" class="btn btn-primary">
                                    <i class="fas fa-shield-alt"></i> Accéder à la gestion des permissions
                                </a>
                            <?php else: ?>
                                <button onclick="location.reload()" class="btn btn-warning">
                                    <i class="fas fa-redo"></i> Réessayer l'installation
                                </button>
                            <?php endif; ?>

                            <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-secondary">
                                <i class="fas fa-home"></i> Retour au dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Instructions post-installation -->
                <?php if (empty($errors)): ?>
                <div class="card shadow mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-book"></i> Prochaines étapes
                        </h5>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li class="mb-2">
                                <strong>Attribuer des permissions aux utilisateurs</strong>
                                <br>
                                <small class="text-muted">
                                    Allez dans Administration > Permissions pour gérer les permissions des utilisateurs
                                </small>
                            </li>
                            <li class="mb-2">
                                <strong>Utiliser les permissions recommandées</strong>
                                <br>
                                <small class="text-muted">
                                    Chaque rôle dispose de permissions recommandées par défaut
                                </small>
                            </li>
                            <li class="mb-2">
                                <strong>Personnaliser les permissions</strong>
                                <br>
                                <small class="text-muted">
                                    Vous pouvez affiner les permissions pour chaque utilisateur selon vos besoins
                                </small>
                            </li>
                            <li class="mb-0">
                                <strong>Consulter la documentation</strong>
                                <br>
                                <small class="text-muted">
                                    Voir docs/PERMISSIONS_GUIDE.md pour plus de détails
                                </small>
                            </li>
                        </ol>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

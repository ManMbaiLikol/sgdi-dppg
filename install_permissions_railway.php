<?php
/**
 * Installation des tables de permissions sur Railway
 * À exécuter une seule fois sur l'environnement de production
 *
 * URL: https://sgdi-dppg-production.up.railway.app/install_permissions_railway.php
 *
 * IMPORTANT: Supprimer ce fichier après l'installation !
 */

// Sécurité basique - à adapter selon vos besoins
$INSTALL_PASSWORD = 'install2024'; // Changez ce mot de passe !

session_start();
require_once 'config/database.php';

$error = null;
$success = null;
$step = 1;

// Vérification du mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $INSTALL_PASSWORD) {
        $_SESSION['install_authorized'] = true;
    } else {
        $error = "Mot de passe incorrect";
    }
}

// Vérifier si autorisé
$authorized = isset($_SESSION['install_authorized']) && $_SESSION['install_authorized'] === true;

// Traitement de l'installation
if ($authorized && isset($_POST['install'])) {
    try {
        $pdo->beginTransaction();

        // 1. Créer la table permissions
        $sql_permissions = "CREATE TABLE IF NOT EXISTS permissions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            code VARCHAR(100) UNIQUE NOT NULL,
            module VARCHAR(50) NOT NULL,
            nom VARCHAR(150) NOT NULL,
            description TEXT,
            date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_permissions_module (module),
            INDEX idx_permissions_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $pdo->exec($sql_permissions);

        // 2. Créer la table user_permissions
        $sql_user_permissions = "CREATE TABLE IF NOT EXISTS user_permissions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            permission_id INT NOT NULL,
            accordee_par INT NOT NULL,
            date_attribution TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_permission (user_id, permission_id),
            INDEX idx_user_permissions_user (user_id),
            INDEX idx_user_permissions_permission (permission_id),
            INDEX idx_user_permissions_accordee (accordee_par)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $pdo->exec($sql_user_permissions);

        // 3. Insérer les permissions
        $permissions = [
            // Module Dossiers
            ['dossiers.create', 'dossiers', 'Créer un dossier', 'Permet de créer de nouveaux dossiers d\'implantation'],
            ['dossiers.view', 'dossiers', 'Voir les dossiers', 'Permet de consulter les dossiers'],
            ['dossiers.edit', 'dossiers', 'Modifier un dossier', 'Permet de modifier les informations d\'un dossier'],
            ['dossiers.delete', 'dossiers', 'Supprimer un dossier', 'Permet de supprimer un dossier'],
            ['dossiers.list', 'dossiers', 'Lister les dossiers', 'Permet d\'accéder à la liste des dossiers'],
            ['dossiers.view_all', 'dossiers', 'Voir tous les dossiers', 'Permet de voir tous les dossiers sans filtre de rôle'],
            ['dossiers.export', 'dossiers', 'Exporter les dossiers', 'Permet d\'exporter les données des dossiers'],
            ['dossiers.localisation', 'dossiers', 'Gérer la localisation', 'Permet de gérer les coordonnées GPS et la carte'],

            // Module Commission
            ['commission.create', 'commission', 'Constituer une commission', 'Permet de nommer les membres d\'une commission'],
            ['commission.view', 'commission', 'Voir les commissions', 'Permet de consulter les commissions'],
            ['commission.edit', 'commission', 'Modifier une commission', 'Permet de modifier la composition d\'une commission'],
            ['commission.validate', 'commission', 'Valider une inspection', 'Permet de valider un rapport d\'inspection'],

            // Module Paiements
            ['paiements.view', 'paiements', 'Voir les paiements', 'Permet de consulter les paiements'],
            ['paiements.create', 'paiements', 'Enregistrer un paiement', 'Permet d\'enregistrer un nouveau paiement'],
            ['paiements.edit', 'paiements', 'Modifier un paiement', 'Permet de modifier un paiement existant'],
            ['paiements.receipt', 'paiements', 'Générer un reçu', 'Permet de générer et imprimer un reçu de paiement'],

            // Module DAJ
            ['daj.view', 'daj', 'Voir les analyses DAJ', 'Permet de consulter les analyses juridiques'],
            ['daj.create', 'daj', 'Faire une analyse DAJ', 'Permet de réaliser une analyse juridique'],
            ['daj.edit', 'daj', 'Modifier une analyse DAJ', 'Permet de modifier une analyse juridique'],
            ['daj.validate', 'daj', 'Valider une analyse DAJ', 'Permet de valider une analyse juridique'],

            // Module Inspections
            ['inspections.view', 'inspections', 'Voir les inspections', 'Permet de consulter les inspections'],
            ['inspections.create', 'inspections', 'Faire une inspection', 'Permet de réaliser une inspection terrain'],
            ['inspections.edit', 'inspections', 'Modifier une inspection', 'Permet de modifier un rapport d\'inspection'],
            ['inspections.validate', 'inspections', 'Valider une inspection', 'Permet de valider une inspection'],
            ['inspections.print', 'inspections', 'Imprimer les fiches', 'Permet d\'imprimer les fiches d\'inspection'],

            // Module Visa
            ['visa.chef_service', 'visa', 'Visa Chef Service', 'Permet d\'apposer le visa niveau 1'],
            ['visa.sous_directeur', 'visa', 'Visa Sous-Directeur', 'Permet d\'apposer le visa niveau 2'],
            ['visa.directeur', 'visa', 'Visa Directeur', 'Permet d\'apposer le visa niveau 3'],
            ['visa.view', 'visa', 'Voir les visas', 'Permet de consulter les visas apposés'],

            // Module Décisions
            ['decisions.view', 'decisions', 'Voir les décisions', 'Permet de consulter les décisions ministérielles'],
            ['decisions.create', 'decisions', 'Prendre une décision', 'Permet de prendre une décision finale'],
            ['decisions.transmit', 'decisions', 'Transmettre au ministre', 'Permet de transmettre un dossier au ministre'],

            // Module Documents
            ['documents.view', 'documents', 'Voir les documents', 'Permet de consulter les documents'],
            ['documents.upload', 'documents', 'Uploader des documents', 'Permet d\'uploader de nouveaux documents'],
            ['documents.download', 'documents', 'Télécharger des documents', 'Permet de télécharger les documents'],
            ['documents.delete', 'documents', 'Supprimer des documents', 'Permet de supprimer des documents'],

            // Module Utilisateurs
            ['users.view', 'users', 'Voir les utilisateurs', 'Permet de consulter la liste des utilisateurs'],
            ['users.create', 'users', 'Créer un utilisateur', 'Permet de créer de nouveaux utilisateurs'],
            ['users.edit', 'users', 'Modifier un utilisateur', 'Permet de modifier les informations d\'un utilisateur'],
            ['users.delete', 'users', 'Supprimer un utilisateur', 'Permet de supprimer un utilisateur'],
            ['users.manage_permissions', 'users', 'Gérer les permissions', 'Permet d\'attribuer des permissions aux utilisateurs'],

            // Module Huitaine
            ['huitaine.view', 'huitaine', 'Voir les huitaines', 'Permet de consulter les huitaines en cours'],
            ['huitaine.create', 'huitaine', 'Créer une huitaine', 'Permet de déclencher une huitaine'],
            ['huitaine.regularize', 'huitaine', 'Régulariser une huitaine', 'Permet de régulariser un dossier en huitaine'],

            // Module GPS
            ['gps.view', 'gps', 'Voir les données GPS', 'Permet de consulter les données GPS'],
            ['gps.edit', 'gps', 'Modifier les données GPS', 'Permet de modifier les coordonnées GPS'],
            ['gps.import', 'gps', 'Importer des données GPS', 'Permet d\'importer des données GPS'],
            ['gps.validate', 'gps', 'Valider les coordonnées GPS', 'Permet de valider la cohérence géographique'],

            // Module Rapports
            ['rapports.view', 'rapports', 'Voir les rapports', 'Permet de consulter les rapports'],
            ['rapports.export_excel', 'rapports', 'Exporter en Excel', 'Permet d\'exporter des rapports Excel'],
            ['rapports.export_pdf', 'rapports', 'Exporter en PDF', 'Permet d\'exporter des rapports PDF'],
            ['rapports.statistics', 'rapports', 'Voir les statistiques', 'Permet d\'accéder aux statistiques avancées'],

            // Module Registre Public
            ['registre_public.manage', 'registre_public', 'Gérer le registre public', 'Permet de gérer les publications au registre public'],

            // Module Carte
            ['carte.view', 'carte', 'Voir la carte', 'Permet d\'accéder à la carte des infrastructures'],
            ['carte.export', 'carte', 'Exporter la carte', 'Permet d\'exporter les données cartographiques'],

            // Module Administration
            ['admin.dashboard', 'admin', 'Dashboard admin', 'Permet d\'accéder au tableau de bord administrateur'],
            ['admin.email_logs', 'admin', 'Voir les logs emails', 'Permet de consulter les logs d\'emails'],
            ['admin.test_email', 'admin', 'Tester les emails', 'Permet de tester l\'envoi d\'emails'],
            ['admin.system_settings', 'admin', 'Paramètres système', 'Permet de modifier les paramètres du système']
        ];

        $sql_insert = "INSERT IGNORE INTO permissions (code, module, nom, description) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql_insert);

        $count = 0;
        foreach ($permissions as $perm) {
            $stmt->execute($perm);
            if ($stmt->rowCount() > 0) {
                $count++;
            }
        }

        $pdo->commit();

        $success = "Installation réussie !<br>";
        $success .= "- Table 'permissions' créée ✓<br>";
        $success .= "- Table 'user_permissions' créée ✓<br>";
        $success .= "- {$count} permissions insérées ✓<br><br>";
        $success .= "<strong style='color: red;'>IMPORTANT: Supprimez ce fichier install_permissions_railway.php immédiatement !</strong>";

        $step = 3;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur lors de l'installation : " . $e->getMessage();
    }
}

// Vérifier si déjà installé
$already_installed = false;
if ($authorized) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM permissions");
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            $already_installed = true;
            $step = 2;
        }
    } catch (Exception $e) {
        // Table n'existe pas encore
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Permissions - SGDI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-database"></i> Installation du Système de Permissions
                        </h4>
                    </div>
                    <div class="card-body">

                        <?php if (!$authorized): ?>
                            <!-- Étape 1: Authentification -->
                            <div class="alert alert-warning">
                                <i class="fas fa-lock"></i>
                                <strong>Authentification requise</strong><br>
                                Entrez le mot de passe d'installation pour continuer.
                            </div>

                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Mot de passe d'installation</label>
                                    <input type="password" name="password" class="form-control" required autofocus>
                                    <small class="text-muted">Mot de passe par défaut: install2024</small>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Se connecter
                                </button>
                            </form>

                        <?php elseif ($already_installed && $step === 2): ?>
                            <!-- Étape 2: Déjà installé -->
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Système déjà installé</strong><br>
                                Les tables de permissions existent déjà dans la base de données.
                            </div>

                            <form method="POST">
                                <p>Voulez-vous réinstaller le système ? Cela va :</p>
                                <ul>
                                    <li>Conserver les tables existantes</li>
                                    <li>Ajouter les permissions manquantes (IGNORE)</li>
                                    <li>Ne pas supprimer les attributions existantes</li>
                                </ul>
                                <button type="submit" name="install" value="1" class="btn btn-warning">
                                    <i class="fas fa-sync"></i> Réinstaller / Mettre à jour
                                </button>
                                <a href="modules/permissions/index.php" class="btn btn-success">
                                    <i class="fas fa-arrow-right"></i> Aller à la gestion des permissions
                                </a>
                            </form>

                        <?php elseif ($step === 3): ?>
                            <!-- Étape 3: Installation terminée -->
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                            </div>

                            <div class="d-grid gap-2">
                                <a href="modules/permissions/index.php" class="btn btn-success btn-lg">
                                    <i class="fas fa-arrow-right"></i> Accéder à la gestion des permissions
                                </a>
                            </div>

                        <?php else: ?>
                            <!-- Étape 2: Lancer l'installation -->
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Prêt pour l'installation</strong><br>
                                Cliquez sur le bouton ci-dessous pour installer les tables de permissions.
                            </div>

                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>

                            <form method="POST">
                                <p>Cette installation va :</p>
                                <ul>
                                    <li>Créer la table <code>permissions</code></li>
                                    <li>Créer la table <code>user_permissions</code></li>
                                    <li>Insérer 41 permissions couvrant 13 modules</li>
                                </ul>

                                <button type="submit" name="install" value="1" class="btn btn-primary btn-lg">
                                    <i class="fas fa-play"></i> Lancer l'installation
                                </button>
                            </form>
                        <?php endif; ?>

                    </div>
                </div>

                <?php if ($step === 3): ?>
                <div class="alert alert-danger mt-4">
                    <h5><i class="fas fa-exclamation-triangle"></i> SÉCURITÉ IMPORTANTE</h5>
                    <p class="mb-0">
                        <strong>Supprimez immédiatement ce fichier</strong> <code>install_permissions_railway.php</code>
                        du serveur pour des raisons de sécurité !
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

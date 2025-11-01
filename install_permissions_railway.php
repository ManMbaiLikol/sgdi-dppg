<?php
/**
 * Installation des tables de permissions sur Railway
 * Version simplifiée avec affichage des erreurs
 */

// Afficher toutes les erreurs pour debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Sécurité basique
$INSTALL_PASSWORD = 'install2024';

session_start();

// Tentative de connexion à la base de données
try {
    require_once __DIR__ . '/config/database.php';
    $db_connected = true;
} catch (Exception $e) {
    $db_connected = false;
    $db_error = $e->getMessage();
}

$error = null;
$success = null;
$messages = [];

// Vérification du mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $INSTALL_PASSWORD) {
        $_SESSION['install_authorized'] = true;
    } else {
        $error = "Mot de passe incorrect";
    }
}

$authorized = isset($_SESSION['install_authorized']) && $_SESSION['install_authorized'] === true;

// Installation
if ($authorized && $db_connected && isset($_POST['install'])) {
    try {
        $messages[] = "Début de l'installation...";

        $pdo->beginTransaction();
        $messages[] = "Transaction démarrée";

        // 1. Créer table permissions
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
        $messages[] = "✓ Table 'permissions' créée";

        // 2. Créer table user_permissions
        $sql_user_permissions = "CREATE TABLE IF NOT EXISTS user_permissions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            permission_id INT NOT NULL,
            accordee_par INT NOT NULL,
            date_attribution TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_permission (user_id, permission_id),
            INDEX idx_user_permissions_user (user_id),
            INDEX idx_user_permissions_permission (permission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $pdo->exec($sql_user_permissions);
        $messages[] = "✓ Table 'user_permissions' créée";

        // 3. Insérer les permissions
        $sql_insert = "INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
            ('dossiers.create', 'dossiers', 'Créer un dossier', 'Permet de créer de nouveaux dossiers'),
            ('dossiers.view', 'dossiers', 'Voir les dossiers', 'Permet de consulter les dossiers'),
            ('dossiers.edit', 'dossiers', 'Modifier un dossier', 'Permet de modifier les informations'),
            ('dossiers.delete', 'dossiers', 'Supprimer un dossier', 'Permet de supprimer un dossier'),
            ('dossiers.list', 'dossiers', 'Lister les dossiers', 'Permet d\'accéder à la liste des dossiers'),
            ('dossiers.view_all', 'dossiers', 'Voir tous les dossiers', 'Permet de voir tous les dossiers'),
            ('dossiers.export', 'dossiers', 'Exporter les dossiers', 'Permet d\'exporter les données'),
            ('dossiers.localisation', 'dossiers', 'Gérer la localisation', 'Permet de gérer les coordonnées GPS'),
            ('commission.create', 'commission', 'Constituer une commission', 'Permet de nommer les membres'),
            ('commission.view', 'commission', 'Voir les commissions', 'Permet de consulter les commissions'),
            ('commission.edit', 'commission', 'Modifier une commission', 'Permet de modifier la composition'),
            ('commission.validate', 'commission', 'Valider une inspection', 'Permet de valider un rapport'),
            ('paiements.view', 'paiements', 'Voir les paiements', 'Permet de consulter les paiements'),
            ('paiements.create', 'paiements', 'Enregistrer un paiement', 'Permet d\'enregistrer un paiement'),
            ('paiements.edit', 'paiements', 'Modifier un paiement', 'Permet de modifier un paiement'),
            ('paiements.receipt', 'paiements', 'Générer un reçu', 'Permet de générer un reçu'),
            ('daj.view', 'daj', 'Voir les analyses DAJ', 'Permet de consulter les analyses'),
            ('daj.create', 'daj', 'Faire une analyse DAJ', 'Permet de réaliser une analyse'),
            ('daj.edit', 'daj', 'Modifier une analyse DAJ', 'Permet de modifier une analyse'),
            ('daj.validate', 'daj', 'Valider une analyse DAJ', 'Permet de valider une analyse'),
            ('inspections.view', 'inspections', 'Voir les inspections', 'Permet de consulter les inspections'),
            ('inspections.create', 'inspections', 'Faire une inspection', 'Permet de réaliser une inspection'),
            ('inspections.edit', 'inspections', 'Modifier une inspection', 'Permet de modifier un rapport'),
            ('inspections.validate', 'inspections', 'Valider une inspection', 'Permet de valider une inspection'),
            ('inspections.print', 'inspections', 'Imprimer les fiches', 'Permet d\'imprimer les fiches'),
            ('visa.chef_service', 'visa', 'Visa Chef Service', 'Permet d\'apposer le visa niveau 1'),
            ('visa.sous_directeur', 'visa', 'Visa Sous-Directeur', 'Permet d\'apposer le visa niveau 2'),
            ('visa.directeur', 'visa', 'Visa Directeur', 'Permet d\'apposer le visa niveau 3'),
            ('visa.view', 'visa', 'Voir les visas', 'Permet de consulter les visas'),
            ('decisions.view', 'decisions', 'Voir les décisions', 'Permet de consulter les décisions'),
            ('decisions.create', 'decisions', 'Prendre une décision', 'Permet de prendre une décision'),
            ('decisions.transmit', 'decisions', 'Transmettre au ministre', 'Permet de transmettre un dossier'),
            ('documents.view', 'documents', 'Voir les documents', 'Permet de consulter les documents'),
            ('documents.upload', 'documents', 'Uploader des documents', 'Permet d\'uploader des documents'),
            ('documents.download', 'documents', 'Télécharger des documents', 'Permet de télécharger'),
            ('documents.delete', 'documents', 'Supprimer des documents', 'Permet de supprimer'),
            ('users.view', 'users', 'Voir les utilisateurs', 'Permet de consulter la liste'),
            ('users.create', 'users', 'Créer un utilisateur', 'Permet de créer des utilisateurs'),
            ('users.edit', 'users', 'Modifier un utilisateur', 'Permet de modifier un utilisateur'),
            ('users.delete', 'users', 'Supprimer un utilisateur', 'Permet de supprimer un utilisateur'),
            ('users.manage_permissions', 'users', 'Gérer les permissions', 'Permet d\'attribuer des permissions')";

        $stmt = $pdo->exec($sql_insert);
        $messages[] = "✓ Permissions insérées";

        $pdo->commit();
        $messages[] = "✓ Transaction validée";

        $success = true;

    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        $error = "Erreur : " . $e->getMessage();
        $messages[] = "✗ Erreur : " . $e->getMessage();
    }
}

// Vérifier si déjà installé
$already_installed = false;
if ($authorized && $db_connected) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM permissions");
        $count = $stmt->fetchColumn();
        $already_installed = $count > 0;
    } catch (Exception $e) {
        // Pas encore installé
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Permissions - SGDI</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; margin-top: 0; }
        .alert { padding: 15px; margin: 15px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn:hover { opacity: 0.9; }
        input[type="password"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        .messages { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0; }
        .messages div { margin: 5px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔧 Installation Permissions - SGDI</h1>

        <?php if (!$db_connected): ?>
            <div class="alert alert-danger">
                <strong>Erreur de connexion à la base de données</strong><br>
                <?php echo htmlspecialchars($db_error); ?>
            </div>
        <?php elseif (!$authorized): ?>
            <div class="alert alert-warning">
                <strong>🔒 Authentification requise</strong><br>
                Entrez le mot de passe d'installation.
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <label>Mot de passe :</label>
                <input type="password" name="password" required autofocus>
                <small style="color: #666;">Par défaut: install2024</small><br><br>
                <button type="submit" class="btn btn-primary">Se connecter</button>
            </form>

        <?php elseif ($success): ?>
            <div class="alert alert-success">
                <strong>✓ Installation réussie !</strong>
            </div>
            <?php if (!empty($messages)): ?>
                <div class="messages">
                    <?php foreach ($messages as $msg): ?>
                        <div><?php echo htmlspecialchars($msg); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a href="modules/permissions/index.php" class="btn btn-success">
                → Accéder à la gestion des permissions
            </a>
            <div class="alert alert-danger" style="margin-top: 20px;">
                <strong>⚠️ SÉCURITÉ</strong><br>
                Supprimez immédiatement le fichier <code>install_permissions_railway.php</code> !
            </div>

        <?php elseif ($already_installed): ?>
            <div class="alert alert-info">
                <strong>ℹ️ Déjà installé</strong><br>
                Les tables de permissions existent déjà.
            </div>
            <form method="POST">
                <p>Voulez-vous réinstaller / mettre à jour ?</p>
                <button type="submit" name="install" value="1" class="btn btn-primary">
                    🔄 Réinstaller
                </button>
                <a href="modules/permissions/index.php" class="btn btn-success">
                    → Gestion des permissions
                </a>
            </form>

        <?php else: ?>
            <div class="alert alert-info">
                <strong>Prêt pour l'installation</strong>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($messages)): ?>
                <div class="messages">
                    <?php foreach ($messages as $msg): ?>
                        <div><?php echo htmlspecialchars($msg); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <p>Cette installation va créer :</p>
                <ul>
                    <li>Table <code>permissions</code></li>
                    <li>Table <code>user_permissions</code></li>
                    <li>41 permissions sur 13 modules</li>
                </ul>
                <button type="submit" name="install" value="1" class="btn btn-primary">
                    ▶️ Lancer l'installation
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

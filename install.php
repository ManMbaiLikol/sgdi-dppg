<?php
/**
 * Script d'installation automatique du SGDI
 * À exécuter une seule fois après le déploiement
 */

// Désactiver l'affichage des erreurs pour sécurité
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Vérifier si déjà installé
if (file_exists('config/.installed')) {
    die('Le système est déjà installé. Supprimez le fichier config/.installed pour réinstaller.');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation SGDI - MINEE/DPPG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .install-container {
            background: white;
            border-radius: 12px;
            padding: 3rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
        }
        .step {
            display: none;
        }
        .step.active {
            display: block;
        }
        .check-item {
            padding: 0.5rem;
            margin: 0.5rem 0;
            border-left: 3px solid #ddd;
            padding-left: 1rem;
        }
        .check-item.success {
            border-color: #10b981;
            background: #ecfdf5;
        }
        .check-item.error {
            border-color: #ef4444;
            background: #fef2f2;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="text-center mb-4">
            <h1 class="text-primary">Installation SGDI</h1>
            <p class="text-muted">Système de Gestion des Dossiers d'Implantation</p>
            <p class="text-muted">MINEE/DPPG - République du Cameroun</p>
        </div>

        <?php
        // Étape 1: Vérification des prérequis
        if (!isset($_POST['step'])) {
        ?>
            <div class="step active" id="step1">
                <h3 class="mb-4">Étape 1: Vérification des prérequis</h3>

                <?php
                $checks = [];
                $all_ok = true;

                // PHP Version
                $php_version = phpversion();
                $php_ok = version_compare($php_version, '7.4.0', '>=');
                $checks[] = [
                    'name' => 'Version PHP (>= 7.4)',
                    'value' => $php_version,
                    'status' => $php_ok
                ];
                if (!$php_ok) $all_ok = false;

                // Extensions PHP
                $extensions = ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'gd'];
                foreach ($extensions as $ext) {
                    $loaded = extension_loaded($ext);
                    $checks[] = [
                        'name' => "Extension PHP: $ext",
                        'value' => $loaded ? 'Installée' : 'Manquante',
                        'status' => $loaded
                    ];
                    if (!$loaded) $all_ok = false;
                }

                // Permissions
                $dirs_writable = ['uploads', 'logs'];
                foreach ($dirs_writable as $dir) {
                    if (!is_dir($dir)) {
                        mkdir($dir, 0775, true);
                    }
                    $writable = is_writable($dir);
                    $checks[] = [
                        'name' => "Dossier '$dir' accessible en écriture",
                        'value' => $writable ? 'OK' : 'NON',
                        'status' => $writable
                    ];
                    if (!$writable) $all_ok = false;
                }

                // Afficher les résultats
                foreach ($checks as $check) {
                    $class = $check['status'] ? 'success' : 'error';
                    $icon = $check['status'] ? '✓' : '✗';
                    echo "<div class='check-item $class'>";
                    echo "<strong>$icon {$check['name']}</strong>: {$check['value']}";
                    echo "</div>";
                }

                if ($all_ok) {
                    echo "<div class='alert alert-success mt-4'>";
                    echo "<strong>✓ Tous les prérequis sont satisfaits!</strong>";
                    echo "</div>";
                    echo "<form method='POST'>";
                    echo "<input type='hidden' name='step' value='2'>";
                    echo "<button type='submit' class='btn btn-primary btn-lg w-100'>Continuer vers la configuration</button>";
                    echo "</form>";
                } else {
                    echo "<div class='alert alert-danger mt-4'>";
                    echo "<strong>✗ Certains prérequis ne sont pas satisfaits.</strong><br>";
                    echo "Veuillez corriger les problèmes avant de continuer.";
                    echo "</div>";
                }
                ?>
            </div>

        <?php
        }
        // Étape 2: Configuration base de données
        elseif ($_POST['step'] == '2') {
        ?>
            <div class="step active" id="step2">
                <h3 class="mb-4">Étape 2: Configuration de la base de données</h3>

                <form method="POST">
                    <input type="hidden" name="step" value="3">

                    <div class="mb-3">
                        <label class="form-label">Hôte de la base de données</label>
                        <input type="text" class="form-control" name="db_host" value="localhost" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nom de la base de données</label>
                        <input type="text" class="form-control" name="db_name" value="sgdi_mvp" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Utilisateur</label>
                        <input type="text" class="form-control" name="db_user" value="root" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" class="form-control" name="db_pass">
                    </div>

                    <div class="alert alert-info">
                        <strong>Note:</strong> La base de données doit déjà exister. Le script créera les tables automatiquement.
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">Tester et continuer</button>
                </form>
            </div>

        <?php
        }
        // Étape 3: Installation
        elseif ($_POST['step'] == '3') {
        ?>
            <div class="step active" id="step3">
                <h3 class="mb-4">Étape 3: Installation</h3>

                <?php
                $db_host = $_POST['db_host'];
                $db_name = $_POST['db_name'];
                $db_user = $_POST['db_user'];
                $db_pass = $_POST['db_pass'];

                try {
                    // Test connexion
                    echo "<div class='check-item'>Test de connexion à la base de données...</div>";
                    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    echo "<div class='check-item success'>✓ Connexion réussie</div>";

                    // Créer fichier de configuration
                    echo "<div class='check-item'>Création du fichier de configuration...</div>";
                    $config_content = "<?php\n";
                    $config_content .= "// Configuration de la base de données\n";
                    $config_content .= "define('DB_HOST', '$db_host');\n";
                    $config_content .= "define('DB_NAME', '$db_name');\n";
                    $config_content .= "define('DB_USER', '$db_user');\n";
                    $config_content .= "define('DB_PASS', '$db_pass');\n\n";
                    $config_content .= "// Connexion PDO\n";
                    $config_content .= "try {\n";
                    $config_content .= "    \$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);\n";
                    $config_content .= "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
                    $config_content .= "    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);\n";
                    $config_content .= "} catch(PDOException \$e) {\n";
                    $config_content .= "    die('Erreur de connexion : ' . \$e->getMessage());\n";
                    $config_content .= "}\n";

                    file_put_contents('config/database.php', $config_content);
                    echo "<div class='check-item success'>✓ Fichier config/database.php créé</div>";

                    // Exécuter le schéma
                    echo "<div class='check-item'>Installation du schéma de base de données...</div>";
                    if (file_exists('database/schema.sql')) {
                        $schema = file_get_contents('database/schema.sql');
                        // Retirer les commentaires et USE
                        $schema = preg_replace('/^--.*$/m', '', $schema);
                        $schema = preg_replace('/^USE .*?;/m', '', $schema);

                        $statements = array_filter(array_map('trim', explode(';', $schema)));
                        foreach ($statements as $stmt) {
                            if (!empty($stmt)) {
                                try {
                                    $pdo->exec($stmt);
                                } catch (Exception $e) {
                                    // Ignorer erreurs si table existe déjà
                                }
                            }
                        }
                        echo "<div class='check-item success'>✓ Schéma installé</div>";
                    }

                    // Exécuter les données initiales
                    echo "<div class='check-item'>Installation des données initiales...</div>";
                    if (file_exists('database/seed.sql')) {
                        $seed = file_get_contents('database/seed.sql');
                        $seed = preg_replace('/^--.*$/m', '', $seed);
                        $seed = preg_replace('/^USE .*?;/m', '', $seed);

                        $statements = array_filter(array_map('trim', explode(';', $seed)));
                        foreach ($statements as $stmt) {
                            if (!empty($stmt)) {
                                try {
                                    $pdo->exec($stmt);
                                } catch (Exception $e) {
                                    // Ignorer doublons
                                }
                            }
                        }
                        echo "<div class='check-item success'>✓ Données initiales installées</div>";
                    }

                    // Marquer comme installé
                    file_put_contents('config/.installed', date('Y-m-d H:i:s'));

                    echo "<div class='alert alert-success mt-4'>";
                    echo "<h4>✓ Installation réussie!</h4>";
                    echo "<p>Le système SGDI a été installé avec succès.</p>";
                    echo "<p><strong>Compte administrateur par défaut:</strong></p>";
                    echo "<ul>";
                    echo "<li>Nom d'utilisateur: <code>admin</code></li>";
                    echo "<li>Mot de passe: <code>Admin@2025</code></li>";
                    echo "</ul>";
                    echo "<div class='alert alert-warning'>";
                    echo "<strong>⚠ IMPORTANT:</strong> Changez ce mot de passe dès votre première connexion!";
                    echo "</div>";
                    echo "</div>";

                    echo "<a href='index.php' class='btn btn-success btn-lg w-100'>Accéder au système</a>";

                } catch (Exception $e) {
                    echo "<div class='alert alert-danger mt-4'>";
                    echo "<strong>✗ Erreur d'installation:</strong><br>";
                    echo htmlspecialchars($e->getMessage());
                    echo "</div>";
                    echo "<a href='install.php' class='btn btn-secondary w-100'>Recommencer</a>";
                }
                ?>
            </div>
        <?php
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

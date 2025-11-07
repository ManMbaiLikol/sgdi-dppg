<?php
/**
 * Script de Correction Railway - Ajout du rôle 'ministre'
 *
 * Ce script :
 * 1. Ajoute le rôle 'ministre' à l'ENUM de la colonne users.role
 * 2. Crée le compte ministre
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

$results = [];
$errors = [];

// ============================================================
// CONNEXION À LA BASE DE DONNÉES
// ============================================================

try {
    $host = $_ENV['MYSQL_HOST'] ?? $_SERVER['MYSQL_HOST'] ?? getenv('MYSQL_HOST') ?: 'localhost';
    $port = $_ENV['MYSQL_PORT'] ?? $_SERVER['MYSQL_PORT'] ?? getenv('MYSQL_PORT') ?: '3306';
    $dbname = $_ENV['MYSQL_DATABASE'] ?? $_SERVER['MYSQL_DATABASE'] ?? getenv('MYSQL_DATABASE') ?: 'sgdi_mvp';
    $user = $_ENV['MYSQL_USER'] ?? $_SERVER['MYSQL_USER'] ?? getenv('MYSQL_USER') ?: 'root';
    $password = $_ENV['MYSQL_PASSWORD'] ?? $_SERVER['MYSQL_PASSWORD'] ?? getenv('MYSQL_PASSWORD') ?: '';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    $results[] = "✅ Connexion à la base de données réussie";

} catch (PDOException $e) {
    $errors[] = "❌ Erreur de connexion: " . $e->getMessage();
    $db = null;
}

if ($db) {
    // ============================================================
    // ÉTAPE 1 : VÉRIFIER LA STRUCTURE ACTUELLE
    // ============================================================

    try {
        $check_sql = "SHOW COLUMNS FROM users WHERE Field = 'role'";
        $check_stmt = $db->query($check_sql);
        $column_info = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($column_info) {
            $results[] = "📋 Structure actuelle de la colonne 'role' :";
            $results[] = "   Type: " . $column_info['Type'];

            // Vérifier si 'ministre' est déjà présent
            $has_ministre = strpos($column_info['Type'], "'ministre'") !== false;

            if ($has_ministre) {
                $results[] = "   ✅ Le rôle 'ministre' existe déjà dans l'ENUM";
            } else {
                $results[] = "   ⚠️ Le rôle 'ministre' n'existe PAS dans l'ENUM";
            }
        }
    } catch (PDOException $e) {
        $errors[] = "❌ Erreur lors de la vérification: " . $e->getMessage();
    }

    // ============================================================
    // ÉTAPE 2 : AJOUTER LE RÔLE 'ministre' SI NÉCESSAIRE
    // ============================================================

    if (!$has_ministre) {
        try {
            $results[] = "";
            $results[] = "🔧 Ajout du rôle 'ministre' à l'ENUM...";

            $alter_sql = "ALTER TABLE users
                          MODIFY COLUMN role ENUM(
                              'admin',
                              'chef_service',
                              'sous_directeur',
                              'directeur',
                              'ministre',
                              'cabinet',
                              'cadre_dppg',
                              'cadre_daj',
                              'chef_commission',
                              'billeteur',
                              'lecteur_public'
                          ) NOT NULL";

            $db->exec($alter_sql);

            $results[] = "✅ Rôle 'ministre' ajouté avec succès à l'ENUM !";

        } catch (PDOException $e) {
            $errors[] = "❌ Erreur lors de l'ajout du rôle: " . $e->getMessage();
        }
    }

    // ============================================================
    // ÉTAPE 3 : CRÉER LE COMPTE MINISTRE
    // ============================================================

    try {
        $results[] = "";
        $results[] = "👤 Création du compte Ministre...";

        // Vérifier si le compte existe déjà
        $check_ministre_sql = "SELECT id, username, email, role FROM users WHERE username = :username";
        $check_ministre_stmt = $db->prepare($check_ministre_sql);
        $check_ministre_stmt->execute(['username' => 'ministre']);

        if ($check_ministre_stmt->rowCount() > 0) {
            $existing = $check_ministre_stmt->fetch(PDO::FETCH_ASSOC);
            $results[] = "ℹ️ Le compte existe déjà";
            $results[] = "   Username: " . htmlspecialchars($existing['username']);
            $results[] = "   Email: " . htmlspecialchars($existing['email']);
            $results[] = "   Rôle: " . htmlspecialchars($existing['role']);

            // Vérifier si le rôle est correct
            if ($existing['role'] !== 'ministre') {
                $results[] = "   ⚠️ Le rôle n'est pas 'ministre', mise à jour...";

                $update_sql = "UPDATE users SET role = :role WHERE username = :username";
                $update_stmt = $db->prepare($update_sql);
                $update_stmt->execute([
                    'role' => 'ministre',
                    'username' => 'ministre'
                ]);

                $results[] = "   ✅ Rôle mis à jour vers 'ministre'";
            }

        } else {
            // Créer le compte
            $insert_sql = "INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif, date_creation)
                           VALUES (:username, :email, :password, :role, :nom, :prenom, :telephone, :actif, NOW())";

            $insert_stmt = $db->prepare($insert_sql);
            $insert_stmt->execute([
                'username' => 'ministre',
                'email' => 'ministre@minee.cm',
                'password' => '$2y$10$mTQL2.kuw0g4eBPojVmMOehRxiD8t6OBBsX08XiU7H1NjHLR.yayW', // Ministre@2025
                'role' => 'ministre',
                'nom' => 'CABINET',
                'prenom' => 'Ministre',
                'telephone' => '+237690000009',
                'actif' => 1
            ]);

            $results[] = "✅ Compte Ministre créé avec succès !";
        }

    } catch (PDOException $e) {
        $errors[] = "❌ Erreur création compte Ministre: " . $e->getMessage();
    }

    // ============================================================
    // ÉTAPE 4 : VÉRIFICATION FINALE
    // ============================================================

    try {
        $results[] = "";
        $results[] = "🔍 Vérification finale...";

        $verify_sql = "SELECT username, email, role, nom, prenom, actif
                       FROM users
                       WHERE username = 'ministre'";
        $verify_stmt = $db->prepare($verify_sql);
        $verify_stmt->execute();

        if ($verify_stmt->rowCount() > 0) {
            $account = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            $results[] = "✅ Compte Ministre vérifié :";
            $results[] = "   Username: " . htmlspecialchars($account['username']);
            $results[] = "   Email: " . htmlspecialchars($account['email']);
            $results[] = "   Rôle: " . htmlspecialchars($account['role']);
            $results[] = "   Nom: " . htmlspecialchars($account['nom'] . " " . $account['prenom']);
            $results[] = "   Actif: " . ($account['actif'] ? 'Oui' : 'Non');
        } else {
            $errors[] = "❌ Le compte Ministre n'a pas été trouvé après création !";
        }

    } catch (PDOException $e) {
        $errors[] = "❌ Erreur vérification: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correction Railway - Rôle Ministre</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        h1 {
            color: #1a202c;
            margin-bottom: 8px;
            font-size: 32px;
            font-weight: 700;
        }
        .subtitle {
            color: #718096;
            margin-bottom: 32px;
            font-size: 16px;
        }
        .box {
            background: #f7fafc;
            border-left: 4px solid #48bb78;
            padding: 24px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .box.error {
            border-left-color: #f56565;
            background: #fff5f5;
        }
        .box h3 {
            font-size: 18px;
            margin-bottom: 16px;
            color: #2d3748;
        }
        .box p {
            margin: 6px 0;
            color: #4a5568;
            line-height: 1.8;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        .success-box {
            background: #f0fff4;
            border-left: 4px solid #48bb78;
            padding: 24px;
            margin: 24px 0;
            border-radius: 8px;
        }
        .success-box h3 {
            color: #22543d;
            margin-bottom: 16px;
        }
        .credentials {
            background: white;
            border: 2px solid #e2e8f0;
            padding: 20px;
            margin: 16px 0;
            border-radius: 8px;
        }
        .credentials strong {
            color: #5a67d8;
            font-size: 16px;
        }
        .credentials code {
            background: #edf2f7;
            padding: 2px 6px;
            border-radius: 4px;
            color: #2d3748;
        }
        .next-steps {
            background: #e6fffa;
            border-left: 4px solid #319795;
            padding: 24px;
            margin: 24px 0;
            border-radius: 8px;
        }
        .next-steps h3 {
            color: #234e52;
            margin-bottom: 16px;
        }
        .next-steps ol {
            margin-left: 24px;
            color: #234e52;
        }
        .next-steps li {
            margin: 12px 0;
            line-height: 1.6;
        }
        .next-steps a {
            color: #3182ce;
            text-decoration: none;
            font-weight: 600;
        }
        .next-steps a:hover {
            text-decoration: underline;
        }
        .warning {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Correction Railway</h1>
        <p class="subtitle">Ajout du rôle 'ministre' et création du compte</p>

        <?php if (!empty($errors)): ?>
        <div class="box error">
            <h3>❌ Erreurs</h3>
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($results)): ?>
        <div class="box">
            <h3>📋 Rapport d'Exécution</h3>
            <?php foreach ($results as $result): ?>
                <p><?php echo htmlspecialchars($result); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($errors) && !empty($results)): ?>
        <div class="success-box">
            <h3>🎉 Succès !</h3>

            <div class="credentials">
                <strong>COMPTE MINISTRE</strong><br><br>
                URL de connexion: <a href="/">https://sgdi-dppg-production.up.railway.app/</a><br><br>
                Username: <code>ministre</code><br>
                Mot de passe: <code>Ministre@2025</code><br>
                Rôle: <code>ministre</code>
            </div>
        </div>

        <div class="next-steps">
            <h3>📋 Prochaines Étapes</h3>
            <ol>
                <li>
                    <strong>Tester la connexion :</strong><br>
                    <a href="/" target="_blank">Se connecter avec ministre / Ministre@2025</a>
                </li>
                <li>
                    <strong>Accéder au Dashboard Ministre :</strong><br>
                    Vous devriez voir l'espace "Cabinet du Ministre"
                </li>
                <li>
                    <strong>Vérifier les dossiers :</strong><br>
                    Les dossiers avec statut 'visa_directeur' apparaîtront automatiquement
                </li>
                <li>
                    <strong>🔒 Supprimer ce fichier :</strong><br>
                    Supprimez <code>fix_railway.php</code>, <code>init_railway.php</code> et <code>setup_railway_accounts.php</code> après utilisation
                </li>
            </ol>
        </div>
        <?php endif; ?>

        <div class="warning">
            <strong>⚠️ Important - Sécurité</strong><br>
            Ce script modifie la structure de la base de données. Il doit être supprimé immédiatement après utilisation.
        </div>
    </div>
</body>
</html>

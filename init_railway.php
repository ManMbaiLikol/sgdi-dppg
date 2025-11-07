<?php
/**
 * Initialisation des Comptes Railway
 * Version simplifiée sans dépendances
 */

// Activer l'affichage des erreurs pour le diagnostic
ini_set('display_errors', 1);
error_reporting(E_ALL);

$results = [];
$errors = [];

// ============================================================
// CONNEXION DIRECTE À LA BASE DE DONNÉES
// ============================================================

try {
    // Récupération des variables d'environnement Railway
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
    $results[] = "   - Host: {$host}:{$port}";
    $results[] = "   - Database: {$dbname}";

} catch (PDOException $e) {
    $errors[] = "❌ Erreur de connexion: " . $e->getMessage();
    $db = null;
}

// ============================================================
// CRÉATION DES COMPTES
// ============================================================

if ($db) {
    // 1. CRÉER LE COMPTE MINISTRE
    try {
        $check_sql = "SELECT id, username, email, role FROM users WHERE username = :username";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->execute(['username' => 'ministre']);

        if ($check_stmt->rowCount() > 0) {
            $results[] = "✅ Le compte Ministre existe déjà";
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
            $results[] = "   - Username: " . htmlspecialchars($existing['username']);
            $results[] = "   - Email: " . htmlspecialchars($existing['email']);
            $results[] = "   - Rôle: " . htmlspecialchars($existing['role']);
        } else {
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
            $results[] = "   - Username: ministre";
            $results[] = "   - Email: ministre@minee.cm";
            $results[] = "   - Mot de passe: Ministre@2025";
            $results[] = "   - Rôle: ministre";
        }
    } catch (PDOException $e) {
        $errors[] = "❌ Erreur création compte Ministre: " . $e->getMessage();
    }

    // 2. CRÉER/VÉRIFIER LE COMPTE SOUS-DIRECTEUR
    try {
        $check_sd_sql = "SELECT id, username, email, role FROM users WHERE role = :role";
        $check_sd_stmt = $db->prepare($check_sd_sql);
        $check_sd_stmt->execute(['role' => 'sous_directeur']);

        if ($check_sd_stmt->rowCount() > 0) {
            $results[] = "✅ Un compte Sous-Directeur existe déjà";
            $existing_sd = $check_sd_stmt->fetch(PDO::FETCH_ASSOC);
            $results[] = "   - Username: " . htmlspecialchars($existing_sd['username']);
            $results[] = "   - Email: " . htmlspecialchars($existing_sd['email']);
            $results[] = "   - Rôle: " . htmlspecialchars($existing_sd['role']);
        } else {
            $insert_sd_sql = "INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif, date_creation)
                              VALUES (:username, :email, :password, :role, :nom, :prenom, :telephone, :actif, NOW())";

            $insert_sd_stmt = $db->prepare($insert_sd_sql);
            $insert_sd_stmt->execute([
                'username' => 'sousdirecteur',
                'email' => 'sousdirecteur@dppg.cm',
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // admin123
                'role' => 'sous_directeur',
                'nom' => 'SOUS-DIRECTEUR',
                'prenom' => 'SDTD',
                'telephone' => '+237690000007',
                'actif' => 1
            ]);

            $results[] = "✅ Compte Sous-Directeur créé avec succès !";
            $results[] = "   - Username: sousdirecteur";
            $results[] = "   - Email: sousdirecteur@dppg.cm";
            $results[] = "   - Mot de passe: admin123";
            $results[] = "   - Rôle: sous_directeur";
        }
    } catch (PDOException $e) {
        $errors[] = "❌ Erreur création compte Sous-Directeur: " . $e->getMessage();
    }

    // 3. VÉRIFICATION FINALE
    try {
        $verify_sql = "SELECT username, email, role, nom, prenom, actif
                       FROM users
                       WHERE role IN ('ministre', 'sous_directeur')
                       ORDER BY role";
        $verify_stmt = $db->prepare($verify_sql);
        $verify_stmt->execute();

        $accounts = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($accounts) > 0) {
            $results[] = "";
            $results[] = "📋 RÉCAPITULATIF DES COMPTES";
            foreach ($accounts as $account) {
                $results[] = "---";
                $results[] = "Username: " . htmlspecialchars($account['username']);
                $results[] = "Email: " . htmlspecialchars($account['email']);
                $results[] = "Rôle: " . htmlspecialchars($account['role']);
                $results[] = "Nom: " . htmlspecialchars($account['nom'] . " " . $account['prenom']);
                $results[] = "Actif: " . ($account['actif'] ? 'Oui' : 'Non');
            }
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
    <title>Initialisation Railway</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
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
            line-height: 1.6;
        }
        .credentials {
            background: white;
            border: 2px solid #e2e8f0;
            padding: 20px;
            margin: 16px 0;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
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
            background: #f0fff4;
            border-left: 4px solid #48bb78;
            padding: 24px;
            margin: 24px 0;
            border-radius: 8px;
        }
        .next-steps h3 {
            color: #22543d;
            margin-bottom: 16px;
        }
        .next-steps ol {
            margin-left: 24px;
            color: #22543d;
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
        .success { color: #48bb78; font-weight: 600; }
        .error { color: #f56565; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Initialisation Railway</h1>
        <p class="subtitle">Configuration des comptes de production</p>

        <?php if (!empty($errors)): ?>
        <div class="box error">
            <h3 class="error">❌ Erreurs</h3>
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($results)): ?>
        <div class="box">
            <h3 class="success">✅ Résultats</h3>
            <?php foreach ($results as $result): ?>
                <p><?php echo htmlspecialchars($result); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="box">
            <h3>🔑 Identifiants de Connexion</h3>

            <div class="credentials">
                <strong>MINISTRE</strong><br><br>
                URL: <a href="/">https://sgdi-dppg-production.up.railway.app/</a><br>
                Username: <code>ministre</code><br>
                Mot de passe: <code>Ministre@2025</code><br>
                Rôle: Cabinet/Secrétariat Ministre
            </div>

            <div class="credentials">
                <strong>SOUS-DIRECTEUR</strong><br><br>
                URL: <a href="/">https://sgdi-dppg-production.up.railway.app/</a><br>
                Username: <code>sousdirecteur</code><br>
                Mot de passe: <code>admin123</code><br>
                Rôle: Sous-Directeur SDTD
            </div>
        </div>

        <div class="next-steps">
            <h3>📋 Prochaines Étapes</h3>
            <ol>
                <li>
                    <strong>Tester la connexion Ministre :</strong><br>
                    <a href="/">Aller à la page de connexion</a>
                </li>
                <li>
                    <strong>Faire progresser des dossiers :</strong><br>
                    Consultez <code>GUIDE_CIRCUIT_VISAS.md</code> pour le workflow complet
                </li>
                <li>
                    <strong>🔒 Supprimer ce fichier :</strong><br>
                    Supprimez <code>init_railway.php</code> après utilisation (sécurité)
                </li>
            </ol>
        </div>

        <div class="warning">
            <strong>⚠️ Important - Sécurité</strong><br>
            Ce script permet de créer des comptes administrateurs. Il doit être supprimé immédiatement après utilisation.
        </div>
    </div>
</body>
</html>

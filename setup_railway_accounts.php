<?php
/**
 * Script de Configuration des Comptes Railway
 *
 * Ce script crée les comptes Ministre et Sous-Directeur sur Railway.
 * À SUPPRIMER après utilisation pour des raisons de sécurité.
 *
 * URL: https://sgdi-dppg-production.up.railway.app/setup_railway_accounts.php
 */

require_once __DIR__ . '/config/database.php';

// Désactiver l'affichage des erreurs en production
ini_set('display_errors', 1);
error_reporting(E_ALL);

$database = new Database();
$db = $database->getConnection();

$results = [];
$errors = [];

// ============================================================
// 1. CRÉER LE COMPTE MINISTRE
// ============================================================

try {
    // Vérifier si le compte existe déjà
    $check_sql = "SELECT id, username, email, role FROM users WHERE username = :username";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bindParam(':username', $ministre_username = 'ministre');
    $check_stmt->execute();

    if ($check_stmt->rowCount() > 0) {
        $results[] = "✅ Le compte Ministre existe déjà";
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
        $results[] = "   - Username: " . $existing['username'];
        $results[] = "   - Email: " . $existing['email'];
        $results[] = "   - Rôle: " . $existing['role'];
    } else {
        // Créer le compte Ministre
        $insert_sql = "INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif, date_creation)
                       VALUES (:username, :email, :password, :role, :nom, :prenom, :telephone, :actif, NOW())";

        $insert_stmt = $db->prepare($insert_sql);

        $ministre_data = [
            'username' => 'ministre',
            'email' => 'ministre@minee.cm',
            'password' => '$2y$10$mTQL2.kuw0g4eBPojVmMOehRxiD8t6OBBsX08XiU7H1NjHLR.yayW', // Ministre@2025
            'role' => 'ministre',
            'nom' => 'CABINET',
            'prenom' => 'Ministre',
            'telephone' => '+237690000009',
            'actif' => 1
        ];

        $insert_stmt->execute($ministre_data);

        $results[] = "✅ Compte Ministre créé avec succès !";
        $results[] = "   - Username: ministre";
        $results[] = "   - Email: ministre@minee.cm";
        $results[] = "   - Mot de passe: Ministre@2025";
        $results[] = "   - Rôle: ministre";
    }
} catch (PDOException $e) {
    $errors[] = "❌ Erreur création compte Ministre: " . $e->getMessage();
}

// ============================================================
// 2. CRÉER/VÉRIFIER LE COMPTE SOUS-DIRECTEUR
// ============================================================

try {
    // Vérifier si un compte sous-directeur existe déjà
    $check_sd_sql = "SELECT id, username, email, role FROM users WHERE role = :role";
    $check_sd_stmt = $db->prepare($check_sd_sql);
    $check_sd_stmt->bindParam(':role', $sd_role = 'sous_directeur');
    $check_sd_stmt->execute();

    if ($check_sd_stmt->rowCount() > 0) {
        $results[] = "✅ Un compte Sous-Directeur existe déjà";
        $existing_sd = $check_sd_stmt->fetch(PDO::FETCH_ASSOC);
        $results[] = "   - Username: " . $existing_sd['username'];
        $results[] = "   - Email: " . $existing_sd['email'];
        $results[] = "   - Rôle: " . $existing_sd['role'];
    } else {
        // Créer le compte Sous-Directeur
        $insert_sd_sql = "INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif, date_creation)
                          VALUES (:username, :email, :password, :role, :nom, :prenom, :telephone, :actif, NOW())";

        $insert_sd_stmt = $db->prepare($insert_sd_sql);

        $sd_data = [
            'username' => 'sousdirecteur',
            'email' => 'sousdirecteur@dppg.cm',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // admin123
            'role' => 'sous_directeur',
            'nom' => 'SOUS-DIRECTEUR',
            'prenom' => 'SDTD',
            'telephone' => '+237690000007',
            'actif' => 1
        ];

        $insert_sd_stmt->execute($sd_data);

        $results[] = "✅ Compte Sous-Directeur créé avec succès !";
        $results[] = "   - Username: sousdirecteur";
        $results[] = "   - Email: sousdirecteur@dppg.cm";
        $results[] = "   - Mot de passe: admin123";
        $results[] = "   - Rôle: sous_directeur";
    }
} catch (PDOException $e) {
    $errors[] = "❌ Erreur création compte Sous-Directeur: " . $e->getMessage();
}

// ============================================================
// 3. VÉRIFICATION FINALE
// ============================================================

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
            $results[] = "Username: " . $account['username'];
            $results[] = "Email: " . $account['email'];
            $results[] = "Rôle: " . $account['role'];
            $results[] = "Nom: " . $account['nom'] . " " . $account['prenom'];
            $results[] = "Actif: " . ($account['actif'] ? 'Oui' : 'Non');
        }
    }
} catch (PDOException $e) {
    $errors[] = "❌ Erreur vérification: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration des Comptes Railway</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .results, .errors {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .errors {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .results p, .errors p {
            margin: 8px 0;
            color: #333;
            line-height: 1.6;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box h3 {
            color: #2196F3;
            margin-bottom: 10px;
        }
        .credentials {
            background: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        .credentials strong {
            color: #667eea;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #856404;
        }
        .next-steps {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .next-steps h3 {
            color: #155724;
            margin-bottom: 10px;
        }
        .next-steps ol {
            margin-left: 20px;
            color: #155724;
        }
        .next-steps li {
            margin: 8px 0;
        }
        .next-steps a {
            color: #0056b3;
            text-decoration: none;
            font-weight: bold;
        }
        .next-steps a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Configuration des Comptes Railway</h1>
        <p class="subtitle">Script d'initialisation pour la base de données de production</p>

        <?php if (!empty($errors)): ?>
        <div class="errors">
            <h3 class="error">❌ Erreurs Rencontrées</h3>
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($results)): ?>
        <div class="results">
            <h3 class="success">✅ Résultats</h3>
            <?php foreach ($results as $result): ?>
                <p><?php echo htmlspecialchars($result); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="info-box">
            <h3>🔑 Identifiants de Connexion</h3>

            <div class="credentials">
                <strong>MINISTRE</strong><br>
                URL: <a href="/" target="_blank">https://sgdi-dppg-production.up.railway.app/</a><br>
                Username: <code>ministre</code><br>
                Mot de passe: <code>Ministre@2025</code><br>
                Rôle: Cabinet/Secrétariat Ministre
            </div>

            <div class="credentials">
                <strong>SOUS-DIRECTEUR</strong><br>
                URL: <a href="/" target="_blank">https://sgdi-dppg-production.up.railway.app/</a><br>
                Username: <code>sousdirecteur</code> (ou <code>SDTD_Abena</code> si existant)<br>
                Mot de passe: <code>admin123</code><br>
                Rôle: Sous-Directeur SDTD
            </div>
        </div>

        <div class="next-steps">
            <h3>📋 Prochaines Étapes</h3>
            <ol>
                <li>
                    <strong>Tester la connexion Ministre :</strong><br>
                    <a href="/" target="_blank">Aller à la page de connexion</a>
                </li>
                <li>
                    <strong>Vérifier le workflow :</strong><br>
                    <a href="/utilities/check_workflow_ministre.php" target="_blank">Voir le diagnostic</a><br>
                    <small>(Note: Cette page peut être bloquée par .htaccess)</small>
                </li>
                <li>
                    <strong>Faire progresser des dossiers :</strong><br>
                    Consultez le fichier <code>GUIDE_CIRCUIT_VISAS.md</code> pour savoir comment faire progresser des dossiers à travers le circuit des 3 visas jusqu'au Ministre.
                </li>
                <li>
                    <strong>🔒 IMPORTANT - Supprimer ce script :</strong><br>
                    Après avoir vérifié que tout fonctionne, <strong>supprimez ce fichier</strong> (<code>setup_railway_accounts.php</code>) pour des raisons de sécurité.
                </li>
            </ol>
        </div>

        <div class="warning">
            <strong>⚠️ Sécurité</strong><br>
            Ce script doit être supprimé après utilisation. Il permet de créer des comptes administrateurs et ne doit pas rester accessible en production.
        </div>
    </div>
</body>
</html>

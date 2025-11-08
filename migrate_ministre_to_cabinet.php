<?php
/**
 * Migration: Compte Ministre → Cabinet
 *
 * Ce script :
 * 1. Vérifie si un compte avec username 'ministre' existe
 * 2. Met à jour son rôle de 'ministre' vers 'cabinet'
 * 3. OU crée le compte 'ministre' avec le rôle 'cabinet'
 *
 * Note: Le rôle 'cabinet' fait référence au Cabinet du Ministre
 */

require_once __DIR__ . '/config/database.php';

$results = [];
$errors = [];

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Migration Ministre → Cabinet</title>
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
    <div class='container'>
        <h1>🔄 Migration Ministre → Cabinet</h1>
        <p class='subtitle'>Mise à jour du compte pour utiliser le rôle 'cabinet'</p>
";

try {
    // ============================================================
    // ÉTAPE 1 : VÉRIFIER SI LE COMPTE MINISTRE EXISTE
    // ============================================================

    $check_sql = "SELECT id, username, email, role, nom, prenom, actif
                  FROM users
                  WHERE username = :username";

    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute(['username' => 'ministre']);

    if ($check_stmt->rowCount() > 0) {
        // Le compte existe
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

        $results[] = "✅ Compte trouvé";
        $results[] = "   Username: " . htmlspecialchars($existing['username']);
        $results[] = "   Email: " . htmlspecialchars($existing['email']);
        $results[] = "   Rôle actuel: " . htmlspecialchars($existing['role']);

        // Vérifier si le rôle est déjà 'cabinet'
        if ($existing['role'] === 'cabinet') {
            $results[] = "";
            $results[] = "ℹ️ Le compte utilise déjà le rôle 'cabinet'";
            $results[] = "   Aucune modification nécessaire";

        } else {
            // Mettre à jour le rôle vers 'cabinet'
            $results[] = "";
            $results[] = "🔧 Mise à jour du rôle 'ministre' → 'cabinet'...";

            $update_sql = "UPDATE users SET role = :new_role WHERE id = :id";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([
                'new_role' => 'cabinet',
                'id' => $existing['id']
            ]);

            $results[] = "✅ Rôle mis à jour avec succès !";
            $results[] = "   Ancien rôle: " . htmlspecialchars($existing['role']);
            $results[] = "   Nouveau rôle: cabinet";
        }

    } else {
        // Le compte n'existe pas, le créer avec le rôle 'cabinet'
        $results[] = "⚠️ Le compte 'ministre' n'existe pas";
        $results[] = "";
        $results[] = "🔧 Création du compte avec le rôle 'cabinet'...";

        $insert_sql = "INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif, date_creation)
                       VALUES (:username, :email, :password, :role, :nom, :prenom, :telephone, :actif, NOW())";

        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([
            'username' => 'ministre',
            'email' => 'ministre@minee.cm',
            'password' => '$2y$10$mTQL2.kuw0g4eBPojVmMOehRxiD8t6OBBsX08XiU7H1NjHLR.yayW', // Ministre@2025
            'role' => 'cabinet',
            'nom' => 'CABINET',
            'prenom' => 'Ministre',
            'telephone' => '+237690000009',
            'actif' => 1
        ]);

        $results[] = "✅ Compte créé avec succès !";
        $results[] = "   Username: ministre";
        $results[] = "   Email: ministre@minee.cm";
        $results[] = "   Mot de passe: Ministre@2025";
        $results[] = "   Rôle: cabinet";
    }

    // ============================================================
    // ÉTAPE 2 : VÉRIFICATION FINALE
    // ============================================================

    $results[] = "";
    $results[] = "🔍 Vérification finale...";

    $verify_sql = "SELECT username, email, role, nom, prenom, actif
                   FROM users
                   WHERE username = 'ministre'";
    $verify_stmt = $pdo->prepare($verify_sql);
    $verify_stmt->execute();

    if ($verify_stmt->rowCount() > 0) {
        $account = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        $results[] = "✅ Compte vérifié :";
        $results[] = "   Username: " . htmlspecialchars($account['username']);
        $results[] = "   Email: " . htmlspecialchars($account['email']);
        $results[] = "   Rôle: " . htmlspecialchars($account['role']);
        $results[] = "   Nom: " . htmlspecialchars($account['nom'] . " " . $account['prenom']);
        $results[] = "   Actif: " . ($account['actif'] ? 'Oui' : 'Non');
    } else {
        $errors[] = "❌ Erreur: Le compte n'a pas été trouvé après création/mise à jour !";
    }

} catch (PDOException $e) {
    $errors[] = "❌ Erreur de base de données: " . $e->getMessage();
}

// Affichage des résultats
if (!empty($errors)) {
    echo "<div class='box error'>";
    echo "<h3>❌ Erreurs</h3>";
    foreach ($errors as $error) {
        echo "<p>" . htmlspecialchars($error) . "</p>";
    }
    echo "</div>";
}

if (!empty($results)) {
    echo "<div class='box'>";
    echo "<h3>📋 Rapport d'Exécution</h3>";
    foreach ($results as $result) {
        echo "<p>" . htmlspecialchars($result) . "</p>";
    }
    echo "</div>";
}

if (empty($errors) && !empty($results)) {
    echo "<div class='success-box'>";
    echo "<h3>🎉 Succès !</h3>";
    echo "<div class='credentials'>";
    echo "<strong>COMPTE MINISTRE (LOCAL)</strong><br><br>";
    echo "URL de connexion: <a href='http://localhost/dppg-implantation/'>http://localhost/dppg-implantation/</a><br><br>";
    echo "Username: <code>ministre</code><br>";
    echo "Mot de passe: <code>Ministre@2025</code><br>";
    echo "Rôle: <code>cabinet</code> (Cabinet du Ministre)";
    echo "</div>";
    echo "</div>";

    echo "<div class='warning'>";
    echo "<strong>📋 Prochaines Étapes</strong><br><br>";
    echo "1. <strong>Tester la connexion</strong> : <a href='http://localhost/dppg-implantation/'>Se connecter</a><br>";
    echo "2. <strong>Accéder au dashboard</strong> : Vous devriez être redirigé vers le dashboard du Cabinet du Ministre<br>";
    echo "3. <strong>Supprimer ce fichier</strong> : Supprimez <code>migrate_ministre_to_cabinet.php</code> après utilisation";
    echo "</div>";
}

echo "    </div>
</body>
</html>";
?>

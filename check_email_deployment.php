<?php
// Vérification rapide du déploiement des fonctionnalités email
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Vérification déploiement email - SGDI</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .check {
            background: white;
            padding: 20px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #ccc;
        }
        .check.success {
            border-left-color: #4CAF50;
        }
        .check.error {
            border-left-color: #f44336;
        }
        .check h3 {
            margin-top: 0;
        }
        .status {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
        }
        .status.ok {
            background: #4CAF50;
            color: white;
        }
        .status.ko {
            background: #f44336;
            color: white;
        }
        .config {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        code {
            background: #263238;
            color: #aed581;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <h1>🔍 Vérification du déploiement email</h1>
    <p>Cette page vérifie que les fonctionnalités email sont bien déployées sur Railway.</p>

    <?php
    // Vérifier les fichiers
    $files = [
        'config/email.php' => 'Configuration email',
        'includes/email.php' => 'Fonctions d\'envoi email',
        'includes/email_functions.php' => 'Fonctions avancées email',
        'modules/admin/test_email.php' => 'Page de test email',
        'modules/admin/email_logs.php' => 'Page des logs',
        'modules/admin/ajax/get_email_body.php' => 'API AJAX logs',
        'docs/CONFIGURATION_EMAIL.md' => 'Documentation'
    ];

    foreach ($files as $file => $description) {
        $exists = file_exists(__DIR__ . '/' . $file);
        echo '<div class="check ' . ($exists ? 'success' : 'error') . '">';
        echo '<h3>' . htmlspecialchars($description) . '</h3>';
        echo '<span class="status ' . ($exists ? 'ok' : 'ko') . '">';
        echo $exists ? '✓ PRÉSENT' : '✗ MANQUANT';
        echo '</span> <code>' . htmlspecialchars($file) . '</code>';
        echo '</div>';
    }
    ?>

    <div class="check success">
        <h3>📋 Configuration actuelle</h3>
        <div class="config">
            <?php
            require_once __DIR__ . '/config/email.php';
            echo '<p><strong>EMAIL_ENABLED:</strong> ' . (EMAIL_ENABLED ? '<span class="status ok">✓ ACTIVÉ</span>' : '<span class="status ko">✗ DÉSACTIVÉ</span>') . '</p>';
            echo '<p><strong>SMTP_HOST:</strong> <code>' . htmlspecialchars(SMTP_HOST) . '</code></p>';
            echo '<p><strong>SMTP_PORT:</strong> <code>' . htmlspecialchars(SMTP_PORT) . '</code></p>';
            echo '<p><strong>SMTP_USERNAME:</strong> <code>' . htmlspecialchars(SMTP_USERNAME) . '</code></p>';
            echo '<p><strong>EMAIL_FROM:</strong> <code>' . htmlspecialchars(EMAIL_FROM) . '</code></p>';
            echo '<p><strong>EMAIL_DEBUG:</strong> ' . (EMAIL_DEBUG ? 'Activé' : 'Désactivé') . '</p>';
            ?>
        </div>
    </div>

    <?php
    // Vérifier la connexion DB et la table email_logs
    try {
        require_once __DIR__ . '/config/database.php';
        $stmt = $pdo->query("SHOW TABLES LIKE 'email_logs'");
        $table_exists = $stmt->rowCount() > 0;

        echo '<div class="check ' . ($table_exists ? 'success' : 'error') . '">';
        echo '<h3>🗄️ Table email_logs</h3>';
        echo '<span class="status ' . ($table_exists ? 'ok' : 'ko') . '">';
        echo $table_exists ? '✓ EXISTE' : '✗ MANQUANTE';
        echo '</span>';

        if ($table_exists) {
            $count = $pdo->query("SELECT COUNT(*) FROM email_logs")->fetchColumn();
            echo '<p>' . $count . ' email(s) loggé(s)</p>';
        } else {
            echo '<p>La table sera créée automatiquement au premier envoi d\'email</p>';
        }
        echo '</div>';
    } catch (Exception $e) {
        echo '<div class="check error">';
        echo '<h3>❌ Erreur de connexion DB</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
    }
    ?>

    <div class="check success">
        <h3>🔗 Liens rapides</h3>
        <p>
            <a href="modules/admin/test_email.php" style="display: inline-block; margin: 5px; padding: 10px 20px; background: #1e3a8a; color: white; text-decoration: none; border-radius: 5px;">
                📧 Tester l'envoi
            </a>
            <a href="modules/admin/email_logs.php" style="display: inline-block; margin: 5px; padding: 10px 20px; background: #059669; color: white; text-decoration: none; border-radius: 5px;">
                📜 Voir les logs
            </a>
            <a href="docs/CONFIGURATION_EMAIL.md" style="display: inline-block; margin: 5px; padding: 10px 20px; background: #d97706; color: white; text-decoration: none; border-radius: 5px;">
                📚 Documentation
            </a>
        </p>
    </div>

    <div class="check" style="border-left-color: #2196F3;">
        <h3>ℹ️ Statut du déploiement</h3>
        <p><strong>Commit actuel:</strong> <code><?php echo substr(exec('git rev-parse HEAD 2>&1'), 0, 7); ?></code></p>
        <p><strong>Dernière modification:</strong> <?php echo date('d/m/Y H:i:s', filemtime(__FILE__)); ?></p>
        <?php if (!EMAIL_ENABLED): ?>
        <div style="background: #fff3cd; padding: 15px; border-radius: 4px; margin-top: 10px;">
            <strong>⚠️ Les emails sont désactivés</strong><br>
            Pour activer l'envoi réel sur Railway, définissez <code>EMAIL_ENABLED=true</code> dans les variables d'environnement.
        </div>
        <?php endif; ?>
    </div>

    <p style="text-align: center; margin-top: 40px;">
        <a href="dashboard.php" style="color: #1e3a8a;">← Retour au dashboard</a>
    </p>
</body>
</html>

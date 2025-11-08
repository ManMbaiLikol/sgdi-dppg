<?php
/**
 * Script de nettoyage Railway - S'exécute une fois puis s'auto-détruit
 */

$files_to_delete = [
    'fix_railway.php',
    'init_railway.php',
    'setup_railway_accounts.php',
    'cleanup_railway.php' // Auto-destruction
];

$deleted = [];
$not_found = [];

foreach ($files_to_delete as $file) {
    $filepath = __DIR__ . '/' . $file;
    if (file_exists($filepath)) {
        if (unlink($filepath)) {
            $deleted[] = $file;
        }
    } else {
        $not_found[] = $file;
    }
}

// Affichage du rapport
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nettoyage Railway - SGDI</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 700px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        h1 {
            color: #1a202c;
            margin-bottom: 24px;
            font-size: 28px;
        }
        .success {
            background: #f0fff4;
            border-left: 4px solid #48bb78;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .success h3 {
            color: #22543d;
            margin-bottom: 12px;
        }
        .success ul {
            list-style: none;
            padding-left: 0;
        }
        .success li {
            padding: 8px 0;
            color: #2f855a;
        }
        .success li:before {
            content: "✅ ";
            margin-right: 8px;
        }
        .info {
            background: #ebf8ff;
            border-left: 4px solid #4299e1;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            color: #2c5282;
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
        <h1>🧹 Nettoyage Railway - SGDI</h1>

        <?php if (count($deleted) > 0): ?>
            <div class="success">
                <h3>Fichiers supprimés avec succès</h3>
                <ul>
                    <?php foreach ($deleted as $file): ?>
                        <li><?php echo htmlspecialchars($file); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (count($not_found) > 0): ?>
            <div class="info">
                <h3>ℹ️ Fichiers déjà absents</h3>
                <ul style="list-style: none; padding-left: 0;">
                    <?php foreach ($not_found as $file): ?>
                        <li style="padding: 4px 0;">📄 <?php echo htmlspecialchars($file); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="warning">
            <strong>⚠️ Important</strong><br><br>
            Ce script s'est auto-détruit après exécution.<br>
            Tous les fichiers de diagnostic temporaires ont été nettoyés.<br><br>
            <strong>Cette page n'existe plus.</strong> Si vous la rechargez, vous obtiendrez une erreur 404.
        </div>
    </div>
</body>
</html>

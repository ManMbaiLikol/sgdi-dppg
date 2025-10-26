<?php
/**
 * Script web pour augmenter la taille des colonnes superficie_site et besoins_mensuels_litres
 * URL: https://votre-app.railway.app/database/fix_superficie.php?token=fix2025
 *
 * IMPORTANT: Supprimez ce fichier après utilisation !
 */

// Protection par token
$expected_token = 'fix2025';
$provided_token = $_GET['token'] ?? '';

if ($provided_token !== $expected_token) {
    http_response_code(403);
    die('❌ Accès refusé. Utilisez: ?token=fix2025');
}

require_once __DIR__ . '/../config/database.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Superficie - Railway</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 10px 0; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0; }
        .info { background: #e7f3fe; border-left: 4px solid #2196F3; padding: 15px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Taille Colonnes - Railway</h1>

        <div class="info">
            <strong>ℹ️ Objectif:</strong><br>
            Augmenter la taille de <code>superficie_site</code> et <code>besoins_mensuels_litres</code><br>
            De: <code>DECIMAL(10,2)</code> → À: <code>DECIMAL(15,2)</code>
        </div>

        <?php
        try {
            echo "<h2>📝 Exécution des modifications...</h2>";

            // Désactiver le mode strict SQL pour gérer les dates invalides
            $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
            echo "<div class='success'>✅ Mode SQL strict désactivé</div>";

            // Modification 1: superficie_site
            echo "<p>Modification de <strong>superficie_site</strong>...</p>";
            $sql1 = "ALTER TABLE fiches_inspection
                    MODIFY COLUMN superficie_site DECIMAL(15,2) NULL COMMENT 'La superficie du site (mètre carré)'";
            $pdo->exec($sql1);
            echo "<div class='success'>✅ Colonne <strong>superficie_site</strong> modifiée (DECIMAL(15,2))</div>";

            // Modification 2: besoins_mensuels_litres
            echo "<p>Modification de <strong>besoins_mensuels_litres</strong>...</p>";
            $sql2 = "ALTER TABLE fiches_inspection
                    MODIFY COLUMN besoins_mensuels_litres DECIMAL(15,2) NULL COMMENT 'Besoins moyens mensuels en produits pétroliers (en litres)'";
            $pdo->exec($sql2);
            echo "<div class='success'>✅ Colonne <strong>besoins_mensuels_litres</strong> modifiée (DECIMAL(15,2))</div>";

            // Vérification
            echo "<h2>🔍 Vérification...</h2>";
            $stmt = $pdo->query("SHOW COLUMNS FROM fiches_inspection WHERE Field IN ('superficie_site', 'besoins_mensuels_litres')");
            $colonnes = $stmt->fetchAll();

            echo "<table style='width:100%; border-collapse: collapse; margin-top: 10px;'>";
            echo "<tr style='background: #f0f0f0;'><th style='padding: 10px; border: 1px solid #ddd;'>Colonne</th><th style='padding: 10px; border: 1px solid #ddd;'>Type</th></tr>";
            foreach ($colonnes as $col) {
                echo "<tr><td style='padding: 10px; border: 1px solid #ddd;'><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
                echo "<td style='padding: 10px; border: 1px solid #ddd;'><code>" . htmlspecialchars($col['Type']) . "</code></td></tr>";
            }
            echo "</table>";

            echo "<div class='success'>";
            echo "<h2>🎉 SUCCÈS !</h2>";
            echo "<p>Les colonnes ont été modifiées avec succès.</p>";
            echo "<p>Vous pouvez maintenant saisir des valeurs jusqu'à <strong>999 999 999 999.99</strong></p>";
            echo "</div>";

            echo "<div class='error'>";
            echo "<h3>🔐 Supprimez ce fichier maintenant !</h3>";
            echo "<pre>git rm database/fix_superficie.php\ngit commit -m \"Remove: Script fix temporaire\"\ngit push origin main</pre>";
            echo "</div>";

        } catch (PDOException $e) {
            echo "<div class='error'>❌ <strong>Erreur:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
    </div>
</body>
</html>

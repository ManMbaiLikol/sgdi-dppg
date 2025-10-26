<?php
/**
 * Script web pour appliquer les migrations
 * URL: https://votre-app.railway.app/database/apply_migrations.php
 *
 * IMPORTANT: Supprimez ce fichier après utilisation pour des raisons de sécurité!
 */

// Protection: Token de sécurité simple
$expected_token = 'migrate2025';
$provided_token = $_GET['token'] ?? '';

if ($provided_token !== $expected_token) {
    http_response_code(403);
    die('❌ Accès refusé. Utilisez: ?token=migrate2025');
}

// Charger la configuration de la base de données
require_once __DIR__ . '/../config/database.php';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application des migrations - Railway</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .info {
            background: #e7f3fe;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 10px 0;
        }
        .query {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 5px 0;
            overflow-x: auto;
        }
        .summary {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Application des migrations sur Railway</h1>

        <div class="info">
            <strong>ℹ️ Information:</strong><br>
            Ce script va appliquer toutes les migrations nécessaires pour le module fiche d'inspection.<br>
            Environnement: <strong><?php echo getenv('RAILWAY_ENVIRONMENT') ?: 'Local'; ?></strong><br>
            Base de données: <strong><?php echo getenv('MYSQL_DATABASE') ?: 'sgdi_mvp'; ?></strong>
        </div>

        <?php
        // Charger le fichier de migration
        $migration_file = __DIR__ . '/migrations/APPLIQUER_TOUTES_MIGRATIONS.sql';

        if (!file_exists($migration_file)) {
            echo '<div class="error">❌ Erreur: Fichier de migration non trouvé: ' . htmlspecialchars($migration_file) . '</div>';
            echo '</div></body></html>';
            exit;
        }

        $sql_content = file_get_contents($migration_file);

        if ($sql_content === false) {
            echo '<div class="error">❌ Erreur: Impossible de lire le fichier de migration</div>';
            echo '</div></body></html>';
            exit;
        }

        echo "<h2>📝 Exécution des migrations...</h2>";

        // DEBUG: Afficher infos fichier
        echo "<div class='info'>";
        echo "<strong>📋 DEBUG - Fichier SQL:</strong><br>";
        echo "Chemin: <code>" . htmlspecialchars($migration_file) . "</code><br>";
        echo "Taille brute: " . strlen($sql_content) . " octets<br>";
        echo "Premières 300 caractères:<br><pre>" . htmlspecialchars(substr($sql_content, 0, 300)) . "</pre>";
        echo "</div>";

        try {
            // Railway utilise la base "railway" - on reste dessus (pas de sgdi_mvp)
            echo "<div class='info'><strong>🔧 Préparation base de données...</strong><br>";
            $current_db = $pdo->query("SELECT DATABASE()")->fetchColumn();
            echo "✅ Base de données actuelle: <strong>" . htmlspecialchars($current_db) . "</strong><br>";
            echo "</div>";

            // Supprimer les commentaires SQL
            $sql_clean = preg_replace('/^--.*$/m', '', $sql_content);  // Commentaires --
            $sql_clean = preg_replace('/\/\*.*?\*\//s', '', $sql_clean); // Commentaires /* */

            // IMPORTANT: Supprimer la ligne "USE sgdi_mvp;" car Railway utilise la base "railway"
            $sql_clean = preg_replace('/^\s*USE\s+sgdi_mvp\s*;?\s*$/mi', '', $sql_clean);

            // IMPORTANT: MySQL (Railway) ne supporte pas "IF NOT EXISTS" dans ALTER TABLE ADD COLUMN
            // On le supprime et on gérera les erreurs "Duplicate column" comme des avertissements
            $sql_clean = preg_replace('/IF NOT EXISTS\s+/i', '', $sql_clean);

            echo "<div class='info'>";
            echo "Taille après nettoyage: " . strlen($sql_clean) . " octets<br>";
            echo "⚠️ Note: 'IF NOT EXISTS' supprimé pour compatibilité MySQL Railway<br>";
            echo "</div>";

            // Séparer les requêtes par point-virgule
            $queries_raw = explode(';', $sql_clean);

            // Filtrer et nettoyer
            $queries = [];
            foreach ($queries_raw as $query) {
                $query = trim($query);
                // Ignorer les requêtes vides, USE, SET, etc.
                if (!empty($query) &&
                    strlen($query) > 10 &&
                    !preg_match('/^(USE|SET|DELIMITER|SHOW|SELECT)\s/i', $query)) {
                    $queries[] = $query;
                }
            }

            echo "<p>Nombre de requêtes à exécuter: <strong>" . count($queries) . "</strong></p>";

            // DEBUG: Afficher les 3 premières requêtes
            if (count($queries) > 0 && count($queries) < 5) {
                echo "<div class='info'><strong>Preview requêtes:</strong><br>";
                foreach (array_slice($queries, 0, 3) as $i => $q) {
                    echo "<code>" . ($i+1) . ". " . htmlspecialchars(substr($q, 0, 100)) . "...</code><br>";
                }
                echo "</div>";
            }

            $success_count = 0;
            $error_count = 0;
            $warning_count = 0;

            foreach ($queries as $index => $query) {
                $query = trim($query);
                if (empty($query)) continue;

                try {
                    $pdo->exec($query . ';');
                    $success_count++;

                    // Extraire le type de requête pour affichage
                    if (preg_match('/^(ALTER TABLE|CREATE TABLE|CREATE VIEW|DROP VIEW|INSERT INTO|USE)\s+([^\s;]+)/i', $query, $matches)) {
                        echo '<div class="success">✅ ' . htmlspecialchars($matches[1] . ' ' . $matches[2]) . '</div>';
                    } else {
                        echo '<div class="success">✅ Requête ' . ($index + 1) . ' exécutée</div>';
                    }

                } catch (PDOException $e) {
                    $error_message = $e->getMessage();

                    // Ignorer les erreurs "Duplicate column" (colonne déjà existante)
                    if (strpos($error_message, 'Duplicate column') !== false) {
                        $warning_count++;
                        if (preg_match('/^ALTER TABLE\s+([^\s]+)\s+ADD/i', $query, $matches)) {
                            echo '<div class="warning">⚠️ Colonne déjà existante dans ' . htmlspecialchars($matches[1]) . ' (ignoré)</div>';
                        } else {
                            echo '<div class="warning">⚠️ Colonne déjà existante (ignoré)</div>';
                        }
                    } else {
                        $error_count++;
                        echo '<div class="error">';
                        echo '❌ <strong>Erreur:</strong> ' . htmlspecialchars($error_message) . '<br>';
                        echo '<div class="query">Requête: ' . htmlspecialchars(substr($query, 0, 200)) . '...</div>';
                        echo '</div>';
                    }
                }
            }

            // Résumé
            echo '<div class="summary">';
            echo '<h2>📊 Résumé de l\'exécution</h2>';
            echo '<ul>';
            echo '<li>✅ <strong>Requêtes réussies:</strong> ' . $success_count . '</li>';
            echo '<li>⚠️ <strong>Avertissements:</strong> ' . $warning_count . '</li>';
            echo '<li>❌ <strong>Erreurs:</strong> ' . $error_count . '</li>';
            echo '</ul>';
            echo '</div>';

            // Vérification des champs
            echo "<h2>🔍 Vérification des nouveaux champs</h2>";

            $nouveaux_champs = [
                'numero_contrat_approvisionnement',
                'societe_contractante',
                'besoins_mensuels_litres',
                'nombre_personnels',
                'superficie_site',
                'recommandations',
                'parc_engin',
                'systeme_recuperation_huiles',
                'batiments_site',
                'infra_eau',
                'infra_electricite',
                'reseau_camtel',
                'reseau_mtn',
                'reseau_orange',
                'reseau_nexttel'
            ];

            $stmt = $pdo->query("SHOW COLUMNS FROM fiches_inspection");
            $colonnes_existantes = array_column($stmt->fetchAll(), 'Field');

            $champs_trouves = 0;
            echo '<ul>';
            foreach ($nouveaux_champs as $champ) {
                if (in_array($champ, $colonnes_existantes)) {
                    echo '<li>✅ <strong>' . htmlspecialchars($champ) . '</strong></li>';
                    $champs_trouves++;
                } else {
                    echo '<li>❌ <strong>' . htmlspecialchars($champ) . '</strong> (MANQUANT)</li>';
                }
            }
            echo '</ul>';

            if ($champs_trouves === count($nouveaux_champs)) {
                echo '<div class="success">';
                echo '<h2>🎉 SUCCÈS TOTAL!</h2>';
                echo '<p>Toutes les migrations ont été appliquées correctement!</p>';
                echo '<p><strong>' . $champs_trouves . '/' . count($nouveaux_champs) . '</strong> champs vérifiés et confirmés.</p>';
                echo '</div>';
            } else {
                echo '<div class="error">';
                echo '<h2>⚠️ ATTENTION</h2>';
                echo '<p>Seulement <strong>' . $champs_trouves . '/' . count($nouveaux_champs) . '</strong> champs trouvés.</p>';
                echo '<p>Certains champs sont manquants. Vérifiez les erreurs ci-dessus.</p>';
                echo '</div>';
            }

            echo '<div class="info">';
            echo '<h3>🔐 Sécurité importante</h3>';
            echo '<p><strong>⚠️ SUPPRIMEZ CE FICHIER après utilisation :</strong></p>';
            echo '<pre>git rm database/apply_migrations.php
git commit -m "Remove: Script migration temporaire"
git push origin main</pre>';
            echo '</div>';

        } catch (PDOException $e) {
            echo '<div class="error">❌ <strong>Erreur de connexion:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
    </div>
</body>
</html>

<?php
/**
 * Import des stations-service historiques sur Railway
 * Import de 1011 stations depuis un fichier CSV
 *
 * IMPORTANT: Supprimer ce fichier après utilisation !
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300); // 5 minutes
ini_set('memory_limit', '256M');

$IMPORT_PASSWORD = 'import2024';

session_start();

try {
    require_once __DIR__ . '/config/database.php';
    $db_connected = true;
} catch (Exception $e) {
    $db_connected = false;
    $db_error = $e->getMessage();
}

$error = null;
$success = null;
$stats = [];
$messages = [];

// Vérification du mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $IMPORT_PASSWORD) {
        $_SESSION['import_authorized'] = true;
    } else {
        $error = "Mot de passe incorrect";
    }
}

$authorized = isset($_SESSION['import_authorized']) && $_SESSION['import_authorized'] === true;

// Traitement de l'upload et import
if ($authorized && $db_connected && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    try {
        $messages[] = "📁 Fichier CSV reçu: " . $_FILES['csv_file']['name'];

        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$file) {
            throw new Exception("Impossible d'ouvrir le fichier CSV");
        }

        // Lire l'en-tête
        $header = fgetcsv($file, 0, ',');
        $messages[] = "📋 En-tête CSV: " . implode(', ', $header);

        // Créer un mapping des colonnes
        $colMap = array_flip($header);
        $messages[] = "🔍 Colonnes trouvées: " . count($header);

        // Préparer la requête d'insertion (colonnes minimales requises)
        $sql = "INSERT INTO dossiers (
            numero, type_infrastructure, sous_type, nom_demandeur,
            contact_demandeur, telephone_demandeur, email_demandeur,
            region, departement, ville, arrondissement, quartier, lieu_dit,
            coordonnees_gps, statut, date_creation, user_id
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?
        )";

        $stmt = $pdo->prepare($sql);

        $pdo->beginTransaction();
        $messages[] = "🔄 Transaction démarrée";

        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $lineNumber = 1;

        while (($row = fgetcsv($file, 0, ',')) !== false) {
            $lineNumber++;

            try {
                // Fonction helper pour récupérer valeur par nom de colonne
                $getCol = function($name) use ($row, $colMap) {
                    return isset($colMap[$name]) ? ($row[$colMap[$name]] ?? null) : null;
                };

                $numero = $getCol('numero');
                if (empty($numero)) {
                    $skipped++;
                    continue; // Pas de numéro
                }

                // Vérifier si le numéro existe déjà
                $checkStmt = $pdo->prepare("SELECT id FROM dossiers WHERE numero = ?");
                $checkStmt->execute([$numero]);

                if ($checkStmt->fetch()) {
                    $skipped++;
                    continue; // Déjà existant
                }

                // Insérer le dossier avec les colonnes du CSV
                $stmt->execute([
                    $numero,
                    $getCol('type_infrastructure') ?? 'station_service',
                    $getCol('sous_type') ?? 'implantation',
                    $getCol('nom_demandeur'),
                    $getCol('contact_demandeur'),
                    $getCol('telephone_demandeur'),
                    $getCol('email_demandeur'),
                    $getCol('region'),
                    $getCol('departement'),
                    $getCol('ville'),
                    $getCol('arrondissement'),
                    $getCol('quartier'),
                    $getCol('lieu_dit'),
                    $getCol('coordonnees_gps'),
                    $getCol('statut') ?? 'historique_autorise',
                    $getCol('date_creation') ?? date('Y-m-d H:i:s'),
                    $getCol('user_id') ?? 1
                ]);

                $imported++;

                // Afficher la progression tous les 100
                if ($imported % 100 === 0) {
                    $messages[] = "⏳ Progression: {$imported} stations importées...";
                }

            } catch (Exception $e) {
                $errors++;
                if ($errors < 10) { // Limiter l'affichage des erreurs
                    $messages[] = "❌ Erreur ligne {$lineNumber}: " . $e->getMessage();
                }
            }
        }

        fclose($file);

        $pdo->commit();
        $messages[] = "✅ Transaction validée";

        $stats = [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => $lineNumber - 1
        ];

        $success = true;
        $messages[] = "🎉 Import terminé avec succès !";

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Erreur lors de l'import : " . $e->getMessage();
        $messages[] = "❌ Rollback effectué";
    }
}

// Compter les stations existantes
$existing_count = 0;
if ($authorized && $db_connected) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE statut = 'historique_autorise'");
        $existing_count = $stmt->fetchColumn();
    } catch (Exception $e) {
        // Table n'existe pas encore
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Stations Historiques - SGDI</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; margin-top: 0; }
        .alert { padding: 15px; margin: 15px 0; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn:hover { opacity: 0.9; }
        input[type="password"], input[type="file"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        .messages { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0; max-height: 400px; overflow-y: auto; }
        .messages div { margin: 5px 0; font-family: monospace; font-size: 14px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
        .stat-box { background: #f8f9fa; padding: 15px; border-radius: 6px; text-align: center; }
        .stat-number { font-size: 32px; font-weight: bold; color: #007bff; }
        .stat-label { color: #666; margin-top: 5px; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>📥 Import Stations Historiques - SGDI</h1>

        <?php if (!$db_connected): ?>
            <div class="alert alert-danger">
                <strong>Erreur de connexion à la base de données</strong><br>
                <?php echo htmlspecialchars($db_error); ?>
            </div>

        <?php elseif (!$authorized): ?>
            <div class="alert alert-warning">
                <strong>🔒 Authentification requise</strong><br>
                Entrez le mot de passe d'import.
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <label>Mot de passe :</label>
                <input type="password" name="password" required autofocus>
                <small style="color: #666;">Par défaut: import2024</small><br><br>
                <button type="submit" class="btn btn-primary">Se connecter</button>
            </form>

        <?php elseif ($success): ?>
            <div class="alert alert-success">
                <strong>✅ Import terminé avec succès !</strong>
            </div>

            <div class="stats">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $stats['imported']; ?></div>
                    <div class="stat-label">Importées</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $stats['skipped']; ?></div>
                    <div class="stat-label">Ignorées (doublons)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $stats['errors']; ?></div>
                    <div class="stat-label">Erreurs</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total traité</div>
                </div>
            </div>

            <?php if (!empty($messages)): ?>
                <div class="messages">
                    <strong>📝 Log d'import :</strong>
                    <?php foreach ($messages as $msg): ?>
                        <div><?php echo htmlspecialchars($msg); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <a href="modules/registre_public/carte.php" class="btn btn-success">
                🗺️ Voir la carte
            </a>

            <div class="alert alert-danger" style="margin-top: 20px;">
                <strong>⚠️ SÉCURITÉ</strong><br>
                Supprimez immédiatement le fichier <code>import_historical_stations_railway.php</code> !
            </div>

        <?php else: ?>
            <div class="alert alert-info">
                <strong>Prêt pour l'import</strong><br>
                <?php if ($existing_count > 0): ?>
                    <p>⚠️ La base contient déjà <strong><?php echo $existing_count; ?> stations historiques</strong>.</p>
                    <p>Les doublons seront automatiquement ignorés.</p>
                <?php else: ?>
                    <p>Aucune station historique trouvée dans la base.</p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <label>Fichier CSV exporté :</label>
                <input type="file" name="csv_file" accept=".csv" required>

                <p style="margin-top: 20px;"><strong>Format attendu :</strong></p>
                <ul>
                    <li>Fichier CSV séparé par virgules</li>
                    <li>Avec en-tête (première ligne)</li>
                    <li>Colonnes dans l'ordre de l'export SQL</li>
                    <li>Environ 1011 lignes (stations historiques)</li>
                </ul>

                <button type="submit" class="btn btn-primary" style="margin-top: 20px;">
                    📤 Lancer l'import
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

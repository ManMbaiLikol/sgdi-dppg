<?php
/**
 * Script d'installation des nouvelles fonctionnalités
 * - Système de contraintes de distance
 * - Fiche d'inspection détaillée
 *
 * À exécuter une seule fois pour créer toutes les tables nécessaires
 */

// Charger la configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Fonction pour exécuter un fichier SQL
function executeSQLFile($pdo, $filename) {
    echo "<div style='margin: 20px; padding: 15px; border: 1px solid #ddd; background: #f8f9fa; border-radius: 5px;'>";
    echo "<h3 style='color: #007bff; margin-top: 0;'>⚙️ Exécution de : " . basename($filename) . "</h3>";

    if (!file_exists($filename)) {
        echo "<p style='color: red;'><strong>❌ ERREUR:</strong> Le fichier n'existe pas : $filename</p>";
        echo "</div>";
        return false;
    }

    $sql = file_get_contents($filename);
    if ($sql === false) {
        echo "<p style='color: red;'><strong>❌ ERREUR:</strong> Impossible de lire le fichier : $filename</p>";
        echo "</div>";
        return false;
    }

    // Séparer les commandes SQL (en supprimant les commentaires)
    $sql = preg_replace('/--.*$/m', '', $sql); // Supprimer les commentaires de ligne
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Supprimer les commentaires de bloc

    // Découper par point-virgule (mais pas dans les chaînes)
    $statements = [];
    $current = '';
    $in_string = false;
    $string_char = '';

    for ($i = 0; $i < strlen($sql); $i++) {
        $char = $sql[$i];

        if (($char === "'" || $char === '"') && ($i === 0 || $sql[$i-1] !== '\\')) {
            if (!$in_string) {
                $in_string = true;
                $string_char = $char;
            } elseif ($char === $string_char) {
                $in_string = false;
            }
        }

        if ($char === ';' && !$in_string) {
            $statement = trim($current);
            if (!empty($statement)) {
                $statements[] = $statement;
            }
            $current = '';
        } else {
            $current .= $char;
        }
    }

    // Ajouter la dernière instruction si elle n'est pas vide
    $statement = trim($current);
    if (!empty($statement)) {
        $statements[] = $statement;
    }

    $success_count = 0;
    $error_count = 0;
    $errors = [];

    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        if (empty($statement)) {
            continue;
        }

        try {
            $pdo->exec($statement);
            $success_count++;

            // Afficher un résumé de la commande
            $preview = substr($statement, 0, 80);
            if (strlen($statement) > 80) {
                $preview .= '...';
            }
            echo "<p style='color: green; margin: 5px 0;'>✅ Commande " . ($index + 1) . " : " . htmlspecialchars($preview) . "</p>";

        } catch (PDOException $e) {
            $error_count++;
            $error_msg = $e->getMessage();

            // Ignorer certaines erreurs non critiques
            if (strpos($error_msg, 'Duplicate column name') !== false ||
                strpos($error_msg, 'Table') !== false && strpos($error_msg, 'already exists') !== false ||
                strpos($error_msg, 'Duplicate key name') !== false) {
                echo "<p style='color: orange; margin: 5px 0;'>⚠️ Avertissement : " . htmlspecialchars($error_msg) . "</p>";
            } else {
                $errors[] = "Commande " . ($index + 1) . ": " . $error_msg;
                echo "<p style='color: red; margin: 5px 0;'><strong>❌ Erreur :</strong> " . htmlspecialchars($error_msg) . "</p>";
            }
        }
    }

    echo "<div style='margin-top: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #007bff;'>";
    echo "<strong>📊 Résumé :</strong><br>";
    echo "✅ Commandes réussies : $success_count<br>";
    if ($error_count > 0) {
        echo "❌ Erreurs rencontrées : $error_count";
    }
    echo "</div>";

    echo "</div>";

    return empty($errors);
}

// Vérifier que l'utilisateur est admin (simple vérification)
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('<div style="margin: 50px auto; max-width: 600px; padding: 30px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; text-align: center;">
        <h2 style="color: #856404;">🔒 Accès restreint</h2>
        <p>Seuls les administrateurs peuvent exécuter ce script d\'installation.</p>
        <a href="../dashboard.php" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">Retour au tableau de bord</a>
    </div>');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation des nouvelles fonctionnalités - SGDI</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 15px;
            margin-top: 0;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            border-left: 4px solid;
        }
        .alert-info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        .alert-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .alert-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .feature-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        .feature-box h3 {
            margin-top: 0;
            color: #495057;
        }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            margin: 10px 5px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Installation des nouvelles fonctionnalités</h1>

        <div class="alert alert-info">
            <strong>ℹ️ Information</strong><br>
            Ce script va installer deux nouvelles fonctionnalités majeures dans votre système SGDI :
        </div>

        <div class="feature-box">
            <h3>📍 1. Système de contraintes de distance</h3>
            <p>
                <strong>Fonctionnalités :</strong>
            </p>
            <ul>
                <li>Gestion des Points d'Intérêt Stratégiques (POI)</li>
                <li>Validation géospatiale automatique des dossiers</li>
                <li>Vérification des distances réglementaires (500m entre stations, distances avec établissements)</li>
                <li>Carte interactive avec zones de contrainte</li>
                <li>Support zone urbaine/rurale (réduction de 20% en zone rurale)</li>
            </ul>
        </div>

        <div class="feature-box">
            <h3>📋 2. Fiche d'inspection détaillée</h3>
            <p>
                <strong>Fonctionnalités :</strong>
            </p>
            <ul>
                <li>Formulaire complet de récolte de données sur site</li>
                <li>Gestion des cuves et pompes (produits, capacités, nombres)</li>
                <li>Mesure des distances aux édifices et stations-service environnants</li>
                <li>Géoréférencement GPS (décimal et DMS)</li>
                <li>Impression PDF (version remplie et vierge)</li>
            </ul>
        </div>

<?php
// Vérifier si l'installation a déjà été lancée
if (isset($_GET['install']) && $_GET['install'] === 'start'):

    echo "<h2 style='color: #007bff; margin-top: 30px;'>🔧 Installation en cours...</h2>";

    try {
        // Liste des fichiers SQL à exécuter
        $sql_files = [
            __DIR__ . '/add_contraintes_distance_compatible.sql',  // Version compatible MySQL 5.7+
            __DIR__ . '/add_fiche_inspection.sql'
        ];

        $all_success = true;

        foreach ($sql_files as $file) {
            $result = executeSQLFile($pdo, $file);
            if (!$result) {
                $all_success = false;
            }
        }

        if ($all_success) {
            echo "<div class='alert alert-success'>";
            echo "<h3 style='margin-top: 0;'>✅ Installation terminée avec succès !</h3>";
            echo "<p>Toutes les tables et fonctionnalités ont été installées correctement.</p>";
            echo "<p><strong>Prochaines étapes :</strong></p>";
            echo "<ol>";
            echo "<li>Accédez au module <strong>Gérer les POI</strong> (visible dans la carte pour les administrateurs)</li>";
            echo "<li>Ajoutez les points d'intérêt stratégiques de votre région</li>";
            echo "<li>Testez la validation géospatiale sur un dossier station-service avec coordonnées GPS</li>";
            echo "<li>Créez une fiche d'inspection pour un dossier (accessible aux cadres DPPG)</li>";
            echo "</ol>";
            echo "</div>";

            echo "<div style='text-align: center; margin-top: 30px;'>";
            echo "<a href='../dashboard.php' class='btn btn-primary'>Retour au tableau de bord</a>";
            echo "<a href='../modules/carte/index.php' class='btn btn-secondary'>Voir la carte</a>";
            echo "</div>";
        } else {
            echo "<div class='alert alert-warning'>";
            echo "<h3 style='margin-top: 0;'>⚠️ Installation terminée avec des avertissements</h3>";
            echo "<p>Certaines erreurs non critiques ont été rencontrées (probablement des tables qui existaient déjà).</p>";
            echo "<p>Vérifiez les messages ci-dessus pour plus de détails.</p>";
            echo "</div>";

            echo "<div style='text-align: center; margin-top: 30px;'>";
            echo "<a href='../dashboard.php' class='btn btn-primary'>Retour au tableau de bord</a>";
            echo "</div>";
        }

    } catch (Exception $e) {
        echo "<div class='alert alert-warning'>";
        echo "<h3 style='margin-top: 0;'>❌ Erreur durant l'installation</h3>";
        echo "<p><strong>Message d'erreur :</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>Veuillez contacter l'administrateur système si le problème persiste.</p>";
        echo "</div>";

        echo "<div style='text-align: center; margin-top: 30px;'>";
        echo "<a href='?install=start' class='btn btn-primary'>Réessayer</a>";
        echo "<a href='../dashboard.php' class='btn btn-secondary'>Retour au tableau de bord</a>";
        echo "</div>";
    }

else:
    // Afficher le formulaire de confirmation
?>
        <div class="alert alert-warning">
            <strong>⚠️ Attention</strong><br>
            Cette installation va créer de nouvelles tables dans votre base de données. Si les tables existent déjà,
            le script ignorera les erreurs de duplication.
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="?install=start" class="btn btn-primary">🚀 Lancer l'installation</a>
            <a href="../dashboard.php" class="btn btn-secondary">Annuler</a>
        </div>

        <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
            <h3>ℹ️ Informations techniques</h3>
            <p><strong>Tables qui seront créées :</strong></p>
            <ul>
                <li><code>categories_poi</code> - Catégories de points d'intérêt</li>
                <li><code>points_interet</code> - Points d'intérêt stratégiques</li>
                <li><code>validations_geospatiales</code> - Historique des validations</li>
                <li><code>violations_contraintes</code> - Violations détectées</li>
                <li><code>audit_poi</code> - Journal des modifications POI</li>
                <li><code>fiches_inspection</code> - Fiches d'inspection principales</li>
                <li><code>fiche_inspection_cuves</code> - Cuves des fiches</li>
                <li><code>fiche_inspection_pompes</code> - Pompes des fiches</li>
                <li><code>fiche_inspection_distances_edifices</code> - Distances aux édifices</li>
                <li><code>fiche_inspection_distances_stations</code> - Distances aux stations</li>
            </ul>

            <p><strong>Colonnes ajoutées à la table dossiers :</strong></p>
            <ul>
                <li><code>zone_type</code> - Type de zone (urbaine/rurale)</li>
                <li><code>validation_geospatiale_faite</code> - Si validation effectuée</li>
                <li><code>conformite_geospatiale</code> - Résultat de conformité</li>
            </ul>
        </div>
<?php
endif;
?>

    </div>
</body>
</html>

<?php
// Test de validation du fichier CSV
require_once '../../includes/auth.php';
require_once 'functions.php';

requireLogin();

$pageTitle = "Test de validation CSV";
include '../../includes/header.php';
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-info text-white">
            <h3>🧪 Test de validation du fichier CSV</h3>
        </div>
        <div class="card-body">

            <h4>Données de test (ligne 2 du fichier TEST_PILOTE_10_DOSSIERS.csv) :</h4>

            <?php
            // Simuler une ligne du CSV
            $ligne = [
                'numero_dossier' => '',
                'type_infrastructure' => 'Implantation station-service',
                'nom_demandeur' => 'TOTAL CAMEROUN',
                'region' => 'Littoral',
                'ville' => 'Douala',
                'latitude' => '4.0511',
                'longitude' => '9.7679',
                'date_autorisation' => '15/03/2015',
                'numero_decision' => 'N°0125/MINEE/SG/DPPG/SDTD',
                'observations' => 'Station autorisée avant SGDI - Test pilote'
            ];

            echo '<pre>';
            print_r($ligne);
            echo '</pre>';

            // Valider la ligne
            echo '<h4>Résultat de la validation :</h4>';
            $erreurs = validerLigneImport($ligne, 2);

            if (empty($erreurs)) {
                echo '<div class="alert alert-success">';
                echo '<h5>✅ Validation réussie !</h5>';
                echo '<p>Aucune erreur détectée. Cette ligne peut être importée.</p>';
                echo '</div>';

                // Tester l'import
                echo '<h4>Test d\'import dans la base :</h4>';
                $result = insererDossierHistorique($ligne, $_SESSION['user_id']);

                if ($result['success']) {
                    echo '<div class="alert alert-success">';
                    echo '<h5>✅ Import réussi !</h5>';
                    echo '<p>Dossier ID : ' . $result['dossier_id'] . '</p>';
                    echo '<p>Numéro : ' . $result['numero'] . '</p>';
                    echo '</div>';
                } else {
                    echo '<div class="alert alert-danger">';
                    echo '<h5>❌ Erreur lors de l\'import :</h5>';
                    echo '<p>' . htmlspecialchars($result['error']) . '</p>';
                    echo '</div>';
                }

            } else {
                echo '<div class="alert alert-danger">';
                echo '<h5>❌ Erreurs de validation détectées :</h5>';
                echo '<ul>';
                foreach ($erreurs as $erreur) {
                    echo '<li>' . htmlspecialchars($erreur) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            ?>

            <hr>

            <h4>Test de lecture du fichier CSV complet :</h4>

            <?php
            $csvPath = __DIR__ . '/templates/TEST_PILOTE_10_DOSSIERS.csv';

            if (file_exists($csvPath)) {
                echo '<p>✅ Fichier trouvé : <code>' . $csvPath . '</code></p>';

                try {
                    $donnees = lireCSV($csvPath);
                    echo '<p>✅ Fichier lu avec succès</p>';
                    echo '<p><strong>Nombre de lignes :</strong> ' . count($donnees) . '</p>';

                    echo '<h5>Validation de toutes les lignes :</h5>';
                    $totalErreurs = 0;

                    foreach ($donnees as $index => $ligne) {
                        $ligneNum = $index + 2; // +2 car ligne 1 = en-tête, index 0 = ligne 2
                        $errs = validerLigneImport($ligne, $ligneNum);

                        if (!empty($errs)) {
                            $totalErreurs += count($errs);
                            echo '<div class="alert alert-warning">';
                            echo '<strong>Ligne ' . $ligneNum . ' :</strong>';
                            echo '<ul>';
                            foreach ($errs as $err) {
                                echo '<li>' . htmlspecialchars($err) . '</li>';
                            }
                            echo '</ul>';
                            echo '</div>';
                        }
                    }

                    if ($totalErreurs === 0) {
                        echo '<div class="alert alert-success">';
                        echo '<h5>✅ Toutes les lignes sont valides !</h5>';
                        echo '<p>Le fichier peut être importé sans erreur.</p>';
                        echo '</div>';
                    } else {
                        echo '<div class="alert alert-danger">';
                        echo '<h5>❌ Total : ' . $totalErreurs . ' erreur(s) détectée(s)</h5>';
                        echo '</div>';
                    }

                } catch (Exception $e) {
                    echo '<div class="alert alert-danger">';
                    echo '❌ Erreur de lecture : ' . htmlspecialchars($e->getMessage());
                    echo '</div>';
                }

            } else {
                echo '<div class="alert alert-danger">';
                echo '❌ Fichier non trouvé : ' . $csvPath;
                echo '</div>';
            }
            ?>

            <hr>
            <a href="index.php" class="btn btn-secondary">← Retour au module</a>
            <a href="test_database.php" class="btn btn-info">Test base de données</a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

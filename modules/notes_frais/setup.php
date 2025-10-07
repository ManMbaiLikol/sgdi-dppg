<?php
// Configuration automatique du module notes de frais - SGDI MVP
require_once '../../includes/auth.php';

// Seul l'admin peut configurer le système
requireRole('admin');

$page_title = 'Configuration du module Notes de frais';
$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        try {
            global $pdo;

            // Vérifier si la table existe déjà
            $tables_check = $pdo->query("SHOW TABLES LIKE 'notes_frais'");
            if ($tables_check->rowCount() > 0) {
                $errors[] = 'La table notes_frais existe déjà';
            } else {
                // Créer la table notes_frais
                $sql = "CREATE TABLE notes_frais (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    dossier_id INT NOT NULL,
                    description TEXT,
                    montant_base DECIMAL(12,2) NOT NULL,
                    montant_frais_deplacement DECIMAL(12,2) DEFAULT 0,
                    montant_total DECIMAL(12,2) NOT NULL,
                    statut ENUM('en_attente', 'validee', 'payee', 'annulee') DEFAULT 'en_attente',
                    user_id INT NOT NULL,
                    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    FOREIGN KEY (dossier_id) REFERENCES dossiers(id),
                    FOREIGN KEY (user_id) REFERENCES users(id),
                    INDEX idx_statut (statut),
                    INDEX idx_dossier (dossier_id)
                )";

                $pdo->exec($sql);

                // Insérer quelques notes de frais de démonstration
                $demo_notes = [
                    [
                        'dossier_id' => 1,
                        'description' => 'Frais d\'inspection - Station service TOTAL',
                        'montant_base' => 75000,
                        'montant_frais_deplacement' => 25000,
                        'montant_total' => 100000,
                        'statut' => 'validee',
                        'user_id' => 2
                    ],
                    [
                        'dossier_id' => 3,
                        'description' => 'Frais de dossier et commission - SHELL',
                        'montant_base' => 50000,
                        'montant_frais_deplacement' => 15000,
                        'montant_total' => 65000,
                        'statut' => 'en_attente',
                        'user_id' => 2
                    ]
                ];

                $insert_sql = "INSERT INTO notes_frais (dossier_id, description, montant_base,
                                                       montant_frais_deplacement, montant_total,
                                                       statut, user_id, date_creation)
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

                $stmt = $pdo->prepare($insert_sql);
                foreach ($demo_notes as $note) {
                    $stmt->execute([
                        $note['dossier_id'],
                        $note['description'],
                        $note['montant_base'],
                        $note['montant_frais_deplacement'],
                        $note['montant_total'],
                        $note['statut'],
                        $note['user_id']
                    ]);
                }

                $success = true;
            }

        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la configuration: ' . $e->getMessage();
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-tools"></i> Configuration du module Notes de frais
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle"></i> Configuration réussie !</h6>
                        <p class="mb-2">Le module Notes de frais a été configuré avec succès :</p>
                        <ul class="mb-3">
                            <li>Table <code>notes_frais</code> créée</li>
                            <li>2 notes de démonstration ajoutées</li>
                            <li>Indexes et contraintes appliqués</li>
                        </ul>
                        <a href="<?php echo url('modules/notes_frais/list.php'); ?>" class="btn btn-success">
                            <i class="fas fa-arrow-right"></i> Accéder aux notes de frais
                        </a>
                    </div>
                    <?php elseif (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle"></i> Erreurs détectées :</h6>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo sanitize($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (!$success): ?>
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Configuration requise</h6>
                        <p>Cette action va créer la table <code>notes_frais</code> et ses dépendances dans la base de données.</p>
                        <p class="mb-0">Cliquez sur "Configurer" pour procéder à l'installation automatique.</p>
                    </div>

                    <h6 class="mt-4 mb-3">Fonctionnalités qui seront activées :</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Création de notes de frais</li>
                                <li><i class="fas fa-check text-success"></i> Gestion des montants</li>
                                <li><i class="fas fa-check text-success"></i> Suivi des statuts</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Validation par workflow</li>
                                <li><i class="fas fa-check text-success"></i> Historique des paiements</li>
                                <li><i class="fas fa-check text-success"></i> Rapports financiers</li>
                            </ul>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?php echo url('modules/notes_frais/list.php'); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <button type="submit" class="btn btn-warning" onclick="return confirm('Êtes-vous sûr de vouloir configurer le module Notes de frais ?')">
                                <i class="fas fa-tools"></i> Configurer maintenant
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
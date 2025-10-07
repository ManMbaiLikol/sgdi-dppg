<?php
// Gestion des notes de frais - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

// Seuls le chef service et l'admin peuvent gérer les notes de frais
requireAnyRole(['chef_service', 'admin', 'billeteur']);

$page_title = 'Gestion des notes de frais';

// Vérifier si la table notes_frais existe
$table_exists = false;
try {
    global $pdo;
    $tables_check = $pdo->query("SHOW TABLES LIKE 'notes_frais'");
    $table_exists = $tables_check->rowCount() > 0;
} catch (Exception $e) {
    // Table n'existe pas
}

// Filtres
$filters = [
    'search' => sanitize($_GET['search'] ?? ''),
    'statut' => sanitize($_GET['statut'] ?? ''),
    'dossier_id' => intval($_GET['dossier_id'] ?? 0)
];

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Récupérer les données si la table existe
$notes_frais = [];
$total_notes = 0;
$stats = ['total' => 0, 'en_attente' => 0, 'validee' => 0, 'payee' => 0, 'montant_total' => 0];

if ($table_exists) {
    $notes_frais = getNotesAvecFiltres($filters, $limit, $offset);
    $total_notes = countNotesAvecFiltres($filters);
    $stats = getStatistiquesNotesFrais();
}

$total_pages = ceil($total_notes / $limit);

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-file-invoice-dollar"></i> Notes de frais
            </h1>
            <p class="text-muted">Gestion des notes de frais d'inspection</p>
        </div>
        <div>
            <?php if (hasAnyRole(['chef_service', 'admin']) && $table_exists): ?>
            <a href="<?php echo url('modules/notes_frais/create.php'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nouvelle note de frais
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$table_exists): ?>
    <!-- Message si table n'existe pas -->
    <div class="row">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Configuration requise</h5>
                </div>
                <div class="card-body">
                    <p>Le module des notes de frais n'est pas encore configuré dans la base de données.</p>
                    <p class="mb-3">Vous devez d'abord exécuter le script de création de la table :</p>

                    <div class="alert alert-info">
                        <h6><i class="fas fa-code"></i> Script SQL à exécuter :</h6>
                        <pre class="mb-0"><code>CREATE TABLE notes_frais (
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
);</code></pre>
                    </div>

                    <?php if (hasRole('admin')): ?>
                    <a href="<?php echo url('modules/notes_frais/setup.php'); ?>" class="btn btn-warning">
                        <i class="fas fa-tools"></i> Configurer automatiquement
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>

    <!-- Statistiques rapides -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-file-invoice fa-2x text-primary mb-2"></i>
                    <h4 class="text-primary"><?php echo $stats['total']; ?></h4>
                    <p class="text-muted mb-0">Total notes</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                    <h4 class="text-warning"><?php echo $stats['en_attente'] ?? 0; ?></h4>
                    <p class="text-muted mb-0">En attente</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <h4 class="text-success"><?php echo $stats['validee'] ?? 0; ?></h4>
                    <p class="text-muted mb-0">Validées</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-money-bill-wave fa-2x text-info mb-2"></i>
                    <h4 class="text-info"><?php echo number_format($stats['montant_total'], 0, ',', ' '); ?> F</h4>
                    <p class="text-muted mb-0">Montant total</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Recherche</label>
                    <input type="text" class="form-control" id="search" name="search"
                           placeholder="Numéro dossier, demandeur..."
                           value="<?php echo sanitize($filters['search']); ?>">
                </div>
                <div class="col-md-3">
                    <label for="statut" class="form-label">Statut</label>
                    <select class="form-select" id="statut" name="statut">
                        <option value="">Tous les statuts</option>
                        <option value="en_attente" <?php echo $filters['statut'] === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                        <option value="validee" <?php echo $filters['statut'] === 'validee' ? 'selected' : ''; ?>>Validée</option>
                        <option value="payee" <?php echo $filters['statut'] === 'payee' ? 'selected' : ''; ?>>Payée</option>
                        <option value="annulee" <?php echo $filters['statut'] === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="<?php echo url('modules/notes_frais/list.php'); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-eraser"></i> Effacer
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des notes de frais -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list"></i> Notes de frais
                <small class="text-muted">(<?php echo $total_notes; ?> note<?php echo $total_notes > 1 ? 's' : ''; ?>)</small>
            </h5>
        </div>

        <?php if (empty($notes_frais)): ?>
        <div class="card-body text-center py-5">
            <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
            <p class="text-muted">Aucune note de frais trouvée avec les critères sélectionnés</p>
            <?php if (hasAnyRole(['chef_service', 'admin'])): ?>
            <a href="<?php echo url('modules/notes_frais/create.php'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Créer la première note de frais
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Dossier</th>
                        <th>Demandeur</th>
                        <th>Description</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notes_frais as $note): ?>
                    <tr>
                        <td>
                            <strong><?php echo sanitize($note['dossier_numero']); ?></strong>
                        </td>
                        <td><?php echo sanitize($note['nom_demandeur']); ?></td>
                        <td>
                            <?php if (strlen($note['description']) > 50): ?>
                                <?php echo sanitize(substr($note['description'], 0, 50)) . '...'; ?>
                            <?php else: ?>
                                <?php echo sanitize($note['description']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo number_format($note['montant_total'], 0, ',', ' '); ?> F</strong>
                        </td>
                        <td>
                            <?php
                            $statut_colors = [
                                'en_attente' => 'warning',
                                'validee' => 'success',
                                'payee' => 'info',
                                'annulee' => 'danger'
                            ];
                            $statut_labels = [
                                'en_attente' => 'En attente',
                                'validee' => 'Validée',
                                'payee' => 'Payée',
                                'annulee' => 'Annulée'
                            ];
                            ?>
                            <span class="badge bg-<?php echo $statut_colors[$note['statut']] ?? 'secondary'; ?>">
                                <?php echo $statut_labels[$note['statut']] ?? $note['statut']; ?>
                            </span>
                        </td>
                        <td><?php echo formatDateTime($note['date_creation'], 'd/m/Y'); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="<?php echo url('modules/notes_frais/view.php?id=' . $note['id']); ?>"
                                   class="btn btn-outline-primary btn-sm"
                                   title="Voir détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (peutModifierNoteFrais($note, $_SESSION['user_role'], $_SESSION['user_id'])): ?>
                                <a href="<?php echo url('modules/notes_frais/edit.php?id=' . $note['id']); ?>"
                                   class="btn btn-outline-warning btn-sm"
                                   title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : ''; ?><?php echo !empty($filters['statut']) ? '&statut=' . urlencode($filters['statut']) : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
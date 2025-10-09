<?php
// Logs d'envoi d'emails - SGDI MVP
require_once '../../includes/auth.php';

// Seuls les administrateurs peuvent voir les logs
requireRole('admin');

$page_title = 'Logs d\'envoi d\'email';

// Filtres
$statut = sanitize($_GET['statut'] ?? '');
$search = sanitize($_GET['search'] ?? '');

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Construction de la requête
$sql = "SELECT * FROM email_logs WHERE 1=1";
$params = [];

if ($statut) {
    $sql .= " AND statut = :statut";
    $params['statut'] = $statut;
}

if ($search) {
    $sql .= " AND (destinataire LIKE :search OR sujet LIKE :search)";
    $params['search'] = "%$search%";
}

$sql .= " ORDER BY date_envoi DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// Compte total
$sql_count = "SELECT COUNT(*) FROM email_logs WHERE 1=1";
if ($statut) {
    $sql_count .= " AND statut = :statut";
}
if ($search) {
    $sql_count .= " AND (destinataire LIKE :search OR sujet LIKE :search)";
}
$stmt_count = $pdo->prepare($sql_count);
foreach ($params as $key => $value) {
    if ($key !== 'limit' && $key !== 'offset') {
        $stmt_count->bindValue(':' . $key, $value);
    }
}
$stmt_count->execute();
$total = $stmt_count->fetchColumn();
$total_pages = ceil($total / $limit);

// Statistiques
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM email_logs")->fetchColumn(),
    'sent' => $pdo->query("SELECT COUNT(*) FROM email_logs WHERE statut = 'sent'")->fetchColumn(),
    'failed' => $pdo->query("SELECT COUNT(*) FROM email_logs WHERE statut = 'failed'")->fetchColumn(),
    'disabled' => $pdo->query("SELECT COUNT(*) FROM email_logs WHERE statut = 'disabled'")->fetchColumn()
];

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3"><?php echo $page_title; ?></h1>
            <p class="text-muted">Historique de tous les emails envoyés par le système</p>
        </div>
        <div>
            <a href="<?php echo url('modules/admin/test_email.php'); ?>" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Tester l'envoi
            </a>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Total emails</p>
                            <h4><?php echo number_format($stats['total']); ?></h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-envelope fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Envoyés</p>
                            <h4 class="text-success"><?php echo number_format($stats['sent']); ?></h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Échoués</p>
                            <h4 class="text-danger"><?php echo number_format($stats['failed']); ?></h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-times-circle fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Désactivés</p>
                            <h4 class="text-warning"><?php echo number_format($stats['disabled']); ?></h4>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-ban fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Recherche</label>
                    <input type="text" class="form-control" name="search"
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Email ou sujet...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Statut</label>
                    <select class="form-select" name="statut">
                        <option value="">Tous les statuts</option>
                        <option value="sent" <?php echo $statut === 'sent' ? 'selected' : ''; ?>>Envoyés</option>
                        <option value="failed" <?php echo $statut === 'failed' ? 'selected' : ''; ?>>Échoués</option>
                        <option value="disabled" <?php echo $statut === 'disabled' ? 'selected' : ''; ?>>Désactivés</option>
                    </select>
                </div>
                <div class="col-md-5 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="<?php echo url('modules/admin/email_logs.php'); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-redo"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des logs -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list"></i> Historique d'envoi
                <span class="badge bg-secondary ms-2"><?php echo number_format($total); ?> email(s)</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucun email trouvé</h5>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Destinataire</th>
                                <th>Sujet</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <small><?php echo formatDateTime($log['date_envoi'], 'd/m/Y H:i'); ?></small>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($log['destinataire']); ?></code>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars(mb_strimwidth($log['sujet'], 0, 60, '...')); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = [
                                            'sent' => 'success',
                                            'failed' => 'danger',
                                            'disabled' => 'warning'
                                        ][$log['statut']] ?? 'secondary';

                                        $label = [
                                            'sent' => 'Envoyé',
                                            'failed' => 'Échoué',
                                            'disabled' => 'Désactivé'
                                        ][$log['statut']] ?? $log['statut'];
                                        ?>
                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                            <?php echo $label; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-info"
                                                onclick="viewEmailBody(<?php echo $log['id']; ?>)">
                                            <i class="fas fa-eye"></i> Voir
                                        </button>
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
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        Précédent
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        Suivant
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour afficher le contenu de l'email -->
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Contenu de l'email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="emailBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2">Chargement...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewEmailBody(emailId) {
    const modal = new bootstrap.Modal(document.getElementById('emailModal'));
    modal.show();

    fetch('<?php echo url("modules/admin/ajax/get_email_body.php"); ?>?id=' + emailId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('emailBody').innerHTML = `
                    <div class="mb-3">
                        <strong>Destinataire:</strong> ${data.email.destinataire}<br>
                        <strong>Sujet:</strong> ${data.email.sujet}<br>
                        <strong>Date:</strong> ${data.email.date_envoi}<br>
                        <strong>Statut:</strong> <span class="badge bg-${data.email.statut === 'sent' ? 'success' : data.email.statut === 'failed' ? 'danger' : 'warning'}">${data.email.statut}</span>
                    </div>
                    <hr>
                    <div style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
                        ${data.email.corps}
                    </div>
                `;
            } else {
                document.getElementById('emailBody').innerHTML = '<div class="alert alert-danger">Erreur de chargement</div>';
            }
        })
        .catch(error => {
            document.getElementById('emailBody').innerHTML = '<div class="alert alert-danger">Erreur de chargement</div>';
        });
}
</script>

<?php require_once '../../includes/footer.php'; ?>

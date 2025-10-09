<?php
// Suivi de dossier - Registre Public
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$page_title = 'Suivi de Dossier';

$numero = sanitize($_GET['numero'] ?? '');
$dossier = null;
$historique = [];

if ($numero) {
    // Récupérer le dossier
    $sql = "SELECT d.*, DATE_FORMAT(d.date_creation, '%d/%m/%Y à %H:%i') as date_creation_format
            FROM dossiers d
            WHERE d.numero = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$numero]);
    $dossier = $stmt->fetch();

    if ($dossier) {
        // Récupérer l'historique
        $sql_hist = "SELECT h.*,
                     DATE_FORMAT(h.date_action, '%d/%m/%Y à %H:%i') as date_action_format,
                     u.nom, u.prenom
                     FROM historique h
                     LEFT JOIN users u ON h.user_id = u.id
                     WHERE h.dossier_id = ?
                     ORDER BY h.date_action ASC";

        $stmt_hist = $pdo->prepare($sql_hist);
        $stmt_hist->execute([$dossier['id']]);
        $historique = $stmt_hist->fetchAll();
    }
}

// Workflow complet (11 étapes)
$workflow_steps = [
    'brouillon' => ['label' => 'Brouillon', 'icon' => 'fa-file', 'order' => 0],
    'cree' => ['label' => 'Dossier créé', 'icon' => 'fa-folder-plus', 'order' => 1],
    'en_cours' => ['label' => 'Note de frais générée', 'icon' => 'fa-receipt', 'order' => 2],
    'note_transmise' => ['label' => 'Note transmise', 'icon' => 'fa-paper-plane', 'order' => 3],
    'paye' => ['label' => 'Paiement effectué', 'icon' => 'fa-money-bill-wave', 'order' => 4],
    'en_huitaine' => ['label' => 'En huitaine (irrégularités)', 'icon' => 'fa-exclamation-triangle', 'order' => 4],
    'analyse_daj' => ['label' => 'Analyse juridique', 'icon' => 'fa-balance-scale', 'order' => 5],
    'inspecte' => ['label' => 'Inspection effectuée', 'icon' => 'fa-clipboard-check', 'order' => 6],
    'validation_commission' => ['label' => 'Validé par la commission', 'icon' => 'fa-check-double', 'order' => 7],
    'visa_chef_service' => ['label' => 'Visa Chef de Service', 'icon' => 'fa-stamp', 'order' => 8],
    'visa_sous_directeur' => ['label' => 'Visa Sous-Directeur', 'icon' => 'fa-stamp', 'order' => 9],
    'visa_directeur' => ['label' => 'Visa Directeur', 'icon' => 'fa-stamp', 'order' => 10],
    'decide' => ['label' => 'Décision prise', 'icon' => 'fa-gavel', 'order' => 11],
    'autorise' => ['label' => 'AUTORISÉE', 'icon' => 'fa-check-circle', 'order' => 12],
    'rejete' => ['label' => 'REJETÉE', 'icon' => 'fa-times-circle', 'order' => 12],
    'ferme' => ['label' => 'Infrastructure fermée', 'icon' => 'fa-ban', 'order' => 13]
];

function getStatutColor($statut) {
    $colors = [
        'brouillon' => 'secondary',
        'cree' => 'info',
        'en_cours' => 'info',
        'note_transmise' => 'primary',
        'paye' => 'primary',
        'en_huitaine' => 'warning',
        'analyse_daj' => 'primary',
        'inspecte' => 'primary',
        'validation_commission' => 'primary',
        'visa_chef_service' => 'primary',
        'visa_sous_directeur' => 'primary',
        'visa_directeur' => 'primary',
        'decide' => 'info',
        'autorise' => 'success',
        'rejete' => 'danger',
        'ferme' => 'dark'
    ];
    return $colors[$statut] ?? 'secondary';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - MINEE/DPPG</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/registre_public.css" rel="stylesheet">

    <style>
        body {
            padding: 2rem 0;
        }

        .tracking-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.2);
        }

        .search-box {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.1);
            margin-bottom: 2rem;
            border: 1px solid #e5e7eb;
        }

        .timeline {
            position: relative;
            padding: 2rem 0;
        }

        .timeline-item {
            position: relative;
            padding: 1rem 0 1rem 3rem;
            margin-bottom: 1rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }

        .timeline-item:last-child::before {
            display: none;
        }

        .timeline-icon {
            position: absolute;
            left: 0;
            top: 1rem;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            z-index: 1;
        }

        .timeline-content {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="tracking-header text-center">
            <h1 class="mb-2"><i class="fas fa-search-location"></i> Suivi de Dossier</h1>
            <p class="mb-0">Suivez l'évolution de votre dossier d'implantation</p>
        </div>

        <!-- Formulaire de recherche -->
        <div class="search-box">
            <form method="GET" action="">
                <div class="row align-items-end">
                    <div class="col-md-9">
                        <label class="form-label fw-bold">Numéro de dossier</label>
                        <input type="text"
                               class="form-control form-control-lg"
                               name="numero"
                               placeholder="Ex: SS2025092201"
                               value="<?php echo htmlspecialchars($numero); ?>"
                               required>
                        <small class="text-muted">Entrez le numéro de référence de votre dossier</small>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($numero && !$dossier): ?>
            <!-- Dossier non trouvé -->
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle"></i> Dossier introuvable</h5>
                <p class="mb-0">Aucun dossier ne correspond au numéro <strong><?php echo htmlspecialchars($numero); ?></strong>.</p>
                <p class="mb-0 mt-2"><small>Vérifiez que vous avez bien saisi le numéro complet (ex: SS2025092201).</small></p>
            </div>
        <?php elseif ($dossier): ?>
            <!-- Informations du dossier -->
            <div class="info-card">
                <h4 class="mb-3"><i class="fas fa-folder-open text-primary"></i> Informations du dossier</h4>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Numéro :</strong> <?php echo htmlspecialchars($dossier['numero']); ?></p>
                        <p class="mb-2"><strong>Type :</strong> <?php echo htmlspecialchars($dossier['type_infrastructure']); ?></p>
                        <p class="mb-2"><strong>Localisation :</strong> <?php echo htmlspecialchars($dossier['ville'] ?? '-'); ?>, <?php echo htmlspecialchars($dossier['region'] ?? '-'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-2"><strong>Date de création :</strong> <?php echo $dossier['date_creation_format']; ?></p>
                        <p class="mb-2"><strong>Statut actuel :</strong></p>
                        <span class="badge bg-<?php echo getStatutColor($dossier['statut']); ?> fs-5">
                            <i class="fas <?php echo $workflow_steps[$dossier['statut']]['icon'] ?? 'fa-circle'; ?>"></i>
                            <?php echo $workflow_steps[$dossier['statut']]['label'] ?? $dossier['statut']; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Historique / Timeline -->
            <div class="info-card">
                <h4 class="mb-4"><i class="fas fa-history text-primary"></i> Historique du dossier</h4>

                <?php if (empty($historique)): ?>
                    <p class="text-muted">Aucun historique disponible.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($historique as $entry): ?>
                            <?php $is_current = ($entry['nouveau_statut'] === $dossier['statut']); ?>
                            <div class="timeline-item">
                                <div class="timeline-icon bg-<?php echo getStatutColor($entry['nouveau_statut']); ?> text-white <?php echo $is_current ? 'current-status' : ''; ?>">
                                    <i class="fas <?php echo $workflow_steps[$entry['nouveau_statut']]['icon'] ?? 'fa-circle'; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <span class="badge bg-<?php echo getStatutColor($entry['nouveau_statut']); ?>">
                                                    <?php echo $workflow_steps[$entry['nouveau_statut']]['label'] ?? $entry['nouveau_statut']; ?>
                                                </span>
                                                <?php if ($is_current): ?>
                                                    <span class="badge bg-success ms-2"><i class="fas fa-arrow-left"></i> Étape actuelle</span>
                                                <?php endif; ?>
                                            </h6>
                                            <?php if ($entry['commentaire']): ?>
                                                <p class="mb-1 text-muted small"><?php echo nl2br(htmlspecialchars($entry['commentaire'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?php echo $entry['date_action_format']; ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Retour -->
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Retour au registre public
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

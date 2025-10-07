<?php
// Analyse DAJ pour un dossier - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';
require_once '../daj/functions.php';

requireLogin();

// Vérifier les permissions (seulement cadre DAJ)
if ($_SESSION['user_role'] !== 'cadre_daj') {
    redirect(url('dashboard.php'), 'Accès non autorisé - Seuls les cadres DAJ peuvent analyser les dossiers', 'error');
}

$dossier_id = $_GET['id'] ?? null;

if (!$dossier_id || !is_numeric($dossier_id)) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

// Récupérer le dossier
$dossier = getDossierById($dossier_id);

if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non trouvé', 'error');
}

// Vérifier que l'utilisateur est membre de la commission pour ce dossier
if (!isMembreCommission($dossier_id, $_SESSION['user_id'], $_SESSION['user_role'])) {
    redirect(url('modules/dossiers/list.php'), 'Vous n\'êtes pas membre de la commission pour ce dossier', 'error');
}

// Vérifier que le dossier peut être analysé
if (!in_array($dossier['statut'], ['paye', 'en_cours'])) {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
             'Ce dossier ne peut plus être analysé (statut: ' . getStatutLabel($dossier['statut']) . ')', 'error');
}

// Récupérer l'analyse existante s'il y en a une
$analyse_existante = getAnalyseDAJ($dossier_id);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $statut_analyse = sanitize($_POST['statut_analyse'] ?? '');
        $observations = sanitize($_POST['observations'] ?? '');
        $documents_manquants = sanitize($_POST['documents_manquants'] ?? '');
        $recommandations = sanitize($_POST['recommandations'] ?? '');

        // Validation
        if (empty($statut_analyse)) {
            $errors[] = 'Le statut de l\'analyse est requis';
        }

        if (empty($observations)) {
            $errors[] = 'Les observations sont requises';
        }

        if (empty($errors)) {
            try {
                if ($analyse_existante) {
                    // Mettre à jour l'analyse existante
                    $sql = "UPDATE analyses_daj
                            SET statut_analyse = ?, observations = ?, documents_manquants = ?,
                                recommandations = ?, date_finalisation = CURRENT_TIMESTAMP
                            WHERE dossier_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute([
                        $statut_analyse, $observations, $documents_manquants,
                        $recommandations, $dossier_id
                    ]);
                } else {
                    // Créer une nouvelle analyse
                    $sql = "INSERT INTO analyses_daj
                            (dossier_id, daj_user_id, statut_analyse, observations,
                             documents_manquants, recommandations, date_finalisation)
                            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute([
                        $dossier_id, $_SESSION['user_id'], $statut_analyse,
                        $observations, $documents_manquants, $recommandations
                    ]);
                }

                if ($result) {
                    // Ajouter une entrée dans l'historique
                    addHistoriqueDossier($dossier_id, $_SESSION['user_id'], 'analyse_daj',
                        'Analyse DAJ réalisée - Statut: ' . $statut_analyse,
                        $dossier['statut'], $dossier['statut']);

                    // Récupérer les membres de la commission pour notification
                    $stmt_commission = $pdo->prepare("
                        SELECT chef_commission_id, cadre_dppg_id
                        FROM commissions
                        WHERE dossier_id = ?
                    ");
                    $stmt_commission->execute([$dossier_id]);
                    $commission = $stmt_commission->fetch();

                    if ($commission) {
                        // Récupérer aussi le directeur
                        $stmt_directeur = $pdo->prepare("
                            SELECT id, email, nom, prenom
                            FROM users
                            WHERE role = 'directeur' AND actif = 1
                            LIMIT 1
                        ");
                        $stmt_directeur->execute();
                        $directeur = $stmt_directeur->fetch();

                        // Récupérer les emails des membres de la commission et du directeur
                        $membres_ids = array_filter([
                            $commission['chef_commission_id'],
                            $commission['cadre_dppg_id'],
                            $directeur['id'] ?? null
                        ]);

                        if (!empty($membres_ids)) {
                            $placeholders = implode(',', array_fill(0, count($membres_ids), '?'));
                            $stmt_emails = $pdo->prepare("
                                SELECT id, email, nom, prenom, role
                                FROM users
                                WHERE id IN ($placeholders)
                            ");
                            $stmt_emails->execute($membres_ids);
                            $membres = $stmt_emails->fetchAll();

                            // Envoyer notification par email à chaque membre
                            foreach ($membres as $membre) {
                                if (!empty($membre['email'])) {
                                    $subject = "Analyse DAJ complétée - Dossier " . $dossier['numero'];
                                    $message = "Bonjour " . $membre['prenom'] . " " . $membre['nom'] . ",\n\n";
                                    $message .= "L'analyse DAJ du dossier " . $dossier['numero'] . " a été complétée.\n\n";
                                    $message .= "Demandeur: " . $dossier['nom_demandeur'] . "\n";
                                    $message .= "Statut de l'analyse: " . ucfirst(str_replace('_', ' ', $statut_analyse)) . "\n\n";

                                    if (!empty($observations)) {
                                        $message .= "Observations:\n" . substr($observations, 0, 200) . (strlen($observations) > 200 ? '...' : '') . "\n\n";
                                    }

                                    $message .= "Consultez le dossier pour plus de détails.\n\n";
                                    $message .= "Cordialement,\nSystème SGDI";

                                    mail($membre['email'], $subject, $message, "From: noreply@sgdi.cm");
                                }
                            }
                        }
                    }

                    $success = true;
                    // Recharger l'analyse
                    $analyse_existante = getAnalyseDAJ($dossier_id);
                } else {
                    $errors[] = 'Erreur lors de l\'enregistrement de l\'analyse';
                }
            } catch (Exception $e) {
                $errors[] = 'Erreur : ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Analyse DAJ - Dossier ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-search"></i>
                        <?php echo $page_title; ?>
                    </h1>
                    <p class="text-muted">Analyse juridique et administrative du dossier</p>
                </div>
                <div>
                    <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Retour au dossier
                    </a>
                </div>
            </div>

            <!-- Informations du dossier -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-folder"></i>
                        Informations du dossier
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Numéro :</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['numero']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Type :</strong></td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $dossier['type_infrastructure']))); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Demandeur :</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['nom_demandeur']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Statut :</strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo getStatutClass($dossier['statut']); ?>">
                                            <?php echo getStatutLabel($dossier['statut']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Date création :</strong></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($dossier['date_creation'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Localisation :</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['region'] . ', ' . $dossier['ville']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alertes -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h6><i class="fas fa-exclamation-triangle"></i> Erreurs détectées :</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <strong>Analyse enregistrée avec succès !</strong>
                </div>
            <?php endif; ?>

            <!-- Formulaire d'analyse -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-edit"></i>
                        Analyse DAJ
                        <?php if ($analyse_existante): ?>
                            <span class="badge bg-info">Analyse existante - Modification</span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <!-- Statut de l'analyse -->
                        <div class="mb-4">
                            <label for="statut_analyse" class="form-label">
                                <strong>Statut de l'analyse <span class="text-danger">*</span></strong>
                            </label>
                            <select class="form-select" id="statut_analyse" name="statut_analyse" required>
                                <option value="">-- Sélectionner le statut --</option>
                                <option value="en_cours" <?php echo ($analyse_existante && $analyse_existante['statut_analyse'] === 'en_cours') ? 'selected' : ''; ?>>
                                    En cours d'analyse
                                </option>
                                <option value="conforme" <?php echo ($analyse_existante && $analyse_existante['statut_analyse'] === 'conforme') ? 'selected' : ''; ?>>
                                    Conforme - Dossier régulier
                                </option>
                                <option value="conforme_avec_reserves" <?php echo ($analyse_existante && $analyse_existante['statut_analyse'] === 'conforme_avec_reserves') ? 'selected' : ''; ?>>
                                    Conforme avec réserves
                                </option>
                                <option value="non_conforme" <?php echo ($analyse_existante && $analyse_existante['statut_analyse'] === 'non_conforme') ? 'selected' : ''; ?>>
                                    Non conforme - Dossier irrégulier
                                </option>
                            </select>
                        </div>

                        <!-- Observations -->
                        <div class="mb-4">
                            <label for="observations" class="form-label">
                                <strong>Observations et constats <span class="text-danger">*</span></strong>
                            </label>
                            <textarea class="form-control" id="observations" name="observations" rows="6" required
                                      placeholder="Détaillez votre analyse juridique et administrative..."><?php echo htmlspecialchars($analyse_existante['observations'] ?? ''); ?></textarea>
                        </div>

                        <!-- Documents manquants -->
                        <div class="mb-4">
                            <label for="documents_manquants" class="form-label">
                                <strong>Documents manquants ou à compléter</strong>
                            </label>
                            <textarea class="form-control" id="documents_manquants" name="documents_manquants" rows="4"
                                      placeholder="Listez les documents manquants ou à compléter..."><?php echo htmlspecialchars($analyse_existante['documents_manquants'] ?? ''); ?></textarea>
                        </div>

                        <!-- Recommandations -->
                        <div class="mb-4">
                            <label for="recommandations" class="form-label">
                                <strong>Recommandations</strong>
                            </label>
                            <textarea class="form-control" id="recommandations" name="recommandations" rows="4"
                                      placeholder="Vos recommandations pour la suite du traitement..."><?php echo htmlspecialchars($analyse_existante['recommandations'] ?? ''); ?></textarea>
                        </div>

                        <!-- Boutons -->
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                <?php echo $analyse_existante ? 'Mettre à jour l\'analyse' : 'Enregistrer l\'analyse'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Analyse existante (affichage) -->
            <?php if ($analyse_existante): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history"></i>
                        Historique de l'analyse
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Date d'analyse :</strong> <?php echo date('d/m/Y H:i', strtotime($analyse_existante['date_analyse'])); ?></p>
                            <p><strong>Dernière mise à jour :</strong>
                                <?php if ($analyse_existante['date_finalisation']): ?>
                                    <?php echo date('d/m/Y H:i', strtotime($analyse_existante['date_finalisation'])); ?>
                                <?php else: ?>
                                    <em>En cours</em>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Statut actuel :</strong>
                                <span class="badge bg-<?php
                                    echo $analyse_existante['statut_analyse'] === 'conforme' ? 'success' :
                                        ($analyse_existante['statut_analyse'] === 'non_conforme' ? 'danger' : 'warning');
                                ?>">
                                    <?php
                                    $labels = [
                                        'en_cours' => 'En cours',
                                        'conforme' => 'Conforme',
                                        'conforme_avec_reserves' => 'Conforme avec réserves',
                                        'non_conforme' => 'Non conforme'
                                    ];
                                    echo $labels[$analyse_existante['statut_analyse']] ?? $analyse_existante['statut_analyse'];
                                    ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
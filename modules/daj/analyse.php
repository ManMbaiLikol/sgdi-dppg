<?php
// Interface d'analyse juridique DAJ - SGDI MVP
require_once '../../includes/auth.php';
require_once '../dossiers/functions.php';
require_once 'functions.php';

requireLogin();

// Vérifier que l'utilisateur est bien un cadre DAJ
if ($_SESSION['user_role'] !== 'cadre_daj') {
    redirect(url('dashboard.php'), 'Accès non autorisé', 'error');
}

$dossier_id = $_GET['id'] ?? null;

if (!$dossier_id) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

// Récupérer les détails du dossier
$dossier = getDossierById($dossier_id);

if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non trouvé', 'error');
}

// Vérifier s'il y a déjà une analyse DAJ
$analyse_existante = getAnalyseDAJ($dossier_id);

// Traitement du formulaire d'analyse
if ($_POST) {
    $statut_analyse = $_POST['statut_analyse'];
    $observations = $_POST['observations'];
    $documents_manquants = $_POST['documents_manquants'];
    $recommandations = $_POST['recommandations'];

    if (enregistrerAnalyseDAJ($dossier_id, $_SESSION['user_id'], $statut_analyse, $observations, $documents_manquants, $recommandations)) {
        // Mettre à jour le statut du dossier si l'analyse est terminée
        if ($statut_analyse !== 'en_cours') {
            updateStatutDossier($dossier_id, 'analyse_daj', 'Analyse juridique DAJ terminée');
        }

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

        redirect(url("modules/daj/analyse.php?id=$dossier_id"), 'Analyse enregistrée avec succès', 'success');
    } else {
        $error_message = 'Erreur lors de l\'enregistrement de l\'analyse';
    }
}

$page_title = 'Analyse Juridique DAJ - Dossier ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-gavel"></i>
                    Analyse Juridique et Réglementaire
                </h1>
                <a href="<?php echo url('modules/dossiers/list.php?statut=paye'); ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Retour à la liste
                </a>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Informations du dossier -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-folder"></i>
                                Informations du Dossier
                            </h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Numéro:</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['numero']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Type:</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['type_infrastructure']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Demandeur:</strong></td>
                                    <td><?php echo htmlspecialchars($dossier['nom_demandeur']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Localisation:</strong></td>
                                    <td>
                                        <?php
                                        // Ordre hiérarchique camerounais : Région → Arrondissement → Ville → Quartier
                                        $localisation = [];
                                        if ($dossier['region']) $localisation[] = $dossier['region'];
                                        if ($dossier['arrondissement']) $localisation[] = $dossier['arrondissement'];
                                        if ($dossier['ville']) $localisation[] = $dossier['ville'];
                                        if ($dossier['quartier']) $localisation[] = $dossier['quartier'];
                                        echo htmlspecialchars(implode(' → ', $localisation));
                                        ?>
                                        <?php if ($dossier['lieu_dit']): ?>
                                        <br><small class="text-muted"><strong>Lieu-dit:</strong> <?php echo htmlspecialchars($dossier['lieu_dit']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Date création:</strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($dossier['date_creation'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Statut:</strong></td>
                                    <td>
                                        <span class="badge badge-success">Payé</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Documents du dossier -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-file-alt"></i>
                                Documents Soumis
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $documents = getDocumentsDossier($dossier_id);
                            if ($documents): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($documents as $doc): ?>
                                        <div class="list-group-item px-0">
                                            <small class="text-muted"><?php echo htmlspecialchars($doc['type_document']); ?></small><br>
                                            <a href="<?php echo url('modules/documents/view.php?id=' . $doc['id']); ?>"
                                               class="text-decoration-none" target="_blank">
                                                <i class="fas fa-file-pdf"></i>
                                                <?php echo htmlspecialchars($doc['nom_fichier']); ?>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">Aucun document trouvé</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Formulaire d'analyse DAJ -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-balance-scale"></i>
                                Analyse Juridique et Réglementaire
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="statut_analyse">Statut de l'analyse *</label>
                                            <select name="statut_analyse" id="statut_analyse" class="form-control" required>
                                                <option value="en_cours" <?php echo ($analyse_existante && $analyse_existante['statut_analyse'] == 'en_cours') ? 'selected' : ''; ?>>
                                                    En cours d'analyse
                                                </option>
                                                <option value="conforme" <?php echo ($analyse_existante && $analyse_existante['statut_analyse'] == 'conforme') ? 'selected' : ''; ?>>
                                                    Conforme - Validé
                                                </option>
                                                <option value="conforme_avec_reserves" <?php echo ($analyse_existante && $analyse_existante['statut_analyse'] == 'conforme_avec_reserves') ? 'selected' : ''; ?>>
                                                    Conforme avec réserves
                                                </option>
                                                <option value="non_conforme" <?php echo ($analyse_existante && $analyse_existante['statut_analyse'] == 'non_conforme') ? 'selected' : ''; ?>>
                                                    Non conforme - Rejet
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="observations">Observations juridiques</label>
                                    <textarea name="observations" id="observations" class="form-control" rows="4"
                                              placeholder="Analyse détaillée de la conformité réglementaire..."><?php echo $analyse_existante ? htmlspecialchars($analyse_existante['observations']) : ''; ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="documents_manquants">Documents manquants ou non conformes</label>
                                    <textarea name="documents_manquants" id="documents_manquants" class="form-control" rows="3"
                                              placeholder="Liste des documents à régulariser..."><?php echo $analyse_existante ? htmlspecialchars($analyse_existante['documents_manquants']) : ''; ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="recommandations">Recommandations</label>
                                    <textarea name="recommandations" id="recommandations" class="form-control" rows="3"
                                              placeholder="Actions à entreprendre..."><?php echo $analyse_existante ? htmlspecialchars($analyse_existante['recommandations']) : ''; ?></textarea>
                                </div>

                                <div class="form-group text-right">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i>
                                        Enregistrer l'analyse
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($analyse_existante): ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-history"></i>
                                Historique de l'analyse
                            </h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Analysé par:</strong> <?php echo htmlspecialchars($analyse_existante['analyste']); ?></p>
                            <p><strong>Date d'analyse:</strong> <?php echo date('d/m/Y H:i', strtotime($analyse_existante['date_analyse'])); ?></p>
                            <?php if ($analyse_existante['date_finalisation']): ?>
                                <p><strong>Date de finalisation:</strong> <?php echo date('d/m/Y H:i', strtotime($analyse_existante['date_finalisation'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
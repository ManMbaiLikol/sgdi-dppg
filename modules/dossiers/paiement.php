<?php
// Enregistrement de paiement - SGDI MVP (Étape 4)
require_once '../../includes/auth.php';
require_once 'functions.php';
require_once '../notes_frais/functions.php';

requireRole('billeteur');

$dossier_id = intval($_GET['id'] ?? 0);
if (!$dossier_id) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

$dossier = getDossierById($dossier_id);
if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier introuvable', 'error');
}

if ($dossier['statut'] !== 'en_cours') {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
            'Ce dossier n\'est pas au stade d\'enregistrement de paiement', 'error');
}

// Vérifier si un paiement n'existe pas déjà
$sql = "SELECT * FROM paiements WHERE dossier_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$dossier_id]);
$paiement_existant = $stmt->fetch();

if ($paiement_existant) {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
            'Un paiement est déjà enregistré pour ce dossier', 'warning');
}

// Récupérer la note de frais du dossier
$note_frais = getNoteFreaisParDossier($dossier_id);
if (!$note_frais) {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
            'Aucune note de frais trouvée pour ce dossier. Veuillez d\'abord créer la note de frais.', 'error');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $montant = floatval($_POST['montant'] ?? 0);
        $devise = cleanInput($_POST['devise'] ?? 'FCFA');
        $mode_paiement = cleanInput($_POST['mode_paiement'] ?? '');
        $reference_paiement = cleanInput($_POST['reference_paiement'] ?? '');
        $date_paiement = cleanInput($_POST['date_paiement'] ?? '');
        $observations = cleanInput($_POST['observations'] ?? '');

        // Validations
        if ($montant <= 0) {
            $errors[] = 'Le montant doit être supérieur à zéro';
        }

        // Validation du montant avec la note de frais
        if ($montant != $note_frais['montant_total']) {
            $errors[] = sprintf(
                'Le montant payé (%s FCFA) doit correspondre exactement au montant de la note de frais (%s FCFA)',
                number_format($montant, 0, ',', ' '),
                number_format($note_frais['montant_total'], 0, ',', ' ')
            );
        }

        if (empty($mode_paiement)) {
            $errors[] = 'Mode de paiement requis';
        }

        if (empty($date_paiement)) {
            $errors[] = 'Date de paiement requise';
        } else {
            // Vérifier que la date n'est pas dans le futur
            if (strtotime($date_paiement) > time()) {
                $errors[] = 'La date de paiement ne peut pas être dans le futur';
            }
        }

        if ($mode_paiement === 'cheque' && empty($reference_paiement)) {
            $errors[] = 'Numéro de chèque requis';
        }

        if ($mode_paiement === 'virement' && empty($reference_paiement)) {
            $errors[] = 'Référence de virement requise';
        }

        if (empty($errors)) {
            $transaction_started = false;
            try {
                $pdo->beginTransaction();
                $transaction_started = true;

                // Enregistrer le paiement
                $sql = "INSERT INTO paiements (dossier_id, montant, devise, mode_paiement,
                               reference_paiement, date_paiement, billeteur_id, observations)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $dossier_id,
                    $montant,
                    $devise,
                    $mode_paiement,
                    $reference_paiement ?: null,
                    $date_paiement,
                    $_SESSION['user_id'],
                    $observations ?: null
                ]);

                if ($result) {
                    // Changer le statut du dossier (sans démarrer de nouvelle transaction)
                    $statut_change = changerStatutDossier($dossier_id, 'paye', $_SESSION['user_id'],
                                       sprintf('Paiement enregistré: %s %s via %s',
                                               number_format($montant, 0, ',', ' '), $devise, $mode_paiement), false);

                    if (!$statut_change) {
                        throw new Exception('Erreur lors du changement de statut du dossier');
                    }

                    // Récupérer les membres de la commission pour notification
                    $stmt_commission = $pdo->prepare("
                        SELECT chef_commission_id, cadre_dppg_id, cadre_daj_id
                        FROM commissions
                        WHERE dossier_id = ?
                    ");
                    $stmt_commission->execute([$dossier_id]);
                    $commission = $stmt_commission->fetch();

                    if ($commission) {
                        // Récupérer les emails des membres
                        $membres_ids = array_filter([
                            $commission['chef_commission_id'],
                            $commission['cadre_dppg_id'],
                            $commission['cadre_daj_id']
                        ]);

                        if (!empty($membres_ids)) {
                            $placeholders = implode(',', array_fill(0, count($membres_ids), '?'));
                            $stmt_emails = $pdo->prepare("
                                SELECT id, email, nom, prenom
                                FROM users
                                WHERE id IN ($placeholders)
                            ");
                            $stmt_emails->execute($membres_ids);
                            $membres = $stmt_emails->fetchAll();

                            // Envoyer notification par email à chaque membre
                            foreach ($membres as $membre) {
                                if (!empty($membre['email'])) {
                                    $subject = "Paiement enregistré - Dossier " . $dossier['numero'];
                                    $message = "Bonjour " . $membre['prenom'] . " " . $membre['nom'] . ",\n\n";
                                    $message .= "Le paiement du dossier " . $dossier['numero'] . " a été enregistré.\n\n";
                                    $message .= "Demandeur: " . $dossier['nom_demandeur'] . "\n";
                                    $message .= "Montant: " . number_format($montant, 0, ',', ' ') . " " . $devise . "\n";
                                    $message .= "Mode: " . ucfirst($mode_paiement) . "\n\n";
                                    $message .= "Le dossier est maintenant disponible pour inspection.\n\n";
                                    $message .= "Cordialement,\nSystème SGDI";

                                    mail($membre['email'], $subject, $message, "From: noreply@sgdi.cm");
                                }
                            }
                        }
                    }

                    $pdo->commit();
                    $transaction_started = false; // Transaction terminée
                    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
                            'Paiement enregistré avec succès. Les membres de la commission ont été notifiés.', 'success');
                } else {
                    throw new Exception('Erreur lors de l\'enregistrement du paiement');
                }

            } catch (Exception $e) {
                // Ne faire rollback que si une transaction est active
                if ($transaction_started && $pdo->inTransaction()) {
                    $pdo->rollback();
                }
                $errors[] = 'Erreur: ' . $e->getMessage();
            }
        }
    }
}

// Le montant est maintenant celui de la note de frais
$montant_requis = $note_frais['montant_total'];

$page_title = 'Enregistrement paiement - Dossier ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-money-bill"></i> Enregistrement du paiement
                </h5>
                <p class="mb-0">
                    Dossier: <strong><?php echo sanitize($dossier['numero']); ?></strong> -
                    <?php echo sanitize($dossier['nom_demandeur']); ?>
                </p>
            </div>

            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="alert alert-info">
                    <h6><i class="fas fa-receipt"></i> Note de frais du dossier</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Type d'infrastructure:</strong><br>
                            <?php echo getTypeLabel($dossier['type_infrastructure'], $dossier['sous_type']); ?></p>

                            <p class="mb-1"><strong>Montant de base:</strong><br>
                            <?php echo number_format($note_frais['montant_base'], 0, ',', ' '); ?> FCFA</p>

                            <p class="mb-0"><strong>Frais de déplacement:</strong><br>
                            <?php echo number_format($note_frais['montant_frais_deplacement'], 0, ',', ' '); ?> FCFA</p>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-success text-white p-3 rounded">
                                <h5 class="mb-1"><i class="fas fa-calculator"></i> Total à payer</h5>
                                <h3 class="mb-0"><?php echo number_format($note_frais['montant_total'], 0, ',', ' '); ?> FCFA</h3>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($note_frais['description'])): ?>
                    <hr>
                    <p class="mb-1"><strong>Description:</strong> <?php echo sanitize($note_frais['description']); ?></p>
                    <?php endif; ?>
                    <div class="text-end">
                        <a href="<?php echo url('modules/notes_frais/view.php?id=' . $note_frais['id']); ?>"
                           class="btn btn-outline-info btn-sm" target="_blank">
                            <i class="fas fa-external-link-alt"></i> Voir le détail de la note de frais
                        </a>
                    </div>
                </div>

                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle"></i> Important</h6>
                    <p class="mb-0">
                        Le montant payé doit correspondre <strong>exactement</strong> au montant total de la note de frais.
                        Aucun montant différent ne sera accepté.
                    </p>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="montant" class="form-label">Montant payé * <small class="text-success">(doit être exactement <?php echo number_format($montant_requis, 0, ',', ' '); ?> FCFA)</small></label>
                            <input type="number" class="form-control" id="montant" name="montant" step="0.01" min="0"
                                   value="<?php echo htmlspecialchars($_POST['montant'] ?? $montant_requis, ENT_QUOTES, 'UTF-8'); ?>"
                                   data-required-amount="<?php echo htmlspecialchars($montant_requis, ENT_QUOTES, 'UTF-8'); ?>"
                                   required>
                            <div class="form-text">
                                <span id="montant-status" class="fw-bold"></span>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="devise" class="form-label">Devise</label>
                            <select class="form-select" id="devise" name="devise">
                                <option value="FCFA" <?php echo (htmlspecialchars($_POST['devise'] ?? 'FCFA', ENT_QUOTES, 'UTF-8') === 'FCFA') ? 'selected' : ''; ?>>
                                    FCFA
                                </option>
                                <option value="EUR" <?php echo (htmlspecialchars($_POST['devise'] ?? '', ENT_QUOTES, 'UTF-8') === 'EUR') ? 'selected' : ''; ?>>
                                    EUR
                                </option>
                                <option value="USD" <?php echo (htmlspecialchars($_POST['devise'] ?? '', ENT_QUOTES, 'UTF-8') === 'USD') ? 'selected' : ''; ?>>
                                    USD
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="mode_paiement" class="form-label">Mode de paiement *</label>
                            <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                                <option value="">Sélectionner</option>
                                <option value="especes" <?php echo (htmlspecialchars($_POST['mode_paiement'] ?? '', ENT_QUOTES, 'UTF-8') === 'especes') ? 'selected' : ''; ?>>
                                    Espèces
                                </option>
                                <option value="cheque" <?php echo (htmlspecialchars($_POST['mode_paiement'] ?? '', ENT_QUOTES, 'UTF-8') === 'cheque') ? 'selected' : ''; ?>>
                                    Chèque
                                </option>
                                <option value="virement" <?php echo (htmlspecialchars($_POST['mode_paiement'] ?? '', ENT_QUOTES, 'UTF-8') === 'virement') ? 'selected' : ''; ?>>
                                    Virement bancaire
                                </option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="date_paiement" class="form-label">Date du paiement *</label>
                            <input type="date" class="form-control" id="date_paiement" name="date_paiement"
                                   value="<?php echo htmlspecialchars($_POST['date_paiement'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"
                                   max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3" id="reference_group">
                        <label for="reference_paiement" class="form-label">Référence de paiement</label>
                        <input type="text" class="form-control" id="reference_paiement" name="reference_paiement"
                               value="<?php echo sanitize($_POST['reference_paiement'] ?? ''); ?>"
                               placeholder="N° chèque, référence virement, etc.">
                        <div class="form-text" id="reference_help"></div>
                    </div>

                    <div class="mb-4">
                        <label for="observations" class="form-label">Observations</label>
                        <textarea class="form-control" id="observations" name="observations" rows="3"
                                  placeholder="Commentaires ou remarques sur ce paiement"><?php echo sanitize($_POST['observations'] ?? ''); ?></textarea>
                    </div>

                    <div class="alert alert-success">
                        <h6><i class="fas fa-forward"></i> Après enregistrement</h6>
                        <p class="mb-0">
                            Le dossier passera automatiquement au statut <strong>"Payé"</strong>
                            et sera disponible pour inspection par le cadre DPPG assigné à la commission.
                        </p>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-money-bill"></i> Enregistrer le paiement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Gestion des champs selon le mode de paiement
document.getElementById('mode_paiement').addEventListener('change', function() {
    const mode = this.value;
    const referenceGroup = document.getElementById('reference_group');
    const referenceInput = document.getElementById('reference_paiement');
    const referenceHelp = document.getElementById('reference_help');

    switch (mode) {
        case 'especes':
            referenceInput.required = false;
            referenceInput.placeholder = 'Optionnel pour les espèces';
            referenceHelp.textContent = 'Vous pouvez indiquer un numéro de reçu si disponible';
            break;

        case 'cheque':
            referenceInput.required = true;
            referenceInput.placeholder = 'N° du chèque (obligatoire)';
            referenceHelp.textContent = 'Numéro du chèque obligatoire';
            break;

        case 'virement':
            referenceInput.required = true;
            referenceInput.placeholder = 'Référence du virement (obligatoire)';
            referenceHelp.textContent = 'Référence ou numéro du virement bancaire obligatoire';
            break;

        default:
            referenceInput.required = false;
            referenceInput.placeholder = '';
            referenceHelp.textContent = '';
    }
});

// Initialiser au chargement
document.getElementById('mode_paiement').dispatchEvent(new Event('change'));

// Valider le montant en temps réel
document.getElementById('montant').addEventListener('input', function() {
    const value = parseFloat(this.value) || 0;
    const requiredAmount = parseFloat(this.dataset.requiredAmount);
    const statusElement = document.getElementById('montant-status');
    const submitButton = document.querySelector('button[type="submit"]');

    if (value === 0) {
        statusElement.textContent = '';
        statusElement.className = 'fw-bold';
        submitButton.disabled = false;
    } else if (value === requiredAmount) {
        statusElement.textContent = '✓ Montant correct';
        statusElement.className = 'fw-bold text-success';
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
        submitButton.disabled = false;
    } else {
        const difference = value - requiredAmount;
        const diffText = difference > 0 ?
            `⚠ ${Math.abs(difference).toLocaleString('fr-FR')} FCFA de trop` :
            `⚠ ${Math.abs(difference).toLocaleString('fr-FR')} FCFA manquant`;

        statusElement.textContent = diffText;
        statusElement.className = 'fw-bold text-danger';
        this.classList.remove('is-valid');
        this.classList.add('is-invalid');
        submitButton.disabled = true;
    }

    // Afficher le montant formaté si positif
    if (value > 0) {
        const formatted = new Intl.NumberFormat('fr-FR').format(value);
        let display = document.getElementById('montant_display');
        if (!display) {
            display = document.createElement('small');
            display.id = 'montant_display';
            display.className = 'text-muted ms-2';
            this.parentNode.appendChild(display);
        }
        display.textContent = formatted + ' FCFA';
    }
});

// Initialiser l'affichage du montant
document.getElementById('montant').dispatchEvent(new Event('input'));
</script>

<?php require_once '../../includes/footer.php'; ?>
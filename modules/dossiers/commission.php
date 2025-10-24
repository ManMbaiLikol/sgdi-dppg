<?php
// Constitution de commission - SGDI MVP (Étape 2)
require_once '../../includes/auth.php';
require_once 'functions.php';

// Seul le Chef de Service SDTD peut constituer les commissions
requireRole('chef_service');

$dossier_id = intval($_GET['id'] ?? 0);
if (!$dossier_id) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

$dossier = getDossierById($dossier_id);
if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier introuvable', 'error');
}

// Vérifier si le dossier peut avoir une commission
if (!in_array($dossier['statut'], ['brouillon', 'en_cours'])) {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
            'Ce dossier n\'est pas au stade de constitution de commission', 'error');
}

// Vérifier si une commission existe déjà
$sql_check = "SELECT id FROM commissions WHERE dossier_id = ?";
$stmt_check = $pdo->prepare($sql_check);
$stmt_check->execute([$dossier_id]);
if ($stmt_check->fetch()) {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
            'Une commission a déjà été constituée pour ce dossier', 'error');
}

// Récupérer les membres disponibles
$cadres_dppg = getUsersByRole('cadre_dppg');
$cadres_daj = getUsersByRole('cadre_daj');

// Chef de commission peut être : Chef de Commission, Chef Service ou Sous-Directeur
$chefs_directeurs = array_merge(
    getUsersByRole('chef_commission'),
    getUsersByRole('chef_service'),
    getUsersByRole('sous_directeur')
);

// Le chef de service actuel peut aussi être chef de commission
// Donc on ne le retire pas de la liste

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $chef_commission_id = intval($_POST['chef_commission_id'] ?? 0);
        $chef_commission_role = cleanInput($_POST['chef_commission_role'] ?? '');
        $cadre_dppg_id = intval($_POST['cadre_dppg_id'] ?? 0);
        $cadre_daj_id = intval($_POST['cadre_daj_id'] ?? 0);

        // DEBUG: Logger les valeurs reçues
        error_log("=== DEBUG CONSTITUTION COMMISSION ===");
        error_log("chef_commission_id: " . $chef_commission_id);
        error_log("chef_commission_role RAW: '" . ($_POST['chef_commission_role'] ?? '') . "'");
        error_log("chef_commission_role CLEAN: '" . $chef_commission_role . "'");
        error_log("chef_commission_role LENGTH: " . strlen($chef_commission_role));
        error_log("cadre_dppg_id: " . $cadre_dppg_id);
        error_log("cadre_daj_id: " . $cadre_daj_id);

        // Le Chef de Service SDTD doit désigner un chef de commission
        if (!$chef_commission_id || !$chef_commission_role) {
            $errors[] = 'Sélection du chef de commission requise';
            error_log("ERREUR: chef_commission_id ou chef_commission_role manquant");
        }

        if (!$cadre_dppg_id) {
            $errors[] = 'Sélection du cadre DPPG requise';
        }

        if (!$cadre_daj_id) {
            $errors[] = 'Sélection du cadre DAJ requise';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // DEBUG: Valeurs à insérer
                error_log("=== VALEURS À INSÉRER ===");
                error_log("dossier_id: " . $dossier_id);
                error_log("chef_commission_id: " . $chef_commission_id);
                error_log("chef_commission_role: '" . $chef_commission_role . "'");
                error_log("cadre_dppg_id: " . $cadre_dppg_id);
                error_log("cadre_daj_id: " . $cadre_daj_id);

                // Vérifier que le role est valide
                $valid_roles = ['chef_service', 'chef_commission', 'sous_directeur', 'directeur'];
                if (!in_array($chef_commission_role, $valid_roles)) {
                    throw new Exception("Rôle invalide: '$chef_commission_role'. Rôles acceptés: " . implode(', ', $valid_roles));
                }

                // Créer la commission
                $sql = "INSERT INTO commissions (dossier_id, chef_commission_id, chef_commission_role, cadre_dppg_id, cadre_daj_id)
                        VALUES (?, ?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $dossier_id,
                    $chef_commission_id,
                    $chef_commission_role,
                    $cadre_dppg_id,
                    $cadre_daj_id
                ]);

                if ($result) {
                    // Changer le statut du dossier (sans transaction interne)
                    $sql = "UPDATE dossiers SET statut = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['en_cours', $dossier_id]);

                    // Note: Table historique_dossier non disponible
                    // L'historique sera géré plus tard si nécessaire

                    $pdo->commit();
                    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
                            'Commission constituée avec succès. Le dossier passe en statut "En cours"', 'success');
                } else {
                    throw new Exception('Erreur lors de la création de la commission');
                }

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollback();
                }
                $errors[] = 'Erreur lors de la constitution de la commission: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Constitution de commission - Dossier ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-users"></i> Constitution de la commission d'inspection
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
                    <h6><i class="fas fa-info-circle"></i> Constitution obligatoire</h6>
                    <p class="mb-0">
                        La commission doit comprendre <strong>3 membres obligatoires</strong>:
                    </p>
                    <ul class="mb-0 mt-2">
                        <li>Un Chef de commission (Chef de Commission, Chef de Service OU Sous-Directeur) - <strong>Président</strong></li>
                        <li>Un Cadre DPPG - <strong>Inspecteur technique</strong></li>
                        <li>Un Cadre DAJ - <strong>Analyse juridique et réglementaire</strong></li>
                    </ul>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <!-- Chef de commission -->
                    <div class="mb-4">
                        <h6 class="text-primary">
                            <i class="fas fa-user-tie"></i> Chef de la commission
                        </h6>

                        <!-- Sélection du chef de commission -->
                        <div class="mb-3">
                            <label for="chef_commission_id" class="form-label">Sélectionner le chef de commission *</label>
                            <small class="form-text text-muted">Le Chef de Service SDTD désigne qui sera le chef de cette commission d'inspection</small>

                            <?php if (empty($chefs_directeurs)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Aucun chef/directeur disponible. Veuillez contacter l'administrateur.
                            </div>
                            <?php else: ?>
                            <select class="form-select" id="chef_commission_id" name="chef_commission_id" required onchange="updateChefRole()">
                                <option value="">Choisir un chef de commission</option>
                                <?php foreach ($chefs_directeurs as $chef):
                                    // Déterminer le libellé du rôle
                                    $role_label = 'Chef de Commission';
                                    if ($chef['role'] === 'chef_service') {
                                        $role_label = 'Chef Service SDTD';
                                    } elseif ($chef['role'] === 'sous_directeur') {
                                        $role_label = 'Sous-Directeur SDTD';
                                    }
                                ?>
                                <option value="<?php echo $chef['id']; ?>" data-role="<?php echo $chef['role']; ?>"
                                        <?php echo (intval($_POST['chef_commission_id'] ?? 0) === intval($chef['id'])) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($chef['prenom'] . ' ' . $chef['nom']); ?>
                                    (<?php echo $role_label; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="chef_commission_role" id="chef_commission_role" value="<?php echo sanitize($_POST['chef_commission_role'] ?? ''); ?>">
                            <small id="role-indicator" class="form-text"></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Cadre DPPG -->
                    <div class="mb-4">
                        <h6 class="text-primary">
                            <i class="fas fa-user-hard-hat"></i> Inspecteur technique (Cadre DPPG)
                        </h6>
                        <div class="mb-3">
                            <label for="cadre_dppg_id" class="form-label">Sélectionner le cadre DPPG *</label>
                            <?php if (empty($cadres_dppg)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Aucun cadre DPPG disponible. Veuillez contacter l'administrateur.
                            </div>
                            <?php else: ?>
                            <select class="form-select" id="cadre_dppg_id" name="cadre_dppg_id" required>
                                <option value="">Choisir un cadre DPPG</option>
                                <?php foreach ($cadres_dppg as $cadre): ?>
                                <option value="<?php echo $cadre['id']; ?>"
                                        <?php echo (intval($_POST['cadre_dppg_id'] ?? 0) === intval($cadre['id'])) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($cadre['prenom'] . ' ' . $cadre['nom']); ?>
                                    <?php if ($cadre['email']): ?>
                                    - <?php echo sanitize($cadre['email']); ?>
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Cadre DAJ -->
                    <div class="mb-4">
                        <h6 class="text-primary">
                            <i class="fas fa-balance-scale"></i> Cadre DAJ (Analyse juridique)
                        </h6>
                        <div class="mb-3">
                            <label for="cadre_daj_id" class="form-label">Sélectionner le cadre DAJ *</label>
                            <?php if (empty($cadres_daj)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Aucun cadre DAJ disponible. Veuillez contacter l'administrateur.
                            </div>
                            <?php else: ?>
                            <select class="form-select" id="cadre_daj_id" name="cadre_daj_id" required>
                                <option value="">Choisir un cadre DAJ</option>
                                <?php foreach ($cadres_daj as $cadre): ?>
                                <option value="<?php echo $cadre['id']; ?>"
                                        <?php echo (intval($_POST['cadre_daj_id'] ?? 0) === intval($cadre['id'])) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($cadre['prenom'] . ' ' . $cadre['nom']); ?>
                                    <?php if ($cadre['email']): ?>
                                    - <?php echo sanitize($cadre['email']); ?>
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="alert alert-success">
                        <h6><i class="fas fa-forward"></i> Après constitution</h6>
                        <p class="mb-0">
                            Une fois la commission constituée, le dossier passera automatiquement au statut
                            <strong>"En cours"</strong> et une note de frais sera générée pour le paiement
                            des frais d'inspection.
                        </p>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                        <?php if (!empty($cadres_dppg) && !empty($cadres_daj) && ($_SESSION['user_role'] === 'chef_service' || !empty($chefs_directeurs))): ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-users"></i> Constituer la commission
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function updateChefRole() {
    const select = document.getElementById('chef_commission_id');
    const roleInput = document.getElementById('chef_commission_role');

    console.log('=== updateChefRole appelé ===');
    console.log('Select value:', select.value);

    if (select.value) {
        const selectedOption = select.options[select.selectedIndex];
        const role = selectedOption.getAttribute('data-role');
        console.log('Role récupéré:', role);
        console.log('data-role attribute:', selectedOption.getAttribute('data-role'));

        // Garder le rôle exact de l'utilisateur sélectionné
        roleInput.value = role;
        console.log('Champ chef_commission_role mis à jour:', roleInput.value);

        // Afficher un indicateur visuel
        const indicator = document.getElementById('role-indicator');
        if (indicator) {
            indicator.textContent = ' ✓ Rôle: ' + role;
            indicator.style.color = 'green';
        }
    } else {
        roleInput.value = '';
        console.log('Champ chef_commission_role vidé');

        const indicator = document.getElementById('role-indicator');
        if (indicator) {
            indicator.textContent = '';
        }
    }
}

// Validation avant soumission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const roleInput = document.getElementById('chef_commission_role');
            const selectInput = document.getElementById('chef_commission_id');

            console.log('=== SOUMISSION FORMULAIRE ===');
            console.log('chef_commission_id:', selectInput.value);
            console.log('chef_commission_role:', roleInput.value);

            if (selectInput.value && !roleInput.value) {
                e.preventDefault();
                alert('ERREUR: Le rôle du chef de commission n\'a pas été détecté.\n\nVeuillez:\n1. Recharger la page (F5)\n2. Resélectionner le chef de commission\n3. Réessayer');
                console.error('ERREUR: chef_commission_role vide alors que chef_commission_id est rempli');
                return false;
            }

            if (!roleInput.value) {
                e.preventDefault();
                alert('Veuillez sélectionner un chef de commission');
                return false;
            }

            const validRoles = ['chef_service', 'chef_commission', 'sous_directeur', 'directeur'];
            if (!validRoles.includes(roleInput.value)) {
                e.preventDefault();
                alert('ERREUR: Rôle invalide "' + roleInput.value + '".\n\nRôles acceptés: ' + validRoles.join(', '));
                console.error('Rôle invalide:', roleInput.value);
                return false;
            }

            console.log('Validation OK, soumission du formulaire...');
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
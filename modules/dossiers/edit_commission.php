<?php
// Modification de commission - SGDI MVP
require_once '../../includes/auth.php';
require_once 'functions.php';

// Seul le Chef de Service SDTD peut modifier les commissions
requireRole('chef_service');

$dossier_id = intval($_GET['id'] ?? 0);
if (!$dossier_id) {
    redirect(url('modules/dossiers/list.php'), 'Dossier non spécifié', 'error');
}

$dossier = getDossierById($dossier_id);
if (!$dossier) {
    redirect(url('modules/dossiers/list.php'), 'Dossier introuvable', 'error');
}

// Récupérer la commission existante
$sql_commission = "SELECT c.*,
                   chef.nom as chef_nom, chef.prenom as chef_prenom,
                   dppg.nom as dppg_nom, dppg.prenom as dppg_prenom,
                   daj.nom as daj_nom, daj.prenom as daj_prenom
                   FROM commissions c
                   LEFT JOIN users chef ON c.chef_commission_id = chef.id
                   LEFT JOIN users dppg ON c.cadre_dppg_id = dppg.id
                   LEFT JOIN users daj ON c.cadre_daj_id = daj.id
                   WHERE c.dossier_id = ?";
$stmt_commission = $pdo->prepare($sql_commission);
$stmt_commission->execute([$dossier_id]);
$commission = $stmt_commission->fetch();

if (!$commission) {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
            'Aucune commission n\'existe pour ce dossier. Veuillez d\'abord en constituer une.', 'error');
}

// Vérifier si le dossier permet encore la modification de commission
// On autorise la modification tant que le dossier n'est pas au stade décision finale
$statuts_bloques = ['decide', 'autorise', 'rejete', 'classe'];
if (in_array($dossier['statut'], $statuts_bloques)) {
    redirect(url('modules/dossiers/view.php?id=' . $dossier_id),
            'La commission ne peut plus être modifiée car le dossier est au stade: ' . getStatutLabel($dossier['statut']), 'error');
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

// Ajouter BANA ESSAMA Joseph (billeteur) qui peut aussi être chef de commission
$sql_bana = "SELECT id, username, email, nom, prenom, telephone, role
             FROM users
             WHERE nom = 'BANA ESSAMA' AND prenom = 'Joseph' AND actif = 1";
$stmt_bana = $pdo->prepare($sql_bana);
$stmt_bana->execute();
$bana_user = $stmt_bana->fetch();
if ($bana_user) {
    $already_in_list = false;
    foreach ($chefs_directeurs as $chef) {
        if ($chef['id'] == $bana_user['id']) {
            $already_in_list = true;
            break;
        }
    }
    if (!$already_in_list) {
        $chefs_directeurs[] = $bana_user;
    }
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide';
    } else {
        $chef_commission_id = intval($_POST['chef_commission_id'] ?? 0);
        $chef_commission_role = cleanInput($_POST['chef_commission_role'] ?? '');
        $cadre_dppg_id = intval($_POST['cadre_dppg_id'] ?? 0);
        $cadre_daj_id = intval($_POST['cadre_daj_id'] ?? 0);

        if (!$chef_commission_id || !$chef_commission_role) {
            $errors[] = 'Sélection du chef de commission requise';
        }

        if (!$cadre_dppg_id) {
            $errors[] = 'Sélection du cadre DPPG requise';
        }

        if (!$cadre_daj_id) {
            $errors[] = 'Sélection du cadre DAJ requise';
        }

        // Vérifier que le role est valide
        $valid_roles = ['chef_service', 'chef_commission', 'sous_directeur', 'directeur', 'billeteur'];
        if (!in_array($chef_commission_role, $valid_roles)) {
            $errors[] = "Rôle invalide: '$chef_commission_role'";
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Garder une trace des anciens membres pour l'historique
                $anciens_membres = [
                    'chef' => $commission['chef_prenom'] . ' ' . $commission['chef_nom'],
                    'dppg' => $commission['dppg_prenom'] . ' ' . $commission['dppg_nom'],
                    'daj' => $commission['daj_prenom'] . ' ' . $commission['daj_nom']
                ];

                // Mettre à jour la commission
                $sql = "UPDATE commissions
                        SET chef_commission_id = ?,
                            chef_commission_role = ?,
                            cadre_dppg_id = ?,
                            cadre_daj_id = ?,
                            date_modification = NOW()
                        WHERE dossier_id = ?";

                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $chef_commission_id,
                    $chef_commission_role,
                    $cadre_dppg_id,
                    $cadre_daj_id,
                    $dossier_id
                ]);

                if ($result) {
                    // Récupérer les noms des nouveaux membres
                    $sql_nouveaux = "SELECT
                        (SELECT CONCAT(prenom, ' ', nom) FROM users WHERE id = ?) as nouveau_chef,
                        (SELECT CONCAT(prenom, ' ', nom) FROM users WHERE id = ?) as nouveau_dppg,
                        (SELECT CONCAT(prenom, ' ', nom) FROM users WHERE id = ?) as nouveau_daj";
                    $stmt_nouveaux = $pdo->prepare($sql_nouveaux);
                    $stmt_nouveaux->execute([$chef_commission_id, $cadre_dppg_id, $cadre_daj_id]);
                    $nouveaux = $stmt_nouveaux->fetch();

                    // Construire le message de modifications
                    $modifications = [];
                    if ($commission['chef_commission_id'] != $chef_commission_id) {
                        $modifications[] = "Chef de commission: {$anciens_membres['chef']} → {$nouveaux['nouveau_chef']}";
                    }
                    if ($commission['cadre_dppg_id'] != $cadre_dppg_id) {
                        $modifications[] = "Cadre DPPG: {$anciens_membres['dppg']} → {$nouveaux['nouveau_dppg']}";
                    }
                    if ($commission['cadre_daj_id'] != $cadre_daj_id) {
                        $modifications[] = "Cadre DAJ: {$anciens_membres['daj']} → {$nouveaux['nouveau_daj']}";
                    }

                    // Enregistrer dans l'historique
                    if (!empty($modifications)) {
                        $description = "Modification de la commission:\n" . implode("\n", $modifications);

                        $sql_hist = "INSERT INTO historique_dossier (dossier_id, user_id, action, description, date_action)
                                    VALUES (?, ?, 'modification_commission', ?, NOW())";
                        $stmt_hist = $pdo->prepare($sql_hist);
                        $stmt_hist->execute([$dossier_id, $_SESSION['user_id'], $description]);
                    }

                    $pdo->commit();

                    $message = 'Commission modifiée avec succès.';
                    if (!empty($modifications)) {
                        $message .= ' Les nouveaux membres ont maintenant accès aux documents du dossier.';
                    }

                    redirect(url('modules/dossiers/view.php?id=' . $dossier_id), $message, 'success');
                } else {
                    throw new Exception('Erreur lors de la modification de la commission');
                }

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollback();
                }
                $errors[] = 'Erreur lors de la modification: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Modifier la commission - Dossier ' . $dossier['numero'];
require_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">
                    <i class="fas fa-edit"></i> Modification de la commission d'inspection
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

                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle"></i> Attention - Impact sur les accès</h6>
                    <p class="mb-0">
                        La modification de la commission <strong>affecte immédiatement les droits d'accès</strong> aux documents du dossier:
                    </p>
                    <ul class="mb-0 mt-2">
                        <li>Les <strong>anciens membres</strong> retirés perdront l'accès aux documents</li>
                        <li>Les <strong>nouveaux membres</strong> désignés auront accès aux documents</li>
                        <li>Un historique de cette modification sera enregistré</li>
                    </ul>
                </div>

                <!-- Commission actuelle -->
                <div class="card mb-4 border-info">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-users"></i> Commission actuelle</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Chef de commission:</strong><br>
                                <?php echo sanitize($commission['chef_prenom'] . ' ' . $commission['chef_nom']); ?>
                                <br><small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $commission['chef_commission_role'])); ?></small>
                            </div>
                            <div class="col-md-4">
                                <strong>Cadre DPPG:</strong><br>
                                <?php echo sanitize($commission['dppg_prenom'] . ' ' . $commission['dppg_nom']); ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Cadre DAJ:</strong><br>
                                <?php echo sanitize($commission['daj_prenom'] . ' ' . $commission['daj_nom']); ?>
                            </div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i> Constituée le: <?php echo date('d/m/Y', strtotime($commission['date_constitution'])); ?>
                            </small>
                        </div>
                    </div>
                </div>

                <form method="POST" id="edit-commission-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <h5 class="text-primary mb-3"><i class="fas fa-edit"></i> Nouvelle composition</h5>

                    <!-- Chef de commission -->
                    <div class="mb-4">
                        <h6 class="text-primary">
                            <i class="fas fa-user-tie"></i> Chef de la commission
                        </h6>

                        <div class="mb-3">
                            <label for="chef_commission_id" class="form-label">Sélectionner le chef de commission *</label>

                            <?php if (empty($chefs_directeurs)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Aucun chef/directeur disponible. Veuillez contacter l'administrateur.
                            </div>
                            <?php else: ?>
                            <select class="form-select" id="chef_commission_id" name="chef_commission_id" required onchange="updateChefRole()">
                                <option value="">Choisir un chef de commission</option>
                                <?php foreach ($chefs_directeurs as $chef):
                                    $role_label = 'Chef de Commission';
                                    if ($chef['role'] === 'chef_service') {
                                        $role_label = 'Chef Service SDTD';
                                    } elseif ($chef['role'] === 'sous_directeur') {
                                        $role_label = 'Sous-Directeur SDTD';
                                    } elseif ($chef['role'] === 'billeteur') {
                                        $role_label = 'Billeteur DPPG';
                                    }
                                    $selected = ($commission['chef_commission_id'] == $chef['id']) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $chef['id']; ?>" data-role="<?php echo $chef['role']; ?>" <?php echo $selected; ?>>
                                    <?php echo sanitize($chef['prenom'] . ' ' . $chef['nom']); ?>
                                    (<?php echo $role_label; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="chef_commission_role" id="chef_commission_role" value="<?php echo sanitize($commission['chef_commission_role']); ?>">
                            <div class="mt-2">
                                <small id="role-indicator" class="form-text text-success">
                                    <?php if ($commission['chef_commission_role']): ?>
                                    ✓ Rôle: <?php echo $commission['chef_commission_role']; ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Cadre DPPG -->
                    <div class="mb-4">
                        <h6 class="text-primary">
                            <i class="fas fa-hard-hat"></i> Inspecteur technique (Cadre DPPG)
                        </h6>
                        <div class="mb-3">
                            <label for="cadre_dppg_id" class="form-label">Sélectionner le cadre DPPG *</label>
                            <?php if (empty($cadres_dppg)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Aucun cadre DPPG disponible.
                            </div>
                            <?php else: ?>
                            <select class="form-select" id="cadre_dppg_id" name="cadre_dppg_id" required>
                                <option value="">Choisir un cadre DPPG</option>
                                <?php foreach ($cadres_dppg as $cadre):
                                    $selected = ($commission['cadre_dppg_id'] == $cadre['id']) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $cadre['id']; ?>" <?php echo $selected; ?>>
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
                                Aucun cadre DAJ disponible.
                            </div>
                            <?php else: ?>
                            <select class="form-select" id="cadre_daj_id" name="cadre_daj_id" required>
                                <option value="">Choisir un cadre DAJ</option>
                                <?php foreach ($cadres_daj as $cadre):
                                    $selected = ($commission['cadre_daj_id'] == $cadre['id']) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $cadre['id']; ?>" <?php echo $selected; ?>>
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

                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?php echo url('modules/dossiers/view.php?id=' . $dossier_id); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Annuler
                        </a>
                        <?php if (!empty($cadres_dppg) && !empty($cadres_daj) && !empty($chefs_directeurs)): ?>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Enregistrer les modifications
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
    const indicator = document.getElementById('role-indicator');

    if (select.value) {
        const selectedOption = select.options[select.selectedIndex];
        const role = selectedOption.getAttribute('data-role');
        roleInput.value = role;

        if (indicator) {
            indicator.textContent = ' ✓ Rôle: ' + role;
            indicator.style.color = 'green';
        }
    } else {
        roleInput.value = '';
        if (indicator) {
            indicator.textContent = '';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialiser le rôle au chargement
    updateChefRole();

    const form = document.getElementById('edit-commission-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const roleInput = document.getElementById('chef_commission_role');
            const selectInput = document.getElementById('chef_commission_id');

            if (selectInput.value && !roleInput.value) {
                e.preventDefault();
                alert('ERREUR: Le rôle du chef de commission n\'a pas été détecté.\n\nVeuillez recharger la page et réessayer.');
                return false;
            }

            if (!roleInput.value) {
                e.preventDefault();
                alert('Veuillez sélectionner un chef de commission');
                return false;
            }

            // Confirmation avant modification
            if (!confirm('Êtes-vous sûr de vouloir modifier la composition de la commission ?\n\nCette action affectera les droits d\'accès aux documents du dossier.')) {
                e.preventDefault();
                return false;
            }
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>

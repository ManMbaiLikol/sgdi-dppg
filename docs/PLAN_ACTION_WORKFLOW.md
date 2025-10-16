# PLAN D'ACTION - Workflow Fiche Inspection
**Date:** 2025-10-16
**Focus:** Compléter le workflow validation + Interface chef commission

---

## 🎯 OBJECTIF

Implémenter le workflow complet de validation des fiches d'inspection :
```
Cadre DPPG → Crée fiche → Valide fiche
    ↓
Chef Commission → Reçoit notification → Valide/Rejette
    ↓
Chef Service → Visa → Circuit d'approbation
```

---

## 📝 TÂCHES DÉTAILLÉES

### PHASE 1 : Workflow Validation Fiche (3-4h)

#### Tâche 1.1 : Compléter logique validation
**Fichier:** `modules/fiche_inspection/functions.php`

```php
/**
 * Valider une fiche d'inspection (version complète)
 */
function validerFicheInspection($fiche_id, $user_id) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // 1. Récupérer la fiche
        $fiche = getFicheInspectionById($fiche_id);
        if (!$fiche) {
            throw new Exception("Fiche introuvable");
        }

        // 2. Vérifications métier
        $erreurs = validerCompletudeFiche($fiche_id);
        if (!empty($erreurs)) {
            throw new Exception("Fiche incomplète : " . implode(", ", $erreurs));
        }

        // 3. Mettre à jour statut fiche
        $sql = "UPDATE fiches_inspection
                SET statut = 'validee',
                    date_validation = NOW(),
                    valideur_id = ?
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $fiche_id]);

        // 4. Mettre à jour statut dossier
        $sql = "UPDATE dossiers
                SET statut = 'inspecte',
                    date_inspection = NOW()
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fiche['dossier_id']]);

        // 5. Créer notification chef commission
        require_once '../../includes/notification_functions.php';
        $commission = getCommissionByDossier($fiche['dossier_id']);
        if ($commission) {
            creerNotification(
                $commission['chef_commission_id'],
                'nouvelle_inspection',
                "Fiche d'inspection validée pour le dossier N° " . $fiche['dossier_numero'],
                "modules/chef_commission/valider_fiche.php?fiche_id=$fiche_id"
            );
        }

        // 6. Historique
        ajouterHistoriqueDossier(
            $fiche['dossier_id'],
            'inspection_validee',
            "Fiche d'inspection validée par " . getUserFullName($user_id),
            $user_id
        );

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur validation fiche: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifier complétude d'une fiche
 */
function validerCompletudeFiche($fiche_id) {
    global $pdo;

    $erreurs = [];

    // Récupérer la fiche
    $sql = "SELECT * FROM fiches_inspection WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fiche_id]);
    $fiche = $stmt->fetch();

    // Champs obligatoires
    if (empty($fiche['raison_sociale'])) $erreurs[] = "Raison sociale manquante";
    if (empty($fiche['ville'])) $erreurs[] = "Ville manquante";
    if (empty($fiche['latitude']) || empty($fiche['longitude'])) {
        $erreurs[] = "Coordonnées GPS manquantes";
    }

    // Au moins une cuve
    $sql = "SELECT COUNT(*) FROM fiche_inspection_cuves WHERE fiche_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fiche_id]);
    if ($stmt->fetchColumn() == 0) {
        $erreurs[] = "Aucune cuve renseignée";
    }

    // Au moins une pompe
    $sql = "SELECT COUNT(*) FROM fiche_inspection_pompes WHERE fiche_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fiche_id]);
    if ($stmt->fetchColumn() == 0) {
        $erreurs[] = "Aucune pompe renseignée";
    }

    return $erreurs;
}
```

#### Tâche 1.2 : Modifier formulaire validation
**Fichier:** `modules/fiche_inspection/edit.php` (lignes 184-192)

```php
// Valider la fiche si demandé
if (isset($_POST['valider'])) {
    // Vérifier complétude
    require_once 'functions.php';
    $erreurs = validerCompletudeFiche($fiche['id']);

    if (!empty($erreurs)) {
        throw new Exception("Fiche incomplète : " . implode(", ", $erreurs));
    }

    if (!validerFicheInspection($fiche['id'], $_SESSION['user_id'])) {
        throw new Exception("Erreur lors de la validation de la fiche");
    }

    $_SESSION['success'] = "Fiche d'inspection validée avec succès. Le chef de commission a été notifié.";
} else {
    $_SESSION['success'] = "Fiche d'inspection enregistrée avec succès";
}
```

#### Tâche 1.3 : Verrouiller modification après validation
**Fichier:** `modules/fiche_inspection/edit.php` (ligne 16)

```php
// Seuls les cadres DPPG peuvent créer et modifier
$peut_modifier = ($_SESSION['user_role'] === 'cadre_dppg' && $fiche['statut'] !== 'validee');
$mode_consultation = !$peut_modifier;
```

---

### PHASE 2 : Interface Chef Commission (4-5h)

#### Tâche 2.1 : Page liste inspections à valider
**Fichier:** `modules/chef_commission/valider_inspections.php` (NOUVEAU)

```php
<?php
require_once '../../includes/auth.php';
require_once '../fiche_inspection/functions.php';

requireLogin();
requireRole('chef_commission');

// Récupérer mes commissions
$sql = "SELECT c.id as commission_id, c.dossier_id,
               d.numero as dossier_numero,
               d.nom_demandeur,
               d.ville,
               fi.id as fiche_id,
               fi.statut as fiche_statut,
               fi.date_validation as fiche_date,
               u.nom as inspecteur_nom,
               u.prenom as inspecteur_prenom
        FROM commissions c
        INNER JOIN dossiers d ON c.dossier_id = d.id
        INNER JOIN fiches_inspection fi ON d.id = fi.dossier_id
        LEFT JOIN users u ON fi.inspecteur_id = u.id
        WHERE c.chef_commission_id = ?
        AND fi.statut = 'validee'
        AND d.statut = 'inspecte'
        ORDER BY fi.date_validation DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$inspections = $stmt->fetchAll();

$pageTitle = "Inspections à valider";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4">
        <i class="fas fa-clipboard-check"></i>
        Inspections à valider
    </h1>

    <?php if (empty($inspections)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            Aucune inspection en attente de validation.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Dossier</th>
                            <th>Demandeur</th>
                            <th>Localisation</th>
                            <th>Inspecteur</th>
                            <th>Date inspection</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inspections as $insp): ?>
                        <tr>
                            <td><strong><?php echo h($insp['dossier_numero']); ?></strong></td>
                            <td><?php echo h($insp['nom_demandeur']); ?></td>
                            <td><?php echo h($insp['ville']); ?></td>
                            <td><?php echo h($insp['inspecteur_prenom'] . ' ' . $insp['inspecteur_nom']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($insp['fiche_date'])); ?></td>
                            <td>
                                <a href="valider_fiche.php?fiche_id=<?php echo $insp['fiche_id']; ?>"
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> Examiner
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
```

#### Tâche 2.2 : Page validation/rejet fiche
**Fichier:** `modules/chef_commission/valider_fiche.php` (NOUVEAU)

Interface avec :
- Affichage lecture seule de la fiche
- Formulaire validation avec :
  - Bouton "Approuver"
  - Bouton "Rejeter" (avec motif obligatoire)
  - Champ commentaires
- Historique des validations précédentes

#### Tâche 2.3 : Traitement validation/rejet
**Fichier:** `modules/chef_commission/functions.php` (NOUVEAU)

```php
<?php

/**
 * Approuver une inspection
 */
function approuverInspection($fiche_id, $chef_commission_id, $commentaires = '') {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // Récupérer la fiche
        $fiche = getFicheInspectionById($fiche_id);

        // 1. Mettre à jour statut dossier
        $sql = "UPDATE dossiers
                SET statut = 'validation_commission'
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fiche['dossier_id']]);

        // 2. Enregistrer la validation
        $sql = "INSERT INTO validations_commission
                (fiche_id, commission_id, chef_commission_id, decision, commentaires)
                VALUES (?, ?, ?, 'approuve', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $fiche_id,
            $fiche['commission_id'],
            $chef_commission_id,
            $commentaires
        ]);

        // 3. Notification chef service
        $chef_service = getChefService();
        creerNotification(
            $chef_service['id'],
            'validation_commission',
            "Inspection approuvée pour le dossier N° " . $fiche['dossier_numero'],
            "modules/dossiers/view.php?id=" . $fiche['dossier_id']
        );

        // 4. Historique
        ajouterHistoriqueDossier(
            $fiche['dossier_id'],
            'validation_commission',
            "Inspection approuvée par le chef de commission",
            $chef_commission_id
        );

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur approbation: " . $e->getMessage());
        return false;
    }
}

/**
 * Rejeter une inspection
 */
function rejeterInspection($fiche_id, $chef_commission_id, $motif) {
    global $pdo;

    if (empty($motif)) {
        throw new Exception("Le motif de rejet est obligatoire");
    }

    try {
        $pdo->beginTransaction();

        $fiche = getFicheInspectionById($fiche_id);

        // 1. Remettre dossier en inspection
        $sql = "UPDATE dossiers
                SET statut = 'paye'
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fiche['dossier_id']]);

        // 2. Remettre fiche en brouillon
        $sql = "UPDATE fiches_inspection
                SET statut = 'brouillon'
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fiche_id]);

        // 3. Enregistrer le rejet
        $sql = "INSERT INTO validations_commission
                (fiche_id, commission_id, chef_commission_id, decision, commentaires)
                VALUES (?, ?, ?, 'rejete', ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $fiche_id,
            $fiche['commission_id'],
            $chef_commission_id,
            $motif
        ]);

        // 4. Notification inspecteur
        creerNotification(
            $fiche['inspecteur_id'],
            'inspection_rejetee',
            "Votre inspection a été rejetée : " . $motif,
            "modules/fiche_inspection/edit.php?dossier_id=" . $fiche['dossier_id']
        );

        // 5. Historique
        ajouterHistoriqueDossier(
            $fiche['dossier_id'],
            'inspection_rejetee',
            "Inspection rejetée : $motif",
            $chef_commission_id
        );

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur rejet: " . $e->getMessage());
        return false;
    }
}
```

---

### PHASE 3 : Table Base de Données (30 min)

#### Tâche 3.1 : Créer table validations_commission
**Fichier:** `database/migrations/add_validations_commission.sql` (NOUVEAU)

```sql
-- Table des validations par le chef de commission
CREATE TABLE IF NOT EXISTS validations_commission (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fiche_id INT NOT NULL,
    commission_id INT NOT NULL,
    chef_commission_id INT NOT NULL,
    decision ENUM('approuve', 'rejete') NOT NULL,
    commentaires TEXT,
    date_validation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (fiche_id) REFERENCES fiches_inspection(id) ON DELETE CASCADE,
    FOREIGN KEY (commission_id) REFERENCES commissions(id) ON DELETE CASCADE,
    FOREIGN KEY (chef_commission_id) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_fiche (fiche_id),
    INDEX idx_commission (commission_id),
    INDEX idx_date (date_validation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tâche 3.2 : Ajouter colonnes fiches_inspection
```sql
ALTER TABLE fiches_inspection
ADD COLUMN date_validation DATETIME NULL AFTER statut,
ADD COLUMN valideur_id INT NULL AFTER date_validation,
ADD FOREIGN KEY (valideur_id) REFERENCES users(id) ON DELETE SET NULL;
```

---

## 📅 CALENDRIER

### Aujourd'hui (4h)
- [x] Audit session terminé
- [ ] Test correction FCFA → 10 min
- [ ] Commit si OK → 5 min
- [ ] Phase 1 : Workflow validation → 2h
- [ ] Phase 3 : Tables BDD → 30 min
- [ ] Tests workflow → 45 min

### Demain (4-5h)
- [ ] Phase 2 : Interface chef commission → 4h
- [ ] Tests complets → 1h
- [ ] Commit + documentation → 30 min

---

## ✅ CHECKLIST TESTS

### Workflow Validation
- [ ] Cadre DPPG valide fiche → statut dossier change
- [ ] Notification envoyée au chef commission
- [ ] Fiche verrouillée après validation
- [ ] Erreur si fiche incomplète
- [ ] Historique enregistré

### Interface Chef Commission
- [ ] Liste affiche seulement inspections à valider
- [ ] Approbation → dossier passe à "validation_commission"
- [ ] Rejet → dossier retourne à "paye" + fiche en brouillon
- [ ] Motif rejet obligatoire
- [ ] Notifications fonctionnent

---

## 🚨 POINTS D'ATTENTION

1. **Sécurité**
   - Vérifier que seul le chef de commission assigné peut valider
   - Empêcher double validation
   - CSRF tokens sur tous formulaires

2. **Performance**
   - Index sur validations_commission
   - Optimiser requêtes liste inspections

3. **UX**
   - Confirmation avant validation/rejet
   - Messages clairs pour l'utilisateur
   - Breadcrumb navigation

---

**Document créé le:** 2025-10-16
**Prochaine révision:** Après Phase 1 complétée

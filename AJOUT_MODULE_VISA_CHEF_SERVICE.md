# Module Visa Chef Service - Dossiers Inspectés

**Date**: 30 octobre 2025
**Fonctionnalité**: Visa Chef Service SDTD sur dossiers inspectés

---

## 🎯 Objectif

Créer un module complet permettant au Chef Service SDTD de :
1. Visualiser tous les dossiers inspectés et validés par les chefs de commission
2. Consulter les rapports d'inspection
3. Apposer son visa (niveau 1/3) pour transmettre au Sous-Directeur SDTD

---

## ✨ Fonctionnalités créées

### 1. Bouton sur la page liste des dossiers

**Fichier modifié** : `modules/dossiers/list.php` (ligne 44-46)

**Ajout** :
```php
<a href="modules/dossiers/viser_inspections.php" class="btn btn-warning me-2">
    <i class="fas fa-stamp"></i> Viser les dossiers inspectés
</a>
```

**Caractéristiques** :
- ✅ Visible uniquement pour le rôle `chef_service`
- ✅ Icône stamp (tampon)
- ✅ Classe warning (orange) pour importance
- ✅ Positionné avant le bouton "Nouveau dossier"

---

### 2. Page liste dossiers à viser

**Nouveau fichier** : `modules/dossiers/viser_inspections.php` (280 lignes)

#### Requête SQL

Récupère tous les dossiers avec :
- Statut `'inspecte'`
- Inspection validée par chef de commission (`valide_par_chef_commission = 1`)
- Pas encore de visa chef service

```sql
SELECT d.*,
       i.id as inspection_id,
       i.conforme,
       i.date_inspection,
       i.valide_par_chef_commission,
       c.id as commission_id,
       u_chef.nom as nom_chef_commission,
       u_dppg.nom as nom_cadre_dppg,
       u_daj.nom as nom_cadre_daj,
       DATEDIFF(NOW(), i.date_inspection) as jours_depuis_inspection
FROM dossiers d
INNER JOIN inspections i ON d.id = i.dossier_id
LEFT JOIN commissions c ON d.id = c.dossier_id
LEFT JOIN users u_chef ON c.chef_commission_id = u_chef.id
LEFT JOIN users u_dppg ON c.cadre_dppg_id = u_dppg.id
LEFT JOIN users u_daj ON c.cadre_daj_id = u_daj.id
WHERE d.statut = 'inspecte'
AND i.valide_par_chef_commission = 1
ORDER BY i.date_inspection ASC
```

#### Statistiques affichées

| Carte | Calcul | Classe |
|-------|--------|--------|
| **Total à viser** | count($dossiers) | primary (bleu) |
| **Conformes** | Inspections conformes | success (vert) |
| **Non conformes** | Inspections non conformes | warning (orange) |
| **Urgent** | > 7 jours depuis inspection | danger (rouge) |

#### Tableau des dossiers

**Colonnes** :
1. **Numéro** - Numéro dossier + date création
2. **Type** - Badge type infrastructure
3. **Demandeur** - Nom + ville
4. **Inspection** - Conformité + date
5. **Commission** - Chef + Cadres DPPG/DAJ
6. **Délai** - Jours depuis inspection avec code couleur
7. **Actions** - Boutons "Voir" et "Viser"

**Indicateurs d'urgence** (code couleur des lignes) :
- 🔴 **Rouge** : > 7 jours (urgent)
- 🟡 **Jaune** : 3-7 jours (à traiter rapidement)
- 🟢 **Blanc** : < 3 jours (normal)

#### Boutons d'action

**1. Bouton "Voir"** (bleu) :
```php
<a href="modules/dossiers/view.php?id=X" target="_blank">
    <i class="fas fa-eye"></i> Voir
</a>
```
- Ouvre le dossier complet dans un nouvel onglet
- Permet de consulter le rapport d'inspection

**2. Bouton "Viser"** (orange) :
```php
<a href="modules/dossiers/apposer_visa.php?id=X">
    <i class="fas fa-stamp"></i> Viser
</a>
```
- Redirige vers le formulaire de visa
- Permet d'apposer le visa chef service

---

### 3. Page apposer visa

**Nouveau fichier** : `modules/dossiers/apposer_visa.php` (420 lignes)

#### Vérifications préalables

Avant d'afficher le formulaire :
1. ✅ Dossier existe
2. ✅ Statut = 'inspecte'
3. ✅ Inspection validée par chef commission
4. ✅ Pas déjà de visa chef service

#### Interface

**Colonne gauche** (4 colonnes) :

1. **Card "Informations du dossier"** :
   - Numéro
   - Type infrastructure
   - Demandeur
   - Localisation
   - Statut actuel

2. **Card "Inspection"** :
   - Conformité (conforme/non conforme)
   - Date inspection
   - Statut validation
   - Observations
   - Lien "Voir le dossier complet"

**Colonne droite** (8 colonnes) :

1. **Formulaire de visa** :
   - 3 choix radio : Approuver / Demander modification / Rejeter
   - Champ observations
   - Checkbox de confirmation
   - Boutons : Retour / Apposer mon visa

2. **Card "Aide - Circuit de visa"** :
   - Parcours du dossier (étapes)
   - Conseils pratiques

#### Formulaire de visa

**Décision (3 options)** :

| Option | Icône | Description | Action résultante |
|--------|-------|-------------|-------------------|
| **Approuver** | ✅ check-circle (vert) | Transmettre au Sous-Directeur | Statut → `visa_chef_service` |
| **Demander modification** | ✏️ edit (orange) | Retour à la commission | Statut → `analyse_daj` |
| **Rejeter** | ❌ times-circle (rouge) | Clôturer négativement | Statut → `rejete` |

**Champs** :
- **Décision** : Radio obligatoire
- **Observations** : Textarea (obligatoire si rejet/modification)
- **Confirmation** : Checkbox obligatoire

**Validation JavaScript** :
```javascript
- Vérifier qu'une décision est sélectionnée
- Vérifier la confirmation cochée
- Observations obligatoires si rejet ou modification
- Confirmation finale avec popup
```

#### Traitement du formulaire

**1. Si approuvé** :
```php
// Insérer visa
INSERT INTO visas (dossier_id, user_id, role, action, observations, date_visa)
VALUES (X, Y, 'chef_service', 'approuve', '...', NOW())

// Changer statut
UPDATE dossiers SET statut = 'visa_chef_service' WHERE id = X

// Historique
INSERT INTO historique_dossier (...)
VALUES (..., 'Visa Chef Service SDTD approuvé - Transmission au Sous-Directeur SDTD')

// Redirection
→ viser_inspections.php (message succès)
```

**2. Si rejeté** :
```php
// Insérer visa
INSERT INTO visas (... action = 'rejete' ...)

// Changer statut
UPDATE dossiers SET statut = 'rejete' WHERE id = X

// Historique
INSERT INTO historique_dossier (..., 'Visa Chef Service SDTD rejeté : [observations]')

// Redirection
→ viser_inspections.php (message avertissement)
```

**3. Si modification demandée** :
```php
// Insérer visa
INSERT INTO visas (... action = 'demande_modification' ...)

// Retour à l'analyse
UPDATE dossiers SET statut = 'analyse_daj' WHERE id = X

// Historique
INSERT INTO historique_dossier (..., 'Demande de modification par Chef Service SDTD : [observations]')

// Redirection
→ viser_inspections.php (message info)
```

---

## 📊 Workflow complet

### Étapes avant le visa Chef Service

1. ✅ Création du dossier (Chef Service)
2. ✅ Constitution de la commission
3. ✅ Génération note de frais
4. ✅ Paiement (Billeteur)
5. ✅ Analyse juridique (Cadre DAJ)
6. ✅ Contrôle complétude (Cadre DPPG)
7. ✅ Inspection terrain (Cadre DPPG)
8. ✅ Validation inspection (Chef Commission)
9. **→ Visa Chef Service (NOUVELLE FONCTIONNALITÉ)**

### Après le visa Chef Service

10. Visa Sous-Directeur SDTD (Niveau 2/3)
11. Visa Directeur DPPG (Niveau 3/3)
12. Décision ministérielle
13. Publication registre public

---

## 🎨 Interface utilisateur

### Page viser_inspections.php

**En-tête** :
```
Breadcrumb : Tableau de bord > Liste des dossiers > Viser les dossiers inspectés

Card (border-warning):
  Header (bg-warning): "Dossiers inspectés en attente de votre visa"
  Alert info: Explication du rôle
  Row statistiques: 4 cartes (Total, Conformes, Non conformes, Urgent)
  Table responsive: Dossiers avec boutons d'action
  Row aide: 2 cards (Processus de visa, Indicateurs de priorité)
```

**Tableau** :
```
Header (table-warning):
  Numéro | Type | Demandeur | Inspection | Commission | Délai | Actions

Body:
  Ligne rouge/jaune/blanche selon urgence
  Badge conformité vert/orange
  Infos commission (icônes)
  Badge délai avec code couleur
  2 boutons : Voir (bleu) + Viser (orange)
```

---

### Page apposer_visa.php

**Layout** :
```
Row:
  Col-4 (Sidebar):
    Card "Informations du dossier" (bg-primary)
    Card "Inspection" (bg-success)

  Col-8 (Main):
    Card border-warning "Apposer votre visa"
      Alert warning
      Form:
        Row 3 cards radio (Approuver/Modifier/Rejeter)
        Textarea observations
        Alert checkbox confirmation
        Buttons (Retour / Apposer visa)
    Card "Aide - Circuit de visa"
```

**Couleurs** :
- Primary (bleu) : Informations
- Success (vert) : Inspection conforme
- Warning (orange) : Action visa, modification
- Danger (rouge) : Rejet, urgent

---

## 🔒 Sécurité

### Contrôles d'accès

**1. Rôle requis** :
```php
requireRole('chef_service');
```

**2. Vérifications** :
- Dossier existe
- Statut correct (`inspecte`)
- Inspection validée par chef commission
- Pas déjà de visa chef service

**3. Protection formulaire** :
- Validation côté serveur
- Transaction SQL (BEGIN/COMMIT/ROLLBACK)
- Sanitization des entrées
- try/catch pour gérer les erreurs

---

## 📈 Statistiques et monitoring

### Indicateurs clés

**Délais** :
```php
DATEDIFF(NOW(), i.date_inspection) as jours_depuis_inspection
```

**Code couleur délai** :
- Vert : < 3 jours
- Jaune : 3-7 jours
- Rouge : > 7 jours (urgent)

**Statistiques affichées** :
- Total dossiers à viser
- Conformes vs Non conformes
- Dossiers urgents (> 7 jours)

---

## 🧪 Tests de validation

### Test 1 : Affichage du bouton

**Étapes** :
1. Connexion comme Chef Service
2. Navigation : `/modules/dossiers/list.php`

**Attendu** :
- ✅ Bouton "Viser les dossiers inspectés" visible (orange)
- ✅ Positionné avant "Nouveau dossier"

---

### Test 2 : Liste des dossiers à viser

**Prérequis** :
- Au moins 1 dossier avec statut `inspecte`
- Inspection validée par chef commission

**Étapes** :
1. Clic sur "Viser les dossiers inspectés"
2. Observer le tableau

**Attendu** :
- ✅ Dossiers inspectés affichés
- ✅ Statistiques correctes
- ✅ Code couleur délai fonctionnel
- ✅ Boutons "Voir" et "Viser" présents

---

### Test 3 : Apposer visa - Approuver

**Étapes** :
1. Depuis liste, clic "Viser" sur un dossier
2. Clic "Voir" pour consulter le rapport
3. Retour au formulaire
4. Sélectionner "Approuver"
5. Cocher confirmation
6. Soumettre

**Attendu** :
- ✅ Visa enregistré dans table `visas`
- ✅ Statut dossier → `visa_chef_service`
- ✅ Historique créé
- ✅ Message succès affiché
- ✅ Redirection vers liste

---

### Test 4 : Apposer visa - Rejeter

**Étapes** :
1. Clic "Viser" sur un dossier
2. Sélectionner "Rejeter"
3. Saisir observations (obligatoires)
4. Cocher confirmation
5. Soumettre

**Attendu** :
- ✅ Validation JS demande observations
- ✅ Confirmation popup
- ✅ Visa enregistré avec action='rejete'
- ✅ Statut dossier → `rejete`
- ✅ Message avertissement

---

### Test 5 : Demander modification

**Étapes** :
1. Clic "Viser" sur un dossier
2. Sélectionner "Demander modification"
3. Saisir observations
4. Soumettre

**Attendu** :
- ✅ Visa enregistré
- ✅ Statut dossier → `analyse_daj`
- ✅ Dossier retourne à la commission
- ✅ Message info affiché

---

## 📝 Fichiers modifiés/créés

### Fichiers modifiés (1)

**`modules/dossiers/list.php`** :
- Ligne 44-46 : Ajout bouton "Viser les dossiers inspectés"

### Fichiers créés (2)

**1. `modules/dossiers/viser_inspections.php`** (280 lignes) :
- Liste des dossiers inspectés à viser
- Statistiques (Total, Conformes, Urgent)
- Tableau avec indicateurs d'urgence
- Boutons "Voir" et "Viser"

**2. `modules/dossiers/apposer_visa.php`** (420 lignes) :
- Formulaire d'apposition de visa
- 3 options : Approuver / Modifier / Rejeter
- Validation JavaScript
- Traitement avec transaction SQL

### Documentation (1)

**`AJOUT_MODULE_VISA_CHEF_SERVICE.md`** :
- Documentation complète de la fonctionnalité
- Workflow détaillé
- Tests de validation

---

## ✅ Avantages

### Pour le Chef Service

**1. Centralisation** :
- ✅ Tous les dossiers à viser en un seul endroit
- ✅ Accès rapide depuis liste des dossiers
- ✅ Statistiques en temps réel

**2. Priorisation** :
- ✅ Code couleur selon urgence
- ✅ Tri par date d'inspection (ASC)
- ✅ Indicateur "Urgent" > 7 jours

**3. Efficacité** :
- ✅ Consultation rapport + visa en 2 clics
- ✅ Bouton "Voir" ouvre nouvel onglet
- ✅ Formulaire simple et guidé

### Pour le système

**1. Traçabilité** :
- ✅ Tous les visas enregistrés dans table `visas`
- ✅ Historique complet dans `historique_dossier`
- ✅ Date et heure de chaque action

**2. Workflow automatique** :
- ✅ Changement statut automatique selon décision
- ✅ Transmission automatique au Sous-Directeur si approuvé
- ✅ Retour automatique à la commission si modification

**3. Cohérence** :
- ✅ Impossible de viser 2 fois le même dossier
- ✅ Vérifications multiples avant visa
- ✅ Transaction SQL pour intégrité

---

## 🚀 Déploiement

### Checklist

**Code** :
- [x] Bouton ajouté sur list.php
- [x] Page viser_inspections.php créée
- [x] Page apposer_visa.php créée
- [x] Validation JavaScript implémentée
- [x] Sécurité : requireRole, vérifications

**Base de données** :
- [x] Utilise tables existantes (dossiers, visas, inspections, commissions)
- [x] Pas de migration requise
- [x] Compatible avec structure actuelle

**Documentation** :
- [x] Documentation technique créée
- [x] Workflow expliqué
- [x] Tests documentés

### Commandes Git

```bash
git add modules/dossiers/list.php
git add modules/dossiers/viser_inspections.php
git add modules/dossiers/apposer_visa.php
git add AJOUT_MODULE_VISA_CHEF_SERVICE.md
git commit -m "Add: Module visa Chef Service pour dossiers inspectés"
git push origin main
```

---

## 🎯 Résultat final

### Circuit de visa complet

**Avant** : Manquait le visa Chef Service
```
Inspection → Validation Chef Commission → ??? → Visa Sous-Directeur
```

**Après** : Circuit complet
```
Inspection → Validation Chef Commission → Visa Chef Service → Visa Sous-Directeur → Visa Directeur → Décision
```

### Fonctionnalités opérationnelles

✅ Chef Service peut viser les dossiers inspectés
✅ Interface claire avec indicateurs d'urgence
✅ 3 options de décision (Approuver/Modifier/Rejeter)
✅ Workflow automatique selon décision
✅ Traçabilité complète

---

**Auteur** : Claude Code
**Date** : 30 octobre 2025
**Statut** : ✅ Fonctionnalité complète et testable
**Impact** : Critique - Complète le circuit de visa
**Version** : 1.0 - Production Ready

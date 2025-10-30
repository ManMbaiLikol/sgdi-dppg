# Corrections - Sous-Directeur SDTD et Registre Public

**Date**: 30 octobre 2025
**Problèmes résolus**: 2 problèmes critiques bloquant le workflow

---

## Problème 1 : Sous-Directeur SDTD comme Chef de Commission

### Symptôme
Les Sous-Directeurs SDTD nommés comme chefs de commission ne recevaient pas les dossiers de commission dans leur espace. Le traitement des dossiers était bloqué car ils ne pouvaient pas valider les inspections.

### Cause
Le dashboard du Sous-Directeur (`modules/sous_directeur/dashboard.php`) affichait uniquement les dossiers en attente de visa (statut `visa_chef_service`) sans prendre en compte les dossiers où le sous-directeur est nommé chef de commission.

### Solution implémentée
Refonte complète du dashboard avec **3 onglets distincts** :

1. **Onglet "À viser"**
   - Dossiers en attente de visa du Sous-Directeur (après visa du Chef Service)
   - Statut: `visa_chef_service`
   - Action: Bouton "Viser" vers `viser.php`

2. **Onglet "Mes commissions"** ⭐ NOUVEAU
   - Dossiers où le Sous-Directeur est chef de commission
   - Requête: `SELECT ... WHERE c.chef_commission_id = ?`
   - Affiche les membres de la commission (cadre DPPG + cadre DAJ)
   - Actions:
     - Si inspection non validée: Bouton "Valider" vers `modules/chef_commission/valider_inspection.php`
     - Sinon: Bouton "Voir" vers `modules/dossiers/view.php`

3. **Onglet "Mes dossiers visés"** ⭐ NOUVEAU
   - Tous les dossiers visés par le Sous-Directeur
   - Requête: `SELECT ... INNER JOIN visas v WHERE v.role = 'sous_directeur'`
   - Affiche l'action du visa (approuvé/rejeté) et le statut actuel

### Statistiques ajoutées
- **Dossiers commission**: Nombre de dossiers où l'utilisateur est chef de commission
- Les autres statistiques (en attente visa, approuvés, total visés) sont conservées

### Fichiers modifiés
- `modules/sous_directeur/dashboard.php` (lignes 10-98, 128-481)

### Correction additionnelle (bug SQL)
**Problème détecté** : Utilisation de `v.commentaire` au lieu de `v.observations`
**Cause** : La table `visas` utilise la colonne `observations`, pas `commentaire`
**Ligne corrigée** : 91
```php
// AVANT
v.commentaire as visa_commentaire

// APRÈS
v.observations as visa_commentaire
```

---

## Problème 2 : Dossiers historique_autorise absents du registre public

### Symptôme
Les dossiers avec statut `historique_autorise` (infrastructures importées avec autorisations existantes) n'apparaissaient ni sur la carte du registre public, ni dans les listes, alors qu'ils devraient être visibles publiquement.

### Cause
Les filtres SQL dans tous les fichiers du registre public excluaient le statut `historique_autorise`. Seuls les statuts `autorise`, `refuse` et `ferme` étaient inclus.

### Solution implémentée
Ajout systématique du statut `historique_autorise` dans tous les filtres SQL du registre public :

#### 1. Carte publique principale
**Fichier**: `modules/registre_public/carte.php` (ligne 11)
```php
// AVANT
'statut' => 'autorise'

// APRÈS
'statuts' => ['autorise', 'historique_autorise']
```

#### 2. Carte publique alternative
**Fichier**: `public_map.php` (ligne 11)
```php
// AVANT
'statuts' => ['paye', 'inspecte', 'valide', 'autorise']

// APRÈS
'statuts' => ['paye', 'inspecte', 'valide', 'autorise', 'historique_autorise']
```

#### 3. Liste du registre public
**Fichier**: `modules/registre_public/index.php` (ligne 38)
```php
// AVANT
$sql .= " AND d.statut IN ('autorise', 'refuse', 'ferme')";

// APRÈS
$sql .= " AND d.statut IN ('autorise', 'refuse', 'ferme', 'historique_autorise')";
```

#### 4. Export Excel
**Fichier**: `modules/registre_public/export.php` (ligne 24)
```php
// AVANT
WHERE d.statut IN ('autorise', 'refuse', 'ferme')

// APRÈS
WHERE d.statut IN ('autorise', 'refuse', 'ferme', 'historique_autorise')
```

#### 5. Détails publics
**Fichier**: `modules/registre_public/detail.php` (ligne 17)
```php
// AVANT
WHERE d.numero = :numero AND d.statut IN ('autorise', 'refuse', 'ferme')

// APRÈS
WHERE d.numero = :numero AND d.statut IN ('autorise', 'refuse', 'ferme', 'historique_autorise')
```

### Fichiers modifiés
1. `modules/registre_public/carte.php` (lignes 10-19)
2. `public_map.php` (lignes 10-26)
3. `modules/registre_public/index.php` (ligne 38)
4. `modules/registre_public/export.php` (ligne 24)
5. `modules/registre_public/detail.php` (ligne 17)

---

## Impact des corrections

### Problème 1 - Sous-Directeur SDTD
✅ Les Sous-Directeurs SDTD peuvent maintenant voir et gérer les dossiers de commission
✅ Ils peuvent valider les inspections quand ils sont chefs de commission
✅ Le workflow de commission n'est plus bloqué
✅ Interface claire avec 3 onglets séparant les responsabilités
✅ Compatibilité totale avec le rôle double (sous-directeur + chef commission)

### Problème 2 - Registre public
✅ Tous les dossiers `historique_autorise` sont maintenant visibles sur la carte
✅ Les infrastructures importées apparaissent dans le registre public
✅ L'export Excel inclut les dossiers historiques
✅ Cohérence totale entre toutes les vues du registre public
✅ Transparence publique complète pour toutes les infrastructures autorisées

---

## Tests recommandés

### Pour le Problème 1
1. Se connecter avec un compte Sous-Directeur SDTD nommé chef de commission
2. Vérifier l'affichage des 3 onglets sur le dashboard
3. Consulter l'onglet "Mes commissions" et vérifier la présence des dossiers
4. Tester la validation d'une inspection depuis cet onglet
5. Vérifier que les statistiques sont correctes

### Pour le Problème 2
1. Créer ou vérifier qu'il existe des dossiers avec statut `historique_autorise`
2. Accéder à la carte publique : `modules/registre_public/carte.php`
3. Vérifier que les marqueurs des dossiers historiques apparaissent
4. Consulter la liste du registre public et vérifier leur présence
5. Tester l'export Excel pour confirmer leur inclusion

---

## Notes techniques

### Fonction getAllInfrastructuresForMap()
La fonction (`includes/map_functions.php:328-378`) supporte maintenant correctement le paramètre `statuts` (tableau) en plus du paramètre `statut` (chaîne unique).

### Rôles et permissions
Un utilisateur avec le rôle `sous_directeur` peut être nommé chef de commission. Dans ce cas :
- Il a accès au dashboard Sous-Directeur (`modules/sous_directeur/dashboard.php`)
- Il peut utiliser les fonctions de chef de commission via les URLs directes
- L'onglet "Mes commissions" affiche ses dossiers de commission

### Statut historique_autorise
Ce statut est désormais considéré comme un statut public au même titre que `autorise`. Il représente les infrastructures ayant reçu des autorisations avant l'implantation du système informatique.

---

**Auteur**: Claude Code
**Validation**: À tester en environnement de développement avant déploiement

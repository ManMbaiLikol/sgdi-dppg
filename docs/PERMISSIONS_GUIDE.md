# Guide du Système de Permissions Granulaires - SGDI

> Documentation complète pour la gestion des permissions au niveau utilisateur

## Vue d'ensemble

Le système de permissions granulaires permet d'attribuer des droits d'accès spécifiques à chaque utilisateur, au-delà du simple système de rôles. Cela offre une flexibilité maximale pour adapter les accès selon les responsabilités réelles de chaque personne.

---

## Installation

### Prérequis

- Base de données SGDI opérationnelle
- Compte administrateur actif
- PHP 7.4+ avec extension PDO MySQL

### Procédure d'installation

1. **Accéder à la page d'installation**
   ```
   http://localhost/dppg-implantation/modules/permissions/install.php
   ```

2. **Vérifier les résultats**
   - Table `permissions` créée : ✓
   - Table `user_permissions` créée : ✓
   - Permissions insérées : ~70 permissions

3. **Accéder à la gestion**
   ```
   Administration > Permissions
   ```

---

## Structure du système

### Tables de base de données

#### Table `permissions`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT | Identifiant unique |
| `code` | VARCHAR(100) | Code unique (ex: `dossiers.create`) |
| `module` | VARCHAR(50) | Module concerné |
| `nom` | VARCHAR(150) | Nom lisible de la permission |
| `description` | TEXT | Description détaillée |
| `date_creation` | TIMESTAMP | Date de création |

#### Table `user_permissions`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT | Identifiant unique |
| `user_id` | INT | ID de l'utilisateur |
| `permission_id` | INT | ID de la permission |
| `accordee_par` | INT | ID de l'admin qui a accordé la permission |
| `date_attribution` | TIMESTAMP | Date d'attribution |

---

## Liste des permissions par module

### Module Dossiers (8 permissions)

| Code | Permission | Description |
|------|------------|-------------|
| `dossiers.create` | Créer un dossier | Permet de créer de nouveaux dossiers |
| `dossiers.view` | Voir les dossiers | Permet de consulter les dossiers |
| `dossiers.edit` | Modifier un dossier | Permet de modifier les informations |
| `dossiers.delete` | Supprimer un dossier | Permet de supprimer un dossier (admin) |
| `dossiers.list` | Lister les dossiers | Permet d'accéder à la liste |
| `dossiers.view_all` | Voir tous les dossiers | Voir tous sans filtre de rôle |
| `dossiers.export` | Exporter les dossiers | Permet d'exporter les données |
| `dossiers.localisation` | Gérer la localisation | Gérer les coordonnées GPS |

### Module Commission (4 permissions)

| Code | Permission | Description |
|------|------------|-------------|
| `commission.create` | Constituer une commission | Nommer les membres |
| `commission.view` | Voir les commissions | Consulter les commissions |
| `commission.edit` | Modifier une commission | Modifier la composition |
| `commission.validate` | Valider une inspection | Valider un rapport |

### Module Paiements (4 permissions)

| Code | Permission | Description |
|------|------------|-------------|
| `paiements.view` | Voir les paiements | Consulter les paiements |
| `paiements.create` | Enregistrer un paiement | Nouveau paiement |
| `paiements.edit` | Modifier un paiement | Modifier un paiement existant |
| `paiements.receipt` | Générer un reçu | Imprimer un reçu |

### Module Analyse DAJ (4 permissions)

| Code | Permission | Description |
|------|------------|-------------|
| `daj.view` | Voir les analyses DAJ | Consulter les analyses |
| `daj.create` | Faire une analyse DAJ | Réaliser une analyse |
| `daj.edit` | Modifier une analyse DAJ | Modifier une analyse |
| `daj.validate` | Valider une analyse DAJ | Valider une analyse |

### Module Inspections (5 permissions)

| Code | Permission | Description |
|------|------------|-------------|
| `inspections.view` | Voir les inspections | Consulter les inspections |
| `inspections.create` | Faire une inspection | Réaliser une inspection terrain |
| `inspections.edit` | Modifier une inspection | Modifier un rapport |
| `inspections.validate` | Valider une inspection | Valider une inspection |
| `inspections.print` | Imprimer les fiches | Imprimer les fiches |

### Module Visa (4 permissions)

| Code | Permission | Description |
|------|------------|-------------|
| `visa.chef_service` | Visa Chef Service | Apposer le visa niveau 1 |
| `visa.sous_directeur` | Visa Sous-Directeur | Apposer le visa niveau 2 |
| `visa.directeur` | Visa Directeur | Apposer le visa niveau 3 |
| `visa.view` | Voir les visas | Consulter les visas apposés |

### Module Décisions (3 permissions)

| Code | Permission | Description |
|------|------------|-------------|
| `decisions.view` | Voir les décisions | Consulter les décisions |
| `decisions.create` | Prendre une décision | Décision finale (autorisation/refus) |
| `decisions.transmit` | Transmettre au ministre | Transmettre un dossier |

### Module Documents (4 permissions)

| Code | Permission | Description |
|------|------------|-------------|
| `documents.view` | Voir les documents | Consulter les documents |
| `documents.upload` | Uploader des documents | Uploader de nouveaux documents |
| `documents.download` | Télécharger des documents | Télécharger les documents |
| `documents.delete` | Supprimer des documents | Supprimer des documents |

### Module Utilisateurs (6 permissions)

| Code | Permission | Description |
|------|------------|-------------|
| `users.view` | Voir les utilisateurs | Consulter la liste |
| `users.create` | Créer un utilisateur | Créer de nouveaux utilisateurs |
| `users.edit` | Modifier un utilisateur | Modifier les informations |
| `users.delete` | Supprimer un utilisateur | Supprimer un utilisateur |
| `users.toggle_status` | Activer/Désactiver | Activer ou désactiver un compte |
| `users.manage_permissions` | Gérer les permissions | Attribuer des permissions |

### Module Huitaine (3 permissions)

| Code | Permission | Description |
|------|------------|-------------|
| `huitaine.view` | Voir les huitaines | Consulter les huitaines |
| `huitaine.create` | Créer une huitaine | Déclencher une huitaine |
| `huitaine.regularize` | Régulariser une huitaine | Régulariser un dossier |

### Module GPS/Géolocalisation (4 permissions)

| Code | Permission | Description |
|------|------------|-------------|
| `gps.view` | Voir les données GPS | Consulter les données GPS |
| `gps.edit` | Modifier les données GPS | Modifier les coordonnées |
| `gps.import` | Importer des données GPS | Importer des données (OSM, CSV) |
| `gps.validate` | Valider les coordonnées GPS | Valider la cohérence géographique |

### Module Rapports (4 permissions)

| Code | Permission | Description |
|------|------------|-------------|
| `rapports.view` | Voir les rapports | Consulter les rapports |
| `rapports.export_excel` | Exporter en Excel | Exporter des rapports Excel |
| `rapports.export_pdf` | Exporter en PDF | Exporter des rapports PDF |
| `rapports.statistics` | Voir les statistiques | Accéder aux statistiques avancées |

### Modules additionnels (4 permissions)

| Code | Permission | Description |
|------|------------|-------------|
| `registre_public.manage` | Gérer le registre public | Gérer les publications |
| `carte.view` | Voir la carte | Accéder à la carte |
| `carte.export` | Exporter la carte | Exporter les données cartographiques |
| `admin.dashboard` | Dashboard admin | Accéder au tableau de bord admin |

---

## Permissions recommandées par rôle

### Chef de Service SDTD

```
✓ dossiers.create, view, edit, list, view_all, localisation
✓ commission.create, view, edit
✓ visa.chef_service, view
✓ documents.view, upload, download
✓ huitaine.view, create
✓ gps.view, edit, import, validate
✓ carte.view
✓ inspections.view
```

### Billeteur DPPG

```
✓ dossiers.view, list
✓ paiements.view, create, edit, receipt
✓ documents.view, download
```

### Cadre DAJ

```
✓ dossiers.view, list
✓ daj.view, create, edit, validate
✓ documents.view, download
✓ huitaine.view, regularize
```

### Inspecteur DPPG

```
✓ dossiers.view, list
✓ inspections.view, create, edit, print
✓ documents.view, upload, download
✓ huitaine.view, regularize
✓ gps.view
✓ carte.view
```

### Chef de Commission

```
✓ dossiers.view, list
✓ commission.view, validate
✓ inspections.view, validate
✓ documents.view, download
```

### Sous-Directeur SDTD

```
✓ dossiers.view, list, view_all
✓ visa.sous_directeur, view
✓ documents.view, download
✓ rapports.view
```

### Directeur DPPG

```
✓ dossiers.view, list, view_all, export
✓ visa.directeur, view
✓ decisions.view, transmit
✓ documents.view, download
✓ rapports.view, export_excel, export_pdf, statistics
✓ carte.view, export
```

### Ministre / Cabinet

```
✓ dossiers.view, list, view_all
✓ decisions.view, create
✓ documents.view, download
✓ rapports.view
```

### Admin Système

**Toutes les permissions automatiquement** (pas de configuration nécessaire)

---

## Guide d'utilisation

### 1. Accéder à la gestion des permissions

1. Se connecter en tant qu'**Admin**
2. Aller dans **Administration > Permissions**
3. La liste de tous les utilisateurs s'affiche avec le nombre de permissions

### 2. Attribuer des permissions à un utilisateur

1. Cliquer sur **"Gérer les permissions"** pour l'utilisateur souhaité
2. Consulter les **permissions recommandées** (marquées avec ⭐)
3. Sélectionner les permissions souhaitées :
   - **Tout sélectionner** : Toutes les permissions
   - **Tout désélectionner** : Aucune permission
   - **Permissions recommandées** : Permissions standards pour le rôle
4. Cliquer sur **"Enregistrer les permissions"**
5. Confirmer l'attribution

### 3. Utiliser les boutons rapides

#### Tout sélectionner
Coche toutes les cases de permissions (rarement utilisé, préférer les permissions recommandées)

#### Tout désélectionner
Décoche toutes les cases (pour recommencer la sélection)

#### Permissions recommandées ⭐
Applique automatiquement les permissions standards pour le rôle de l'utilisateur

### 4. Comprendre les statistiques

**Sur la page principale :**
- **Permissions disponibles** : Nombre total de permissions dans le système
- **Utilisateurs avec permissions** : Nombre d'utilisateurs ayant au moins une permission
- **Modules couverts** : Nombre de modules avec permissions

**Sur la page de gestion :**
- **Permissions actuelles** : Nombre de permissions attribuées à l'utilisateur
- **Permissions recommandées** : Nombre de permissions recommandées pour son rôle
- **Total disponible** : Nombre total de permissions disponibles

---

## Fonctions PHP pour développeurs

### Vérifier une permission

```php
// Dans une page PHP
if (hasPermission('dossiers.create')) {
    // L'utilisateur peut créer un dossier
}

// Dans un template
<?php if (hasPermission('documents.upload')): ?>
    <button>Uploader un document</button>
<?php endif; ?>
```

### Vérifier plusieurs permissions (OU logique)

```php
if (hasAnyPermission(['dossiers.view', 'dossiers.edit'])) {
    // L'utilisateur a au moins une de ces permissions
}
```

### Récupérer toutes les permissions d'un utilisateur

```php
$permissions = getUserPermissions($user_id);
foreach ($permissions as $perm) {
    echo $perm['nom']; // Nom de la permission
}
```

### Attribuer une permission programmatiquement

```php
assignPermission($user_id, $permission_id, $assigned_by);
```

### Synchroniser toutes les permissions d'un utilisateur

```php
$permission_ids = [1, 5, 10, 15]; // IDs des permissions
syncUserPermissions($user_id, $permission_ids, $assigned_by);
```

### Appliquer les permissions recommandées

```php
applyRecommendedPermissions($user_id, $role, $assigned_by);
```

---

## Cas d'usage

### Cas 1 : Nouvel utilisateur

**Situation** : Un nouveau cadre DPPG rejoint l'équipe

**Procédure** :
1. L'admin crée le compte avec le rôle `cadre_dppg`
2. L'admin va dans **Administration > Permissions**
3. Clique sur **"Gérer les permissions"** pour ce nouvel utilisateur
4. Clique sur **"Permissions recommandées"** ⭐
5. Vérifie et ajuste si nécessaire
6. Enregistre

**Résultat** : L'utilisateur a toutes les permissions standard d'un inspecteur DPPG

### Cas 2 : Responsabilités étendues

**Situation** : Un cadre DAJ doit également pouvoir consulter les inspections

**Procédure** :
1. Gérer les permissions du cadre DAJ
2. Garder les permissions DAJ recommandées
3. Ajouter manuellement `inspections.view`
4. Enregistrer

**Résultat** : L'utilisateur conserve ses permissions DAJ + peut voir les inspections

### Cas 3 : Utilisateur temporaire

**Situation** : Un stagiaire doit seulement consulter des dossiers

**Procédure** :
1. Créer le compte avec un rôle minimal (ex: `cadre_dppg`)
2. Gérer les permissions
3. **Tout désélectionner**
4. Cocher uniquement `dossiers.view` et `dossiers.list`
5. Enregistrer

**Résultat** : Accès très limité en lecture seule

### Cas 4 : Transfert de responsabilités

**Situation** : Un utilisateur remplace un collègue

**Procédure** :
1. Noter l'ID ou le nom de l'utilisateur source
2. Utiliser la fonction **"Copier d'un autre utilisateur"** (à venir)
3. Ou recréer manuellement les mêmes permissions

**Résultat** : Le nouvel utilisateur a exactement les mêmes accès

---

## Bonnes pratiques

### ✅ À FAIRE

1. **Utiliser les permissions recommandées** comme point de départ
2. **Documenter** les permissions non-standard attribuées
3. **Réviser régulièrement** les permissions des utilisateurs
4. **Révoquer** immédiatement les permissions des utilisateurs qui quittent
5. **Tester** les accès après attribution

### ❌ À ÉVITER

1. **Ne pas attribuer trop de permissions** "au cas où"
2. **Ne pas laisser des permissions inutiles** actives
3. **Ne pas donner des permissions admin** à des non-admins
4. **Ne pas oublier** de mettre à jour les permissions lors de changements de poste

---

## Dépannage

### Problème : Un utilisateur ne peut pas accéder à une page

**Solution** :
1. Vérifier que l'utilisateur est actif
2. Vérifier qu'il a la permission requise
3. Consulter les logs d'accès
4. Vérifier le code de la page (fonction `hasPermission()`)

### Problème : Les permissions recommandées ne s'appliquent pas

**Solution** :
1. Vérifier que le rôle est bien défini dans `getRecommendedPermissionsByRole()`
2. Vérifier que les codes de permissions existent dans la table
3. Consulter les logs d'erreurs PHP

### Problème : Un admin ne voit pas le menu Permissions

**Solution** :
1. Vérifier que le rôle est bien `admin`
2. Vider le cache du navigateur
3. Se déconnecter et reconnecter

---

## Sécurité

### Règles de sécurité

1. **Seuls les admins** peuvent gérer les permissions
2. **Les admins ont automatiquement toutes les permissions** (pas de configuration)
3. **Chaque attribution est tracée** (qui, quand, par qui)
4. **Les permissions sont vérifiées à chaque requête**

### Audit trail

Toutes les attributions/révocations sont enregistrées dans `user_permissions` avec :
- L'utilisateur concerné
- La permission
- L'admin qui a effectué le changement
- La date/heure

---

## Évolutions futures

### Fonctionnalités prévues

- ✓ Système de permissions granulaires (v1.0)
- ⏳ Copie de permissions entre utilisateurs
- ⏳ Groupes de permissions personnalisés
- ⏳ Templates de permissions par département
- ⏳ Logs d'utilisation des permissions
- ⏳ Permissions temporaires avec date d'expiration

---

## Support

### Questions fréquentes

**Q : Les admins doivent-ils avoir des permissions attribuées ?**
R : Non, les admins ont automatiquement toutes les permissions.

**Q : Que se passe-t-il si je supprime toutes les permissions d'un utilisateur ?**
R : Il ne pourra accéder qu'à son profil et se déconnecter.

**Q : Puis-je créer de nouvelles permissions ?**
R : Oui, en ajoutant des entrées dans la table `permissions` (réservé aux développeurs).

**Q : Les permissions remplacent-elles les rôles ?**
R : Non, elles complètent les rôles. Le système de rôles reste la base.

### Contact

Pour toute question ou problème :
- **Admin Système** : admin@minee.cm
- **Support technique** : support@minee.cm

---

**Version** : 1.0
**Date** : 2025-11-01
**Auteur** : Équipe SGDI

# Guide de Test des Permissions - SGDI

> Guide pratique pour tester le système de contrôle d'accès et les permissions

## Objectif

Valider que chaque utilisateur ne peut accéder qu'aux fonctionnalités autorisées pour son rôle, conformément à la matrice de permissions.

---

## Prérequis

### 1. Créer des comptes de test pour chaque rôle

Utiliser l'interface admin ou exécuter ce script SQL :

```sql
-- Utilisateurs de test (mot de passe: Test1234! pour tous)
INSERT INTO users (username, email, password, role, nom, prenom, actif) VALUES
('admin_test', 'admin@test.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'Système', 1),
('chef_service_test', 'chef@test.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'chef_service', 'Chef', 'Service', 1),
('billeteur_test', 'billeteur@test.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'billeteur', 'Billeteur', 'Test', 1),
('daj_test', 'daj@test.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cadre_daj', 'Cadre', 'DAJ', 1),
('dppg_test', 'dppg@test.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cadre_dppg', 'Inspecteur', 'DPPG', 1),
('commission_test', 'commission@test.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'chef_commission', 'Chef', 'Commission', 1),
('sd_test', 'sd@test.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sous_directeur', 'Sous', 'Directeur', 1),
('dir_test', 'dir@test.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'directeur', 'Directeur', 'DPPG', 1),
('ministre_test', 'ministre@test.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ministre', 'Cabinet', 'Ministre', 1);
```

**Note** : Le mot de passe hashé correspond à `Test1234!`

### 2. Préparer un navigateur

- Utiliser le mode navigation privée pour tester plusieurs utilisateurs
- Ou utiliser plusieurs profils de navigateur
- Ou utiliser différents navigateurs (Chrome, Firefox, Edge)

### 3. URL de base

```
http://localhost/dppg-implantation/
```

---

## Plan de Test

### Test 1 : Accès sans authentification

**Objectif** : Vérifier que les pages privées redirigent vers la connexion

#### Procédure

1. Ouvrir le navigateur en mode **navigation privée**
2. Tenter d'accéder directement à ces URLs :

```
http://localhost/dppg-implantation/dashboard.php
http://localhost/dppg-implantation/modules/dossiers/create.php
http://localhost/dppg-implantation/modules/dossiers/list.php
http://localhost/dppg-implantation/modules/users/list.php
```

#### Résultat attendu

- ✅ Redirection vers `index.php` (page de connexion)
- ✅ Message flash : "Vous devez vous connecter pour accéder à cette page"

#### Résultat attendu DIFFÉRENT pour registre public

```
http://localhost/dppg-implantation/modules/registre_public/index.php
```

- ✅ **PAS de redirection** - Page accessible sans connexion

---

### Test 2 : Accès avec mauvais rôle

**Objectif** : Vérifier qu'un utilisateur ne peut pas accéder aux pages d'un autre rôle

#### Test 2.1 : Billeteur tente de créer un dossier

1. Se connecter avec `billeteur_test` / `Test1234!`
2. Tenter d'accéder à : `http://localhost/dppg-implantation/modules/dossiers/create.php`

**Résultat attendu** :
- ✅ Redirection vers `dashboard.php`
- ✅ Message flash : "Vous n'avez pas les permissions nécessaires"

#### Test 2.2 : Inspecteur DPPG tente de gérer les utilisateurs

1. Se connecter avec `dppg_test` / `Test1234!`
2. Tenter d'accéder à : `http://localhost/dppg-implantation/modules/users/list.php`

**Résultat attendu** :
- ✅ Redirection vers `dashboard.php`
- ✅ Message flash : "Vous n'avez pas les permissions nécessaires"

#### Test 2.3 : Cadre DAJ tente d'apposer visa

1. Se connecter avec `daj_test` / `Test1234!`
2. Créer un dossier test (via admin ou chef service)
3. Tenter d'accéder à : `http://localhost/dppg-implantation/modules/dossiers/apposer_visa.php?id=X`

**Résultat attendu** :
- ✅ Redirection vers `dashboard.php`
- ✅ Message flash : "Vous n'avez pas les permissions nécessaires"

---

### Test 3 : Navigation visible selon le rôle

**Objectif** : Vérifier que le menu de navigation affiche uniquement les liens autorisés

#### Pour chaque rôle, se connecter et vérifier :

| Rôle | Menu DOSSIERS | Menu PAIEMENTS | Menu ANALYSES DAJ | Menu INSPECTIONS | Menu COMMISSION | Menu VALIDATIONS | Menu UTILISATEURS | Menu ADMIN GPS |
|------|---------------|----------------|-------------------|------------------|-----------------|------------------|-------------------|----------------|
| **Admin** | ✅ Visible | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ✅ Visible | ✅ Visible |
| **Chef Service** | ✅ Visible | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ✅ Visible |
| **Billeteur** | ❌ Caché | ✅ Visible | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché |
| **Cadre DAJ** | ❌ Caché | ❌ Caché | ✅ Visible | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché |
| **Inspecteur DPPG** | ❌ Caché | ❌ Caché | ❌ Caché | ✅ Visible | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché |
| **Chef Commission** | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ✅ Visible | ❌ Caché | ❌ Caché | ❌ Caché |
| **Sous-Directeur** | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché |
| **Directeur** | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ✅ Visible | ❌ Caché | ❌ Caché |
| **Ministre** | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché | ❌ Caché |

**Vérifier dans le code source HTML** (includes/header.php) :

```php
<?php if (hasRole('admin')): ?>
    <!-- Menu visible uniquement pour admin -->
<?php endif; ?>
```

---

### Test 4 : Workflow de dossier complet

**Objectif** : Tester le workflow avec les bons rôles à chaque étape

#### Étape 1 : Création du dossier

1. Se connecter avec `chef_service_test`
2. Créer un dossier : `modules/dossiers/create.php`
3. ✅ Création réussie

#### Étape 2 : Nomination de la commission

1. Toujours connecté comme `chef_service_test`
2. Aller sur le dossier créé
3. Nommer une commission
4. ✅ Commission créée

#### Étape 3 : Enregistrement du paiement

1. **Se déconnecter** et se connecter avec `billeteur_test`
2. Aller sur le dossier
3. Enregistrer le paiement
4. ✅ Paiement enregistré

#### Étape 4 : Analyse DAJ

1. **Se déconnecter** et se connecter avec `daj_test`
2. Aller dans "Analyses DAJ"
3. Faire l'analyse du dossier
4. ✅ Analyse complétée

#### Étape 5 : Inspection

1. **Se déconnecter** et se connecter avec `dppg_test`
2. Aller dans "Inspections"
3. Réaliser l'inspection
4. ✅ Inspection complétée

#### Étape 6 : Validation commission

1. **Se déconnecter** et se connecter avec `commission_test`
2. Valider le rapport d'inspection
3. ✅ Validation commission

#### Étape 7 : Visa Chef Service

1. **Se déconnecter** et se connecter avec `chef_service_test`
2. Apposer le visa
3. ✅ Visa 1er niveau

#### Étape 8 : Visa Sous-Directeur

1. **Se déconnecter** et se connecter avec `sd_test`
2. Apposer le visa
3. ✅ Visa 2ème niveau

#### Étape 9 : Visa Directeur

1. **Se déconnecter** et se connecter avec `dir_test`
2. Apposer le visa
3. ✅ Visa 3ème niveau

#### Étape 10 : Décision Ministre

1. **Se déconnecter** et se connecter avec `ministre_test`
2. Prendre la décision (autorisation/refus)
3. ✅ Décision finale

#### Étape 11 : Vérifier le registre public

1. **Ouvrir une fenêtre navigation privée** (sans connexion)
2. Aller sur : `modules/registre_public/index.php`
3. ✅ Le dossier autorisé apparaît dans le registre public

---

### Test 5 : Protection des fichiers sensibles

**Objectif** : Vérifier que les fichiers de bibliothèque ne sont pas accessibles

#### Test 5.1 : Tentative d'accès aux functions.php

Tenter d'accéder directement (sans connexion) à :

```
http://localhost/dppg-implantation/modules/dossiers/functions.php
http://localhost/dppg-implantation/modules/users/functions.php
http://localhost/dppg-implantation/modules/paiements/functions.php
```

**Résultat attendu** :
- ✅ Erreur 403 Forbidden (grâce au .htaccess)
- Ou page blanche / erreur PHP

#### Test 5.2 : Tentative d'accès aux fichiers debug

1. Tenter d'accéder sans connexion :

```
http://localhost/dppg-implantation/modules/notes_frais/debug.php
```

**Résultat attendu** :
- ✅ Redirection vers connexion (requireLogin)

2. Se connecter avec `billeteur_test` et réessayer

**Résultat attendu** :
- ✅ Redirection vers dashboard avec message "Vous n'avez pas les permissions nécessaires"

3. Se connecter avec `admin_test` et réessayer

**Résultat attendu** :
- ✅ Page de debug accessible

---

### Test 6 : Registre public

**Objectif** : Vérifier l'accès public sans authentification

#### Procédure

1. Ouvrir le navigateur en **mode navigation privée** (sans connexion)
2. Accéder à : `http://localhost/dppg-implantation/modules/registre_public/index.php`

**Résultat attendu** :
- ✅ Page accessible sans connexion
- ✅ Liste des dossiers autorisés visible
- ✅ Recherche fonctionnelle
- ✅ Carte accessible
- ✅ Statistiques visibles

#### Pages publiques à tester :

```
/modules/registre_public/index.php       ✅ Accessible
/modules/registre_public/detail.php      ✅ Accessible
/modules/registre_public/carte.php       ✅ Accessible
/modules/registre_public/statistiques.php ✅ Accessible
/modules/registre_public/export.php      ✅ Accessible
/modules/registre_public/suivi.php       ✅ Accessible
```

---

### Test 7 : CSRF Token

**Objectif** : Vérifier la protection CSRF sur les formulaires

#### Procédure

1. Se connecter avec `chef_service_test`
2. Aller sur `modules/dossiers/create.php`
3. Ouvrir les outils de développement (F12)
4. Dans l'onglet "Console", exécuter :

```javascript
// Supprimer le token CSRF du formulaire
document.querySelector('input[name="csrf_token"]').remove();
```

5. Soumettre le formulaire

**Résultat attendu** :
- ✅ Erreur : "Token de sécurité invalide"
- ✅ Formulaire non soumis

---

### Test 8 : Session timeout

**Objectif** : Vérifier que la session expire après un certain temps

#### Procédure

1. Se connecter avec n'importe quel utilisateur
2. Attendre 30 minutes (ou modifier `session.gc_maxlifetime` dans php.ini pour tester plus rapidement)
3. Tenter d'accéder à une page privée

**Résultat attendu** :
- ✅ Redirection vers la page de connexion
- ✅ Message : "Votre session a expiré. Veuillez vous reconnecter."

---

## Checklist de validation

### Sécurité de base

- [ ] Toutes les pages privées redirigent vers connexion si non authentifié
- [ ] Les rôles sont correctement vérifiés pour chaque page
- [ ] Le registre public est accessible sans authentification
- [ ] Les fichiers `functions.php` ne sont pas accessibles directement (403)
- [ ] Les fichiers debug sont protégés par `requireRole('admin')`
- [ ] Les tokens CSRF sont vérifiés sur tous les formulaires

### Navigation par rôle

- [ ] Admin voit : Dossiers, Utilisateurs, GPS, Huitaines
- [ ] Chef Service voit : Dossiers, GPS, Huitaines, OSM
- [ ] Billeteur voit : Paiements
- [ ] Cadre DAJ voit : Analyses DAJ, Huitaines
- [ ] Inspecteur DPPG voit : Inspections, Huitaines
- [ ] Chef Commission voit : Commission
- [ ] Sous-Directeur voit : Son dashboard
- [ ] Directeur voit : Validations, Rapports
- [ ] Ministre voit : Décisions

### Workflow

- [ ] Seul Chef Service peut créer un dossier
- [ ] Seul Billeteur peut enregistrer un paiement
- [ ] Seul Cadre DAJ peut faire une analyse juridique
- [ ] Seul Inspecteur DPPG peut faire une inspection
- [ ] Seul Chef Commission peut valider un rapport
- [ ] Circuit de visa respecté : Chef Service → Sous-Directeur → Directeur
- [ ] Seul Ministre peut prendre la décision finale
- [ ] Dossiers autorisés apparaissent dans le registre public

### Fichiers sensibles

- [ ] `modules/*/functions.php` → 403 Forbidden
- [ ] `modules/notes_frais/debug.php` → Uniquement admin
- [ ] `modules/notes_frais/debug2.php` → Uniquement admin
- [ ] `modules/import_historique/check_structure.php` → Protégé

---

## Outils de test

### Extension navigateur : ModHeader

Pour tester les headers de sécurité, installer **ModHeader** et vérifier :

- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `X-Content-Type-Options: nosniff`

### Curl pour tests automatisés

```bash
# Test 1 : Accès sans auth doit rediriger
curl -I http://localhost/dppg-implantation/dashboard.php
# Doit retourner : 302 Found (redirection)

# Test 2 : Registre public accessible
curl -I http://localhost/dppg-implantation/modules/registre_public/index.php
# Doit retourner : 200 OK

# Test 3 : Fichier functions.php bloqué
curl -I http://localhost/dppg-implantation/modules/dossiers/functions.php
# Doit retourner : 403 Forbidden
```

---

## Rapport de test

### Modèle de rapport

```markdown
# Rapport de Test des Permissions - SGDI

**Date** : [DATE]
**Testeur** : [NOM]
**Version** : 1.0

## Résumé

- Tests réalisés : X/Y
- Tests réussis : X
- Tests échoués : Y
- Critiques : Z

## Détails

### Test 1 : Accès sans authentification

| URL testée | Résultat attendu | Résultat obtenu | Statut |
|------------|------------------|-----------------|--------|
| dashboard.php | Redirection connexion | ✅ Redirigé | PASS |
| ... | ... | ... | ... |

### Test 2 : Accès avec mauvais rôle

[...]

## Problèmes identifiés

1. **[CRITIQUE]** Problème X
2. **[MAJEUR]** Problème Y
3. **[MINEUR]** Problème Z

## Recommandations

- [ ] Action 1
- [ ] Action 2
```

---

## Automatisation (optionnel)

Pour automatiser les tests, créer un script PHP :

```php
<?php
// test_permissions.php
require_once 'includes/auth.php';
requireRole('admin');

$tests = [
    'Registre public accessible' => function() {
        $url = url('modules/registre_public/index.php');
        $headers = get_headers($url);
        return strpos($headers[0], '200 OK') !== false;
    },
    // Ajouter d'autres tests...
];

foreach ($tests as $name => $test) {
    echo ($test() ? '✅' : '❌') . " $name\n";
}
```

---

**Document créé par** : Admin Système
**Dernière mise à jour** : 2025-11-01
**Version** : 1.0

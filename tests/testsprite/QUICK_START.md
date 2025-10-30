# ⚡ TestSprite SGDI - Guide de Démarrage Rapide

Ce guide vous permet de lancer vos premiers tests en **moins de 10 minutes**.

---

## ✅ Étape 1: Installation (3 min)

### 1.1 Installer Node.js

Télécharger et installer Node.js 16+ depuis: https://nodejs.org/

Vérifier l'installation:
```bash
node --version
npm --version
```

### 1.2 Installer les dépendances

```bash
cd C:\wamp64\www\dppg-implantation\tests\testsprite
npm install
npx playwright install chromium
```

✅ **Checkpoint:** Vous devriez voir "Installation complete!"

---

## ✅ Étape 2: Configuration (2 min)

### 2.1 Créer la base de données de test

Ouvrir phpMyAdmin et exécuter:

```sql
CREATE DATABASE sgdi_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Puis importer le schéma:
```bash
mysql -u root sgdi_test < ../../database/schema.sql
```

### 2.2 Copier le fichier de configuration

```bash
# Windows
copy .env.test.example .env.test

# Éditer .env.test et vérifier:
BASE_URL=http://localhost/dppg-implantation
DB_NAME=sgdi_test
```

✅ **Checkpoint:** Le fichier `.env.test` existe

---

## ✅ Étape 3: Créer les Utilisateurs de Test (1 min)

```bash
node utils/db-setup.js
```

Vous devriez voir:
```
✅ Connexion à la base de données établie
✅ Administrateur Système (admin_systeme)
✅ Chef Service SDTD (chef_service)
...
🎉 Configuration terminée avec succès!
```

✅ **Checkpoint:** 10 utilisateurs créés

---

## ✅ Étape 4: Démarrer WAMP/XAMPP (30 sec)

1. Démarrer WAMP ou XAMPP
2. Vérifier que Apache et MySQL sont démarrés (icône verte)
3. Tester l'accès: http://localhost/dppg-implantation

✅ **Checkpoint:** L'application SGDI s'affiche dans le navigateur

---

## ✅ Étape 5: Lancer Vos Premiers Tests (1 min)

### Test simple - Authentification

```bash
npm run test:auth
```

Vous devriez voir:
```
Running 8 tests using 1 worker

  ✓ Authentification › Connexion réussie - Chef Service (2s)
  ✓ Authentification › Connexion réussie - Cadre DPPG (2s)
  ✓ Authentification › Connexion échouée - Mot de passe incorrect (1s)
  ...

8 passed (15s)
```

### Voir le rapport

```bash
npm run test:report
```

Un navigateur s'ouvre avec le rapport HTML détaillé! 🎉

✅ **Checkpoint:** Tous les tests passent (verts)

---

## 🎯 Prochains Tests à Essayer

### Test du Workflow Complet

```bash
npx playwright test e2e/02-workflow/workflow-complet.spec.js --headed
```

L'option `--headed` vous permet de **voir le navigateur** pendant le test!

### Test des Permissions

```bash
npx playwright test e2e/03-roles/cadre-dppg.spec.js
```

Valide que Christian ABANDA ne voit QUE ses dossiers.

### Tests de Sécurité

```bash
npm run test:security
```

Teste CSRF, SQL Injection, XSS.

---

## 📊 Commandes Essentielles

```bash
# Tous les tests
npm test

# Voir le navigateur pendant les tests
npm run test:headed

# Mode debug (pause sur chaque étape)
npm run test:debug

# Interface graphique interactive
npm run test:ui

# Tests spécifiques
npm run test:auth          # Authentification
npm run test:workflow      # Workflow
npm run test:roles         # Permissions
npm run test:security      # Sécurité

# Rapport HTML
npm run test:report

# Nettoyage après tests
node utils/cleanup.js
```

---

## 🐛 Problèmes Fréquents

### ❌ Erreur: "Cannot connect to database"

**Solution:**
1. Vérifier que MySQL est démarré
2. Vérifier que la base `sgdi_test` existe
3. Vérifier les credentials dans `.env.test`

### ❌ Erreur: "Executable doesn't exist"

**Solution:**
```bash
npx playwright install
```

### ❌ Erreur: "Target page has been closed"

**Solution:**
1. Démarrer WAMP/XAMPP
2. Vérifier que http://localhost/dppg-implantation fonctionne

### ❌ Tests échouent sur "Login"

**Solution:**
```bash
# Recréer les utilisateurs de test
node utils/db-setup.js
```

---

## 🎓 Aller Plus Loin

### 1. TestSprite Cloud (Recommandé)

Pour générer automatiquement plus de tests:

1. Créer un compte sur https://testsprite.com
2. Copier votre API Key
3. Mettre à jour `.env.test`:
   ```env
   TESTSPRITE_API_KEY=your_key_here
   ```
4. Uploader `TEST_PLAN.md` sur TestSprite
5. Laisser TestSprite générer les tests automatiquement!

### 2. Créer Vos Propres Tests

Générer du code de test en enregistrant vos actions:

```bash
npx playwright codegen http://localhost/dppg-implantation
```

Un navigateur s'ouvre et Playwright **enregistre** vos actions!

### 3. Intégration CI/CD

Ajouter les tests dans GitHub Actions pour exécution automatique à chaque commit.

Voir `INSTALLATION.md` pour le fichier `.github/workflows/playwright.yml`.

---

## 📚 Documentation Complète

- **TEST_PLAN.md** - Plan de tests détaillé avec tous les scénarios
- **INSTALLATION.md** - Guide d'installation complet
- **README.md** - Documentation générale

---

## ✨ Félicitations!

Vous avez configuré TestSprite et lancé vos premiers tests E2E! 🎉

**Prochaines étapes suggérées:**

1. ✅ Exécuter tous les tests: `npm test`
2. ⏭️ Consulter le rapport: `npm run test:report`
3. ⏭️ Lire `TEST_PLAN.md` pour voir tous les scénarios
4. ⏭️ Uploader sur TestSprite Cloud pour génération automatique
5. ⏭️ Intégrer dans votre workflow de développement

---

**Questions? Problèmes?**

Consulter `INSTALLATION.md` pour le guide complet ou `TEST_PLAN.md` pour les détails des tests.

**Bon testing! 🚀**

---

**Version:** 1.0.0
**Date:** 24 octobre 2025
**Temps de setup:** < 10 minutes

# 🚀 Installation et Configuration TestSprite pour SGDI

Guide d'installation complet pour configurer TestSprite avec Playwright et commencer les tests automatisés E2E du SGDI.

---

## 📋 Prérequis

### Logiciels requis

- **Node.js** 16+ (Télécharger: https://nodejs.org/)
- **WAMP/XAMPP** avec PHP 7.4+ et MySQL
- **Git** (pour versionner les tests)
- **Compte TestSprite** (optionnel - pour utiliser le cloud)

### Vérification

```bash
# Vérifier Node.js
node --version  # Devrait afficher v16.x ou supérieur

# Vérifier npm
npm --version   # Devrait afficher 8.x ou supérieur

# Vérifier Git
git --version
```

---

## 🔧 Installation Étape par Étape

### Étape 1: Installation des Dépendances

Ouvrir un terminal dans le dossier `tests/testsprite/`:

```bash
cd C:\wamp64\www\dppg-implantation\tests\testsprite
```

Installer Playwright et les dépendances:

```bash
npm install
```

Cette commande va installer:
- `@playwright/test` - Framework de test
- `mysql2` - Driver MySQL pour setup BDD
- `dotenv` - Gestion des variables d'environnement

### Étape 2: Installation des Navigateurs

Playwright a besoin d'installer les navigateurs pour les tests:

```bash
npx playwright install
```

Cela télécharge Chromium, Firefox et WebKit (~400 MB).

Pour installer uniquement Chromium (recommandé pour commencer):

```bash
npx playwright install chromium
```

### Étape 3: Configuration de l'Environnement

Créer le fichier `.env.test` à la racine du projet:

```bash
# Copier le template
cp .env.example tests/testsprite/.env.test
```

Éditer `.env.test`:

```env
# Base URL de l'application
BASE_URL=http://localhost/dppg-implantation

# Base de données TEST (IMPORTANTE: Utiliser une BDD séparée!)
DB_HOST=localhost
DB_NAME=sgdi_test
DB_USER=root
DB_PASSWORD=

# Email configuration (Mode test - Mailtrap recommandé)
MAILER_HOST=smtp.mailtrap.io
MAILER_PORT=2525
MAILER_USER=your_mailtrap_user
MAILER_PASSWORD=your_mailtrap_password
MAILER_FROM=noreply@sgdi-test.cm

# TestSprite API Key (si vous utilisez le cloud)
TESTSPRITE_API_KEY=votre_cle_api_ici
```

### Étape 4: Créer la Base de Données de Test

⚠️ **IMPORTANT:** Toujours utiliser une base de données de test séparée!

```sql
-- Dans MySQL (phpMyAdmin ou ligne de commande)
CREATE DATABASE sgdi_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Importer le schéma:

```bash
# Option 1: Via phpMyAdmin
# Importer database/schema.sql dans sgdi_test

# Option 2: Ligne de commande MySQL
mysql -u root -p sgdi_test < database/schema.sql
mysql -u root -p sgdi_test < database/seed_data.sql
```

### Étape 5: Créer les Données de Test

Exécuter le script de seed pour créer les utilisateurs de test:

```bash
php tests/utils/seed_test_users.php
```

Ce script crée les 10 utilisateurs de test avec les mots de passe définis dans `helpers.js`.

---

## ✅ Vérification de l'Installation

### Test 1: Vérifier Playwright

```bash
npx playwright --version
```

Devrait afficher: `Version 1.40.0` ou supérieur

### Test 2: Lancer un Test Exemple

```bash
npm test -- e2e/01-authentication/login.spec.js
```

Si tout est bien configuré, vous verrez:

```
Running 8 tests using 1 worker

  ✓ Authentification › Connexion réussie - Chef Service (3s)
  ✓ Authentification › Connexion réussie - Cadre DPPG (2s)
  ✓ Authentification › Connexion échouée - Mot de passe incorrect (2s)
  ...

8 passed (15s)
```

### Test 3: Générer un Rapport

```bash
npm run test:report
```

Ouvre un rapport HTML dans le navigateur.

---

## 🎯 Utilisation de TestSprite

### Option 1: Utiliser TestSprite Cloud (Recommandé)

#### 1. Créer un Compte TestSprite

1. Aller sur https://testsprite.com
2. S'inscrire avec votre email
3. Copier votre API Key

#### 2. Configurer la Clé API

Exécuter le script de mise à jour:

```bash
php update_testsprite_key.php
```

Ou mettre à jour manuellement dans `.claude.json`:

```json
{
  "mcpServers": {
    "testsprite": {
      "command": "npx",
      "args": ["-y", "@testsprite/mcp-server"],
      "env": {
        "API_KEY": "votre_cle_api_testsprite_ici"
      }
    }
  }
}
```

#### 3. Uploader le Plan de Tests

1. Aller sur https://testsprite.com/projects
2. Créer un nouveau projet "SGDI-DPPG"
3. Uploader `TEST_PLAN.md`
4. Laisser TestSprite générer les tests automatiquement

#### 4. Lancer les Tests dans le Cloud

Via l'interface web ou en ligne de commande:

```bash
testsprite run --project sgdi-dppg --env test
```

Les tests s'exécutent dans le cloud, résultats disponibles en 10-20 minutes.

---

### Option 2: Utiliser Playwright Localement (Sans TestSprite Cloud)

Si vous voulez exécuter les tests manuellement sans le cloud TestSprite:

#### Commandes de Base

```bash
# Tous les tests
npm test

# Tests en mode visuel (voir le navigateur)
npm run test:headed

# Mode debug (pause et inspection)
npm run test:debug

# Interface utilisateur interactive
npm run test:ui

# Tests spécifiques
npm run test:auth          # Tests authentification
npm run test:workflow      # Tests workflow
npm run test:roles         # Tests rôles
npm run test:security      # Tests sécurité
```

#### Exécuter un fichier spécifique

```bash
npx playwright test e2e/03-roles/cadre-dppg.spec.js
```

#### Mode Debug

```bash
npx playwright test --debug e2e/01-authentication/login.spec.js
```

Ouvre l'inspecteur Playwright avec pause sur chaque action.

---

## 📊 Rapports et Résultats

### Rapport HTML

Après chaque exécution, générer le rapport:

```bash
npm run test:report
```

Ouvre `test-results/html/index.html` dans le navigateur.

### Rapport JSON

Le fichier `test-results/results.json` contient les résultats au format JSON pour intégration CI/CD.

### Captures d'Écran

Les captures d'écran des tests échoués sont dans `test-results/screenshots/`.

### Vidéos

Les vidéos des tests échoués sont dans `test-results/videos/`.

---

## 🔄 Workflow de Test Recommandé

### 1. Développement Local

Pendant le développement:

```bash
# Mode interactif - voir les tests en temps réel
npm run test:ui
```

### 2. Avant de Commiter

Exécuter les tests critiques:

```bash
npm run test:auth
npm run test:workflow
npm run test:roles
```

### 3. CI/CD (Automatique)

Configurer GitHub Actions ou autre pour exécuter tous les tests à chaque push.

Exemple `.github/workflows/playwright.yml`:

```yaml
name: Playwright Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: 18

      - name: Install dependencies
        run: cd tests/testsprite && npm ci

      - name: Install Playwright browsers
        run: cd tests/testsprite && npx playwright install --with-deps

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'

      - name: Start services
        run: |
          sudo service mysql start
          php -S localhost:8000 -t . &

      - name: Run tests
        run: cd tests/testsprite && npm test

      - name: Upload test results
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: playwright-report
          path: tests/testsprite/test-results/
```

---

## 🧪 Créer Vos Propres Tests

### Utiliser TestSprite pour Générer des Tests

1. Décrire le scénario de test en langage naturel
2. TestSprite génère le code Playwright automatiquement
3. Réviser et ajuster si nécessaire
4. Commiter dans le projet

### Structure d'un Test

```javascript
const { test, expect } = require('@playwright/test');
const { login, testUsers } = require('../../utils/helpers');

test.describe('Mon Module', () => {

  test.beforeEach(async ({ page }) => {
    // Configuration avant chaque test
    await login(page, testUsers.chef_service);
  });

  test('Mon scénario de test', async ({ page }) => {
    // 1. Aller vers la page
    await page.goto('/ma-page.php');

    // 2. Interagir avec les éléments
    await page.fill('input[name="champ"]', 'valeur');
    await page.click('button[type="submit"]');

    // 3. Vérifier les résultats
    await expect(page).toHaveURL(/resultat\.php/);
    await expect(page.locator('.success')).toBeVisible();
  });

});
```

### Ajouter un Test à la Suite

1. Créer le fichier dans le bon dossier:
   - `e2e/01-authentication/` - Tests connexion
   - `e2e/02-workflow/` - Tests processus
   - `e2e/03-roles/` - Tests permissions
   - etc.

2. Nommer le fichier: `nom-descriptif.spec.js`

3. Exécuter:
   ```bash
   npx playwright test nom-descriptif.spec.js
   ```

---

## 🐛 Dépannage

### Erreur: "Executable doesn't exist"

```bash
npx playwright install
```

### Erreur: "Target page, context or browser has been closed"

Le serveur web n'est pas démarré. Vérifier que WAMP/XAMPP tourne.

### Erreur: "Cannot connect to database"

Vérifier:
1. MySQL est démarré
2. La base `sgdi_test` existe
3. Les credentials dans `.env.test` sont corrects

### Tests Lents

Désactiver le mode headed:

```bash
npm test  # Au lieu de npm run test:headed
```

### Conflicts de BDD

Utiliser un seul worker:

```javascript
// Dans playwright.config.js
workers: 1  // Au lieu de workers: 4
```

---

## 📚 Ressources

### Documentation

- **Playwright:** https://playwright.dev/
- **TestSprite:** https://testsprite.com/docs
- **Plan de Tests SGDI:** `TEST_PLAN.md`

### Tutoriels

- [Playwright Getting Started](https://playwright.dev/docs/intro)
- [TestSprite Quickstart](https://testsprite.com/quickstart)
- [Writing Good E2E Tests](https://playwright.dev/docs/best-practices)

### Support

- **Issues GitHub:** Pour reporter des bugs dans les tests
- **TestSprite Discord:** Support communauté TestSprite
- **Playwright Discord:** Support Playwright

---

## ✨ Prochaines Étapes

1. ✅ Installation terminée
2. ⏭️ Exécuter `npm test` pour lancer tous les tests
3. ⏭️ Consulter `TEST_PLAN.md` pour voir tous les scénarios
4. ⏭️ Uploader sur TestSprite Cloud pour génération auto
5. ⏭️ Configurer CI/CD pour exécution automatique

---

**Bon testing! 🎉**

Si vous rencontrez des problèmes, consultez la section Dépannage ou contactez l'équipe.

---

**Dernière mise à jour:** 24 octobre 2025
**Version:** 1.0.0

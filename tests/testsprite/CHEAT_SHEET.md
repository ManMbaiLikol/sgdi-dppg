# 🎯 TestSprite SGDI - Aide-Mémoire

Guide de référence rapide pour les commandes TestSprite et Playwright les plus utilisées.

---

## ⚡ Installation Rapide

```bash
cd tests/testsprite
npm install
npx playwright install chromium
node utils/db-setup.js
```

---

## 🧪 Commandes de Test

### Exécution

```bash
# Tous les tests
npm test

# Tests spécifiques
npm run test:auth          # Authentification
npm run test:workflow      # Workflow
npm run test:roles         # Permissions
npm run test:security      # Sécurité

# Un fichier spécifique
npx playwright test e2e/01-authentication/login.spec.js

# Un test spécifique
npx playwright test -g "Connexion Chef Service"
```

### Modes de Visualisation

```bash
# Voir le navigateur pendant les tests
npm run test:headed

# Mode debug (pause sur chaque action)
npm run test:debug

# Interface utilisateur interactive
npm run test:ui

# Rapport HTML
npm run test:report
```

### Options Avancées

```bash
# Tests en parallèle (4 workers)
npx playwright test --workers=4

# Un seul navigateur
npx playwright test --project=chromium

# Mise à jour des snapshots
npx playwright test --update-snapshots

# Trace complète
npx playwright test --trace on
```

---

## 🛠️ Utilitaires

```bash
# Setup base de données
node utils/db-setup.js

# Nettoyage après tests
node utils/cleanup.js

# Enregistrer des actions (génère du code)
npx playwright codegen http://localhost/dppg-implantation
```

---

## 📝 Écrire un Test

### Template de Base

```javascript
const { test, expect } = require('@playwright/test');
const { login, testUsers } = require('../../utils/helpers');

test.describe('Mon Module', () => {

  test('Mon scénario', async ({ page }) => {
    // 1. Setup
    await login(page, testUsers.chef_service);

    // 2. Actions
    await page.goto('/ma-page.php');
    await page.fill('input[name="champ"]', 'valeur');
    await page.click('button[type="submit"]');

    // 3. Assertions
    await expect(page).toHaveURL(/resultat\.php/);
    await expect(page.locator('.success')).toBeVisible();
  });

});
```

### Fonctions Helper Disponibles

```javascript
// Connexion
await login(page, testUsers.chef_service);
await logout(page);

// Dossiers
const dossierId = await createDossier(page, options);
await uploadDocument(page, dossierId, filePath);

// Commission
await constituerCommission(page, dossierId, membres);

// Paiement
await enregistrerPaiement(page, dossierId, montant);

// Vérifications
const isVisible = await isDossierVisible(page, dossierId);
const canAccess = await canAccessDossier(page, dossierId);
```

---

## 🎯 Sélecteurs Playwright

```javascript
// Par texte
page.locator('text=Mon texte')
page.getByText('Mon texte')

// Par rôle
page.getByRole('button', { name: 'Soumettre' })

// Par attribut
page.locator('input[name="email"]')
page.locator('[data-testid="submit"]')

// Par classe CSS
page.locator('.btn-primary')

// Combinaisons
page.locator('form >> button[type="submit"]')
```

---

## ✅ Assertions Courantes

```javascript
// URL
await expect(page).toHaveURL(/dashboard\.php/);

// Texte
await expect(page.locator('body')).toContainText('Succès');

// Visibilité
await expect(page.locator('.alert-success')).toBeVisible();
await expect(page.locator('.alert-danger')).toBeHidden();

// Valeurs
await expect(page.locator('input')).toHaveValue('valeur');

// Attributs
await expect(page.locator('button')).toBeDisabled();
await expect(page.locator('button')).toBeEnabled();

// Comptage
await expect(page.locator('tr')).toHaveCount(5);
```

---

## 🔍 Debug

```javascript
// Pause pour inspecter
await page.pause();

// Console log
console.log(await page.locator('.title').textContent());

// Screenshot
await page.screenshot({ path: 'screenshot.png' });

// Attendre (à éviter)
await page.waitForTimeout(2000);

// Mieux: attendre un élément
await page.waitForSelector('.success');
```

---

## 📊 Rapports

### Générer un Rapport

```bash
npm run test:report
```

### Fichiers de Sortie

```
test-results/
├── html/                 # Rapport HTML
├── screenshots/          # Screenshots des échecs
├── videos/              # Vidéos des échecs
├── results.json         # Résultats JSON
└── junit.xml           # Format JUnit (CI/CD)
```

---

## 🌐 Navigateurs

```bash
# Installer tous les navigateurs
npx playwright install

# Un seul navigateur
npx playwright install chromium
npx playwright install firefox
npx playwright install webkit

# Tester sur un navigateur spécifique
npx playwright test --project=firefox
```

---

## 🔧 Configuration

### Fichier: `playwright.config.js`

```javascript
timeout: 60000,              // Timeout global (60s)
workers: 1,                  // Nombre de workers (parallel)
retries: 0,                  // Retry sur échec
use: {
  baseURL: 'http://localhost/dppg-implantation',
  screenshot: 'only-on-failure',
  video: 'retain-on-failure',
}
```

### Fichier: `.env.test`

```env
BASE_URL=http://localhost/dppg-implantation
DB_NAME=sgdi_test
DB_USER=root
DB_PASSWORD=
```

---

## 🐛 Problèmes Fréquents

### Tests Échouent

```bash
# Vérifier que WAMP/XAMPP est démarré
# Vérifier que la BDD existe
mysql -u root -e "SHOW DATABASES LIKE 'sgdi_test'"

# Recréer les utilisateurs
node utils/db-setup.js
```

### Navigateur Non Trouvé

```bash
npx playwright install
```

### Port Déjà Utilisé

```bash
# Changer le port dans .env.test
BASE_URL=http://localhost:8080/dppg-implantation
```

---

## 📚 Ressources

### Documentation

- **Playwright:** https://playwright.dev/docs/intro
- **TestSprite:** https://testsprite.com/docs
- **Plan de Tests:** `TEST_PLAN.md`
- **Installation:** `INSTALLATION.md`
- **Démarrage Rapide:** `QUICK_START.md`

### Exemples

```bash
# Tous les exemples dans e2e/
tests/testsprite/e2e/01-authentication/login.spec.js
tests/testsprite/e2e/02-workflow/workflow-complet.spec.js
tests/testsprite/e2e/03-roles/cadre-dppg.spec.js
```

---

## ✨ Raccourcis Utiles

```bash
# Alias à ajouter dans votre terminal

# Windows (PowerShell)
Set-Alias pt "npx playwright test"
Set-Alias pth "npx playwright test --headed"
Set-Alias ptd "npx playwright test --debug"

# Linux/Mac (bash/zsh)
alias pt='npx playwright test'
alias pth='npx playwright test --headed'
alias ptd='npx playwright test --debug'
alias ptr='npm run test:report'
```

Puis utiliser:
```bash
pt                    # Au lieu de npx playwright test
pth                   # Tests en mode headed
ptd login.spec.js     # Debug d'un test
ptr                   # Rapport
```

---

## 🎯 Checklist Quotidienne

Avant de committer du code:

```bash
# 1. Lancer tests critiques
npm run test:auth
npm run test:workflow

# 2. Vérifier le rapport
npm run test:report

# 3. Si OK, committer
git add .
git commit -m "feat: nouvelle fonctionnalité + tests"
```

---

**Version:** 1.0.0
**Dernière mise à jour:** 24 octobre 2025

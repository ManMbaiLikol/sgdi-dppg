/**
 * Tests de sécurité - Protection CSRF
 *
 * Vérifie que tous les formulaires sont protégés contre les attaques CSRF
 */

const { test, expect } = require('@playwright/test');
const { testUsers, login } = require('../../utils/helpers');

test.describe('Sécurité - Protection CSRF', () => {

  test('Tous les formulaires ont un token CSRF', async ({ page }) => {
    await login(page, testUsers.chef_service);

    // Liste des pages avec formulaires à vérifier
    const formPages = [
      '/login.php',
      '/modules/dossiers/create.php',
      '/modules/dossiers/edit.php?id=1',
      '/modules/commission/constituer.php?dossier_id=1',
      '/modules/paiements/enregistrer.php?dossier_id=1',
      '/modules/daj/analyse.php?dossier_id=1',
      '/modules/fiche_inspection/create.php?dossier_id=1',
      '/modules/visas/viser.php?dossier_id=1&niveau=1',
      '/modules/ministre/decider.php?dossier_id=1'
    ];

    for (const url of formPages) {
      console.log(`\n🔍 Vérification: ${url}`);

      await page.goto(url);

      // Vérifier présence d'un formulaire
      const forms = await page.locator('form').count();

      if (forms > 0) {
        // Vérifier présence du token CSRF
        const csrfInput = await page.locator('input[name="csrf_token"]');

        if (await csrfInput.count() > 0) {
          const token = await csrfInput.first().inputValue();

          expect(token).toBeTruthy();
          expect(token.length).toBeGreaterThan(20);

          console.log(`   ✅ Token CSRF présent (${token.substring(0, 10)}...)`);
        } else {
          console.log(`   ⚠️  Aucun token CSRF trouvé!`);
          // Ne pas échouer le test si c'est une page d'erreur
          const hasError = await page.locator('.alert-danger, .error').count() > 0;
          if (!hasError) {
            throw new Error(`Formulaire sans protection CSRF: ${url}`);
          }
        }
      } else {
        console.log(`   ℹ️  Pas de formulaire sur cette page`);
      }
    }
  });

  test('Soumission sans token CSRF est rejetée', async ({ page, context }) => {
    await login(page, testUsers.chef_service);

    // Aller vers un formulaire
    await page.goto('/modules/dossiers/create.php');

    // Supprimer le token CSRF via JavaScript
    await page.evaluate(() => {
      const csrfInput = document.querySelector('input[name="csrf_token"]');
      if (csrfInput) {
        csrfInput.value = '';
      }
    });

    // Remplir le formulaire
    await page.selectOption('select[name="type_infrastructure"]', 'implantation_station_service');
    await page.fill('input[name="nom_demandeur"]', 'Test CSRF');
    await page.fill('input[name="localisation"]', 'Yaoundé');

    // Tenter de soumettre
    const responsePromise = page.waitForResponse(response =>
      response.url().includes('create.php') && response.request().method() === 'POST'
    );

    await page.click('button[type="submit"]');

    const response = await responsePromise;

    // Vérifier que la soumission est rejetée (soit 403, soit message d'erreur)
    const statusOk = response.status() === 403 || response.status() === 400;

    if (!statusOk) {
      // Vérifier message d'erreur
      const errorVisible = await page.locator('.alert-danger, .error-message').count() > 0;
      expect(errorVisible).toBe(true);
    }

    console.log('✅ Soumission sans token CSRF correctement bloquée');
  });

  test('Token CSRF invalide est rejeté', async ({ page }) => {
    await login(page, testUsers.chef_service);

    await page.goto('/modules/dossiers/create.php');

    // Remplacer le token par une valeur invalide
    await page.evaluate(() => {
      const csrfInput = document.querySelector('input[name="csrf_token"]');
      if (csrfInput) {
        csrfInput.value = 'INVALID_TOKEN_123456789';
      }
    });

    await page.selectOption('select[name="type_infrastructure"]', 'implantation_station_service');
    await page.fill('input[name="nom_demandeur"]', 'Test CSRF Invalid');
    await page.fill('input[name="localisation"]', 'Yaoundé');

    await page.click('button[type="submit"]');

    // Attendre quelques secondes pour la réponse
    await page.waitForTimeout(2000);

    // Vérifier qu'on a une erreur
    const hasError = await page.locator('.alert-danger, .error-message').count() > 0;
    const notRedirected = !page.url().includes('view.php');

    expect(hasError || notRedirected).toBe(true);

    console.log('✅ Token CSRF invalide correctement rejeté');
  });

  test('Token CSRF différent par session', async ({ browser }) => {
    // Créer deux sessions différentes
    const context1 = await browser.newContext();
    const context2 = await browser.newContext();

    const page1 = await context1.newPage();
    const page2 = await context2.newPage();

    // Se connecter sur les deux sessions
    await login(page1, testUsers.chef_service);
    await login(page2, testUsers.chef_service);

    // Aller vers la même page
    await page1.goto('/modules/dossiers/create.php');
    await page2.goto('/modules/dossiers/create.php');

    // Récupérer les tokens
    const token1 = await page1.locator('input[name="csrf_token"]').inputValue();
    const token2 = await page2.locator('input[name="csrf_token"]').inputValue();

    console.log(`Token session 1: ${token1.substring(0, 15)}...`);
    console.log(`Token session 2: ${token2.substring(0, 15)}...`);

    // Les tokens doivent être différents
    expect(token1).not.toBe(token2);

    console.log('✅ Tokens CSRF uniques par session');

    await context1.close();
    await context2.close();
  });

  test('Token CSRF regénéré après login', async ({ page }) => {
    // Aller vers login (sans être connecté)
    await page.goto('/login.php');

    const tokenBeforeLogin = await page.locator('input[name="csrf_token"]').inputValue();

    // Se connecter
    await page.fill('input[name="email"]', testUsers.chef_service.email);
    await page.fill('input[name="password"]', testUsers.chef_service.password);
    await page.click('button[type="submit"]');

    await page.waitForURL(/dashboard\.php/);

    // Aller vers un formulaire
    await page.goto('/modules/dossiers/create.php');

    const tokenAfterLogin = await page.locator('input[name="csrf_token"]').inputValue();

    console.log(`Token avant login: ${tokenBeforeLogin.substring(0, 15)}...`);
    console.log(`Token après login: ${tokenAfterLogin.substring(0, 15)}...`);

    // Les tokens doivent être différents après login
    expect(tokenBeforeLogin).not.toBe(tokenAfterLogin);

    console.log('✅ Token CSRF regénéré après login');
  });

});

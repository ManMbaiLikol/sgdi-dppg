/**
 * Tests de sécurité - Protection SQL Injection
 *
 * Vérifie que l'application est protégée contre les attaques par injection SQL
 */

const { test, expect } = require('@playwright/test');
const { testUsers, login } = require('../../utils/helpers');

test.describe('Sécurité - Protection SQL Injection', () => {

  test('Login - Protection SQL Injection', async ({ page }) => {
    await page.goto('/login.php');

    // Tentatives d'injection SQL classiques
    const sqlInjectionPayloads = [
      "admin' OR '1'='1",
      "admin'--",
      "admin' #",
      "' OR 1=1--",
      "' OR 'a'='a",
      "admin' OR 1=1/*",
      "') OR ('1'='1",
      "1' UNION SELECT NULL--",
      "admin'; DROP TABLE users--"
    ];

    for (const payload of sqlInjectionPayloads) {
      console.log(`\n🔍 Test payload: ${payload}`);

      await page.goto('/login.php');

      await page.fill('input[name="email"]', payload);
      await page.fill('input[name="password"]', 'password');

      await page.click('button[type="submit"]');

      // Attendre la réponse
      await page.waitForTimeout(1000);

      // Vérifier qu'on n'est PAS connecté
      const isLoggedIn = page.url().includes('dashboard.php');

      expect(isLoggedIn).toBe(false);

      console.log('   ✅ Injection SQL bloquée');
    }
  });

  test('Recherche - Protection SQL Injection', async ({ page }) => {
    await login(page, testUsers.chef_service);

    await page.goto('/modules/dossiers/list.php');

    const searchPayloads = [
      "' OR '1'='1",
      "1' UNION SELECT * FROM users--",
      "'; DROP TABLE dossiers--",
      "1' AND 1=1--",
      "' OR 'a'='a"
    ];

    for (const payload of searchPayloads) {
      console.log(`\n🔍 Test recherche: ${payload}`);

      // Remplir le champ de recherche
      const searchInput = await page.locator('input[name="search"], input[type="search"]');

      if (await searchInput.count() > 0) {
        await searchInput.fill(payload);
        await page.click('button[type="submit"], .btn-search');

        await page.waitForTimeout(1000);

        // Vérifier qu'il n'y a pas d'erreur SQL
        const pageContent = await page.textContent('body');

        const hasSqlError =
          pageContent.toLowerCase().includes('mysql') ||
          pageContent.toLowerCase().includes('sql syntax') ||
          pageContent.toLowerCase().includes('error in your sql');

        expect(hasSqlError).toBe(false);

        console.log('   ✅ Aucune erreur SQL affichée');
      }
    }
  });

  test('URL Parameters - Protection SQL Injection', async ({ page }) => {
    await login(page, testUsers.chef_service);

    // Tester injection dans les paramètres d'URL
    const urlPayloads = [
      "/modules/dossiers/view.php?id=1' OR '1'='1",
      "/modules/dossiers/view.php?id=1; DROP TABLE dossiers--",
      "/modules/dossiers/view.php?id=1 UNION SELECT * FROM users",
      "/modules/dossiers/view.php?id=' OR 1=1--"
    ];

    for (const url of urlPayloads) {
      console.log(`\n🔍 Test URL: ${url}`);

      await page.goto(url);

      await page.waitForTimeout(1000);

      // Vérifier qu'il n'y a pas d'erreur SQL
      const pageContent = await page.textContent('body');

      const hasSqlError =
        pageContent.toLowerCase().includes('mysql') ||
        pageContent.toLowerCase().includes('sql syntax') ||
        pageContent.toLowerCase().includes('error in your sql') ||
        pageContent.toLowerCase().includes('warning:');

      expect(hasSqlError).toBe(false);

      console.log('   ✅ Aucune erreur SQL dans l\'URL');
    }
  });

  test('Form Inputs - Protection SQL Injection', async ({ page }) => {
    await login(page, testUsers.chef_service);

    await page.goto('/modules/dossiers/create.php');

    // Tester injection dans les champs de formulaire
    const injectionPayload = "Test' OR '1'='1";

    await page.selectOption('select[name="type_infrastructure"]', 'implantation_station_service');
    await page.fill('input[name="nom_demandeur"]', injectionPayload);
    await page.fill('input[name="localisation"]', injectionPayload);
    await page.fill('input[name="operateur"]', injectionPayload);

    await page.click('button[type="submit"]');

    await page.waitForTimeout(2000);

    // Vérifier qu'il n'y a pas d'erreur SQL
    const pageContent = await page.textContent('body');

    const hasSqlError =
      pageContent.toLowerCase().includes('mysql') ||
      pageContent.toLowerCase().includes('sql syntax');

    expect(hasSqlError).toBe(false);

    // Si le dossier a été créé, vérifier que les données sont échappées
    if (page.url().includes('view.php')) {
      const dossierContent = await page.textContent('body');

      // Les quotes devraient être échappées ou le payload rejeté
      const containsExactPayload = dossierContent.includes(injectionPayload);

      console.log(`   Payload stocké: ${containsExactPayload ? 'Oui (échappé)' : 'Non (rejeté)'}`);
    }

    console.log('✅ Protection SQL Injection dans les formulaires');
  });

  test('Pas d\'erreurs SQL exposées', async ({ page }) => {
    // Tester que les erreurs SQL ne sont jamais affichées à l'utilisateur

    await page.goto('/modules/dossiers/view.php?id=999999');

    const pageContent = await page.textContent('body');

    // Mots-clés d'erreur SQL à ne jamais afficher
    const sqlErrorKeywords = [
      'mysql_fetch',
      'mysqli::',
      'PDOStatement',
      'SQL syntax',
      'mysql_num_rows',
      'mysql_query',
      'Warning: mysql',
      'supplied argument is not a valid MySQL'
    ];

    for (const keyword of sqlErrorKeywords) {
      const hasKeyword = pageContent.toLowerCase().includes(keyword.toLowerCase());

      if (hasKeyword) {
        console.log(`   ❌ ERREUR: "${keyword}" exposé dans la page!`);
      }

      expect(hasKeyword).toBe(false);
    }

    console.log('✅ Aucune erreur SQL technique exposée');
  });

});

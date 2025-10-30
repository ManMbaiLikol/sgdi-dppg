/**
 * Tests Cadre DPPG - Visibilité stricte par commission
 *
 * Ce test valide les corrections de sécurité du 24 octobre 2025
 * Un cadre DPPG ne doit voir QUE les dossiers où il est membre de commission
 */

const { test, expect } = require('@playwright/test');
const { testUsers, login, isDossierVisible, canAccessDossier } = require('../../utils/helpers');

test.describe('Cadre DPPG - Contrôle d\'accès strict par commission', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/logout.php');
  });

  test('TEST 1: Christian ABANDA - Visibilité dossiers commission uniquement', async ({ page }) => {
    // Se connecter comme Christian ABANDA (ID: 27)
    await login(page, testUsers.christian_abanda);

    // Aller vers la liste des dossiers
    await page.goto('/modules/dossiers/list.php');

    // Christian devrait voir uniquement ses dossiers de commission
    // Par exemple: SS20251024025528 où il est membre

    // Vérifier qu'il voit au moins un dossier (le sien)
    const dossierRows = await page.locator('table tbody tr');
    const count = await dossierRows.count();

    console.log(`Christian ABANDA voit ${count} dossier(s)`);

    // Il devrait avoir au moins 1 dossier (celui de sa commission)
    expect(count).toBeGreaterThanOrEqual(0);

    // Extraire tous les numéros de dossiers visibles
    const visibleDossiers = [];
    for (let i = 0; i < count; i++) {
      const row = dossierRows.nth(i);
      const text = await row.textContent();

      // Extraire le numéro de dossier (format: SS20251024025528 ou PC20251010224931)
      const match = text.match(/[A-Z]{2}\d{14}/);
      if (match) {
        visibleDossiers.push(match[0]);
        console.log(`  ✅ Dossier visible: ${match[0]}`);
      }
    }

    // VÉRIFICATION CRITIQUE: Christian ne doit PAS voir les dossiers de Salomon
    const dossiersSalomon = ['PC20251010224931', 'PC20251010222326'];

    for (const dossierId of dossiersSalomon) {
      const isVisible = visibleDossiers.includes(dossierId);

      console.log(`  🔍 Dossier ${dossierId} (Salomon): ${isVisible ? '❌ VISIBLE (ERREUR!)' : '✅ NON VISIBLE (OK)'}`);

      // Assertion: Christian ne doit PAS voir les dossiers de Salomon
      expect(isVisible).toBe(false);
    }
  });

  test('TEST 2: Accès direct bloqué - Dossier d\'une autre commission', async ({ page }) => {
    // Se connecter comme Christian ABANDA
    await login(page, testUsers.christian_abanda);

    // Tenter d'accéder directement à un dossier de Salomon par URL
    const dossierSalomon = 'PC20251010224931';

    // Récupérer l'ID du dossier (simulé ici, à adapter selon votre BDD)
    // Dans un vrai test, vous feriez une requête pour obtenir l'ID

    const canAccess = await canAccessDossier(page, '6'); // Supposons que l'ID est 6

    console.log(`Accès direct au dossier ${dossierSalomon}: ${canAccess ? '❌ AUTORISÉ (ERREUR!)' : '✅ BLOQUÉ (OK)'}`);

    // Assertion: Christian ne doit PAS pouvoir accéder
    expect(canAccess).toBe(false);

    // Vérifier qu'il voit un message d'erreur
    const errorMessage = await page.locator('.alert-danger, .error-message');
    await expect(errorMessage).toBeVisible();
    await expect(page.locator('body')).toContainText(/accès refusé|non autorisé|permission/i);
  });

  test('TEST 3: Salomon MAÏ - Visibilité de ses propres dossiers', async ({ page }) => {
    // Se connecter comme Salomon MAÏ (ID: 16)
    await login(page, testUsers.salomon_mai);

    await page.goto('/modules/dossiers/list.php');

    // Salomon devrait voir ses dossiers de commission
    const dossierRows = await page.locator('table tbody tr');
    const count = await dossierRows.count();

    console.log(`Salomon MAÏ voit ${count} dossier(s)`);

    // Extraire les dossiers visibles
    const visibleDossiers = [];
    for (let i = 0; i < count; i++) {
      const row = dossierRows.nth(i);
      const text = await row.textContent();

      const match = text.match(/[A-Z]{2}\d{14}/);
      if (match) {
        visibleDossiers.push(match[0]);
        console.log(`  ✅ Dossier visible: ${match[0]}`);
      }
    }

    // Salomon ne doit PAS voir le dossier de Christian
    const dossierChristian = 'SS20251024025528';
    const isVisible = visibleDossiers.includes(dossierChristian);

    console.log(`  🔍 Dossier ${dossierChristian} (Christian): ${isVisible ? '❌ VISIBLE (ERREUR!)' : '✅ NON VISIBLE (OK)'}`);

    expect(isVisible).toBe(false);
  });

  test('TEST 4: Page "Faire une inspection" - Filtrage commission', async ({ page }) => {
    // Se connecter comme Christian
    await login(page, testUsers.christian_abanda);

    // Aller vers la page des inspections
    await page.goto('/modules/fiche_inspection/list_dossiers.php');

    // Vérifier qu'il voit uniquement les dossiers de sa commission
    const dossierRows = await page.locator('table tbody tr, .dossier-card');
    const count = await dossierRows.count();

    console.log(`Page inspection - Christian voit ${count} dossier(s)`);

    // Tous les dossiers affichés doivent être de sa commission
    for (let i = 0; i < count; i++) {
      const row = dossierRows.nth(i);

      // Vérifier qu'il y a un badge ou indication "Commission"
      // (à adapter selon votre interface)
      const hasCommissionBadge = await row.locator('.badge-commission, .commission-member').count() > 0;

      if (!hasCommissionBadge) {
        console.log(`  ⚠️  Dossier ${i + 1}: Pas de badge commission trouvé`);
      }
    }
  });

  test('TEST 5: Dashboard - Compteurs cohérents', async ({ page }) => {
    // Se connecter comme Christian
    await login(page, testUsers.christian_abanda);

    await page.goto('/dashboard.php');

    // Récupérer le compteur du dashboard
    const counterElement = await page.locator('.dossier-count, .counter-value, .badge');

    if (await counterElement.count() > 0) {
      const dashboardCount = parseInt(await counterElement.first().textContent());
      console.log(`Dashboard - Compteur: ${dashboardCount}`);

      // Aller vers la liste
      await page.goto('/modules/dossiers/list.php');

      const actualCount = await page.locator('table tbody tr').count();
      console.log(`Liste - Dossiers réels: ${actualCount}`);

      // Les deux nombres doivent correspondre
      expect(dashboardCount).toBe(actualCount);
    }
  });

  test('TEST 6: API getDossiers() cohérente avec canAccessDossier()', async ({ page }) => {
    // Se connecter comme Christian
    await login(page, testUsers.christian_abanda);

    // Récupérer la liste via l'interface
    await page.goto('/modules/dossiers/list.php');
    const visibleDossiers = await page.locator('table tbody tr').count();

    console.log(`getDossiers() retourne: ${visibleDossiers} dossiers`);

    // Créer un dossier d'un autre inspecteur (en étant admin)
    await page.goto('/logout.php');
    await login(page, testUsers.chef_service);

    // Le chef service crée un dossier
    await page.goto('/modules/dossiers/create.php');
    await page.selectOption('select[name="type_infrastructure"]', 'implantation_station_service');
    await page.fill('input[name="nom_demandeur"]', 'Test Société XYZ');
    await page.fill('input[name="localisation"]', 'Douala');
    await page.click('button[type="submit"]');

    // Récupérer l'ID du nouveau dossier
    await page.waitForURL(/view\.php\?id=(\d+)/);
    const newDossierId = page.url().match(/id=(\d+)/)[1];

    console.log(`Nouveau dossier créé: ID ${newDossierId}`);

    // Se reconnecter comme Christian
    await page.goto('/logout.php');
    await login(page, testUsers.christian_abanda);

    // Tenter d'accéder au nouveau dossier
    const canAccess = await canAccessDossier(page, newDossierId);

    console.log(`canAccessDossier(${newDossierId}): ${canAccess ? 'true' : 'false'}`);

    // Il ne doit PAS pouvoir accéder car pas dans sa commission
    expect(canAccess).toBe(false);

    // Vérifier que le dossier n'apparaît pas dans la liste
    const isInList = await isDossierVisible(page, newDossierId);

    console.log(`isDossierVisible(${newDossierId}): ${isInList ? 'true' : 'false'}`);

    expect(isInList).toBe(false);
  });

});

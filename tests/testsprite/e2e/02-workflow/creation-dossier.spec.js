/**
 * Tests Workflow - Création de dossier (Étape 1)
 *
 * Teste la création de dossiers pour différents types d'infrastructure
 */

const { test, expect } = require('@playwright/test');
const { testUsers, login, createDossier, infrastructureTypes, uploadDocument } = require('../../utils/helpers');

test.describe('Workflow Étape 1: Création Dossier', () => {

  test.beforeEach(async ({ page }) => {
    // Se connecter comme Chef Service (seul rôle autorisé à créer des dossiers)
    await page.goto('/logout.php');
    await login(page, testUsers.chef_service);
  });

  test('Créer dossier - Station-Service', async ({ page }) => {
    await page.goto('/modules/dossiers/create.php');

    // Sélectionner le type
    await page.selectOption('select[name="type_infrastructure"]', 'implantation_station_service');

    // Vérifier que les champs requis s'affichent
    await expect(page.locator('input[name="nom_demandeur"]')).toBeVisible();
    await expect(page.locator('input[name="operateur"]')).toBeVisible();

    // Vérifier que le champ "Entreprise bénéficiaire" n'est PAS visible
    const entrepriseBeneficiaire = await page.locator('input[name="entreprise_beneficiaire"]').count();
    expect(entrepriseBeneficiaire).toBe(0);

    // Remplir le formulaire
    await page.fill('input[name="nom_demandeur"]', 'Société Pétrolière du Cameroun');
    await page.fill('input[name="localisation"]', 'Yaoundé, Quartier Bastos');
    await page.fill('input[name="operateur"]', 'TOTAL Cameroun');

    // Coordonnées GPS
    await page.fill('input[name="latitude"]', '3.8480');
    await page.fill('input[name="longitude"]', '11.5021');

    // Localisation administrative
    await page.selectOption('select[name="region"]', 'Centre');
    await page.fill('input[name="departement"]', 'Mfoundi');
    await page.fill('input[name="commune"]', 'Yaoundé 1er');

    // Soumettre
    await page.click('button[type="submit"]');

    // Attendre redirection vers la page de détails
    await expect(page).toHaveURL(/view\.php\?id=\d+/);

    // Vérifier que le dossier a été créé
    await expect(page.locator('.alert-success, .success-message')).toBeVisible();

    // Extraire le numéro de dossier
    const pageContent = await page.textContent('body');
    const dossierNumber = pageContent.match(/SS\d{14}/);

    expect(dossierNumber).toBeTruthy();
    console.log(`✅ Dossier créé: ${dossierNumber[0]}`);

    // Vérifier le statut initial
    await expect(page.locator('body')).toContainText(/brouillon|en cours/i);
  });

  test('Créer dossier - Point Consommateur (avec champs supplémentaires)', async ({ page }) => {
    await page.goto('/modules/dossiers/create.php');

    await page.selectOption('select[name="type_infrastructure"]', 'implantation_point_consommateur');

    // Vérifier champs requis pour Point Consommateur
    await expect(page.locator('input[name="operateur"]')).toBeVisible();
    await expect(page.locator('input[name="entreprise_beneficiaire"]')).toBeVisible();

    // Remplir
    await page.fill('input[name="nom_demandeur"]', 'Cimenterie du Cameroun');
    await page.fill('input[name="localisation"]', 'Douala, Zone Industrielle');
    await page.fill('input[name="operateur"]', 'OILIBYA Cameroun');
    await page.fill('input[name="entreprise_beneficiaire"]', 'Cimenterie du Cameroun SA');

    // Upload du contrat de livraison (requis pour PC)
    const fileInput = await page.locator('input[type="file"][name="contrat_livraison"]');
    if (await fileInput.count() > 0) {
      await fileInput.setInputFiles('./fixtures/documents/contrat_test.pdf');
    }

    await page.fill('input[name="latitude"]', '4.0511');
    await page.fill('input[name="longitude"]', '9.7679');

    await page.selectOption('select[name="region"]', 'Littoral');

    await page.click('button[type="submit"]');

    await expect(page).toHaveURL(/view\.php\?id=\d+/);

    const pageContent = await page.textContent('body');
    const dossierNumber = pageContent.match(/PC\d{14}/);

    expect(dossierNumber).toBeTruthy();
    console.log(`✅ Dossier Point Consommateur créé: ${dossierNumber[0]}`);
  });

  test('Validation - Champs requis manquants', async ({ page }) => {
    await page.goto('/modules/dossiers/create.php');

    await page.selectOption('select[name="type_infrastructure"]', 'implantation_station_service');

    // Ne remplir que le nom du demandeur
    await page.fill('input[name="nom_demandeur"]', 'Test');

    // Tenter de soumettre
    await page.click('button[type="submit"]');

    // Devrait rester sur la même page
    await expect(page).toHaveURL(/create\.php/);

    // Vérifier message d'erreur
    const errorMessage = await page.locator('.alert-danger, .error-message, .invalid-feedback');
    await expect(errorMessage.first()).toBeVisible();
  });

  test('Upload documents - Après création', async ({ page }) => {
    // Créer un dossier d'abord
    await page.goto('/modules/dossiers/create.php');

    await page.selectOption('select[name="type_infrastructure"]', 'implantation_station_service');
    await page.fill('input[name="nom_demandeur"]', 'Société Test Upload');
    await page.fill('input[name="localisation"]', 'Yaoundé');
    await page.fill('input[name="operateur"]', 'TOTAL');
    await page.fill('input[name="latitude"]', '3.8480');
    await page.fill('input[name="longitude"]', '11.5021');

    await page.click('button[type="submit"]');

    // Récupérer l'ID du dossier
    await page.waitForURL(/view\.php\?id=(\d+)/);
    const dossierId = page.url().match(/id=(\d+)/)[1];

    // Aller vers la page d'upload
    await page.goto(`/modules/dossiers/upload_documents.php?dossier_id=${dossierId}`);

    // Vérifier que la page charge correctement
    await expect(page.locator('h1, h2')).toContainText(/upload|documents/i);

    // Uploader un document
    const fileInput = await page.locator('input[type="file"]');
    await expect(fileInput).toBeVisible();

    // Sélectionner le type de document
    await page.selectOption('select[name="type_document"]', 'demande_implantation');

    // Upload (simulé - en vrai test, utilisez un vrai fichier)
    // await fileInput.setInputFiles('./fixtures/documents/demande_test.pdf');

    console.log(`✅ Page upload accessible pour dossier ${dossierId}`);
  });

  test('Seul Chef Service peut créer des dossiers', async ({ page }) => {
    // Se déconnecter du Chef Service
    await page.goto('/logout.php');

    // Se connecter comme Cadre DPPG
    await login(page, testUsers.christian_abanda);

    // Tenter d'accéder à la page de création
    await page.goto('/modules/dossiers/create.php');

    // Devrait être bloqué ou redirigé
    const isBlocked = await page.locator('.alert-danger, .error-message').count() > 0;
    const isRedirected = !page.url().includes('create.php');

    console.log(`Accès création par Cadre DPPG: ${isBlocked || isRedirected ? '✅ BLOQUÉ' : '❌ AUTORISÉ (ERREUR!)'}`);

    expect(isBlocked || isRedirected).toBe(true);
  });

  test('Génération automatique du numéro de dossier', async ({ page }) => {
    await page.goto('/modules/dossiers/create.php');

    await page.selectOption('select[name="type_infrastructure"]', 'implantation_station_service');
    await page.fill('input[name="nom_demandeur"]', 'Test Numéro Auto');
    await page.fill('input[name="localisation"]', 'Yaoundé');
    await page.fill('input[name="operateur"]', 'TOTAL');

    await page.click('button[type="submit"]');

    await page.waitForURL(/view\.php\?id=\d+/);

    const pageContent = await page.textContent('body');

    // Vérifier format du numéro: SS + timestamp (14 chiffres)
    const dossierNumber = pageContent.match(/SS\d{14}/);

    expect(dossierNumber).toBeTruthy();

    // Vérifier que le numéro commence par SS pour Station-Service
    expect(dossierNumber[0]).toMatch(/^SS/);

    console.log(`✅ Numéro auto-généré: ${dossierNumber[0]}`);
  });

  test('Workflow - Statut initial "Brouillon"', async ({ page }) => {
    await page.goto('/modules/dossiers/create.php');

    await page.selectOption('select[name="type_infrastructure"]', 'implantation_depot_gpl');
    await page.fill('input[name="nom_demandeur"]', 'Test Statut Brouillon');
    await page.fill('input[name="localisation"]', 'Douala');

    await page.click('button[type="submit"]');

    await page.waitForURL(/view\.php\?id=\d+/);

    // Vérifier que le statut est "Brouillon"
    const statusBadge = await page.locator('.badge-status, .status-badge, .statut');

    if (await statusBadge.count() > 0) {
      const statusText = await statusBadge.first().textContent();
      console.log(`Statut initial: ${statusText}`);

      expect(statusText.toLowerCase()).toContain('brouillon');
    } else {
      // Vérifier dans le corps de page
      await expect(page.locator('body')).toContainText(/brouillon/i);
    }
  });

  test('Protection CSRF - Formulaire création', async ({ page }) => {
    await page.goto('/modules/dossiers/create.php');

    // Vérifier présence du token CSRF
    const csrfInput = await page.locator('input[name="csrf_token"]');
    await expect(csrfInput).toBeVisible();

    const csrfToken = await csrfInput.inputValue();
    expect(csrfToken).toBeTruthy();
    expect(csrfToken.length).toBeGreaterThan(10);

    console.log('✅ Token CSRF présent');
  });

});

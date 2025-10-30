/**
 * Tests d'authentification SGDI
 */

const { test, expect } = require('@playwright/test');
const { testUsers, login, logout } = require('../../utils/helpers');

test.describe('Authentification', () => {

  test.beforeEach(async ({ page }) => {
    // S'assurer qu'on est déconnecté avant chaque test
    await page.goto('/logout.php');
  });

  test('Connexion réussie - Chef Service', async ({ page }) => {
    const user = testUsers.chef_service;

    await page.goto('/login.php');

    // Remplir le formulaire
    await page.fill('input[name="email"]', user.email);
    await page.fill('input[name="password"]', user.password);

    // Soumettre
    await page.click('button[type="submit"]');

    // Vérifier redirection vers dashboard
    await expect(page).toHaveURL(/dashboard\.php/);

    // Vérifier que le nom de l'utilisateur s'affiche
    await expect(page.locator('body')).toContainText(user.name);
  });

  test('Connexion réussie - Cadre DPPG', async ({ page }) => {
    await login(page, testUsers.christian_abanda);

    // Vérifier qu'on est sur le dashboard
    await expect(page).toHaveURL(/dashboard\.php/);
    await expect(page.locator('body')).toContainText('Christian ABANDA');
  });

  test('Connexion échouée - Mot de passe incorrect', async ({ page }) => {
    await page.goto('/login.php');

    await page.fill('input[name="email"]', testUsers.chef_service.email);
    await page.fill('input[name="password"]', 'MauvaisMotDePasse123');

    await page.click('button[type="submit"]');

    // Vérifier qu'on reste sur la page de login
    await expect(page).toHaveURL(/login\.php/);

    // Vérifier message d'erreur
    await expect(page.locator('.alert-danger, .error-message')).toBeVisible();
    await expect(page.locator('body')).toContainText(/mot de passe|incorrect|invalide/i);
  });

  test('Connexion échouée - Email inexistant', async ({ page }) => {
    await page.goto('/login.php');

    await page.fill('input[name="email"]', 'inexistant@minee.cm');
    await page.fill('input[name="password"]', 'Password123');

    await page.click('button[type="submit"]');

    // Vérifier message d'erreur
    await expect(page.locator('.alert-danger, .error-message')).toBeVisible();
  });

  test('Déconnexion', async ({ page }) => {
    // Se connecter d'abord
    await login(page, testUsers.chef_service);

    // Se déconnecter
    await logout(page);

    // Vérifier qu'on est redirigé vers login
    await expect(page).toHaveURL(/login\.php/);

    // Tenter d'accéder au dashboard sans être connecté
    await page.goto('/dashboard.php');

    // Devrait être redirigé vers login
    await expect(page).toHaveURL(/login\.php/);
  });

  test('Session expirée - Redirection vers login', async ({ page }) => {
    await login(page, testUsers.chef_service);

    // Simuler expiration de session en supprimant les cookies
    await page.context().clearCookies();

    // Tenter d'accéder à une page protégée
    await page.goto('/modules/dossiers/list.php');

    // Devrait être redirigé vers login
    await expect(page).toHaveURL(/login\.php/);
  });

  test('Connexion - Tous les rôles', async ({ page }) => {
    // Tester que tous les utilisateurs peuvent se connecter
    const users = [
      testUsers.admin,
      testUsers.chef_service,
      testUsers.billeteur,
      testUsers.chef_commission,
      testUsers.cadre_daj,
      testUsers.christian_abanda,
      testUsers.sous_directeur,
      testUsers.directeur,
      testUsers.ministre
    ];

    for (const user of users) {
      // Se déconnecter si déjà connecté
      await page.goto('/logout.php');

      // Se connecter
      await login(page, user);

      // Vérifier qu'on est sur le dashboard
      await expect(page).toHaveURL(/dashboard\.php/);

      console.log(`✅ ${user.name} connecté avec succès`);
    }
  });

  test('Redirection après login selon le rôle', async ({ page }) => {
    // Admin devrait voir le dashboard admin
    await login(page, testUsers.admin);
    await expect(page).toHaveURL(/dashboard\.php/);
    // Vérifier qu'il voit les options admin
    await expect(page.locator('body')).toContainText(/utilisateurs|gestion|admin/i);

    await logout(page);

    // Ministre devrait voir son dashboard spécifique
    await login(page, testUsers.ministre);
    await expect(page).toHaveURL(/dashboard\.php/);
  });

  test('Protection CSRF - Login form', async ({ page }) => {
    await page.goto('/login.php');

    // Vérifier présence du token CSRF
    const csrfInput = await page.locator('input[name="csrf_token"]');
    await expect(csrfInput).toBeVisible();

    const csrfToken = await csrfInput.inputValue();
    expect(csrfToken).toBeTruthy();
    expect(csrfToken.length).toBeGreaterThan(10);
  });

});

// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * Configuration Playwright pour TestSprite - SGDI
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './e2e',

  /* Maximum time one test can run */
  timeout: 60 * 1000,

  /* Run tests in files in parallel */
  fullyParallel: false, // Sequential pour garantir l'état de la BDD

  /* Fail the build on CI if you accidentally left test.only in the source code */
  forbidOnly: !!process.env.CI,

  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,

  /* Opt out of parallel tests on CI */
  workers: process.env.CI ? 1 : 1, // Un seul worker pour éviter conflits BDD

  /* Reporter to use */
  reporter: [
    ['html', { outputFolder: 'test-results/html' }],
    ['json', { outputFile: 'test-results/results.json' }],
    ['list'],
    ['junit', { outputFile: 'test-results/junit.xml' }]
  ],

  /* Shared settings for all the projects below */
  use: {
    /* Base URL */
    baseURL: process.env.BASE_URL || 'http://localhost/dppg-implantation',

    /* Collect trace when retrying the failed test */
    trace: 'on-first-retry',

    /* Screenshot on failure */
    screenshot: 'only-on-failure',

    /* Video on failure */
    video: 'retain-on-failure',

    /* Maximum time each action can take */
    actionTimeout: 10 * 1000,

    /* Navigation timeout */
    navigationTimeout: 30 * 1000,

    /* Locale */
    locale: 'fr-FR',

    /* Timezone */
    timezoneId: 'Africa/Douala',
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },

    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },

    // {
    //   name: 'webkit',
    //   use: { ...devices['Desktop Safari'] },
    // },

    /* Test against mobile viewports */
    // {
    //   name: 'Mobile Chrome',
    //   use: { ...devices['Pixel 5'] },
    // },
    // {
    //   name: 'Mobile Safari',
    //   use: { ...devices['iPhone 12'] },
    // },
  ],

  /* Run your local dev server before starting the tests */
  // webServer: {
  //   command: 'php -S localhost:8000',
  //   url: 'http://localhost:8000',
  //   reuseExistingServer: !process.env.CI,
  //   stdout: 'ignore',
  //   stderr: 'pipe',
  // },
});

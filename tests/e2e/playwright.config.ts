import { defineConfig, devices } from '@playwright/test';

/**
 * SAMS runs under a web path with spaces (APP_BASE = '/SV Auto Truck Repair'),
 * so baseURL keeps the trailing slash and specs use relative paths from there.
 */
const BASE_URL =
  process.env.SAMS_BASE_URL ?? 'http://localhost:8080/SV%20Auto%20Truck%20Repair/frontend/';

export default defineConfig({
  testDir: './specs',
  globalSetup: require.resolve('./support/global-setup'),
  // The app writes to a single shared MySQL database and PHP's built-in server
  // is not concurrency-friendly, so run serially to keep results attributable.
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: 0,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['json', { outputFile: 'results.json' }],
  ],
  use: {
    baseURL: BASE_URL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
    ignoreHTTPSErrors: true,
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
});

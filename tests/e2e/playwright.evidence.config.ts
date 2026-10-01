import { defineConfig } from '@playwright/test';
import base from './playwright.config';

/**
 * Evidence capture only. Kept out of the normal run so `npm test` reports
 * pass/fail and nothing else; this config targets evidence.capture.ts alone.
 */
export default defineConfig({
  ...base,
  testDir: '.',
  testMatch: /evidence\.capture\.ts$/,
  reporter: [['list']],
});

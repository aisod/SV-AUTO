import { defineConfig } from '@playwright/test';
import base from './playwright.config';

/**
 * Video capture of the demo walkthrough. Separate from the suite: no global
 * re-seed (the demo relies on the records staged beforehand), a fixed 1280x720
 * frame that projects cleanly, and slowMo so the steps are followable.
 */
export default defineConfig({
  ...base,
  globalSetup: undefined,
  testDir: '.',
  testMatch: /demo\.record\.ts$/,
  reporter: [['list']],
  outputDir: 'demo-video',
  use: {
    ...base.use,
    viewport: { width: 1280, height: 720 },
    video: { mode: 'on', size: { width: 1280, height: 720 } },
    trace: 'off',
    screenshot: 'off',
    launchOptions: { slowMo: 180 },
  },
});

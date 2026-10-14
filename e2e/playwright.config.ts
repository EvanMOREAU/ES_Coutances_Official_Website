import { defineConfig, devices } from '@playwright/test';

// Le serveur PHP de test est lancé par Playwright : base « _test » (voir `composer test:setup`), e-mails nuls,
// double authentification désactivée. En local, E2E_CHANNEL=msedge (ou chrome) évite de télécharger Chromium.
const port = Number(process.env.E2E_PORT ?? 8099);
const channel = process.env.E2E_CHANNEL || undefined;

export default defineConfig({
  testDir: './tests',
  timeout: 30_000,
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
  globalSetup: './global-setup.ts',
  use: {
    baseURL: `http://127.0.0.1:${port}`,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'], channel } },
    { name: 'mobile', use: { ...devices['Pixel 7'], channel }, testMatch: /public\.spec\.ts/ },
  ],
  webServer: {
    command: `php -d variables_order=EGPCS -S 127.0.0.1:${port} -t public`,
    url: `http://127.0.0.1:${port}/robots.txt`,
    reuseExistingServer: !process.env.CI,
    timeout: 60_000,
    cwd: '..',
    env: {
      APP_ENV: 'test',
      APP_DEBUG: '1',
      APP_SECRET: 'e2e-secret',
      TEST_TOKEN: 'e2e',
      ENFORCE_PRIVILEGED_2FA: '0',
      MAILER_DSN: 'null://null',
      PATH: process.env.PATH ?? '',
    },
  },
});

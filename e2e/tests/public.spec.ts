import { expect, test } from '@playwright/test';

// Parcours publics : aucune erreur JavaScript ni violation de Content-Security-Policy ne doit apparaître.
const pages = ['/', '/club/encadrement', '/contact', '/boutique', '/mentions-legales', '/mon-compte/connexion'];

for (const url of pages) {
  test(`${url} s'affiche sans erreur ni violation de CSP`, async ({ page }) => {
    const problems: string[] = [];
    page.on('pageerror', (error) => problems.push(`erreur JS : ${error.message}`));
    page.on('console', (message) => message.type() === 'error' && problems.push(`console : ${message.text()}`));
    await page.addInitScript(() => {
      document.addEventListener('securitypolicyviolation', (event) => {
        console.error(`CSP : ${event.violatedDirective} ${event.blockedURI}`);
      });
    });

    const response = await page.goto(url);

    expect(response?.status()).toBeLessThan(400);
    await expect(page.locator('h1, h2').first()).toBeVisible();
    expect(problems).toEqual([]);
  });
}

test('le menu « Club » se déplie au clavier et annonce son état', async ({ page, isMobile }) => {
  test.skip(isMobile, 'Menu burger sur mobile.');
  await page.goto('/');

  const trigger = page.locator('.nav-bar .nav-item', { hasText: 'Club' }).locator('a, button').first();
  await expect(trigger).toHaveAttribute('aria-expanded', 'false');
  await trigger.focus();
  await expect(trigger).toHaveAttribute('aria-expanded', 'true');
  await page.keyboard.press('Escape');
  await expect(trigger).toHaveAttribute('aria-expanded', 'false');
});

test('le formulaire de contact refuse un envoi vide', async ({ page }) => {
  await page.goto('/contact');

  await page.getByRole('button', { name: /envoyer/i }).click();

  await expect(page).toHaveURL(/\/contact/);
  await expect(page.locator('form')).toBeVisible();
});

test('les pages publiques sont mises en cache pour un visiteur anonyme', async ({ request }) => {
  const response = await request.get('/mentions-legales');

  expect(response.headers()['cache-control']).toContain('public');
  expect(response.headers()['vary']).toContain('Cookie');
});

test('la page de santé répond', async ({ request }) => {
  const response = await request.get('/health');

  expect(response.status()).toBe(200);
  expect((await response.json()).checks.database).toBe('ok');
});

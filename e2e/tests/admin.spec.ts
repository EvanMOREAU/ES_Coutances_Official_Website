import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test } from '@playwright/test';

const seed = JSON.parse(readFileSync(join(__dirname, '..', '.seed.json'), 'utf8'));

test("connexion à l'administration, pages principales sans violation de CSP", async ({ page }) => {
  const problems: string[] = [];
  page.on('pageerror', (error) => problems.push(error.message));
  page.on('console', (message) => message.type() === 'error' && problems.push(message.text()));
  await page.addInitScript(() => {
    document.addEventListener('securitypolicyviolation', (event) => {
      console.error(`CSP : ${event.violatedDirective} ${event.blockedURI}`);
    });
  });

  await page.goto('/admin/login');
  await page.getByLabel('Adresse e-mail').fill(seed.admin);
  await page.getByLabel('Mot de passe').fill(seed.password);
  await page.getByRole('button', { name: 'Se connecter', exact: true }).click();

  await expect(page).toHaveURL(/\/admin$/);
  await expect(page.getByText('Tableau de bord').first()).toBeVisible();

  for (const url of ['/admin/licencies', '/admin/boutique/articles', '/admin/journal']) {
    await page.goto(url);
    await expect(page.locator('h1').first()).toBeVisible();
  }
  expect(problems).toEqual([]);
});

test('un mauvais mot de passe est refusé', async ({ page }) => {
  await page.goto('/admin/login');
  await page.getByLabel('Adresse e-mail').fill(seed.admin);
  await page.getByLabel('Mot de passe').fill('mauvais');
  await page.getByRole('button', { name: 'Se connecter', exact: true }).click();

  await expect(page).toHaveURL(/\/admin\/login/);
});

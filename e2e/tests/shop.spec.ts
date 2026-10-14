import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test } from '@playwright/test';

const seed = JSON.parse(readFileSync(join(__dirname, '..', '.seed.json'), 'utf8'));

test("commande d'un article payée au club, de l'article à la confirmation", async ({ page }) => {
  // Le client se connecte à son compte (la commande est réservée aux comptes connectés).
  await page.goto('/mon-compte/connexion');
  await page.getByLabel('Adresse e-mail').fill(seed.client);
  await page.getByLabel('Mot de passe').fill(seed.password);
  await page.getByRole('button', { name: 'Se connecter', exact: true }).click();
  await expect(page).not.toHaveURL(/connexion/);

  await page.goto(`/boutique/article/${seed.article}`);
  await expect(page.getByRole('heading', { name: /Maillot E2E/ }).first()).toBeVisible();

  await page.getByRole('button', { name: /ajouter au panier/i }).click();
  await expect(page).toHaveURL(/\/boutique\/panier/);
  await expect(page.locator('body')).toContainText('Maillot E2E');

  await page.getByRole('link', { name: /commander|valider/i }).first().click();
  await expect(page).toHaveURL(/\/boutique\/commande/);

  await page.getByLabel(/Prénom/).fill('Camille');
  await page.getByLabel(/^Nom/).fill('Durand');
  await page.getByLabel(/Adresse e-mail/).fill('camille@example.test');
  await page.locator('.pay-option', { hasText: 'Espèces au club' }).click();
  await page.locator('.cgv-check').click();
  await page.getByRole('button', { name: /Commander avec obligation de paiement/ }).click();

  await expect(page).toHaveURL(/\/boutique\/commande\/ESC-/);
  await expect(page.locator('body')).toContainText(/ESC-\d{6}-/);
});

test("la page d'un article porte ses données structurées", async ({ page }) => {
  await page.goto(`/boutique/article/${seed.article}`);

  const json = JSON.parse(await page.locator('script[type="application/ld+json"]').first().innerText());

  expect(json['@type']).toBe('Product');
  expect(json.offers.priceCurrency).toBe('EUR');
});

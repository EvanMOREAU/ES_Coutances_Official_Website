import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

// Accessibilité (WCAG 2 A et AA) des pages publiques : aucune violation ne doit apparaître.
const pages = ['/', '/club/encadrement', '/contact', '/boutique', '/boutique/article/e2e-maillot', '/mentions-legales', '/mon-compte/connexion', '/admin/login'];

for (const url of pages) {
  test(`${url} respecte WCAG 2 A/AA`, async ({ page }) => {
    await page.goto(url);

    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();

    const summary = results.violations.map((violation) => `${violation.id} (${violation.impact}) : ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`);
    expect(summary).toEqual([]);
  });
}

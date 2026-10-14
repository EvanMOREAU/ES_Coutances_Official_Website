import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';

// Base dédiée aux tests de bout en bout (suffixe « _teste2e », pour ne pas polluer celle de PHPUnit) : créée,
// migrée, puis garnie d'un article en stock et d'un compte administrateur (voir seed.php).
export default async function globalSetup(): Promise<void> {
  const root = join(__dirname, '..');
  const env = { ...process.env, APP_ENV: 'test', APP_DEBUG: '1', APP_SECRET: 'e2e-secret', TEST_TOKEN: 'e2e' };
  const run = (args: string[]) => execFileSync('php', args, { cwd: root, env, encoding: 'utf8' });

  run(['bin/console', 'doctrine:database:create', '--if-not-exists', '--no-interaction']);
  run(['bin/console', 'doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration']);
  const out = run([join(__dirname, 'seed.php')]);
  writeFileSync(join(__dirname, '.seed.json'), out.trim().split('\n').pop() ?? '{}');
}

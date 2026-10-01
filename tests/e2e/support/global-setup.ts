import { execFileSync } from 'node:child_process';
import { join } from 'node:path';

/**
 * Re-seed before every run.
 *
 * Several cases mutate shared rows (CLIENT-11 blocks and restores an account),
 * so a run interrupted part-way can leave the database in a state that makes
 * later runs fail for the wrong reason. Seeding first makes results
 * reproducible regardless of how the previous run ended.
 */
export default function globalSetup() {
  const php = process.env.SAMS_PHP ?? 'php';
  const seed = join(__dirname, '..', '..', '..', 'backend', 'setup', 'seed_test_data.php');
  const out = execFileSync(php, [seed], { encoding: 'utf8' });
  const clients = out.split('\n').filter((l) => l.startsWith('client ')).length;
  console.log(`[global-setup] re-seeded: ${clients} clients`);
}

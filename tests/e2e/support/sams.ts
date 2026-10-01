import { expect, type Page, type APIRequestContext } from '@playwright/test';
import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';

/**
 * Shared helpers for the SAMS suite.
 *
 * Accounts and record ids come from seed-manifest.json, written by
 * backend/setup/seed_test_data.php, so specs never hard-code primary keys.
 */

export const PASSWORD = 'Test@1234';

export const STAFF = {
  admin: 'qa.admin@test.local',
  manager: 'qa.manager@test.local',
  technician: 'qa.technician@test.local',
  finance: 'qa.finance@test.local',
  hr: 'qa.hr@test.local',
  cleaner: 'qa.cleaner@test.local',
} as const;

export const CLIENTS = {
  approved: 'qa.client.approved@test.local',
  other: 'qa.client.other@test.local',
  pending: 'qa.client.pending@test.local',
  blocked: 'qa.client.blocked@test.local',
} as const;

type Fixture = {
  clientId: number;
  vehicleId: number;
  jobCardId: number;
  quotationId: number;
  invoiceId: number;
};

type Manifest = {
  password: string;
  clients: Record<string, number>;
  fixtures: Record<string, Fixture>;
};

const manifestPath = join(__dirname, '..', 'seed-manifest.json');

export function manifest(): Manifest {
  if (!existsSync(manifestPath)) {
    throw new Error(
      `seed-manifest.json missing. Run: php backend/setup/seed_test_data.php`,
    );
  }
  return JSON.parse(readFileSync(manifestPath, 'utf8')) as Manifest;
}

export function fixtureFor(email: string): Fixture {
  const f = manifest().fixtures[email];
  if (!f) throw new Error(`No seeded fixture for ${email}`);
  return f;
}

/**
 * Submit the unified login form. Does not assert the outcome — callers decide
 * what "success" means, since several cases expect rejection.
 */
export async function submitLogin(page: Page, email: string, password: string) {
  await page.goto('login.php?force=1', { waitUntil: 'domcontentloaded' });
  // The field is disabled while the brute-force lockout is active, so wait for
  // it to be editable rather than assuming the form is ready on load.
  const username = page.locator('#username');
  await username.waitFor({ state: 'visible' });
  await username.fill(email);
  await page.locator('#password').fill(password);
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.locator('button.btn-signin-submit').click(),
  ]);
}

/** Log in and assert we landed somewhere other than the login page. */
export async function loginAs(page: Page, email: string, password = PASSWORD) {
  await submitLogin(page, email, password);
  await expect(page, `login as ${email} should leave the login page`).not.toHaveURL(
    /login\.php/,
  );
}

export async function logout(page: Page) {
  await page.goto('Admin/Auth/logout.php?redirect=login');
}

/** The visible error/success banner on the login page, if any. */
export async function loginMessage(page: Page): Promise<string> {
  const alert = page.locator('.alert').first();
  return (await alert.count()) ? ((await alert.textContent()) ?? '').trim() : '';
}

/**
 * Fetch a URL without following redirects, so a spec can assert on the 302
 * itself rather than on wherever it lands.
 */
export async function rawGet(request: APIRequestContext, url: string) {
  return request.get(url, { maxRedirects: 0, failOnStatusCode: false });
}

/** Path part of a Location header, decoded and without the app base prefix. */
export function locationOf(headers: Record<string, string>): string {
  return decodeURIComponent(headers['location'] ?? '');
}

/**
 * True when the response body looks like a leaked PHP error rather than a page.
 * Used by several cases that require errors not to reach the end user.
 */
export function looksLikePhpError(body: string): boolean {
  return /Fatal error|Warning:|Notice:|Uncaught (?:PDO)?Exception|SQLSTATE\[|Stack trace:|Connection failed:/i.test(
    body,
  );
}

/**
 * Flip a client's status directly in the database.
 *
 * Used by the mid-session cases, where the point is that the app must react to
 * a change made outside the browser session. Shells out to PHP so the test uses
 * the same connection settings as the app itself.
 */
export function setClientStatus(email: string, status: 'pending' | 'approved' | 'blocked') {
  const php = process.env.SAMS_PHP ?? 'php';
  const configPath = join(__dirname, '..', '..', '..', 'backend', 'config', 'config.php');
  const code = [
    `require '${configPath.split('\\').join('/')}';`,
    `$s = $pdo->prepare("UPDATE clients SET status = ? WHERE email = ?");`,
    `$s->execute([${JSON.stringify(status)}, ${JSON.stringify(email)}]);`,
    `echo $s->rowCount();`,
  ].join(' ');
  const out = execFileSync(php, ['-r', code], { encoding: 'utf8' });
  if (out.trim() === '0') {
    throw new Error(`setClientStatus: no client row updated for ${email}`);
  }
}

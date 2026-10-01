import { test, expect, type Page } from '@playwright/test';
import { STAFF, CLIENTS, PASSWORD, submitLogin } from './support/sams';

/**
 * Records the three-act demo as a video, for use as a presentation backup.
 *
 *     npm run demo:video
 *
 * Not part of the test suite — the filename avoids *.spec.ts so the default
 * testMatch skips it, and it asserts almost nothing. Its job is to produce a
 * watchable recording, not to verify behaviour.
 *
 * Act 3 permanently deletes the three seeded recycle-bin records, so re-seed
 * them before presenting. The caption overlay narrates each step on screen.
 */

const BEAT = 2200; // ms to hold each caption, tuned for reading on a projector

/** Draw a caption bar over the page. Survives until the next navigation. */
async function say(page: Page, act: string, text: string, tone: 'ok' | 'bad' | 'plain' = 'plain') {
  await page.evaluate(
    ({ act, text, tone }) => {
      document.getElementById('__demo_caption__')?.remove();
      const bar = document.createElement('div');
      bar.id = '__demo_caption__';
      const bg = tone === 'bad' ? '#B3261E' : tone === 'ok' ? '#1B5E20' : '#1F1F1F';
      bar.setAttribute(
        'style',
        `position:fixed;left:0;right:0;bottom:0;z-index:2147483647;
         background:${bg};color:#fff;font:600 18px/1.45 Segoe UI,system-ui,sans-serif;
         padding:16px 24px;box-shadow:0 -4px 24px rgba(0,0,0,.35);
         display:flex;gap:16px;align-items:baseline;`,
      );
      const tag = document.createElement('span');
      tag.textContent = act;
      tag.setAttribute(
        'style',
        'font:700 12px/1 Segoe UI,system-ui,sans-serif;letter-spacing:.14em;' +
          'text-transform:uppercase;opacity:.75;white-space:nowrap;',
      );
      const body = document.createElement('span');
      body.textContent = text;
      bar.append(tag, body);
      document.body.appendChild(bar);
    },
    { act, text, tone },
  );
  await page.waitForTimeout(BEAT);
}

async function show(page: Page, path: string) {
  await page.goto(path, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await page.waitForTimeout(600);
}

/**
 * Switch accounts. The explicit logout matters: login.php?force=1 never shows
 * the form to a signed-in visitor (finding M7), so without it the next sign-in
 * has no field to fill.
 */
async function signInAs(page: Page, who: string) {
  await page.goto('Admin/Auth/logout.php?redirect=login', { waitUntil: 'domcontentloaded' }).catch(() => {});
  await page.waitForTimeout(400);
  await submitLogin(page, who, PASSWORD);
}

test('SAMS demo walkthrough', async ({ page }) => {
  test.setTimeout(300_000);

  // ---------------------------------------------------------------- Act 1
  await show(page, 'login.php?force=1');
  await say(page, 'Act 1', 'SAMS — a working truck-repair management system.', 'ok');

  await signInAs(page, STAFF.admin);
  await say(page, 'Act 1', 'Administrator signs in. Dashboard loads: job cards, quotations, invoices.', 'ok');

  await show(page, 'Admin/Client/clients.php');
  await say(page, 'Act 1', 'Client records load correctly.', 'ok');

  await signInAs(page, CLIENTS.approved);
  await say(page, 'Act 1', 'A customer signs in to their own portal. Their quotation and invoice are here.', 'ok');

  await signInAs(page, CLIENTS.blocked);
  await say(page, 'Act 1', 'A blocked customer is correctly refused. All 11 authentication tests pass.', 'ok');

  // ---------------------------------------------------------------- Act 2
  await signInAs(page, STAFF.technician);
  await say(page, 'Act 2', 'A technician signs in successfully — and lands on a 404. The dashboard does not exist.', 'bad');

  await signInAs(page, STAFF.admin);
  await show(page, 'Admin/Inventory/inventory.php');
  await say(page, 'Act 2', 'Sidebar link "Inventory" → 404. Look at the address bar: the folder name is doubled.', 'bad');

  await show(page, 'Admin/Report/reports.php');
  await say(page, 'Act 2', 'Reports: the same. 10 of 15 navigation links are dead stubs.', 'bad');

  await show(page, 'Admin/Quotation/quotations.php');
  await say(page, 'Act 2', 'Quotations returns a bare fatal error — it queries a column that does not exist.', 'bad');

  await signInAs(page, CLIENTS.approved);
  await show(page, 'Client/view_invoice.php?id=3');
  await say(page, 'Act 2', 'A customer opening their own invoice is shown a stack trace with server filesystem paths.', 'bad');

  // ---------------------------------------------------------------- Act 3
  const anon = await page.context().browser()!.newContext({ viewport: page.viewportSize() });
  const anonPage = await anon.newPage();
  await anonPage.goto(
    new URL(
      '../backend/migrations/run_all_migrations.php',
      page.url().replace(/frontend\/.*$/, 'frontend/'),
    ).href,
    { waitUntil: 'domcontentloaded' },
  ).catch(() => {});
  await anonPage.waitForTimeout(600);
  await say(anonPage, 'Act 3', 'No login. No session. This browser has never authenticated.', 'bad');
  await say(anonPage, 'Act 3', 'The database migration runner executes for an anonymous visitor.', 'bad');
  await anonPage.close();
  await anon.close();

  await signInAs(page, STAFF.technician);
  await show(page, 'Admin/dashboard.php');
  await say(page, 'Act 3', 'The technician — who has no dashboard of their own — is served the full admin dashboard.', 'bad');

  await show(page, 'Admin/recycle-bin/recycle_bin.php');
  await say(page, 'Act 3', 'And the recycle bin. 16 of 145 admin files carry an auth guard; 9 check the role.', 'bad');

  await signInAs(page, CLIENTS.approved);
  await say(page, 'Act 3', 'Now a customer. The lowest-privilege account in the system.', 'bad');

  await show(page, 'Admin/recycle-bin/permanent_delete_all.php');
  await say(page, 'Act 3', 'A customer just permanently deleted three expense records. There is no undo.', 'bad');

  await signInAs(page, 'john.doe@email.com');
  await say(page, 'Act 3', 'This staff account is admitted to the client portal…', 'bad');
  await say(page, 'Act 3', '…where it reads its account status from a different person’s record entirely.', 'bad');

  await show(page, 'login.php?force=1');
  await say(page, 'Summary', '86 tests · 49 pass · 36 fail · 23 confirmed defects · 2 critical.', 'plain');

  expect(true).toBe(true);
});

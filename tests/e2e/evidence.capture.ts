import { test, expect } from '@playwright/test';
import { STAFF, CLIENTS, loginAs, submitLogin, setClientStatus, fixtureFor } from './support/sams';

/**
 * Evidence capture for the QA report.
 *
 * Not part of the normal run - the filename deliberately avoids *.spec.ts so
 * Playwright's default testMatch skips it. Run it explicitly:
 *
 *     npx playwright test --testMatch="**\/evidence.capture.ts"
 *
 * Each case navigates to a confirmed finding and writes a full-page PNG into
 * evidence/, named after the checklist ID it supports.
 */

const DIR = 'evidence';

test.describe.configure({ mode: 'serial' });

test('EV-01 client invoice fatal error (CLIENT-09, SEC-06)', async ({ page }) => {
  const mine = fixtureFor(CLIENTS.approved);
  await loginAs(page, CLIENTS.approved);
  await page.goto(`Client/view_invoice.php?id=${mine.invoiceId}`);
  await page.screenshot({ path: `${DIR}/EV-01-client-invoice-fatal-error.png`, fullPage: true });
});

test('EV-02 admin quotations module fatal error (ADMIN-06)', async ({ page }) => {
  await loginAs(page, STAFF.admin);
  await page.goto('Admin/Quotation/quotations.php');
  await page.screenshot({ path: `${DIR}/EV-02-admin-quotations-fatal-error.png`, fullPage: true });
});

test('EV-03 sidebar module redirects to a doubled path and 404s (ADMIN-NAV)', async ({ page }) => {
  await loginAs(page, STAFF.admin);
  await page.goto('Admin/Inventory/inventory.php');
  await page.screenshot({ path: `${DIR}/EV-03-inventory-404-doubled-path.png`, fullPage: true });
});

test('EV-04 technician is served the full Admin dashboard', async ({ page }) => {
  await loginAs(page, STAFF.technician);
  await page.goto('Admin/dashboard.php');
  await page.screenshot({ path: `${DIR}/EV-04-technician-admin-dashboard.png`, fullPage: true });
});

test('EV-05 technician is served the Admin recycle bin', async ({ page }) => {
  await loginAs(page, STAFF.technician);
  await page.goto('Admin/recycle-bin/recycle_bin.php');
  await page.screenshot({ path: `${DIR}/EV-05-technician-recycle-bin.png`, fullPage: true });
});

test('EV-06 a logged-in client can run permanent_delete_all.php', async ({ page }) => {
  await loginAs(page, CLIENTS.approved);
  // The endpoint executes the DELETE then redirects with a success flash.
  await page.goto('Admin/recycle-bin/permanent_delete_all.php');
  await page.screenshot({ path: `${DIR}/EV-06-client-permanent-delete.png`, fullPage: true });
  expect(page.url()).toContain('recycle_bin.php');
});

test('EV-07 schema DDL scripts run for anonymous visitors (SEC-07)', async ({ page }) => {
  await page.context().clearCookies();
  await page.goto('Admin/recycle-bin/setup_recycle_bin_all.php');
  await page.screenshot({ path: `${DIR}/EV-07a-anon-setup-recycle-bin.png`, fullPage: true });

  await page.goto('../backend/migrations/run_all_migrations.php');
  await page.screenshot({ path: `${DIR}/EV-07b-anon-run-migrations.png`, fullPage: true });
});

test('EV-08 technician dashboard does not exist (ROLE-03)', async ({ page }) => {
  await loginAs(page, STAFF.technician);
  await page.screenshot({ path: `${DIR}/EV-08-technician-dashboard-404.png`, fullPage: true });
});

test('EV-09 finance dashboard does not exist (ROLE-04)', async ({ page }) => {
  await loginAs(page, STAFF.finance);
  await page.screenshot({ path: `${DIR}/EV-09-finance-dashboard-404.png`, fullPage: true });
});

test('EV-10 HR dashboard does not exist (ROLE-05)', async ({ page }) => {
  await loginAs(page, STAFF.hr);
  await page.screenshot({ path: `${DIR}/EV-10-hr-dashboard-404.png`, fullPage: true });
});

test('EV-11 cross-tenant quotation request is not denied cleanly (CLIENT-07)', async ({ page }) => {
  const theirs = fixtureFor(CLIENTS.other);
  await loginAs(page, CLIENTS.approved);
  await page.goto(`Client/view_quotation.php?id=${theirs.quotationId}`);
  await page.screenshot({ path: `${DIR}/EV-11-cross-tenant-quotation.png`, fullPage: true });
});

test('EV-13 an approved self-registered client is still held at pending-approval', async ({
  page,
}) => {
  const email = `qa.ev13.${Date.now()}@test.local`;

  await page.goto('Client/register.php');
  await page.locator('select[name="title"]').selectOption('Mr');
  await page.locator('[name="full_name"]').fill('Evidence Approved');
  await page.locator('[name="phone"]').fill('0811000096');
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill('Test@1234');
  await page.locator('[name="confirm_password"]').fill('Test@1234');
  await page.locator('button[type="submit"]').first().click();
  await page.waitForLoadState('networkidle');

  setClientStatus(email, 'approved');

  await submitLogin(page, email, 'Test@1234');
  await page.goto('Client/dashboard.php');
  await page.screenshot({ path: `${DIR}/EV-13-approved-client-still-pending.png`, fullPage: true });
});

// Healthy pages, for contrast in the report. Separate tests so each gets a
// fresh browser context rather than re-authenticating over a live session.
test('EV-12a admin dashboard renders correctly', async ({ page }) => {
  await loginAs(page, STAFF.admin);
  await page.goto('Admin/dashboard.php');
  await page.screenshot({ path: `${DIR}/EV-12a-admin-dashboard-ok.png`, fullPage: true });
});

test('EV-12b client dashboard renders correctly', async ({ page }) => {
  await loginAs(page, CLIENTS.approved);
  await page.goto('Client/dashboard.php');
  await page.screenshot({ path: `${DIR}/EV-12b-client-dashboard-ok.png`, fullPage: true });
});

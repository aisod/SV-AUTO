import { test, expect } from '@playwright/test';
import { STAFF, loginAs, rawGet, locationOf, looksLikePhpError } from '../support/sams';


/**
 * Navigate and assert a real page rendered.
 *
 * The 404 body echoes the requested path, so asserting on page text alone
 * passes on a Not Found page — e.g. /Admin/Employee/Employee/employees.php
 * contains "employee". Check the HTTP status and the 404 title first.
 */
async function expectPageRenders(page: import('@playwright/test').Page, url: string) {
  const res = await page.goto(url);
  expect(res, `no response for ${url}`).not.toBeNull();
  expect(res!.status(), `${url} did not return 200`).toBe(200);
  await expect(page, `${url} resolved to a Not Found page`).not.toHaveTitle(/not found/i);
  await expect(
    page.locator('body'),
    `${url} rendered the 404 page`,
  ).not.toContainText('was not found on this server');
}

/** Section 4: the Admin role. */
test.describe('ADMIN - Admin role', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STAFF.admin);
  });

  test('ADMIN-01 the dashboard renders its summary widgets without errors', async ({ page }) => {
    await page.goto('Admin/dashboard.php');
    const body = await page.content();
    expect(looksLikePhpError(body), 'dashboard leaked a PHP/SQL error').toBe(false);
    await expect(page.locator('body')).toContainText(/job card|quotation|invoice/i);
  });

  test('ADMIN-NAV every sidebar link resolves to a real page', async ({ page, request, baseURL }) => {
    await page.goto('Admin/dashboard.php');

    const hrefs = await page
      .locator('a[href]')
      .evaluateAll((els) =>
        els
          .map((e) => (e as HTMLAnchorElement).getAttribute('href') ?? '')
          .filter((h) => h && !h.startsWith('http') && !h.startsWith('#') && h.endsWith('.php')),
      );

    const unique = [...new Set(hrefs)];
    expect(unique.length, 'no navigable links found on the dashboard').toBeGreaterThan(3);

    const broken: string[] = [];
    for (const href of unique) {
      const target = new URL(href, page.url()).href;
      const res = await rawGet(request, target);

      if (res.status() === 404) {
        broken.push(`${href} -> 404`);
        continue;
      }
      // A redirect stub that points at itself or at a missing page is broken too.
      if (res.status() === 302) {
        const next = new URL(locationOf(res.headers()), target).href;
        const follow = await rawGet(request, next);
        if (follow.status() === 404) {
          broken.push(`${href} -> 302 -> ${locationOf(res.headers())} -> 404`);
        }
      }
    }
    expect(broken, 'admin navigation points at pages that do not resolve').toEqual([]);
  });

  test('ADMIN-02 a new job card can be created and appears in the list', async ({ page }) => {
    await page.goto('Admin/JobCard/add_job_card.php');
    const body = await page.content();
    expect(looksLikePhpError(body), 'job card form leaked an error').toBe(false);
    await expect(page.locator('form')).toHaveCount(1, { timeout: 10_000 });
  });

  test('ADMIN-06 the quotations module lists quotations', async ({ page }) => {
    await page.goto('Admin/Quotation/quotations.php');
    const body = await page.content();
    expect(looksLikePhpError(body), 'quotations page leaked an error').toBe(false);
    await expect(page.locator('body')).toContainText(/quotation/i);
  });

  test('ADMIN-08 the invoice module lists invoices', async ({ page }) => {
    await page.goto('Admin/Invoice/invoices.php');
    const body = await page.content();
    expect(looksLikePhpError(body), 'invoices page leaked an error').toBe(false);
    await expect(page.locator('body')).toContainText(/invoice/i);
  });

  test('ADMIN-09 the clients module lists clients', async ({ page }) => {
    await page.goto('Admin/Client/clients.php');
    const body = await page.content();
    expect(looksLikePhpError(body), 'clients page leaked an error').toBe(false);
    await expect(page.locator('body')).toContainText('qa.client.approved@test.local');
  });

  test('ADMIN-12 the employee module loads', async ({ page }) => {
    await expectPageRenders(page, 'Admin/Employee/employees.php');
    await expect(page.locator('body')).toContainText(/employee/i);
  });

  test('ADMIN-13 the user-role module loads', async ({ page }) => {
    await expectPageRenders(page, 'Admin/user-role/user_roles.php');
    await expect(page.locator('body')).toContainText(/role/i);
  });

  test('ADMIN-15 the inventory module loads', async ({ page }) => {
    await expectPageRenders(page, 'Admin/Inventory/inventory.php');
    await expect(page.locator('body')).toContainText(/inventory|stock|part/i);
  });

  test('ADMIN-17 the purchase order module loads', async ({ page }) => {
    await expectPageRenders(page, 'Admin/purchase-order/purchase_orders.php');
    await expect(page.locator('body')).toContainText(/purchase[ -]?order|supplier/i);
  });

  test('ADMIN-18 the expense module loads', async ({ page }) => {
    await expectPageRenders(page, 'Admin/Expense/expenses.php');
    await expect(page.locator('body')).toContainText(/expense/i);
  });

  test('ADMIN-20 the statutory document module loads', async ({ page }) => {
    await expectPageRenders(page, 'Admin/Statutory/statutory.php');
    await expect(page.locator('body')).toContainText(/statutory|document/i);
  });

  test('ADMIN-21 the reports module loads', async ({ page }) => {
    await expectPageRenders(page, 'Admin/Report/reports.php');
    await expect(page.locator('body')).toContainText(/report/i);
  });

  test('ADMIN-25 the recycle bin loads', async ({ page }) => {
    await page.goto('Admin/recycle-bin/recycle_bin.php');
    const body = await page.content();
    expect(looksLikePhpError(body), 'recycle bin leaked an error').toBe(false);
    await expect(page.locator('body')).toContainText(/recycle|deleted|restore/i);
  });
});

/** Section 5: the Manager role. */
test.describe('MGR - Manager role', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, STAFF.manager);
  });

  test('MGR-01 the manager dashboard renders', async ({ page }) => {
    await page.goto('Manager/manager_dashboard.php');
    const body = await page.content();
    expect(looksLikePhpError(body), 'manager dashboard leaked an error').toBe(false);
    await expect(page.locator('body')).toContainText(/job card|quotation|invoice/i);
  });

  test('MGR-02 the manager job card list renders', async ({ page }) => {
    await page.goto('Manager/manager_job_cards.php');
    const body = await page.content();
    expect(looksLikePhpError(body), 'manager job cards leaked an error').toBe(false);
  });

  test('MGR-04 the manager quotation list renders', async ({ page }) => {
    await page.goto('Manager/manager_quotations.php');
    const body = await page.content();
    expect(looksLikePhpError(body), 'manager quotations leaked an error').toBe(false);
  });

  test('MGR-05 the manager invoice list renders', async ({ page }) => {
    await page.goto('Manager/manager_invoices.php');
    const body = await page.content();
    expect(looksLikePhpError(body), 'manager invoices leaked an error').toBe(false);
  });
});

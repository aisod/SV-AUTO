import { test, expect } from '@playwright/test';
import { STAFF, PASSWORD, submitLogin, rawGet } from '../support/sams';

/**
 * Section 2: getRoleRedirect(). Each role must land on its documented
 * dashboard AND that dashboard must actually exist - a redirect to a 404 is
 * not a passing redirect.
 */
const cases: Array<{ id: string; email: string; expected: RegExp; path: string }> = [
  {
    id: 'ROLE-01',
    email: STAFF.admin,
    expected: /Admin\/dashboard\.php/,
    path: 'Admin/dashboard.php',
  },
  {
    id: 'ROLE-02',
    email: STAFF.manager,
    expected: /Manager\/manager_dashboard\.php/,
    path: 'Manager/manager_dashboard.php',
  },
  {
    id: 'ROLE-03',
    email: STAFF.technician,
    expected: /Technician\/technician_dashboard\.php/,
    path: 'Technician/technician_dashboard.php',
  },
  {
    id: 'ROLE-04',
    email: STAFF.finance,
    expected: /Admin\/finance_dashboard\.php/,
    path: 'Admin/finance_dashboard.php',
  },
  {
    id: 'ROLE-05',
    email: STAFF.hr,
    expected: /Hr\/hr_dashboard\.php/,
    path: 'Hr/hr_dashboard.php',
  },
];

test.describe('ROLE - Role redirect logic', () => {
  for (const c of cases) {
    test(`${c.id} ${c.email} lands on a working ${c.path}`, async ({ page }) => {
      await submitLogin(page, c.email, PASSWORD);
      await expect(page, 'redirect target').toHaveURL(c.expected);

      // The checklist's expected result is a working dashboard, so prove the
      // page rendered rather than 404'd.
      const status = page.url().includes('404') ? 404 : 200;
      expect(status).toBe(200);
      await expect(
        page.locator('body'),
        'dashboard should render content, not a Not Found page',
      ).not.toContainText(/not found/i);
    });
  }

  test('ROLE-07 unrecognised role falls back to the Admin dashboard (privilege check)', async ({
    page,
  }) => {
    await submitLogin(page, STAFF.cleaner, PASSWORD);
    // Documented behaviour is a fallback to Admin. The checklist asks whether
    // that is intentional - this asserts the behaviour so a future change to a
    // safer default (deny) shows up as a deliberate break.
    await expect(page).toHaveURL(/Admin\/dashboard\.php/);
  });

  test('ROLE-DASH the three dashboards referenced by getRoleRedirect exist', async ({
    request,
    baseURL,
  }) => {
    const missing: string[] = [];
    for (const p of [
      'Technician/technician_dashboard.php',
      'Admin/finance_dashboard.php',
      'Hr/hr_dashboard.php',
    ]) {
      const res = await rawGet(request, new URL(p, baseURL).href);
      if (res.status() === 404) missing.push(`${p} -> 404`);
    }
    expect(missing, 'getRoleRedirect points at dashboards that do not exist').toEqual([]);
  });
});

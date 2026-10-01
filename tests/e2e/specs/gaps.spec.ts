import { test, expect } from '@playwright/test';
import { STAFF, CLIENTS, PASSWORD, submitLogin, loginAs, rawGet } from '../support/sams';

/**
 * Checklist cases with no same-named test elsewhere in the suite.
 *
 * Written so the SAMS Test Cases document can carry a measured verdict for
 * every row rather than "not run". Cases that remain unexecutable are recorded
 * in the document against the finding that blocks them; they are not here.
 */

/** A page counts as reachable only if it returns 200 and is not the 404 body. */
async function reach(page: import('@playwright/test').Page, url: string) {
  const res = await page.goto(url);
  const body = (await page.locator('body').textContent()) ?? '';
  return {
    status: res?.status() ?? 0,
    notFound: /was not found on this server/i.test(body),
    url: page.url(),
    body,
  };
}

test.describe('ROLE - remaining redirect cases', () => {
  test('ROLE-06 a staff row whose role is "client" must not enter the client portal', async ({
    page,
  }) => {
    // database.sql seeds users.id 6 (john.doe@email.com) with the Client role.
    // The checklist says this "should not occur for staff table" — verify.
    await submitLogin(page, 'john.doe@email.com', PASSWORD);
    await expect(page).toHaveURL(/Client\/dashboard\.php/);

    // The portal keys client status off $_SESSION['user_id'], which here is a
    // users.id. It collides with an unrelated clients.id, so this staff row
    // reads a different client's status row.
    const r = await reach(page, 'Client/dashboard.php');
    expect(
      r.status === 200 && !r.notFound,
      'a users-table row with the client role was served the client portal, ' +
        'reading its status from an unrelated clients row',
    ).toBe(false);
  });
});

test.describe('TECH / FIN / HR - dashboards', () => {
  const dashboards: Array<[string, string, string]> = [
    ['TECH-01', STAFF.technician, 'Technician/technician_dashboard.php'],
    ['FIN-01', STAFF.finance, 'Admin/finance_dashboard.php'],
    ['HR-01', STAFF.hr, 'Hr/hr_dashboard.php'],
  ];

  for (const [id, who, path] of dashboards) {
    test(`${id} ${who.split('@')[0]} reaches a working dashboard`, async ({ page }) => {
      await loginAs(page, who);
      const r = await reach(page, path);
      expect(r.status, `${path} did not render`).toBe(200);
      expect(r.notFound, `${path} is a 404`).toBe(false);
    });
  }

  test('ADMIN-24 the finance dashboard is reachable for an admin', async ({ page }) => {
    await loginAs(page, STAFF.admin);
    const r = await reach(page, 'Admin/finance_dashboard.php');
    expect(r.status, 'finance dashboard did not render').toBe(200);
    expect(r.notFound, 'finance dashboard is a 404').toBe(false);
  });
});

test.describe('FIN / HR - module access', () => {
  const cases: Array<[string, string, string]> = [
    ['FIN-02', STAFF.finance, 'Admin/Invoice/invoices.php'],
    ['FIN-03', STAFF.finance, 'Admin/Expense/expenses.php'],
    ['HR-02', STAFF.hr, 'Admin/Employee/employees.php'],
    ['HR-03', STAFF.hr, 'Admin/hr-request/hr_requests.php'],
  ];

  for (const [id, who, path] of cases) {
    test(`${id} ${who.split('@')[0]} can use ${path.split('/').pop()}`, async ({ page }) => {
      await loginAs(page, who);
      const r = await reach(page, path);
      expect(r.notFound, `${path} resolved to a 404`).toBe(false);
      expect(r.url, `${path} redirected away instead of rendering`).toContain(
        path.split('/').pop()!,
      );
    });
  }
});

test.describe('MGR - remaining cases', () => {
  test('MGR-03 a manager can open the job card module', async ({ page }) => {
    await loginAs(page, STAFF.manager);
    // Manager/JobCard/job_card.php is a redirect stub of the same shape as the
    // nine admin ones: it points at manager_job_cards.php relative to its own
    // directory, producing Manager/JobCard/manager_job_cards.php, which 404s.
    // The real list lives one level up and is covered by MGR-02.
    const r = await reach(page, 'Manager/JobCard/job_card.php');
    expect(r.notFound, 'the manager job card entry point resolves to a 404').toBe(false);
    expect(r.status, 'manager job card module did not render').toBe(200);
  });

  test('MGR-07 the manager operations page renders', async ({ page }) => {
    await loginAs(page, STAFF.manager);
    const r = await reach(page, 'Manager/manager_operations.php');
    expect(r.status, 'manager operations did not render').toBe(200);
    expect(r.notFound, 'manager operations is a 404').toBe(false);
    expect(r.body).not.toMatch(/Fatal error|SQLSTATE\[/i);
  });
});

test.describe('CLIENT - remaining cases', () => {
  test('CLIENT-10 a client can open profile and settings', async ({ page }) => {
    await loginAs(page, CLIENTS.approved);
    for (const p of ['Client/profile.php', 'Client/settings.php']) {
      const r = await reach(page, p);
      expect(r.status, `${p} did not render`).toBe(200);
      expect(r.notFound, `${p} is a 404`).toBe(false);
      expect(r.body, `${p} leaked a PHP error`).not.toMatch(/Fatal error|SQLSTATE\[/i);
    }
  });
});

test.describe('ADMIN - remaining cases', () => {
  test('ADMIN-23 relevant notifications are visible to an admin', async ({ page, baseURL }) => {
    await loginAs(page, STAFF.admin);
    const res = await page.request.get(
      new URL('Admin/Utils/notifications_feed.php', baseURL).href,
      { failOnStatusCode: false },
    );
    const body = await res.text();
    expect(() => JSON.parse(body), 'notification feed did not return JSON').not.toThrow();
    const feed = JSON.parse(body);
    expect(feed.ok ?? true, 'notification feed reported an error').toBeTruthy();
  });

  test('ADMIN-26 contact enquiries are retrievable by an admin', async ({ page }) => {
    // Submit one, then confirm an admin can see it.
    await page.goto('index.php');
    const token = (await page.content()).match(/name="csrf_token"\s+value="([^"]+)"/)?.[1];
    expect(token, 'no contact CSRF token').toBeTruthy();
    const marker = `QAEnquiry${Date.now()}`;
    await page.request.post(new URL('contact_send.php', page.url()).href, {
      form: {
        csrf_token: token!,
        first_name: 'QA',
        last_name: marker,
        email: 'qa.enquiry@test.local',
        message: 'Automated ADMIN-26 check.',
      },
      maxRedirects: 0,
      failOnStatusCode: false,
    });

    await loginAs(page, STAFF.admin);
    const r = await reach(page, 'Admin/Client/clients.php?tab=enquiries');
    expect(r.notFound, 'enquiries view is a 404').toBe(false);
    expect(r.body, 'the submitted enquiry is not visible to the admin').toContain(marker);
  });
});

test.describe('AUTH - remaining cases', () => {
  // AUTH-04 cannot be tested: the users table has no active/disabled/status
  // column, so a staff account cannot be disabled and there is nothing to
  // exercise. Recorded as skipped rather than given a test that passes without
  // measuring anything. Delete this skip and write the real case if the column
  // is ever added.
  test.skip('AUTH-04 a disabled staff account is denied login', async () => {});

  test('AUTH-07 a manager is denied an Admin-only URL', async ({ page }) => {
    await loginAs(page, STAFF.manager);
    const r = await reach(page, 'Admin/dashboard.php');
    expect(
      r.status === 200 && !r.notFound && /Admin\/dashboard\.php/.test(r.url),
      'a manager was served the admin dashboard',
    ).toBe(false);
  });
});

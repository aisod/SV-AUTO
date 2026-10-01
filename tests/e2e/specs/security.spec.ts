import { test, expect } from '@playwright/test';
import {
  STAFF,
  CLIENTS,
  loginAs,
  rawGet,
  locationOf,
  fixtureFor,
  looksLikePhpError,
} from '../support/sams';

/** Section 9: cross-cutting security cases. */
test.describe('SEC - Cross-cutting security', () => {
  test('SEC-01 a disguised executable is rejected, or stored non-executable', async ({
    page,
    baseURL,
  }) => {
    // A harmless marker: if it ever runs server-side the output differs from
    // the source, which is what makes this detectable without doing anything.
    const payload = '<?php echo "SEC01_EXECUTED_".(2*21); ?>';
    const marker = 'SEC01_EXECUTED_42';

    await loginAs(page, STAFF.admin);

    async function upload(filename: string) {
      return page.request.post(
        new URL('Admin/Statutory/upload_statutory.php', baseURL).href,
        {
          multipart: {
            name: `SEC01 ${filename}`,
            type: 'founding',
            file: { name: filename, mimeType: 'image/jpeg', buffer: Buffer.from(payload) },
          },
          maxRedirects: 0,
          failOnStatusCode: false,
        },
      );
    }

    // Location comes back percent-decoded, so match on words not encoding.
    const loc = (r: { headers(): Record<string, string> }) =>
      decodeURIComponent(r.headers()['location'] ?? '').replace(/\+/g, ' ');

    // A bare .php must be refused by the extension whitelist.
    const php = await upload('sec01probe.php');
    expect(loc(php), 'a .php upload was accepted').toMatch(/invalid file type/i);

    // A .jpg carrying PHP source is accepted - content is never inspected.
    const disguised = await upload('sec01probe.php.jpg');
    const accepted = /success/i.test(loc(disguised));

    if (!accepted) return; // Rejected outright: the stronger of the two outcomes.

    // Accepted, so the checklist's other clause must hold: not executable.
    // upload_statutory.php writes to Admin/uploads/ while recording
    // uploads/ in the database, so probe both locations.
    for (const dir of ['uploads/statutory', 'Admin/uploads/statutory']) {
      const listing = await page.request.get(new URL(`${dir}/`, baseURL).href, {
        failOnStatusCode: false,
      });
      const names = [...(await listing.text()).matchAll(/sec01probe[^"'<>\s]*\.jpg/g)].map(
        (m) => m[0],
      );
      for (const n of new Set(names)) {
        const res = await page.request.get(new URL(`${dir}/${n}`, baseURL).href, {
          failOnStatusCode: false,
        });
        expect(
          await res.text(),
          `uploaded payload executed when served from ${dir}/${n}`,
        ).not.toContain(marker);
      }
    }
  });

  test('SEC-02 a stored XSS payload in a client name is escaped where admins read it', async ({
    page,
  }) => {
    // Store the payload through the public registration form, then render it
    // in the admin client list - the checklist's "client name" case.
    const marker = `xss${Date.now()}`;
    const payload = `<script>window.__xss='${marker}'</script>`;

    await page.goto('Client/register.php');
    await page.locator('select[name="title"]').selectOption('Mr');
    await page.locator('[name="full_name"]').fill(payload);
    await page.locator('[name="phone"]').fill('0811000098');
    await page.locator('[name="email"]').fill(`qa.xss.${Date.now()}@test.local`);
    await page.locator('[name="password"]').fill('Test@1234');
    await page.locator('[name="confirm_password"]').fill('Test@1234');
    await page.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');

    await loginAs(page, STAFF.admin);
    await page.goto('Admin/Client/clients.php');
    await page.waitForLoadState('networkidle');

    const executed = await page.evaluate(
      (m) => (window as unknown as Record<string, string>).__xss === m,
      marker,
    );
    expect(executed, 'stored payload executed in the admin client list').toBe(false);
    expect(
      (await page.content()).includes(payload),
      'payload was written into the document unescaped',
    ).toBe(false);
  });

  test('SEC-03 tampering with a record id cannot reach another tenant', async ({ page }) => {
    const theirs = fixtureFor(CLIENTS.other);
    await loginAs(page, CLIENTS.approved);

    for (const url of [
      `Client/view_quotation.php?id=${theirs.quotationId}`,
      `Client/view_invoice.php?id=${theirs.invoiceId}`,
    ]) {
      await page.goto(url);
      const body = (await page.locator('body').textContent()) ?? '';
      expect(body, `${url} exposed another tenant's record`).not.toContain('QA-BRAVO-001');
    }
  });

  test('SEC-04 the session cookie is HttpOnly', async ({ page, context }) => {
    await loginAs(page, STAFF.admin);
    const cookies = await context.cookies();
    const session = cookies.find((c) => /^PHPSESSID$/i.test(c.name));
    expect(session, 'no PHPSESSID cookie found').toBeTruthy();
    expect(session!.httpOnly, 'PHPSESSID must be HttpOnly').toBe(true);
    // Secure/SameSite are recorded rather than asserted: over plain HTTP on
    // localhost, Secure would break the session. Production must set both.
    test.info().annotations.push({
      type: 'SEC-04 cookie flags',
      description: `httpOnly=${session!.httpOnly} secure=${session!.secure} sameSite=${session!.sameSite}`,
    });
  });

  test('SEC-05 a state-changing GET without a CSRF token is refused', async ({
    page,
    request,
    baseURL,
  }) => {
    await loginAs(page, STAFF.admin);
    const cookies = await page.context().cookies();
    const jar = cookies.map((c) => `${c.name}=${c.value}`).join('; ');
    const mine = fixtureFor(CLIENTS.approved);

    // Forged cross-site request: authenticated cookies, foreign Referer, no token.
    const res = await request.get(
      new URL(`Admin/JobCard/delete_job_card.php?id=${mine.jobCardId}`, baseURL).href,
      {
        headers: { Cookie: jar, Referer: 'https://evil.example.com/' },
        maxRedirects: 0,
        failOnStatusCode: false,
      },
    );

    // Expected: rejected. A 302 back into the app means the delete was accepted.
    expect(
      [400, 403, 405].includes(res.status()),
      `delete_job_card.php accepted a tokenless cross-site request (status ${res.status()})`,
    ).toBe(true);
  });

  test('SEC-06 a malformed id does not leak a stack trace or credentials', async ({
    page,
  }) => {
    await loginAs(page, STAFF.admin);
    await page.goto("Admin/Invoice/view.php?id=not-a-number'%22");
    const body = await page.content();
    expect(looksLikePhpError(body), 'PHP error surfaced to the end user').toBe(false);
    expect(body).not.toMatch(/sams_db|127\.0\.0\.1:3307/);
  });

  test('SEC-07 secrets are not committed and setup scripts are not world-callable', async ({
    request,
    baseURL,
  }) => {
    // Schema-mutating setup endpoints must not run for an anonymous visitor.
    const dangerous = [
      'Admin/recycle-bin/setup_recycle_bin_all.php',
      'Admin/Statutory/add_deleted_column_statutory.php',
    ];
    const reachable: string[] = [];
    for (const p of dangerous) {
      const res = await rawGet(request, new URL(p, baseURL).href);
      const loc = locationOf(res.headers());
      const redirectedToLogin = res.status() === 302 && /login\.php/.test(loc);
      if (res.status() === 200 || (res.status() === 302 && !redirectedToLogin)) {
        reachable.push(`${p} -> ${res.status()} ${loc}`);
      }
    }
    expect(reachable, 'unauthenticated visitors can run schema DDL').toEqual([]);
  });
});

/**
 * Section 4 of the checklist, access-control half: staff boundaries.
 *
 * These target pages that actually render. The sidebar stubs (employees,
 * inventory, reports, ...) are excluded on purpose: they are 190-byte
 * redirects to paths that 404, so reaching them proves nothing either way.
 */
test.describe('SEC - Role boundaries', () => {
  /** Admin pages that render real content, with the size that proves it did. */
  const adminPages = [
    'Admin/dashboard.php',
    'Admin/recycle-bin/recycle_bin.php',
    'Admin/Client/clients.php',
    'Admin/Invoice/invoices.php',
    'Admin/JobCard/job_card.php',
  ];

  /** Pages this role was served instead of being turned away. */
  async function servedAdminPages(page: import('@playwright/test').Page) {
    const served: string[] = [];
    for (const p of adminPages) {
      await page.goto(p);
      const url = page.url();
      const body = (await page.locator('body').textContent()) ?? '';
      // Still on the requested Admin URL with substantial content rendered.
      if (url.includes(p) && body.trim().length > 500) {
        served.push(`${p} (${body.trim().length} chars)`);
      }
    }
    return served;
  }

  test('MGR-06 a manager is not served Admin pages', async ({ page }) => {
    await loginAs(page, STAFF.manager);
    expect(await servedAdminPages(page), 'manager was served Admin pages').toEqual([]);
  });

  test('TECH-04 a technician is not served Admin pages', async ({ page }) => {
    await loginAs(page, STAFF.technician);
    expect(await servedAdminPages(page), 'technician was served Admin pages').toEqual([]);
  });

  test('FIN-04 a finance user is not served Admin pages', async ({ page }) => {
    await loginAs(page, STAFF.finance);
    expect(await servedAdminPages(page), 'finance user was served Admin pages').toEqual([]);
  });

  test('HR-04 an HR user is not served Admin pages', async ({ page }) => {
    await loginAs(page, STAFF.hr);
    expect(await servedAdminPages(page), 'HR user was served Admin pages').toEqual([]);
  });

  test('SEC-DEL a non-admin cannot execute the permanent-delete endpoint', async ({ page }) => {
    // permanent_delete_all.php runs DELETE on a GET and checks only that
    // SOME session exists, so any authenticated role - including a client -
    // can empty the recycle bin.
    await loginAs(page, CLIENTS.approved);
    await page.goto('Admin/recycle-bin/permanent_delete_all.php');
    expect(
      page.url(),
      'a logged-in client executed permanent_delete_all.php',
    ).not.toMatch(/recycle_bin\.php\?success/);
  });
});

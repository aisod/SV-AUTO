import { test, expect } from '@playwright/test';
import {
  STAFF,
  PASSWORD,
  submitLogin,
  loginAs,
  loginMessage,
  rawGet,
  locationOf,
  looksLikePhpError,
} from '../support/sams';

/** Section 1 of the SAMS Test Cases: authentication, all users. */
test.describe('AUTH - Authentication', () => {
  test('AUTH-01 valid staff credentials reach a role dashboard', async ({ page }) => {
    await submitLogin(page, STAFF.admin, PASSWORD);
    await expect(page).toHaveURL(/Admin\/dashboard\.php/);
  });

  test('AUTH-02 correct username with wrong password is rejected', async ({ page }) => {
    await submitLogin(page, STAFF.admin, 'definitely-not-the-password');
    await expect(page).toHaveURL(/login\.php/);
    expect(await loginMessage(page)).toMatch(/invalid email or password/i);
  });

  test('AUTH-03 non-existent username is rejected with the same generic error', async ({
    page,
  }) => {
    await submitLogin(page, 'no.such.person@test.local', PASSWORD);
    await expect(page).toHaveURL(/login\.php/);
    // Must match AUTH-02 exactly, or the difference enumerates accounts.
    expect(await loginMessage(page)).toMatch(/invalid email or password/i);
  });

  test('AUTH-05 logout destroys the session and the dashboard is no longer reachable', async ({
    page,
  }) => {
    await loginAs(page, STAFF.admin);
    await page.goto('Admin/Auth/logout.php?redirect=login');

    await page.goto('Admin/dashboard.php');
    await expect(page, 'dashboard must not render after logout').toHaveURL(/login\.php/);
  });

  test('AUTH-06 protected URL while logged out redirects to login', async ({
    page,
    request,
    baseURL,
  }) => {
    const res = await rawGet(request, new URL('Admin/dashboard.php', baseURL).href);
    expect(res.status(), 'expected a redirect, not a rendered page').toBe(302);
    expect(locationOf(res.headers())).toMatch(/login\.php/);

    await page.goto('Admin/dashboard.php');
    await expect(page).toHaveURL(/login\.php/);
  });

  test('AUTH-13 SQL injection in the login fields does not authenticate', async ({ page }) => {
    await submitLogin(page, "' OR '1'='1", "' OR '1'='1");
    await expect(page).toHaveURL(/login\.php/);
    const body = await page.content();
    expect(looksLikePhpError(body), 'SQL error leaked to the page').toBe(false);
  });

  test('AUTH-14 empty credentials are rejected before any lookup', async ({ page }) => {
    await page.goto('login.php?force=1');
    // The form is novalidate, so the submit reaches PHP and the server must refuse.
    await page.locator('button.btn-signin-submit').click();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/login\.php/);
  });

  test('AUTH-CSRF login POST without a token is refused', async ({ request, baseURL }) => {
    const res = await request.post(new URL('login.php', baseURL).href, {
      form: { username: STAFF.admin, password: PASSWORD },
      maxRedirects: 0,
      failOnStatusCode: false,
    });
    // A missing token must not produce a successful login redirect.
    expect(res.status(), 'tokenless login should not redirect to a dashboard').not.toBe(302);
    expect(await res.text()).toMatch(/session expired/i);
  });
});

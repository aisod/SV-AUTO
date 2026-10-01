import { test, expect } from '@playwright/test';
import { STAFF, PASSWORD, submitLogin, logout, loginMessage } from '../support/sams';

/**
 * Section 1, the password-reset half of the checklist (AUTH-10/11/12).
 *
 * These were originally marked "manual only — needs SMTP". They do not:
 * when mail is unconfigured and the request is local, forgot-password.php
 * prints the reset URL on the page (erp_request_password_reset's
 * dev_reset_url), so the whole flow is drivable end to end.
 *
 * The tests reset a dedicated account and put its password back afterwards,
 * so they can run in any order alongside the rest of the suite.
 */

const RESET_TARGET = STAFF.hr;
const NEW_PASSWORD = 'Reset@5678';

/** Request a reset and return the dev reset URL, or null if none was offered. */
async function requestReset(page: import('@playwright/test').Page, email: string) {
  await page.goto('Admin/Auth/forgot-password.php');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');

  const link = page.locator('a.dev-link');
  return (await link.count()) ? await link.getAttribute('href') : null;
}

/** Set a new password using a reset URL. */
async function useResetLink(page: import('@playwright/test').Page, url: string, pw: string) {
  await page.goto(url);
  await page.locator('input[type="password"]').first().fill(pw);
  await page.locator('input[type="password"]').nth(1).fill(pw);
  await page.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
}

test.describe('AUTH - Password reset', () => {
  test('AUTH-11 an unregistered email does not reveal whether the account exists', async ({
    page,
  }) => {
    const url = await requestReset(page, 'definitely.not.registered@nowhere.test');

    expect(url, 'a reset link was offered for an address with no account').toBeNull();
    await expect(page.locator('.msg')).toContainText(/if this email is registered/i);
  });

  test('AUTH-10 a reset link sets a new password and revokes the old one', async ({ page }) => {
    const url = await requestReset(page, RESET_TARGET);
    expect(url, 'no reset link produced for a real account').toBeTruthy();

    await useResetLink(page, url!, NEW_PASSWORD);
    await expect(page).toHaveURL(/reset=success/);

    try {
      await submitLogin(page, RESET_TARGET, NEW_PASSWORD);
      await expect(page, 'the new password should be accepted').not.toHaveURL(/login\.php/);

      // Must log out first: login.php reads ?force=1 when deciding whether to
      // redirect (line 10) but the template branches on $alreadyLoggedIn alone
      // (line 199), so a signed-in visitor is never shown the form.
      await logout(page);

      await submitLogin(page, RESET_TARGET, PASSWORD);
      await expect(page, 'the old password should no longer work').toHaveURL(/login\.php/);
      expect(await loginMessage(page)).toMatch(/invalid email or password/i);
    } finally {
      // Put the account back so the rest of the suite can use it.
      const again = await requestReset(page, RESET_TARGET);
      if (again) await useResetLink(page, again, PASSWORD);
    }
  });

  test('AUTH-12 a reset link cannot be used twice', async ({ page }) => {
    const url = await requestReset(page, RESET_TARGET);
    expect(url, 'no reset link produced').toBeTruthy();

    await useResetLink(page, url!, NEW_PASSWORD);
    await expect(page).toHaveURL(/reset=success/);

    // Second visit to the same link: the form must be gone.
    await page.goto(url!);
    await expect(
      page.locator('input[type="password"]'),
      'a consumed reset link still offers the password form',
    ).toHaveCount(0);
    await expect(page.locator('body')).toContainText(/request a new reset link/i);

    const again = await requestReset(page, RESET_TARGET);
    if (again) await useResetLink(page, again, PASSWORD);
  });
});

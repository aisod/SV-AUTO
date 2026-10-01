import { test, expect } from '@playwright/test';
import {
  CLIENTS,
  PASSWORD,
  submitLogin,
  loginAs,
  loginMessage,
  fixtureFor,
  looksLikePhpError,
  setClientStatus,
} from '../support/sams';

/** Section 3: the client self-service portal and its status state machine. */
test.describe('CLIENT - Client portal', () => {
  test('CLIENT-01 a new registration is created with status = pending', async ({ page }) => {
    const email = `qa.reg.${Date.now()}@test.local`;
    await page.goto('Client/register.php');
    await page.locator('select[name="title"]').selectOption('Mr');
    await page.locator('[name="full_name"]').fill('Echo Registrant');
    await page.locator('[name="phone"]').fill('0811000099');
    await page.locator('[name="email"]').fill(email);
    await page.locator('[name="password"]').fill(PASSWORD);
    await page.locator('[name="confirm_password"]').fill(PASSWORD);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/registration successful/i);

    // The account must then behave as a pending client on the next login.
    await submitLogin(page, email, PASSWORD);
    await expect(
      page,
      'a freshly registered client should reach the pending-approval page',
    ).toHaveURL(/pending-approval\.php/);
  });

  test('CLIENT-05 an approved self-registered client reaches the dashboard', async ({ page }) => {
    const email = `qa.reg.approve.${Date.now()}@test.local`;

    await page.goto('Client/register.php');
    await page.locator('select[name="title"]').selectOption('Mr');
    await page.locator('[name="full_name"]').fill('Foxtrot Approved');
    await page.locator('[name="phone"]').fill('0811000097');
    await page.locator('[name="email"]').fill(email);
    await page.locator('[name="password"]').fill(PASSWORD);
    await page.locator('[name="confirm_password"]').fill(PASSWORD);
    await page.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');

    // Approve them, exactly as an admin would.
    setClientStatus(email, 'approved');

    await submitLogin(page, email, PASSWORD);
    await expect(
      page,
      'an approved client must not be held on the pending-approval page',
    ).not.toHaveURL(/pending-approval\.php/);
    await page.goto('Client/dashboard.php');
    await expect(page).toHaveURL(/Client\/dashboard\.php/);
  });

  test('CLIENT-02 a pending client is held at pending-approval.php', async ({ page }) => {
    await submitLogin(page, CLIENTS.pending, PASSWORD);
    await expect(page).toHaveURL(/pending-approval\.php/);

    // And must not be able to step around it.
    await page.goto('Client/dashboard.php');
    await expect(page).toHaveURL(/pending-approval\.php/);
  });

  test('CLIENT-03 an approved client reaches the dashboard', async ({ page }) => {
    await submitLogin(page, CLIENTS.approved, PASSWORD);
    await expect(page).toHaveURL(/Client\/dashboard\.php/);
  });

  test('CLIENT-04 a blocked client is denied with a blocked message', async ({ page }) => {
    await submitLogin(page, CLIENTS.blocked, PASSWORD);
    await expect(page).toHaveURL(/login\.php/);
    expect(await loginMessage(page)).toMatch(/suspended|blocked/i);
  });

  test('CLIENT-06 the quotations list shows only this client’s quotations', async ({
    page,
  }) => {
    const mine = fixtureFor(CLIENTS.approved);
    const theirs = fixtureFor(CLIENTS.other);

    await loginAs(page, CLIENTS.approved);
    await page.goto('Client/quotations.php');

    const body = (await page.locator('body').textContent()) ?? '';
    expect(body, 'own quotation should be listed').toContain('QA-ALPHA-001');
    expect(body, 'another client’s job card must not appear').not.toContain('QA-BRAVO-001');
    expect(mine.quotationId).not.toBe(theirs.quotationId);
  });

  test('CLIENT-07 another client’s quotation data is not disclosed', async ({ page }) => {
    const theirs = fixtureFor(CLIENTS.other);
    await loginAs(page, CLIENTS.approved);

    await page.goto(`Client/view_quotation.php?id=${theirs.quotationId}`);
    await expect(page.locator('body')).not.toContainText('QA-BRAVO-001');
    await expect(page.locator('body')).not.toContainText('Volvo FH16');
  });

  test('CLIENT-07b the cross-tenant request is denied cleanly', async ({ page }) => {
    const theirs = fixtureFor(CLIENTS.other);
    await loginAs(page, CLIENTS.approved);

    await page.goto(`Client/view_quotation.php?id=${theirs.quotationId}`);
    // includes/header.php has already emitted HTML by the time the ownership
    // check runs, so its header('Location: ...') cannot take effect and the
    // page renders an empty shell instead of redirecting to the list.
    await expect(page, 'should bounce back to the quotations list').toHaveURL(
      /quotations\.php/,
    );
  });

  test('CLIENT-08 another client’s invoice data is not disclosed', async ({ page }) => {
    const theirs = fixtureFor(CLIENTS.other);
    await loginAs(page, CLIENTS.approved);

    await page.goto(`Client/view_invoice.php?id=${theirs.invoiceId}`);
    await expect(page.locator('body')).not.toContainText('QA-BRAVO-001');
    await expect(page.locator('body')).not.toContainText('Volvo FH16');
  });

  test('CLIENT-09 own invoice renders without leaking PHP errors', async ({ page }) => {
    const mine = fixtureFor(CLIENTS.approved);
    await loginAs(page, CLIENTS.approved);

    await page.goto(`Client/view_invoice.php?id=${mine.invoiceId}`);
    const body = await page.content();
    expect(looksLikePhpError(body), 'invoice view leaked a PHP error').toBe(false);
    await expect(page.locator('body')).toContainText(/invoice/i);
  });

  test('CLIENT-11 blocking a client mid-session cuts off further access', async ({ page }) => {
    await loginAs(page, CLIENTS.approved);
    await expect(page).toHaveURL(/Client\/dashboard\.php/);

    // Block the account out-of-band, exactly as an admin would mid-session.
    setClientStatus(CLIENTS.approved, 'blocked');
    try {
      await page.goto('Client/dashboard.php');
      await expect(
        page,
        'a blocked client must not keep browsing on an existing session',
      ).not.toHaveURL(/Client\/dashboard\.php/);
    } finally {
      setClientStatus(CLIENTS.approved, 'approved');
    }
  });
});

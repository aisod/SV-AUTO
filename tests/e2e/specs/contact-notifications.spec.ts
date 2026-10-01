import { test, expect } from '@playwright/test';
import { STAFF, loginAs, looksLikePhpError } from '../support/sams';

/**
 * Section 10: notifications and the public contact form.
 *
 * contact_send.php requires a CSRF token minted by index.php and carries a
 * "company" honeypot field, so the direct-POST cases fetch a real token first.
 */
test.describe('CONTACT - Public contact form', () => {
  test('CONTACT-01 a valid submission is accepted and confirmed', async ({ page }) => {
    await page.goto('index.php');

    const form = page.locator('form').filter({ has: page.locator('[name="message"]') }).first();
    await expect(form, 'no contact form found on the homepage').toHaveCount(1);

    await form.locator('[name="first_name"]').fill('QA');
    await form.locator('[name="last_name"]').fill('Visitor');
    await form.locator('[name="email"]').fill('qa.visitor@test.local');
    const phone = form.locator('[name="phone"]');
    if (await phone.count()) await phone.first().fill('0811000050');
    await form.locator('[name="message"]').fill('Automated contact-form check.');

    await form.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');

    const body = await page.content();
    expect(looksLikePhpError(body), 'contact handler leaked a PHP error').toBe(false);
    await expect(page.locator('body')).toContainText(/thank|received|success|sent|touch/i);
  });

  test('CONTACT-02 a submission missing required fields is rejected', async ({
    page,
    request,
    baseURL,
  }) => {
    await page.goto('index.php');
    const token = (await page.content()).match(/name="csrf_token"\s+value="([^"]+)"/)?.[1];
    expect(token, 'no contact CSRF token').toBeTruthy();

    // Name and message are the server-side required fields; omit the message.
    // The handler puts the outcome in a one-shot session flash and redirects.
    // maxRedirects:0 matters - letting the request follow the 302 would consume
    // the flash before the assertion below can see it.
    await page.request.post(new URL('contact_send.php', baseURL).href, {
      form: {
        csrf_token: token!,
        first_name: 'QA',
        last_name: 'Visitor',
        email: 'qa.visitor@test.local',
        message: '',
      },
      maxRedirects: 0,
      failOnStatusCode: false,
    });
    await page.goto('index.php');

    const body = await page.content();
    expect(looksLikePhpError(body), 'contact handler leaked a PHP error').toBe(false);
    await expect(
      page.locator('body'),
      'an empty message should produce a validation error',
    ).toContainText(/fill in your name and message/i);
  });

  test('CONTACT-03 repeated rapid submissions are throttled', async ({ page, baseURL }) => {
    await page.goto('index.php');
    const html = await page.content();
    const token = html.match(/name="csrf_token"\s+value="([^"]+)"/)?.[1];
    expect(token, 'no contact CSRF token').toBeTruthy();

    const url = new URL('contact_send.php', baseURL).href;
    const send = () =>
      page.request.post(url, {
        form: {
          csrf_token: token!,
          first_name: 'QA',
          last_name: 'Spammer',
          email: 'qa.spam@test.local',
          phone: '0811000051',
          message: 'spam probe',
        },
        failOnStatusCode: false,
      });

    const results = [];
    for (let i = 0; i < 6; i++) results.push(await send());

    // The checklist asks whether rate limiting or a captcha exists.
    const accepted = results.filter((r) => r.status() < 400).length;
    expect(
      accepted,
      `all ${accepted} rapid submissions were accepted - no rate limiting or captcha`,
    ).toBeLessThan(results.length);
  });
});

test.describe('NOTIF - Notifications', () => {
  test('NOTIF-01 the admin notification feed responds with JSON', async ({ page, baseURL }) => {
    await loginAs(page, STAFF.admin);
    const res = await page.request.get(
      new URL('Admin/Utils/notifications_feed.php', baseURL).href,
      { failOnStatusCode: false },
    );
    const body = await res.text();
    expect(looksLikePhpError(body), 'notification feed leaked a PHP error').toBe(false);
    expect(() => JSON.parse(body), `feed did not return JSON: ${body.slice(0, 200)}`).not.toThrow();
  });

  test('NOTIF-02 the notification feed is not readable while logged out', async ({
    request,
    baseURL,
  }) => {
    const res = await request.get(new URL('Admin/Utils/notifications_feed.php', baseURL).href, {
      failOnStatusCode: false,
      maxRedirects: 0,
    });
    const body = res.status() === 200 ? await res.text() : '';
    expect(
      res.status() === 200 && /"id"|"message"|"title"/.test(body),
      'notifications are readable without a session',
    ).toBe(false);
  });
});

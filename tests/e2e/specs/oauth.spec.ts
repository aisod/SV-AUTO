import { test, expect } from '@playwright/test';

/**
 * Section 1, the Google OAuth cases (AUTH-08/09).
 *
 * The consent flow itself cannot be automated — it requires real Google Cloud
 * credentials and a sign-in through Google's own pages. What CAN be tested is
 * everything on our side of the redirect: the authorisation URL we build, and
 * the guards google-callback.php applies before it ever contacts Google.
 *
 * These tests need backend/config/google.local.php to exist. Placeholder values
 * are enough — every case here is rejected by the callback before the token
 * exchange, so no request reaches Google. Without that file the callback dies
 * on its "is OAuth configured" guard and the cases below are unreachable, so
 * they skip rather than fail.
 */

const AUTH = 'Admin/Auth/google-auth.php';
const CALLBACK = 'Admin/Auth/google-callback.php';

/** The authorisation URL google-auth.php redirects to, or null if unconfigured. */
async function authRedirect(request: import('@playwright/test').APIRequestContext, baseURL: string) {
  const res = await request.get(new URL(AUTH, baseURL).href, {
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  if (res.status() !== 302) return null;
  return res.headers()['location'] ?? null;
}

test.describe('AUTH - Google OAuth', () => {
  test('OAUTH-01 the authorisation request is well formed', async ({ request, baseURL }) => {
    const loc = await authRedirect(request, baseURL!);
    test.skip(loc === null, 'OAuth not configured — see google.local.example.php');

    const url = new URL(loc!);
    expect(url.origin + url.pathname).toBe('https://accounts.google.com/o/oauth2/v2/auth');
    expect(url.searchParams.get('response_type')).toBe('code');
    expect(url.searchParams.get('scope')).toBe('email profile');

    // A state token must be present and long enough to be unguessable.
    const state = url.searchParams.get('state') ?? '';
    expect(state.length, 'state token is too short to resist guessing').toBeGreaterThanOrEqual(32);

    // The client secret must never appear in a front-channel redirect.
    expect(loc!, 'client_secret leaked into the authorisation URL').not.toContain('client_secret');
  });

  test('OAUTH-02 two authorisation requests use different state tokens', async ({
    request,
    baseURL,
  }) => {
    const a = await authRedirect(request, baseURL!);
    test.skip(a === null, 'OAuth not configured');
    const b = await authRedirect(request, baseURL!);

    const s = (u: string) => new URL(u).searchParams.get('state');
    expect(s(a!), 'state token is not regenerated per request').not.toBe(s(b!));
  });

  test('OAUTH-03 the callback rejects a request with no state', async ({ request, baseURL }) => {
    test.skip((await authRedirect(request, baseURL!)) === null, 'OAuth not configured');

    const res = await request.get(new URL(`${CALLBACK}?code=forged`, baseURL).href, {
      failOnStatusCode: false,
    });
    expect(await res.text()).toMatch(/invalid state/i);
  });

  test('OAUTH-04 the callback rejects a mismatched state (CSRF)', async ({ request, baseURL }) => {
    test.skip((await authRedirect(request, baseURL!)) === null, 'OAuth not configured');

    const res = await request.get(
      new URL(`${CALLBACK}?code=forged&state=attacker-controlled`, baseURL).href,
      { failOnStatusCode: false },
    );
    expect(
      await res.text(),
      'an attacker-supplied state was accepted',
    ).toMatch(/invalid state/i);
  });

  test('OAUTH-05 a valid state with no authorisation code is rejected', async ({
    request,
    baseURL,
  }) => {
    const loc = await authRedirect(request, baseURL!);
    test.skip(loc === null, 'OAuth not configured');

    // Reuse the session that minted the state so it matches.
    const state = new URL(loc!).searchParams.get('state')!;
    const res = await request.get(new URL(`${CALLBACK}?state=${state}`, baseURL).href, {
      failOnStatusCode: false,
    });
    expect(await res.text()).toMatch(/authorization code not received/i);
  });

  test('OAUTH-06 the callback fails closed when OAuth is unconfigured', async ({
    request,
    baseURL,
  }) => {
    // Whatever the configuration, neither endpoint may leak a secret or a
    // stack trace to an unauthenticated caller.
    for (const p of [AUTH, CALLBACK]) {
      const res = await request.get(new URL(p, baseURL).href, {
        maxRedirects: 0,
        failOnStatusCode: false,
      });
      const body = await res.text();
      expect(body, `${p} leaked a stack trace`).not.toMatch(/Stack trace:|Fatal error/i);
      expect(body, `${p} leaked a client secret`).not.toMatch(/GOOGLE_CLIENT_SECRET|client_secret=/);
    }
  });
});

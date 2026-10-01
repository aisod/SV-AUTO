# Manual test procedure — Google OAuth (AUTH-08, AUTH-09)

These two cases cannot be automated: they require real Google Cloud credentials
and a sign-in through Google's own consent screen. Everything on our side of
the redirect is covered by `specs/oauth.spec.ts` (OAUTH-01 to OAUTH-06) and
passes; what follows is the part only a human can run.

---

## Before you start — one known blocker

**The app will almost certainly fail with `redirect_uri_mismatch` until this is
fixed.**

`google-auth.php:32` builds the authorisation URL with
`http_build_query($params)`, which uses RFC1738 encoding. Because the app is
served from a path containing spaces (`/SV Auto Truck Repair/`), those spaces
become `+`:

```
redirect_uri=http%3A%2F%2Flocalhost%3A8080%2FSV+Auto+Truck+Repair%2F...
```

Google Cloud Console will not accept a redirect URI containing literal spaces,
so the value you register there will be the `%20` form. Google compares the two
as strings, and `+` will not match `%20`.

Pick one of these before testing:

| Option | Change | Notes |
| --- | --- | --- |
| **A — recommended** | `google-auth.php:32` → `http_build_query($params, '', '&', PHP_QUERY_RFC3986)` | One-line fix. Produces `%20`, which matches what Console accepts. |
| B | Register the `+` form in Console as well as the `%20` form | Console may reject it; try A first. |
| C | Serve the app from a path with no spaces | Requires changing `APP_BASE` in `config.php` and the web-root folder name. |

Option A is the real fix — the same bug will affect any deployment whose base
path contains a space or any other character that RFC1738 and RFC3986 encode
differently.

---

## Setup

### 1. Replace the placeholder credentials

`backend/config/google.local.php` currently holds **placeholder values** written
for the automated tests. They are not real credentials and the consent flow
will not work with them. Replace the file with values from your own project:

```php
<?php
define('GOOGLE_CLIENT_ID',     'YOUR-ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR-SECRET');
define('GOOGLE_REDIRECT_URI',  'http://localhost:8080/SV Auto Truck Repair/frontend/Admin/Auth/google-callback.php');
define('GOOGLE_REGISTER_REDIRECT_URI', 'http://localhost:8080/SV Auto Truck Repair/frontend/Admin/Auth/register.php');
```

The file is gitignored. Do not commit it.

### 2. Google Cloud Console

1. Create or open a project at <https://console.cloud.google.com/>.
2. **APIs & Services → OAuth consent screen** — configure as *External*, add
   your test Google account under **Test users** while the app is unpublished.
3. **APIs & Services → Credentials → Create credentials → OAuth client ID**,
   type *Web application*.
4. Under **Authorised redirect URIs**, add exactly:
   ```
   http://localhost:8080/SV%20Auto%20Truck%20Repair/frontend/Admin/Auth/google-callback.php
   ```
5. Copy the client ID and secret into `google.local.php`.

### 3. Start the stack

```bash
"C:/sams-stack/mariadb/bin/mariadbd.exe" --defaults-file=C:/sams-stack/my.ini --console
```

```bash
cd /c/sams-stack/htdocs && PHP_CLI_SERVER_WORKERS=8 "C:/sams-stack/php/php.exe" -c "C:/sams-stack/php/php.ini" -S localhost:8080 -t "C:/sams-stack/htdocs"
```

### 4. Confirm the wiring before involving Google

```bash
cd tests/e2e && npx playwright test -g "OAUTH-" --reporter=list
```

All six must pass. If they skip, `google.local.php` is not being read.

---

## AUTH-08 — Sign in with Google, account already exists

**Precondition:** a client row exists whose `email` matches the Google account
you will sign in with.

```sql
-- run against sams_db, substituting your address
INSERT INTO clients (name, email, phone, address, title, first_name, last_name,
                     password_hash, role, status)
VALUES ('OAuth Tester', 'YOUR-ADDRESS@gmail.com', '0811000090', 'Test',
        'Mr', 'OAuth', 'Tester', '', 'client', 'approved');
```

**Steps**

1. Open `http://localhost:8080/SV%20Auto%20Truck%20Repair/frontend/login.php`.
2. Click the **G** button in the social row.
3. Complete the Google consent screen with the matching account.

**Expected:** redirected to `Client/dashboard.php`, signed in as that client.
The existing row is reused — `google_id` is populated on it, and no second
client row is created.

**Record**

- [ ] Landed on the client dashboard
- [ ] `SELECT id, email, google_id, status FROM clients WHERE email = '…'` shows
      one row with `google_id` now set
- [ ] No duplicate client row was created

---

## AUTH-09 — Sign in with Google, no matching account

**Precondition:** no `clients` row exists for the Google account you use. Use a
second Google account, or delete the row created above.

**Steps**

1. Sign out fully, then open the login page.
2. Click **G** and complete consent with the non-matching account.

**Expected per the checklist:** "Appropriate handling (auto-create, or rejection
message) — verify actual behavior matches intent."

**Actual behaviour, from code review of `google-callback.php:130`:** a new
`clients` row is created with `status = 'pending'` and a random password hash,
the session is marked `google_new_user`, and the user is sent to
`complete-profile.php`. It fails safe — no account is auto-approved.

**Record**

- [ ] A new client row was created with `status = 'pending'`
- [ ] Redirected to `complete-profile.php`, not straight to the dashboard
- [ ] The account cannot reach `Client/dashboard.php` until an admin approves it

---

## Two things to watch for specifically

These come from code review and are the reason AUTH-09 is worth running
carefully rather than just ticking off.

### Finding H10 — the verified-email claim is never checked

`google-callback.php:79` links an identity to an existing account with:

```php
SELECT ... FROM clients WHERE email = ? OR google_id = ?
```

Nothing in the file reads Google's `email_verified` claim. Matching on an
unverified address is the standard account-takeover path in OAuth
integrations.

**Worth testing if you can:** sign in with a Google Workspace account whose
email is unverified, or any account whose address you control but have not
verified with Google, and whose address matches an existing client. If it links
straight into that client's account, H10 is confirmed as exploitable rather
than theoretical.

### Finding M6 — staff accounts are not handled

`google-callback.php` never queries the `users` table. Sign in with the Google
account of an existing **staff** member (say the address on `qa.admin@test.local`,
if you point it at a real mailbox) and confirm what happens.

**Expected from code review:** they do not reach their staff account. A brand
new *client* account is created instead, in pending status.

**Record**

- [ ] Staff member signing in with Google receives a client account, not their
      staff account
- [ ] Their original `users` row is untouched

---

## Results

| Case | Expected | Result | Notes |
| --- | --- | --- | --- |
| AUTH-08 | Existing client linked, reaches dashboard | | |
| AUTH-09 | New client created as pending, sent to complete-profile | | |
| H10 probe | Unverified email must not link to an existing account | | |
| M6 probe | Staff member gets a client account | | |

---

## Afterwards

Restore the placeholder credentials so the automated suite keeps working
without your real client secret sitting on disk:

```php
define('GOOGLE_CLIENT_ID', 'test-harness-not-a-real-client.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'test-harness-not-a-real-secret');
```

Or delete `google.local.php` entirely — the six OAuth tests skip cleanly when it
is absent, except OAUTH-06, which verifies the unconfigured path fails closed
and runs either way.

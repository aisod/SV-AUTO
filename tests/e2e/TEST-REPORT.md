# SAMS — Test Execution Report

**System:** SV Auto Truck Repair — Shop Automated Management System
**Run date:** 30 September 2026
**Commit:** `638dd6b` (branch `main`, clean tree at start)
**Method:** Playwright 1.49 driving Chromium, against a locally built stack
**Checklist:** *SAMS Test Cases v1.0* (28 September 2026)

---

## 1. Executive summary

The application was built, deployed and exercised end to end. It runs, and its
core flows — staff login, the admin dashboard, quotations, invoices, the client
portal — work. **Data isolation between client tenants holds**: no test was able
to read another client's quotation or invoice.

However, the run surfaced **two critical access-control defects, eleven
high-severity defects, seven medium and four low**. Two of the criticals are
exploitable by an unauthenticated visitor or by the lowest-privilege account in
the system.

Three findings block ordinary use of the product as shipped:

- **No self-registered client can ever use the portal**, even after an admin
  approves them (H3).
- **Nine of fifteen admin sidebar modules are dead** — employees, inventory,
  reports, expenses, statutory, purchase orders, HR requests, user roles,
  services (H6).
- **The committed database schema cannot be installed** by following the
  documented steps (M1, M2, M3).
- **Every statutory document upload is unretrievable** — files are written to
  one directory and linked from another (H9).
- **Password reset cannot work on the production host** — two path strings use
  the wrong case, which Windows tolerates and Linux does not (H11).

### Result counts

| Outcome | Count |
| --- | --- |
| Checklist cases in scope [2] | 89 |
| Automated tests executed | 86 |
| Passed | 49 |
| Failed | 36 |
| Skipped (no feature to test) | 1 |
| Distinct defects confirmed | 23 |
| Checklist cases requiring manual execution | 2 |

Every failure below was reproduced by hand outside Playwright before being
recorded, so none of them are test artifacts.

**Correction against an earlier draft of this report.** Six ADMIN cases
(12, 13, 15, 18, 20, 21) were initially recorded as passing. They were not: the
PHP 404 page echoes the requested path, so `Admin/Employee/Employee/employees.php`
contains the word "employee" and an assertion on page text alone passed against
a Not Found page. Those tests now check the HTTP status and reject the 404 body.
The defects were always present; the tests were not catching them. A seventh
case, AUTH-04, carried a test that asserted nothing and has been replaced with
an explicit skip.

---

## 2. Environment

The documented setup (XAMPP) was not installed on the test machine, and the
`winget` install stalled on an elevation prompt. A portable, equivalent stack
was built instead:

| Component | Version | Notes |
| --- | --- | --- |
| PHP | 8.2.34 NTS | `pdo_mysql`, `mbstring`, `gd`, `openssl`, `curl`, `zip`, `intl` |
| MariaDB | 11.4.9 | port 3307, matching `backend/config/config.php:36` |
| dompdf | 3.1.5 | via `composer install` |
| PHPMailer | 7.0.2 | via `composer install` |
| Chromium | 153.0.8010.12 | Playwright build |
| Web server | PHP built-in | Apache not used — see limitation below |

**Limitation:** Apache was not used, so the `.htaccess` rewrite rules were not
exercised. Those rules only redirect pre-split legacy paths (`/Admin/*`,
`/Config/*`) and no checklist case covers them, so coverage is unaffected.

**Base URL:** `http://localhost:8080/SV%20Auto%20Truck%20Repair/frontend/`

---

## 3. Critical findings

### C1 — Any authenticated user can permanently delete records

**Severity: Critical · Broken access control · Evidence: `evidence/EV-06-client-permanent-delete.png`**

`frontend/Admin/recycle-bin/permanent_delete_all.php` runs
`DELETE FROM expenses WHERE deleted_at IS NOT NULL` and checks only that *some*
session exists:

```php
if (!isset($_SESSION['user_id'])) {   // line 5 — the only gate
    header('Location: ../Auth/login.php');
    exit;
}
```

A **self-registered client** — the lowest-privilege account, obtainable by
anyone from the public registration form — executed this endpoint successfully
and received the success redirect. `permanent_delete_selected.php` has the same
gate.

Because it is a `GET` with no CSRF token, it also fires from any third-party
page an authenticated user visits.

**Fix:** add `require_admin()`, convert to `POST`, and require a CSRF token.

### C2 — Unauthenticated visitors can run schema DDL

**Severity: Critical · Evidence: `evidence/EV-07a-anon-setup-recycle-bin.png`, `evidence/EV-07b-anon-run-migrations.png`**

With no session at all, all three of these execute and return HTTP 200:

| Endpoint | What it does |
| --- | --- |
| `Admin/recycle-bin/setup_recycle_bin_all.php` | `ALTER TABLE` on 8 tables |
| `Admin/Statutory/add_deleted_column_statutory.php` | `ALTER TABLE statutory_docs` |
| `backend/migrations/run_all_migrations.php` | Executes the full migration file |

**Fix:** these are one-off setup scripts. Remove them from the deployed tree, or
gate them behind `require_admin()` plus an environment check.

---

## 4. High-severity findings

### H1 — Role boundaries are not enforced on pages that render

**Evidence: `evidence/EV-04-technician-admin-dashboard.png`, `evidence/EV-05-technician-recycle-bin.png`**

Measured directly, per role:

| Page | Technician | Finance | HR | Manager |
| --- | --- | --- | --- | --- |
| `Admin/dashboard.php` | **served (50 KB)** | **served (50 KB)** | **served (50 KB)** | **served (57 KB)** |
| `Admin/recycle-bin/recycle_bin.php` | **served (63 KB)** | **served (63 KB)** | **served (63 KB)** | **served (69 KB)** |
| `Admin/Client/clients.php` | blocked | blocked | blocked | blocked |
| `Admin/Invoice/invoices.php` | blocked | blocked | blocked | blocked |
| `Admin/JobCard/job_card.php` | blocked | blocked | blocked | blocked |

The guard exists but is barely applied. Across 145 admin PHP files:

- **16** include `Auth/auth.php`
- **9** call `require_admin()`

Pages that guard themselves with only `isset($_SESSION['user_id'])` — including
the dashboard and the recycle bin — admit every authenticated role. Note that
`auth.php` bounces managers away from `/Admin/` URLs, but `dashboard.php` never
includes `auth.php`, so a manager reaches it anyway.

*Covers MGR-06, TECH-04, FIN-04, HR-04.*

### H2 — No CSRF protection on state-changing endpoints

Only **2 of 145** admin PHP files reference a CSRF token (`Auth/register.php`,
`Client/clients.php`). `delete_job_card.php` accepted a forged cross-site `GET`
carrying a foreign `Referer` and no token, returning a success redirect.

The checklist (SEC-05) asks to flag this if absent. It is absent.

### H3 — Self-registered clients can never access the portal

Registration creates a row in **`users`** *and* a row in **`clients`**, with
independent auto-increment ids:

| Account | `users.id` | `clients.id` |
| --- | --- | --- |
| `qa.reg.…@test.local` | 17 | 11 |

`login.php` checks `users` first, so the session gets `user_id = 17`.
`Client/includes/header.php:18` then refreshes status with:

```php
$stmt = $pdo->prepare("SELECT status FROM clients WHERE id = ?");
$stmt->execute([$userId]);          // users.id, not clients.id
$_SESSION['status'] = $currentStatus ?: 'pending';
```

Reproduced end to end (`evidence/EV-13-approved-client-still-pending.png`): a
fresh registration was set to `approved`, the user logged in successfully, and
was still redirected to `pending-approval.php`. The lookup
misses, the status defaults to `pending`, and the client is locked out forever
regardless of admin action.

**This also has a security dimension, and it is already live.** The seeded data
demonstrates it: `users.id 6` is `john.doe@email.com`, carrying the Client role
from `database.sql`. Logging in sends it to the client portal, where
`header.php` reads `clients WHERE id = 6` — which is
`qa.client.blocked@test.local`, **a different person**, whose status is
`blocked`. Because H8 means `blocked` is never handled, the dashboard is served
anyway (HTTP 200).

One staff row therefore reads a second person's account status, and the status
it reads should have denied access but does not. This is checklist case ROLE-06,
which asks to verify the assumption that a client role "should not occur" in the
staff table. It does occur, in the shipped seed data.

*Covers CLIENT-05.* Note that CLIENT-01 **passes** — registration itself works
and the account is correctly created as `pending`. The defect only appears at
the next step, when approval fails to release the account, so a checklist run
that stops at CLIENT-01 would miss it entirely.

### H4 — Every client invoice fatals and leaks filesystem paths

**Evidence: `evidence/EV-01-client-invoice-fatal-error.png`**

```
Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found:
1054 Unknown column 'i.issue_date' in 'SELECT'
in C:\Users\Tommy Kampolo\Documents\GitHub\SV-AUTO\frontend\Client\view_invoice.php:69
```

`Client/view_invoice.php:54` selects `i.issue_date`. The column created by
`add_invoices_table.sql` is **`issued_date`**. The other nine files that touch
this column spell it correctly — this is a single-file typo that breaks the
entire client invoice view, for every client, every time.

It also renders SEC-06 a failure: a full stack trace with absolute paths is
shown to the end user.

*Covers CLIENT-09, SEC-06. Fix: `issue_date` → `issued_date` at lines 54 and 366.*

### H5 — The admin quotations module is entirely dead

**Evidence: `evidence/EV-02-admin-quotations-fatal-error.png`**

`Admin/Quotation/quotations.php:166` queries `created_at` on `quotations`. That
column does not exist (`id, job_card_id, client_id, amount, details, status,
submitted_at, client_status, client_response_date, client_response_notes,
deleted_at`). The page returns 516 bytes — just the fatal error.

The comment on line 165 reads *"use submitted_at, fall back to created_at"*, so
the fallback was written against a column that was never added.

*Covers ADMIN-06.*

### H6 — Nine of fifteen admin modules are dead redirect stubs

**Evidence: `evidence/EV-03-inventory-404-doubled-path.png`**

Each is a ~190-byte file that redirects to its own path *relative to itself*,
producing a doubled path that 404s:

| Sidebar link | Size | Redirects to | Result |
| --- | --- | --- | --- |
| `Employee/employees.php` | 193 B | `Employee/employees.php` | `/Admin/Employee/Employee/…` → 404 |
| `Expense/expenses.php` | 191 B | `Expense/expenses.php` | 404 |
| `Inventory/inventory.php` | 194 B | `Inventory/inventory.php` | 404 |
| `Report/reports.php` | 189 B | `Report/reports.php` | 404 |
| `Service/services_update.php` | 198 B | `Service/services_update.php` | 404 |
| `Statutory/statutory.php` | 194 B | `Statutory/statutory.php` | 404 |
| `hr-request/hr_requests.php` | 197 B | `hr-request/hr_requests.php` | 404 |
| `purchase-order/purchase_orders.php` | 205 B | `purchase-order/purchase_orders.php` | 404 |
| `user-role/user_roles.php` | 195 B | `user-role/user_roles.php` | 404 |

The real page content is not elsewhere in the repository — `git log` shows these
files were committed as stubs in `638dd6b`. The working modules (clients,
invoices, job cards, quotations, recycle bin) are 34–86 KB files.

**This is not confined to the Admin portal.** `Manager/JobCard/job_card.php` is
a tenth stub of the same shape: it redirects to `manager_job_cards.php` relative
to its own directory, producing `Manager/JobCard/manager_job_cards.php`, which
404s. The working list is one level up at `Manager/manager_job_cards.php`
(MGR-02, which passes).

*Covers ADMIN-12, 13, 15, 17, 18, 20, 21, MGR/FIN/HR module cases.*

### H7 — Three of six role dashboards do not exist

**Evidence: `evidence/EV-08`, `EV-09`, `EV-10`**

`getRoleRedirect()` in `frontend/login.php:131-146` routes to six destinations.
Three are missing from the filesystem, so those users log in successfully and
land on a 404:

| Role | Redirect target | Exists |
| --- | --- | --- |
| admin | `Admin/dashboard.php` | yes |
| manager | `Manager/manager_dashboard.php` | yes |
| **technician** | `Technician/technician_dashboard.php` | **no** |
| **finance** | `Admin/finance_dashboard.php` | **no** |
| **hr** | `Hr/hr_dashboard.php` | **no** |
| client | `Client/dashboard.php` | yes |

*Covers ROLE-03, ROLE-04, ROLE-05, TECH-01, FIN-01, HR-01, ADMIN-24.*

### H8 — Blocking a client mid-session has no effect

`Client/includes/header.php:24` refreshes status on every page load, then acts
on it — but only branches on one value:

```php
if ($_SESSION['role_name'] === 'client' && ($_SESSION['status'] ?? 'pending') === 'pending') {
```

`blocked` is never handled, so a client blocked by an admin during an active
session keeps full access until they log out. Login-time blocking works
correctly (CLIENT-04 passed); only mid-session enforcement is missing.

*Covers CLIENT-11.*

---

### H9 — Uploaded statutory documents are written where nothing can find them

`upload_statutory.php:31` builds its target as `__DIR__ . '/../uploads/statutory/'`.
`__DIR__` is `frontend/Admin/Statutory`, so files land in
**`frontend/Admin/uploads/statutory/`** — but line 50 records the URL as
`uploads/statutory/<name>`, which resolves from the frontend root to
**`frontend/uploads/statutory/`**.

Verified by uploading two files: both were written to `frontend/Admin/uploads/`
(a directory that did not previously exist and is not in the repository), while
the database rows pointed at `frontend/uploads/`. Requesting the recorded URL
returns 404. **Every statutory document uploaded through the app is
unretrievable.**

*Fix: `__DIR__ . '/../../uploads/statutory/'`.*

### H10 — The OAuth callback never checks Google's `email_verified` claim

`google-callback.php:79` links a Google identity to an existing account on
`WHERE email = ? OR google_id = ?`, and nothing in the file reads Google's
`email_verified` / `verified_email` claim.

Matching on an unverified email is the standard account-takeover path in OAuth
integrations: an identity whose email address is attacker-controlled and
unverified would be linked straight into the matching client account. The
callback also validates `state` with `!==` rather than `hash_equals()`.

Could not be exercised live: `backend/config/google.local.php` does not exist
(correctly — it is gitignored), so `google-auth.php` fails closed with
"Google OAuth is not configured" and leaks nothing. This finding is therefore
from code review, not a reproduced exploit.

*Fix: require the verified-email claim before matching on email; use
`hash_equals()` for the state comparison.*

### H11 — Email is broken in production by a path-casing mismatch

`backend/config/functions.php` builds two paths with the wrong case:

| Code asks for | Actually on disk |
| --- | --- |
| `backend/Config/mail.php` | `backend/config/` |
| `backend/Libraries/PHPMailer/src/` | `backend/libraries/` |

Windows is case-insensitive, so local testing passes. Linux is not, and the
documented production host is Hostinger. There, `mail.php` never loads and
PHPMailer is never found, so `erp_send_password_reset_email()` returns
"PHPMailer is not installed" and **password reset cannot work at all**.

It also promotes L4 from a theoretical concern to a live one: with mail failing,
the reset response differs depending on whether the account exists, which is an
account-enumeration oracle.

*Fix: four strings in functions.php — `/Config/` to `/config/`, `/Libraries/` to
`/libraries/`.*

## 5. Medium findings

### M1 — `database.sql` cannot be executed top to bottom

`users` is declared at line 11 with `FOREIGN KEY (client_id) REFERENCES
clients(id)`, but `clients` is not created until line 24. On a clean server,
**10 of 18 `CREATE TABLE` statements fail** with errno 150:

`users`, `employees`, `job_cards`, `quotations`, `purchase_orders`, `invoices`,
`payments`, `expenses`, `hr_forms`, `audit_logs`

**Fix:** move the `clients` definition above `users`, or wrap the load in
`SET FOREIGN_KEY_CHECKS = 0`.

### M2 — The client migrations cannot apply

`update_clients_table.sql` fails on three statements:

- `ADD COLUMN … AFTER last_name` — `last_name` does not exist on the table
  `database.sql` created, so the whole multi-clause `ALTER` aborts
- `UPDATE clients SET title = 'Mr'` — `title` was never added
- `CHANGE COLUMN password password_hash` — no `password` column exists

`add_client_status.sql` and `fix_google_oauth.sql` then fail in turn. The net
effect is that **`clients` never gains `password_hash`, `role` or `status`** —
the columns `login.php:82` depends on. Client login is impossible on a
by-the-book install.

### M3 — Required setup step is undocumented

The `deleted_at` columns that `Admin/dashboard.php` and most list pages query
are added only by `Admin/recycle-bin/setup_recycle_bin_all.php`. No migration
creates them and the documentation's four installation steps never mention
running it. A by-the-book install produces a dashboard that fatals.

### M4 — No rate limiting on the public contact form

Six rapid submissions were all accepted and all persisted to
`contact_enquiries`. There is a CSRF token and a honeypot field, but no
throttle and no captcha. The checklist (CONTACT-03) asks to flag this if absent.

### M5 — Cross-tenant requests are not denied cleanly

**Evidence: `evidence/EV-11-cross-tenant-quotation.png`**

`Client/view_quotation.php:41` and `view_invoice.php:71` correctly detect the
mismatch and call `header('Location: …')` — but `includes/header.php` has
already emitted HTML, so the redirect cannot be sent. PHP emits a
"headers already sent" warning and the page renders an empty shell.

**No data is disclosed** — the SQL is properly scoped by `client_id` — so the
security property holds. The handling is just incorrect.

---

### M6 — Google sign-in only ever creates client accounts

`google-callback.php` touches the `clients` table exclusively — it never
queries or updates `users`. A staff member who signs in with Google therefore
does not reach their staff account; they get a brand-new **client** account in
`pending` status.

AUTH-09 asks to "verify actual behavior matches intent". The behaviour is
consistent and fails safe (new accounts are `pending`, never auto-approved),
but it means Google sign-in is a client-only feature despite appearing on the
staff login page.

### M7 — `login.php?force=1` does not work

`login.php:10` consults `?force` when deciding whether to redirect an
already-signed-in visitor, but the template at line 199 branches on
`$alreadyLoggedIn` alone. A signed-in user sent to `login.php?force=1` is shown
"Continue to dashboard / Sign out" and **never the login form**, so they cannot
switch accounts without logging out first.

Found because the AUTH-10 test needed two consecutive logins in one session.

## 6. Low findings

| ID | Finding |
| --- | --- |
| L1 | `Client/view_invoice.php:11` — the payment-notification handler tests `isset($inv)` about 60 lines before `$inv` is assigned, so it can never fire. Dead code. |
| L2 | `contact_send.php:126` writes enquiry files to `frontend/storage/`, but the documentation and the existing committed files use `backend/storage/`. ADMIN-26 checks the wrong directory. |
| L4 | The reset flow's response differs by account existence when mail fails: an unknown address returns the generic "If this email is registered…", while a real one returns a mail-transport error. On localhost this is the dev-link branch and harmless, but on a production server with broken SMTP it becomes an account-enumeration oracle. |
| L3 | `database.sql` seeds six users with literal `'hashed_password1'`-style strings rather than bcrypt hashes, so no seeded account can log in. |

---

## 7. What passed

These were verified working and are worth recording:

- **Tenant data isolation.** Requesting another client's quotation or invoice by
  id disclosed nothing. Both queries are correctly scoped by `client_id`.
- **Login CSRF token** — a tokenless login POST is refused.
- **Brute-force throttle** — 8 attempts then a 15-minute lockout.
- **No user enumeration** — wrong password and unknown user return the identical
  message.
- **SQL injection** in the login fields is rejected with no error leakage.
- **`PHPSESSID` is `HttpOnly`**, and `session_regenerate_id(true)` runs on login.
- **Logout** destroys the session; protected pages are not reachable afterwards.
- **Client status at login** — pending → `pending-approval.php`, approved →
  dashboard, blocked → denied with a suspension message.
- **Contact form** — CSRF token, honeypot field, and server-side validation all
  work; empty submissions are rejected and not stored.
- **Admin dashboard, clients, invoices, job cards, recycle bin** render correctly
  with no SQL errors.
- **Password reset end to end** — a reset link sets a new password, the old
  password stops working immediately, and the link is rejected on reuse
  (AUTH-10, AUTH-12). An unregistered address gets the generic response with no
  link offered (AUTH-11).
- **File upload (SEC-01)** — a bare `.php` is refused by the extension
  whitelist. A `.php` renamed `.jpg` *is* accepted (content is never
  inspected), but the stored file keeps its `.jpg` extension and is served as
  inert `image/jpeg`, so it does not execute. The checklist's requirement
  ("rejected **or** not executable") is met on the second clause — see the
  caveat below.

---

### Caveat on SEC-01

It passes only because the extension whitelist happens to keep a safe
extension on disk. There is no defence in depth behind it: file content is
never inspected, and neither `frontend/uploads/` nor the directory files
actually land in (H9) carries an `.htaccess` deny rule. On a server that maps
extra extensions to PHP, or if any code ever `include`s an uploaded path, this
becomes remote code execution. Worth hardening even though it passes today.

## 8. Checklist coverage

86 automated tests cover 75 of the 89 checklist cases. The remaining 14 are
recorded in the completed checklist against the finding that blocks them, or as
not implemented; none is a bare "not run".

| Section | Checklist cases | Tests run | Passed | Failed |
| --- | --- | --- | --- | --- |
| 1. Authentication (AUTH) | 14 | 13 | 11 | 1 |
| 1a. Google OAuth (OAUTH) | — | 6 | 6 | 0 |
| 2. Role redirect (ROLE) | 7 | 8 | 3 | 5 |
| 3. Client portal (CLIENT) | 11 | 12 | 8 | 4 |
| 4. Admin (ADMIN) | 26 | 17 | 7 | 10 |
| 5. Manager (MGR) | 7 | 7 | 5 | 2 |
| 6. Technician (TECH) | 4 | 2 | 0 | 2 |
| 7. Finance (FIN) | 4 | 4 | 0 | 4 |
| 8. HR (HR) | 4 | 4 | 0 | 4 |
| 9. Security (SEC) | 7 | 8 | 5 | 3 |
| 10. Notifications / contact | 5 | 5 | 4 | 1 |
| **Total** | **89** | **86** | **49** | **36** |

One AUTH test is skipped rather than run: AUTH-04 requires a disabled staff
account, and the `users` table has no `active`, `disabled` or `status` column,
so the feature does not exist. It is recorded as skipped rather than given a
test that passes without measuring anything.

Sections 6–8 are thin on *useful* coverage because the technician, finance and
HR dashboards do not exist (H7). Every test in those sections fails, and the
cases beyond access control cannot be attempted at all.

Eleven tests go beyond the checklist and are named to distinguish them:
`AUTH-CSRF` (tokenless login POST), `ROLE-DASH` (dashboard existence),
`ADMIN-NAV` (every sidebar link resolves), `CLIENT-07b` (clean denial versus
non-disclosure), `SEC-DEL` (the permanent-delete endpoint), and `OAUTH-01`
through `OAUTH-06` (everything on our side of the Google redirect).

### The 36 failures

| Tests | Finding |
| --- | --- |
| `ADMIN-12`, `ADMIN-13`, `ADMIN-15`, `ADMIN-17`, `ADMIN-18`, `ADMIN-20`, `ADMIN-21`, `ADMIN-NAV`, `MGR-03`, `FIN-03`, `HR-02`, `HR-03` | H6 — dead module stubs |
| `ROLE-03`, `ROLE-04`, `ROLE-05`, `ROLE-DASH`, `ADMIN-24`, `TECH-01`, `FIN-01`, `HR-01` | H7 — missing dashboards |
| `AUTH-07`, `MGR-06`, `TECH-04`, `FIN-04`, `HR-04` | H1 — role boundaries not enforced |
| `CLIENT-05`, `ROLE-06` | H3 — client status read from the wrong key space |
| `ADMIN-06` | H5 — quotations queries a `created_at` that does not exist |
| `CLIENT-09` | H4 — invoice selects `issue_date` instead of `issued_date` |
| `CLIENT-11` | H8 — `blocked` not handled mid-session |
| `CLIENT-07b` | M5 — redirect issued after output has started |
| `FIN-02` | H1 inverse — the finance role is locked out of its own module |
| `SEC-05` | H2 — no CSRF on state-changing routes |
| `SEC-07` | C2 — anonymous visitors can run schema DDL |
| `SEC-DEL` | C1 — a client can execute the permanent-delete endpoint |
| `CONTACT-03` | M4 — no rate limiting on the contact form |

### Not automatable in this environment

| ID | Reason |
| --- | --- |
| AUTH-08, AUTH-09 | The Google consent flow — needs live Google Cloud credentials and a sign-in through Google's own pages. Procedure in [MANUAL-TESTS.md](MANUAL-TESTS.md). |

Everything on our side of that redirect *is* covered, by `OAUTH-01` to
`OAUTH-06`: the authorisation URL is well formed and leaks no secret, state
tokens are per-request and long enough to resist guessing, and the callback
rejects a missing state, an attacker-supplied state, and a valid state with no
code. All six pass.

AUTH-10/11/12 and SEC-01 were initially listed as manual-only. On inspection
they are automatable and are now covered:

- **AUTH-10/11/12** — when SMTP is unconfigured and the request is local,
  `forgot-password.php` prints the reset URL on the page
  (`erp_request_password_reset`'s `dev_reset_url`), so the whole reset flow
  runs without email. See `specs/password-reset.spec.ts`.
- **SEC-01** — the upload and the subsequent fetch of the stored file can both
  be driven directly. See `SEC-01` in `specs/security.spec.ts`.

---

## 9. Reproducing this run

```bash
# 1. dependencies
composer install

# 2. schema (see M1/M2/M3 — the documented steps alone do not work)
#    load database.sql with FOREIGN_KEY_CHECKS=0, then the migrations in
#    date order, then Admin/recycle-bin/setup_recycle_bin_all.php

# 3. test data
php backend/setup/seed_test_data.php

# 4. run
cd tests/e2e
npm install && npx playwright install chromium
npm test              # headless
npm run test:headed   # watch it in Chromium
npm run report        # HTML report
npm run evidence      # regenerate evidence/ screenshots
```

All seeded accounts use the password `Test@1234`. See
[README.md](README.md) for the full account list.

---

## 10. Recommended priority

1. **C1, C2** — remove or gate the delete and setup endpoints. Both are
   reachable by untrusted parties today.
2. **H1, H2** — apply `require_admin()` across the admin tree and add CSRF
   tokens to state-changing routes.
3. **H4, H5, H9** — three one-line path/column fixes that restore the client
   invoice view, the admin quotations module and statutory document retrieval.
4. **H3, H8** — fix the client status lookup and handle `blocked`.
5. **H11** — four path strings, fixing password reset on Linux. Do this before
   anyone relies on the reset flow in production.
6. **H10** — require Google's verified-email claim before linking by email,
   before OAuth is enabled in production.
7. **H6, H7** — restore the ten missing module pages and the three missing
   dashboards, or remove their navigation entries.
8. **M1–M3** — make the schema installable from the committed files and update
   the documentation to match.
9. **M7, L4** — small correctness fixes: honour `?force=1`, and make the reset
   response identical whether or not the account exists.

# SAMS end-to-end suite

Playwright/Chromium tests mapped one-to-one onto the IDs in *SAMS Test Cases*
(AUTH-*, ROLE-*, CLIENT-*, ADMIN-*, MGR-*, SEC-*, NOTIF-*, CONTACT-*), so a
failing test names the checklist row it belongs to.

**Results of the last run are in [TEST-REPORT.md](TEST-REPORT.md)** — 86 tests,
49 passed, 36 failed, 1 skipped, 23 confirmed defects. Supporting screenshots
are in `evidence/`, and the filled-in checklist is `SAMS_Test_Cases_COMPLETED.docx`.

## Prerequisites

- PHP 8.2+ with `pdo_mysql`, `mbstring`, `gd`
- MySQL or MariaDB reachable at `127.0.0.1:3307` as `root` with no password
  (this is hardcoded in `backend/config/config.php`)
- `composer install` run at the repository root
- Node 18+

## Setting up

```bash
# 1. schema: base file, then the migrations in date order
#    NOTE: Admin/recycle-bin/setup_recycle_bin_all.php must also run, or the
#    dashboard queries deleted_at columns that do not exist.

# 2. test accounts and records
php backend/setup/seed_test_data.php

# 3. node dependencies
cd tests/e2e && npm install && npx playwright install chromium
```

`seed_test_data.php` writes `seed-manifest.json` next to this README. The specs
read record ids from it, so they never hard-code primary keys.

## Running

```bash
cd tests/e2e
npm test              # headless
npm run test:headed   # watch it drive Chromium
npm run report        # open the HTML report
npm run evidence      # regenerate evidence/ screenshots
```

Every run re-seeds the database first (`support/global-setup.ts`). Several
cases mutate shared rows — CLIENT-11 blocks an account and restores it — so a
run interrupted part-way would otherwise leave state that makes the next run
fail for the wrong reason.

`SAMS_BASE_URL` overrides the target (default
`http://localhost:8080/SV%20Auto%20Truck%20Repair/frontend/`). `SAMS_PHP`
overrides the PHP binary used by the helper that flips client status mid-session.

## Seeded accounts

All use the password `Test@1234`.

| Account | Role / status |
| --- | --- |
| `qa.admin@test.local` | Admin |
| `qa.manager@test.local` | Manager |
| `qa.technician@test.local` | Technician |
| `qa.finance@test.local` | Finance User |
| `qa.hr@test.local` | HR User |
| `qa.cleaner@test.local` | Unrecognised role, for the ROLE-07 fallback case |
| `qa.client.approved@test.local` | Client, approved |
| `qa.client.other@test.local` | Client, approved — the second tenant for IDOR cases |
| `qa.client.pending@test.local` | Client, pending |
| `qa.client.blocked@test.local` | Client, blocked |

## What is not covered

Only the Google consent flow itself (AUTH-08/09) stays manual — it needs live
Google Cloud credentials and a sign-in through Google's own pages. See
[MANUAL-TESTS.md](MANUAL-TESTS.md). Everything on our side of that redirect is
covered by `specs/oauth.spec.ts` (OAUTH-01 to OAUTH-06), which all pass.

Password reset (AUTH-10/11/12) and the disguised-upload check (SEC-01) were
initially assumed to need SMTP and manual inspection. They do not, and both are
now automated: `forgot-password.php` prints the reset URL on the page when mail
is unconfigured on localhost, and the upload plus the fetch of the stored file
can both be driven directly.

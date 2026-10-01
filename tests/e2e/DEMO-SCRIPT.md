# SAMS — presentation demo script

Every step below was run end to end and reproduces. Expected output is stated
so you can tell immediately if something has drifted.

**Total runtime:** about 12 minutes for the full three acts, or 5 minutes for
Act 3 alone if you are short on time.

---

## Before you start

### 1. Bring the stack up

All commands run in **Git Bash**.

**First check whether it is already running** — starting a second copy fails
confusingly:

```bash
"C:/sams-stack/mariadb/bin/mariadb.exe" --host=127.0.0.1 --port=3307 --user=root -e "SELECT VERSION();" && curl -s -o /dev/null -w "app HTTP %{http_code}
" "http://localhost:8080/SV%20Auto%20Truck%20Repair/frontend/login.php"
```

If that prints a version and `HTTP 200`, the stack is up — skip to step 2.

Otherwise, two terminals, each left running:

```bash
"C:/sams-stack/mariadb/bin/mariadbd.exe" --defaults-file=C:/sams-stack/my.ini --console
```

```bash
cd /c/sams-stack/htdocs && "C:/sams-stack/php/php.exe" -c "C:/sams-stack/php/php.ini" -S localhost:8080 -t "C:/sams-stack/htdocs" "C:/sams-stack/router.php"
```

The trailing `router.php` matters: without it the login-page logo shows as a
broken image. That is an artefact of this portable test stack (the app is
served through a directory junction), not a defect in SAMS — a real XAMPP or
Hostinger install does not need it. The full suite gives identical results
with and without the router.

**Two messages you can ignore:**

- `InnoDB: The data file './ibdata1' must be writable` — MariaDB is already
  running and holding the files. Not a permissions fault. Run the check above
  and carry on with the instance you have.
- `forking is not supported on this platform` — `PHP_CLI_SERVER_WORKERS` is a
  Unix-only setting. PHP ignores it on Windows and starts single-threaded,
  which is fine here. The line above omits it.

### 2. Reset to a known state

```bash
cd "C:/Users/Tommy Kampolo/Documents/GitHub/SV-AUTO" && "C:/sams-stack/php/php.exe" -c "C:/sams-stack/php/php.ini" backend/setup/seed_test_data.php
```

### 3. Put three records in the recycle bin

Act 3 is far stronger when the client destroys something real rather than an
empty bin. Run this immediately before presenting — the demo consumes them.

```bash
"C:/sams-stack/mariadb/bin/mariadb.exe" --host=127.0.0.1 --port=3307 --user=root sams_db -e "DELETE FROM expenses WHERE description LIKE 'Workshop consumables%' OR description LIKE 'Parts return%' OR description LIKE 'Diagnostic software%'; INSERT INTO expenses (description, amount, deleted_at) VALUES ('Workshop consumables - March', 4500.00, NOW()), ('Parts return credit', 1250.00, NOW()), ('Diagnostic software licence', 8900.00, NOW());"
```

### 4. Have these open

- Browser at `http://localhost:8080/SV%20Auto%20Truck%20Repair/frontend/login.php`
- A second **private/incognito window** — you need a genuinely session-free
  browser for the Act 3 opener, and logging out is easy to get wrong on stage
- `SAMS_Test_Cases_COMPLETED.docx`, for the closing slide

**All accounts use `Test@1234`.**

---

## Act 1 — It works (3 min)

Establish that this is a real, working application. The findings land harder
when the audience has seen the product succeed first.

| Step | Do | They should see |
| --- | --- | --- |
| 1 | Log in as `qa.admin@test.local` | Admin dashboard: job cards, quotations, invoices, a job-status chart |
| 2 | Open Clients from the sidebar | A populated client list |
| 3 | Log out, log in as `qa.client.approved@test.local` | Client portal: 1 pending quotation, 1 unpaid invoice, N$15,000 |
| 4 | Log out, try `qa.client.blocked@test.local` | *"Your account has been suspended."* Denied correctly. |

**Say:** authentication is the strongest part of this system. All 11 auth tests
pass — no user enumeration, CSRF on the login form, a brute-force lockout,
single-use password reset tokens. Client data isolation holds too: no test
could read another client's quotation or invoice.

**Optional, 30 seconds, if you want a working-control moment:** on the login
page click *Forgot password*, enter `qa.hr@test.local`. A reset link appears
on the page because SMTP isn't configured locally. Enter `nobody@nowhere.test`
instead and you get *"If this email is registered…"* with no link — the system
refuses to confirm whether an account exists.

---

## Act 2 — It's broken (4 min)

| Step | Do | They should see |
| --- | --- | --- |
| 1 | Log in as `qa.technician@test.local` | **404.** They authenticate successfully and land on a page that does not exist. |
| 2 | Log in as admin, click **Inventory** in the sidebar | **404** — and point at the address bar: `/Admin/Inventory/Inventory/inventory.php`. The folder name is doubled. |
| 3 | Click **Employees**, **Reports**, **Expenses** | The same 404 each time |
| 4 | Open **Quotations** | A bare PHP fatal error, nothing else |
| 5 | Log in as `qa.client.approved@test.local`, open the invoice | A PDO stack trace **with your full filesystem paths**, shown to a customer |

**The numbers to quote:**

- **10 of 15 navigation links are dead.** Nine in Admin, one in Manager. Each is
  a ~190-byte stub redirecting to its own path relative to itself.
- **3 of 6 role dashboards don't exist.** Technician, finance and HR.
- Step 4 is one wrong column name: `quotations.php` asks for `created_at`,
  which the table doesn't have.
- Step 5 is one wrong column name: `view_invoice.php` asks for `issue_date`
  where the column is `issued_date`. Nine other files spell it correctly.

**Say:** two of these are single-word typos. They take minutes to fix and they
restore a whole module each.

---

## Act 3 — It's unsafe (5 min)

This is the part that matters. Build it in this order — each step raises the
stakes.

### Step 1 — No login at all

**In the incognito window**, paste:

```
http://localhost:8080/SV%20Auto%20Truck%20Repair/backend/migrations/run_all_migrations.php
```

**They see:** the migration runner executing, listing `ALTER TABLE` statements
against the live database. **No login. No session. Anyone on the internet.**

Two more that behave the same way:

```
http://localhost:8080/SV%20Auto%20Truck%20Repair/frontend/Admin/recycle-bin/setup_recycle_bin_all.php
```

**Say:** these are one-off setup scripts that shipped with the application.
They should not be on a deployed server at all.

### Step 2 — Any staff role is an admin

Log in as `qa.technician@test.local`. It 404s (Act 2, step 1). Now paste:

```
http://localhost:8080/SV%20Auto%20Truck%20Repair/frontend/Admin/dashboard.php
```

**They see:** the full admin dashboard. Then:

```
http://localhost:8080/SV%20Auto%20Truck%20Repair/frontend/Admin/recycle-bin/recycle_bin.php
```

**They see:** the recycle bin, including the three deleted expense records.

Repeat with `qa.hr@test.local` — identical. Then with `qa.manager@test.local`
— identical, even though the code *does* contain a rule that bounces managers
out of Admin URLs. That rule lives in `auth.php`, and the dashboard never
includes `auth.php`.

**The number:** of 145 admin PHP files, 16 include the auth guard and only 9
call `require_admin()`.

### Step 3 — A customer can destroy your records

Show the three records first, so the audience sees what is about to go.

```bash
"C:/sams-stack/mariadb/bin/mariadb.exe" --host=127.0.0.1 --port=3307 --user=root sams_db -e "SELECT id, description, amount FROM expenses WHERE deleted_at IS NOT NULL;"
```

Now log in as `qa.client.approved@test.local` — a customer account anyone can
create from the public registration form — and paste:

```
http://localhost:8080/SV%20Auto%20Truck%20Repair/frontend/Admin/recycle-bin/permanent_delete_all.php
```

**They see:** *"Recycle bin emptied! 3 expense(s) permanently deleted"*

Then run the same SQL again. **Zero rows. The records are gone.**

**Say:** the only check on that endpoint is whether *some* session exists. Not
which role. It's a GET request with no CSRF token, so it also fires from any
third-party page an administrator happens to visit. There is no undo.

### Step 4 — One account reading another's record

The sharpest finding, and the one least likely to be found by hand.

Log in as `john.doe@email.com` — a staff row that shipped in `database.sql`.

**They see:** the client portal.

Then explain, with the database open:

```bash
"C:/sams-stack/mariadb/bin/mariadb.exe" --host=127.0.0.1 --port=3307 --user=root sams_db -e "SELECT id, email FROM users WHERE id=6; SELECT id, email, status FROM clients WHERE id=6;"
```

`users.id 6` is `john.doe@email.com`. `clients.id 6` is
`qa.client.blocked@test.local` — **a different person, whose account is
blocked.**

The portal reads account status with `SELECT status FROM clients WHERE id = ?`
using the *user's* id against the *clients* table. Two independent
auto-increment sequences, treated as one. So this login reads a stranger's
status — and because the code only ever checks for `pending`, never `blocked`,
the dashboard is served regardless.

**Say:** this is also why no self-registered customer can ever use the portal.
Approve them and they are still locked out, because their status is being read
from the wrong row.

---

## Closing (2 min)

Open `SAMS_Test_Cases_COMPLETED.docx`.

> 89 checklist cases. 86 automated tests. 49 pass, 36 fail, 23 confirmed
> defects. Every failure reproduced by hand before it was recorded.

| Severity | Count | Nature |
| --- | --- | --- |
| Critical | 2 | Exploitable by an unauthenticated visitor or a customer |
| High | 11 | A core feature unusable, or a security control absent |
| Medium | 7 | Works but incorrectly |
| Low | 4 | Cosmetic, dead code, documentation drift |

**The encouraging half:** three of the eleven high-severity findings are a
single wrong identifier each — a column name, a column name, and a relative
path. Between them they restore the client invoice view, the admin quotations
module, and statutory document retrieval.

**The order of work:**

1. Remove or gate the two endpoints from Act 3, steps 1 and 3
2. Apply `require_admin()` across the admin tree, add CSRF tokens
3. Three one-line identifier fixes
4. Fix the client status lookup, and handle `blocked`

**If asked "did you run the tests live?"**

```bash
cd tests/e2e && npm run test:headed
```

That drives a visible Chromium window. It takes about 5 minutes for the full
suite, so prefer a single group:

```bash
cd tests/e2e && npx playwright test -g "AUTH-" --headed
```

11 tests, about 50 seconds, all green — a good note to end on.

---

## Things that will go wrong, and what to do

| Problem | Fix |
| --- | --- |
| Pages return "Connection failed" | MariaDB isn't running. Restart it from step 1. |
| Everything 404s | PHP server isn't running, or you're missing `%20` in the URL. |
| Act 3 step 3 says "0 expenses" | You already ran it. Re-run the seeding command in step 3 of Before you start. |
| A client login lands on pending-approval | Re-run `seed_test_data.php`. CLIENT-11 blocks an account and restores it; an interrupted run can leave it blocked. |
| MariaDB won't start, "ibdata1 must be writable" | An instance is already running and holding the data files. Not a fault — run the check in step 1 and use the instance you have. |
| `forking is not supported on this platform` | Harmless. `PHP_CLI_SERVER_WORKERS` is Unix-only; PHP ignores it and starts anyway. |
| Port 8080 already in use | A PHP server is already running. Check with the step 1 command before starting another. |

**Do not demo Google sign-in.** `backend/config/google.local.php` holds
placeholder values I used for testing the callback's CSRF defence. The consent
flow will fail at Google. If asked, the honest answer is that the consent flow
needs live credentials and was not run, but everything on our side of the
redirect is covered by six passing tests — and code review found that the
callback never checks Google's verified-email claim before linking an account
by email address.

**Don't claim the suite found everything.** Two defects in the tests themselves
turned up during this work: six cases were passing against 404 pages because
the error page echoes the requested path, and one case asserted nothing at all.
Both are fixed and documented in section 3.4 of the report. If someone asks how
you know the results are trustworthy, that is the answer worth giving — the
suite was audited, not just run.

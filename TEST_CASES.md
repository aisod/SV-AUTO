# SV Auto (SAMS) — Test Cases

No automated test suite currently exists in this project. This document is a manual QA checklist covering authentication and every user role/module. Each case lists: ID, precondition, steps, expected result.

Recommended next step for automation: `composer require --dev phpunit/phpunit` and add a `tests/` directory mirroring `backend/` and `frontend/` modules, starting with the auth/role-redirect logic and DB-layer functions in `backend/config/functions.php`.

## 1. Authentication (All Users)

| ID | Precondition | Steps | Expected Result |
|---|---|---|---|
| AUTH-01 | Valid staff account exists | Go to login page, enter correct email/username + password, submit | Redirected to role-specific dashboard (per role table in DOCUMENTATION.md) |
| AUTH-02 | Valid staff account exists | Enter correct username, wrong password | Login rejected, error shown, no session created |
| AUTH-03 | — | Enter non-existent username | Login rejected with generic error (no user enumeration) |
| AUTH-04 | Account exists but inactive/disabled | Attempt login | Login denied with appropriate message |
| AUTH-05 | Logged in as any role | Click logout | Session destroyed, redirected to login page, back-button does not restore session |
| AUTH-06 | Not logged in | Directly navigate to a protected URL (e.g. `Admin/dashboard.php`) | Redirected to login page, not shown protected content |
| AUTH-07 | Logged in as Manager | Directly navigate to an Admin-only URL | Access denied / redirected, not shown Admin content |
| AUTH-08 | Google OAuth configured | Click "Sign in with Google", complete consent | Account linked/created, redirected to correct role dashboard |
| AUTH-09 | Google OAuth | Sign in with Google account not matching any existing user | Appropriate handling (auto-create, or rejection message) — verify actual behavior matches intent |
| AUTH-10 | Valid account | Use "forgot password" flow with valid email | Reset email sent, link allows setting new password, old password stops working |
| AUTH-11 | — | Use "forgot password" flow with unregistered email | No account-existence leak; generic confirmation message |
| AUTH-12 | Reset link used once | Reuse the same password-reset link a second time | Link rejected as expired/invalid |
| AUTH-13 | — | Attempt SQL injection in login fields (e.g. `' OR '1'='1`) | Login fails safely, no error/stack trace leaked, no unauthorized access |
| AUTH-14 | — | Submit login form with empty fields | Client/server-side validation error, no request to DB with empty creds |

## 2. Role Redirect Logic (`getRoleRedirect`)

| ID | Role name in DB | Expected Redirect |
|---|---|---|
| ROLE-01 | `admin` / `Admin` / `Super Admin` | `Admin/dashboard.php` |
| ROLE-02 | `manager` / `Shop Manager` | `Manager/manager_dashboard.php` |
| ROLE-03 | `technician` / `tech` | `Technician/technician_dashboard.php` |
| ROLE-04 | `finance` / `Finance Officer` | `Admin/finance_dashboard.php` |
| ROLE-05 | `hr` / `HR Officer` | `Hr/hr_dashboard.php` |
| ROLE-06 | `client` (should not occur for staff table, verify) | `Client/dashboard.php` |
| ROLE-07 | Unrecognized/custom role name (e.g. `Cleaner`) | Falls back to `Admin/dashboard.php` — verify this fallback is intentional and not a privilege-escalation bug |

## 3. Client Portal

| ID | Precondition | Steps | Expected Result |
|---|---|---|---|
| CLIENT-01 | — | New client fills registration form | Account created with `status = pending` |
| CLIENT-02 | Client status = `pending` | Attempt login | Redirected to `pending-approval.php`, cannot view dashboard |
| CLIENT-03 | Client status = `approved` | Login | Redirected to `Client/dashboard.php` |
| CLIENT-04 | Client status = `blocked` | Attempt login | Login denied with blocked message |
| CLIENT-05 | Client approved, profile incomplete | Login | Redirected to `complete-profile.php` before dashboard access |
| CLIENT-06 | Client logged in | View own quotations list | Only quotations belonging to this client are shown |
| CLIENT-07 | Client logged in | Open `view_quotation.php?id=<other client's quotation id>` | Access denied — cannot view another client's quotation |
| CLIENT-08 | Client logged in | Open `view_invoice.php?id=<other client's invoice id>` | Access denied — cannot view another client's invoice |
| CLIENT-09 | Client logged in, has an invoice | Download invoice as PDF | PDF generated via dompdf with correct client/invoice data |
| CLIENT-10 | Client logged in | Update profile / settings | Changes persist and are reflected on next login |
| CLIENT-11 | Admin has changed client status to `blocked` while client has active session | Client performs any action | Session is invalidated / access is denied going forward |

## 4. Admin Role

| ID | Precondition | Steps | Expected Result |
|---|---|---|---|
| ADMIN-01 | Admin logged in | View dashboard | Summary stats/widgets load correctly |
| ADMIN-02 | Admin logged in | Create a new job card | Job card saved, appears in job card list with unique card number |
| ADMIN-03 | Job card exists | Edit job card details | Changes saved and reflected in job card view |
| ADMIN-04 | Job card exists | Soft-delete job card (move to recycle bin) | Card disappears from active list, appears in recycle bin |
| ADMIN-05 | Job card in recycle bin | Restore job card | Card reappears in active list; verify card number reuse logic does not collide with a newly created card |
| ADMIN-06 | Admin logged in | Create a quotation for a client | Quotation created, viewable by that client, correct totals/labor rates applied |
| ADMIN-07 | Quotation exists | Convert/generate invoice from quotation | Invoice created with matching line items and correct totals |
| ADMIN-08 | Invoice exists | Generate invoice PDF | PDF downloads with correct formatting, company details, and totals |
| ADMIN-09 | Admin logged in | Create/edit/delete a client record | Client changes persist; deleted client no longer appears in active client list |
| ADMIN-10 | Admin logged in | Approve a pending client | Client `status` becomes `approved`; client can now log in fully |
| ADMIN-11 | Admin logged in | Block an approved client | Client `status` becomes `blocked`; client login subsequently denied |
| ADMIN-12 | Admin logged in | Create/edit an employee record | Employee data saved correctly |
| ADMIN-13 | Admin logged in | Create a new role and assign permissions | Role appears in role list; user assigned this role gets correct redirect/access |
| ADMIN-14 | Admin logged in | Edit an existing role's permissions | Users with that role immediately reflect new permission set (or on next login, per design) |
| ADMIN-15 | Admin logged in | Add/edit inventory item | Stock quantity and details update correctly |
| ADMIN-16 | Inventory item exists with low stock | View inventory | Low-stock indicator/alert shown (if implemented) |
| ADMIN-17 | Admin logged in | Create a purchase order | PO saved with correct supplier/items/quantities |
| ADMIN-18 | Admin logged in | Record an expense | Expense saved; totals in reports reflect it |
| ADMIN-19 | Expense exists | Soft-delete expense | Expense marked deleted, excluded from active totals/reports |
| ADMIN-20 | Admin logged in | Upload a statutory document for a client/vehicle | File uploaded, stored under `uploads/statutory`, retrievable |
| ADMIN-21 | Admin logged in | View reports module | Correct aggregated data (revenue, jobs, expenses) for selected period |
| ADMIN-22 | Admin logged in | Configure labor rates (normal/after-hours/weekend/holiday) | New rates saved and applied to subsequently created quotations/invoices |
| ADMIN-23 | Admin logged in | View notifications | Relevant notifications (new client registration, job card updates, etc.) appear |
| ADMIN-24 | Admin logged in | Access finance dashboard | Financial summaries display correctly |
| ADMIN-25 | Admin logged in | Use recycle bin to permanently delete a record | Record removed from DB entirely, cannot be restored |
| ADMIN-26 | Public visitor submits contact form | Admin checks `backend/storage/` enquiries | Enquiry recorded as `.txt` (or DB, if migrated) and visible to admin |

## 5. Manager Role

| ID | Precondition | Steps | Expected Result |
|---|---|---|---|
| MGR-01 | Manager logged in | View manager dashboard | Correct summary of job cards/quotations/invoices relevant to manager scope |
| MGR-02 | Manager logged in | View job cards list | Sees job cards (verify scope — all shop cards vs. assigned only) |
| MGR-03 | Manager logged in | Create/edit a job card | Saved correctly, consistent with Admin job card behavior |
| MGR-04 | Manager logged in | View/create quotations | Same functional behavior as Admin quotation module (or reduced permissions — verify) |
| MGR-05 | Manager logged in | View/create invoices | Same as above |
| MGR-06 | Manager logged in | Attempt to access Admin-only pages (user roles, employees) directly by URL | Access denied |
| MGR-07 | Manager logged in | Perform "manager_operations" actions | Operation completes and reflects correctly in related modules |

## 6. Technician Role

| ID | Precondition | Steps | Expected Result |
|---|---|---|---|
| TECH-01 | Technician logged in | View technician dashboard | Sees only job cards assigned to them (verify scoping) |
| TECH-02 | Job card assigned to technician | Update job card progress/status | Progress recorded, visible to Admin/Manager and client (if applicable) |
| TECH-03 | Technician logged in | Attempt to access another technician's assigned job card directly by URL | Access denied or read-only, per intended design — verify |
| TECH-04 | Technician logged in | Attempt to access Admin/Manager-only pages | Access denied |

## 7. Finance Role

| ID | Precondition | Steps | Expected Result |
|---|---|---|---|
| FIN-01 | Finance user logged in | View finance dashboard | Correct financial summaries (invoices, expenses, revenue) |
| FIN-02 | Finance user logged in | View/generate invoices | Behaves consistently with Admin invoice module |
| FIN-03 | Finance user logged in | Record/view expenses | Behaves consistently with Admin expense module |
| FIN-04 | Finance user logged in | Attempt to access HR/Employee management pages | Access denied |

## 8. HR Role

| ID | Precondition | Steps | Expected Result |
|---|---|---|---|
| HR-01 | HR user logged in | View HR dashboard | Correct employee summary/pending requests shown |
| HR-02 | HR user logged in | View/manage employee records | CRUD operations behave correctly |
| HR-03 | Employee submits HR request (e.g. leave) | HR user reviews request | Request appears with correct status; approve/reject updates status |
| HR-04 | HR user logged in | Attempt to access Finance/Invoice pages | Access denied |

## 9. Cross-Cutting / Security

| ID | Steps | Expected Result |
|---|---|---|
| SEC-01 | Inspect file upload endpoints (avatars, statutory docs) with a disguised executable (e.g. `.php` renamed `.jpg`) | Upload rejected or file not executable when served |
| SEC-02 | Submit forms with XSS payload (e.g. `<script>alert(1)</script>`) in text fields (client name, job description) | Payload is escaped/sanitized on output, not executed |
| SEC-03 | Tamper with an `id` parameter in a URL (e.g. invoice/quotation/job card id) to access another tenant's record | Server-side authorization check blocks access regardless of client-side hiding |
| SEC-04 | Check session cookie attributes | `HttpOnly` set; `Secure`/`SameSite` set appropriately for production |
| SEC-05 | Attempt CSRF: submit a state-changing form (e.g. delete job card) from an external page without valid token | Request rejected if CSRF protection is implemented — flag as gap if not |
| SEC-06 | Trigger a PHP error (e.g. malformed input) in production config | No stack trace/DB credentials leaked to end user |
| SEC-07 | Check `db.local.php` / `google.local.php` are excluded from git (`.gitignore`) | Secrets are not committed to the repository |

## 10. Notifications & Contact Form

| ID | Steps | Expected Result |
|---|---|---|
| NOTIF-01 | Trigger an event that should notify a role (e.g. new client registration) | Notification created and visible to the correct role(s) |
| NOTIF-02 | Mark a notification as read | Notification state updates, does not reappear as unread |
| CONTACT-01 | Public visitor submits contact form with valid data | Submission recorded, confirmation shown to visitor |
| CONTACT-02 | Submit contact form with missing required fields | Validation error shown, submission not recorded |
| CONTACT-03 | Submit contact form rapidly multiple times (spam check) | Verify whether rate-limiting/captcha exists; flag as gap if not |

---

### Recommended Automated Test Coverage (if PHPUnit is introduced)

- Unit tests for `backend/config/functions.php` helper functions.
- Unit tests for `getRoleRedirect()` in `frontend/login.php` (extract into a testable function/class if needed).
- Unit tests for labor rate calculation logic.
- Integration tests for job card create/restore (recycle bin) card-number reuse logic, using a test database.
- Integration tests for client approval state machine (`pending` → `approved`/`blocked`) and its effect on login.

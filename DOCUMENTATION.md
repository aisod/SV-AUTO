# SV Auto — SAMS (Shop Automated Management System)

A PHP web application for **SV Auto Truck Repair** to manage job cards, quotations, invoices, clients, employees, inventory, purchase orders, and statutory documents for a truck repair shop.

## 1. Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP (procedural, no framework) |
| Database | MySQL (via PDO), database name `sams_db` |
| PDF generation | `dompdf/dompdf` (invoices, quotations) |
| Email | `phpmailer/phpmailer` |
| Auth | Native PHP sessions + `password_hash`/`password_verify`, plus Google OAuth 2.0 |
| Frontend | Server-rendered PHP, plain CSS/JS (no SPA framework, no Node build step) |
| Web server | Apache (`.htaccess` present); local dev via XAMPP |

## 2. Project Structure

```
/
├── backend/
│   ├── config/          # config.php, db.local.php, google.local.php, functions.php,
│   │                     notifications.php, contact_enquiries.php, user_drafts.php
│   ├── libraries/PHPMailer/
│   ├── migrations/      # SQL + one-off PHP migration scripts
│   ├── setup/           # admin/diagnostic scripts (verify_accounts.php, check_users.php)
│   └── storage/         # runtime files (contact form submissions)
├── frontend/
│   ├── index.php, login.php, contact_send.php
│   ├── Admin/           # Admin-only modules
│   ├── Manager/         # Manager modules
│   ├── Client/          # Client self-service portal
│   └── assets/, uploads/
├── composer.json
└── index.php            # root redirector -> frontend/index.php
```

## 3. Configuration & Setup

1. Install PHP dependencies:
   ```
   composer install
   ```
2. Copy and fill in local config files (not committed, contain secrets):
   - `backend/config/db.local.php` — database host/user/password/name
   - `backend/config/google.local.php` — `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`
3. Create the database `sams_db` and run the SQL files in `backend/migrations/` in order (by filename/date), or the corresponding `run_*.php` scripts.
4. Set environment constants in `backend/config/config.php`:
   - `APP_PRODUCTION_HOST` — production hostname (e.g. Hostinger domain)
   - `APP_BASE` — base path used for local vs production URL building
5. Serve the app via Apache/XAMPP with the project root as the document root, and browse to `index.php`.

## 4. User Roles

The system has two authentication tiers:

- **Internal staff** — stored in `users` table, joined to a `roles` table via `role_id`. Role name determines post-login redirect (`frontend/login.php:getRoleRedirect()`):
  | Role name contains | Redirect |
  |---|---|
  | `admin` (default fallback too) | `Admin/dashboard.php` |
  | `manager` | `Manager/manager_dashboard.php` |
  | `tech` | `Technician/technician_dashboard.php` |
  | `finance` | `Admin/finance_dashboard.php` |
  | `hr` | `Hr/hr_dashboard.php` |
  | `client` | `Client/dashboard.php` |

- **Clients (customers)** — stored separately in a `clients` table with their own password hash and a `status` field:
  - `pending` — awaiting admin approval, sent to `Client/pending-approval.php`
  - `approved` — full access to `Client/dashboard.php`
  - `blocked` — denied login

## 5. Core Modules / Features

- **Authentication** — unified login page for all staff roles + separate client login; Google OAuth sign-in (`google-auth.php`, `google-callback.php`); password reset/forgot-password.
- **Job Cards** — create/track vehicle repair jobs, progress history, recycle-bin restore with card-number reuse.
- **Quotations** — create and send quotations to clients; client-facing view (`view_quotation.php`).
- **Invoices** — generate invoices, PDF export (dompdf), client-facing view (`view_invoice.php`).
- **Clients** — client records, approval workflow (pending/approved/blocked).
- **Employees / HR** — employee records and HR requests.
- **Inventory & Purchase Orders** — stock tracking and purchase order management.
- **Expenses** — expense tracking with soft delete.
- **Statutory Documents** — compliance document uploads per client/vehicle.
- **User Roles & Permissions** — role CRUD (`user-role/create_role.php`, `edit_role.php`, `view_role.php`).
- **Labor Rates** — configurable normal/after-hours/weekend/holiday rates.
- **Reports** — admin reporting module.
- **Notifications** — in-app notification system.
- **Recycle Bin** — soft-delete/restore for job cards and other records.
- **Contact Form** — public enquiry form, stored as `.txt` files under `backend/storage/`.

## 6. Known Gaps

- No automated test suite exists (no PHPUnit, no `tests/` directory). See `TEST_CASES.md` for a manual QA checklist covering all roles and modules, and a recommendation for introducing PHPUnit.
- No formal migration framework — migrations are plain SQL/PHP scripts run manually in order.

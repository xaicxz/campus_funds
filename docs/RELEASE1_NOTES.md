# CampusFunds — Release 1 Starter Application

CampusFunds is an **educational XAMPP prototype** based on `CampusFunds_System_Blueprint.pdf`, pp. 2–12. It uses PHP, MySQL/MariaDB, HTML, CSS, and JavaScript. It implements real server-side access checks; it is not ready for production deployment or actual school money without security review and campus financial policy sign-off.


## Demo logins for EVERY user role (new in account update)

This update adds 30 distinct **local-development-only** test accounts: 2 SG/admin logins and 7 roles for each of SELECT, PSCAS, JPIA, and CRIM. The account list is in `docs/account_matrix.csv`. The demo account passwords do NOT have universal defaults: the installation script creates a secure and unique random password for each login and writes a **private one-time CSV**.

**Fresh database:** import the updated `database/schema.sql` in phpMyAdmin, then run the command below.

**Database from the previous ZIP:** first import `database/migrations/002_add_authorized_financial_user.sql` in phpMyAdmin (this extends the role list; it does not delete existing users or transactions), then run the command below.

```bat
cd C:\xampp\htdocs\campus_funds
C:\xampp\php\php.exe scripts\seed_demo_accounts.php
```

The script normally saves passwords at `C:\xampp\CampusFunds_Demo_Credentials.csv` (**outside** `htdocs`), with each account's email, role, and one-time password. Do not publish this file. If the CSV already exists, the script refuses to overwrite it; move the file to a secure folder first, or specify a separate path:

```bat
C:\xampp\php\php.exe scripts\seed_demo_accounts.php "C:\Users\YourName\Desktop\credentials.csv"
```

Log in via `http://localhost/campus_funds/public/index.php?page=login` with each email and corresponding password from the generated CSV. Open **My Account** to change the temporary password. Existing email accounts are intentionally skipped rather than having their password or permissions changed.

The **Authorized Financial User** role is a restricted placeholder: the blueprint defines fine-grained assignments, which have not yet been implemented; the account cannot read private financial records unless it receives an additional authorized role. A **Member** has a sign-in and limited dashboard only. Organization officers can submit fund requests; auditors have read-only access. SG Finance has consolidated summaries but not private transactions without explicit assignment.

**Do not execute demo seeding on a production deployment.** Change test passwords if any demo users are kept, and disable/delete demo users before working with genuine money.

## Setup on XAMPP (Windows)

1. Copy `campus_funds` into `C:\xampp\htdocs\campus_funds`.
2. Start **Apache** and **MySQL** in XAMPP.
3. Open `http://localhost/phpmyadmin`. Use **Import** to import `database/schema.sql`. This creates `campus_funds_db` and seeds SG + SELECT, PSCAS, JPIA, and CRIM, with zero opening balances.
4. If your database credentials differ from the normal XAMPP local defaults, edit `config/database.php` or set `CF_DB_HOST`, `CF_DB_NAME`, `CF_DB_USER`, `CF_DB_PASS` environment variables.
5. Open Windows Command Prompt, go to `C:\xampp\htdocs\campus_funds` and run:

   ```bat
   C:\xampp\php\php.exe scripts\install_admin.php "System Administrator" "admin@campus.test" "ChangeThisStrongPass123!"
   ```

   Use **your own email and strong password**, not those example credentials. Never publish them.
6. Visit `http://localhost/campus_funds/public/index.php` and sign in as the newly created admin.
7. Open **User Management**. Create separate users and organization roles:
   - SG Financial Administrator: `sg_finance` under **Student Government**.
   - Organization President: `president` under an organization.
   - Organization Treasurer: `treasurer` under an organization.
   - Organization Adviser: `adviser` under an organization.
   - Organization Officer: `officer` under an organization.
   - Auditor: `auditor` under an organization.
8. Test budgets: Treasurer creates a budget, President approves, Adviser approves. Once approved, the Treasurer can record cash income, and the Adviser can verify/post it.
9. Officers can submit fund requests under approved budgets. Treasurer → Adviser → President approvals are mandatory. A request can only be finally approved with adequate uncommitted cash and budget. The Treasurer can then record an expense against the approved budget/request and attach a PDF, JPG, or PNG receipt; the Adviser separately verifies/posts it.
10. Use **Reports** for dashboard numbers and **CSV** transaction exports. Receipts are stored in `storage/uploads` and can only be downloaded through permission-checked PHP.

## Role boundary

A System Administrator (SG-scoped `system_admin`) manages accounts; it **cannot automatically view or spend organization funds**. SG Financial Administrators can see consolidated totals, but private transaction details need an assigned organization role. Every organization record is protected by an organization ID and validated in PHP.

## What is implemented

- Login with `password_hash`, `password_verify`, secure-ish sessions, and CSRF form tokens.
- Organization-specific access control with admin-provisioned roles and no public sign-up.
- SG consolidated financial summary and individual organization dashboards.
- Annual/event/project budgets with line items, President → Adviser approval, optional SG stage.
- Fund requests with Treasurer → Adviser → President approval, self-approval prevention, reason on rejection, unique references, and cash/budget commitment checks.
- Income and expenses pending independent Adviser verification; posted transactions cannot be edited/deleted through normal routes.
- Cash = opening + posted income − posted expenses; available = cash − approved unpaid commitments.
- Receipt uploads outside `public`, MIME/size checks, randomized storage names, authorized downloads.
- Transaction history, audit events, basic budget utilization reporting, CSV download.
- Foundation tables for later modules (liquidation, notifications, calendar).

## Not yet implemented

Formal liquidation workflow and resolution of unused advances, reimbursements, email notification, deadline/calendar screens, PDF/Excel report generation, detailed changes/reversals, bank reconciliation, academic year rollover UI, configurable approval policy UI, full double-entry accounting, and production audit/security tooling. The source blueprint places most of those in later releases.

The source blueprint contains other example `/api/...` REST endpoints. **This starter uses straightforward `index.php?page=...` and `index.php?action=...` endpoints** rather than claiming those future APIs already exist.

## Important operational cautions

- `storage/` must remain outside the web document root in production (here it is a sibling of `public/`); when using XAMPP's default `htdocs` folder layout, the included Apache `.htaccess` blocks direct access if overrides are enabled. For any deployed server, explicitly deny URL access to `/storage`, `/app`, `/config`, `/database`, and `/scripts`, or make `public/` the actual document root.
- Create database and document backups; test restoration.
- Enable HTTPS, login rate limiting, security headers, proper role expiry, monitoring, and session cookie policies before real deployment.
- Roles and financial policy should be confirmed with the campus. Demo opening balances are **zero**, not fabricated financial data.
- Financial arithmetic is stored in SQL as `DECIMAL(14,2)` and checked in PHP as integer cent values for the basic workflow. Extensive financial reconciliation and concurrency tests are still required before deployment.

## File map

A standalone static UI example is available at `http://localhost/campus_funds/public/ui-preview.html`; it displays no real financial data and is **not** the authentication route.

| File / folder | Responsibility |
| --- | --- |
| `public/index.php` | Main page/action router and HTML error pages |
| `public/ui-preview.html` | Static, data-free responsive dashboard HTML mockup |
| `public/assets/css/style.css` | Responsive UI layout and visual style |
| `public/assets/js/app.js` | Menu, conditional forms, rejection-reason interactions |
| `app/bootstrap.php` | PDO, sessions, permission checks, financial summaries |
| `app/actions.php` | Mutating actions, approval routes, receipt handling and export |
| `app/views.php` | HTML login, dashboards, forms, tables and user management |
| `config/database.php` | Database connection settings |
| `database/schema.sql` | Schema, constraints, organization seed data |
| `scripts/install_admin.php` | CLI-only first system admin provisioning |
| `storage/uploads/` | Protected receipt storage, not a public asset directory |

## Suggested first test (no fabricated figures)

1. Create Treasurer, President and Adviser accounts for SELECT LSL plus one Officer account.
2. Log in as Treasurer and propose a budget with `Events|5000.00`.
3. Log in as President and approve; then Adviser and approve.
4. As Treasurer, record income of `5000.00`, then as Adviser verify/post it.
5. As Officer, request `2000.00` for a planned activity; complete the three approvals with distinct users.
6. Observe that actual cash stays 5000.00 while available cash becomes 3000.00.
7. Treasurer records a 1000.00 expense against that request with a PDF receipt; Adviser posts it.
8. Observe actual cash = 4000.00 and outstanding commitment = 1000.00; available cash remains 3000.00.

These numbers are **test inputs**, not actual campus financial records.

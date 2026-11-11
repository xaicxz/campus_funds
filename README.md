# LSL Financial Portal — Release 2 Finalized UI

**Build:** Release 2.1 — Refined interface, Financial Accountability, Liquidation & Reporting  
**Technology:** PHP 8.1+ / MySQL 8 or MariaDB 10.4+ / HTML / CSS / JavaScript / XAMPP  
**Status:** Working source-code implementation for a **local development/test environment**, **not production certified**. PHP syntax and database-independent smoke tests have been checked; a connected MySQL/XAMPP end-to-end run, 26 acceptance tests, policy sign-off and backup restoration **must still be completed**.

LSL Financial Portal is the updated display name of CampusFunds. Release 2 extends the earlier release **without intentionally replacing its 30 demo user accounts, financial records or officer permissions**. Do **not** import the baseline `database/schema.sql` over an existing live database as a replacement for a migration. Back up first.


## Release 2.1 UI finalization (22 October 2026)

- Rebranded all application-facing titles and headers to **LSL Financial Portal**; retained the existing `campus_funds` installation directory and `campus_funds_db` database name for compatibility. Existing `@campusfunds.test` demo login emails and credential CSV paths were **not changed**; do not reseed when upgrading.
- Applied the five reference palette hex values **#001B48, #02457A, #018ABE, #97CADB, #D6E8EE** across login, navigation, cards, charts, forms and data tables.
- Added a responsive collapsible mobile sidebar with overlay and keyboard Escape handling, active navigation labels, unread notification badge, account identity and role label, show/hide password button, branded favicon, accessible focus states, and print styles.
- Added a **progressively enhanced liquidation item editor**: dated line items, budget line selection restricted to the chosen request budget, itemized total and individual receipt inputs. Without JavaScript, the original line-format submission form remains available. Server-side financial validations and approval controls remain unchanged.
- Added two non-sensitive **static design previews**: `public/ui-preview.html` and `public/login-preview.html`. These do not connect to the database and do not display fictitious balances. Open directly in a browser after extracting the ZIP, or through XAMPP, to review the styling.
- Source filenames for new CSV/report downloads use the `lsl-financial-portal-` prefix. Historical PHP route names, accounts, database schemas and migrations remain unchanged to prevent destructive upgrades.

**Important verification status:** PHP lint and database-independent smoke tests passed. Real-database approval, liquidation, export, role-isolation, and backup/restore tests have NOT been run in this environment. This is a refined development build, not a production-certified financial application.

## Install or upgrade (Windows XAMPP)

### Upgrading an existing Release 1 installation (recommended)

1. Back up `campus_funds_db` using phpMyAdmin **Export -> SQL**, and independently back up `campus_funds/storage/uploads/`. Save a copy of the current Release 1 project and record current cash balances.
2. Stop maintenance writes. Replace the application files under `C:\xampp\htdocs\campus_funds` with the matching contents from this Release 2 ZIP. Keep the existing **storage/uploads** directory and your database connection settings.
3. Open phpMyAdmin, select `campus_funds_db` and import **`database/migrations/003_release2.sql` exactly once**. If you originally installed from a pre-account-update schema, you must first have applied `002_add_authorized_financial_user.sql`.
4. Open `http://localhost/campus_funds/public/index.php?page=login` and sign in using the **same Release 1 accounts and passwords**. The migration does **not** reset passwords or reseed accounts.
5. In **User Management**, the System Administrator can set each organization's liquidation deadline (1-180 days; default 14 days). This is a demonstration default pending campus policy approval.
6. In Command Prompt run the database-independent smoke checks:

   ```bat
   cd C:\xampp\htdocs\campus_funds
   C:\xampp\php\php.exe tests\smoke.php
   ```

7. Schedule once a day using Windows Task Scheduler, **Run whether user is logged on or not**, program `C:\xampp\php\php.exe` and arguments `C:\xampp\htdocs\campus_funds\scripts\cron_deadlines.php`. Set Start in `C:\xampp\htdocs\campus_funds`. The job sends in-app alerts, not email. Run it once manually to verify.
8. **Optional for PDF/Excel exports:** Install [Composer](https://getcomposer.org/) and run `composer install` (or `composer update` when generating the initial lockfile). Enable XAMPP extensions required by these libraries (typically zip, mbstring, gd, intl as indicated by Composer). If dependencies are absent, CSV works and PDF/XLSX show a clear installation error instead of invalid downloads.
9. Complete the real-database scenarios in `tests/RELEASE2_ACCEPTANCE.md` **before using actual money**.

### Fresh test installation

1. Copy the `campus_funds` folder into `C:\xampp\htdocs\`.
2. Import `database/schema.sql` in phpMyAdmin, **then import `database/migrations/003_release2.sql` exactly once**. The account role enum in the included `schema.sql` already contains the account update; **do not run migration 002 on a fresh import**.
3. Run `C:\xampp\php\php.exe scripts\seed_demo_accounts.php` to create distinct passwords for 30 demo users. The one-time credentials CSV is saved **outside htdocs** in `C:\xampp\CampusFunds_Demo_Credentials.csv`; never share or upload it publicly. Existing user passwords are not overwritten by rerunning the seeder.
4. Open `http://localhost/campus_funds/public/index.php?page=login` and log in with credentials from the **private credentials CSV**, then change demo passwords under **My Account**.

## New pages and actions

| Browser page | Main purpose | Access |
| --- | --- | --- |
| `?page=liquidations&org=2` | Pending advances/returns, itemized liquidation, revisions, independent review and final closure | Organization Treasurer/Adviser/President/Auditor; owner submits own case |
| `?page=calendar&org=2` | Organization-scoped deadlines and manual milestones | Finance roles, request owners for their own liquidation deadlines |
| `?page=notifications` | User-only alert inbox | Any signed-in user |
| `?page=reports&org=2` | Income, expense, cash, budget, request, liquidation and audit reports; CSV/PDF/XLSX | Organization finance readers |
| `?page=reports&scope=sg` | SG consolidated organization-only report | SG Financial Administrator |
| `?page=audit&org=2` | Searchable timestamped audit history | Organization finance readers |
| `?page=liquidation_evidence&org=2&id=...` | Authorized private evidence download | Owning requester / authorized finance reader |

**New action routes** include `record_advance`, `record_return`, `liquidation_submit`, `liquidation_decide`, `notification_read`, `calendar_save`, `calendar_complete`, and `set_liquidation_policy` on the existing `index.php?action=...` pattern. Every mutation is POST + CSRF and checks organization ownership and role permissions.

## Financial rules (Release 2)

- **Actual cash** = opening balances + posted earned income + posted advance returns - paid direct expenses - posted advance disbursements.
- **Unpaid approved commitments** = per request `MAX(0, approved - posted direct expenses - posted advances - authorized released remainder)`.
- **Available cash** = actual cash - unpaid approved commitments.
- **Recognized expenses** = posted direct expenses + amount of the latest independently verified liquidation submission. An advance is a cash movement, not a second expense.
- **Budget remaining** = approved allocations - recognized expenses.
- **Uncommitted budget** = approved allocations - recognized expenses - approved request reservations (remaining approved amount not yet recognized or explicitly released).
- After the final President closure, any **unspent approved remainder** is formally released; original approvals and disbursements remain in the audit trail.

**Illustration (not school financial data):** posted income PHP 20,000 -> request approved PHP 10,000 (cash 20,000, available 10,000) -> PHP 8,000 advance posted (cash 12,000, unpaid commitment 2,000) -> PHP 6,500 itemized spending verified (cash stays 12,000) -> PHP 1,500 returned (cash 13,500) -> verified liquidation closed and PHP 2,000 unused approval released (available 13,500, recognized expense 6,500). 

### End-to-end review route

1. Treasurer posts **pending** disbursement with valid protected evidence, unique reference and chosen organization account.
2. A different Adviser verifies the transaction. **Only posting** reduces cash and creates the liquidation case plus deadline. System rejects insufficient account cash, over-advance and reused event keys.
3. Request owner submits itemized Date|Payee|Description|Budget line ID|Amount data and **one PDF/JPG/PNG proof per item**. Each resubmission is kept as a new immutable version.
4. Treasurer approves or returns/rejects with a reason. Adviser independently verifies **advance total = submitted spending + posted returns**, no pending cash movements and no budget overspend.
5. President completes the final **closed** decision; the system frees unused, unpaid commitment. The original request remains with its approvals, transactions and decision history.

**Separation of duties:** users cannot review their own request or submitted liquidation. One user with multiple roles cannot sign two stages of the same submission. Administrator-only roles have no spending rights. SG sees organization aggregates only.

## File map (important Release 2 files)

| File | Description |
| --- | --- |
| `database/migrations/003_release2.sql` | Add event types, liquidation revisions, proof records, reviews, notification keys and calendar owners |
| `app/release2.php` | Cash, budget, advance, liquidation services; scoped actions and receipt downloads |
| `app/report_service.php` | Shared report datasets for screen, CSV, PDF and XLSX |
| `app/release2_views.php` | New responsive finance pages and charts |
| `app/actions.php` | Integrated Release 1 posting and approvals with Release 2 checks |
| `app/bootstrap.php` | Shared authentication, scope and balance wrappers |
| `scripts/cron_deadlines.php` | CLI-only daily deadline scan (Manila time); duplicate-safe event IDs |
| `tests/smoke.php` | Database-independent PHP checks |
| `tests/RELEASE2_ACCEPTANCE.md` | Twenty-six scenarios to verify with a real MySQL/XAMPP installation |
| `composer.json` | Optional PDF and Excel libraries |

## Policy limitations / production blockers

- The default liquidation reviewer order is Treasurer -> Adviser -> President and default deadline is 14 calendar days **for testing only**. Campus policy must confirm approval owners, thresholds and sign-off steps.
- No automatic refunds or banking integration, receipt OCR, email alerts, bank reconciliation, payment gateway, digital signatures, arbitrary approval tree, or double-entry accounting. Those are later releases.
- No silent edit/delete UI exists for posted records; separate authorized reversal/adjustment workflow and formal reconciliation must be implemented and reviewed before production.
- PDF/XLSX require Composer vendor libraries. Core CSV is immediately available. Spreadsheet formula injection is neutralized in CSV and exported XLSX strings are written explicitly as text.
- Existing `liquidations` with Release 1 `complete` status are remapped to `closed`. Old transactions are **not** automatically classified as advances. Review ambiguous historical expenses **manually**, never silently reclassify them.
- The app needs full MySQL-backed integration tests, concurrent-posting tests, upload/download permission tests, backups and restore drills, rate limits, HTTPS, and campus/data-privacy approval before handling live financial records.
- `storage/` and code directories must be denied direct HTTP access. Configure the webserver document root to `public/` for production or explicitly deny access to sibling directories.

## Troubleshooting

- **Unknown column / enum error**: make sure migration `003_release2.sql` ran once after backup. Do not repeatedly import partial migrations; restore and diagnose first.
- **No database connection**: start MySQL in XAMPP, check `config/database.php` and enable `pdo_mysql`.
- **Cannot download PDF/Excel**: from the project folder run Composer and enable the required PHP extensions. CSV has no external library requirement.
- **Password not visible**: demo passwords only appear in the private credentials CSV made by the seeding script. Password hashes in MySQL are not reversible.
- **Uploads fail**: check PHP `file_uploads`, `upload_max_filesize`, `post_max_size`, and private folder write permissions.
- **No reminders**: run `cron_deadlines.php` in the command line (do not open it in a web browser) and check scheduled task logs.


## LSL tagline update

The official display tagline appears on the sign-in introduction, organization dashboard headings, and sidebar branding, using the exact supplied text:

- Creating Lifes Champion
- Better LSL: learn better. lead better.

This is a presentation-only update; the database, login credentials, approval rules, and existing financial workflows are unchanged.

ps:
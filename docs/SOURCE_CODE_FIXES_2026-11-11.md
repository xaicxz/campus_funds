# CampusFunds source-code corrective patch — 11 November 2026

## Scope and compatibility

This patch modifies the uploaded `campus_funds.zip` application in place inside the delivery ZIP, preserving the current PHP front-controller architecture, existing roles, and **Release 2 schema**. It targets concrete issues found while comparing the PHP source, the System Blueprint v1.0, Release 2 Blueprint v2.0 and the uploaded `campus_funds_db.sql`. The existing uploaded SQL dump already contains the Release 2 tables and columns used by these fixes. **No database schema migration is required for this patch.** Do not reimport the original SQL dump over a working database.

The output archive intentionally omits the original `.git` directory. A unified diff is also provided separately for code review.

## Changes

| Area | Before | Corrected behavior |
| --- | --- | --- |
| Budget commitment reservations | Returned unused advances remained counted as reserved budget even after final closure | The reservation function subtracts posted advance returns as well as recognized spending and formally released remainders; never drops below zero. |
| Financial account cash | Direct expenses could post against an underfunded selected account if another organization account had sufficient cash | Every direct expense must have sufficient *selected-account* cash at posting time, in addition to the existing organization and budget checks. |
| Closed financial requests | A pending direct expense could post after its fund request had been closed | Both recording and posting link checks require the request to remain open and approved. |
| Multi-role approval conflicts | One user with multiple roles could sign successive budget/request stages | Budget and fund-request approval stages now reject an actor who has already reviewed the same object. UI avoids offering the impossible stage. |
| Direct-pay request closure | Unspent approved commitments for paid-directly or cancelled requests could remain reserved forever | Added a `close_direct_request` President action with mandatory reason, per-org lock, no-pending-events rule, no-advance rule, immutable audit and notification. Closing releases only the unused remainder; it does not edit posted spending. |
| Monetary arithmetic | Floating-point conversion was used for MySQL `DECIMAL`, and negative amounts formatted incorrectly | Decimal strings now convert into signed integer cents without floats and format negative amounts correctly. |
| Deactivated accounts | A session could persist after a user was disabled | Each authenticated request verifies active user status against the database; disabled users are returned to login. |
| Read privacy | Officers with no finance role could view unapproved budget proposals | Their budget view is limited to approved proposals and allocations. |
| Transaction attachment cleanup | Failed transaction after upload could leave orphaned private receipt | Newly uploaded attachment is cleaned up on a subsequent transaction rollback. |
| Finance form permissions | A former officer with a different active organization role could submit a liquidation revision | Only an active organization officer may submit their own liquidation. |
| Admin configuration | Setting liquidation days silently failed without an existing settings row | Configuration now uses upsert. |
| Calendar actions | Completing a nonexistent/already completed deadline could still write a misleading success audit | Action now verifies one row changed before emitting a completion audit. |
| Alert usability | Notifications showed only plain text | Where defined, an allowlisted link opens the appropriate record area; target routes still check server permissions. |
| Role management | Creating users assumed only organization IDs 2–5 existed | Organization selection is validated by active SG/participating-organization classification rather than ID range. |
| Reports | Budget snapshot was presented under selected date filters without explaining the difference | Budget reports explicitly label their values as a current snapshot not filtered by date. |
| Private evidence | Transaction receipts omitted no-store cache header | Transaction downloads now explicitly disable caching. |
| Auditability | Request insert, audit, and notification were not atomic | Fund request creation is transactional. |

## New action: close a direct-pay request

The request must be `approved`, open, owned by the current organization, and not owned by the acting President. No liquidation case, advance event or pending financial transaction may exist. A reason is required. Any *unused* approved amount is moved into `commitment_released`, the request is marked `closed_at`, and an audit event plus requester notification are saved in a single transaction. Requests with cash advances **cannot** use this action; they must use the normal liquidation approval and closure workflow. Posted direct expenses remain unchanged.

## Tests completed here

- `php -l` passes for all application PHP files.
- `php tests/smoke.php` passes.
- `php tests/regression_finance.php` passes; it includes high-precision and signed money values and a full reservation sequence matching the blueprint's ₱10,000 / ₱8,000 / ₱6,500 / ₱1,500 / ₱2,000 example.

**Important limitation:** This workspace does not have `pdo_mysql` or a MariaDB server, so actual database-integrated routes, SQL assertions, role-guard behavior, concurrent posts, and real receipt uploads/downloads could **not** be executed. This is a code correction and static/pure-unit validation, not a production deployment certificate. Run the acceptance checklist in XAMPP with a backed-up test database before going live.

## Remaining production work (not silently claimed complete)

- Blueprint-specified **authorized correction/adjustment/reversal workflow** for financial entries needs an agreed accounting policy and a dedicated schema/audit design; this patch does not invent transactions that could misstate campus funds.
- Real MySQL integration, concurrent posting tests, upload/download authorization testing, backups/restoration and audit retention review remain mandatory.
- Login rate limiting, HTTPS/server deployment controls, further privacy/compliance review, and campus confirmation of approval and deadline policies remain deployment gates.
- Period-accurate historical budget snapshots / full academic-year report designs require an agreed reporting model; existing budget and SG summaries are clearly labeled as current/all-time snapshots.
- PDF and Excel report generation still need the optional Composer dependencies.

## Deployment steps

1. Back up **both** the current database and `storage/uploads/` before updating the application.
2. Replace the PHP application files using this patched project, or review/apply the accompanying unified diff.
3. Keep existing `config/database.php` secrets and `storage/uploads` from your live server as applicable; this archive contains only the original development defaults and an empty uploads folder.
4. Do **not** rerun migration `003_release2.sql` or import the original SQL dump if the live database already has the Release 2 tables (`liquidation_submissions`, `liquidation_items`, `liquidation_decisions`).
5. Run `php tests/smoke.php` and `php tests/regression_finance.php` with XAMPP PHP.
6. Test the extended acceptance scenarios in `tests/RELEASE2_ACCEPTANCE.md` against disposable test funds, using separate users for each review role.
7. Only then release into a controlled environment with campus policy authorization and backup/restore procedures.

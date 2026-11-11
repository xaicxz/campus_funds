# CampusFunds Release 2 - MySQL/XAMPP acceptance checklist

This is a **manual execution plan**, **not** a claim that all checks have passed. Run as separate seeded accounts across SELECT, PSCAS, JPIA and CRIM after backup. Use **test funds only**. Preserve evidence screenshots and compare actual database totals.

| ID | Scenario to perform | Expected outcome |
|---|---|---|
| R2-01 | SELECT financial user edits URL to PSCAS liquidation, calendar, evidence or report ID | Server responds 403/404; no private data |
| R2-02 | Log in only as SG system administrator; attempt to post a disbursement or review liquidation | Forbidden |
| R2-03 | SG Finance opens reports scope=sg | Exactly four separate organization figures, no private receipt rows |
| R2-04 | Approve request but do not disburse | Actual cash unchanged; unpaid commitment rises |
| R2-05 | Submit same disbursement event reference twice and double-click verify | At most one posted cash movement |
| R2-06 | Post PHP 8,000 advance on approved PHP 10,000 request | Cash decreases PHP 8,000 once; recognized expense unchanged |
| R2-07 | Verify PHP 6,500 itemized receipt submission | Budget spending +PHP 6,500, cash unchanged |
| R2-08 | Post PHP 1,500 advance return | Cash +PHP 1,500; income reporting unchanged |
| R2-09 | Final President closure with PHP 2,000 unpaid remainder | Commitment released; original request and decisions remain |
| R2-10 | Submit case with empty evidence / invalid budget line | Rejected; no partially persisted submitted case |
| R2-11 | Try to verify unbalanced case (released != spent + return) | Blocked with a clear validation message |
| R2-12 | Assign two roles to one user; attempt self-review or dual-stage review | Rejected |
| R2-13 | Adviser returns case for revision; requester resubmits | Both submission versions and decisions retained |
| R2-14 | Let case due date expire | OVERDUE shown independently of stored status |
| R2-15 | Compare screen/CSV/PDF/XLSX for same period and organization | Matching row count and totals |
| R2-16 | SG Finance tries to export an organization's private income ledger | Forbidden unless separately assigned finance reader role |
| R2-17 | Export PDF/XLSX after Composer install | Includes report title, basis, org and selected period |
| R2-18 | Enter a text description beginning =, +, - or @ then export | Spreadsheet does not execute formulas |
| R2-19 | Re-run daily job twice on same day | At most one notification per recipient and key |
| R2-20 | Verify deadline handling on boundary around midnight Philippines time | Manila local dates respected |
| R2-21 | Auditor opens audit trail then attempts any POST financial update | Read allowed, modification forbidden |
| R2-22 | Guess private receipt file URL and evidence document ID as an unrelated user | No document bytes returned |
| R2-23 | Try deleting or silently editing posted advance/expense | No route exists to perform silent modification |
| R2-24 | Backup database and `storage/uploads`, restore on test instance | Accounts, review history, evidence and balances match |
| R2-25 | Simultaneous postings for same account / same request | No negative cash, double posting, or overapproved amount |
| R2-26 | Migrate populated Release 1 test database | User passwords, posted cash balances and provenance preserved |

**Finance test sequence:** Start with posted income PHP 20,000. Approve a PHP 10,000 request. Post PHP 8,000 advance, submit PHP 6,500 itemized spending, post PHP 1,500 return, have Treasurer and Adviser verify, then President close. Expected final cash PHP 13,500; recognized spending PHP 6,500; unpaid commitment PHP 0. Check permissions by switching user account after every stage.

**Go/no-go:** No live-money rollout until all 26 tests (particularly financial concurrency and cross-organization access), backup restore, written campus finance policy and Philippine data-privacy review are completed and recorded.

## Corrective patch regression scenarios (11 November 2026)

| ID | Scenario | Expected result |
|---|---|---|
| FIX-01 | Use ₱10,000 approved, ₱8,000 advanced, ₱6,500 verified, ₱1,500 returned and ₱2,000 released at closure; run budget report | Uncommitted reservation for that request is **₱0**, not ₱1,500; recognized spending is ₱6,500. |
| FIX-02 | Give organization Account A ₱0 and Account B ₱2,000; try posting a ₱500 direct expense on A | Posting rejected even though total organization cash is adequate. |
| FIX-03 | Have one user hold Treasurer and Adviser, and attempt both review stages of the same request; repeat for President and Adviser budget stages | Second review rejected and no extra approval record created. |
| FIX-04 | President closes a partially paid direct-pay request with a reason after all pending transactions are resolved | Remaining commitment released; posted expenses remain; `closed_at`, audit entry, notification stored. |
| FIX-05 | Try the direct-pay closure action on an advance-funded request or one with a pending transaction | Action rejected; no commitment or status changed. |
| FIX-06 | Create an expense against an open request, close that request, then attempt to post the expense | Closure should be blocked until pending entry resolves; posting against a closed request is independently blocked. |
| FIX-07 | Deactivate a logged-in user in the database, then refresh a protected page or POST a notification action | User is returned to login and the protected action is not executed. |
| FIX-08 | Officer with no finance role visits budgets page | Only approved budgets/allocations appear, not pending or rejected proposals. |
| FIX-09 | User with only a Member role attempts a POST `liquidation_submit` for a request they originally created when an Officer | POST forbidden. |
| FIX-10 | Post a negative cash position using preexisting data and generate a report | Negative values render as e.g. `-1.50` without an extra minus in the cents field. |
| FIX-11 | Re-submit a completed deadline using a stale form | Rejected; no duplicate completion audit event. |

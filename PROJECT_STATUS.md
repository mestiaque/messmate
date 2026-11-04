# Project Status

Last updated: 2026-11-03

## Phase checklist

| Phase | Scope | Status |
|---|---|---|
| 1 | Foundation, auth, tenants, messes, roles, isolation | Complete on a throwaway MySQL 8.4 instance: migrate:fresh --seed OK, 35/35 tests, UI smoke-tested. Re-run on the real `messmate` DB pending |
| 2 | Members, accounting periods, meals | Complete on the private test instance: 72/72 tests, browser-tested. Re-run on the real DB pending |
| 3 | Bazar, expenses, deposits, ledger | Complete on the private test instance: 98/98 tests, browser-tested. Re-run on the real DB pending |
| 4 | Calculation engine, settlement | Complete on the private test instance: 129/129 tests, browser-tested. Re-run on the real DB pending |
| 5 | Dashboards, reports, member portal | Complete on the private test instance: 140/140 tests, browser-tested. Excel/PDF via CSV + print (see below) |
| 6 | Subscriptions, platform billing | Not started |
| 7 | Security/performance audit, release | Not started |

## Phase 1 — delivered

- Laravel 13 project, MySQL config (`messmate`, tests on `messmate_test`).
- Auth: register (email or phone), login (email or phone, rate-limited), logout, profile & password change,
  suspended users blocked.
- Tenants: create, owner membership, settings, platform suspend/activate.
- Messes: create (multiple per tenant), edit, unique slug per tenant, creator optionally joins as Mess Administrator.
- Membership: add a registered user to a mess with roles; one active membership per user per mess
  (enforced in service and by a unique index on a generated column); sequential member codes.
- Roles & permissions: tables, permission catalogue (`App\Support\Tenancy\Permissions`), 5 system mess roles,
  per-mess role assignment, policy-based checks.
- Tenant/mess context middleware, scoped bindings, tenant-scoped cache keys, queue context restoration.
- Switching: home page lists every accessible organization and mess; user menu links back to it.
- Dashboards: platform, tenant, mess (real counts only).
- Audit log for tenant/mess/member/role changes.
- Factories, seeders (roles/permissions, platform admin, demo data), feature tests.

## Phase 2 — delivered

- Members: add without account, edit, invite by email/phone (hashed single-use token, 7-day expiry, must match the
  recipient), link an account later, active / inactive / suspended / left with an effective left date, member page
  with per-period meal statement.
- Accounting periods: calendar month or custom range, overlap check under a row lock + DB CHECK on dates,
  lifecycle draft → open → reviewing → finalized → locked → archived, finalize blocked while items are pending,
  reopen of finalized/locked periods with `periods.reopen`, a reason and an audit record.
- Meal types: configurable name/code/factor/order/default/active; Breakfast 0.5, Lunch 1, Dinner 1 created per new mess.
- Meal entries: individual (quantity × snapshotted factor) and daily total (direct units, or raw count × factor with
  the derivation stored); one entry per member per date; no mode mixing on a day; effective membership dates enforced;
  edits only in open periods; correcting an approved entry returns it to submitted and is audited.
- Guest meals (sponsor, optional meal type, optional charge tracked separately), meal adjustments (±units).
- Approval screen with approve / reject (reason) / bulk approve; void with reason.
- Screens: daily bulk grid with member selection and daily summary, month calendar (mess or member; plain members
  only see their own), guest meals, adjustments, approvals, periods, meal types.
- Decimal-safe arithmetic via bcmath (`App\Support\Decimal`).

Not in Phase 2 by design: meal-rate calculation (Phase 4), self-service meal entry by members (Phase 5),
`meal_schedules` table (no screen needs it yet; add with self-service).

## Phase 3 — delivered

- Double-entry ledger: per-mess system accounts (cash, bank, mobile wallet, member balances, vendor payables,
  food expense, other expenses), sequential transaction numbers under a lock, Σdebit = Σcredit enforced in code,
  DB CHECK that each line is one-sided and positive, posted transactions/lines immutable at model level,
  reversal = mirror transaction referencing the original (once only).
- Bazar: total-only or itemized entry, server-computed line totals / subtotal / discount / total and food share
  (`food_amount`, entry discount spread pro rata), receipts on the private disk served via an authorized route,
  approve → ledger (Dr food / other expense, Cr cash·bank·wallet·member·payable), reject, void (reverses ledger),
  approved entries not editable.
- Expenses: categories by type, equal / meal-based / custom / usage-based / excluded; custom must sum exactly,
  usage-based split by largest remainder; food expenses must be meal-based; approve / reject / void with ledger.
- Deposits: cash / bank / mobile wallet, transaction reference required for bank & wallet and unique per mess+method,
  approve (Dr money, Cr member balance), reject, reverse.
- Idempotency keys on create forms (double submit → one record; same key with different data → error).
- Screens: bazar list/form/detail, expense list/form/detail, deposits, ledger, balances (trial-balance check +
  member balances), period report (bazar by category/purchaser, expenses by category, deposits by member; printable),
  bazar/expense category settings.

Deferred by design: refunds and financial adjustments (spec places refunds with settlement, Phase 4);
equal/meal-based expense shares are computed at settlement; Excel/PDF export is Phase 5.

## Phase 4 — delivered

- Engine (`app/Services/Settlement`): `MealRateStrategyInterface` with Weighted (default), Equal and ConfigurableCustom
  strategies; FoodExpensePoolService, MealCalculationService, ExpenseAllocationService, MemberBalanceService,
  FinancialReconciliationService, SettlementService; all arithmetic via bcmath with exact largest-remainder splits.
- Per-mess policy (strategy, fixed rate, equal-split by members or by days present) stored in `system_settings`
  and copied into every snapshot.
- Live preview → draft (regenerable) → finalize: immutable snapshot with full calculation JSON, settlements +
  itemized statement lines, member charges posted to the ledger, per-member reconciliation against the ledger,
  period marked finalized — in one DB transaction; any mismatch rolls everything back.
- Opening credit = ledger balance the day before the period → carry-forward is automatic.
- Settlement payments: collection of negative balances, refunds of positive ones, partial/full, idempotent.
- Member credit/debit adjustments with approval, ledger posting and void.
- Reopen: settlement superseded (kept), charges reversed, new version on re-finalize. Periods can no longer be
  finalized except through the settlement.
- Screens: settlement (preview/final, settings, versions), member statement (printable, visible to the member),
  credits & debits; "Settle & finalize" on the periods page; settlements listed on the member page.

Not implemented: a separate `refunds` table — refunds are `settlement_payments` of type `refund`; settlement status
`reviewed` (drafts serve that role).

## Phase 5 — delivered

- Admin dashboard (per period): active members, meal units, estimated meal rate, bazar, other expenses,
  deposits, what members owe, pending approvals, recent transactions, daily meal-unit line chart and daily
  spending bar chart (Chart.js, single series, hover tooltips, data-table toggle, future days left blank).
  Cached 60 s under a tenant+mess-scoped key.
- Member portal (`/me`): today's meals, own units, estimated rate and closing, current balance, recent
  transactions, statements, announcements. Plain members land here instead of the admin dashboard.
- Self-service meal entry (per-mess switch, optional approval); approved days are locked for members.
- Announcements with expiry/withdraw; portal settings.
- Reports: daily meals, monthly meals by member, bazar, expenses, deposits, settlement, outstanding balances,
  member ledger (members can read their own), plus the existing financial summary. Each renders in the browser,
  prints cleanly (print stylesheet hides navigation), and downloads as UTF-8 CSV that opens in Excel.
- N+1 guard test on eight list/report pages.

**Not done as specified:** native `.xlsx` and server-generated PDF. Installing packages
(`laravel/boost`, and a spreadsheet/PDF library) was blocked in this environment, so exports are CSV
(opens in Excel, with BOM for Bangla and formula-injection protection) and PDF is via the browser's
print-to-PDF. Adding e.g. `maatwebsite/excel` + `barryvdh/laravel-dompdf` later only needs a new writer for the
existing report structure. Laravel Boost (required by CLAUDE.md) is also not installed yet.

## Phase 1 acceptance criteria

| Criterion | Covered by |
|---|---|
| A tenant can create multiple messes | `TenantAndMessManagementTest::test_tenant_owner_can_create_multiple_messes` |
| A user can join multiple messes | `test_user_can_join_multiple_messes_with_different_roles` |
| Different roles in different messes | same test |
| Cross-tenant access rejected | `TenantIsolationTest` |
| Cross-mess access rejected | `TenantIsolationTest` |
| Authentication works | `AuthenticationTest` |
| All migrations execute | passed on MySQL 8.4.11 (private test instance) |

Verified 2026-11-03 on a private MySQL 8.4.11 instance: `migrate:fresh --seed` OK, `php artisan test` 35/35, HTTP flows for owner/manager/member/platform admin, headless-Chrome screenshots at 1366px and 390px (no console errors, no horizontal overflow).

## Decisions / deviations from the spec

- Platform super admin is a `users.is_platform_admin` flag; the spec has no user↔platform-role table.
- Tenant-level management is owner-only for now (tenant roles beyond owner are not modelled yet).
- Subscription tables (spec migration steps 4–7) are deferred to Phase 6 instead of being created empty now.
- `audit_logs` was created in Phase 1 (spec step 40) because Phase 1 already writes audit events.
- Adding a member currently requires an existing account; email/phone invitations and members without
  accounts arrive in Phase 2 (`mess_invitations` table already exists).
- Platform admins have no implicit access to tenant/mess data.

## Phase 2 verification (2026-11-03, private MySQL 8.4.11)

- `migrate:fresh --seed` OK; `php artisan test` 72/72 (278 assertions).
- Headless Chrome: daily grid edit + save, mode-conflict locking, calendar, guest meal, adjustment, reject with
  reason, bulk approve, period create / overlap rejection / open, meal-type factor edit, member without account,
  invitation create → register → accept, plain-member view. No console errors, no page-level horizontal overflow.
- Bugs found and fixed: adjustments/guest pages crashed after a row existed (eager load omitted `period.status`),
  guest list N+1 on period; regression test added.

## Phase 3 verification (2026-11-03, private MySQL 8.4.11)

- `migrate:fresh --seed` OK (demo ledger balanced); `php artisan test` 98/98 (385 assertions).
- Headless Chrome: itemized bazar with live estimate → server totals → approve → ledger card → void; total-only
  purchase; reject with reason; cancelled reason prompt does not submit; custom expense split mismatch rejected
  then accepted; food category forces meal-based; deposit approve-now; duplicate bKash reference rejected;
  deposit reversal; balances page shows Dr = Cr after reversals; category add; bazar manager and plain member
  see only their menus; member gets 403 on ledger. No console errors.
- Fixed during verification: item category dropdown defaulted to a non-food category (food first now); period
  report crashed on a joined enum column; missing sidebar icon; demo data produced negative cash.

## Phase 4 verification (2026-11-03, private MySQL 8.4.11)

- `php artisan test` 129/129 (519 assertions), covering every scenario in the spec's Phase 4 list: zero meals,
  zero food, factors, both modes, guests, custom/equal/meal-based allocation, mid-period join/leave, multiple
  deposits, refunds, opening credits, negative/positive balance, rounding, reversals, duplicate generation,
  reopening; plus reconciliation-failure rollback and snapshot reproducibility.
- Headless Chrome month-end: approve pending → draft → review → finalize (rate 123.8113, Σ meal cost = pool
  3,281.00, Σ closing 9,220.00 = cash in hand) → statement → partial refund → reopen (v1 superseded, charges
  reversed) → re-finalize v2; ledger balanced throughout; member sees own statement. No console errors.
- Fixed during this phase: test assumptions about which member receives the extra paisa (order is by member code);
  three weak assertions rewritten so they actually test something.

## Phase 5 verification (2026-11-04, private MySQL 8.4.11)

- `php artisan test` 140/140.
- Headless Chrome: dashboard cards and both charts render with real figures; reports list/monthly/deposits/
  outstanding; CSV headers and content; print media hides sidebar and buttons; announcement publish; member
  redirected to portal with own balance and announcements; self-service submit; member blocked from another
  member's ledger. No unexpected console errors.
- Fixed during verification: the meal chart dropped to zero for days not yet reached; the portal ran the
  settlement calculation twice.

## Known issues

- None open. Fixed during verification: `mess_members.user_id` FK cannot be SET NULL (base of a stored generated column) → now RESTRICT; profile update crashed when `phone` was omitted; roles were unseeded in later test classes; sidebar shrank next to wide tables.

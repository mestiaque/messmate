# Changelog

## [Unreleased]

### Added — Phase 6 (subscriptions and platform)
- Subscription plans, trials, limits and features; read-only mode for lapsed subscriptions.
- Checkout with invoices, manual and sandbox payment providers, signed idempotent callbacks, renewals and carry-over.
- Hourly subscription sweep; platform admin for plans, payments, settings, support tickets and announcements.

### Added — Phase 5 (dashboards, reports, member portal)
- Admin dashboard with cached period figures and trend charts.
- Member portal, self-service meal entry with approval option, announcements.
- Eight reports with print layout and CSV export.

### Added — Phase 4 (calculation engine and settlement)
- Meal-rate strategies (weighted, equal, fixed), food pool, unit and expense allocation services.
- Draft and final settlements with immutable snapshots, itemized member statements, ledger posting and
  per-member reconciliation; settlement collections and refunds; member credit/debit adjustments.
- Audited reopening that supersedes the settlement and reverses its charges.

### Changed
- Ledger reversals are dated like the original transaction by default.
- Periods can only be finalized through the settlement.
- Usage-based expense split now uses the shared `Decimal::allocate`.

### Added — Phase 3 (bazar, expenses, deposits, ledger)
- Double-entry ledger with immutable postings and reversals; account and member balances; trial-balance check.
- Bazar purchases (total or itemized) with server-side totals, food share, receipts and approval workflow.
- Expenses with five allocation methods, receipts and approval workflow.
- Member deposits with reference de-duplication, approval and reversal.
- Idempotent create forms; period finance report; category management.

### Added — Phase 2 (members, periods, meals)
- Members without accounts, invitations, account linking, status changes with effective dates, member meal statement.
- Accounting periods with overlap protection, lifecycle, guarded finalization and audited reopening.
- Configurable meal types with factor snapshots; individual and daily-total meal entry; bulk daily grid; calendar.
- Guest meals, meal adjustments, approval / rejection / void workflow with audit log.

### Changed
- `mess_members.user_id` foreign key is RESTRICT (MySQL restriction with generated columns).

### Added — Phase 1 (foundation)
- Laravel 13 project configured for MySQL.
- Email/phone authentication, registration, profile.
- Tenants, messes, memberships, system mess roles and permissions.
- Tenant/mess context middleware, policies, scoped bindings, tenant-scoped cache keys, tenant-aware queued jobs.
- Platform, tenant and mess dashboards; platform tenant suspension/activation.
- Audit logging, factories, seeders, feature tests.

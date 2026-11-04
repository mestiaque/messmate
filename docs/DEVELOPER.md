# Developer Guide

## Stack

Laravel 13, PHP 8.3+, MySQL 8 and Blade with Bootstrap 5 (from a CDN). There is no frontend build step. Chart.js comes from jsdelivr.

## Local setup

```sh
composer install
cp .env.example .env && php artisan key:generate
php artisan messmate:install --demo     # or: php artisan migrate --seed
php artisan serve
php artisan test
```

Tests use the `messmate_test` database (see `phpunit.xml`) and seed `BaseDataSeeder` once.

## Layout

| Path | What lives there |
|---|---|
| `app/Http/Controllers/*` | Thin controllers, grouped by area (Meals, Finance, Settlement, Billing, Platform, …) |
| `app/Http/Requests/*` | Validation. Never trust totals or rates from the browser. |
| `app/Services/*` | All business logic. Controllers call services; services own transactions. |
| `app/Policies`, `App\Services\Access\MessAccessService` | Authorization |
| `app/Support/Decimal.php` | bcmath money math, half-up rounding, `allocate()` largest-remainder splits |
| `app/Support/Tenancy/*` | `TenantContext`, the permission catalogue |
| `app/Console/Commands` | `messmate:install`, `messmate:check`, `subscriptions:sweep` |

## Rules for contributors

1. **Tenancy.**
   - Every tenant route lives under `/t/{tenant}` (and `/m/{mess}`) with `scopeBindings()`.
   - A route parameter name must match a relation on the parent (`{supportTicket}` → `supportTickets()`).
   - Never look up a tenant-owned model by a bare id.
2. **Authorization.**
   - Add a permission to `Permissions`, a method to `MessPolicy`, and re-run `BaseDataSeeder`.
   - The tenant owner implicitly has all permissions.
3. **Money.**
   - Use `Decimal`, never floats.
   - Store money as `decimal(15,2)` and units as `decimal(12,4)`.
4. **Ledger.**
   - Post through `LedgerService` only. Lines are immutable.
   - Correct mistakes with `reverse()`. Debits must equal credits (enforced in code and by a CHECK constraint).
5. **Settlement.**
   - Final snapshots are immutable.
   - To change a finalized month, reopen. This supersedes the snapshot and reverses its charges.
6. **Billing.**
   - Plan limits go through `SubscriptionService::assertCanAdd()`, features through `hasFeature()`.
   - Writes to a read-only organization are blocked by `subscription.writable`.
7. **Caching.** Build keys with `TenantContext::cacheKey()` so tenants never share entries.
8. **Queues.** Jobs that need tenant context use `App\Jobs\Concerns\TenantAware`. Notifications sent inside a transaction call `afterCommit()`.

## Adding a payment provider

1. Implement `App\Services\Billing\Providers\PaymentProviderInterface`:
   - `start()` returns a `PaymentIntent`.
   - `verifyCallback()` returns a `CallbackResult` or throws `InvalidCallbackException`.
2. Verify the provider's signature with `hash_equals`, reject stale callbacks, and return the amount and currency so `BillingService` can match them.
3. Register it in `PaymentProviderManager` and add its key to `BILLING_PROVIDERS`.
4. Callbacks arrive at `POST /billing/callback/{provider}`. This route is CSRF-exempt and throttled. Processing is idempotent per `provider_reference`.

## Tests

- `tests/Unit` holds pure maths (`Decimal`).
- `tests/Feature/*` covers each area. Notable suites:
  - `Settlement/*`: calculation and reconciliation.
  - `Tenancy/*` and `Release/IsolationSweepTest`: cross-tenant access across every scoped screen.
  - `Billing/*`: limits, read-only mode, callbacks and carry-over.
  - `Portal/QueryCountTest`: N+1 guard.

Run the whole suite before every commit.

## Documentation to keep current

`PROJECT_STATUS.md`, `DATABASE_SCHEMA.md`, `BUSINESS_RULES.md`, `CHANGELOG.md`.

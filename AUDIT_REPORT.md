# MessMate — Production Readiness Audit

Date: 2026-11-04 · Scope: Phases 1–6, commit `e5b65c8` plus the Phase 7 changes · Environment: PHP 8.5, MySQL 8.4 (private test instance)

## Result

**Ready for a controlled production release**, with the limitations listed at the end.

- 171 automated tests, 899 assertions: all pass.
- A fresh install with `php artisan messmate:install` migrates, seeds and creates the admin without errors.

## Security audit

| Area | Finding | Status |
|---|---|---|
| Authentication | Login by email or phone. 5 failed attempts per login+IP locks for a minute, plus a route throttle of 10/min. Inactive users are refused at login and logged out on every request (`EnsureUserIsActive`). Session ID is regenerated on login; logout invalidates the session and CSRF token. | OK |
| Password reset | **Was missing; added in Phase 7.** Uses Laravel's token broker (hashed, 60-minute tokens). The same response is returned for unknown emails, so accounts cannot be enumerated. Requests are throttled at 5/min. The remember token is rotated on reset. | Fixed |
| Password policy | `Password::defaults()` (min 8) on register, profile and reset. The installer rejects weak admin passwords. | OK |
| Default credentials | `DatabaseSeeder` used to create `admin@…/password` in any environment. **Now skipped outside local/testing unless `PLATFORM_ADMIN_PASSWORD` is set.** | Fixed |
| Authorization | Every action is checked through policies (`MessPolicy`, `MessMemberPolicy`, `TenantPolicy`) backed by `MessAccessService`. Platform screens use `platform.admin`. | OK |
| Tenant / mess isolation | Context comes from the URL and is verified by `ResolveTenant` / `ResolveMess`. Non-members get 404. `scopeBindings()` resolves children only through their parent. **New `IsolationSweepTest` walks all 31 tenant- and mess-level GET screens (those without a record id): owner gets 200, another organization's owner gets 403/404 on every one.** | OK |
| IDOR | Children resolve via scoped relations; bulk approvals and member pickers re-check ownership (`MealScreensTest`, `Tenancy/*`). Invoice and ticket routes are scoped to the tenant. | OK |
| CSRF | On for all web routes. The only exemption is `billing/callback/*`, which is authenticated by HMAC signature instead. | OK |
| XSS | No `{!! !!}` in any view; all output escaped by Blade. CSV export neutralises formula injection (`= + - @`). | OK |
| SQL injection | Eloquent / query builder throughout. All 10 `selectRaw` calls use constant SQL with no interpolated input. | OK |
| File uploads | `jpg, jpeg, png, webp, pdf`, max 5 MB, MIME checked by `fileinfo`. Stored under a random name. | OK |
| Private files | Receipts live on the private disk, per tenant/mess. They are served only by `ReceiptController` after a permission check, never from `public/`. | OK |
| Rate limiting | Login, register, password reset, invitations, checkout, self-service meals and payment callbacks are all throttled. | OK |
| Session security | Database sessions, HttpOnly, SameSite=lax. Production template sets `SESSION_SECURE_COOKIE=true` and `SESSION_ENCRYPT=true`; `messmate:check` warns if insecure. | OK |
| Security headers | **Added `SecurityHeaders` middleware:** nosniff, X-Frame-Options SAMEORIGIN, Referrer-Policy, Permissions-Policy, HSTS on HTTPS. Trusted proxies are configurable (`TRUSTED_PROXIES`). | Fixed |
| Financial integrity | `Decimal` (bcmath) everywhere; no floats. Double-entry ledger with immutable lines and a DB CHECK constraint. Reversals instead of edits. Final snapshots and settlements are immutable; reopening supersedes the settlement and reverses its charges. Totals and rates are always computed on the server. Idempotency keys on money-creating forms. | OK |
| Payment callbacks | HMAC-SHA256 verified with `hash_equals`; older than 10 minutes is rejected; amount and currency must match; the row is locked and processing is idempotent per provider reference. The sandbox provider is excluded from production defaults, and `messmate:check` fails if it is enabled. | OK |

## Performance audit

| Area | Finding |
|---|---|
| N+1 queries | `QueryCountTest` asserts the query count stays flat as rows grow on the main list and dashboard screens. List screens eager-load their relations. |
| Indexing | 50 explicit indexes, plus FK indexes: `(tenant_id, mess_id, …)` composites on all scoped tables, period/date/status indexes on meals, ledger and finance tables, unique keys for invoice numbers, provider references and active memberships. |
| Slow queries | Reports and the dashboard aggregate in SQL (`SUM`/`GROUP BY`) per period instead of loading rows. |
| Dashboard | Cached 60 s per tenant-scoped key; invalidated by time only, so figures can be up to a minute old. |
| Pagination | All 14 list controllers paginate. Unbounded lists are limited (portal transactions 15, announcements 5). |
| Queue | Payment-receipt mail is now queued and sent after the transaction commits. The database queue driver is the default; see the deployment guide for the worker. |
| Cache isolation | All cache keys are built by `TenantContext::cacheKey()` with the tenant (and mess) id. |
| Large reports | Reports are scoped to one period or one member, so the size is bounded by members × days. CSV is streamed (`streamDownload`). |
| Memory | `subscriptions:sweep` iterates with `cursor()`. Settlement works per period. |

## Automated tests

| Kind | Where |
|---|---|
| Unit | `tests/Unit/DecimalTest` (rounding, allocation) |
| Calculation | `Settlement/*` (weighted / equal / custom rates, food pool, allocation, balances) |
| Ledger reconciliation | `Finance/*`, `Settlement/*` (balanced postings, reversals, reconcile after finalize / reopen) |
| Authorization | `Tenancy/*`, `Meals/MealScreensTest`, `Portal/*` |
| Tenant isolation | `Tenancy/*`, `Release/IsolationSweepTest` |
| Subscription | `Billing/SubscriptionTest`, `PaymentTest`, `PlatformAdminTest` |
| Integration | Full HTTP flows: meals → bazar / expenses / deposits → settlement → payments; checkout → callback → invoice |
| Release (new) | `Release/PasswordResetTest`, `SecurityHeadersTest`, `InstallCommandTest`, `IsolationSweepTest` |

## Acceptance criteria

| Criterion | Evidence |
|---|---|
| Installs on a fresh environment | `messmate:install` on an empty MySQL 8.4 database: migrations, base data, admin and demo data |
| Runs all migrations | 24 migrations, run by the test suite and the installer |
| Passes automated tests | 171 / 171 |
| Prevents cross-tenant access | `IsolationSweepTest` + tenancy tests |
| Reconciles financial records | Ledger balance check + settlement reconciliation tests |
| Correct monthly settlements | Settlement calculation tests with hand-checked figures |
| Subscription restrictions | Limits, features and read-only mode tests |
| Desktop and mobile | Bootstrap responsive layouts, checked in headless Chrome at desktop and phone widths in earlier phases |
| No fake buttons | All screens are wired. The only gateway that redirects off-site is the sandbox, which is labelled "test only" and disabled in production. |

## Known limitations

1. **No real online payment gateway.** Production uses manual bank/bKash transfers confirmed by an admin. bKash/SSLCommerz need an implementation of `PaymentProviderInterface` and merchant credentials.
2. **No proration.** A plan change applies immediately; remaining paid time is carried over, not refunded.
3. **Exports are CSV + print-to-PDF.** Native Excel/PDF were not added because installing extra packages was not permitted in this environment.
4. **No email/phone verification and no 2FA.** A password change does not sign out other sessions. Recommended next security items.
5. **No Content-Security-Policy header.** Pages load Bootstrap and Chart.js from CDNs and use small inline scripts. A CSP needs nonces or self-hosted assets.
6. **Installer is CLI-only** (`messmate:install`). No web installer, by design (see DEPLOYMENT.md).
7. **Laravel Boost** dev tooling, requested in `CLAUDE.md`, was not installed (package install not permitted here). Run `composer require laravel/boost --dev && php artisan boost:install` if wanted.
8. **Dashboard figures may lag by up to 60 seconds** because of caching.
9. **Verification** was done on a private MySQL 8.4 instance, not on the target production server. Run `messmate:check` and the test suite there before go-live.
10. **License terms** in `LICENSE.md` are a template. The owner must finalize them.

# MessMate

Multi-tenant mess management SaaS (Laravel 13, PHP 8.3+, MySQL 8, Blade + Bootstrap 5).

See `masterprompt.md` for the full specification and `PROJECT_STATUS.md` for what is built so far.

| Document | For |
|---|---|
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Production install: requirements, installer, migrations, seeders, storage, queue, scheduler, Nginx, logging |
| [docs/UPGRADE.md](docs/UPGRADE.md) | Upgrading and rolling back |
| [docs/BACKUP_RESTORE.md](docs/BACKUP_RESTORE.md) | Backups and restore |
| [docs/USER_GUIDE.md](docs/USER_GUIDE.md) | Owners, managers and members |
| [docs/DEVELOPER.md](docs/DEVELOPER.md) | Code layout and rules for contributors |
| [AUDIT_REPORT.md](AUDIT_REPORT.md) | Security / performance audit and known limitations |
| [LICENSE.md](LICENSE.md) | License (template) and third-party licenses |

## Setup

Requirements: PHP 8.3+ with `pdo_mysql`, `bcmath`, `mbstring`, `intl`; Composer; MySQL 8.

```bash
composer install
cp .env.example .env        # skip if .env already exists
php artisan key:generate
```

Create the databases and a user (as a MySQL admin):

```sql
CREATE DATABASE messmate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE messmate_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'messmate'@'localhost' IDENTIFIED BY 'change-me';
GRANT ALL PRIVILEGES ON messmate.* TO 'messmate'@'localhost';
GRANT ALL PRIVILEGES ON messmate_test.* TO 'messmate'@'localhost';
```

Set `DB_USERNAME` / `DB_PASSWORD` in `.env`. Tests use `messmate_test` (see `phpunit.xml`) with the same
credentials; set `DB_PASSWORD` there too or export it in your shell.

Quickest path — the installer validates the environment, migrates, seeds and creates the admin:

```bash
php artisan messmate:install --demo   # interactive; --demo only outside production
php artisan messmate:check            # environment validation on its own
```

Or by hand:

```bash
php artisan migrate --seed   # roles/permissions + platform admin (+ demo data when APP_ENV=local)
php artisan serve
php artisan test
```

After pulling a new phase on an existing database: `php artisan migrate` then
`php artisan db:seed --class=BaseDataSeeder` (idempotent; adds new permissions and plans). See docs/UPGRADE.md.

### Seeded accounts (password: `password`)

| Account | Purpose |
|---|---|
| `admin@messmate.test` | Platform admin (override with `PLATFORM_ADMIN_EMAIL` / `PLATFORM_ADMIN_PASSWORD`) |
| `owner@messmate.test` | Owner of "Demo Organization" with two messes; North Hall has an open current-month period with meals (local only) |
| `manager@messmate.test` | Manager in North Hall, plain member in South Hall (local only) |
| `member@messmate.test` | Member in North Hall (local only) |

## Scheduler and billing

Run the Laravel scheduler (cron: `* * * * * php artisan schedule:run`). It runs `subscriptions:sweep` hourly,
which moves ended trials/periods to past-due or expired and purges old idempotency keys. Access checks are
date-based, so enforcement is correct even if the sweep is late.

Payment providers are set with `BILLING_PROVIDERS` (`manual`, `sandbox`). Manual payments are confirmed by a
platform admin under **Platform → Payments**. The sandbox gateway simulates a hosted checkout with signed
callbacks to `POST /billing/callback/sandbox`; it is for testing only. A real gateway (bKash, SSLCommerz…)
is added by implementing `App\Services\Billing\Providers\PaymentProviderInterface` and registering it in
`PaymentProviderManager`.

## Architecture overview

- URLs carry the context: `/t/{tenant}/m/{mess}/...`. `ResolveTenant` and `ResolveMess` middleware
  verify membership on every request; scoped route bindings make a mess resolvable only under its own
  tenant and a member only under its own mess. Non-members get 404 (no slug probing).
- `App\Support\Tenancy\TenantContext` holds the resolved tenant/mess (scoped singleton) and builds
  tenant-scoped cache keys.
- `App\Services\Access\MessAccessService` decides mess permissions: tenant owner = all, otherwise the
  active membership's roles. Policies (`TenantPolicy`, `MessPolicy`) use it.
- Business logic lives in `app/Services/*`; controllers stay thin; validation in Form Requests.
- Queued jobs that need tenant context use the `App\Jobs\Concerns\TenantAware` trait.

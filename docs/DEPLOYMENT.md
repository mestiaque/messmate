# Deployment Guide

This guide covers a single-server production install (Ubuntu + Nginx + PHP-FPM + MySQL 8).
Commands run from the application root as the deploy user unless noted.

## 1. Server requirements

| Component | Version / note |
|---|---|
| PHP | 8.3 or newer, with `bcmath`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` (and `intl` recommended) |
| MySQL | 8.0 or newer (InnoDB, `utf8mb4`). Generated columns and CHECK constraints are used. |
| Composer | 2.x |
| Web server | Nginx or Apache with HTTPS. Document root is `public/`. |
| Process manager | systemd or Supervisor, for the queue worker |
| Cron | for the Laravel scheduler |

`php artisan messmate:check` validates all of this.

## 2. Install

```sh
git clone https://github.com/mestiaque/messmate.git /var/www/messmate
cd /var/www/messmate
composer install --no-dev --optimize-autoloader
cp .env.production.example .env        # fill DB_*, APP_URL, MAIL_*
php artisan key:generate
php artisan messmate:install           # checks env, migrates, seeds base data, creates the admin
php artisan storage:link               # harmless; receipts stay private regardless
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Create the database and a dedicated user first. The app user needs only privileges on its own schema:

```sql
CREATE DATABASE messmate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'messmate'@'localhost' IDENTIFIED BY '<strong password>';
GRANT ALL PRIVILEGES ON messmate.* TO 'messmate'@'localhost';
```

Ownership: `storage/` and `bootstrap/cache/` must be writable by the PHP-FPM user (`www-data`):

```sh
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

## 3. Installation wizard

`php artisan messmate:install` is the installer. It is safe to re-run.

1. Runs the environment checks and stops on any required failure.
2. Runs `migrate --force`.
3. Seeds roles, permissions and subscription plans (idempotent).
4. Creates the platform admin. If the email already exists it is only promoted to admin; its password is never changed.

Non-interactive use (CI, provisioning scripts):

```sh
php artisan messmate:install --admin-name="Owner" --admin-email=you@example.com --admin-password='...'
```

`--demo` loads demo organizations. It is ignored in production.

The installer runs from the command line on purpose. A web installer would leave a public endpoint that can create admins.

## 4. Database migrations

- Always back up before migrating (see [BACKUP_RESTORE.md](BACKUP_RESTORE.md)).
- Review pending migrations first: `php artisan migrate:status` and `php artisan migrate --pretend`.
- Apply: `php artisan migrate --force`.
- Never run `migrate:fresh`, `migrate:refresh` or `db:wipe` on production. They destroy finalized accounting records.
- Ledger rows, final snapshots and finalized settlements are immutable by design. Corrections are made with reversals and adjustments, never by editing rows.

## 5. Seeders

| Seeder | Purpose | Production? |
|---|---|---|
| `BaseDataSeeder` | Roles, permissions, subscription plans. Idempotent. Re-run after upgrades. | Yes |
| `DatabaseSeeder` | Base data + platform admin from `PLATFORM_ADMIN_EMAIL` / `PLATFORM_ADMIN_PASSWORD`. Outside local/testing it skips the admin when no password is set. | Prefer the installer |
| `DemoSeeder` | Demo organization with password `password`. | **Never** |

## 6. Storage

- Receipts are stored on the private `local` disk under `storage/app/private/receipts/{tenant}/{mess}`.
- They are served only through an authorized controller route, never from `public/`.
- Include `storage/app/private` in backups.
- To use S3, configure a private bucket and change the disk in `ReceiptStorage`. Keep the bucket non-public.

## 7. Queue worker

Payment-receipt emails are queued. Run one worker under systemd:

```ini
# /etc/systemd/system/messmate-queue.service
[Unit]
Description=MessMate queue worker
After=network.target mysql.service

[Service]
User=www-data
Restart=always
WorkingDirectory=/var/www/messmate
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```sh
sudo systemctl enable --now messmate-queue
```

After each deploy, run `php artisan queue:restart` so workers load the new code. Inspect failed jobs with `php artisan queue:failed`.

## 8. Scheduler

Add one cron entry for the web user (`sudo crontab -u www-data -e`):

```
* * * * * cd /var/www/messmate && php artisan schedule:run >> /dev/null 2>&1
```

The schedule runs `subscriptions:sweep` every hour. It moves ended trials and periods to past-due/expired and purges expired idempotency keys.

## 9. Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name messmate.example.com;
    root /var/www/messmate/public;
    index index.php;
    client_max_body_size 6M;   # receipts are limited to 5 MB

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\.(?!well-known) { deny all; }
}
```

If you run behind a load balancer or proxy that terminates TLS, set `TRUSTED_PROXIES` (the proxy IPs, or `*`). Otherwise HSTS and secure-URL detection will not work.

## 10. Error logging and monitoring

- `LOG_STACK=daily` keeps 14 days in `storage/logs/`. `LOG_LEVEL=warning` in production.
- `APP_DEBUG` must be `false`. `messmate:check` fails otherwise.
- Health check endpoint: `GET /up` (returns 200).
- Watch `queue:failed`, disk space in `storage/`, and the platform payments queue for pending manual transfers.

## 11. Deploying an update

See [UPGRADE.md](UPGRADE.md).

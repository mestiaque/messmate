# Backup and Restore

MessMate keeps two kinds of state: the **MySQL database** and **private files** (`storage/app/private`, i.e. receipts). Back up both together.

## What to back up

| Item | Path / source | Frequency |
|---|---|---|
| Database | `mysqldump` of the `messmate` schema | Daily (more often for busy installs) |
| Private files | `storage/app/private/` | Daily |
| Configuration | `.env` (contains `APP_KEY`) | On change. Store it separately and encrypted. |

Without the same `APP_KEY`, encrypted sessions and any encrypted values cannot be read after a restore.

## Backup script

```sh
#!/usr/bin/env bash
set -euo pipefail
STAMP=$(date +%F-%H%M)
DEST=/var/backups/messmate
mkdir -p "$DEST"

# Consistent InnoDB snapshot without locking tables.
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 \
  -u messmate -p"$DB_PASSWORD" messmate | gzip > "$DEST/db-$STAMP.sql.gz"

tar -czf "$DEST/files-$STAMP.tar.gz" -C /var/www/messmate storage/app/private

# Keep 30 days locally; copy off-site (rclone, s3 cp, etc.).
find "$DEST" -type f -mtime +30 -delete
```

Schedule it from root's crontab, for example `30 2 * * * /usr/local/bin/messmate-backup`. Copy the files off the server: a backup on the same disk is not a backup.

## Restore

```sh
php artisan down
gunzip < db-YYYY-MM-DD-HHMM.sql.gz | mysql -u messmate -p messmate
tar -xzf files-YYYY-MM-DD-HHMM.tar.gz -C /var/www/messmate
sudo chown -R deploy:www-data /var/www/messmate/storage
php artisan migrate --force          # only if the code is newer than the backup
php artisan optimize:clear && php artisan queue:restart
php artisan up
```

## Verify after restore

- Log in as the platform admin and open an organization.
- For each active mess, open **Finance → Ledger → Balances**. The badge must read "Ledger balanced" (debits = credits).
- Open the latest finalized settlement and compare member totals with the statements members received.
- Open a receipt to confirm files were restored.

Test the restore procedure on a staging server at least once a quarter.

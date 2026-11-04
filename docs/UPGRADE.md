# Upgrade Instructions

1. Read `CHANGELOG.md` for the target version. Look for migration or configuration notes.
2. Back up the database and `storage/app/private` ([BACKUP_RESTORE.md](BACKUP_RESTORE.md)).
3. Enter maintenance mode: `php artisan down --retry=60`
4. Update the code:
   ```sh
   git fetch --tags && git checkout <version>
   composer install --no-dev --optimize-autoloader
   ```
5. Review and apply migrations:
   ```sh
   php artisan migrate --pretend    # read what will run
   php artisan migrate --force
   php artisan db:seed --class=BaseDataSeeder --force   # new permissions / plans
   ```
6. Rebuild caches and restart workers:
   ```sh
   php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
   php artisan queue:restart
   ```
7. Run the environment check: `php artisan messmate:check`
8. Leave maintenance mode: `php artisan up`

## Rolling back

- Code: check out the previous tag and repeat steps 4 and 6.
- Database: prefer restoring the backup from step 2 over `migrate:rollback`. Rolling back migrations that dropped or reshaped columns can lose data.
- Never roll back across a migration that touched ledger or settlement tables without restoring a backup.

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckEnvironment extends Command
{
    protected $signature = 'messmate:check';

    protected $description = 'Validate the server environment and configuration for MessMate';

    public function handle(): int
    {
        $failed = 0;

        foreach ($this->checks() as [$label, $ok, $hint, $required]) {
            $this->line(sprintf('  %s %s%s', $ok ? '<info>✔</info>' : ($required ? '<error>✘</error>' : '<comment>!</comment>'), $label, $ok ? '' : " — {$hint}"));
            $failed += (! $ok && $required) ? 1 : 0;
        }

        if ($failed) {
            $this->error("{$failed} required check(s) failed.");

            return self::FAILURE;
        }

        $this->info('Environment looks good.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: bool, 2: string, 3: bool}>
     */
    public function checks(): array
    {
        $production = app()->environment('production');

        $checks = [
            ['PHP >= 8.3', version_compare(PHP_VERSION, '8.3.0', '>='), 'Upgrade PHP', true],
        ];

        foreach (['bcmath', 'pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'intl'] as $ext) {
            $checks[] = ["PHP extension {$ext}", extension_loaded($ext), "Install php-{$ext}", $ext !== 'intl'];
        }

        $checks[] = ['APP_KEY is set', filled(config('app.key')), 'Run php artisan key:generate', true];
        $checks[] = ['storage/ is writable', is_writable(storage_path()) && is_writable(storage_path('app/private') ?: storage_path('app')), 'chown/chmod storage', true];
        $checks[] = ['bootstrap/cache is writable', is_writable(base_path('bootstrap/cache')), 'chmod bootstrap/cache', true];
        $checks[] = ['Database connection', $this->databaseReachable(), 'Check DB_* settings and that the database exists', true];

        if ($production) {
            $checks[] = ['APP_DEBUG is off', ! config('app.debug'), 'Set APP_DEBUG=false', true];
            $checks[] = ['APP_URL uses https', str_starts_with((string) config('app.url'), 'https://'), 'Serve over HTTPS', false];
            $checks[] = ['Secure session cookie', (bool) config('session.secure'), 'Set SESSION_SECURE_COOKIE=true', false];
            $checks[] = ['Sandbox gateway disabled', ! in_array('sandbox', config('billing.providers', []), true), 'Remove sandbox from BILLING_PROVIDERS', true];
            $checks[] = ['Queue is not sync', config('queue.default') !== 'sync', 'Use QUEUE_CONNECTION=database or redis', false];
            $checks[] = ['Mail is configured', ! in_array(config('mail.default'), ['log', 'array'], true), 'Set MAIL_MAILER for password reset and receipts', false];
        }

        return $checks;
    }

    private function databaseReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}

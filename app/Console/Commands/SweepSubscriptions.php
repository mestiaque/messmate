<?php

namespace App\Console\Commands;

use App\Services\Billing\SubscriptionService;
use App\Services\Finance\IdempotencyService;
use Illuminate\Console\Command;

class SweepSubscriptions extends Command
{
    protected $signature = 'subscriptions:sweep';

    protected $description = 'Move ended trials and periods to past-due/expired, and purge expired idempotency keys';

    public function handle(SubscriptionService $subscriptions, IdempotencyService $idempotency): int
    {
        $changed = $subscriptions->sweep();
        $purged = $idempotency->purgeExpired();
        $this->info("{$changed} subscription(s) updated, {$purged} idempotency key(s) purged.");

        return self::SUCCESS;
    }
}

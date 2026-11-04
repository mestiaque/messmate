<?php

namespace App\Services\Billing\Providers;

final class CallbackResult
{
    public function __construct(
        public readonly string $reference,
        public readonly bool $success,
        public readonly string $amount,
        public readonly string $currency,
        public readonly array $raw = [],
    ) {}
}

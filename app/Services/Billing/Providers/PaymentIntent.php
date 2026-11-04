<?php

namespace App\Services\Billing\Providers;

final class PaymentIntent
{
    public function __construct(
        public readonly ?string $redirectUrl,
        public readonly ?string $instructions = null,
    ) {}
}

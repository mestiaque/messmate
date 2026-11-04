<?php

namespace App\Services\Billing;

use App\Services\Billing\Providers\ManualTransferProvider;
use App\Services\Billing\Providers\PaymentProviderInterface;
use App\Services\Billing\Providers\SandboxGatewayProvider;
use Illuminate\Validation\ValidationException;

/**
 * Registry of payment providers; which ones are offered comes from config/billing.php.
 */
class PaymentProviderManager
{
    private const PROVIDERS = [
        'manual' => ManualTransferProvider::class,
        'sandbox' => SandboxGatewayProvider::class,
    ];

    /**
     * @return array<string, PaymentProviderInterface>
     */
    public function enabled(): array
    {
        $enabled = [];
        foreach (config('billing.providers') as $key) {
            if (isset(self::PROVIDERS[$key])) {
                $enabled[$key] = app(self::PROVIDERS[$key]);
            }
        }

        return $enabled;
    }

    public function get(string $key): PaymentProviderInterface
    {
        return $this->enabled()[$key] ?? throw ValidationException::withMessages(['provider' => 'That payment method is not available.']);
    }
}

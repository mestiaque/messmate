<?php

namespace App\Services\Finance;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Runs a create action at most once per (tenant, key). Forms carry a random key,
 * so a double click or a resubmitted form does not create a second record.
 */
class IdempotencyService
{
    /**
     * @return array{0: bool, 1: mixed}  [ran now?, callback result or stored response]
     */
    public function once(int $tenantId, ?string $key, array $payload, Closure $callback): array
    {
        if (! $key) {
            return [true, $callback()];
        }

        ksort($payload);
        $hash = hash('sha256', json_encode($payload));

        try {
            $row = IdempotencyKey::create([
                'tenant_id' => $tenantId,
                'idempotency_key' => $key,
                'request_hash' => $hash,
                'expires_at' => now()->addDay(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = IdempotencyKey::query()->where('tenant_id', $tenantId)->where('idempotency_key', $key)->first();

            if ($existing && $existing->request_hash !== $hash) {
                throw ValidationException::withMessages(['idempotency_key' => 'This form was already submitted with different data. Reload the page and try again.']);
            }

            return [false, $existing?->response_data];
        }

        try {
            $result = $callback();
        } catch (\Throwable $e) {
            $row->delete(); // let a corrected resubmission through

            throw $e;
        }

        $row->update(['response_data' => ['id' => $result?->getKey()]]);

        return [true, $result];
    }

    public function purgeExpired(): int
    {
        return IdempotencyKey::query()->where('expires_at', '<', now())->delete();
    }
}

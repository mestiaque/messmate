<?php

namespace App\Services\Settlement;

/**
 * Per-mess calculation policy, stored in system_settings and copied into every
 * snapshot so a settlement can be reproduced later.
 */
final class SettlementPolicy
{
    public const STRATEGIES = ['weighted', 'equal', 'custom'];

    public const SPLIT_BASES = ['members', 'days'];

    public function __construct(
        public readonly string $strategy = 'weighted',
        public readonly ?string $customRate = null,
        public readonly string $equalSplitBasis = 'members',
    ) {}

    public static function fromArray(?array $data): self
    {
        $data ??= [];

        return new self(
            in_array($data['strategy'] ?? null, self::STRATEGIES, true) ? $data['strategy'] : 'weighted',
            isset($data['custom_rate']) && $data['custom_rate'] !== '' ? (string) $data['custom_rate'] : null,
            in_array($data['equal_split_basis'] ?? null, self::SPLIT_BASES, true) ? $data['equal_split_basis'] : 'members',
        );
    }

    public function toArray(): array
    {
        return ['strategy' => $this->strategy, 'custom_rate' => $this->customRate, 'equal_split_basis' => $this->equalSplitBasis];
    }
}

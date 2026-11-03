<?php

namespace App\Services\Settlement;

use App\Models\Mess;
use App\Models\SystemSetting;
use App\Services\Audit\AuditLogger;

class SettlementSettingsService
{
    private const KEY = 'settlement_policy';

    public function __construct(private readonly AuditLogger $audit) {}

    public function get(Mess $mess): SettlementPolicy
    {
        $value = SystemSetting::query()->where('tenant_id', $mess->tenant_id)->where('mess_id', $mess->id)->where('key', self::KEY)->value('value');

        return SettlementPolicy::fromArray(is_string($value) ? json_decode($value, true) : $value);
    }

    public function save(Mess $mess, SettlementPolicy $policy): void
    {
        $old = $this->get($mess)->toArray();
        SystemSetting::query()->updateOrCreate(
            ['tenant_id' => $mess->tenant_id, 'mess_id' => $mess->id, 'key' => self::KEY],
            ['value' => $policy->toArray()],
        );
        $this->audit->log('settlement_policy.updated', $mess, $old, $policy->toArray(), $mess->tenant_id, $mess->id);
    }
}

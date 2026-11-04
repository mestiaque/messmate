<?php

namespace App\Services\Portal;

use App\Models\Mess;
use App\Models\SystemSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\SubscriptionService;

/**
 * Member self-service options per mess, stored in system_settings.
 */
class PortalSettingsService
{
    private const KEY = 'member_portal';

    public const DEFAULTS = ['self_service_meals' => false, 'require_approval' => true];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * @return array{self_service_meals: bool, require_approval: bool}
     */
    public function get(Mess $mess): array
    {
        $value = SystemSetting::query()->where('tenant_id', $mess->tenant_id)->where('mess_id', $mess->id)->where('key', self::KEY)->value('value');
        $value = is_string($value) ? json_decode($value, true) : $value;

        $settings = array_map('boolval', array_intersect_key(($value ?? []) + self::DEFAULTS, self::DEFAULTS));

        // Self-service is a plan feature; a stored "on" has no effect on plans without it.
        $settings['self_service_meals'] = $settings['self_service_meals'] && $this->subscriptions->hasFeature($mess->tenant, 'self_service_meals');

        return $settings;
    }

    public function save(Mess $mess, bool $selfService, bool $requireApproval): void
    {
        $old = $this->get($mess);
        $new = ['self_service_meals' => $selfService, 'require_approval' => $requireApproval];
        SystemSetting::query()->updateOrCreate(['tenant_id' => $mess->tenant_id, 'mess_id' => $mess->id, 'key' => self::KEY], ['value' => $new]);
        $this->audit->log('portal_settings.updated', $mess, $old, $new, $mess->tenant_id, $mess->id);
    }
}

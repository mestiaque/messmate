<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly Request $request,
    ) {}

    public function log(string $action, ?Model $subject = null, array $old = [], array $new = [], ?int $tenantId = null, ?int $messId = null): AuditLog
    {
        return AuditLog::create([
            'tenant_id' => $tenantId ?? $subject?->getAttribute('tenant_id') ?? $this->context->tenant()?->id,
            'mess_id' => $messId ?? $subject?->getAttribute('mess_id') ?? $this->context->mess()?->id,
            'user_id' => $this->request->user()?->id,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 512) ?: null,
        ]);
    }
}

<?php

namespace Tests\Feature\Tenancy;

use App\Jobs\Concerns\TenantAware;
use App\Models\Mess;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LogicException;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_keys_are_scoped_by_tenant_and_mess(): void
    {
        $messA = Mess::factory()->create();
        $messB = Mess::factory()->create();
        $context = app(TenantContext::class);

        $context->setTenant($messA->tenant);
        $tenantKey = $context->cacheKey('summary');
        $context->setMess($messA);
        $messKeyA = $context->cacheKey('summary');

        $context->setTenant($messB->tenant);
        $context->setMess($messB);
        $messKeyB = $context->cacheKey('summary');

        $this->assertSame("tenant:{$messA->tenant_id}:summary", $tenantKey);
        $this->assertSame("tenant:{$messA->tenant_id}:mess:{$messA->id}:summary", $messKeyA);
        $this->assertNotSame($messKeyA, $messKeyB);
    }

    public function test_mess_from_another_tenant_cannot_be_set(): void
    {
        $messA = Mess::factory()->create();
        $messB = Mess::factory()->create();
        $context = app(TenantContext::class);
        $context->setTenant($messA->tenant);

        $this->expectException(LogicException::class);
        $context->setMess($messB);
    }

    public function test_queued_job_runs_with_dispatching_tenant_context_and_clears_it(): void
    {
        $mess = Mess::factory()->create();
        $context = app(TenantContext::class);
        $context->setTenant($mess->tenant);
        $context->setMess($mess);

        $job = new RecordTenantContextJob;
        $context->clear();

        $this->assertSame($mess->tenant_id, $job->tenantId);
        $this->assertSame($mess->id, $job->messId);

        RecordTenantContextJob::$seen = null;
        dispatch($job);

        $this->assertSame(['tenant' => $mess->tenant_id, 'mess' => $mess->id], RecordTenantContextJob::$seen);
        $this->assertNull(app(TenantContext::class)->tenant());
    }
}

class RecordTenantContextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public static ?array $seen = null;

    public function __construct()
    {
        $this->captureTenantContext();
    }

    public function handle(TenantContext $context): void
    {
        static::$seen = ['tenant' => $context->tenant()?->id, 'mess' => $context->mess()?->id];
    }
}

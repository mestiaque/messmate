<?php

namespace Tests\Feature\Portal;

use App\Models\MealEntry;
use App\Models\Role;
use App\Services\Portal\PortalSettingsService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Settlement\SettlementTestCase;

class DashboardAndPortalTest extends SettlementTestCase
{
    private function base(): string
    {
        return "/t/{$this->mess->tenant->slug}/m/{$this->mess->slug}";
    }

    public function test_admin_dashboard_shows_real_figures(): void
    {
        $a = $this->member();
        $b = $this->member();
        $this->units($a, '6');
        $this->units($b, '4');
        $this->food('1000', paidBy: $a);
        $this->deposit($b, '700');
        $this->expense('300');

        $response = $this->actingAs($this->owner())->get($this->base());

        $response->assertOk()
            ->assertSee('100.00')      // estimated rate 1000 / 10
            ->assertSee('1,000.00')    // bazar
            ->assertSee('700.00')      // deposits
            ->assertSee('Meal units per day')
            ->assertSee('chart.umd.min.js', false);
    }

    public function test_dashboard_cache_key_is_scoped_to_tenant_and_mess(): void
    {
        $this->actingAs($this->owner())->get($this->base())->assertOk();

        $key = "tenant:{$this->mess->tenant_id}:mess:{$this->mess->id}:dashboard:period:{$this->period->id}";
        $this->assertTrue(Cache::has($key));

        [$other] = $this->messWithOpenPeriod();
        $this->actingAs($other->tenant->owner)->get("/t/{$other->tenant->slug}/m/{$other->slug}")->assertOk();
        $this->assertNotSame($key, app(TenantContext::class)->cacheKey("dashboard:period:{$this->period->id}"));
    }

    public function test_plain_member_is_sent_to_their_portal_with_own_figures(): void
    {
        $a = $this->member();
        $b = $this->member();
        $this->units($a, '5');
        $this->units($b, '5');
        $this->food('500', paidBy: $b);
        $this->deposit($a, '1200');
        $this->mess->announcements()->create(['tenant_id' => $this->mess->tenant_id, 'title' => 'Water off', 'body' => 'No water on Friday', 'published_at' => now()]);
        $this->mess->announcements()->create(['tenant_id' => $this->mess->tenant_id, 'title' => 'Old news', 'body' => 'Expired', 'published_at' => now()->subWeek(), 'expires_at' => now()->subDay()]);

        $this->actingAs($a->user)->get($this->base())->assertRedirect(route('portal.index', ['tenant' => $this->mess->tenant, 'mess' => $this->mess]));

        $this->actingAs($a->user)->get("{$this->base()}/me")->assertOk()
            ->assertSee($a->full_name)
            ->assertSee('1,200.00')    // own current balance (deposit)
            ->assertSee('Water off')
            ->assertDontSee('Old news')
            ->assertDontSee($b->full_name);
    }

    public function test_self_service_meals_follow_portal_settings(): void
    {
        $a = $this->member();
        $types = $this->mealTypeIds($this->mess);
        $url = "{$this->base()}/me/meals";
        $payload = ['date' => '2026-11-10', 'meals' => [$types['lunch'] => 1, $types['dinner'] => 1]];
        $settings = app(PortalSettingsService::class);

        $this->actingAs($a->user)->get($url)->assertForbidden();
        $this->actingAs($a->user)->post($url, $payload)->assertForbidden();

        $settings->save($this->mess, true, true);
        $this->actingAs($a->user)->post($url, $payload)->assertSessionHasNoErrors();
        $entry = MealEntry::where('member_id', $a->id)->firstOrFail();
        $this->assertSame('submitted', $entry->status->value);
        $this->assertSame('2.0000', $entry->total_units);

        app(\App\Services\Meals\MealApprovalService::class)->approve($entry, $this->owner());
        $this->actingAs($a->user)->post($url, ['date' => '2026-11-10', 'meals' => [$types['lunch'] => 3]])->assertSessionHasErrors('date');
        $this->assertSame('2.0000', $entry->fresh()->total_units);

        $settings->save($this->mess, true, false);
        $this->actingAs($a->user)->post($url, ['date' => '2026-11-11', 'meals' => [$types['breakfast'] => 1]])->assertSessionHasNoErrors();
        $this->assertSame('approved', MealEntry::where('member_id', $a->id)->whereDate('meal_date', '2026-11-11')->value('status')->value);

        // Outside any open period.
        $this->actingAs($a->user)->post($url, ['date' => '2027-01-05', 'meals' => [$types['lunch'] => 1]])->assertSessionHasErrors('meal_date');
    }

    public function test_announcements_and_portal_settings_need_permission(): void
    {
        $a = $this->member();
        $manager = $this->memberOf($this->mess, Role::MANAGER);
        $base = "{$this->base()}/announcements";

        $this->actingAs($a->user)->get($base)->assertForbidden();
        $this->actingAs($a->user)->post($base, ['title' => 'x', 'body' => 'y'])->assertForbidden();
        $this->actingAs($a->user)->put("{$this->base()}/portal-settings", ['self_service_meals' => '1'])->assertForbidden();

        $this->actingAs($manager->user)->post($base, ['title' => 'Gas bill due', 'body' => 'Pay by Sunday'])->assertSessionHasNoErrors();
        $announcement = $this->mess->announcements()->first();
        $this->actingAs($manager->user)->delete("{$base}/{$announcement->id}")->assertSessionHasNoErrors();
        $this->actingAs($a->user)->get("{$this->base()}/me")->assertDontSee('Gas bill due');
    }
}

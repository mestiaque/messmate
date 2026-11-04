<?php

namespace Tests\Feature\Release;

use App\Models\Role;
use App\Services\Portal\PortalSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * Walks every tenant- and mess-scoped GET screen: the owner must get a working
 * page, and an owner of another organization must be refused on every one.
 */
class IsolationSweepTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_scoped_screen_renders_for_owner_and_is_refused_to_outsiders(): void
    {
        [$mess] = $this->messWithOpenPeriod();
        [$other] = $this->messWithOpenPeriod();
        $owner = $mess->tenant->owner;
        // The member portal (/me) needs a membership, so the owner also eats here.
        $this->memberOf($mess, Role::MEMBER, $owner);
        app(PortalSettingsService::class)->save($mess, selfService: true, requireApproval: true);
        $outsider = $other->tenant->owner;

        $routes = collect(Router::getRoutes()->getRoutes())->filter(fn (Route $r) => in_array('GET', $r->methods(), true)
            && str_starts_with($r->uri(), 't/{tenant}')
            && array_diff($r->parameterNames(), ['tenant', 'mess']) === []
            && ! str_contains($r->uri(), 'export'));

        $this->assertGreaterThan(30, $routes->count());

        foreach ($routes as $route) {
            $url = '/'.strtr($route->uri(), ['{tenant}' => $mess->tenant->slug, '{mess}' => $mess->slug]);

            $status = $this->actingAs($owner)->get($url)->status();
            $this->assertContains($status, [200, 302], "Owner got {$status} on {$url}");

            $status = $this->actingAs($outsider)->get($url)->status();
            $this->assertContains($status, [403, 404], "Outsider got {$status} on {$url}");
        }
    }
}

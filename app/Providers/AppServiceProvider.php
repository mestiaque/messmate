<?php

namespace App\Providers;

use App\Services\Access\MessAccessService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: flushed between requests and between queued jobs.
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(MessAccessService::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
    }
}

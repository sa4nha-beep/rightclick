<?php

namespace App\Providers;

use App\Infrastructure\Persistence\Support\BranchContext;
use App\Infrastructure\Persistence\Support\MigrationMacros;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: Laravel mengosongkan instance ini di antara permintaan HTTP
        // dan di antara job queue, sehingga worker yang sama tidak membawa
        // cabang dari job sebelumnya (App\Infrastructure\Persistence\Support\BranchContext).
        $this->app->scoped(BranchContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        MigrationMacros::register();
    }
}

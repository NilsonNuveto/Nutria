<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! config('session.driver')) {
            config([
                'session.driver' => 'database',
            ]);
        }

        if (! config('cache.default')) {
            config([
                'cache.default' => 'database',
            ]);
        }

        if (! config('queue.default')) {
            config([
                'queue.default' => 'sync',
            ]);
        }

        if (! config('filesystems.default')) {
            config([
                'filesystems.default' => 'local',
            ]);
        }

        if (! config('database.default')) {
            config([
                'database.default' => 'pgsql',
            ]);
        }
    }

    public function boot(): void
    {
        //
    }
}
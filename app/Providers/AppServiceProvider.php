<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('testing')) {
            return;
        }

        $defaultConn = config('database.default', env('DB_CONNECTION'));
        if ($defaultConn === 'mysql' || env('DB_CONNECTION') === 'mysql') {
            $this->app->booted(function () {
                config([
                    'database.default' => 'mysql',
                    'queue.connections.database.connection' => 'mysql',
                    'queue.failed.database' => 'mysql',
                    'queue.batching.database' => 'mysql',
                ]);
            });
        }
    }
}

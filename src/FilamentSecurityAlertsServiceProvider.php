<?php

declare(strict_types=1);

namespace Ruvelo\FilamentSecurityAlerts;

use Illuminate\Support\ServiceProvider;

class FilamentSecurityAlertsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-security-alerts');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/filament-security-alerts'),
            ], 'filament-security-alerts-views');
        }
    }
}

<?php

namespace Tintaungkhant\TrafficMonitor;

use Illuminate\Support\ServiceProvider;

class TrafficMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}

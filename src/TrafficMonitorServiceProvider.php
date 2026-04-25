<?php

namespace Tintaungkhant\TrafficMonitor;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Tintaungkhant\TrafficMonitor\Listeners\LogIncomingHttp;
use Tintaungkhant\TrafficMonitor\Listeners\LogOutgoingHttp;

class TrafficMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/traffic-monitor.php', 'traffic-monitor');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->publishes([
            __DIR__.'/../config/traffic-monitor.php' => config_path('traffic-monitor.php'),
        ], 'traffic-monitor-config');

        Event::listen(ResponseReceived::class, [LogOutgoingHttp::class, 'handleResponse']);
        Event::listen(ConnectionFailed::class, [LogOutgoingHttp::class, 'handleFailure']);

        Event::listen(RequestHandled::class, [LogIncomingHttp::class, 'handle']);
    }
}

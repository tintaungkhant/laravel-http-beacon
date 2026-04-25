<?php

namespace Tintaungkhant\TrafficMonitor;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Tintaungkhant\TrafficMonitor\Listeners\LogIncomingHttp;
use Tintaungkhant\TrafficMonitor\Listeners\LogOutgoingHttp;

class TrafficMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/traffic-monitor.php', 'traffic-monitor');

        $this->app->singleton(RequestCollector::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/traffic-monitor.php' => config_path('traffic-monitor.php'),
        ], 'traffic-monitor-config');

        Event::listen(ResponseReceived::class, [LogOutgoingHttp::class, 'handleResponse']);
        Event::listen(ConnectionFailed::class, [LogOutgoingHttp::class, 'handleFailure']);

        Event::listen(RequestHandled::class, [LogIncomingHttp::class, 'handle']);

        if (! $this->app->runningInConsole()) {
            $collector = $this->app->make(RequestCollector::class);

            Event::listen(QueryExecuted::class, [$collector, 'recordQuery']);
            Event::listen('eloquent.*', [$collector, 'recordModel']);
            Event::listen(JobQueued::class, [$collector, 'recordJob']);
            Event::listen(JobProcessing::class, [$collector, 'pause']);
            Event::listen(JobProcessed::class, [$collector, 'resume']);
            Event::listen(JobFailed::class, [$collector, 'resume']);
        }
    }
}

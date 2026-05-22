<?php

namespace HttpBeacon\Tests;

use HttpBeacon\BeaconServiceProvider;
use HttpBeacon\Models\IncomingRequest;
use HttpBeacon\Models\OutgoingRequest;
use HttpBeacon\Models\SharedLink;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Routes default to the 'web' middleware group, which includes CSRF.
        // Tests don't carry a session token, so disable that single check.
        $this->withoutMiddleware([VerifyCsrfToken::class]);
    }

    protected function getPackageProviders($app): array
    {
        return [BeaconServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('beacon.storage.connection', 'testing');
        $app['config']->set('beacon.enabled', true);
        $app['config']->set('beacon.sampling_rate', 1.0);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function makeIncoming(array $overrides = []): IncomingRequest
    {
        return IncomingRequest::create(array_merge([
            'hostname' => 'example.test',
            'method' => 'GET',
            'middlewares' => [],
            'path' => '/foo',
            'status' => 200,
            'duration_ms' => 50,
            'memory_mb' => 1.0,
            'payload' => [],
            'request_headers' => [],
            'response' => null,
            'response_headers' => [],
            'query_count' => 0,
            'created_at' => now(),
        ], $overrides));
    }

    protected function makeOutgoing(array $overrides = []): OutgoingRequest
    {
        return OutgoingRequest::create(array_merge([
            'hostname' => 'api.example.com',
            'method' => 'GET',
            'uri' => 'https://api.example.com/foo',
            'status' => 200,
            'duration_ms' => 100,
            'payload' => [],
            'request_headers' => [],
            'response' => null,
            'response_headers' => [],
            'failed' => false,
            'created_at' => now(),
        ], $overrides));
    }

    protected function makeShare(array $overrides = []): SharedLink
    {
        return SharedLink::create(array_merge([
            'token' => Str::random(48),
            'request_type' => 'incoming',
            'request_id' => 1,
            'password' => null,
            'expires_at' => null,
            'revoked_at' => null,
            'view_count' => 0,
            'last_viewed_at' => null,
            'created_at' => now(),
        ], $overrides));
    }
}

<?php

namespace HttpBeacon\Tests\Feature;

use HttpBeacon\Beacon;
use HttpBeacon\Models\IncomingRequest;
use HttpBeacon\Models\QueryRecord;
use HttpBeacon\RequestCollector;
use HttpBeacon\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogIncomingHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_handled_request(): void
    {
        $request = Request::create('/orders', 'POST', ['sku' => 'abc']);
        $response = new Response('ok', 201);

        event(new RequestHandled($request, $response));
        $this->app->terminate();

        $this->assertSame(1, IncomingRequest::query()->count());
        $row = IncomingRequest::query()->first();
        $this->assertSame('POST', $row->method);
        $this->assertSame('/orders', $row->path);
        $this->assertSame(201, $row->status);
    }

    public function test_skips_when_recording_paused(): void
    {
        Beacon::pause();

        event(new RequestHandled(Request::create('/foo'), new Response('ok', 200)));
        $this->app->terminate();

        $this->assertSame(0, IncomingRequest::query()->count());
    }

    public function test_only_paths_and_ignore_paths_are_honored(): void
    {
        config()->set('beacon.incoming.only_paths', ['api/*']);

        event(new RequestHandled(Request::create('/web/home'), new Response('ok', 200)));
        event(new RequestHandled(Request::create('/api/users'), new Response('ok', 200)));
        $this->app->terminate();

        $this->assertSame(1, IncomingRequest::query()->count());
        $this->assertSame('/api/users', IncomingRequest::query()->value('path'));
    }

    public function test_collected_queries_are_persisted_with_request(): void
    {
        $collector = $this->app->make(RequestCollector::class);
        $collector->recordQuery(new QueryExecuted(
            'select 1',
            [],
            1.5,
            $this->app['db']->connection('testing'),
        ));

        event(new RequestHandled(Request::create('/foo'), new Response('ok', 200)));
        $this->app->terminate();

        $this->assertSame(1, IncomingRequest::query()->count());
        $this->assertSame(1, QueryRecord::query()->count());
        $this->assertSame('select 1', QueryRecord::query()->value('sql'));
    }
}

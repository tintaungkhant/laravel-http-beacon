<?php

namespace HttpBeacon\Tests\Feature;

use HttpBeacon\Models\IncomingRequest;
use HttpBeacon\Models\QueryRecord;
use HttpBeacon\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IncomingRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_latest_first_with_keyset_pagination(): void
    {
        $a = $this->makeIncoming(['path' => '/a']);
        $b = $this->makeIncoming(['path' => '/b']);
        $c = $this->makeIncoming(['path' => '/c']);

        $first = $this->getJson('/beacon/api/incoming-requests');
        $first->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame([$c->id, $b->id, $a->id], array_column($first->json('data'), 'id'));

        $page2 = $this->getJson("/beacon/api/incoming-requests?before_id={$b->id}");
        $page2->assertJsonCount(1, 'data');
        $this->assertSame($a->id, $page2->json('data.0.id'));
    }

    public function test_filters_apply_independently(): void
    {
        $this->makeIncoming(['method' => 'POST', 'path' => '/users/create', 'status' => 201]);
        $this->makeIncoming(['method' => 'GET', 'path' => '/users', 'status' => 200]);
        $this->makeIncoming(['method' => 'GET', 'path' => '/items', 'status' => 500]);
        $this->makeIncoming(['method' => 'GET', 'path' => '/items', 'status' => 200, 'created_at' => now()->subDays(2)]);

        $this->getJson('/beacon/api/incoming-requests?search=users')->assertJsonCount(2, 'data');
        $this->getJson('/beacon/api/incoming-requests?method=POST')->assertJsonCount(1, 'data');
        $this->getJson('/beacon/api/incoming-requests?status=5xx')->assertJsonCount(1, 'data');

        $from = urlencode(now()->subDay()->toIso8601String());
        $this->getJson("/beacon/api/incoming-requests?from={$from}")->assertJsonCount(3, 'data');
    }

    public function test_show_returns_request_with_relations(): void
    {
        $incoming = $this->makeIncoming();
        QueryRecord::create([
            'request_id' => $incoming->id,
            'connection' => 'testing',
            'type' => 'SELECT',
            'sql' => 'select 1',
            'sql_with_bindings' => 'select 1',
            'time_ms' => 1.5,
            'caller' => 'App\\Foo@bar:7',
            'created_at' => now(),
        ]);

        $response = $this->getJson("/beacon/api/incoming-requests/{$incoming->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.queries'));
        $this->assertSame('select 1', $response->json('data.queries.0.sql'));
        $this->assertSame('App\\Foo@bar:7', $response->json('data.queries.0.caller'));
    }

    public function test_destroy_clears_all_rows(): void
    {
        $this->makeIncoming();
        $this->makeIncoming();

        $this->deleteJson('/beacon/api/incoming-requests')->assertOk();

        $this->assertSame(0, IncomingRequest::query()->count());
    }
}

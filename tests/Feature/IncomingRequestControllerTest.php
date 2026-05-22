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

    public function test_sort_by_id_ascending_uses_forward_keyset(): void
    {
        $a = $this->makeIncoming();
        $b = $this->makeIncoming();
        $c = $this->makeIncoming();

        $res = $this->getJson('/beacon/api/incoming-requests?sort=id_asc');
        $res->assertOk();
        $this->assertSame([$a->id, $b->id, $c->id], array_column($res->json('data'), 'id'));

        $page2 = $this->getJson("/beacon/api/incoming-requests?sort=id_asc&before_id={$a->id}");
        $this->assertSame([$b->id, $c->id], array_column($page2->json('data'), 'id'));
    }

    public function test_sort_by_duration_uses_offset_pagination(): void
    {
        $slow = $this->makeIncoming(['duration_ms' => 900]);
        $mid = $this->makeIncoming(['duration_ms' => 300]);
        $fast = $this->makeIncoming(['duration_ms' => 10]);

        $desc = $this->getJson('/beacon/api/incoming-requests?sort=duration_desc');
        $desc->assertOk();
        $this->assertSame([$slow->id, $mid->id, $fast->id], array_column($desc->json('data'), 'id'));

        $asc = $this->getJson('/beacon/api/incoming-requests?sort=duration_asc');
        $this->assertSame([$fast->id, $mid->id, $slow->id], array_column($asc->json('data'), 'id'));

        $offset = $this->getJson('/beacon/api/incoming-requests?sort=duration_desc&offset=1');
        $this->assertSame([$mid->id, $fast->id], array_column($offset->json('data'), 'id'));
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

    public function test_filters_by_duration(): void
    {
        $fast = $this->makeIncoming(['duration_ms' => 10]);
        $mid = $this->makeIncoming(['duration_ms' => 200]);
        $slow = $this->makeIncoming(['duration_ms' => 900]);

        $gte = $this->getJson('/beacon/api/incoming-requests?duration=200&duration_op=gte');
        $gte->assertOk();
        $this->assertEqualsCanonicalizing([$mid->id, $slow->id], array_column($gte->json('data'), 'id'));

        $lte = $this->getJson('/beacon/api/incoming-requests?duration=200&duration_op=lte');
        $this->assertEqualsCanonicalizing([$fast->id, $mid->id], array_column($lte->json('data'), 'id'));

        $invalid = $this->getJson('/beacon/api/incoming-requests?duration=abc');
        $invalid->assertJsonCount(3, 'data');
    }

    public function test_search_supports_star_wildcard(): void
    {
        $chatSend = $this->makeIncoming(['path' => '/api/chat/send']);
        $chatList = $this->makeIncoming(['path' => '/v2/chat/list']);
        $users = $this->makeIncoming(['path' => '/api/users']);

        $wildcard = $this->getJson('/beacon/api/incoming-requests?search='.urlencode('*/chat/*'));
        $wildcard->assertOk();
        $this->assertEqualsCanonicalizing(
            [$chatSend->id, $chatList->id],
            array_column($wildcard->json('data'), 'id'),
        );

        $prefix = $this->getJson('/beacon/api/incoming-requests?search='.urlencode('/api/*'));
        $this->assertEqualsCanonicalizing(
            [$chatSend->id, $users->id],
            array_column($prefix->json('data'), 'id'),
        );

        $plain = $this->getJson('/beacon/api/incoming-requests?search=chat');
        $plain->assertJsonCount(2, 'data');

        // A wildcard term stays a substring match: "*/chat" still matches
        // "/api/chat/send" even though that path does not end with "/chat".
        $unanchored = $this->getJson('/beacon/api/incoming-requests?search='.urlencode('*/chat'));
        $this->assertEqualsCanonicalizing(
            [$chatSend->id, $chatList->id],
            array_column($unanchored->json('data'), 'id'),
        );
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

    public function test_after_id_returns_only_rows_newer_than_the_cursor(): void
    {
        $this->makeIncoming();
        $b = $this->makeIncoming();
        $c = $this->makeIncoming();

        $response = $this->getJson('/beacon/api/incoming-requests?after_id='.$b->id);

        $response->assertOk();
        $this->assertSame([$c->id], array_column($response->json('data'), 'id'));
    }

    public function test_destroy_clears_all_rows(): void
    {
        $this->makeIncoming();
        $this->makeIncoming();

        $this->deleteJson('/beacon/api/incoming-requests')->assertOk();

        $this->assertSame(0, IncomingRequest::query()->count());
    }
}

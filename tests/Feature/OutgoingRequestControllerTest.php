<?php

namespace HttpBeacon\Tests\Feature;

use HttpBeacon\Models\OutgoingRequest;
use HttpBeacon\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OutgoingRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_search_method_and_status(): void
    {
        $this->makeOutgoing(['hostname' => 'api.stripe.com', 'uri' => 'https://api.stripe.com/v1/charges', 'method' => 'POST', 'status' => 201]);
        $this->makeOutgoing(['hostname' => 'api.example.com', 'uri' => 'https://api.example.com/users', 'method' => 'GET', 'status' => 200]);
        $this->makeOutgoing(['hostname' => 'api.example.com', 'uri' => 'https://api.example.com/items', 'method' => 'GET', 'status' => 500]);

        $this->getJson('/beacon/api/outgoing-requests?search=stripe')->assertJsonCount(1, 'data');
        $this->getJson('/beacon/api/outgoing-requests?method=POST')->assertJsonCount(1, 'data');
        $this->getJson('/beacon/api/outgoing-requests?status=5xx')->assertJsonCount(1, 'data');
    }

    public function test_failed_filter_overrides_status_filter(): void
    {
        $this->makeOutgoing(['status' => 200, 'failed' => false]);
        $this->makeOutgoing(['status' => null, 'duration_ms' => null, 'failed' => true]);
        $this->makeOutgoing(['status' => null, 'duration_ms' => null, 'failed' => true]);

        $response = $this->getJson('/beacon/api/outgoing-requests?failed=1&status=2xx');

        $response->assertOk()->assertJsonCount(2, 'data');
        foreach ($response->json('data') as $row) {
            $this->assertTrue($row['failed']);
        }
    }

    public function test_filters_by_duration(): void
    {
        $fast = $this->makeOutgoing(['duration_ms' => 10]);
        $mid = $this->makeOutgoing(['duration_ms' => 200]);
        $slow = $this->makeOutgoing(['duration_ms' => 900]);

        $gte = $this->getJson('/beacon/api/outgoing-requests?duration=200&duration_op=gte');
        $gte->assertOk();
        $this->assertEqualsCanonicalizing([$mid->id, $slow->id], array_column($gte->json('data'), 'id'));

        $lte = $this->getJson('/beacon/api/outgoing-requests?duration=200&duration_op=lte');
        $this->assertEqualsCanonicalizing([$fast->id, $mid->id], array_column($lte->json('data'), 'id'));

        $invalid = $this->getJson('/beacon/api/outgoing-requests?duration=abc');
        $invalid->assertJsonCount(3, 'data');
    }

    public function test_sort_by_id_ascending_uses_forward_keyset(): void
    {
        $a = $this->makeOutgoing();
        $b = $this->makeOutgoing();
        $c = $this->makeOutgoing();

        $res = $this->getJson('/beacon/api/outgoing-requests?sort=id_asc');
        $res->assertOk();
        $this->assertSame([$a->id, $b->id, $c->id], array_column($res->json('data'), 'id'));

        $page2 = $this->getJson("/beacon/api/outgoing-requests?sort=id_asc&before_id={$a->id}");
        $this->assertSame([$b->id, $c->id], array_column($page2->json('data'), 'id'));
    }

    public function test_sort_by_duration_uses_offset_pagination(): void
    {
        $slow = $this->makeOutgoing(['duration_ms' => 900]);
        $mid = $this->makeOutgoing(['duration_ms' => 300]);
        $fast = $this->makeOutgoing(['duration_ms' => 10]);

        $desc = $this->getJson('/beacon/api/outgoing-requests?sort=duration_desc');
        $desc->assertOk();
        $this->assertSame([$slow->id, $mid->id, $fast->id], array_column($desc->json('data'), 'id'));

        $asc = $this->getJson('/beacon/api/outgoing-requests?sort=duration_asc');
        $this->assertSame([$fast->id, $mid->id, $slow->id], array_column($asc->json('data'), 'id'));

        $offset = $this->getJson('/beacon/api/outgoing-requests?sort=duration_desc&offset=1');
        $this->assertSame([$mid->id, $fast->id], array_column($offset->json('data'), 'id'));
    }

    public function test_destroy_clears_all_rows(): void
    {
        $this->makeOutgoing();
        $this->makeOutgoing();

        $this->deleteJson('/beacon/api/outgoing-requests')->assertOk();

        $this->assertSame(0, OutgoingRequest::query()->count());
    }
}

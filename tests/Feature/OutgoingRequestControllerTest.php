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

    public function test_destroy_clears_all_rows(): void
    {
        $this->makeOutgoing();
        $this->makeOutgoing();

        $this->deleteJson('/beacon/api/outgoing-requests')->assertOk();

        $this->assertSame(0, OutgoingRequest::query()->count());
    }
}

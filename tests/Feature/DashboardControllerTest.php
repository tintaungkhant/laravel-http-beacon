<?php

namespace HttpBeacon\Tests\Feature;

use HttpBeacon\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_aggregations_within_24h_window(): void
    {
        // Inside the 24h window
        $this->makeIncoming(['status' => 200, 'duration_ms' => 50]);
        $this->makeIncoming(['status' => 404, 'duration_ms' => 100]);
        $this->makeIncoming(['status' => 500, 'duration_ms' => 1000]);

        // Outside the window — must be excluded
        $this->makeIncoming(['status' => 200, 'duration_ms' => 9999, 'created_at' => now()->subHours(25)]);

        $this->makeOutgoing(['status' => 200, 'duration_ms' => 200]);
        $this->makeOutgoing(['status' => null, 'duration_ms' => null, 'failed' => true]);

        $response = $this->getJson('/beacon/api/dashboard');

        $response->assertOk();
        $this->assertNotNull($response->json('data.since'));

        $this->assertSame(3, $response->json('data.incoming.total'));
        $this->assertSame(1, $response->json('data.incoming.status_buckets.2xx'));
        $this->assertSame(0, $response->json('data.incoming.status_buckets.3xx'));
        $this->assertSame(1, $response->json('data.incoming.status_buckets.4xx'));
        $this->assertSame(1, $response->json('data.incoming.status_buckets.5xx'));

        // Slowest is the 1000ms server-error row.
        $this->assertSame(1000, $response->json('data.incoming.slowest.0.duration_ms'));

        $this->assertSame(2, $response->json('data.outgoing.total'));
        $this->assertSame(1, $response->json('data.outgoing.failed'));

        // Outgoing status breakdown: one 2xx, the failed row has no status.
        $this->assertSame(1, $response->json('data.outgoing.status_buckets.2xx'));
        $this->assertSame(0, $response->json('data.outgoing.status_buckets.5xx'));
    }
}

<?php

namespace HttpBeacon\Tests\Feature;

use HttpBeacon\Models\IncomingRequest;
use HttpBeacon\Models\OutgoingRequest;
use HttpBeacon\Models\SharedLink;
use HttpBeacon\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ClearCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_clear_deletes_traffic_and_shared_links(): void
    {
        $incoming = $this->makeIncoming();
        $this->makeOutgoing();
        $this->makeShare(['request_type' => 'incoming', 'request_id' => $incoming->id]);

        $this->artisan('beacon:clear')->assertSuccessful();

        $this->assertSame(0, IncomingRequest::query()->count());
        $this->assertSame(0, OutgoingRequest::query()->count());
        $this->assertSame(0, SharedLink::query()->count());
    }
}

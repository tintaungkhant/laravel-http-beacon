<?php

namespace HttpBeacon\Tests\Feature;

use HttpBeacon\Models\IncomingRequest;
use HttpBeacon\Models\OutgoingRequest;
use HttpBeacon\Models\SharedLink;
use HttpBeacon\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PruneCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_old_rows_keeps_recent(): void
    {
        $oldIncoming = $this->makeIncoming(['created_at' => now()->subDays(2)]);
        $recentIncoming = $this->makeIncoming(['created_at' => now()->subHours(2)]);

        $oldOutgoing = $this->makeOutgoing(['created_at' => now()->subDays(2)]);
        $recentOutgoing = $this->makeOutgoing(['created_at' => now()->subHours(2)]);

        $this->artisan('beacon:prune', ['--hours' => 24])->assertSuccessful();

        $this->assertNull(IncomingRequest::query()->find($oldIncoming->id));
        $this->assertNotNull(IncomingRequest::query()->find($recentIncoming->id));
        $this->assertNull(OutgoingRequest::query()->find($oldOutgoing->id));
        $this->assertNotNull(OutgoingRequest::query()->find($recentOutgoing->id));
    }

    public function test_dry_run_counts_without_deleting(): void
    {
        $this->makeIncoming(['created_at' => now()->subDays(2)]);
        $this->makeIncoming(['created_at' => now()->subHours(2)]);

        $this->artisan('beacon:prune', ['--hours' => 24, '--dry-run' => true])
            ->expectsOutputToContain('Would prune 1 incoming')
            ->assertSuccessful();

        $this->assertSame(2, IncomingRequest::query()->count());
    }

    public function test_prune_removes_revoked_and_expired_shared_links_keeps_active(): void
    {
        $incoming = $this->makeIncoming();

        $active = $this->makeShare(['request_id' => $incoming->id]);
        $revoked = $this->makeShare(['request_id' => $incoming->id, 'revoked_at' => now()]);
        $expired = $this->makeShare(['request_id' => $incoming->id, 'expires_at' => now()->subHour()]);

        $this->artisan('beacon:prune')->assertSuccessful();

        $this->assertNotNull(SharedLink::query()->find($active->id));
        $this->assertNull(SharedLink::query()->find($revoked->id));
        $this->assertNull(SharedLink::query()->find($expired->id));
    }
}

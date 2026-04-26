<?php

namespace HttpBeacon\Tests\Unit;

use HttpBeacon\Models\IncomingRequest;
use HttpBeacon\Support\RowLimit;
use HttpBeacon\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class RowLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_nothing_when_limit_is_null_or_zero(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeIncoming();
        }

        $latest = (int) IncomingRequest::query()->max('id');

        // Bump the inserted id to a multiple of 100 so the throttle gate would otherwise fire.
        RowLimit::enforce('beacon_incoming_requests', null, 100);
        $this->assertSame(5, IncomingRequest::query()->count());

        RowLimit::enforce('beacon_incoming_requests', 0, 100);
        $this->assertSame(5, IncomingRequest::query()->count());

        // Sanity: limit set but throttle not triggered (id 4) leaves rows alone.
        RowLimit::enforce('beacon_incoming_requests', 1, $latest);
        $this->assertSame(5, IncomingRequest::query()->count());
    }

    public function test_trims_oldest_rows_when_over_limit(): void
    {
        // Seed 100 rows so the latest id (100) hits the throttle gate.
        $oldestId = null;
        for ($i = 0; $i < 100; $i++) {
            $row = $this->makeIncoming();
            $oldestId ??= $row->id;
        }

        RowLimit::enforce('beacon_incoming_requests', 50, 100);

        $this->assertSame(50, IncomingRequest::query()->count());
        // The oldest 50 rows should be gone.
        $this->assertNull(IncomingRequest::query()->find($oldestId));
        // And the newest 50 should remain.
        $this->assertNotNull(IncomingRequest::query()->find(100));
    }

    public function test_lock_holder_skips_concurrent_trim(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->makeIncoming();
        }

        // Pretend another process is mid-trim — grab the lock and don't release it.
        $lock = Cache::lock('beacon:trim:beacon_incoming_requests', 5);
        $this->assertTrue($lock->get());

        try {
            RowLimit::enforce('beacon_incoming_requests', 50, 100);
            // Trim should have been skipped — table is untouched.
            $this->assertSame(100, IncomingRequest::query()->count());
        } finally {
            $lock->release();
        }
    }
}

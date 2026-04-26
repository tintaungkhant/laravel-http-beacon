<?php

namespace HttpBeacon\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class RowLimit
{
    /**
     * Inserts whose primary key is a multiple of this number trigger a trim
     * attempt. Throttles the COUNT(*) cost; over-shoot is bounded by this value.
     */
    private const CHECK_INTERVAL = 100;

    /** Lock TTL — long enough for a normal trim, short enough to recover from a crash. */
    private const LOCK_TTL = 5;

    /**
     * Trim the oldest rows from the given table down to {@param $limit}.
     *
     * Concurrency-safe: a `Cache::lock` per table dedupes simultaneous trim
     * attempts; if another process holds the lock, this call returns
     * immediately and lets that process do the work.
     */
    public static function enforce(string $table, ?int $limit, int $insertedId): void
    {
        if (! $limit || $limit <= 0) {
            return;
        }

        if ($insertedId % self::CHECK_INTERVAL !== 0) {
            return;
        }

        $lock = Cache::lock("beacon:trim:{$table}", self::LOCK_TTL);

        if (! $lock->get()) {
            return;
        }

        try {
            $db = DB::connection(config('beacon.storage.connection'));

            $excess = (int) $db->table($table)->count() - $limit;

            if ($excess <= 0) {
                return;
            }

            $db->table($table)
                ->orderBy('id')
                ->limit($excess)
                ->delete();
        } catch (Throwable $e) {
            report($e);
        } finally {
            $lock->release();
        }
    }
}

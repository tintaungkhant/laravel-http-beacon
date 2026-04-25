<?php

namespace HttpBeacon;

use Illuminate\Support\Facades\Cache;

class Beacon
{
    private const PAUSE_KEY = 'beacon:paused';

    public static function isRecording(): bool
    {
        return ! Cache::get(self::PAUSE_KEY, false);
    }

    public static function pause(): void
    {
        if (! Cache::get(self::PAUSE_KEY)) {
            Cache::put(self::PAUSE_KEY, true, now()->addDays(30));
        }
    }

    public static function resume(): void
    {
        Cache::forget(self::PAUSE_KEY);
    }
}

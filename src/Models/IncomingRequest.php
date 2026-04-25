<?php

namespace HttpBeacon\Models;

use Illuminate\Database\Eloquent\Model;

class IncomingRequest extends Model
{
    protected $table = 'beacon_incoming_requests';

    public $timestamps = false;

    protected $guarded = [];

    public function getConnectionName()
    {
        return config('beacon.storage.connection');
    }

    protected $casts = [
        'middlewares' => 'array',
        'payload' => 'array',
        'request_headers' => 'array',
        'response' => 'array',
        'response_headers' => 'array',
        'queries' => 'array',
        'models' => 'array',
        'jobs' => 'array',
        'memory_mb' => 'float',
        'duration_ms' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
    ];
}

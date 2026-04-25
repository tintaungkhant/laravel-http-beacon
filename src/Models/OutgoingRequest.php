<?php

namespace HttpBeacon\Models;

use Illuminate\Database\Eloquent\Model;

class OutgoingRequest extends Model
{
    protected $table = 'beacon_outgoing_requests';

    public $timestamps = false;

    protected $guarded = [];

    public function getConnectionName()
    {
        return config('beacon.storage.connection');
    }

    protected $casts = [
        'payload' => 'array',
        'request_headers' => 'array',
        'response' => 'array',
        'response_headers' => 'array',
        'failed' => 'boolean',
        'duration_ms' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
    ];
}

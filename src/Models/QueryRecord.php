<?php

namespace HttpBeacon\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryRecord extends Model
{
    protected $table = 'beacon_request_queries';

    public $timestamps = false;

    protected $guarded = [];

    public function getConnectionName()
    {
        return config('beacon.storage.connection');
    }

    protected $casts = [
        'request_id' => 'integer',
        'bindings' => 'array',
        'time_ms' => 'float',
        'created_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(IncomingRequest::class, 'request_id');
    }
}

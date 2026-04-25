<?php

namespace HttpBeacon\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'memory_mb' => 'float',
        'duration_ms' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
    ];

    public function modelTouches(): HasMany
    {
        return $this->hasMany(ModelTouch::class, 'request_id');
    }

    public function jobDispatches(): HasMany
    {
        return $this->hasMany(JobDispatch::class, 'request_id');
    }
}

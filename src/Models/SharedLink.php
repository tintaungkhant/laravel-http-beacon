<?php

namespace HttpBeacon\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $token
 * @property string $request_type
 * @property int $request_id
 * @property string|null $password
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property int $view_count
 * @property \Illuminate\Support\Carbon|null $last_viewed_at
 * @property \Illuminate\Support\Carbon $created_at
 */
class SharedLink extends Model
{
    protected $table = 'beacon_shared_links';

    public $timestamps = false;

    protected $guarded = [];

    public function getConnectionName()
    {
        return config('beacon.storage.connection');
    }

    protected $casts = [
        'request_id' => 'integer',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'created_at' => 'datetime',
        'view_count' => 'integer',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null && ! $this->isExpired();
    }

    public function status(): string
    {
        if ($this->revoked_at !== null) {
            return 'revoked';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        return 'active';
    }
}

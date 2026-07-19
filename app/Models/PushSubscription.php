<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** F20.2 — a browser that agreed to receive notifications. */
class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'p256dh',
        'auth',
        'user_agent',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The endpoint is the identity; this is what makes it indexable. */
    public static function hashFor(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}

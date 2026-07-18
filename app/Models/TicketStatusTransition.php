<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/** One row = one legal "from status → to status" move, editable from the admin. */
class TicketStatusTransition extends Model
{
    protected $fillable = ['from_status_id', 'to_status_id'];

    protected static function booted(): void
    {
        // Doesn't change ticket_statuses itself, but the transition GRAPH is
        // cached separately under TicketStatusDefinition — bust that one too,
        // or an admin's edit here would silently not take effect.
        $bust = fn () => Cache::forget(TicketStatusDefinition::CACHE_KEY . '.transitions');

        static::saved($bust);
        static::deleted($bust);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(TicketStatusDefinition::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(TicketStatusDefinition::class, 'to_status_id');
    }
}

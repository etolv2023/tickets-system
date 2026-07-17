<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Sequential ticket numbers with no gaps and no collisions under load (F03).
 *
 * MAX(number)+1 read outside a lock is the classic race: two requests read the
 * same max and both try to insert it. Instead the row that defines the current
 * maximum is locked for the length of the transaction, so a concurrent caller
 * waits rather than reading a stale value. The UNIQUE index on ticket_number is
 * the backstop if this is ever bypassed.
 *
 * Callers must already be inside a transaction — TicketService::create() is.
 */
class TicketNumberService
{
    public function next(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $prefix = "TK-{$year}-";

        // withTrashed: a soft-deleted ticket still owns its number, and reusing
        // it would break every reference to the old one.
        $last = Ticket::withTrashed()
            ->where('ticket_number', 'like', $prefix . '%')
            ->orderByDesc('ticket_number')
            ->lockForUpdate()
            ->value('ticket_number');

        $sequence = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}

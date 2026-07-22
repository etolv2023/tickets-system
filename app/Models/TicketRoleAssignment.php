<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who holds a given role on a given ticket — the generic counterpart of
 * assigned_frontend_id/assigned_backend_id/tester_id/devops_id for every role
 * an admin has opted into the assignment panel (Role::assignable_on_tickets).
 */
class TicketRoleAssignment extends Model
{
    protected $fillable = ['ticket_id', 'role_id', 'user_id'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

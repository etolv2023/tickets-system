<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F15 heal (2026-07-21): before approve() advanced the status, approving a
 * feature/module left it at status='pending_approval' while approval_status
 * became 'approved'. Those tickets read as still-pending (the badge shows
 * «بانتظار الموافقة»), are gone from the approvals queue, and were never
 * assigned — an invisible limbo an admin can't act on.
 *
 * This moves every ticket in exactly that state onto 'new' — approved and ready
 * to assign, which is what approve() now does going forward. It touches only the
 * inconsistent (pending_approval + approved) rows; anything genuinely awaiting a
 * decision (approval_status = 'pending') is left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tickets')
            ->where('status', 'pending_approval')
            ->where('approval_status', 'approved')
            ->update(['status' => 'new']);
    }

    public function down(): void
    {
        // One-way heal: 'new' + approved is a legitimate state on its own (a
        // normal ticket that happened to be approved), so there is nothing safe
        // to roll back to without guessing.
    }
};

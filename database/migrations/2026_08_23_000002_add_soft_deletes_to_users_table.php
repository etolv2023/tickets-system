<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retiring a person without erasing what they did.
 *
 * Until now users could not be deleted at all — UserPolicy said so in as many
 * words, and the database agreed: eleven foreign keys point at `users` with
 * RESTRICT (tickets.created_by, ticket_comments.user_id, ticket_work_logs,
 * time_entries, point_transactions, ratings.rated_by, and more), so a real
 * DELETE of anybody who had ever done anything simply failed. That was the
 * right instinct — a leaver's name has to stay on the ticket they opened and on
 * the points they earned — but it left no way to take somebody off the system.
 *
 * A timestamp is enough. Nothing is removed, so the RESTRICT keys are never
 * tested and the seven CASCADE keys (ratings received, watchers, leaves,
 * permission overrides) never fire — those would have quietly destroyed history
 * of their own.
 *
 * Deleting also sets is_active = false, and that is what does the actual work:
 * every guard in the system already turns on that column — login refuses it,
 * hasPermission() returns false, User::active() filters it, and the assignment
 * pickers are built from User::active(). So a deleted user disappears from all
 * live workflow without a single one of those call sites being touched.
 *
 * See App\Models\User for why the usual SoftDeletes global scope is deliberately
 * NOT applied: twenty-five belongsTo(User::class) relations render a name in
 * history, and the scope would blank every one of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

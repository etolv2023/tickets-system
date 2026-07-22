<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-07-24) Phase 2 of role-based distribution.
 *
 * The backfill (000003) already stamped every work log's role_id from its
 * WorkSide. Now `side` is retired: a work log belongs to a role, one per
 * logs_work role on the ticket. The uniqueness that used to be "one log per
 * side" becomes "one log per role".
 *
 * By the time this runs, role_id is populated for every existing row (000003),
 * so making it the unique key loses nothing. doctrine/dbal isn't installed, so
 * the enum drop goes through Schema::dropColumn (a plain DROP, no ->change()).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Any row that reached here without a role (a side the backfill couldn't
        // map because its role was deleted) can't satisfy the new key — there is
        // nothing sensible to keep it as. None exist on a clean backfill; this is
        // belt-and-braces for a hand-edited database.
        DB::table('ticket_work_logs')->whereNull('role_id')->delete();

        // Add the new "one per role" key FIRST: the ticket_id foreign key is
        // backed by the (ticket_id, side) unique index, so that index can't be
        // dropped until another index leads with ticket_id. The new unique does.
        Schema::table('ticket_work_logs', function (Blueprint $table) {
            $table->unique(['ticket_id', 'role_id']);
        });

        Schema::table('ticket_work_logs', function (Blueprint $table) {
            $table->dropUnique(['ticket_id', 'side']);
            $table->dropColumn('side');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_work_logs', function (Blueprint $table) {
            $table->enum('side', ['frontend', 'backend'])->nullable()->after('user_id');
        });

        // Restore `side` from the role key for the two roles that map to one.
        foreach (['frontend', 'backend'] as $key) {
            $roleId = DB::table('roles')->where('key', $key)->value('id');
            if ($roleId !== null) {
                DB::table('ticket_work_logs')->where('role_id', $roleId)->update(['side' => $key]);
            }
        }

        // Rows for roles with no WorkSide equivalent can't be represented by the
        // restored enum; drop them rather than write an invalid value.
        DB::table('ticket_work_logs')->whereNull('side')->delete();

        // Restore the (ticket_id, side) key first so it backs the ticket_id
        // foreign key, then drop the (ticket_id, role_id) key — same ordering
        // constraint as up(), in reverse.
        Schema::table('ticket_work_logs', function (Blueprint $table) {
            $table->unique(['ticket_id', 'side']);
        });

        Schema::table('ticket_work_logs', function (Blueprint $table) {
            $table->dropUnique(['ticket_id', 'role_id']);
        });
    }
};

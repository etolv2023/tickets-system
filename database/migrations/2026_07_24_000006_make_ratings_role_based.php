<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-07-24) Role-based ratings (F17), following the distribution rework.
 *
 * A rating used to name a fixed `side`. Now you rate the person as the role they
 * hold on the ticket, so a rating carries role_id. Existing ratings are mapped
 * to the matching role; the legacy 'support' rating (which named the ticket's
 * creator, never a role assignment) keeps its side and a null role_id so old
 * data still reads.
 *
 * doctrine/dbal isn't installed, so `side` is made nullable with raw SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('ratee_id')->constrained()->cascadeOnDelete();
        });

        // Map each rated side to its role before the side key stops driving things.
        foreach (['frontend', 'backend', 'tester'] as $key) {
            $roleId = DB::table('roles')->where('key', $key)->value('id');
            if ($roleId !== null) {
                DB::table('ratings')->where('side', $key)->update(['role_id' => $roleId]);
            }
        }

        // The old key was per-side; the new one is per-role. Legacy 'support'
        // rows keep role_id null and are unaffected by the role-based key.
        //
        // Add the new unique first: the ticket_id foreign key is backed by the
        // old (ticket_id, ratee_id, side) index, which can't be dropped until
        // another index leads with ticket_id.
        Schema::table('ratings', function (Blueprint $table) {
            $table->unique(['ticket_id', 'ratee_id', 'role_id']);
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->dropUnique(['ticket_id', 'ratee_id', 'side']);
        });

        DB::statement("ALTER TABLE ratings MODIFY side ENUM('support','frontend','backend','tester') NULL");
    }

    public function down(): void
    {
        // Restore side from role for the rows the up() mapped, so the NOT NULL
        // enum can hold again.
        foreach (['frontend', 'backend', 'tester'] as $key) {
            $roleId = DB::table('roles')->where('key', $key)->value('id');
            if ($roleId !== null) {
                DB::table('ratings')->where('role_id', $roleId)->update(['side' => $key]);
            }
        }

        // Any rating for a role with no side equivalent can't be represented.
        DB::table('ratings')->whereNull('side')->delete();

        // Restore the (ticket_id, ratee_id, side) key first so it backs the
        // ticket_id foreign key, then drop the role-based key and role_id.
        Schema::table('ratings', function (Blueprint $table) {
            $table->unique(['ticket_id', 'ratee_id', 'side']);
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->dropUnique(['ticket_id', 'ratee_id', 'role_id']);
            $table->dropConstrainedForeignId('role_id');
        });

        DB::statement("ALTER TABLE ratings MODIFY side ENUM('support','frontend','backend','tester') NOT NULL");
    }
};

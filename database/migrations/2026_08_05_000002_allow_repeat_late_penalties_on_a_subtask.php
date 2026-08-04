<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F18 — UNIQUE(subtask_id) becomes UNIQUE(subtask_id, charge_key).
 *
 * The old index said "a subtask is charged exactly once, ever". That was the
 * right guard while the only charge was the award. It stopped being right the
 * moment «تراكم التأخير» arrived: with accumulation on, a subtask that stays
 * overdue is docked again every morning, so a subtask can legitimately carry
 * many rows — one award and N penalties.
 *
 * charge_key is what the DB now counts as "the same charge":
 *
 *   'award'                → the one and only payout. Still exactly one per
 *                            subtask, still enforced by the database and not by
 *                            code, because that is the row that becomes money.
 *   'penalty:YYYY-MM-DD'   → one deduction per subtask per DAY.
 *
 * The date in the penalty key is doing real work: it is what makes the 6 AM
 * sweep idempotent. Run the command twice on the same morning — or run it by
 * hand after it already ran — and the second insert hits this index and is
 * refused, instead of docking somebody twice for one day.
 *
 * "Only once ever unless accumulation is on" is NOT expressed here on purpose.
 * It is a business rule that an admin flips in the settings, and business rules
 * that change belong in code (LatePenaltyService); the index only enforces the
 * two things that are true regardless of any setting.
 *
 * A NOT NULL column with a default rather than a nullable one: MySQL's unique
 * indexes ignore NULLs, so a nullable charge_key would silently switch the
 * award guard off — the exact failure this index exists to prevent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->string('charge_key', 24)->default('award')->after('type');
        });

        // Existing rows predate penalties: every subtask-bound row is an award.
        DB::table('point_transactions')->update(['charge_key' => 'award']);

        // Order matters: MySQL refuses to drop an index a foreign key relies on,
        // and UNIQUE(subtask_id) is the only index subtask_id leads. The FK goes
        // first, then the index it was leaning on, then the replacement — which
        // subtask_id still leads, so the FK can lean on that one when it comes
        // back. (Same dance as 2026_07_19_000006 did for ticket_id.)
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropForeign(['subtask_id']);
            $table->dropUnique(['subtask_id']);
            $table->unique(['subtask_id', 'charge_key']);
            $table->foreign('subtask_id')->references('id')->on('ticket_subtasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Reinstating UNIQUE(subtask_id) can only succeed if no subtask carries
        // more than one row. Drop the extra penalties first — they are the rows
        // that could not have existed under the old guard.
        DB::statement("
            DELETE p FROM point_transactions p
            JOIN point_transactions keep ON keep.subtask_id = p.subtask_id AND keep.id < p.id
            WHERE p.subtask_id IS NOT NULL
        ");

        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropForeign(['subtask_id']);
            $table->dropUnique(['subtask_id', 'charge_key']);
            $table->unique('subtask_id');
            $table->foreign('subtask_id')->references('id')->on('ticket_subtasks')->nullOnDelete();
            $table->dropColumn('charge_key');
        });
    }
};

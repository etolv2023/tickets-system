<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F18 — a third kind of ledger row: the late-delivery penalty.
 *
 * A subtask that carried a due date and was finished after it does not earn its
 * points; it loses them. PointEngineService writes one row with NEGATIVE points
 * instead of the positive one it would have written, and that row is a
 * 'penalty' — not an 'award' (nobody was paid) and not a 'correction' (nobody
 * typed it in). The distinction is not cosmetic: the reports read `type` to
 * decide what a row IS, and folding a penalty into either of the other two
 * makes the month's numbers lie about where they came from.
 *
 * Doctrine/DBAL isn't installed, so widening the enum goes through raw SQL
 * rather than Blueprint::change() — same as the nullable flip in
 * 2026_07_19_000006.
 *
 * The down() is safe as written because no penalty row can exist while the
 * older code is running; if one somehow does, the ALTER would refuse rather
 * than silently blank it, which is the correct failure for a money table.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE point_transactions
             MODIFY type ENUM('award', 'correction', 'penalty') NOT NULL DEFAULT 'award'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE point_transactions
             MODIFY type ENUM('award', 'correction') NOT NULL DEFAULT 'award'"
        );
    }
};

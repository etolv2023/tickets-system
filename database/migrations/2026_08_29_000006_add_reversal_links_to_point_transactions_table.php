<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-08-29) F18 — undoing a manual correction WITHOUT rewriting one.
 *
 * The ledger stays exactly as append-only as it was: nothing here loosens
 * PointTransaction::booted(), which still refuses every update and every
 * delete. "تعديل" and "حذف" on the corrections screen are labels for what the
 * user wants to happen; underneath, both write NEW rows.
 *
 *   حذف   → one reversing row: same person, same period, points negated,
 *            reverses_id pointing back at the original.
 *   تعديل → that same reversing row, plus a replacement carrying the corrected
 *            values and replaces_id pointing at the original.
 *
 * Neither touches the original row, which is why no exception to the immutable
 * ledger was needed: "this correction was cancelled" is not stored ON it, it is
 * DERIVED from the existence of a row pointing back at it.
 *
 * Both columns are UNIQUE, and that is the real guard rather than a nicety. A
 * double-submit or two admins on the screen at once would otherwise post two
 * reversals for one row and take the points away twice — from someone's pay.
 * The service checks first; the index is what makes the check unnecessary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            // "This row cancels that row." Set only on a reversing entry.
            $table->foreignId('reverses_id')->nullable()->after('reason')
                ->constrained('point_transactions')->restrictOnDelete();

            // "This row is the corrected version of that row." Set only on the
            // replacement half of an edit.
            $table->foreignId('replaces_id')->nullable()->after('reverses_id')
                ->constrained('point_transactions')->restrictOnDelete();

            // One reversal per row, ever. One replacement per row, ever.
            $table->unique('reverses_id');
            $table->unique('replaces_id');
        });
    }

    public function down(): void
    {
        Schema::table('point_transactions', function (Blueprint $table) {
            $table->dropUnique(['reverses_id']);
            $table->dropUnique(['replaces_id']);
            $table->dropForeign(['reverses_id']);
            $table->dropForeign(['replaces_id']);
            $table->dropColumn(['reverses_id', 'replaces_id']);
        });
    }
};

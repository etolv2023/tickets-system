<?php

use App\Models\TicketTypeDefinition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-08-02) The starting points a subtask carries now come from its
 * ticket's type instead of one flat constant.
 *
 * SubtaskService has carried a single DEFAULT_POINTS = 1.0 since 2026-07-23,
 * when the old side/scope/role matrix was deliberately deleted. That decision
 * stands — there is still no formula, and a subtask's points are still freely
 * editable afterwards. This only makes the *starting* number a property of the
 * type, so a فيتشر can open at a different weight from a بج without anyone
 * retyping it on every row.
 *
 * It lives on ticket_types (not settings) because the type list is already
 * admin-owned: adding a type invents its weight in the same screen, with no
 * code change. Same reasoning as needs_approval.
 *
 * Every type starts at 1.00 — exactly today's behaviour — except فيتشر, the one
 * weight the client named. Nothing retroactive: existing subtasks keep the
 * points they were created with, and point_transactions already paid are never
 * recomputed (F18 — the ledger keeps what it paid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->decimal('default_points', 5, 2)
                ->default(1.00)
                ->after('needs_approval');
        });

        DB::table('ticket_types')->where('key', 'feature')->update(['default_points' => 2.00]);

        $this->bustCache();
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('default_points');
        });

        $this->bustCache();
    }

    /**
     * TicketTypeDefinition::map() is a rememberForever cache, so a deploy that
     * only ran `migrate` would keep serving rows selected BEFORE this column
     * existed — every type would silently read the 1.0 fallback until someone
     * happened to edit one. Verified locally: feature read 2.00 in the column
     * and 1.0 through the cast until the cache was cleared.
     */
    private function bustCache(): void
    {
        Cache::forget(TicketTypeDefinition::CACHE_KEY);
        \App\Casts\TicketTypeValue::forget();
    }
};

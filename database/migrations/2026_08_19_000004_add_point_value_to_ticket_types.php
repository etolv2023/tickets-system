<?php

use App\Models\TicketTypeDefinition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-08-19) F18.3 — what a point is worth, in money, per ticket type.
 *
 * F18 has always paid in points, and points were deliberately unitless: the
 * ledger records that someone earned 2, and a human decided later what 2 was
 * worth. That decision was made off-system, in a spreadsheet, once a month.
 *
 * This column brings the conversion in. `point_value` is the money one point
 * earns on a ticket of this type — so a point on a فيتشر can be worth more than
 * a point on a بج without anyone touching how many points either awards.
 *
 * Two separate knobs, and keeping them separate is the point:
 *   `default_points`  how much work this type is — set by whoever plans.
 *   `point_value`     what that work pays — set by whoever pays.
 * Collapsing them into one number would mean a manager could not reweight the
 * effort of a type without also changing everybody's salary.
 *
 * Default 0.00, NOT a guessed rate. A zero reads on the money report as "nobody
 * has priced this type yet", which is true on the day this runs; any non-zero
 * default would invent a payroll figure and print it next to real names.
 *
 * Nothing retroactive and nothing stored: the ledger keeps points, exactly as
 * F18 requires, and money is computed at report time from the price standing
 * today. Repricing a type therefore reprices its history — which is the correct
 * behaviour for a rate card, and the reason no money figure is ever written
 * into point_transactions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            // 10,2 rather than 5,2 like default_points: that one counts effort
            // and never needs three digits, this one is currency.
            $table->decimal('point_value', 10, 2)
                ->default(0.00)
                ->after('default_points');
        });

        $this->bustCache();
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('point_value');
        });

        $this->bustCache();
    }

    /** @see the 2026-08-02 default_points migration — same rememberForever trap. */
    private function bustCache(): void
    {
        Cache::forget(TicketTypeDefinition::CACHE_KEY);
        \App\Casts\TicketTypeValue::forget();
    }
};

<?php

use App\Models\TicketTypeDefinition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ★ (2026-08-19) F26 — the «اكسبشن» type.
 *
 * A ticket opened by the error reporter is not a بج somebody wrote up; nobody
 * chose it, nobody phrased it, and it arrives at whatever hour the server had
 * its bad moment. Giving it its own type is what lets every screen tell the two
 * apart — and, more to the point, lets the points report and the money report
 * price it on its own, which is the whole reason the client asked for a type
 * filter there.
 *
 * `is_system` because code depends on this key existing: the intake looks the
 * type up by name and the tickets.type foreign key would reject a ticket whose
 * type had been deleted underneath it. An admin can still rename it, recolour
 * it and reweight it — only deletion is refused.
 *
 * needs_approval = false, deliberately. F15's approval gate exists so nobody
 * builds a feature the business didn't ask for. An exception already happened;
 * there is nothing to approve, and waiting for an admin to bless it would burn
 * the four-hour deadline before anyone was even assigned.
 *
 * default_points = 1.00, the same as بج. An exception is a defect, and pricing
 * it above ordinary defect work at seed time would be inventing a policy the
 * client hasn't set — the number is an admin setting from the moment this runs.
 */
return new class extends Migration
{
    private const KEY = 'exception';

    public function up(): void
    {
        // updateOrInsert, not insert: this is a system row, and a re-run (or a
        // database where somebody already added the key by hand) must not die
        // on the unique index.
        DB::table('ticket_types')->updateOrInsert(
            ['key' => self::KEY],
            [
                'name_ar' => 'اكسبشن',
                // Red is the incident colour the badge palette already uses for
                // بج and «بتبلوك» — an error is the same family of bad news.
                'color' => 'red',
                'icon' => 'alert',
                'needs_approval' => false,
                'default_points' => 1.00,
                'is_system' => true,
                'position' => (int) DB::table('ticket_types')->max('position') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->bustCache();
    }

    public function down(): void
    {
        // Only ever removes the row when nothing points at it. tickets.type is
        // a foreign key, so deleting a type that real tickets carry would throw
        // — and a rollback that destroys tickets to free a lookup row is worse
        // than a rollback that leaves one row behind.
        if (! DB::table('tickets')->where('type', self::KEY)->exists()) {
            DB::table('ticket_types')->where('key', self::KEY)->delete();
        }

        $this->bustCache();
    }

    /**
     * map() is rememberForever, so a deploy that only ran `migrate` would keep
     * serving the type list as it was before this row existed — and the intake
     * would fail to find its own type. Same trap the default_points migration
     * documented on 2026-08-02.
     */
    private function bustCache(): void
    {
        Cache::forget(TicketTypeDefinition::CACHE_KEY);
        \App\Casts\TicketTypeValue::forget();
    }
};

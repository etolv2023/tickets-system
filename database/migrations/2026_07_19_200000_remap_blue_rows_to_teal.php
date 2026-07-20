<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Redesign (2026-07-19): blue became the interaction colour and left the
 * semantic palette — anything blue is now a control, never a state. The rows
 * that carried blue (in_progress, testing, medium, plus any admin-made status
 * or label) move to teal: 40° away from the interaction hue where cyan is
 * only 15°, so a badge can never be mistaken for a button.
 *
 * 'blue' was removed from Label::COLORS at the same time, which closes the
 * admin forms; this migration closes the data.
 */
return new class extends Migration
{
    /** Tables carrying a user-editable colour column. */
    private const TABLES = ['ticket_statuses', 'priorities', 'labels'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->where('color', 'blue')->update(['color' => 'teal']);
        }

        // Statuses and priorities are cached forever and busted only by model
        // events (§ 4.7) — a DB::table() update fires none, so without this
        // the UI keeps serving blue badges from cache until someone clears it
        // by hand. Literal keys rather than the model constants: a migration
        // must not depend on app classes that may change after it ships.
        Cache::forget('ticket_statuses.map');
        Cache::forget('ticket_statuses.map.transitions');
        Cache::forget('priorities.map');
    }

    public function down(): void
    {
        // Lossy on purpose — a teal row cannot know whether it was born teal
        // or remapped from blue, so there is nothing safe to restore.
    }
};

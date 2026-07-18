<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The colour palette went from ten role-named values (urgent, bug, feature...)
 * to hue-named ones (red, rose, orange, amber, ... slate).
 *
 * Statuses, priorities and labels store that value per row, and admins may have
 * created their own — so the old values have to be translated in place rather
 * than reseeded. The map goes by what each old key actually looked like on
 * screen, not by what it was named: 'bug' rendered pink, so it becomes rose.
 */
return new class extends Migration
{
    /** @var array<string, string> old colour key => new hue */
    private const MAP = [
        'urgent' => 'red',
        'high' => 'orange',
        'medium' => 'amber',
        'low' => 'slate',
        'neutral' => 'slate',
        'resolved' => 'green',
        'blocked' => 'brown',
        'bug' => 'rose',
        'inquiry' => 'blue',
        'feature' => 'violet',
        'module' => 'teal',
    ];

    /** Tables carrying a user-editable colour column. */
    private const TABLES = ['ticket_statuses', 'priorities', 'labels'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            foreach (self::MAP as $old => $new) {
                DB::table($table)->where('color', $old)->update(['color' => $new]);
            }
        }
    }

    public function down(): void
    {
        // The map is lossy — five hues cannot resolve back to ten role names.
        // Rows keep their hue; the palette is simply the smaller one again.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-08-05) Removes worklog.completion.optional, one migration after it
 * was added.
 *
 * It was the right mechanism for the wrong shape of question. A permission is
 * held or not held, so it could only ever say "this person never has to press
 * «خلصت»" — while what was actually wanted was "…on the tickets he shares with
 * these particular people". That lives in worklog_completion_waivers, which can
 * express both the narrow case and the blanket one (a NULL counterpart).
 *
 * Leaving the key in place as a second way to say the same thing is worse than
 * removing it: two sources for one rule, and the permission would silently
 * outrank the table for anyone who still held it.
 *
 * Any grant is deleted with it. Nothing is lost that the waivers screen cannot
 * express — a person who held the permission is a person who wants an
 * "everyone" waiver, and that is one row.
 */
return new class extends Migration
{
    private const KEY = 'worklog.completion.optional';

    public function up(): void
    {
        $id = DB::table('permissions')->where('key', self::KEY)->value('id');

        if ($id === null) {
            return;
        }

        // ★ Carry the intent across rather than dropping it on the floor: anyone
        // who was granted the old permission becomes an "everyone" waiver, which
        // is exactly what they had. Only real grants (granted = 1) — a row with
        // granted = 0 was a REVOKE and must not become a waiver.
        if (Schema::hasTable('worklog_completion_waivers')) {
            $holders = DB::table('permission_user')
                ->where('permission_id', $id)
                ->where('granted', true)
                ->pluck('user_id');

            foreach ($holders as $userId) {
                DB::table('worklog_completion_waivers')->updateOrInsert(
                    ['user_id' => $userId, 'counterpart_user_id' => null],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }

        DB::table('permission_user')->where('permission_id', $id)->delete();
        DB::table('permission_role')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();
    }

    public function down(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => self::KEY],
            [
                'group' => 'workflow',
                'name_ar' => 'مش ملزم يضغط «خلصت» لإقفال التذكرة',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};

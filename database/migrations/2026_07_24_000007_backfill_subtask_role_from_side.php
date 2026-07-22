<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ★ (2026-07-24) Subtask categorisation goes fully role-based.
 *
 * A subtask used to carry a hardcoded `side` (SubtaskSide: frontend/backend/qa/
 * support/devops/other) alongside the newer, dynamic role_id. Now the role — a
 * row in `roles`, chosen by the admin — is the categorisation, so this stamps
 * role_id from `side` for every existing subtask that hasn't got one, mapping
 * each fixed side to the matching built-in role.
 *
 * `other` maps to nothing: it always meant "uncategorised", which stays
 * role_id = null. The `side` column is left in place (points still fall back to
 * it for any legacy row this couldn't map) but no screen writes or reads it any
 * more — the UI is role-based from here.
 *
 * Money-neutral: role_id and side both attribute a subtask to the same person
 * for the same points; the point engine already pays a role_id subtask by its
 * role. Only rows with no role_id yet are touched, so nothing is re-pointed.
 */
return new class extends Migration
{
    /** subtask side => the role key it maps to */
    private const SIDE_ROLE = [
        'frontend' => 'frontend',
        'backend' => 'backend',
        'qa' => 'tester',
        'support' => 'support',
        'devops' => 'devops',
    ];

    public function up(): void
    {
        foreach (self::SIDE_ROLE as $side => $roleKey) {
            $roleId = DB::table('roles')->where('key', $roleKey)->value('id');
            if ($roleId === null) {
                continue;
            }

            DB::table('ticket_subtasks')
                ->where('side', $side)
                ->whereNull('role_id')
                ->update(['role_id' => $roleId]);
        }
    }

    /**
     * Only clears the role_id this migration set — a role_id that still matches
     * its side's mapping. A subtask whose role was chosen by hand (its role
     * doesn't match its side) is left alone.
     */
    public function down(): void
    {
        foreach (self::SIDE_ROLE as $side => $roleKey) {
            $roleId = DB::table('roles')->where('key', $roleKey)->value('id');
            if ($roleId === null) {
                continue;
            }

            DB::table('ticket_subtasks')
                ->where('side', $side)
                ->where('role_id', $roleId)
                ->update(['role_id' => null]);
        }
    }
};

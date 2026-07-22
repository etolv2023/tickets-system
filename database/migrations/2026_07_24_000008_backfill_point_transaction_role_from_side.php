<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ★ (2026-07-24) The points ledger's `side` filter goes role-based.
 *
 * A point_transactions row carried a hardcoded PointSide `side` (support/
 * frontend/backend/tester/devops). Role-based awards already write role_id
 * instead; this stamps role_id onto every legacy row that still only has a
 * side, mapping each side to the role of the same key, so the points-detail
 * report and the corrections screen can filter by role across old and new data
 * alike.
 *
 * Strictly additive and money-neutral: only role_id is set, and only on rows
 * that don't have one yet. The user, the points, the period and the `side`
 * itself are untouched — nothing is re-pointed, and the `side` column stays as
 * a display fallback. PointSide side values map 1:1 to the built-in role keys.
 */
return new class extends Migration
{
    private const SIDE_KEYS = ['support', 'frontend', 'backend', 'tester', 'devops'];

    public function up(): void
    {
        foreach (self::SIDE_KEYS as $key) {
            $roleId = DB::table('roles')->where('key', $key)->value('id');
            if ($roleId === null) {
                continue;
            }

            DB::table('point_transactions')
                ->where('side', $key)
                ->whereNull('role_id')
                ->update(['role_id' => $roleId]);
        }
    }

    /** Clears only the role_id this migration set — a role that still matches its side. */
    public function down(): void
    {
        foreach (self::SIDE_KEYS as $key) {
            $roleId = DB::table('roles')->where('key', $key)->value('id');
            if ($roleId === null) {
                continue;
            }

            DB::table('point_transactions')
                ->where('side', $key)
                ->where('role_id', $roleId)
                ->update(['role_id' => null]);
        }
    }
};

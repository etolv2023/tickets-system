<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ★ (2026-07-24) Phase 1 of role-based distribution — data backfill.
 *
 * Copies every existing assignment held in the four fixed columns into the
 * generic ticket_role_assignments table, and stamps each existing work log with
 * the role that matches its WorkSide. After this runs, the role-based tables
 * describe exactly what the columns did — so the later phases can switch the
 * read/write paths over without losing a single existing assignment.
 *
 * Additive and idempotent: insertOrIgnore respects the unique(ticket_id,role_id)
 * key, so re-running (or running after new role-based assignments already exist)
 * never duplicates or clobbers.
 */
return new class extends Migration
{
    /** column on tickets => role key it maps to */
    private const COLUMN_ROLE = [
        'assigned_frontend_id' => 'frontend',
        'assigned_backend_id' => 'backend',
        'tester_id' => 'tester',
        'devops_id' => 'devops',
    ];

    public function up(): void
    {
        $roleIds = DB::table('roles')->pluck('id', 'key');
        $now = now();

        foreach (self::COLUMN_ROLE as $column => $roleKey) {
            $roleId = $roleIds[$roleKey] ?? null;
            if ($roleId === null) {
                continue; // role was deleted — nothing to map onto
            }

            $rows = DB::table('tickets')
                ->whereNotNull($column)
                ->pluck($column, 'id'); // ticket_id => user_id

            $insert = [];
            foreach ($rows as $ticketId => $userId) {
                $insert[] = [
                    'ticket_id' => $ticketId,
                    'role_id' => $roleId,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($insert, 500) as $chunk) {
                DB::table('ticket_role_assignments')->insertOrIgnore($chunk);
            }
        }

        // Stamp existing work logs with the role behind their WorkSide.
        foreach (['frontend', 'backend'] as $side) {
            $roleId = $roleIds[$side] ?? null;
            if ($roleId === null) {
                continue;
            }

            DB::table('ticket_work_logs')
                ->where('side', $side)
                ->whereNull('role_id')
                ->update(['role_id' => $roleId]);
        }
    }

    /**
     * The backfill only ever ADDED rows derived from data that still exists in
     * the four columns and the work logs' `side`. Reversing it means clearing
     * exactly what it wrote: the role_id stamps, and the role assignments that
     * mirror a fixed column. Role assignments created independently (a custom
     * role dropdown) don't match any of the four keys, so they're left alone.
     */
    public function down(): void
    {
        $roleIds = DB::table('roles')->pluck('id', 'key');

        DB::table('ticket_work_logs')->update(['role_id' => null]);

        $columnRoleIds = collect(self::COLUMN_ROLE)
            ->map(fn ($key) => $roleIds[$key] ?? null)
            ->filter()
            ->values()
            ->all();

        if ($columnRoleIds !== []) {
            DB::table('ticket_role_assignments')
                ->whereIn('role_id', $columnRoleIds)
                ->delete();
        }
    }
};
